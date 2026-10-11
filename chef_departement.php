<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
//***********************************************************************
// Fonction pour créer une notification d'UE vacante (une seule fois)
function createVacanteNotification($conn, $user_id, $nb_vacantes) {
    // Vérifier si une notification similaire existe déjà
    $checkQuery = "SELECT id FROM notifications 
                  WHERE user_id = ? 
                  AND type = 'warning' 
                  AND related_entity_type = 'ue_vacante'";
    
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Mettre à jour la notification existante
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
        // Créer une notification UNIQUEMENT si elle n'existe pas déjà
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
//***********************************************************************************

// Fonction pour créer/mettre à jour une notification de souhaits en attente
function createSouhaitsNotification($conn, $user_id, $nb_souhaits) {
    // Vérifier si une notification similaire existe déjà
    $checkQuery = "SELECT id FROM notifications 
                  WHERE user_id = ? 
                  AND type = 'info' 
                  AND related_entity_type = 'souhaits_attente'";
    
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Mettre à jour la notification existante
        $notif_id = $result->fetch_assoc()['id'];
        $title = "Demandes en attente";
        $message = "$nb_souhaits demande(s) d'affectation en attente";
        
        $updateQuery = "UPDATE notifications 
                       SET title = ?, message = ?, is_read = FALSE 
                       WHERE id = ?";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssi", $title, $message, $notif_id);
        $stmt->execute();
    } else {
        // Créer une nouvelle notification
        $title = "Demandes en attente";
        $message = "$nb_souhaits demande(s) d'affectation en attente";
        
        $insertQuery = "INSERT INTO notifications 
                       (user_id, title, message, type, is_read, related_entity_type) 
                       VALUES (?, ?, ?, 'info', FALSE, 'souhaits_attente')";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iss", $user_id, $title, $message);
        $stmt->execute();
    }
}
//**************************************************************************
// Fonction pour créer/mettre à jour une notification de professeurs sous-chargés
function createSousChargeNotification($conn, $user_id, $nb_sous_charges) {
    // Vérifier si une notification similaire existe déjà
    $checkQuery = "SELECT id FROM notifications 
                  WHERE user_id = ? 
                  AND type = 'warning' 
                  AND related_entity_type = 'prof_sous_charge'";
    
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Mettre à jour la notification existante
        $notif_id = $result->fetch_assoc()['id'];
        $title = "Professeurs sous-chargés";
        $message = "$nb_sous_charges professeur(s) avec charge horaire insuffisante";
        
        $updateQuery = "UPDATE notifications 
                       SET title = ?, message = ?, is_read = FALSE 
                       WHERE id = ?";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssi", $title, $message, $notif_id);
        $stmt->execute();
    } else {
        // Créer une nouvelle notification
        $title = "Professeurs sous-chargés";
        $message = "$nb_sous_charges professeur(s) avec charge horaire insuffisante";
        
        $insertQuery = "INSERT INTO notifications 
                       (user_id, title, message, type, is_read, related_entity_type) 
                       VALUES (?, ?, ?, 'warning', FALSE, 'prof_sous_charge')";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iss", $user_id, $title, $message);
        $stmt->execute();
    }
}
//**************************************************************************
// Récupérer les statistiques depuis la base
$userId = $_SESSION['user_id'];

// Nombre d'UE affectées
$queryUE = "SELECT COUNT(*) as nb_ue FROM affectations WHERE user_id = ? AND annee_univ = '2025-2026'";
$stmtUE = $conn->prepare($queryUE);
$stmtUE->bind_param("i", $userId);
$stmtUE->execute();
$resultUE = $stmtUE->get_result();
$nbUE = $resultUE->fetch_assoc()['nb_ue'];

