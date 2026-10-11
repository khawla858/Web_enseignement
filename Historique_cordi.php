<?php
session_start();
require_once 'config.php';

// Vérification connexion et rôle
if ($conn->connect_error) die("Erreur connexion : " . $conn->connect_error);
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'chef_departement' && $_SESSION['role'] !== 'coordonnateur')) {
    header('Location: login.php');
    exit();
}

// Récupération du département associé à l'utilisateur
$departement = '';
$user_id = $_SESSION['user_id'];
try {
    $sql_departement = "SELECT d.nom 
                       FROM departements d
                       JOIN professeurs_departements pd ON d.id = pd.departement_id
                       WHERE pd.professeur_id = ?";
    
    $stmt = $conn->prepare($sql_departement);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $departement = $result->fetch_assoc()['nom'] ?? '';
    $stmt->close();
} catch (Exception $e) {
    die("Erreur récupération département : " . $e->getMessage());
}

// Récupération des filtres
$annee_univ = $_GET['annee_univ'] ?? '';
$enseignant_nom = $_GET['enseignant_nom'] ?? '';
$ue_code = $_GET['ue_code'] ?? '';
$show_vacantes = isset($_GET['show_vacantes']) ? 1 : 0;
$show_vacataires = isset($_GET['show_vacataires']) ? 1 : 0;

// REQUÊTE PRINCIPALE ADAPTÉE À VOTRE STRUCTURE
$sql = "SELECT 
          a.annee_univ,
          CONCAT(u.nom, ' ', u.prenom) AS enseignant,
          u.role AS type_utilisateur,
          ue.code_ue,
          ue.nom_ue,
          a.type_enseignement,
          (ue.volume_cm + ue.volume_td + ue.volume_tp) AS charge_horaire,
          IF(a.valide = 1, 'validé', 'refusé') AS statut,
          a.date_affectation,
          ue.specialite
        FROM affectations a
        JOIN users u ON a.user_id = u.id
        JOIN unites_enseignement ue ON a.ue_id = ue.id
        WHERE 1=1";

// Construction dynamique des filtres
$params = [];
$types = '';

if (!empty($annee_univ)) {
    $sql .= " AND a.annee_univ = ?";
    $types .= 's';
    $params[] = $annee_univ;
}

if (!empty($enseignant_nom)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
    $types .= 'ss';
    $like_nom = "%$enseignant_nom%";
    $params[] = $like_nom;
    $params[] = $like_nom;
}

if (!empty($ue_code)) {
    $sql .= " AND ue.code_ue LIKE ?";
    $types .= 's';
    $params[] = "%$ue_code%";
}

if ($show_vacataires) {
    $sql .= " AND u.role = 'vacataire'";
}

$sql .= " ORDER BY a.date_affectation DESC";

// Exécution avec prepared statement
try {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $historique = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        throw new Exception("Erreur préparation requête : " . $conn->error);
    }
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupération des UE vacantes si demandé
$ue_vacantes = [];
if ($show_vacantes) {
    $sql_vacantes = "SELECT ue.code_ue, ue.nom_ue, 
                    ue.volume_cm + ue.volume_td + ue.volume_tp AS charge_horaire,
                    ue.specialite
                    FROM unites_enseignement ue
                    LEFT JOIN affectations a ON ue.id = a.ue_id AND a.annee_univ = ?
                    WHERE a.ue_id IS NULL";
    
    try {
        $stmt_vacantes = $conn->prepare($sql_vacantes);
        if ($stmt_vacantes) {
            $annee_vacantes = empty($annee_univ) ? date('Y') . '-' . (date('Y')+1) : $annee_univ;
            $stmt_vacantes->bind_param('s', $annee_vacantes);
            $stmt_vacantes->execute();
            $result_vacantes = $stmt_vacantes->get_result();
            $ue_vacantes = $result_vacantes->fetch_all(MYSQLI_ASSOC);
            $stmt_vacantes->close();
        }
    } catch (Exception $e) {
        die("Erreur récupération UE vacantes : " . $e->getMessage());
    }
}

