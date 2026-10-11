<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Fonctions de notification
function createVacanteNotification($conn, $user_id, $nb_vacantes) {
    $checkQuery = "SELECT id FROM notifications 
                   WHERE user_id = ? 
                   AND type = 'warning' 
                   AND related_entity_type = 'ue_vacante'";

    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $notif_id = $result->fetch_assoc()['id'];
        $title = "UE vacantes";
        $message = "$nb_vacantes unité(s) d'enseignement sans affectation";

        $updateQuery = "UPDATE notifications 
                        SET title = ?, message = ?, is_read = FALSE 
                        WHERE id = ?";

        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssi", $title, $message, $notif_id);
        $stmt->execute();
    } else {
        $title = "UE vacantes";
        $message = "$nb_vacantes unité(s) d'enseignement sans affectation";

        $insertQuery = "INSERT INTO notifications 
                        (user_id, title, message, type, is_read, related_entity_type) 
                        VALUES (?, ?, ?, 'warning', FALSE, 'ue_vacante')";

        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iss", $user_id, $title, $message);
        $stmt->execute();
    }
}

$userId = $_SESSION['user_id'];

// Modules assurés
$stmt = $conn->prepare("SELECT COUNT(*) FROM affectations WHERE user_id = ? AND valide = 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($modules_assures);
$stmt->fetch();
$stmt->close();

// Charge horaire
$query = "SELECT SUM(
            CASE WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
                 WHEN a.type_enseignement = 'TD' THEN ue.volume_td
                 WHEN a.type_enseignement = 'TP' THEN ue.volume_tp
            END
          ) as total_heures
          FROM affectations a
          JOIN unites_enseignement ue ON a.ue_id = ue.id
          WHERE a.user_id = ? AND a.valide = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($charge_horaire);
$stmt->fetch();
$stmt->close();

