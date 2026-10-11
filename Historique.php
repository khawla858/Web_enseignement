<?php
session_start();
require_once 'config.php'; // Connexion à la BDD

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'chef_departement' && $_SESSION['role'] !== 'coordonnateur')) {
    header('Location: login.php');
    exit();
}

$chef_departement_id = $_SESSION['user_id'];
// ===== VÉRIFICATION DU DÉPARTEMENT ===== //
$req_dep = $conn->prepare("SELECT departement_id FROM chefs_departement WHERE user_id = ?");
$req_dep->bind_param('i', $chef_departement_id);
$req_dep->execute();
$result_dep = $req_dep->get_result();

if ($result_dep->num_rows === 0) {
    die("<div class='alert alert-danger'>Vous n'êtes associé à aucun département. Contactez l'administrateur.</div>");
}

$departement_id = $result_dep->fetch_assoc()['departement_id'];
$req_dep->close();

// Récupération du nom du département pour l'affichage
$stmt_dep_name = $conn->prepare("SELECT nom FROM departements WHERE id = ?");
$stmt_dep_name->bind_param('i', $departement_id);
$stmt_dep_name->execute();
$result_dep_name = $stmt_dep_name->get_result();
$departement_name = $result_dep_name->fetch_assoc()['nom'];
$stmt_dep_name->close();

// Filtres GET
$annee_univ = $_GET['annee_univ'] ?? '';
$enseignant_nom = $_GET['enseignant_nom'] ?? '';
$ue_code = $_GET['ue_code'] ?? '';

// Construction de la requête principale
$sql = "
    SELECT 
        ha.annee_univ,
        CONCAT(u.nom, ' ', u.prenom) AS enseignant,
        ue.code_ue,
        ue.nom_ue,
        ha.type_enseignement,
        (ue.volume_cm + ue.volume_td + ue.volume_tp) AS charge_horaire,
        ha.statut,
        a.date_affectation,
        a.valide
    FROM historique_affectations ha
    JOIN users u ON ha.user_id = u.id
    JOIN unites_enseignement ue ON ha.ue_id = ue.id
    JOIN professeurs_departements pd ON u.id = pd.professeur_id
    JOIN chefs_departement cd ON cd.departement_id = pd.departement_id
    LEFT JOIN affectations a 
        ON a.user_id = ha.user_id 
        AND a.ue_id = ha.ue_id 
        AND a.annee_univ = ha.annee_univ
    WHERE cd.user_id = ?
";

// Initialisation des paramètres
$types = "i";
$params = [$chef_departement_id];

// Ajout conditionnel des filtres
if (!empty($annee_univ)) {
    $sql .= " AND ha.annee_univ = ?";
    $types .= "s";
    $params[] = $annee_univ;
}
if (!empty($enseignant_nom)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
    $types .= "ss";
    $like_nom = '%' . $enseignant_nom . '%';
    $params[] = $like_nom;
    $params[] = $like_nom;
}
if (!empty($ue_code)) {
    $sql .= " AND ue.code_ue LIKE ?";
    $types .= "s";
    $params[] = '%' . $ue_code . '%';
}

$sql .= " ORDER BY ha.annee_univ DESC, u.nom, u.prenom";

// Préparation et exécution
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erreur de préparation : " . $conn->error);
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    die("Erreur d'exécution : " . $stmt->error);
}

$result = $stmt->get_result();
$historique = $result->fetch_all(MYSQLI_ASSOC);