// Récupération des années pour le filtre
$annees_univ = [];
try {
    $annees_result = $conn->query("SELECT DISTINCT annee_univ FROM affectations ORDER BY annee_univ DESC");
    if ($annees_result) {
        while ($row = $annees_result->fetch_assoc()) {
            $annees_univ[] = $row['annee_univ'];
        }
    }
} catch (Exception $e) {
    die("Erreur récupération années : " . $e->getMessage());
}

// Requête pour les statistiques (graphiques)
$stats = [];
try {
    // Stats par type d'enseignement
    $sql_stats = "SELECT 
                    a.type_enseignement,
                    SUM(ue.volume_cm) as total_cm,
                    SUM(ue.volume_td) as total_td,
                    SUM(ue.volume_tp) as total_tp
                  FROM affectations a
                  JOIN unites_enseignement ue ON a.ue_id = ue.id
                  WHERE a.annee_univ = ?
                  GROUP BY a.type_enseignement";
    
    $stmt_stats = $conn->prepare($sql_stats);
    $annee_stats = empty($annee_univ) ? date('Y') . '-' . (date('Y')+1) : $annee_univ;
    $stmt_stats->bind_param('s', $annee_stats);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    $stats = $result_stats->fetch_all(MYSQLI_ASSOC);
    $stmt_stats->close();
} catch (Exception $e) {
    // On continue sans les stats si erreur
    $stats = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Affectations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
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
        .btn-return {
            background-color: rgb(79, 116, 177);
            border: 1px solid white;
        }
        .btn-return:hover {
            background: blue;
            color: var(--primary-color) !important;
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
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
        }
        .form-filter { 
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px; 
        }
        .chart-container { margin: 30px 0; }
        .badge-vacataire { background-color: #28a745; }
        .badge-vacante { background-color: #ffc107; color: #000; }
        .refused { background-color: #ffdddd; }
        .vacante { background-color: #fff3cd; }
        .vacataire { background-color: #d4edda; }
        .main-content {
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Header identique à l'autre page -->
        <div class="header-prof">
            <div class="header-content">
                <div class="header-text">
                    <h2>Historique des Affectations</h2>
                    <p>Année universitaire <?= htmlspecialchars($annee_univ ?: date('Y').'-'.(date('Y')+1)) ?>/p>
                </div>
                <div>
                    <p><?= ucfirst($_SESSION['role']) ?> : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>
                    <a href="coordonnateur.php" class="btn btn-return text-white">
                        <i class="bi bi-arrow-left me-2"></i>Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>

        <!-- Formulaire de filtres amélioré -->
        <div class="form-filter">
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
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_vacantes" id="show_vacantes" <?= $show_vacantes ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_vacantes">UE Vacantes</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_vacataires" id="show_vacataires" <?= $show_vacataires ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_vacataires">Vacataires</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="historique_affectations.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Graphiques -->
        <div class="row chart-container">
            <div class="col-md-6">
                <canvas id="chartChargeHoraire"></canvas>
            </div>
            <div class="col-md-6">
                <canvas id="chartRepartitionUE"></canvas>
            </div>
        </div>

        <!-- Tableau des résultats -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-list-check"></i> Liste des Affectations
                </h2>
                <div>
                    <span class="badge bg-primary ms-2">
                        Total : <?= count($historique) + ($show_vacantes ? count($ue_vacantes) : 0) ?> éléments
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table id="historiqueTable" class="table table-striped table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Année Univ.</th>
                            <th>Enseignant</th>
                            <th>Type</th>
                            <th>Code UE</th>
                            <th>UE</th>
                            <th>Spécialité</th>
                            <th>Type Enseign.</th>
                            <th>Charge Horaire</th>
                            <th>Statut</th>
                            <th>Date Affectation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique as $affectation): ?>
                            <tr class="<?= $affectation['statut'] === 'refusé' ? 'refused' : '' ?> <?= $affectation['type_utilisateur'] === 'vacataire' ? 'vacataire' : '' ?>">
                                <td><?= htmlspecialchars($affectation['annee_univ']) ?></td>
                                <td>
                                    <?= htmlspecialchars($affectation['enseignant']) ?>
                                    <?= $affectation['type_utilisateur'] === 'vacataire' ? '<span class="badge badge-vacataire ms-2">Vacataire</span>' : '' ?>
                                </td>
                                <td><?= htmlspecialchars($affectation['type_utilisateur']) ?></td>
                                <td><?= htmlspecialchars($affectation['code_ue']) ?></td>
                                <td><?= htmlspecialchars($affectation['nom_ue']) ?></td>
                                <td><?= htmlspecialchars($affectation['specialite']) ?></td>
                                <td><?= htmlspecialchars($affectation['type_enseignement']) ?></td>
                                <td><?= htmlspecialchars($affectation['charge_horaire']) ?>h</td>
                                <td><?= htmlspecialchars($affectation['statut']) ?></td>
                                <td><?= $affectation['date_affectation'] ? date('d/m/Y H:i', strtotime($affectation['date_affectation'])) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if ($show_vacantes): ?>
                            <?php foreach ($ue_vacantes as $ue): ?>
                                <tr class="vacante">
                                    <td><?= htmlspecialchars($annee_univ ?: date('Y').'-'.(date('Y')+1)) ?></td>
                                    <td colspan="2">Non attribué</td>
                                    <td><?= htmlspecialchars($ue['code_ue']) ?></td>
                                    <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                                    <td><?= htmlspecialchars($ue['specialite']) ?></td>
                                    <td>N/A</td>
                                    <td><?= htmlspecialchars($ue['charge_horaire']) ?>h</td>
                                    <td><span class="badge badge-vacante">Vacante</span></td>
                                    <td>N/A</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Boutons d'export -->
            <div class="mt-3 d-flex gap-2">
                <a href="exporter.php?type=affectations&annee_univ=<?= urlencode($annee_univ) ?>&enseignant_nom=<?= urlencode($enseignant_nom ?? '') ?>&ue_code=<?= urlencode($ue_code ?? '') ?>" 
                   class="btn btn-success">
                    <i class="bi bi-download"></i> Exporter les Affectations (Excel)
                </a>
                <?php if ($show_vacantes): ?>
                    <a href="exporter.php?type=vacantes&annee_univ=<?= urlencode($annee_univ) ?>" 
                       class="btn btn-warning">
                        <i class="bi bi-download"></i> Exporter les UE Vacantes (Excel)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de DataTable
            $('#historiqueTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });

            // Données pour les graphiques
            const chargeData = {
                labels: ['CM', 'TD', 'TP'],
                datasets: [{
                    label: 'Répartition des heures',
                    data: [
                        <?= array_sum(array_column($stats, 'total_cm')) ?>,
                        <?= array_sum(array_column($stats, 'total_td')) ?>,
                        <?= array_sum(array_column($stats, 'total_tp')) ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)'
                    ]
                }]
            };

            const ueData = {
                labels: <?= json_encode(array_unique(array_column(array_merge($historique, $ue_vacantes), 'specialite'))) ?>,
                datasets: [{
                    label: 'UE Affectées',
                    data: [
                        <?php 
                        $specialites = array_count_values(array_column($historique, 'specialite'));
                        foreach (array_unique(array_column(array_merge($historique, $ue_vacantes), 'specialite')) as $spec) {
                            echo ($specialites[$spec] ?? 0) . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)'
                }, {
                    label: 'UE Vacantes',
                    data: [
                        <?php 
                        $vacantes_spec = array_count_values(array_column($ue_vacantes, 'specialite'));
                        foreach (array_unique(array_column(array_merge($historique, $ue_vacantes), 'specialite')) as $spec) {
                            echo ($vacantes_spec[$spec] ?? 0) . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(255, 206, 86, 0.5)'
                }]
            };

            // Création des graphiques
            new Chart(document.getElementById('chartChargeHoraire'), {
                type: 'pie',
                data: chargeData,
                options: { responsive: true }
            });

            new Chart(document.getElementById('chartRepartitionUE'), {
                type: 'bar',
                data: ueData,
                options: { 
                    responsive: true,
                    scales: { x: { stacked: true }, y: { stacked: true } }
                }
            });
        });
    </script>
</body>
</html>