// Heures totales d'enseignement
$queryHeures = "SELECT SUM(CASE 
                    WHEN type_enseignement = 'CM' THEN ue.volume_cm 
                    WHEN type_enseignement = 'TD' THEN ue.volume_td 
                    WHEN type_enseignement = 'TP' THEN ue.volume_tp 
                END) as total_heures
                FROM affectations a
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                WHERE a.user_id = ? AND a.annee_univ = '2025-2026'";
$stmtHeures = $conn->prepare($queryHeures);
$stmtHeures->bind_param("i", $userId);
$stmtHeures->execute();
$resultHeures = $stmtHeures->get_result();
$totalHeures = $resultHeures->fetch_assoc()['total_heures'] ?? 0;

// Pourcentage du service (supposons un service de 192h)
$serviceComplet = 192;
$pourcentageService = round(($totalHeures / $serviceComplet) * 100);

// Notes uploadées (supposition - à adapter selon votre structure)
$queryNotes = "SELECT COUNT(DISTINCT ue_id) as nb_notes FROM notes WHERE ens_id = ?";
$stmtNotes = $conn->prepare($queryNotes);
$stmtNotes->bind_param("i", $userId);
$stmtNotes->execute();
$resultNotes = $stmtNotes->get_result();
$nbNotes = $resultNotes->fetch_assoc()['nb_notes'];
$notesRestantes = max(0, $nbUE - $nbNotes);

// Compter les UE vacantes
$queryVacantes = "SELECT COUNT(*) as nb_vacantes 
                 FROM unites_enseignement ue
                 LEFT JOIN affectations a ON ue.id = a.ue_id AND a.annee_univ = '2025-2026'
                 WHERE a.ue_id IS NULL 
                 AND ue.specialite = (SELECT nom FROM departements WHERE id = 
                    (SELECT departement_id FROM chefs_departement WHERE user_id = ?))";
$stmtVacantes = $conn->prepare($queryVacantes);
$stmtVacantes->bind_param("i", $userId);
$stmtVacantes->execute();
$resultVacantes = $stmtVacantes->get_result();
$nbVacantes = $resultVacantes->fetch_assoc()['nb_vacantes'];

// Compter les souhaits en attente
$querySouhaits = "SELECT COUNT(*) as nb_souhaits 
                 FROM souhaits s
                 JOIN unites_enseignement ue ON s.ue_id = ue.id
                 WHERE ue.specialite = (SELECT nom FROM departements WHERE id = 
                    (SELECT departement_id FROM chefs_departement WHERE user_id = ?))
                 AND s.user_id NOT IN (
                    SELECT user_id FROM affectations WHERE ue_id = s.ue_id AND annee_univ = '2025-2026'
                 )";
$stmtSouhaits = $conn->prepare($querySouhaits);
$stmtSouhaits->bind_param("i", $userId);
$stmtSouhaits->execute();
$resultSouhaits = $stmtSouhaits->get_result();
$nbSouhaits = $resultSouhaits->fetch_assoc()['nb_souhaits'];

