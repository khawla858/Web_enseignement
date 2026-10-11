<?php
session_start();
require 'config.php';

// 1. Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur') {
    header("Location: login.php");
    exit();
}

// 2. Récupération du département du professeur
$user_id = $_SESSION['user_id'];
try {
    // Récupérer le nom du département
    $sql_departement = "SELECT d.nom 
                       FROM departements d
                       JOIN professeurs_departements pd ON d.id = pd.departement_id
                       WHERE pd.professeur_id = ?";
    $stmt = $pdo->prepare($sql_departement);
    $stmt->execute([$user_id]);
    $departement = $stmt->fetchColumn();

    if (!$departement) {
        die("Aucun département trouvé pour ce professeur.");
    }

    // 3. Récupération des UE du département
    $sql_ue = "SELECT code_ue, nom_ue, volume_cm, volume_td, volume_tp, semestre
              FROM unites_enseignement
              WHERE specialite = ?
              ORDER BY semestre, code_ue";
    $stmt = $pdo->prepare($sql_ue);
    $stmt->execute([$departement]);
    $unites = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Après avoir récupéré les modules
$modules = $stmt->fetchAll();

// Définir l'année universitaire (simplement)
$annee_univ = "2025-2026"; 

// Récupérer dynamiquement le département du professeur
$stmtDept = $pdo->prepare("
    SELECT d.nom 
    FROM departements d
    JOIN professeurs_departements pd ON d.id = pd.departement_id
    WHERE pd.professeur_id = ?
");
$stmtDept->execute([$_SESSION['user_id']]);
$departement_nom = $stmtDept->fetchColumn();

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
        .header {
            background-color: #3a86ff;
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
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

        
        /* Header Prof Style */
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
    color: #dfe6e9; /* gris très clair pour contraste */
}

/* Bouton retour */
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

    </style>
</head>
<body>
<div class="header-prof">
    <div class="header-content">
    <p>Professeur : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>

        <div class="header-text">
            <h2> les unités d'enseignement</h2>
            <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Département <?= htmlspecialchars($departement_nom) ?></p>
        </div>

        <a href="professeur.php" class="btn-retour">Retour</a>
    </div>
</div>

    <div class="container">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-list-check"></i> Liste des UE
                </h2>
                <span class="badge bg-primary">
                    Total : <?= count($unites) ?> UE
                </span>
            </div>

            <?php if (count($unites) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Nom de l'UE</th>
                                <th class="text-center">CM (h)</th>
                                <th class="text-center">TD (h)</th>
                                <th class="text-center">TP (h)</th>
                                <th>Semestre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unites as $ue): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($ue['code_ue']) ?></td>
                                <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                                <td class="volume-cell"><?= htmlspecialchars($ue['volume_cm']) ?></td>
                                <td class="volume-cell"><?= htmlspecialchars($ue['volume_td']) ?></td>
                                <td class="volume-cell"><?= htmlspecialchars($ue['volume_tp']) ?></td>
                                <td>
                                    <span class="badge badge-semestre">
                                        <?= htmlspecialchars($ue['semestre']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aucune unité d'enseignement trouvée pour votre département.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="mt-5 py-3 text-center text-muted">
        <p>© <?= date('Y') ?> - Gestion des UE - Département <?= htmlspecialchars($departement) ?></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>