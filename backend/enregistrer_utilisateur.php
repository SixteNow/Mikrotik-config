<?php
    //JSON headers
    header('Content-Type: application/json');
    header("Access-Control-Allow-Origin: *");
    
    if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
        http_response_code(200);
        exit;
    }
    
    //Connexion bdd
    $servername = "[ADRESSE_SERVEUR_DB]";
    $username = "[UTILISATEUR_DB]";
    $password = "[MOT_DE_PASSE_DB]";
    $dbname = "[NOM_DE_VOTRE_BASE_DE_DONNEES]";

    //PDO
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }

    //Récupérer les données JSON.stringify
    
    $data=json_decode(file_get_contents('php://input'), true);
    $fullname=$data['fullname'];
    $username=$data['username'];
    $contact=$data['contact'];
    $user_agent=$data['user_agent'];
    $ip_address=$_SERVER['HTTP_X_FORWARDED_FOR']
   ?? $_SERVER['HTTP_CLIENT_IP']
   ?? $_SERVER['REMOTE_ADDR']
   ?? null;
   $mac_address = $_GET['mac'] ?? null; // MikroTik passe ?mac=XX:XX:XX:XX:XX:XX dans l'URL


    //Enregistrer les données dans la table ctnwifi
    $sql = "INSERT INTO ctnwifi (fullname, username, contact, user_agent, ip_address, mac_address) VALUES (:fullname, :username, :contact, :user_agent, :ip_address, :mac_address)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':fullname', $fullname);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':contact', $contact);
    $stmt->bindParam(':user_agent', $user_agent);
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->bindParam(':mac_address', $mac_address);
    
    if ($stmt->execute()){
        echo json_encode(array("success" => true, "message" => "Utilisateur enregistré avec succès"));
    }else{
        echo json_encode(array("success" => false, "message" => "Erreur lors de l'enregistrement"));
    }

    $conn = null;

?>
    

    
    