// Liste des années pour le filtre
$annees_univ = [];
$annees_query = $conn->query("SELECT DISTINCT annee_univ FROM historique_affectations ORDER BY annee_univ DESC");
if ($annees_query) {
    while ($row = $annees_query->fetch_assoc()) {
        $annees_univ[] = $row['annee_univ'];
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Affectations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #3a86ff;
            --primary-dark: #2563eb;
            --light-bg: #f8f9fa;
            --text-color: #333;
            --light-text: #6c757d;
            --white: #ffffff;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            margin: 0;
            padding: 0;
        }

        .header-prof {
            background-color: var(--primary-color);
            padding: 30px;
            border-radius: 10px;
            margin: 20px;
            color: var(--white);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-text h1 {
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
            color: var(--primary-color);
            padding: 10px 20px;
            font-weight: bold;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.3s, color 0.3s;
        }

        .btn-retour:hover {
            background-color: var(--primary-dark);
            color: white;
        }

        .container {
            padding: 20px;
        }

        .filter-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .refused { 
            background-color: #ffdddd; 
        }

        .form-filter { 
            background-color: #f8f9fa; 
            padding: 20px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        th {
            background-color: var(--primary-color);
            color: var(--white);
        }

        tr:hover {
            background-color: #f1f8ff;
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

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="header-prof">
        <div class="header-content">
            <div class="header-text">
                <h1>Historique des Affectations</h1>
                <p>Département <?= htmlspecialchars($departement_name ?? 'Inconnu') ?></p>
            </div>
            <a href="chef_departement.php" class="btn-retour">Retour</a>
        </div>
    </div>

    <div class="container">
        <!-- Formulaire de filtres -->
        <div class="filter-card">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="annee_univ" class="form-label">Année universitaire</label>
                    <select name="annee_univ" id="annee_univ" class="form-select">
                        <option value="">-- Toutes les années --</option>
                        <?php foreach ($annees_univ as $annee): ?>
                            <option value="<?= htmlspecialchars($annee) ?>" <?= $annee == $annee_univ ? 'selected' : '' ?>>
                                <?= htmlspecialchars($annee) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="enseignant_nom" class="form-label">Enseignant</label>
                    <input type="text" name="enseignant_nom" id="enseignant_nom" class="form-control" 
                           value="<?= htmlspecialchars($enseignant_nom ?? '') ?>" placeholder="Nom ou prénom">
                </div>
                <div class="col-md-3">
                    <label for="ue_code" class="form-label">Code UE</label>
                    <input type="text" name="ue_code" id="ue_code" class="form-control" 
                           value="<?= htmlspecialchars($ue_code ?? '') ?>" placeholder="Code UE">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="historique_affectations.php" class="btn btn-secondary ms-2">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Message si aucune donnée -->
        <?php if (empty($historique)): ?>
            <div class="alert alert-warning">Aucune affectation trouvée pour les filtres sélectionnés.</div>
        <?php endif; ?>

        <!-- Tableau des résultats -->
        <div class="table-responsive">
            <table id="historiqueTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Année Univ.</th>
                        <th>Enseignant</th>
                        <th>Code UE</th>
                        <th>UE</th>
                        <th>Type</th>
                        <th>Charge Horaire</th>
                        <th>Statut</th>
                        <th>Date Affectation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $affectation): ?>
                        <tr class="<?= $affectation['statut'] === 'refuse' ? 'refused' : '' ?>">
                            <td><?= htmlspecialchars($affectation['annee_univ']) ?></td>
                            <td><?= htmlspecialchars($affectation['enseignant']) ?></td>
                            <td><?= htmlspecialchars($affectation['code_ue']) ?></td>
                            <td><?= htmlspecialchars($affectation['nom_ue']) ?></td>
                            <td><?= htmlspecialchars($affectation['type_enseignement']) ?></td>
                            <td><?= htmlspecialchars($affectation['charge_horaire']) ?>h</td>
                            <td><?= htmlspecialchars($affectation['statut'] ?? 'non spécifié') ?></td>
                            <td><?= $affectation['date_affectation'] ? date('d/m/Y H:i', strtotime($affectation['date_affectation'])) : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="utils/export_data.php?annee_univ=<?= urlencode($annee_univ) ?>&enseignant_nom=<?= urlencode($enseignant_nom) ?>&ue_code=<?= urlencode($ue_code) ?>" 
           class="btn btn-success">
            <i class="bi bi-file-excel"></i> Exporter en Excel
        </a>
    </div>

    <!-- Scripts JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#historiqueTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                }
            });
        });
    </script>
</body>
</html>