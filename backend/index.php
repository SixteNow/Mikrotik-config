<?php
session_start();

// Configuration de la base de données
$servername = "[ADRESSE_SERVEUR_DB]";
$username_db = "[UTILISATEUR_DB]";
$password_db = "[MOT_DE_PASSE_DB]";
$dbname = "[NOM_DE_VOTRE_BASE_DE_DONNEES]";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$error = "";
$success = "";

// Traitement de la déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Traitement de la connexion
if (isset($_POST['login'])) {
    $pseudo = $_POST['pseudo'];
    $mdp = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin WHERE pseudo = :pseudo");
    $stmt->bindParam(':pseudo', $pseudo);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($mdp, $admin['motdepasse'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_pseudo'] = $admin['pseudo'];
    } else {
        $error = "Identifiants incorrects.";
    }
}

// Traitement du changement de mot de passe
if (isset($_POST['change_password']) && isset($_SESSION['admin_logged_in'])) {
    $new_mdp = $_POST['new_password'];
    $confirm_mdp = $_POST['confirm_password'];

    if ($new_mdp === $confirm_mdp) {
        $hash = password_hash($new_mdp, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin SET motdepasse = :mdp WHERE pseudo = :pseudo");
        $stmt->bindParam(':mdp', $hash);
        $stmt->bindParam(':pseudo', $_SESSION['admin_pseudo']);
        if ($stmt->execute()) {
            $success = "Mot de passe mis à jour avec succès.";
        } else {
            $error = "Erreur lors de la mise à jour.";
        }
    } else {
        $error = "Les mots de passe ne correspondent pas.";
    }
}

// Pagination Logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$connections = [];
$total_pages = 0;

if (isset($_SESSION['admin_logged_in'])) {
    // Count total
    $count_stmt = $conn->query("SELECT COUNT(*) FROM ctnwifi");
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch records
    $stmt = $conn->prepare("SELECT * FROM ctnwifi ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - [NOM_DE_VOTRE_RESEAU_WIFI]</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --background: #ffffff;
            --foreground: #09090b;
            --card: #ffffff;
            --card-foreground: #09090b;
            --popover: #ffffff;
            --popover-foreground: #09090b;
            --primary: #18181b;
            --primary-foreground: #fafafa;
            --secondary: #f4f4f5;
            --secondary-foreground: #18181b;
            --muted: #f4f4f5;
            --muted-foreground: #71717a;
            --accent: #f4f4f5;
            --accent-foreground: #18181b;
            --destructive: #ef4444;
            --destructive-foreground: #fafafa;
            --border: #e4e4e7;
            --input: #e4e4e7;
            --ring: #18181b;
            --radius: 0.5rem;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--foreground);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-y: auto; /* Fix frozen scroll */
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Card Style */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        /* Forms */
        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            font-size: 14px;
            border: 1px solid var(--input);
            border-radius: var(--radius);
            background: var(--background);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--ring);
            box-shadow: 0 0 0 2px rgba(24, 24, 27, 0.1);
        }

        button, .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 500;
            border-radius: var(--radius);
            cursor: pointer;
            transition: opacity 0.2s;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--primary-foreground);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: var(--secondary-foreground);
            border: 1px solid var(--border);
            text-decoration: none;
        }

        .btn-destructive {
            background-color: var(--destructive);
            color: var(--destructive-foreground);
            text-decoration: none;
        }

        button:hover, .btn:hover {
            opacity: 0.9;
        }

        /* Table */
        .table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background-color: var(--muted);
            color: var(--muted-foreground);
            font-weight: 500;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
        }

        .pagination-info {
            font-size: 14px;
            color: var(--muted-foreground);
        }

        .pagination-btns {
            display: flex;
            gap: 8px;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--foreground);
            transition: background 0.2s;
        }

        .page-link:hover {
            background: var(--secondary);
        }

        .page-link.active {
            background: var(--primary);
            color: var(--primary-foreground);
            border-color: var(--primary);
        }

        .page-link.disabled {
            pointer-events: none;
            opacity: 0.5;
        }

        .login-card {
            max-width: 400px;
            margin: 100px auto;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!isset($_SESSION['admin_logged_in'])): ?>
        <div class="card login-card">
            <h1>Connexion</h1>
            <p style="color: var(--muted-foreground); font-size: 14px; margin-bottom: 24px;">Entrez vos identifiants pour accéder à l'administration.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="pseudo">Pseudo</label>
                    <input type="text" name="pseudo" id="pseudo" placeholder="admin" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit" name="login" class="btn-primary" style="width: 100%;">Se connecter</button>
            </form>
        </div>
    <?php else: ?>
        <div class="header">
            <div>
                <h1>Administration</h1>
                <p style="color: var(--muted-foreground); font-size: 14px;">Bienvenue, <?php echo htmlspecialchars($_SESSION['admin_pseudo']); ?></p>
            </div>
            <a href="?logout=1" class="btn btn-destructive">Déconnexion</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Dernières Connexions</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nom Complet</th>
                            <th>Code</th>
                            <th>Contact</th>
                            <th>Navigateur</th>
                            <th>IP</th>
                            <th>Adresse MAC</th>
                            <th>Date et heure de connexion</th>
                            <th>Date et heure de deconnexion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($connections)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--muted-foreground);">Aucune donnée pour le moment.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($connections as $row): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($row['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact']); ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($row['user_agent']); ?>">
                                        <?php echo htmlspecialchars($row['user_agent']); ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($row['ip_address']); ?></code></td>
                                    <td><code><?php echo htmlspecialchars($row['mac_address']); ?></code></td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($row['logout_time'])==null?'En cours':htmlspecialchars($row['logout_time']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                    </div>
                    <div class="pagination-btns">
                        <a href="?page=<?php echo $page - 1; ?>" class="page-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>">Précédent</a>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                <span style="padding: 0 4px;">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <a href="?page=<?php echo $page + 1; ?>" class="page-link <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">Suivant</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 500px;">
            <h2>Sécurité</h2>
            <p style="color: var(--muted-foreground); font-size: 14px; margin-bottom: 20px;">Mettez à jour votre mot de passe pour plus de sécurité.</p>
            <form method="POST">
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmation</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-primary">Mettre à jour le mot de passe</button>
            </form>
        </div>

    <?php endif; ?>
</div>

</body>
</html>