// Notes uploadées
$stmt = $conn->prepare("SELECT COUNT(DISTINCT ue_id) FROM notes WHERE ens_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($notes_uploaded);
$stmt->fetch();
$stmt->close();

// Total modules
$stmt = $conn->prepare("SELECT COUNT(DISTINCT a.ue_id) FROM affectations a WHERE a.user_id = ? AND a.valide = 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($total_modules);
$stmt->fetch();
$stmt->close();
$notes_restantes = $total_modules - $notes_uploaded;

// UE affectées
$stmt = $conn->prepare("SELECT COUNT(*) as nb_ue FROM affectations WHERE user_id = ? AND annee_univ = '2025-2026'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($nbUE);
$stmt->fetch();
$stmt->close();

// Heures totales
$query = "SELECT SUM(CASE 
             WHEN type_enseignement = 'CM' THEN ue.volume_cm 
             WHEN type_enseignement = 'TD' THEN ue.volume_td 
             WHEN type_enseignement = 'TP' THEN ue.volume_tp 
         END) as total_heures
         FROM affectations a
         JOIN unites_enseignement ue ON a.ue_id = ue.id
         WHERE a.user_id = ? AND a.annee_univ = '2025-2026'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($totalHeures);
$stmt->fetch();
$stmt->close();

$serviceComplet = 192;
$pourcentageService = round(($totalHeures / $serviceComplet) * 100);

// Notes coordinateur
$stmt = $conn->prepare("SELECT COUNT(DISTINCT ue_id) as nb_notes FROM notes WHERE ens_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($nbNotes);
$stmt->fetch();
$stmt->close();
$notesRestantes = max(0, $nbUE - $nbNotes);

// UE vacantes
$query = "SELECT COUNT(*) as nb_vacantes 
          FROM unites_enseignement ue
          LEFT JOIN affectations a ON ue.id = a.ue_id AND a.annee_univ = '2025-2026'
          WHERE a.ue_id IS NULL 
          AND ue.filiere IN (SELECT filiere FROM coordinateurs_filieres WHERE user_id = ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($nbVacantes);
$stmt->fetch();
$stmt->close();

if ($nbVacantes > 0) {
    createVacanteNotification($conn, $userId, $nbVacantes);
} else {
    $deleteQuery = "DELETE FROM notifications 
                    WHERE user_id = ? 
                    AND type = 'warning' 
                    AND related_entity_type = 'ue_vacante'";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

// Notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultNotifs = $stmt->get_result();
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
.nav-section-header span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}
        .quick-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    text-decoration: none;
    color: var(--text-color);
    transition: transform 0.2s;
    text-align: center;
}

.quick-action:hover {
    transform: translateY(-5px);
    color: var(--primary-color);
}

.quick-action i {
    font-size: 24px;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.quick-action span {
    font-size: 14px;
    font-weight: 500;
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

        /* Tabs navigation - Version améliorée */
.tabs-container {
    position: relative;
    width: 100%;
    margin-bottom: 20px;
}

.tabs {
    display: flex;
    flex-wrap: wrap; /* Permet le passage à la ligne */
    gap: 10px;
    padding-bottom: 5px;
    overflow: visible; /* Supprime le défilement */
}

.tab {
    padding: 10px 20px;
    background-color: white;
    border-radius: 20px;
    color: var(--text-color);
    text-decoration: none;
    font-weight: 500;
    white-space: nowrap;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    margin-bottom: 5px; /* Espace entre les lignes */
}

.tab:hover {
    background-color: var(--primary-light);
    color: var(--primary-color);
}

.tab.active {
    background-color: var(--primary-color);
    color: white;
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
/* ***************************************CSS de notifcation****************************** */
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
.tab-parent {
    position: relative;
    display: inline-block;
}

/* Style pour les sous-onglets */
.sub-tabs {
    display: none;
    position: absolute;
    left: 0;
    top: 100%;
    background-color: white;
    border-radius: 0 0 20px 20px;
    box-shadow: var(--shadow);
    z-index: 100;
    min-width: 80%;
    white-space: nowrap;
}

.sub-tabs .tab {
    display: block;
    border-radius: 0;
    margin: 0;
    box-shadow: none;
    border-bottom: 1px solid #eee;
    width: 100%;
}

.sub-tabs .tab:last-child {
    border-bottom: none;
    border-radius: 0 0 20px 20px;
}

/* Ajustement pour l'icône */
.tab-parent .tab i {
    margin-left: 5px;
    font-size: 12px;
    transition: transform 0.2s;
}

.tab-parent:hover .tab i {
    transform: rotate(180deg);
}

.tab-parent:hover .sub-tabs {
    display: block;
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
/* Style pour les sections */
.section-toggle, .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.section-toggle {
    cursor: pointer;
}

.section-toggle:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.toggle-icon {
    transition: transform 0.3s ease;
}

.toggle-icon.rotate {
    transform: rotate(-180deg);
}

.collapsible-section {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    background-color: white;
    border-radius: 17px;
}

.collapsible-section.open {
    max-height: 500px; /* Valeur suffisamment grande pour contenir tous les éléments */
}
.collapsible-section span{
    color:black;
}
.collapsible-section i{
    color:black;
}
.collapsible-section :hover{
    background-color: rgb(200, 210, 214);
}

/* Style des liens dans les sections */
.nav-item .nav-link {
    padding: 10px 15px;
    display: flex;
    align-items: center;
    color: rgb(255, 255, 255);
    text-decoration: none;
    transition: all 0.2s;
    border-radius: 6px;
    margin: 5px 10px;
}

.nav-item .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}

.nav-item .nav-link.active {
    background-color: var(--primary-color);
    color: white;
}

.nav-icon {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}
    </style>
</head>
<body>
    <!-- Sidebar -->
  <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-university fa-3x mb-2"></i>
            <div class="app-title">Espace Coordinateur</div>
        </div>
        <ul class="nav">
            <li class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <!-- Section Fonctionnalités Professeurs avec toggle -->
            <li class="nav-section-header">
                <div class="section-toggle" onclick="toggleSection('prof-section')">
                    <span>Fonctionnalités Professeurs</span>
                    <i class="fas fa-chevron-down toggle-icon" id="prof-section-icon"></i>
                </div>
            </li>
            
            <div id="prof-section" class="collapsible-section">
                <li class="nav-item">
                    <a href="module_assures_prof.php" class="nav-link">
                        <i class="fas fa-bullseye nav-icon"></i>
                        <span>Mes modules assurés</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="la_charge_horaire_proffesseur.php" class="nav-link">
                        <i class="fas fa-clock nav-icon"></i>
                        <span>Charge Horaire</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="Upload_Note_prof.php" class="nav-link">
                        <i class="fas fa-upload nav-icon"></i>
                        <span>Upload notes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="exprimer_souhaits.php" class="nav-link">
                        <i class="fas fa-folder nav-icon"></i>
                        <span>Expression souhaits</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="historique_professeur.php" class="nav-link">
                        <i class="fas fa-history nav-icon"></i>
                        <span>Historique</span>
                    </a>
                </li>
            </div>
            
            <!-- Section Fonctionnalités Coordinateur (toujours visible) -->
            <li class="nav-section-header">
                <div class="section-header">
                    <span>Fonctionnalités Coordinateur</span>
                </div>
            </li>
            
            <li class="nav-item">
                <a href="saisie_ue.php" class="nav-link">
                    <i class="fas fa-plus-circle nav-icon"></i>
                    <span>Inserer UE</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="listeUEfilliere.php" class="nav-link">
                    <i class="fas fa-list nav-icon"></i>
                    <span>Liste des unités fillière</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="definir_groupe.php" class="nav-link">
                    <i class="fas fa-users nav-icon"></i>
                    <span>Définir les GRP</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="affectations_valides.php" class="nav-link">
                    <i class="fas fa-check-circle nav-icon"></i>
                    <span>Affectations validées</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="charger_emploit.php" class="nav-link">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    <span>Chargement emplois</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="descriptif_ue.php" class="nav-link">
                    <i class="fas fa-file-alt nav-icon"></i>
                    <span>Descriptif des UE</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="affecation_co.php" class="nav-link">
                    <i class="fas fa-user-plus nav-icon"></i>
                    <span>Affectation UE aux vacataires</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="ajoutercV.php" class="nav-link">
                    <i class="fas fa-user-plus nav-icon"></i>
                    <span>Création comptes vacataires</span>
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
        <a href="parametres.php" class="user-menu-item">
            <i class="fas fa-cog"></i> Paramètres
        </a>
        <a href="changerpass.php" class="user-menu-item">
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
        <h4>Notifications (<span id="notification-count">0</span>)</h4>
        <button class="mark-all-read">Tout marquer comme lu</button>
    </div>
    <div class="notification-list" id="notification-list">
        <!-- Les notifications seront chargées ici dynamiquement -->
    </div>
    <div class="notification-footer">
        <a href="notifications.php">Voir toutes les notifications</a>
    </div>
</div>
   <div class="main-content">
        <h1 class="page-title">Tableau de bord Coordinateur</h1>

        <!-- Statistiques côté coordinateur -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-title">UE Affectées</div>
                <div class="stat-value"><?php echo $nbUE; ?></div>
                <div class="stat-info">
                    <i class="fas fa-arrow-up"></i> Cette année
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #fff3cd; color: #997404;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-title">Heures d'enseignement</div>
                <div class="stat-value"><?php echo $totalHeures; ?>h</div>
                <div class="stat-info" style="color: <?php echo ($pourcentageService >= 100) ? '#dc3545' : '#997404'; ?>;">
                    <i class="fas fa-<?php echo ($pourcentageService >= 100) ? 'exclamation-circle' : 'exclamation-triangle'; ?>"></i> 
                    <?php echo $pourcentageService; ?>% du service
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #d1fae5; color: #047857;">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-title">Notes Uploadées</div>
                <div class="stat-value"><?php echo $nbNotes; ?>/<?php echo $nbUE; ?></div>
                <div class="stat-info">
                    <?php if($notesRestantes > 0): ?>
                        <i class="fas fa-exclamation-circle"></i> <?php echo $notesRestantes; ?> restante(s)
                    <?php else: ?>
                        <i class="fas fa-check"></i> Complété
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background-color: #ffeceb; color: #dc3545;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-title">UE Vacantes</div>
                <div class="stat-value"><?php echo $nbVacantes; ?></div>
                <div class="stat-info" style="color: #dc3545;">
                    <i class="fas fa-exclamation-circle"></i> 
                    <a href="UE_vacante.php" style="color: #dc3545; text-decoration: underline;">Voir détails</a>
                </div>
            </div>
        </div>

        <!-- Statistiques côté professeur -->
        <h1 class="page-title" style="margin-top: 40px;">Tableau de bord Professeur</h1>

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

        <!-- Onglets pour les fonctionnalités -->
        <div class="tabs-container">
            <div class="tabs">
                <a href="saisie_ue.php" class="tab">Inserer UE</a>
                <a href="listeUEfilliere.php" class="tab">Liste des unités fillière</a>
                <a href="definir_groupe.php" class="tab">Définir les GRP</a>
                <a href="affectations_valides.php" class="tab">Liste affectations valides</a>
                
                <div class="tab-parent">
                    <a href="#" class="tab">Gestion Vacataires <i class="fas fa-chevron-down"></i></a>
                    <div class="sub-tabs">
                        <a href="affecation_co.php" class="tab">Affectation des UE aux vacataires</a>
                        <a href="ajoutercV.php" class="tab">Création comptes vacataires</a>
                    </div>
                </div>
                
                <a href="charger_emploit.php" class="tab">Chargement emplois</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        © 2025 Espace Coordinateur | Tous droits réservés
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
    
    if (!dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});

// Marquer comme lu au clic
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function() {
        this.classList.remove('unread');
        // Ici vous pourriez ajouter un appel AJAX pour marquer comme lu en base
    });
});

// Tout marquer comme lu
document.querySelector('.mark-all-read').addEventListener('click', function() {
    document.querySelectorAll('.notification-item').forEach(item => {
        item.classList.remove('unread');
    });
    // Ici un appel AJAX pour tout marquer comme lu
});
function showVacataireTabs() {
    document.getElementById('vacataireTabs').style.display = 'block';
}

function hideVacataireTabs() {
    document.getElementById('vacataireTabs').style.display = 'none';
}

// Fermer aussi quand on clique ailleurs
document.addEventListener('click', function(event) {
    if (!event.target.closest('.tab-parent')) {
        hideVacataireTabs();
    }
});

// Fonction pour basculer l'affichage des sections
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const icon = document.getElementById(sectionId + '-icon');
    
    section.classList.toggle('open');
    icon.classList.toggle('rotate');
    
    // Sauvegarder l'état dans localStorage
    localStorage.setItem(sectionId + '-state', section.classList.contains('open'));
}

// Initialiser les sections au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    const sections = ['prof-section', 'coord-section'];
    
    sections.forEach(sectionId => {
        const section = document.getElementById(sectionId);
        const icon = document.getElementById(sectionId + '-icon');
        const savedState = localStorage.getItem(sectionId + '-state');
        
        // Si un état est sauvegardé, l'appliquer
        if (savedState === 'true') {
            section.classList.add('open');
            icon.classList.add('rotate');
        } else if (savedState === null) {
            // Par défaut, ouvrir la section coordinateur
            if (sectionId === 'coord-section') {
                section.classList.add('open');
                icon.classList.add('rotate');
                localStorage.setItem(sectionId + '-state', 'true');
            }
        }
    });
});
    </script>
</body>
</html>
</body>
</html>