// Créer ou mettre à jour la notification pour les souhaits
if ($nbSouhaits > 0) {
    createSouhaitsNotification($conn, $userId, $nbSouhaits);
} else {
    // Supprimer la notification s'il n'y a plus de souhaits en attente
    $deleteQuery = "DELETE FROM notifications 
                   WHERE user_id = ? 
                   AND type = 'info' 
                   AND related_entity_type = 'souhaits_attente'";
    
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

// Vérifier les nouvelles demandes d'affectation
$queryNouvellesDemandes = "SELECT COUNT(*) as nb_nouvelles_demandes 
                          FROM notifications 
                          WHERE user_id = ? 
                          AND related_entity_type = 'souhait' 
                          AND is_read = FALSE";
$stmtNouvellesDemandes = $conn->prepare($queryNouvellesDemandes);
$stmtNouvellesDemandes->bind_param("i", $userId);
$stmtNouvellesDemandes->execute();
$resultNouvellesDemandes = $stmtNouvellesDemandes->get_result();
$nbNouvellesDemandes = $resultNouvellesDemandes->fetch_assoc()['nb_nouvelles_demandes'];

// Compter les professeurs sous-chargés (charge < 192h)
$querySousCharges = "SELECT COUNT(*) as nb_sous_charges 
                    FROM users u
                    JOIN professeurs_departements pd ON u.id = pd.professeur_id
                    LEFT JOIN (
                        SELECT a.user_id, 
                        SUM(CASE 
                            WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
                            WHEN a.type_enseignement = 'TD' THEN ue.volume_td
                            WHEN a.type_enseignement = 'TP' THEN ue.volume_tp
                            ELSE 0
                        END) as total_charge
                        FROM affectations a
                        JOIN unites_enseignement ue ON a.ue_id = ue.id
                        WHERE a.valide = TRUE AND a.annee_univ = '2025-2026'
                        GROUP BY a.user_id
                    ) charges ON u.id = charges.user_id
                    WHERE pd.departement_id = (SELECT departement_id FROM chefs_departement WHERE user_id = ?)
                    AND u.role = 'professeur'
                    AND (charges.total_charge < 192 OR charges.total_charge IS NULL)";

$stmtSousCharges = $conn->prepare($querySousCharges);
$stmtSousCharges->bind_param("i", $userId);
$stmtSousCharges->execute();
$resultSousCharges = $stmtSousCharges->get_result();
$nbSousCharges = $resultSousCharges->fetch_assoc()['nb_sous_charges'];

// Créer ou mettre à jour la notification pour les professeurs sous-chargés
if ($nbSousCharges > 0) {
    createSousChargeNotification($conn, $userId, $nbSousCharges);
} else {
    // Supprimer la notification s'il n'y a plus de professeurs sous-chargés
    $deleteQuery = "DELETE FROM notifications 
                   WHERE user_id = ? 
                   AND type = 'warning' 
                   AND related_entity_type = 'prof_sous_charge'";
    
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

// Créer ou mettre à jour la notification pour les UE vacantes
if ($nbVacantes > 0) {
    createVacanteNotification($conn, $userId, $nbVacantes);
} else {
    // Supprimer la notification s'il n'y a plus d'UE vacantes
    $deleteQuery = "DELETE FROM notifications 
                   WHERE user_id = ? 
                   AND type = 'warning' 
                   AND related_entity_type = 'ue_vacante'";
    
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();



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
/* Animation pour le dropdown */
/* Styles améliorés pour les menus déroulants */
.nav-dropdown-menu {
    max-height: 0;
    overflow: hidden;
    transition: 
        max-height 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        opacity 0.3s ease,
        transform 0.3s ease;
    opacity: 0;
    transform: translateY(-10px);
    background-color: rgb(255, 255, 255);
    border-radius: 8px;
    margin: 5px 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    padding: 0;
    list-style: none;
}

.nav-dropdown-menu.show {
    max-height: 500px;
    opacity: 1;
    transform: translateY(0);
    padding: 5px 0;
}

/* Animation de l'icône */
.nav-dropdown-icon {
    transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55);
    transform-origin: center;
    font-size: 12px;
    margin-left: 5px;
}

.nav-dropdown-icon.rotated {
    transform: rotate(180deg);
}

/* Styles pour les éléments du menu déroulant */
.nav-dropdown-menu .nav-item {
    margin: 0;
}

.nav-dropdown-menu .nav-link {
    padding: 10px 15px;
    border-radius: 5px;
    margin: 0 5px;
    color: rgb(6, 6, 6);
    transition: all 0.2s;
}

.nav-dropdown-menu .nav-link:hover {
    background-color: rgb(200, 210, 214);
    color: white;
    transform: translateX(3px);
}

.nav-dropdown-menu .nav-icon {
    color:black;
    font-size: 16px;
}

/* Effet de rebond lors de l'ouverture */
@keyframes bounceIn {
    0% {
        opacity: 0;
        transform: translateY(-10px);
    }
    60% {
        opacity: 1;
        transform: translateY(5px);
    }
    100% {
        transform: translateY(0);
    }
}

.nav-dropdown-menu.show {
    animation: bounceIn 0.5s forwards;
}

/* Effet de rebond lors de l'ouverture */


.nav-dropdown-menu.show {
    animation: bounceIn 0.5s forwards;
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

        /* Tabs navigation */
        .tabs {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 5px;
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

.notification-item.unread.important {
    border-left: 3px solid #dc3545;
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

.notification-badge-count {
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
/* Styles pour les menus déroulants */
.nav-dropdown-menu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    list-style: none;
}

.nav-dropdown-menu.show {
    max-height: 500px; /* Ajustez selon le nombre d'items */
}

.nav-dropdown-toggle {
    cursor: pointer;
    user-select: none;
}

.nav-dropdown-toggle .nav-dropdown-icon.rotated {
    transform: rotate(180deg);
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
        /* Style pour les en-têtes de section dans la sidebar */
.nav-section-header {
    padding: 10px 15px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}
    </style>
</head>
<body>
    <!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">E</div>
        <div class="app-title">Espace Chef département</div>
    </div>
    <ul class="nav">
        <li class="nav-item">
            <a href="#" class="nav-link active">
                <i class="fas fa-tachometer-alt nav-icon"></i>
                <span>Tableau de bord</span>
            </a>
        </li>
        
        <!-- Section pour les professeurs -->
       <!-- Section pour les professeurs - version déroulante -->
<li class="nav-section-header nav-dropdown-toggle">
    <a href="#" style="display: flex; justify-content: space-between; width: 100%; color: rgba(255, 255, 255, 0.7); text-decoration: none;">
        <span>Fonctionnalités Professeurs</span>
        <i class="fas fa-chevron-down nav-dropdown-icon" style="transition: transform 0.3s;"></i>
    </a>
</li>

<div class="nav-dropdown-menu">
    <li class="nav-item">
        <a href="la_charge_horaire_proffesseur.php" class="nav-link">
            <i class="fas fa-clock nav-icon"></i>
            <span>Charge Horaire</span>
        </a>
    </li>
    <li class="nav-item">
        <a href="module_assures_prof.php" class="nav-link">
            <i class="fas fa-check-circle nav-icon"></i>
            <span>Modules Assurés</span>
        </a>
    </li>
    <li class="nav-item">
        <a href="Upload_Note_prof.php" class="nav-link">
            <i class="fas fa-folder nav-icon"></i>
            <span>Upload des Notes</span>
        </a>
    </li>
    <li class="nav-item">
        <a href="historique_professeur.php" class="nav-link">
            <i class="fas fa-history nav-icon"></i>
            <span>Historique</span>
        </a>
    </li>
</div>
        <!-- Section pour les chefs de département -->
        <li class="nav-section-header">
            <span>Fonctionnalités Chef de Département</span>
        </li>
        <li class="nav-item">
              <li class="nav-item">
                <a href="liste_ue.php" class="nav-link">
                    <i class="fas fa-book nav-icon"></i>
                    <span>Unités d'Enseignement</span>
                </a>
            </li>
            <a href="liste_profs.php" class="nav-link">
                <i class="fas fa-users nav-icon"></i>
                <span>Liste des enseignants</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="chargehorairedep.php" class="nav-link">
                <i class="fas fa-chart-bar nav-icon"></i>
                <span>Charge Horaire département</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="affecter.php" class="nav-link">
                <i class="fas fa-inbox nav-icon"></i>
                <span>souhaits des proffesseur</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="UE_vacante.php" class="nav-link">
                <i class="fas fa-exclamation-triangle nav-icon"></i>
                <span>UE vacantes</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="Historique.php" class="nav-link">
                <i class="fas fa-archive nav-icon"></i>
                <span>Historique des affectations</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="affectations_manuelles.php" class="nav-link">
                <i class="fas fa-hand-pointer nav-icon"></i>
                <span>Affectation manuelle</span>
            </a>
        </li>
        <li class="nav-item">
    <a href="rapports.php" class="nav-link">
        <i class="fas fa-file-alt nav-icon"></i>
        <span>Rapports</span>
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
        <!-- Les notifications seront chargées depuis la base de données -->
        <?php 
        // Récupérer les notifications de l'utilisateur - Assurons-nous que la requête est correcte
        $queryNotifs = "SELECT * FROM notifications 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 10";
        $stmtNotifs = $conn->prepare($queryNotifs);
        $stmtNotifs->bind_param("i", $userId);
        $stmtNotifs->execute();
        $resultNotifs = $stmtNotifs->get_result();

        // Ajoutons un débogage pour vérifier les notifications récupérées
        $debug_notifs = [];
        while ($notif = $resultNotifs->fetch_assoc()) {
            $debug_notifs[] = $notif;
        }

        // Réinitialisons le pointeur du résultat pour l'utilisation normale
        $resultNotifs->data_seek(0);

        // Affichons les informations de débogage dans la console
        echo "<script>console.log('Notifications récupérées:', " . json_encode($debug_notifs) . ");</script>";

        while ($notif = $resultNotifs->fetch_assoc()) {
            $unreadClass = $notif['is_read'] ? '' : 'unread';
            $importantClass = ($notif['type'] == 'warning' || $notif['type'] == 'alert') ? 'important' : '';
            $iconClass = '';
            $iconColor = '';
            
            switch ($notif['type']) {
                case 'warning':
                    $iconClass = 'fas fa-exclamation-triangle';
                    $iconColor = '#dc3545';
                    break;
                case 'info':
                    $iconClass = 'fas fa-info-circle';
                    $iconColor = '#0d6efd';
                    break;
                case 'success':
                    $iconClass = 'fas fa-check-circle';
                    $iconColor = '#198754';
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
        <h1 class="page-title">Tableau de bord cote chef de department</h1>

        <!-- Statistics Cards -->
     <!-- Main Content -->
   

        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-title">Modules Affectés</div>
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
            
            <!-- Nouvelle carte pour les demandes de souhaits en attente -->
            <div class="stat-card">
                <div class="stat-icon" style="background-color: #d1e7ff; color: #0d6efd;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-title">Demandes en attente</div>
                <div class="stat-value">
                    <?php 
                    $queryDemandes = "SELECT COUNT(*) as nb_demandes FROM souhaits 
                                     WHERE annee_univ = '2025-2026' AND user_id IN 
                                     (SELECT professeur_id FROM professeurs_departements WHERE departement_id = 1)";
                    $resultDemandes = $conn->query($queryDemandes);
                    $nbDemandes = $resultDemandes->fetch_assoc()['nb_demandes'];
                    echo $nbDemandes;
                    ?>
                </div>
                <div class="stat-info">
                    <i class="fas fa-users"></i> Enseignants
                </div>
            </div>
            
            <!-- Carte pour les UE vacantes -->
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

        <!-- Tabs -->
        
    <!-- Main Content -->
  / 
        <h1 class="page-title">Tableau de bord cote  professeur</h1>

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
      

        <!-- Contennnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnt -->
        
</div>

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
    
    if (!dropdown.contains(event.target)) {
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
        body: 'user_id=' + <?php echo $userId; ?>
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
        badge.textContent = unreadCount;
        badge.style.display = unreadCount > 0 ? 'flex' : 'none';
    });
}

// Exécuter au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationCount();
});
// Gestion des menus déroulants
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const menu = this.nextElementSibling;
            const icon = this.querySelector('.nav-dropdown-icon');
            
            // Basculer l'affichage du menu
            menu.classList.toggle('show');
            
            // Rotation de l'icône
            icon.classList.toggle('rotated');
            
            // Fermer les autres menus ouverts si nécessaire
            dropdownToggles.forEach(otherToggle => {
                if (otherToggle !== toggle) {
                    otherToggle.nextElementSibling.classList.remove('show');
                    otherToggle.querySelector('.nav-dropdown-icon').classList.remove('rotated');
                }
            });
        });
    });
});
    </script>
</body>
</html>