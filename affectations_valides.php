<?php
session_start();
require_once 'config.php'; // Connexion à la base de données

// --- Vérification d'authentification ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Récupération des informations de l'utilisateur ---
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'coordonnateur') {
    header("Location: login.php");
    exit();
}

// --- Paramètres GET (avec valeurs par défaut) ---
$semestre = $_GET['semestre'] ?? 'S1';
$annee_univ = $_GET['annee'] ?? date('Y') . '-' . (date('Y') + 1);

// --- Requête SQL pour récupérer les affectations validées ---
$sql = "
    SELECT 
        ue.code_ue,
        ue.nom_ue,
        ue.volume_cm,
        ue.volume_td,
        ue.volume_tp,
        ue.semestre,
        a.type_enseignement,
        a.annee_univ,
        u.nom AS enseignant_nom,
        u.prenom AS enseignant_prenom
    FROM affectations a
    JOIN unites_enseignement ue ON a.ue_id = ue.id
    JOIN users u ON a.user_id = u.id
    JOIN filieres f ON ue.filiere = f.nom
    JOIN coordinateurs_filieres cf ON cf.filiere_id = f.id
    WHERE a.valide = TRUE
      AND cf.user_id = ?
      AND a.annee_univ = ?
      AND ue.semestre = ?
    ORDER BY ue.semestre, ue.code_ue
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $annee_univ, $semestre);
$stmt->execute();
$result = $stmt->get_result();
$affectations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Calcul des statistiques ---
$stats = [
    'total' => count($affectations),
    'cm' => 0,
    'td' => 0,
    'tp' => 0,
    'enseignants' => [],
    'ues' => [],
];

foreach ($affectations as $aff) {
    switch ($aff['type_enseignement']) {
        case 'CM': $stats['cm']++; break;
        case 'TD': $stats['td']++; break;
        case 'TP': $stats['tp']++; break;
    }

    $enseignant = $aff['enseignant_prenom'] . ' ' . $aff['enseignant_nom'];
    if (!in_array($enseignant, $stats['enseignants'])) {
        $stats['enseignants'][] = $enseignant;
    }

    $ue = $aff['code_ue'] . ' - ' . $aff['nom_ue'];
    if (!in_array($ue, $stats['ues'])) {
        $stats['ues'][] = $ue;
    }
}

$stats['enseignants_count'] = count($stats['enseignants']);
$stats['ues_count'] = count($stats['ues']);

// --- Fermeture connexion ---
$conn->close();
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affectations Validées</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Header Prof Style */
        .header-prof {
            background-color: #3a86ff;
            padding: 30px;
            margin-bottom: 30px;
            color: #ffffff;
            width: 100%;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1320px;
            margin: 0 auto;
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

        /* Ajustement du conteneur principal */
        .main-container {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Style pour le bouton d'export */
        .export-btn {
            display: inline-block;
            background-color: #28a745; /* Vert Excel */
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
            transition: background 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .export-btn i {
            margin-right: 8px;
        }

        .export-btn:hover {
            background-color: #218838; /* Vert plus foncé */
            transform: scale(1.03);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="header-prof">
        <div class="header-content">
            <p>Coordonnateur : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>

            <div class="header-text">
                <h2>Affectations validées</h2>
                <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Semestre <?= htmlspecialchars($semestre) ?></p>
            </div>

            <a href="coordonnateur.php" class="btn-retour">Retour</a>
        </div>
    </div>
    
    <div class="main-container">
        <!-- Filtres -->
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-3">
                <select name="semestre" class="form-select">
                    <option value="S1" <?= $semestre === 'S1' ? 'selected' : '' ?>>Semestre 1</option>
                    <option value="S2" <?= $semestre === 'S2' ? 'selected' : '' ?>>Semestre 2</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="annee" class="form-control" 
                       value="<?= htmlspecialchars($annee_univ) ?>" placeholder="2023-2024">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
            <div class="col-md-2">
                <a href="utils/export_data.php?type=affectations&semestre=<?= urlencode($semestre) ?>&annee=<?= urlencode($annee_univ) ?>" 
                   class="btn btn-success export-btn">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
            </div>
        </form>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Affectations</h5>
                        <p class="card-text display-4"><?= $stats['total'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Enseignants</h5>
                        <p class="card-text display-4"><?= $stats['enseignants_count'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title">CM</h5>
                        <p class="card-text display-4"><?= $stats['cm'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title">TD</h5>
                        <p class="card-text display-4"><?= $stats['td'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title">TP</h5>
                        <p class="card-text display-4"><?= $stats['tp'] ?></p>
                    </div>
                </div>
            </div>
        </div>

       <!-- Tableau des affectations -->
<?php if (count($affectations) > 0): ?>
<table id="affectationsTable" class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Code UE</th>
            <th>Nom UE</th>
            <th>Semestre</th>
            <th>Type</th>
            <th>Volume CM</th>
            <th>Volume TD</th>
            <th>Volume TP</th>
            <th>Enseignant</th>
            <th>Année Univ</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($affectations as $ue): ?>
        <tr>
            <td><?= htmlspecialchars($ue['code_ue']) ?></td>
            <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
            <td><?= htmlspecialchars($ue['semestre']) ?></td>
            <td><?= htmlspecialchars($ue['type_enseignement']) ?></td>
            <td><?= $ue['type_enseignement'] === 'CM' ? $ue['volume_cm'] : '-' ?></td>
            <td><?= $ue['type_enseignement'] === 'TD' ? $ue['volume_td'] : '-' ?></td>
            <td><?= $ue['type_enseignement'] === 'TP' ? $ue['volume_tp'] : '-' ?></td>
            <td><?= htmlspecialchars($ue['enseignant_prenom'] . ' ' . $ue['enseignant_nom']) ?></td>
            <td><?= htmlspecialchars($ue['annee_univ']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="alert alert-info">Aucune affectation validée pour votre filière pour l'année <?= htmlspecialchars($annee_univ) ?>.</p>
<?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#affectationsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                order: [[0, 'asc']]
            });
        });
    </script>
</body>
</html>