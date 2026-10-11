<?php
require_once 'config.php';
session_start();

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupération des informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user['role'] != 'coordonnateur') {
    header("Location: unauthorized.php");
    exit();
}

// Récupération de la filière associée au coordinateur
$stmt = $conn->prepare("SELECT f.* FROM filieres f
                       JOIN coordinateurs_filieres cf ON f.id = cf.filiere_id
                       WHERE cf.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$filiere = $stmt->get_result()->fetch_assoc();

if (!$filiere) {
    die("Aucune filière associée à ce coordinateur.");
}

// Récupération des filtres
$filtre_code = $_GET['code_ue'] ?? '';
$filtre_nom = $_GET['nom_ue'] ?? '';

// Requête optimisée pour récupérer les UE avec leur statut
$query = "SELECT 
             ue.id,
             ue.code_ue,
             ue.nom_ue,
             ue.volume_cm,
             ue.volume_td,
             ue.volume_tp,
             ue.semestre,
             ue.responsable_id,
             CONCAT(u_resp.nom, ' ', u_resp.prenom) AS responsable_nom,
             
             /* Statut global de l'UE */
             CASE WHEN EXISTS (
                 SELECT 1 FROM affectations 
                 WHERE ue_id = ue.id AND valide = 1
             ) THEN 'Affectée' ELSE 'Vacante' END AS statut,
             
             /* Détails des affectations */
             GROUP_CONCAT(DISTINCT 
                 CONCAT(aff.type_enseignement, ' - ', 
                 CASE WHEN u_aff.role = 'vacataire' 
                      THEN CONCAT(u_aff.nom, ' (Vacataire)') 
                      ELSE CONCAT(u_aff.nom, ' ', u_aff.prenom) END
                 ) SEPARATOR '<br>'
             ) AS affectations_details,
             
             /* Statut par type d'heure */
             MAX(CASE WHEN aff.type_enseignement = 'CM' AND aff.valide = 1 THEN 1 ELSE 0 END) AS cm_affecte,
             MAX(CASE WHEN aff.type_enseignement= 'TD' AND aff.valide = 1 THEN 1 ELSE 0 END) AS td_affecte,
             MAX(CASE WHEN aff.type_enseignement = 'TP' AND aff.valide = 1 THEN 1 ELSE 0 END) AS tp_affecte
          FROM unites_enseignement ue
          LEFT JOIN users u_resp ON ue.responsable_id = u_resp.id
          LEFT JOIN affectations aff ON ue.id = aff.ue_id AND aff.valide = 1
          LEFT JOIN users u_aff ON aff.user_id = u_aff.id
          WHERE ue.filiere = ?
          AND ue.code_ue LIKE ?
          AND ue.nom_ue LIKE ?
          GROUP BY ue.id
          ORDER BY ue.code_ue";

$code_param = "%{$filtre_code}%";
$nom_param = "%{$filtre_nom}%";

$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $filiere['nom'], $code_param, $nom_param);
$stmt->execute();
$result = $stmt->get_result();

$ues = [];
while ($row = $result->fetch_assoc()) {
    $ues[] = $row;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des UE - <?= htmlspecialchars($filiere['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-custom {
            max-width: 1200px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
        }
        .filters-form {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .filters-form .form-group {
            flex: 1;
            min-width: 200px;
        }
        .filters-form input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
        }
        .filters-form button {
            background-color: #4a90e2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .export-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .export-btn:hover {
            background-color: #218838;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        th {
            background-color: #34495e;
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        td {
            background-color: white;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        tr:hover td {
            background-color: #f8f9fa;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .badge-cm { background-color: #6f42c1; }
        .badge-td { background-color: #fd7e14; }
        .badge-tp { background-color: #20c997; }
        .badge-affectee { background-color: #28a745; }
        .badge-vacante { background-color: #6c757d; }
        .badge-partiel { background-color: #ffc107; color: #212529; }
        .actions-cell {
            white-space: nowrap;
        }
        .status-details {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <h2>
            <i class="bi bi-book"></i> Gestion des Unités d'Enseignement
            <br><small class="text-muted"><?= htmlspecialchars($filiere['nom']) ?></small>
        </h2>

        <form method="get" class="filters-form">
            <div class="form-group">
                <input type="text" name="code_ue" placeholder="Code UE" value="<?= htmlspecialchars($filtre_code) ?>">
            </div>
            <div class="form-group">
                <input type="text" name="nom_ue" placeholder="Nom UE" value="<?= htmlspecialchars($filtre_nom) ?>">
            </div>
            <button type="submit">
                <i class="bi bi-funnel"></i> Filtrer
            </button>
            <a href="utils/export_data.php?filiere=<?= urlencode($filiere['nom']) ?>&code_ue=<?= urlencode($filtre_code) ?>&nom_ue=<?= urlencode($filtre_nom) ?>" class="export-btn">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </a>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code UE</th>
                        <th>Nom UE</th>
                        <th>Volumes</th>
                        <th>Responsable</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ues)): ?>
                        <?php foreach ($ues as $ue): ?>
                            <tr>
                                <td><?= htmlspecialchars($ue['code_ue']) ?></td>
                                <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($ue['volume_cm'] > 0): ?>
                                            <span class="badge badge-cm">CM: <?= $ue['volume_cm'] ?>h</span>
                                        <?php endif; ?>
                                        <?php if ($ue['volume_td'] > 0): ?>
                                            <span class="badge badge-td">TD: <?= $ue['volume_td'] ?>h</span>
                                        <?php endif; ?>
                                        <?php if ($ue['volume_tp'] > 0): ?>
                                            <span class="badge badge-tp">TP: <?= $ue['volume_tp'] ?>h</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ue['responsable_nom'] ?? 'Non défini') ?>
                                </td>
                                <td>
                                    <?php if ($ue['statut'] === 'Affectée'): ?>
                                        <?php if ($ue['cm_affecte'] && $ue['td_affecte'] && $ue['tp_affecte']): ?>
                                            <span class="badge badge-affectee">Complètement affectée</span>
                                        <?php else: ?>
                                            <span class="badge badge-partiel">Partiellement affectée</span>
                                        <?php endif; ?>
                                        <?php if ($ue['affectations_details']): ?>
                                            <div class="status-details"><?= $ue['affectations_details'] ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-vacante">Vacante</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <div class="d-flex gap-2">
                                        <a href="edit_ue.php?id=<?= $ue['id'] ?>" class="btn btn-sm btn-warning" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="affecation_co.php?ue_id=<?= $ue['id'] ?>" class="btn btn-sm btn-success" title="Affecter">
                                            <i class="bi bi-person-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Aucune unité d'enseignement trouvée
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>