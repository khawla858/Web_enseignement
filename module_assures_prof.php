<?php
session_start();
require_once 'config.php'; // établit la connexion $conn

// Vérification de l'authentification et du rôle professeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur' && $_SESSION['role'] !== 'chef_departement' && $_SESSION['role']!=='coordonnateur' && $_SESSION['role']!=='vacataire'){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les modules assurés
$stmt = $conn->prepare("
    SELECT ue.code_ue, ue.nom_ue, a.type_enseignement,
           ue.volume_cm, ue.volume_td, ue.volume_tp,
           ue.semestre
    FROM affectations a
    JOIN unites_enseignement ue ON a.ue_id = ue.id
    WHERE a.user_id = ? AND a.valide = 1
    ORDER BY ue.semestre, ue.code_ue
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$modules = [];
while ($row = $result->fetch_assoc()) {
    $modules[] = $row;
}
$stmt->close();

// Définir l'année universitaire (simplement)
$annee_univ = "2025-2026"; 

// Récupérer dynamiquement le département du professeur
$stmtDept = $conn->prepare("
    SELECT d.nom 
    FROM departements d
    JOIN professeurs_departements pd ON d.id = pd.departement_id
    WHERE pd.professeur_id = ?
");
$stmtDept->bind_param("i", $user_id);
$stmtDept->execute();
$resultDept = $stmtDept->get_result();
$departement_nom = $resultDept->fetch_assoc()['nom'] ?? null;
$stmtDept->close();
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Modules Assurés</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .filters {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
        .modules-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .modules-table th, .modules-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .modules-table th {
            background-color: #f2f2f2;
        }
        .btn {
            padding: 8px 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn.active {
            background-color: #3a86ff;
        }
        .summary {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }


        /* Global reset */


/* Conteneur général */
.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    
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
    justify-content: space-around;
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
            <h2>les modules assures</h2>
            <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Département <?= htmlspecialchars($departement_nom) ?></p>
        </div>

        <a href="professeur.php" class="btn-retour">Retour</a>
    </div>
</div>
<div class="container">
    <!-- Tableau des modules -->
    <table class="modules-table">
        <thead>
            <tr>
                <th>Code UE</th>
                <th>Nom UE</th>
                <th>Type</th>
                <th>Volume Horaire</th>
                <th>Semestre</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modules as $module): ?>
            <tr data-semestre="<?= $module['semestre'] ?>">
                <td><?= htmlspecialchars($module['code_ue']) ?></td>
                <td><?= htmlspecialchars($module['nom_ue']) ?></td>
                <td><?= htmlspecialchars($module['type_enseignement']) ?></td>
                <td>
                    <?php 
                    $volume = 0;
                    switch($module['type_enseignement']) {
                        case 'CM': $volume = $module['volume_cm']; break;
                        case 'TD': $volume = $module['volume_td']; break;
                        case 'TP': $volume = $module['volume_tp']; break;
                    }
                    echo $volume . 'h';
                    ?>
                </td>
                <td>S<?= $module['semestre'] ?></td>
                <td>
                    <a href="Upload_Note_prof.php?ue=<?= urlencode($module['code_ue']) ?>" class="btn">Gérer notes</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Résumé charge horaire -->
    <div class="summary">
        <h3>Résumé de ma charge horaire</h3>
        <p>Total heures: <span id="total-hours">0</span>h</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filters .btn');
    const moduleRows = document.querySelectorAll('.modules-table tbody tr');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const semestre = this.dataset.semestre;
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            moduleRows.forEach(row => {
                if (semestre === 'all' || row.dataset.semestre === semestre) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            calculateTotals();
        });
    });

    calculateTotals();

    function calculateTotals() {
        let totalHours = 0;
        document.querySelectorAll('.modules-table tbody tr:not([style*="display: none"])').forEach(row => {
            const cells = row.cells;
            totalHours += parseInt(cells[3].textContent);
        });
        document.getElementById('total-hours').textContent = totalHours;
    }
});
</script>

</body>
</html>