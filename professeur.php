<?php
session_start();
require_once 'config.php'; // Fichier de configuration avec la connexion à la BD

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id']) ){
    header("Location: login.php");
    exit();
}

// Récupération des informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['role'] !== 'professeur') {
    header("Location: unauthorized.php");
    exit();
}

// Récupération des statistiques pour le tableau de bord
// Modules assurés
$stmt = $pdo->prepare("SELECT COUNT(*) FROM affectations WHERE user_id = ? AND valide = 1");
$stmt->execute([$user_id]);
$modules_assures = $stmt->fetchColumn();

// Charge horaire totale
$stmt = $pdo->prepare("
    SELECT SUM(
        CASE WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
             WHEN a.type_enseignement = 'TD' THEN ue.volume_td
             WHEN a.type_enseignement = 'TP' THEN ue.volume_tp
        END
    ) as total_heures
    FROM affectations a
    JOIN unites_enseignement ue ON a.ue_id = ue.id
    WHERE a.user_id = ? AND a.valide = 1
");
$stmt->execute([$user_id]);
$charge_horaire = $stmt->fetchColumn();

// Notes uploadées
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT ue_id) FROM notes WHERE ens_id = ?");
$stmt->execute([$user_id]);
$notes_uploaded = $stmt->fetchColumn();

// Nombre total de modules avec notes à uploader
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.ue_id) 
    FROM affectations a
    WHERE a.user_id = ? AND a.valide = 1
");
$stmt->execute([$user_id]);
$total_modules = $stmt->fetchColumn();
$notes_restantes = $total_modules - $notes_uploaded;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Enseignant - Tableau de bord</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a86ff;
            --primary-dark: #1e60d0;
            --primary-light: #e3f2fd;
            --secondary-color: #1e3152;
            --accent-color: #4cc9f0;
            --text-color: #333;
            --light-text: #6c757d;
            --light-bg: #f0f4f8;
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --border-radius: 10px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 220px;
            height: 100%;
            background-color: var(--primary-color);
            color: white;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }

        .sidebar-logo {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--primary-color);
            font-size: 24px;
            font-weight: bold;
        }

        .app-title {
            font-size: 20px;
            font-weight: 600;
            margin-top: 10px;
            text-align: center;
        }

        .nav {
            list-style: none;
        }

        .nav-item {
            margin: 5px 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .nav-icon {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 220px;
            right: 0;
            height: 60px;
            background-color: white;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 900;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Main Content */
        .main-content {
            margin-left: 220px;
            padding: 80px 20px 20px;
        }

        .page-title {
            font-size: 28px;
            color: var(--secondary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Dashboard Cards */
        .dashboard-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            flex: 1;
            min-width: 250px;
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 24px;
            color: var(--primary-color);
            background-color: var(--primary-light);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .stat-title {
            color: var(--light-text);
            font-size: 14px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .stat-info {
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        /* Modules List */
        .modules-list {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
        }

        .modules-list h2 {
            margin-bottom: 15px;
            color: var(--secondary-color);
        }

        .module-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .module-item:last-child {
            border-bottom: none;
        }

        .module-name {
            font-weight: 500;
        }

        .module-hours {
            color: var(--light-text);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            .app-title, .nav-link span {
                display: none;
            }
            .navbar {
                left: 80px;
            }
            .main-content {
                margin-left: 80px;
            }
        }
         /* Styles pour la recherche */
    








    /* Styles pour la recherche */
.search-box {
    position: relative;
    width: 300px;
}

.search-input {
    width: 100%;
    padding: 8px 15px 8px 35px;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    outline: none;
    transition: all 0.2s;
    font-size: 14px;
}

.search-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.2);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--light-text);
}

/* Styles pour le menu utilisateur */
.user-details {
    display: flex;
    flex-direction: column;
    margin-left: 10px;
}

.user-role {
    font-size: 12px;
    color: var(--light-text);
}

.dropdown-arrow {
    margin-left: 10px;
    font-size: 12px;
    transition: transform 0.2s;
}

.user-menu {
    position: absolute;
    right: 20px;
    top: 70px;
    width: 280px;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    padding: 15px 0;
    display: none;
    z-index: 1000;
}

.user-menu.show {
    display: block;
}

.user-menu-header {
    display: flex;
    padding: 0 15px 15px;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.menu-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--primary-light);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 12px;
}

.menu-username {
    font-weight: 600;
    margin-bottom: 2px;
}

.menu-email {
    font-size: 12px;
    color: var(--light-text);
}

.user-menu-item {
    padding: 10px 20px;
    color: var(--text-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: background-color 0.2s;
}

.user-menu-item:hover {
    background-color: var(--light-bg);
}

.user-menu-item i {
    width: 20px;
    text-align: center;
}

.user-menu-divider {
    height: 1px;
    background-color: #eee;
    margin: 8px 0;
}

.logout {
    color: #dc3545;
}

/* Notification badge */
.badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #ff4757;
    color: white;
    font-size: 10px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-btn {
    position: relative;
    background: none;
    border: none;
    font-size: 16px;
    color: var(--secondary-color);
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
}

.notification-btn:hover {
    background-color: var(--light-bg);
}
/* Barre de navigation supérieure */
.navbar {
    position: fixed;
    top: 0;
    left: 220px;
    right: 0;
    height: 60px;
    background-color: white;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 900;
}

.navbar-left {
    display: flex;
    align-items: center;
}

.navbar-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.nav-btn {
    background: none;
    border: none;
    color: var(--secondary-color);
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    border-radius: 5px;
    transition: background-color 0.2s;
}

.nav-btn:hover {
    background-color: var(--light-bg);
}

.search-box {
    position: relative;
    width: 300px;
}

.search-input {
    width: 100%;
    padding: 8px 12px 8px 35px;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    outline: none;
    transition: all 0.2s;
}

.search-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.2);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--light-text);
}

