<?php
/**
 * Script de synchronisation MikroTik Hotspot <-> Base de données MySQL
 * Gère les déconnexions automatiques et les reconnexions (mises à jour)
 */

// --- CONFIGURATION ---
$routerIP = '[ADRESSE_IP_DU_ROUTEUR]';
$apiPort  = 8728;
$apiUser  = '[NOM_UTILISATEUR_API_MIKROTIK]';
$apiPass  = '[MOT_DE_PASSE_API_MIKROTIK]';

// Configuration Database
$dbHost = '[ADRESSE_SERVEUR_DB]';
$dbName = '[NOM_DE_VOTRE_BASE_DE_DONNEES]';
$dbUser = '[UTILISATEUR_DB]';
$dbPass = '[MOT_DE_PASSE_DB]';

try {
    $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", "$dbUser", "$dbPass");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion DB : " . $e->getMessage());
}

// 1. Récupérer les sessions actives sur MikroTik
$activeSessions = getMikrotikActiveSessions($routerIP, $apiPort, $apiUser, $apiPass);
$activeMACs = array_keys($activeSessions);

// 2. Récupérer les utilisateurs considérés comme "En ligne" en base de données
$stmt = $conn->query("SELECT id, mac_address, username FROM ctnwifi WHERE logout_time IS NULL");
$loggedInUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$loggedInMACs = array_column($loggedInUsers, 'mac_address');

echo "--- Analyse en cours (" . date('Y-m-d H:i:s') . ") ---\n";

// 3. GÉRER LES DÉCONNEXIONS
// Si présent en DB mais absent du MikroTik
foreach ($loggedInUsers as $user) {
    // Ta correction : on vérifie que la MAC existe et n'est plus active
    if (!in_array($user['mac_address'], $activeMACs) && $user['mac_address'] != null) {
        $update = $conn->prepare("UPDATE ctnwifi SET logout_time = NOW() WHERE id = :id");
        $update->execute([':id' => $user['id']]);
        echo "[Déconnexion] ID: {$user['id']} | User: {$user['username']} | MAC: {$user['mac_address']}\n";
    }
}

// 4. GÉRER LES RECONNEXIONS (Mise à jour par Username)
// Si présent sur MikroTik mais marqué déconnecté en DB
foreach ($activeSessions as $mac => $data) {
    if (!in_array($mac, $loggedInMACs)) {
        // On met à jour la dernière session connue de cet utilisateur
        $updateReconnexion = $conn->prepare("
            UPDATE ctnwifi 
            SET logout_time = NULL, 
                mac_address = :mac, 
                ip_address = :ip, 
                login_time = NOW() 
            WHERE username = :user 
            ORDER BY id DESC LIMIT 1
        ");
        
        $updateReconnexion->execute([
            ':mac'  => $mac,
            ':ip'   => $data['ip'],
            ':user' => $data['user']
        ]);
        
        if ($updateReconnexion->rowCount() > 0) {
            echo "[Reconnexion] User: {$data['user']} | MAC: $mac | IP: {$data['ip']}\n";
        }
    }
}

echo "--- Fin du traitement ---\n\n";

// --- FONCTIONS API MIKROTIK CORRIGÉES ---

function getMikrotikActiveSessions($ip, $port, $user, $pass) {
    $socket = @fsockopen($ip, $port, $errno, $errstr, 5);
    if (!$socket) {
        echo "Impossible de se connecter au routeur : $errstr\n";
        return [];
    }

    // Login (Protocole robuste pour v6.43+ et v7)
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, '=name=' . $user);
    mikrotikWrite($socket, '=password=' . $pass);
    mikrotikWrite($socket, '', true);
    
    $response = mikrotikRead($socket);
    if (isset($response[0]['!trap'])) {
        echo "Erreur d'authentification MikroTik.\n";
        return [];
    }

    // Récupération des données Hotspot
    mikrotikWrite($socket, '/ip/hotspot/active/print');
    mikrotikWrite($socket, '', true);
    $data = mikrotikRead($socket);
    fclose($socket);

    $sessions = [];
    foreach ($data as $row) {
        if (isset($row['!re']) && isset($row['mac-address'])) {
            $mac = strtolower($row['mac-address']);
            $sessions[$mac] = [
                'user' => $row['user'] ?? 'inconnu',
                'ip'   => $row['address'] ?? ''
            ];
        }
    }
    return $sessions;
}

function mikrotikWrite($socket, $word, $end = false) {
    if ($word !== '') {
        $length = strlen($word);
        if ($length < 0x80) {
            fwrite($socket, chr($length));
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($socket, chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
        }
        fwrite($socket, $word);
    }
    if ($end) fwrite($socket, chr(0));
}

function mikrotikRead($socket) {
    $responses = [];
    $current = [];
    while (true) {
        $byte = ord(fread($socket, 1));
        if ($byte & 0x80) {
            if (($byte & 0xC0) == 0x80) {
                $length = (($byte & 0x3F) << 8) + ord(fread($socket, 1));
            } else { break; }
        } else { $length = $byte; }

        if ($length == 0) {
            if (!empty($current)) $responses[] = $current;
            if (isset($current['!done'])) break;
            $current = [];
            continue;
        }

        $word = "";
        while (strlen($word) < $length) {
            $word .= fread($socket, $length - strlen($word));
        }

        if (strpos($word, '!') === 0) {
            $current[$word] = true;
        } elseif (strpos($word, '=') !== false) {
            $parts = explode('=', $word, 3);
            if (count($parts) >= 3) $current[$parts[1]] = $parts[2];
        }
    }
    return $responses;
}
?>