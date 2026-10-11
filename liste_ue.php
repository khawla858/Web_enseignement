<?php
session_start();
require 'config.php'; // ce fichier doit définir $conn (mysqli)

// Vérification du rôle chef de département
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef_departement') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Trouver le département du chef via professeurs_departements
$sql_departement = "SELECT d.nom 
                   FROM departements d
                   JOIN chefs_departement cd ON d.id = cd.departement_id
                   WHERE cd.user_id = ?";


if ($stmt = $conn->prepare($sql_departement)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($departement);
    $stmt->fetch();
    $stmt->close();

    if (!$departement) {
        die("Aucun département trouvé pour ce chef. Vérifiez votre association dans professeurs_departements.");
    }
} else {
    die("Erreur préparation : " . $conn->error);
}

// Récupérer les UE de ce département
$sql = "SELECT ue.*, CONCAT(u.nom, ' ', u.prenom) AS responsable_nom 
        FROM unites_enseignement ue 
        LEFT JOIN users u ON ue.responsable_id = u.id
        WHERE ue.specialite = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $departement);
    $stmt->execute();
    $result = $stmt->get_result();
    $unites = [];

    while ($row = $result->fetch_assoc()) {
        $unites[] = $row;
    }

    $stmt->close();
} else {
    die("Erreur préparation : " . $conn->error);
}

// Définir l’année universitaire
$annee_univ = "2025-2026";
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unités d'Enseignement - <?= htmlspecialchars($departement) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
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
        .btn-return {
            background-color:rgb(79, 116, 177);
            border: 1px solid white;
        }

        .btn-return:hover {
            background: blue;
            color: var(--primary-color) !important;
        }
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
        }
        h1 {
            color: #2c3e50;
            font-weight: 600;
        }
        .badge-semestre {
            font-size: 0.9em;
            background-color: #6c757d;
        }
        .volume-cell {
            font-weight: 500;
            text-align: center;
        }
        .main-content {
            padding: 30px;
        }
    </style>
</head>
<body>
<div class="main-content">
        <div class="header-prof">
            <div class="header-content">
                <div class="header-text">
                    <h2>Gestion des Unités d'Enseignement</h2>
                    <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Département <?= htmlspecialchars($departement) ?></p>
                </div>
                <div>
                    <p>Chef de département : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>
                    <a href="chef_departement.php" class="btn btn-return">
                    <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                </a>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-list-check"></i> Liste des UE - Département <?= htmlspecialchars($departement) ?>
                </h2>
                <div>
                    <div class="dropdown d-inline">
    <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="bi bi-download"></i> Exporter
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="utils/export_data.php?type=ues&filiere=<?= urlencode($departement) ?>">Excel</a></li>
    </ul>
</div>
                    <span class="badge bg-primary ms-2">
                        Total : <?= count($unites) ?> UE
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Code UE</th>
                            <th>Nom UE</th>
                            <th class="text-center">CM (h)</th>
                            <th class="text-center">TD (h)</th>
                            <th class="text-center">TP (h)</th>
                            <th>Semestre</th>
                            <th>Responsable</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($unites) > 0): ?>
                            <?php foreach ($unites as $ue): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($ue['code_ue']) ?></td>
                                    <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                                    <td class="volume-cell"><?= htmlspecialchars($ue['volume_cm']) ?></td>
                                    <td class="volume-cell"><?= htmlspecialchars($ue['volume_td']) ?></td>
                                    <td class="volume-cell"><?= htmlspecialchars($ue['volume_tp']) ?></td>
                                    <td>
                                        <span class="badge badge-semestre">
                                            S<?= htmlspecialchars($ue['semestre']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($ue['responsable_nom'] ?? 'Non assigné') ?></td>
                                    <td>
                                        <a href="affecter.php?ue_id=<?= $ue['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Affecter">
                                            <i class="bi bi-person-plus"></i>
                                        </a>
                                        <a href="details_ue.php?ue_id=<?= $ue['id'] ?>" class="btn btn-sm btn-outline-info" title="Détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> Aucune unité d'enseignement trouvée pour votre département.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>