.notification-btn {
    position: relative;
}

.badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #ff4757;
    color: white;
    font-size: 10px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: var(--primary-light);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-name {
    font-size: 14px;
    font-weight: 500;
}

/* Menu utilisateur */
.user-menu {
    position: absolute;
    right: 20px;
    top: 60px;
    width: 200px;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 10px 0;
    display: none;
    z-index: 1000;
}
/* Notification Modal Styles */
.notification-modal {
    display: none;
    position: fixed;
    top: 60px;
    right: 20px;
    width: 400px;
    max-height: 80vh;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    z-index: 1100;
    overflow: hidden;
}

.modal-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 0;
    overflow-y: auto;
    flex-grow: 1;
}

.notification-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: #f9f9f9;
}

.notification-item.unread {
    background-color: #f0f7ff;
}

.notification-title {
    font-weight: bold;
    margin-bottom: 5px;
}

.notification-message {
    color: #555;
    font-size: 14px;
}

.notification-time {
    font-size: 12px;
    color: #888;
    text-align: right;
    margin-top: 5px;
}

.loading {
    padding: 20px;
    text-align: center;
    color: #666;
}
.user-menu-item {
    padding: 10px 15px;
    color: var(--text-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background-color 0.2s;
}

.user-menu-item:hover {
    background-color: var(--light-bg);
}

.user-menu-item i {
    width: 20px;
    text-align: center;
}

.logout {
    color: #dc3545;
}

.user-menu-divider {
    height: 1px;
    background-color: #eee;
    margin: 5px 0;
}
:root {
    --primary-color: #3a86ff;
    --primary-dark: #1e60d0;
    --primary-light: #e3f2fd;
    --secondary-color: #1e3152;
    --accent-color: #4cc9f0;
    --text-color: #333;
    --light-text: #6c757d;
    --light-bg: #f0f4f8;
    --white: #ffffff;
    --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    --border-radius: 10px;
}
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">E</div>
            <div class="app-title">Espace Enseignant</div>
        </div>
        <ul class="nav">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="UE_coteprof.php" class="nav-link">
                    <i class="fas fa-book nav-icon"></i>
                    <span>Unités d'Enseignement</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="exprimer_souhaits.php" class="nav-link">
                    <i class="fas fa-bullseye nav-icon"></i>
                    <span>Expression des Souhaits</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="la_charge_horaire_proffesseur.php" class="nav-link">
                    <i class="fas fa-clock nav-icon"></i>
                    <span>Charge Horaire</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="module_assures_prof.php" class="nav-link">
                    <i class="fas fa-folder nav-icon"></i>
                    <span>Modules Assurés</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="Upload_Note_prof.php" class="nav-link">
                    <i class="fas fa-upload nav-icon"></i>
                    <span>Upload des Notes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="historique_professeur.php" class="nav-link">
                    <i class="fas fa-history nav-icon"></i>
                    <span>Historique</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Navbar -->
     <!-- Barre de navigation supérieure (navbar) mise à jour -->
<<!-- Barre de navigation supérieure (navbar) mise à jour -->
<!-- Navbar -->
<div class="navbar">
        <div class="navbar-left">
            <button class="nav-btn">
                <i class="fas fa-home"></i> Accueil
            </button>
        </div>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Rechercher...">
        </div>
        <div class="navbar-right">
            <button class="nav-btn notification-btn" onclick="toggleNotifications()">
    <i class="fas fa-bell"></i>
    <span class="badge">3</span>
</button>
            <button class="nav-btn notification-btn">
                <i class="fas fa-envelope"></i>
                <span class="badge">2</span>
            </button>
            <div class="user-info" onclick="toggleUserMenu()">
               <div class="user-avatar">NC</div>
                <div class="user-name"><?php echo $_SESSION['nom']?></div>
                <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                
            </div>
        </div>
    </div>

    <!-- User Menu -->
      <div class="user-menu" id="userMenu">
        <a href="profil.php" class="user-menu-item">
            <i class="fas fa-user"></i> Mon profil
        </a>
        <a href="parametre.php" class="user-menu-item">
            <i class="fas fa-cog"></i> Paramètres
        </a>
        <a href="chnager_motpass.php" class="user-menu-item">
            <i class="fas fa-key"></i> Changer mot de passe
        </a>
        <a href="ind.php" class="user-menu-item logout">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>

    <!-- Notification Modal -->
<div class="notification-modal" id="notificationModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mes Notifications</h3>
            <button class="close-btn" onclick="toggleNotifications()">&times;</button>
        </div>
        <div class="modal-body" id="notificationList">
            <!-- Les notifications seront chargées ici dynamiquement -->
            <div class="loading">Chargement des notifications...</div>
        </div>
    </div>
</div>
  

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="page-title">Tableau de bord</h1>

        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-title">Modules Assurés</div>
                <div class="stat-value"><?= $modules_assures ?></div>
                <div class="stat-info">
                    <i class="fas fa-arrows-alt-v"></i> Ce semestre
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #fff3cd; color: #997404;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-title">Heures d'enseignement</div>
                <div class="stat-value"><?= $charge_horaire ?>h</div>
                <div class="stat-info" style="color: #997404;">
                    <i class="fas fa-chart-line"></i> <?= round(($charge_horaire/192)*100) ?>% du service
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #d1fae5; color: #047857;">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-title">Notes Uploadées</div>
                <div class="stat-value"><?= $notes_uploaded ?>/<?= $total_modules ?></div>
                <div class="stat-info">
                    <?php if ($notes_restantes > 0): ?>
                        <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <?= $notes_restantes ?> restante(s)
                    <?php else: ?>
                        <i class="fas fa-check-circle"></i> Toutes à jour
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modules Assurés -->
        <div class="modules-list">
            <h2><i class="fas fa-folder"></i> Mes Modules Assurés</h2>
            <?php
            $stmt = $pdo->prepare("
                SELECT ue.code_ue, ue.nom_ue, a.type_enseignement, 
                       CASE WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
                            WHEN a.type_enseignement = 'TD' THEN ue.volume_td
                            WHEN a.type_enseignement = 'TP' THEN ue.volume_tp
                       END as volume
                FROM affectations a
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                WHERE a.user_id = ? AND a.valide = 1
                ORDER BY ue.code_ue
            ");
            $stmt->execute([$user_id]);
            $modules = $stmt->fetchAll();

            if (count($modules) > 0): ?>
                <?php foreach ($modules as $module): ?>
                    <div class="module-item">
                        <div class="module-name">
                            <?= $module['code_ue'] ?> - <?= $module['nom_ue'] ?> (<?= $module['type_enseignement'] ?>)
                        </div>
                        <div class="module-hours"><?= $module['volume'] ?>h</div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Aucun module affecté pour le moment.</p>
            <?php endif; ?>
        </div>

        <!-- Notes à uploader -->
        <div class="modules-list">
            <h2><i class="fas fa-upload"></i> Notes à Uploader</h2>
            <?php
            $stmt = $pdo->prepare("
                SELECT ue.code_ue, ue.nom_ue
                FROM affectations a
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                WHERE a.user_id = ? AND a.valide = 1
                AND a.ue_id NOT IN (SELECT ue_id FROM notes WHERE ens_id = ?)
                ORDER BY ue.code_ue
            ");
            $stmt->execute([$user_id, $user_id]);
            $notes_a_uploader = $stmt->fetchAll();

            if (count($notes_a_uploader) > 0): ?>
                <?php foreach ($notes_a_uploader as $module): ?>
                    <div class="module-item">
                        <div class="module-name">
                            <?= $module['code_ue'] ?> - <?= $module['nom_ue'] ?>
                        </div>
                        <a href="upload_notes.php?ue=<?= $module['code_ue'] ?>" class="btn-upload">
                            <i class="fas fa-upload"></i> Uploader
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Toutes vos notes sont à jour.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleUserMenu() {
            // Implémentation du menu utilisateur
            console.log("Menu utilisateur cliqué");
        }

    // Gestion du menu utilisateur
    const userInfoDropdown = document.getElementById('userInfoDropdown');
    const userMenu = document.getElementById('userMenu');
    
    userInfoDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
        userMenu.classList.toggle('show');
        document.querySelector('.dropdown-arrow').style.transform = userMenu.classList.contains('show') 
            ? 'rotate(180deg)' 
            : 'rotate(0)';
    });
    // Fonction pour basculer l'affichage des notifications
function toggleNotifications() {
    const modal = document.getElementById('notificationModal');
    if (modal.style.display === 'block') {
        modal.style.display = 'none';
    } else {
        modal.style.display = 'block';
        loadNotifications();
    }
}

// Fonction pour charger les notifications via AJAX
function loadNotifications() {
    const notificationList = document.getElementById('notificationList');
    
    fetch('get_notifications.php')
        .then(response => response.text())
        .then(data => {
            notificationList.innerHTML = data;
            // Mettre à jour le badge de notification
            updateNotificationBadge();
        })
        .catch(error => {
            notificationList.innerHTML = '<div class="error">Erreur lors du chargement des notifications</div>';
        });
}

// Fonction pour mettre à jour le badge de notification
function updateNotificationBadge() {
    fetch('count_unread_notifications.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-btn .badge');
            if (data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });
}

