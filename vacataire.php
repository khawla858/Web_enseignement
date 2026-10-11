<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vacataire') {
    header("Location: login.php"); // Redirection si non connecté ou non vacataire
    exit();
}

require_once 'config.php';

// Récupération de l'ID de l'utilisateur
$user_id = $_SESSION['user_id'];

// Récupération des notifications non lues
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$unread_notifications = $row['count'];

// Récupération des statistiques pour le tableau de bord
// Modules assurés
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM affectations WHERE user_id = ? AND valide = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$modules_assures = $row['count'];

// Modules assurés ce semestre
$semestre_actuel = (date('n') >= 8 || date('n') <= 1) ? 1 : 2; // Semestre 1: août-janvier, Semestre 2: février-juillet
$annee_univ = (date('n') >= 8) ? date('Y').'-'.(date('Y')+1) : (date('Y')-1).'-'.date('Y');

$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM affectations a
    JOIN unites_enseignement ue ON a.ue_id = ue.id
    WHERE a.user_id = ? AND a.valide = 1 
    AND ue.semestre = ? AND a.annee_univ = ?
");
$stmt->bind_param("iis", $user_id, $semestre_actuel, $annee_univ);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$modules_semestre = $row['count'];

// Charge horaire totale
$stmt = $conn->prepare("
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
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$charge_horaire = $row['total_heures'] ?? 0;

// Calcul du pourcentage de service (base 192h pour un vacataire)
$service_max = 192; // Heures maximum pour un vacataire
$pourcentage_service = round(($charge_horaire / $service_max) * 100);
$statut_service = ($pourcentage_service < 70) ? 'sous-service' : (($pourcentage_service > 100) ? 'sur-service' : 'normal');

// Notes uploadées
$stmt = $conn->prepare("SELECT COUNT(DISTINCT ue_id) as count FROM notes WHERE ens_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$notes_uploaded = $row['count'];

// Nombre total de modules avec notes à uploader
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT a.ue_id) as count
    FROM affectations a
    WHERE a.user_id = ? AND a.valide = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_modules = $row['count'];
$notes_restantes = $total_modules - $notes_uploaded;

// Répartition par type d'enseignement
$stmt = $conn->prepare("
    SELECT 
        a.type_enseignement,
        SUM(
            CASE WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
                 WHEN a.type_enseignement = 'TD' THEN ue.volume_td
                 WHEN a.type_enseignement = 'TP' THEN ue.volume_tp
            END
        ) as volume
    FROM affectations a
    JOIN unites_enseignement ue ON a.ue_id = ue.id
    WHERE a.user_id = ? AND a.valide = 1
    GROUP BY a.type_enseignement
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$heures_cm = 0;
$heures_td = 0;
$heures_tp = 0;

while ($row = $result->fetch_assoc()) {
    switch ($row['type_enseignement']) {
        case 'CM': $heures_cm = $row['volume']; break;
        case 'TD': $heures_td = $row['volume']; break;
        case 'TP': $heures_tp = $row['volume']; break;
    }
}

// Récupérer les UE par semestre
$stmt = $conn->prepare("
    SELECT 
        ue.semestre,
        COUNT(*) as count
    FROM affectations a
    JOIN unites_enseignement ue ON a.ue_id = ue.id
    WHERE a.user_id = ? AND a.valide = 1
    GROUP BY ue.semestre
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$ue_semestre1 = 0;
$ue_semestre2 = 0;

while ($row = $result->fetch_assoc()) {
    if ($row['semestre'] == 1) {
        $ue_semestre1 = $row['count'];
    } else {
        $ue_semestre2 = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Enseignant</title>
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
            width: 220px;
            height: 100%;
            background-color: var(--primary-color);
            color: white;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }

        .app-title {
            font-size: 20px;
            font-weight: 600;
            margin-top: 10px;
            text-align: center;
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

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .nav-icon {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
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
            top: 60px;
            right: 20px;
            width: 200px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 10px 0;
            display: none;
            z-index: 1000;
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

        .logout {
            color: #dc3545;
            border-top: 1px solid #eee;
            margin-top: 5px;
            padding-top: 10px;
        }

        /* Contenu principal */
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

        /* Dashboard cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
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
            margin-bottom: 15px;
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
            color: #28a745;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        

        /* Content card */
        .content-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .card-title {
            font-size: 18px;
            color: var(--secondary-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary-color);
        }

        /* Table styles */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            color: var(--secondary-color);
            font-weight: 600;
            background-color: #f9fafb;
        }

        .data-table tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-success {
            background-color: #d1fae5;
            color: #047857;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #997404;
        }

        .status-active {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            border: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-light);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        /* Footer */
        .footer {
            margin-left: 220px;
            padding: 20px;
            text-align: center;
            color: var(--light-text);
            font-size: 13px;
            border-top: 1px solid #eee;
        }

        /* Media queries pour responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar .app-title {
                display: none;
            }
            
            .nav-link span {
                display: none;
            }
            
            .navbar {
                left: 80px;
            }
            
            .main-content, .footer {
                margin-left: 80px;
            }
        }
/* ****************************************CSS de notifcation******************************* */
/* Notification Dropdown */
.notification-dropdown {
    position: absolute;
    top: 60px;
    right: 20px;
    width: 350px;
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    z-index: 1100;
    display: none;
    max-height: 70vh;
    overflow-y: auto;
}

.notification-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h4 {
    margin: 0;
    font-size: 16px;
}

.mark-all-read {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    font-size: 12px;
}

.notification-list {
    padding: 0;
}

.notification-item {
    padding: 12px 15px;
    display: flex;
    border-bottom: 1px solid #f5f5f5;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: var(--light-bg);
}

.notification-item.unread {
    background-color: #f8f9fa;
}

.notification-icon {
    margin-right: 12px;
    color: var(--primary-color);
    font-size: 18px;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--secondary-color);
}

.notification-text {
    color: var(--text-color);
    font-size: 14px;
    margin-bottom: 5px;
}

.notification-time {
    color: var(--light-text);
    font-size: 12px;
}

.notification-footer {
    padding: 10px 15px;
    text-align: center;
    border-top: 1px solid #eee;
}

.notification-footer a {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 13px;
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
        <i class="fas fa-university fa-3x mb-2"></i>
            <div class="app-title">Espace Vacataire</div>
        </div>
        <ul class="nav">
            <li class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="liste_vac.php" class="nav-link">
                    <i class="fas fa-book nav-icon"></i>
                    <span>Mes Unités</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="la_charge_horaire_proffesseur.php" class="nav-link">
                    <i class="fas fa-clock nav-icon"></i>
                    <span>Mon Charge Horaire</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="Upload_Note_prof.php" class="nav-link">
                    <i class="fas fa-upload nav-icon"></i>
                    <span>Upload des Notes</span>
                </a>
            </li>
            
        </ul>
    </div>

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
            <button class="nav-btn notification-btn">
                <i class="fas fa-bell"></i>
                <span class="badge"><?php echo $unread_notifications > 0 ? $unread_notifications : ''; ?></span>
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
        <a href="#" class="user-menu-item">
            <i class="fas fa-cog"></i> Paramètres
        </a>
        <a href="#" class="user-menu-item">
            <i class="fas fa-key"></i> Changer mot de passe
        </a>
        <a href="ind.php" class="user-menu-item logout">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>

<!------------------------------Notification---------------->
<!-- Notification Dropdown -->
<div class="notification-dropdown" id="notificationDropdown">
    <div class="notification-header">
        <h4>Notifications (<span id="notification-count"><?php echo $unread_notifications; ?></span>)</h4>
        <button class="mark-all-read">Tout marquer comme lu</button>
    </div>
    <div class="notification-list" id="notification-list">
        <?php 
        // Récupérer les notifications de l'utilisateur
        $queryNotifs = "SELECT * FROM notifications 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 10";
        $stmtNotifs = $conn->prepare($queryNotifs);
        $stmtNotifs->bind_param("i", $user_id);
        $stmtNotifs->execute();
        $resultNotifs = $stmtNotifs->get_result();
        
        while ($notif = $resultNotifs->fetch_assoc()) {
            $unreadClass = $notif['is_read'] ? '' : 'unread';
            $importantClass = $notif['type'] === 'warning' ? 'important' : '';
            
            // Définir l'icône et la couleur en fonction du type
            switch ($notif['type']) {
                case 'info':
                    $iconClass = 'fas fa-info-circle';
                    $iconColor = '#3a86ff';
                    break;
                case 'warning':
                    $iconClass = 'fas fa-exclamation-triangle';
                    $iconColor = '#ff9500';
                    break;
                case 'success':
                    $iconClass = 'fas fa-check-circle';
                    $iconColor = '#28a745';
                    break;
                default:
                    $iconClass = 'fas fa-bell';
                    $iconColor = '#6c757d';
            }
            
            echo '<div class="notification-item ' . $unreadClass . ' ' . $importantClass . '" data-id="' . $notif['id'] . '">
                    <div class="notification-icon">
                        <i class="' . $iconClass . '" style="color: ' . $iconColor . ';"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">' . htmlspecialchars($notif['title']) . '</div>
                        <div class="notification-text">' . htmlspecialchars($notif['message']) . '</div>
                        <div class="notification-time">' . date('d/m/Y H:i', strtotime($notif['created_at'])) . '</div>
                    </div>
                  </div>';
        }
        
        // Si aucune notification
        if ($resultNotifs->num_rows == 0) {
            echo '<div class="notification-item">
                    <div class="notification-content">
                        <div class="notification-text">Aucune notification</div>
                    </div>
                  </div>';
        }
        ?>
    </div>
    <div class="notification-footer">
        <a href="notifications.php">Voir toutes les notifications</a>
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
                <div class="stat-value"><?php echo $modules_assures; ?></div>
                <div class="stat-info">
                    <i class="fas fa-arrow-up"></i> +<?php echo $modules_semestre; ?> ce semestre
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #fff3cd; color: #997404;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-title">Heures d'enseignement</div>
                <div class="stat-value"><?php echo $charge_horaire; ?>h</div>
                <div class="stat-info" style="color: <?php echo $statut_service === 'sous-service' ? '#dc3545' : ($statut_service === 'sur-service' ? '#28a745' : '#997404'); ?>">
                    <i class="fas fa-<?php echo $statut_service === 'sous-service' ? 'exclamation-triangle' : ($statut_service === 'sur-service' ? 'check' : 'chart-line'); ?>"></i> 
                    <?php echo $pourcentage_service; ?>% du service
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #d1fae5; color: #047857;">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-title">Notes Uploadées</div>
                <div class="stat-value"><?php echo $notes_uploaded; ?>/<?php echo $total_modules; ?></div>
                <div class="stat-info">
                    <i class="fas fa-<?php echo $notes_restantes > 0 ? 'hourglass-half' : 'check'; ?>"></i> 
                    <?php echo $notes_restantes > 0 ? $notes_restantes . ' restante(s)' : 'Complet'; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #dbeafe; color: #1e40af;">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-title">Répartition des enseignements</div>
                <div class="stat-value" style="font-size: 18px;">
                    <?php if ($heures_cm > 0): ?>
                        <span class="badge status-active" style="margin-right: 5px;">CM: <?php echo $heures_cm; ?>h</span>
                    <?php endif; ?>
                    <?php if ($heures_td > 0): ?>
                        <span class="badge status-success" style="margin-right: 5px;">TD: <?php echo $heures_td; ?>h</span>
                    <?php endif; ?>
                    <?php if ($heures_tp > 0): ?>
                        <span class="badge status-pending">TP: <?php echo $heures_tp; ?>h</span>
                    <?php endif; ?>
                </div>
                <div class="stat-info">
                    <i class="fas fa-calendar-alt"></i> 
                    S1: <?php echo $ue_semestre1; ?> UE / S2: <?php echo $ue_semestre2; ?> UE
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-chart-pie"></i> Répartition de ma charge d'enseignement
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                <div style="width: 48%;">
                    <h4 style="font-size: 16px; color: var(--secondary-color); margin-bottom: 15px;">Par type d'enseignement</h4>
                    <div class="progress-container" style="height: 25px; margin-bottom: 30px;">
                        <?php
                        $total = $heures_cm + $heures_td + $heures_tp;
                        $pct_cm = $total > 0 ? ($heures_cm / $total) * 100 : 0;
                        $pct_td = $total > 0 ? ($heures_td / $total) * 100 : 0;
                        $pct_tp = $total > 0 ? ($heures_tp / $total) * 100 : 0;
                        ?>
                        
                        <?php if ($pct_cm > 0): ?>
                        <div class="progress-bar" style="width: <?php echo $pct_cm; ?>%; background-color: #3a86ff; float: left; height: 100%; text-align: center; color: white; line-height: 25px; font-size: 12px;">
                            <?php echo round($pct_cm); ?>% CM
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pct_td > 0): ?>
                        <div class="progress-bar" style="width: <?php echo $pct_td; ?>%; background-color: #4cc9f0; float: left; height: 100%; text-align: center; color: white; line-height: 25px; font-size: 12px;">
                            <?php echo round($pct_td); ?>% TD
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pct_tp > 0): ?>
                        <div class="progress-bar" style="width: <?php echo $pct_tp; ?>%; background-color: #8ac926; float: left; height: 100%; text-align: center; color: white; line-height: 25px; font-size: 12px;">
                            <?php echo round($pct_tp); ?>% TP
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="width: 48%;">
                    <h4 style="font-size: 16px; color: var(--secondary-color); margin-bottom: 15px;">Par semestre</h4>
                    <div class="progress-container" style="height: 25px; margin-bottom: 30px;">
                        <?php
                        $total_ue = $ue_semestre1 + $ue_semestre2;
                        $pct_s1 = $total_ue > 0 ? ($ue_semestre1 / $total_ue) * 100 : 0;
                        $pct_s2 = $total_ue > 0 ? ($ue_semestre2 / $total_ue) * 100 : 0;
                        ?>
                        
                        <?php if ($pct_s1 > 0): ?>
                        <div class="progress-bar" style="width: <?php echo $pct_s1; ?>%; background-color: #ff9500; float: left; height: 100%; text-align: center; color: white; line-height: 25px; font-size: 12px;">
                            <?php echo round($pct_s1); ?>% S1
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pct_s2 > 0): ?>
                        <div class="progress-bar" style="width: <?php echo $pct_s2; ?>%; background-color: #ff2d55; float: left; height: 100%; text-align: center; color: white; line-height: 25px; font-size: 12px;">
                            <?php echo round($pct_s2); ?>% S2
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: center; color: var(--light-text); font-size: 14px;">
                <p>Charge totale: <?php echo $charge_horaire; ?>h sur <?php echo $service_max; ?>h (<?php echo $pourcentage_service; ?>% du service)</p>
            </div>
        </div>

        <!-- Content -->
        

    <!-- Footer -->
    <div class="footer">
        © 2025 Espace Enseignant | Tous droits réservés
    </div>

    <script>
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

        // Activation des onglets
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
        // Ajoutez ceci à votre script existant
        document.querySelector('.notification-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            const userMenu = document.getElementById('userMenu');
            
            // Fermer le menu utilisateur si ouvert
            userMenu.style.display = 'none';
            
            // Basculer l'affichage des notifications
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Fermer les notifications quand on clique ailleurs
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const notifBtn = document.querySelector('.notification-btn');
            
            if (!dropdown.contains(event.target) && !notifBtn.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Marquer comme lu au clic
        document.addEventListener('click', function(event) {
            const notifItem = event.target.closest('.notification-item');
            if (notifItem && notifItem.classList.contains('unread')) {
                const notifId = notifItem.dataset.id;
                notifItem.classList.remove('unread');
                
                // Appel AJAX pour marquer comme lu en base
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notifId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationCount();
                    }
                });
            }
        });

        // Tout marquer comme lu
        document.querySelector('.mark-all-read').addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Appel AJAX pour tout marquer comme lu
            fetch('mark_all_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=<?php echo $user_id; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationCount();
                }
            });
        });

        // Mettre à jour le compteur de notifications
        function updateNotificationCount() {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            document.getElementById('notification-count').textContent = unreadCount;
            
            // Mettre à jour les badges
            document.querySelectorAll('.notification-btn .badge').forEach(badge => {
                badge.textContent = unreadCount > 0 ? unreadCount : '';
                badge.style.display = unreadCount > 0 ? 'flex' : 'none';
            });
        }

        // Exécuter au chargement
        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationCount();
        });
    </script>
</body>
</html>