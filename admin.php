<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=base_web", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Compter les utilisateurs
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    
    // Compter les départements
    $stmt = $pdo->query("SELECT COUNT(*) FROM departements");
    $total_departments = $stmt->fetchColumn();
    
    // Compter les filières
    $stmt = $pdo->query("SELECT COUNT(*) FROM filieres");
    $total_filieres = $stmt->fetchColumn();
    
    // Compter les demandes en attente (souhaits non encore affectés)
    $stmt = $pdo->query("SELECT COUNT(*) FROM souhaits s 
                        LEFT JOIN affectations a ON s.user_id = a.user_id AND s.ue_id = a.ue_id AND s.type_enseignement = a.type_enseignement
                        WHERE a.id IS NULL");
    $pending_requests = $stmt->fetchColumn();
    
    // Compter les affectations validées
    $stmt = $pdo->query("SELECT COUNT(*) FROM affectations WHERE valide = 1");
    $valid_affectations = $stmt->fetchColumn();
    
    // Compter les unités d'enseignement
    $stmt = $pdo->query("SELECT COUNT(*) FROM unites_enseignement");
    $total_ue = $stmt->fetchColumn();
    
    // Compter les professeurs sous-chargés (exemple: moins de 100 heures)
    $stmt = $pdo->query("SELECT COUNT(*) FROM charges_horaires WHERE total_heures < 100");
    $underloaded_teachers = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    // En cas d'erreur, on initialise les variables avec des valeurs par défaut
    $total_users = 0;
    $total_departments = 0;
    $total_filieres = 0;
    $pending_requests = 0;
    $valid_affectations = 0;
    $total_ue = 0;
    $underloaded_teachers = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Admin - eServices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variables pour une cohérence des couleurs */
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        /* Reset et styles généraux */
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
            overflow-x: hidden;
        }

        /* Barre latérale */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px; /* Augmentation de la largeur */
            height: 100%;
            background-color: var(--primary-color);
            color: white;
            z-index: 1000;
            overflow-y: auto;
            transition: width 0.3s;
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px; /* Plus d'espace */
            display: flex;
            flex-direction: column;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
        }

        .sidebar-logo {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--primary-color);
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .app-title {
            font-size: 16px;
            font-weight: 600;
            text-align: center;
        }

        .nav {
            list-style: none;
            padding: 0;
        }

        .nav-item {
            margin: 2px 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px; /* Plus d'espace horizontal */
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 4px 10px; /* Marges horizontales augmentées */
            transition: all 0.2s;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .nav-icon {
            margin-right: 10px;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        /* Barre de navigation supérieure */
        .navbar {
            position: fixed;
            top: 0;
            left: 240px; /* Ajusté à la nouvelle largeur de la sidebar */
            right: 0;
            height: 65px; /* Légèrement plus haute */
            background-color: white;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px; /* Plus d'espace horizontal */
            z-index: 900;
        }
        .nav-btn {
            border:none;
            background-color: transparent;
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

        .navbar-left {
            display: flex;
            align-items: center;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
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

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text);
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--secondary-color);
            font-size: 16px;
            cursor: pointer;
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

        /* Contenu principal */
        .main-content {
            margin-left: 240px; /* Ajusté à la nouvelle largeur de la sidebar */
            padding: 85px 30px 30px; 
            margin-top: 1px; /* Plus d'espace tout autour */
        }

        .page-title {
            font-size: 24px;
            color: var(--secondary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Dashboard cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px; /* Plus d'espace entre les cartes */
            margin-bottom: 40px;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* Ombre plus douce */
            padding: 25px; /* Plus d'espace intérieur */
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12); /* Ombre plus prononcée au survol */
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .stat-title {
            color: var(--light-text);
            font-size: 14px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .stat-info {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Styles pour le menu déroulant */
        .dropdown-menu {
            display: none;
            list-style: none;
            padding-left: 30px;
            margin-top: 5px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            background-color:white;
            border-radius: 10px;
            margin-left: 13px;
            margin-right: 13px;
            
        }
        
        .dropdown-menu li {
            margin-bottom: 8px;
        }
        
        .dropdown-menu a {
            color: black;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 8px ;
            border-radius: 6px;
            transition: all 0.2s;
            
        }
        
        .dropdown-menu a:hover {
           
            color: var(--primary-color);
        }
        
        .dropdown-menu a i {
            margin-right: 8px;
            font-size: 14px;
            width: 20px;
            text-align: center;
        }
        
        .nav-item.has-dropdown {
            position: relative;
        }
        
        .nav-item.has-dropdown .nav-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dropdown-icon {
            transition: transform 0.3s ease;
        }
        
        .nav-item.has-dropdown.active .dropdown-icon {
            transform: rotate(180deg);
        }

        /* Media queries pour responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px; /* Un peu plus large que l'original */
            }
            
            .sidebar .app-title {
                display: none;
            }
            
            .nav-link span {
                display: none;
            }
            
            .navbar {
                left: 70px;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .search-box {
                display: none;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .navbar {
                padding: 0 10px;
            }
            
            .user-name {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">A</div>
            <div class="app-title">Espace Admin</div>
        </div>
        <ul class="nav">
            <li class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="liste_utilisateurs.php" class="nav-link">
                    <i class="fas fa-users nav-icon"></i>
                    <span>Liste des users</span>
                </a>
            </li>
            <li class="nav-item has-dropdown" onmouseover="showDropdown(this)" onmouseout="hideDropdown(this)">
                <a href="#" class="nav-link">
                    <i class="fas fa-user-cog nav-icon"></i>
                    <span>Gérer les comptes</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a href="ajouterc.php">
                            <i class="fas fa-user-plus"></i>
                            <span>Ajouter Compte</span>
                        </a>
                    </li>
                    <li>
                        <a href="modifierc.php">
                            <i class="fas fa-user-edit"></i>
                            <span>Modifier Compte</span>
                        </a>
                    </li>
                    <li>
                        <a href="supprimerc.php">
                            <i class="fas fa-user-times"></i>
                            <span>Supprimer Compte</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a href="modifier_permissions.php" class="nav-link">
                    <i class="fas fa-user-shield nav-icon"></i>
                    <span>Permissions</span>
                </a>
            </li>
           
            <li class="nav-item">
                <a href="liste_departements.php" class="nav-link">
                    <i class="fas fa-list nav-icon"></i>
                    <span>Liste Départements/Filières</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="ind.php" class="nav-link">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Navbar -->
    <div class="navbar">
        <div class="navbar-left">
            <button class="nav-btn" id="toggleSidebar">
                <i class="fas fa-home"></i> Accueil
            </button>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Rechercher...">
            </div>
        </div>
        <div class="navbar-right">
            <button class="notification-btn" id="notificationBtn">
                <i class="fas fa-bell"></i>
                <span class="badge"><?php echo $pending_requests; ?></span>
            </button>
            <div class="user-info" id="userInfoBtn">
                <div class="user-avatar">
                    <?php echo isset($_SESSION['prenom']) ? substr($_SESSION['prenom'], 0, 1) : 'A'; ?>
                </div>
                <span class="user-name">
                    <?php echo isset($_SESSION['prenom']) && isset($_SESSION['nom']) ? $_SESSION['prenom'] . " " . $_SESSION['nom'] : "Admin"; ?>
                </span>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>

    <!-- User Menu -->
    <div class="user-menu" id="userMenu" style="display: none; position: absolute; top: 60px; right: 20px; background-color: white; border-radius: 10px; box-shadow: var(--shadow); width: 200px; z-index: 1000;">
        <a href="profil.php" class="user-menu-item" style="display: flex; padding: 12px 15px; text-decoration: none; color: var(--text-color); align-items: center; gap: 10px;">
            <i class="fas fa-user"></i> Mon profil
        </a>
        <a href="parametres.php" class="user-menu-item" style="display: flex; padding: 12px 15px; text-decoration: none; color: var(--text-color); align-items: center; gap: 10px;">
            <i class="fas fa-cog"></i> Paramètres
        </a>
        <a href="changerpass.php" class="user-menu-item" style="display: flex; padding: 12px 15px; text-decoration: none; color: var(--text-color); align-items: center; gap: 10px;">
            <i class="fas fa-key"></i> Changer mot de passe
        </a>
        <a href="ind.php" class="user-menu-item" style="display: flex; padding: 12px 15px; text-decoration: none; color: var(--danger-color); align-items: center; gap: 10px; border-top: 1px solid #eee;">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>
    <div class="section-title" style="font-size: 20px; color: var(--secondary-color); margin: 30px 0 15px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
    <i class="fas fa-sitemap"></i> Gestion académique
</div>

<div class="dashboard-grid" style="margin-bottom: 30px;"></div>

    <!-- Notification Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown" style="display: none; position: absolute; top: 60px; right: 80px; width: 350px; background-color: white; border-radius: var(--border-radius); box-shadow: var(--shadow); z-index: 1000; max-height: 70vh; overflow-y: auto;">
        <div class="notification-header" style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h4>Notifications (<span id="notification-count">10</span>)</h4>
            <button class="mark-all-read" style="background: none; border: none; color: var(--primary-color); cursor: pointer; font-size: 14px;">Tout marquer comme lu</button>
        </div>
        <div class="notification-list" id="notification-list" style="max-height: 400px; overflow-y: auto;">
            <!-- Exemple de notifications -->
            <div class="notification-item unread" style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 10px; cursor: pointer; background-color: rgba(232, 240, 254, 0.4);">
                <div class="notif-icon" style="width: 40px; height: 40px; border-radius: 50%; background-color: #e3f2fd; display: flex; align-items: center; justify-content: center; color: var(--primary-color);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="notif-content" style="flex: 1;">
                    <div class="notif-title" style="font-weight: 600; margin-bottom: 3px;">Nouvel utilisateur</div>
                    <div class="notif-text" style="font-size: 13px; color: var(--light-text);">Un nouvel utilisateur s'est inscrit.</div>
                    <div class="notif-time" style="font-size: 12px; color: var(--light-text); margin-top: 5px;">Il y a 5 minutes</div>
                </div>
            </div>
            <div class="notification-item" style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 10px; cursor: pointer;">
                <div class="notif-icon" style="width: 40px; height: 40px; border-radius: 50%; background-color: #fff3cd; display: flex; align-items: center; justify-content: center; color: #997404;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="notif-content" style="flex: 1;">
                    <div class="notif-title" style="font-weight: 600; margin-bottom: 3px;">Alerte système</div>
                    <div class="notif-text" style="font-size: 13px; color: var(--light-text);">Mise à jour de sécurité disponible.</div>
                    <div class="notif-time" style="font-size: 12px; color: var(--light-text); margin-top: 5px;">Il y a 1 jour</div>
                </div>
            </div>
        </div>
        <div class="notification-footer" style="padding: 12px 15px; text-align: center; border-top: 1px solid #eee;">
            <a href="notifications.php" style="color: var(--primary-color); text-decoration: none; font-size: 14px;">Voir toutes les notifications</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="page-title">Tableau de bord</h1>

        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background-color: var(--primary-light); color: var(--primary-color);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-title">Utilisateurs</div>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-info" style="color: var(--success-color);">
                    <i class="fas fa-arrow-up"></i> Actifs
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-icon" style="background-color: #d1fae5; color: #047857;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-title">Filières</div>
                <div class="stat-value"><?php echo $total_filieres; ?></div>
                <div class="stat-info" style="color: var(--success-color);">
                    <i class="fas fa-check"></i> Actives
                </div>
            </div>
             <a href="liste_departements.php" style="text-decoration: none;">
        <div class="stat-card" style="cursor: pointer; transition: all 0.3s;">
            <div class="stat-icon" style="background-color: #e0f2fe; color: #0369a1;">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-title">Départements</div>
            <div class="stat-value"><?php echo $total_departments; ?></div>
            <div class="stat-info" style="color: #0369a1;">
                <i class="fas fa-eye"></i> Voir tous
            </div>
        </div>
    </a>

   
            <div class="stat-card">
                <div class="stat-icon" style="background-color: #fee2e2; color: #dc2626;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-title">Demandes en attente</div>
                <div class="stat-value"><?php echo $pending_requests; ?></div>
                <div class="stat-info" style="color: var(--danger-color);">
                    <i class="fas fa-exclamation-triangle"></i> À traiter
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #e0e7ff; color: #4f46e5;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-title">Affectations validées</div>
                <div class="stat-value"><?php echo $valid_affectations; ?></div>
                <div class="stat-info" style="color: #4f46e5;">
                    <i class="fas fa-check"></i> Confirmées
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #ecfdf5; color: #059669;">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-title">Unités d'enseignement</div>
                <div class="stat-value"><?php echo $total_ue; ?></div>
                <div class="stat-info" style="color: #059669;">
                    <i class="fas fa-book-open"></i> Disponibles
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #fef3c7; color: #d97706;">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-title">Professeurs sous-chargés</div>
                <div class="stat-value"><?php echo $underloaded_teachers; ?></div>
                <div class="stat-info" style="color: #d97706;">
                    <i class="fas fa-exclamation"></i> Moins de 100h
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonctions pour afficher/masquer le menu déroulant
        function showDropdown(element) {
            const dropdownMenu = element.querySelector('.dropdown-menu');
            dropdownMenu.style.display = 'block';
            element.classList.add('active');
        }
        
        function hideDropdown(element) {
            const dropdownMenu = element.querySelector('.dropdown-menu');
            dropdownMenu.style.display = 'none';
            element.classList.remove('active');
        }
        
        // Toggle sidebar
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        });

        // Toggle user menu
        document.getElementById('userInfoBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = document.getElementById('userMenu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            
            // Fermer les notifications si ouvertes
            document.getElementById('notificationDropdown').style.display = 'none';
        });

        // Toggle notifications
        document.getElementById('notificationBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            const userMenu = document.getElementById('userMenu');
            
            // Fermer le menu utilisateur si ouvert
            userMenu.style.display = 'none';
            
            // Basculer l'affichage des notifications
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Fermer les menus quand on clique ailleurs
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const notifDropdown = document.getElementById('notificationDropdown');
            
            if (!event.target.closest('#userInfoBtn') && !event.target.closest('#userMenu')) {
                userMenu.style.display = 'none';
            }
            
            if (!event.target.closest('#notificationBtn') && !event.target.closest('#notificationDropdown')) {
                notifDropdown.style.display = 'none';
            }
        });

        // Marquer les notifications comme lues
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                this.classList.remove('unread');
                updateNotificationCount();
            });
        });

        // Tout marquer comme lu
        document.querySelector('.mark-all-read').addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('unread');
            });
            updateNotificationCount();
        });

        // Mettre à jour le compteur de notifications
        function updateNotificationCount() {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            document.getElementById('notification-count').textContent = unreadCount;
            document.querySelector('.badge').textContent = unreadCount > 0 ? unreadCount : '';
        }
    </script>
</body>
</html>