// Fermer la modal si on clique en dehors
document.addEventListener('click', function(event) {
    const modal = document.getElementById('notificationModal');
    const btn = document.querySelector('.notification-btn');
    
    if (modal.style.display === 'block' && 
        !modal.contains(event.target) && 
        !btn.contains(event.target)) {
        modal.style.display = 'none';
    }
});
    // Fermer le menu quand on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!userMenu.contains(e.target) && !userInfoDropdown.contains(e.target)) {
            userMenu.classList.remove('show');
            document.querySelector('.dropdown-arrow').style.transform = 'rotate(0)';
        }
    });
    function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }

        // Fermer le menu utilisateur quand on clique ailleurs
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('userMenu');
            const userInfo = document.querySelector('.user-info');
            
            if (menu.style.display === 'block' && 
                !userInfo.contains(event.target) && 
                !menu.contains(event.target)) {
                menu.style.display = 'none';
            }
        });
    
    // Gestion de la recherche (exemple basique)
    const searchInput = document.querySelector('.search-input');
    searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = this.value.trim();
            if (searchTerm.length > 0) {
                alert('Recherche pour: ' + searchTerm);
                // Ici vous pourriez faire une requête AJAX ou rediriger
                // window.location.href = 'recherche.php?q=' + encodeURIComponent(searchTerm);
            }
        }
    });

    </script>
</body>
</html>