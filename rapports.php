<?php
session_start();
require_once 'config.php';

// Vérification des droits
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupération du département du chef
$stmt = $conn->prepare("SELECT cd.departement_id, d.nom AS departement_nom 
                       FROM chefs_departement cd
                       JOIN departements d ON cd.departement_id = d.id
                       WHERE cd.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("Accès refusé");
$departement = $result->fetch_assoc();

// Récupération des filtres
$report_type = $_GET['type'] ?? 'affectations';
$annee_univ = $_GET['annee_univ'] ?? date('Y').'-'.(date('Y')+1);
$filiere_id = $_GET['filiere'] ?? 'all';
$semestre = $_GET['semestre'] ?? 'all';
$statut = $_GET['statut'] ?? 'all';

// Seuil de charge minimale (configurable)
$seuil_minimum = 192; // heures

// Récupération des années disponibles
$annees = [];
$res = $conn->query("SELECT DISTINCT annee_univ FROM affectations ORDER BY annee_univ DESC");
while ($row = $res->fetch_assoc()) $annees[] = $row['annee_univ'];

// Récupération des filières du département
$filieres = [];
$stmt = $conn->prepare("SELECT id, nom FROM filieres WHERE departement_id = ?");
$stmt->bind_param("i", $departement['departement_id']);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $filieres[] = $row;

// Requêtes selon le type de rapport
$results = [];
$stats = [
    'total_ue' => 0,
    'total_heures' => 0,
    'total_enseignants' => 0,
    'sous_charges' => 0,
    'cm' => 0, 'td' => 0, 'tp' => 0
];

switch ($report_type) {
    case 'affectations':
        $sql = "SELECT a.id, u.nom, u.prenom, ue.code_ue, ue.nom_ue, f.nom AS filiere,
                       a.type_enseignement, a.valide, a.date_affectation,
                       ue.volume_cm, ue.volume_td, ue.volume_tp
                FROM affectations a
                JOIN users u ON a.user_id = u.id
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                JOIN filieres f ON ue.filiere = f.nom
                JOIN professeurs_departements pd ON u.id = pd.professeur_id
                WHERE pd.departement_id = ? AND a.annee_univ = ?";
        
        if ($filiere_id !== 'all') $sql .= " AND ue.filiere = ?";
        if ($statut !== 'all') $sql .= " AND a.valide = ?";
        
        $stmt = $conn->prepare($sql);
        $params = [$departement['departement_id'], $annee_univ];
        $types = "is";
        
        if ($filiere_id !== 'all') {
            $params[] = $filiere_id;
            $types .= "i";
        }
        if ($statut !== 'all') {
            $params[] = ($statut === 'validé') ? 1 : 0;
            $types .= "i";
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calcul des statistiques
        foreach ($results as $row) {
            $stats['total_ue']++;
            $stats['total_heures'] += ($row['volume_cm'] + $row['volume_td'] + $row['volume_tp']);
            $stats['cm'] += $row['volume_cm'];
            $stats['td'] += $row['volume_td'];
            $stats['tp'] += $row['volume_tp'];
        }
        
        // Enseignants sous-chargés
        $sql = "SELECT u.id, CONCAT(u.nom, ' ', u.prenom) AS enseignant,
                      SUM(ue.volume_cm + ue.volume_td + ue.volume_tp) AS total_heures
                FROM affectations a
                JOIN users u ON a.user_id = u.id
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                JOIN professeurs_departements pd ON u.id = pd.professeur_id
                WHERE pd.departement_id = ? AND a.annee_univ = ? AND a.valide = 1
                GROUP BY u.id
                HAVING total_heures < ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $departement['departement_id'], $annee_univ, $seuil_minimum);
        $stmt->execute();
        $stats['sous_charges'] = $stmt->get_result()->num_rows;
        break;

    case 'charges':
        $sql = "SELECT u.id, CONCAT(u.nom, ' ', u.prenom) AS enseignant,
                      SUM(ue.volume_cm) AS cm, SUM(ue.volume_td) AS td, SUM(ue.volume_tp) AS tp,
                      SUM(ue.volume_cm + ue.volume_td + ue.volume_tp) AS total
                FROM affectations a
                JOIN users u ON a.user_id = u.id
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                JOIN professeurs_departements pd ON u.id = pd.professeur_id
                WHERE pd.departement_id = ? AND a.annee_univ = ? AND a.valide = 1
                GROUP BY u.id
                ORDER BY total ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $departement['departement_id'], $annee_univ);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stats['total_enseignants'] = count($results);
        
        foreach ($results as $row) {
            $stats['total_heures'] += $row['total'];
            if ($row['total'] < $seuil_minimum) $stats['sous_charges']++;
        }
        break;

    case 'ue_vacantes':
        $sql = "SELECT ue.id, ue.code_ue, ue.nom_ue, f.nom AS filiere,
                       ue.volume_cm, ue.volume_td, ue.volume_tp
                FROM unites_enseignement ue
                JOIN filieres f ON ue.filiere= f.nom
                WHERE ue.id NOT IN (
                    SELECT ue_id FROM affectations 
                    WHERE annee_univ = ? AND valide = 1
                )
                AND f.departement_id = ?";
        
        if ($filiere_id !== 'all') $sql .= " AND ue.filiere_id = ?";
        
        $stmt = $conn->prepare($sql);
        $params = [$annee_univ, $departement['departement_id']];
        if ($filiere_id !== 'all') $params[] = $filiere_id;
        
        $types = str_repeat("s", count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($results as $row) {
            $stats['total_ue']++;
            $stats['total_heures'] += ($row['volume_cm'] + $row['volume_td'] + $row['volume_tp']);
        }
        break;

    case 'historique':
        $sql = "SELECT a.annee_univ, 
                       COUNT(DISTINCT a.id) AS nb_affectations,
                       SUM(ue.volume_cm + ue.volume_td + ue.volume_tp) AS total_heures,
                       COUNT(DISTINCT a.user_id) AS nb_enseignants
                FROM affectations a
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                JOIN professeurs_departements pd ON a.user_id = pd.professeur_id
                WHERE pd.departement_id = ?
                GROUP BY a.annee_univ
                ORDER BY a.annee_univ DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $departement['departement_id']);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - <?= $departement['departement_nom'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .report-card { transition: all 0.3s; }
        .report-card:hover { transform: scale(1.05); }
        .under-min { background-color: #ffdddd !important; }
        .stat-card { border-left: 4px solid; }
        .stat-card.cm { border-color: #0d6efd; }
        .stat-card.td { border-color: #198754; }
        .stat-card.tp { border-color: #ffc107; }
        .badge-en-attente { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-bar-chart-line"></i> Rapports - <?= htmlspecialchars($departement['departement_nom']) ?>
            </h1>
            <div>
                <span class="badge bg-primary">Année: <?= htmlspecialchars($annee_univ) ?></span>
            </div>
        </div>

        <!-- Sélection du rapport -->
        <div class="row mb-4">
            <div class="col">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="?type=affectations&annee_univ=<?= $annee_univ ?>" 
                               class="btn btn-outline-primary report-card <?= $report_type === 'affectations' ? 'active' : '' ?>">
                                <i class="bi bi-list-check"></i> Affectations
                            </a>
                            <a href="?type=charges&annee_univ=<?= $annee_univ ?>" 
                               class="btn btn-outline-primary report-card <?= $report_type === 'charges' ? 'active' : '' ?>">
                                <i class="bi bi-clock-history"></i> Charges
                            </a>
                            <a href="?type=ue_vacantes&annee_univ=<?= $annee_univ ?>" 
                               class="btn btn-outline-primary report-card <?= $report_type === 'ue_vacantes' ? 'active' : '' ?>">
                                <i class="bi bi-file-earmark-excel"></i> UE Vacantes
                            </a>
                            <a href="?type=historique&annee_univ=<?= $annee_univ ?>" 
                               class="btn btn-outline-primary report-card <?= $report_type === 'historique' ? 'active' : '' ?>">
                                <i class="bi bi-calendar-range"></i> Historique
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0"><i class="bi bi-funnel"></i> Filtres</h2>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <input type="hidden" name="type" value="<?= $report_type ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">Année universitaire</label>
                        <select name="annee_univ" class="form-select">
                            <?php foreach ($annees as $annee): ?>
                                <option value="<?= htmlspecialchars($annee) ?>" <?= $annee === $annee_univ ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($annee) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Filière</label>
                        <select name="filiere" class="form-select">
                            <option value="all">Toutes les filières</option>
                            <?php foreach ($filieres as $filiere): ?>
                                <option value="<?= $filiere['id'] ?>" <?= $filiere['id'] == $filiere_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($filiere['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($report_type !== 'historique'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select name="statut" class="form-select">
                            <option value="all">Tous</option>
                            <option value="validé" <?= $statut === 'validé' ? 'selected' : '' ?>>Validé</option>
                            <option value="refusé" <?= $statut === 'refusé' ? 'selected' : '' ?>>Refusé</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-funnel"></i> Appliquer
                        </button>
                        <a href="?type=<?= $report_type ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <?php if ($report_type === 'affectations'): ?>
                <div class="col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['total_ue'] ?></h3>
                            <p class="card-text">UE Affectées</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['total_heures'] ?>h</h3>
                            <p class="card-text">Volume Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card cm h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['cm'] ?>h</h3>
                            <p class="card-text">Volume CM</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card h-100 <?= $stats['sous_charges'] > 0 ? 'bg-warning' : '' ?>">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['sous_charges'] ?></h3>
                            <p class="card-text">Enseignants sous <?= $seuil_minimum ?>h</p>
                        </div>
                    </div>
                </div>
            
            <?php elseif ($report_type === 'charges'): ?>
                <div class="col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['total_enseignants'] ?></h3>
                            <p class="card-text">Enseignants</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['total_heures'] ?>h</h3>
                            <p class="card-text">Volume Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card cm h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= round($stats['total_heures'] / max(1, $stats['total_enseignants']), 1) ?>h</h3>
                            <p class="card-text">Moyenne/Enseignant</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card h-100 <?= $stats['sous_charges'] > 0 ? 'bg-warning' : '' ?>">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['sous_charges'] ?></h3>
                            <p class="card-text">Sous <?= $seuil_minimum ?>h</p>
                        </div>
                    </div>
                </div>
            
            <?php elseif ($report_type === 'ue_vacantes'): ?>
                <div class="col-md-4">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['total_ue'] ?></h3>
                            <p class="card-text">UE Vacantes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title"><?= $stats['total_heures'] ?>h</h3>
                            <p class="card-text">Volume Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title">
                                <?= $filieres[$filiere_id !== 'all' ? array_search($filiere_id, array_column($filieres, 'id')) : 0]['nom'] ?? 'Toutes' ?>
                            </h3>
                            <p class="card-text">Filière</p>
                        </div>
                    </div>
                </div>
            
            <?php elseif ($report_type === 'historique'): ?>
                <div class="col-md-12">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <h3 class="card-title">Évolution sur <?= count($results) ?> années</h3>
                            <p class="card-text">Comparaison historique</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tableau de résultats -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">
                    <?= match($report_type) {
                        'affectations' => 'Liste des Affectations',
                        'charges' => 'Répartition des Charges',
                        'ue_vacantes' => 'Unités d\'Enseignement Vacantes',
                        'historique' => 'Historique des Affectations'
                    } ?>
                </h2>
                <div>
                    <a href="export.php?type=<?= $report_type ?>&annee=<?= $annee_univ ?>&filiere=<?= $filiere_id ?>" 
                       class="btn btn-success btn-sm">
                        <i class="bi bi-file-excel"></i> Exporter
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="dataTable">
                        <thead class="table-light">
                            <?php if ($report_type === 'affectations'): ?>
                                <tr>
                                    <th>Enseignant</th>
                                    <th>UE</th>
                                    <th>Filière</th>
                                    <th>Type</th>
                                    <th>CM</th>
                                    <th>TD</th>
                                    <th>TP</th>
                                    <th>Total</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            <?php elseif ($report_type === 'charges'): ?>
                                <tr>
                                    <th>Enseignant</th>
                                    <th>CM</th>
                                    <th>TD</th>
                                    <th>TP</th>
                                    <th>Total</th>
                                    <th>% Charge</th>
                                </tr>
                            <?php elseif ($report_type === 'ue_vacantes'): ?>
                                <tr>
                                    <th>Code UE</th>
                                    <th>Intitulé</th>
                                    <th>Filière</th>
                                    <th>CM</th>
                                    <th>TD</th>
                                    <th>TP</th>
                                    <th>Total</th>
                                </tr>
                            <?php elseif ($report_type === 'historique'): ?>
                                <tr>
                                    <th>Année</th>
                                    <th>Affectations</th>
                                    <th>Enseignants</th>
                                    <th>Heures</th>
                                    <th>Moyenne</th>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php if ($report_type === 'affectations'): ?>
                                <?php foreach ($results as $row): ?>
                                <tr class="<?= !$row['valide'] ? 'table-warning' : '' ?>">
                                    <td><?= htmlspecialchars($row['nom'].' '.$row['prenom']) ?></td>
                                    <td>
                                        <span class="fw-bold"><?= htmlspecialchars($row['code_ue']) ?></span><br>
                                        <small><?= htmlspecialchars($row['nom_ue']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['filiere']) ?></td>
                                    <td><?= htmlspecialchars($row['type_enseignement']) ?></td>
                                    <td><?= $row['volume_cm'] ?>h</td>
                                    <td><?= $row['volume_td'] ?>h</td>
                                    <td><?= $row['volume_tp'] ?>h</td>
                                    <td><?= ($row['volume_cm'] + $row['volume_td'] + $row['volume_tp']) ?>h</td>
                                    <td>
                                        <?php if ($row['valide']): ?>
                                            <span class="badge bg-success">Validé</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Refusé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['date_affectation'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            
                            <?php elseif ($report_type === 'charges'): ?>
                                <?php foreach ($results as $row): ?>
                                <tr class="<?= $row['total'] < $seuil_minimum ? 'under-min' : '' ?>">
                                    <td><?= htmlspecialchars($row['enseignant']) ?></td>
                                    <td><?= $row['cm'] ?>h</td>
                                    <td><?= $row['td'] ?>h</td>
                                    <td><?= $row['tp'] ?>h</td>
                                    <td class="fw-bold"><?= $row['total'] ?>h</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= min(100, ($row['total'] / $seuil_minimum) * 100) ?>%" 
                                                 aria-valuenow="<?= $row['total'] ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?= $seuil_minimum * 1.2 ?>">
                                                <?= round(($row['total'] / $seuil_minimum) * 100) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            
                            <?php elseif ($report_type === 'ue_vacantes'): ?>
                                <?php foreach ($results as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['code_ue']) ?></td>
                                    <td><?= htmlspecialchars($row['nom_ue']) ?></td>
                                    <td><?= htmlspecialchars($row['filiere']) ?></td>
                                    <td><?= $row['volume_cm'] ?>h</td>
                                    <td><?= $row['volume_td'] ?>h</td>
                                    <td><?= $row['volume_tp'] ?>h</td>
                                    <td class="fw-bold"><?= ($row['volume_cm'] + $row['volume_td'] + $row['volume_tp']) ?>h</td>
                                </tr>
                                <?php endforeach; ?>
                            
                            <?php elseif ($report_type === 'historique'): ?>
                                <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['annee_univ']) ?></td>
                                    <td><?= $row['nb_affectations'] ?></td>
                                    <td><?= $row['nb_enseignants'] ?></td>
                                    <td><?= $row['total_heures'] ?>h</td>
                                    <td><?= round($row['total_heures'] / max(1, $row['nb_enseignants']), 1) ?>h</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                dom: '<"top"Bfrtip<"clear">',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ],
                responsive: true,
                order: [[0, 'asc']]
            });
        });
    </script>
</body>
</html>