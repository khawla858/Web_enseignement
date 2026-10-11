<?php
session_start();
require_once 'config.php';
/*
// Vérification de l'authentification et du rôle professeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur'||$_SESSION['role'] != 'chef_departement'||$_SESSION['role'] != 'coordonateur') {
    header('Location: login.php');
    exit();
}*/
$professeur_id = $_SESSION['user_id'];
$professeur_nom = $_SESSION['nom'] ?? '';
$professeur_prenom = $_SESSION['prenom'] ?? '';

try {
    // Récupération de l'historique des affectations du professeur
    $sql = "
        SELECT 
            a.annee_univ,
            ue.code_ue,
            ue.nom_ue,
            ue.volume_cm,
            ue.volume_td,
            ue.volume_tp,
            a.type_enseignement,
            (ue.volume_cm + ue.volume_td + ue.volume_tp) AS charge_horaire,
            a.date_affectation
        FROM affectations a
        JOIN unites_enseignement ue ON a.ue_id = ue.id
        WHERE a.user_id = ?
        ORDER BY a.annee_univ DESC, a.date_affectation DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur de préparation: " . $conn->error);
    }

    $stmt->bind_param("i", $professeur_id);
    if (!$stmt->execute()) {
        throw new Exception("Erreur d'exécution: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $affectations = $result->fetch_all(MYSQLI_ASSOC);

    // Calcul de la charge horaire totale par année
    $charges_annuelles = [];
    foreach ($affectations as $affectation) {
        $annee = $affectation['annee_univ'];
        if (!isset($charges_annuelles[$annee])) {
            $charges_annuelles[$annee] = 0;
        }
        $charges_annuelles[$annee] += $affectation['charge_horaire'];
    }

    $stmt->close();
} catch (Exception $e) {
    $error_message = "Erreur lors de la récupération des données: " . $e->getMessage();
    $affectations = [];
    $charges_annuelles = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique de mes Affectations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .header-prof {
            background-color: #3a86ff;
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        .stat-card {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .table-responsive {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            padding: 1rem;
        }
        .volume-cell {
            text-align: center;
            font-weight: 500;
        }
        .cm { color: #0d6efd; }
        .td { color: #198754; }
        .tp { color: #fd7e14; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container py-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-octagon"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <div class="header-prof">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-journal-bookmark"></i> Historique de mes Affectations</h1>
                    <p class="mb-0">Professeur <?= htmlspecialchars($professeur_nom) ?> <?= htmlspecialchars($professeur_prenom) ?></p>
                </div>
              
                <a href="<?php 
    if ($_SESSION['role'] === 'vacataire') {
        echo 'vacataire.php';
    
    } elseif ($_SESSION['role'] === 'professeur') {
        echo 'professeur.php';
    } 
    
     elseif ($_SESSION['role'] === 'chef_departement') {
        echo 'chef_departement.php';
    }  elseif ($_SESSION['role'] === 'coordonnateur') {
        echo 'coordonnateur.php';
    } 
    
    else {
        echo 'login.php';
    }
?>" class="btn btn-light">  <i class="bi bi-arrow-left"></i> Retour</a>
            </div>
        </div>

        <!-- Statistiques par année -->
        <div class="row mb-4">
            <?php foreach ($charges_annuelles as $annee => $charge): ?>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3><i class="bi bi-calendar3"></i> <?= htmlspecialchars($annee) ?></h3>
                        <p class="fs-4">Charge totale: <span class="badge bg-primary"><?= $charge ?>h</span></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tableau des affectations -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Année</th>
                        <th>Code UE</th>
                        <th>Unité d'Enseignement</th>
                        <th class="text-center">CM</th>
                        <th class="text-center">TD</th>
                        <th class="text-center">TP</th>
                        <th class="text-center">Total</th>
                        <th>Type</th>
                        <th>Date Affectation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affectations as $affectation): ?>
                        <tr>
                            <td><?= htmlspecialchars($affectation['annee_univ']) ?></td>
                            <td><?= htmlspecialchars($affectation['code_ue']) ?></td>
                            <td><?= htmlspecialchars($affectation['nom_ue']) ?></td>
                            <td class="volume-cell cm"><?= htmlspecialchars($affectation['volume_cm']) ?>h</td>
                            <td class="volume-cell td"><?= htmlspecialchars($affectation['volume_td']) ?>h</td>
                            <td class="volume-cell tp"><?= htmlspecialchars($affectation['volume_tp']) ?>h</td>
                            <td class="volume-cell total"><?= htmlspecialchars($affectation['charge_horaire']) ?>h</td>
                            <td><?= htmlspecialchars($affectation['type_enseignement']) ?></td>
                            <td><?= date('d/m/Y', strtotime($affectation['date_affectation'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($affectations)): ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> Aucune affectation enregistrée dans votre historique.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
