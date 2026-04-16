<?php
    header('Content-Type: application/json');
    header("Access-Control-Allow-Origin: *");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    try {
        $conn = new PDO("mysql:host=[ADRESSE_SERVEUR_DB];dbname=[NOM_DE_VOTRE_BASE_DE_DONNEES]", "[UTILISATEUR_DB]", "[MOT_DE_PASSE_DB]");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit;
    }

    $data        = json_decode(file_get_contents('php://input'), true);
    $mac_address = $data['mac_address'] ?? null;
    $ip_address  = $data['ip_address']  ?? null;
    $username    = $data['username']    ?? null;

    if (!$mac_address || !$username) {
        echo json_encode(["success" => false, "message" => "Données manquantes"]);
        exit;
    }

    // Récupérer le dernier enregistrement de cet utilisateur
    $select = $conn->prepare("SELECT id FROM ctnwifi WHERE username = :username ORDER BY id DESC LIMIT 1");
    $select->bindParam(':username', $username);
    $select->execute();
    $row = $select->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["success" => false, "message" => "Utilisateur non trouvé"]);
        exit;
    }

    $id = $row['id'];

    $stmt = $conn->prepare("UPDATE ctnwifi SET mac_address = :mac_address, ip_address = :ip_address WHERE id = :id");
    $stmt->bindParam(':mac_address', $mac_address);
    $stmt->bindParam(':ip_address',  $ip_address);
    $stmt->bindParam(':id',          $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "id" => $id]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur UPDATE"]);
    }

    $conn = null;
?>