<?php
require_once 'config.php';
define('CHARGE_MINIMALE', 192);

session_start();

// Vérification du rôle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef_departement') {
    header("Location: login.php");
    exit();
}

// Récupérer le nom du département
$sql_departement = "SELECT nom FROM departements WHERE id = ?";
$stmt_dep = $conn->prepare($sql_departement);
$stmt_dep->bind_param("i", $_SESSION['departement_id']);
$stmt_dep->execute();
$result_dep = $stmt_dep->get_result();
$departement = $result_dep->fetch_assoc();
$departement_nom = $departement['nom'] ?? 'Inconnu';

// Requête pour les professeurs
$sql = "
    SELECT 
        u.id,
        u.nom,
        u.prenom,
        u.specialite,
        COALESCE(SUM(
            CASE a.type_enseignement
                WHEN 'CM' THEN ue.volume_cm
                WHEN 'TD' THEN ue.volume_td
                WHEN 'TP' THEN ue.volume_tp
                ELSE 0
            END
        ), 0) AS total_charge,
        COUNT(DISTINCT a.ue_id) AS nb_ue,
       GROUP_CONCAT(DISTINCT 
    CONCAT(ue.code_ue, ' - ', ue.nom_ue, ' (', a.type_enseignement, ') - ', 
    CASE a.type_enseignement
        WHEN 'CM' THEN ue.volume_cm
        WHEN 'TD' THEN ue.volume_td
        WHEN 'TP' THEN ue.volume_tp
        ELSE 0
    END, 'h')
    SEPARATOR '|'
) AS ues_affectees
    FROM users u
    JOIN professeurs_departements pd ON u.id = pd.professeur_id
    LEFT JOIN affectations a ON u.id = a.user_id AND a.valide = TRUE
    LEFT JOIN unites_enseignement ue ON a.ue_id = ue.id
    WHERE u.role = 'professeur' 
    AND pd.departement_id = ?
    GROUP BY u.id
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erreur de préparation de la requête: " . $conn->error);
}

$stmt->bind_param("i", $_SESSION['departement_id']);
$stmt->execute();
$result = $stmt->get_result();

$professeurs = [];
while ($row = $result->fetch_assoc()) {
    $row['total_charge'] = (int)$row['total_charge'];
    $row['nb_ue'] = (int)$row['nb_ue'];
    $row['statut'] = ($row['total_charge'] < CHARGE_MINIMALE) ? 'warning' : 'success';
    $row['ues_list'] = !empty($row['ues_affectees']) ? explode('|', $row['ues_affectees']) : [];
    $professeurs[] = $row;
}

$annee_univ = "2025-2026"; // Défini comme chaîne de caractères
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Affectations</title>
    <style>
        /* Votre CSS reste inchangé */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #3a86ff;
            color: white;
        }
        .warning {
            background-color: #ffe5e5;
        }
        .success {
            background-color: #e5ffe5;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 300px;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            padding: 10px;
            max-height: 400px;
            overflow-y: auto;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .ue-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .statut {
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 5px;
        }
        .statut-warning {
            background-color: #ff4444;
            color: white;
        }
        .statut-ok {
            background-color: #4CAF50;
            color: white;
        }
        .btn-export {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .btn-export:hover {
            background-color: #218838;
        }
        .header-prof {
            background-color: #3a86ff;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            color: #ffffff;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-text {
            text-align: left;
        }
        .header-text h2 {
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: bold;
        }
        .header-text p {
            margin-top: 5px;
            font-size: 16px;
            color: #dfe6e9;
        }
        .btn-retour {
            background-color: white;
            color: #3a86ff;
            padding: 10px 20px;
            font-weight: bold;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.3s, color 0.3s;
        }
        .btn-retour:hover {
            background-color: #2563eb;
            color: white;
        }

        .dropdown-content {
    display: none;
    position: absolute;
    background-color: #f8f9fa;
    min-width: 350px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    border-radius: 8px;
    z-index: 1000;
    padding: 15px;
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
}

.ue-item {
    padding: 10px 15px;
    margin: 5px 0;
    background-color: white;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-left: 4px solid #3a86ff;
    transition: all 0.2s ease;
}

.ue-item:hover {
    background-color: #f1f8ff;
    transform: translateX(5px);
}

.ue-code {
    font-weight: bold;
    color: #3a86ff;
}

.ue-name {
    color: #495057;
    margin: 5px 0;
}

.ue-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    margin-right: 8px;
}

.ue-type-cm {
    background-color: #e3f2fd;
    color: #1565c0;
}

.ue-type-td {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.ue-type-tp {
    background-color: #fff3e0;
    color: #ef6c00;
}

.ue-hours {
    font-size: 0.9em;
    color: #6c757d;
    margin-top: 5px;
}
    </style>
</head>
<body>
    <div class="header-prof">
        <div class="header-content">
            <div class="header-text">
            <h1>Charge Horaire des Enseignants</h1>

                <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Département <?= htmlspecialchars($departement_nom) ?></p>
            </div>
            <form method="post" action="utils/export_data.php">
    <button type="submit" class="btn-export">
        Exporter en Excel
    </button>
    <a href="chef_departement.php" class="btn-retour">Retour</a>
</form>

        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Spécialité</th>
                <th>UE Affectées</th>
                <th>Charge Totale</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($professeurs as $prof): ?>
            <tr class="<?= $prof['statut'] ?>">
                <td><?= htmlspecialchars($prof['nom']) ?></td>
                <td><?= htmlspecialchars($prof['prenom']) ?></td>
                <td><?= htmlspecialchars($prof['specialite']) ?></td>
                <td>
                    <div class="dropdown">
                        <span style="cursor:pointer;color:blue;text-decoration:underline">
                            <?= $prof['nb_ue'] ?> UE(s) - Voir détails
                        </span>
                        <div class="dropdown-content">
                            <?php if (!empty($prof['ues_list'])): ?>
                                <?php foreach ($prof['ues_list'] as $ue): ?>
                                    <div class="ue-item"><?= htmlspecialchars($ue) ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div>Aucune UE affectée</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?= $prof['total_charge'] ?> heures</td>
                <td>
                    <span class="statut statut-<?= $prof['statut'] === 'warning' ? 'warning' : 'ok' ?>">
                        <?= $prof['statut'] === 'warning' ? 'SOUS-CHARGE' : 'OK' ?>
                    </span>
                    <div style="font-size:0.8em;margin-top:5px;">
                        Minimum requis: <?= CHARGE_MINIMALE ?>h
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>