<?php
session_start();
// Vérification de la connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Connexion à la base de données
require_once('config.php');

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Variables pour les messages
$success_message = "";
$error_message = "";

// Gérer la mise à jour des informations utilisateur
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    // Récupération et nettoyage des données
    $new_name = htmlspecialchars(trim($_POST['nom']));
    $new_prenom = htmlspecialchars(trim($_POST['prenom']));
    $new_email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    // Validation des données
    $valid = true;
    
    // Validation de l'email
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format d'email invalide.";
        $valid = false;
    }
    
    // Si toutes les validations sont passées
    if ($valid) {
        // Préparer la requête
        $query = "UPDATE users SET nom = ?, prenom = ?, email = ? WHERE id = ?";
        $params = [$new_name, $new_prenom, $new_email, $user_id];
        $types = "sssi";
        
        // Exécuter la requête
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success_message = "Informations mises à jour avec succès.";
            
            // Mettre à jour les données de session
            $_SESSION['nom'] = $new_name;
            $_SESSION['prenom'] = $new_prenom;
            
            // Rafraîchir les données utilisateur
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error_message = "Erreur lors de la mise à jour: " . $conn->error;
        }
    }
}

// Déterminer la page de retour en fonction du rôle
$return_page = "index.php";
if ($user['role'] === 'admin') {
    $return_page = "admin.php";
} elseif ($user['role'] === 'chef_departement') {
    $return_page = "chef_departement.php";
} elseif ($user['role'] === 'enseignant') {
    $return_page = "enseignant.php";
} elseif ($user['role'] === 'vacataire') {
    $return_page = "vacataire.php";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres du compte</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a86ff;
            --primary-dark: #1e60d0;
            --primary-light: #e3f2fd;
            --secondary-color: #1e3152;
            --accent-color: #4cc9f0;
            --success-color: #4CAF50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --text-color: #333;
            --light-text: #6c757d;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --border-radius: 10px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            margin: 0;
            padding: 0;
            color: var(--text-color);
        }

        .header {
            background-color: var(--primary-color);
            padding: 30px;
            color: var(--white);
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-text {
            text-align: center;
            flex-grow: 1;
        }

        .header-text h1 {
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: bold;
        }

        .header-text p {
            margin-top: 5px;
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
        }

        .btn-retour {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-retour:hover {
            background-color: white;
            color: var(--primary-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto 40px;
            padding: 0 20px;
        }

        .sections-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .section-left {
            flex: 3;
        }

        .section-right {
            flex: 2;
        }

        .card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 20px;
            height: 100%;
        }

        .card-header {
            background-color: var(--primary-light);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 18px;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary-color);
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.2);
        }

        .form-control:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .btn {
            display: inline-block;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 12px 20px;
            font-size: 16px;
            line-height: 1.5;
            border-radius: 5px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }

        .btn-primary {
            color: var(--white);
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            color: var(--text-color);
            background-color: var(--light-bg);
            border-color: var(--border-color);
        }

        .btn-secondary:hover {
            background-color: #e2e6ea;
            border-color: #dae0e5;
        }

        .btn-danger {
            color: var(--white);
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 5px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: var(--light-bg);
            border-radius: var(--border-radius);
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-right: 20px;
        }

        .user-details {
            flex-grow: 1;
        }

        .user-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-role {
            display: inline-block;
            padding: 5px 10px;
            background-color: var(--primary-light);
            color: var(--primary-dark);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .user-email {
            color: var(--light-text);
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-buttons .btn {
            flex: 1;
        }

        .quick-links {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: var(--light-bg);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .quick-link:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .quick-link i {
            font-size: 24px;
            margin-right: 15px;
            color: var(--primary-color);
        }

        .quick-link-text {
            font-weight: 500;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: var(--light-text);
            font-size: 14px;
            border-top: 1px solid var(--border-color);
            margin-top: 40px;
        }

        @media (max-width: 992px) {
            .sections-container {
                flex-direction: column;
            }
            
            .section-left, .section-right {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .header-text h1 {
                font-size: 24px;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .user-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="<?php echo $return_page; ?>" class="btn-retour">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            
            <div class="header-text">
                <h1>Paramètres du compte</h1>
                <p>Gérez vos informations personnelles</p>
            </div>
            
            <div style="width: 100px;"></div> <!-- Élément vide pour équilibrer le header -->
        </div>
    </div>

    <div class="container">
        <!-- Messages d'alerte -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Conteneur pour les deux sections côte à côte -->
        <div class="sections-container">
            <!-- Section gauche: Informations utilisateur -->
            <div class="section-left">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-circle"></i> Profil utilisateur</h2>
                    </div>
                    <div class="card-body">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <div class="user-name"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></div>
                                <div class="user-role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                        </div>

                        <form method="POST" action="" id="profileForm">
                            <input type="hidden" name="action" value="update">
                            <div class="form-group">
                                <label for="nom">Nom</label>
                                <input type="text" id="nom" name="nom" class="form-control" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="prenom">Prénom</label>
                                <input type="text" id="prenom" name="prenom" class="form-control" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="role">Rôle</label>
                                <input type="text" id="role" class="form-control" value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" disabled>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Mettre à jour
                                </button>
                                <button type="button" class="btn btn-secondary" id="resetBtn">
                                    <i class="fas fa-undo"></i> Réinitialiser
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Section droite: Accès rapides -->
            <div class="section-right">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-bolt"></i> Accès rapides</h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-links">
                            <a href="changerpass.php" class="quick-link">
                                <i class="fas fa-key"></i>
                                <span class="quick-link-text">Changer mon mot de passe</span>
                            </a>
                            
                            <a href="profil.php" class="quick-link">
                                <i class="fas fa-id-card"></i>
                                <span class="quick-link-text">Voir mon profil</span>
                            </a>
                            
                            <a href="<?php echo $return_page; ?>" class="quick-link">
                                <i class="fas fa-home"></i>
                                <span class="quick-link-text">Retour à l'accueil</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Stocker les valeurs originales pour la réinitialisation
        const originalValues = {
            nom: document.getElementById('nom').value,
            prenom: document.getElementById('prenom').value,
            email: document.getElementById('email').value
        };

        // Ajouter un écouteur d'événement pour le bouton Réinitialiser
        document.getElementById('resetBtn').addEventListener('click', function() {
            document.getElementById('nom').value = originalValues.nom;
            document.getElementById('prenom').value = originalValues.prenom;
            document.getElementById('email').value = originalValues.email;
        });
    </script>
</body>
</html>