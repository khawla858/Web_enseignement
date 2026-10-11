<?php
session_start();
require 'config.php';

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id'])) {
    header("Location: ind.php");
    exit();
}

// Récupération du département du chef connecté - VERSION CORRIGÉE
$stmt = $conn->prepare("SELECT d.id, d.nom 
                        FROM departements d
                        JOIN chefs_departement cd ON d.id = cd.departement_id
                        WHERE cd.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$departement = $result->fetch_assoc();
$stmt->close();

if (!$departement) {
    die("<div class='alert alert-danger'>Vous n'êtes associé à aucun département. Contactez l'administrateur.</div>");
}
// Récupération des filtres
$filters = [
    'semestre' => $_GET['semestre'] ?? '',
    'filiere' => $_GET['filiere'] ?? '',
    'type' => $_GET['type'] ?? '',
    'search' => trim($_GET['search'] ?? '')
];

// Construction dynamique de la requête SQL
$sql = "SELECT ue.id, ue.code_ue, ue.nom_ue, 
               ue.volume_cm, ue.volume_td, ue.volume_tp,
               ue.semestre, ue.specialite, ue.filiere,
               ue.filiere as nom_filiere
        FROM unites_enseignement ue
        LEFT JOIN affectations a ON ue.id = a.ue_id AND a.annee_univ = YEAR(CURDATE())
        WHERE a.id IS NULL 
        AND ue.specialite = ?";

$types = "s";
$params = [$departement['nom']];

// Ajout des filtres conditionnels
if (!empty($filters['semestre'])) {
    $sql .= " AND ue.semestre = ?";
    $types .= "s";
    $params[] = $filters['semestre'];
}

if (!empty($filters['filiere'])) {
    $sql .= " AND ue.filiere = ?";
    $types .= "s";
    $params[] = $filters['filiere'];
}

if (!empty($filters['type'])) {
    if ($filters['type'] === 'CM') {
        $sql .= " AND ue.volume_cm > 0";
    } elseif ($filters['type'] === 'TD') {
        $sql .= " AND ue.volume_td > 0";
    } elseif ($filters['type'] === 'TP') {
        $sql .= " AND ue.volume_tp > 0";
    }
}

if (!empty($filters['search'])) {
    $sql .= " AND (ue.code_ue LIKE ? OR ue.nom_ue LIKE ?)";
    $types .= "ss";
    $searchTerm = '%' . $conn->real_escape_string($filters['search']) . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Tri des résultats
$sql .= " ORDER BY ue.semestre, ue.code_ue";

// Exécution de la requête
try {
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $ues_vacantes = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    die("Erreur lors de l'exécution de la requête: " . $e->getMessage());
}
$annee_univ="2025-2026";
// Récupération des filières pour le filtre
$filieres = [];
try {
    $res = $conn->query("SELECT DISTINCT filiere as id, filiere as nom FROM unites_enseignement WHERE filiere IS NOT NULL AND filiere != ''");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $filieres[] = $row;
        }
        $res->free();
    }
} catch (mysqli_sql_exception $e) {
    die("Erreur lors de la récupération des filières: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Chef département - UE Vacantes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
   <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Header Prof Style */
        .header-prof {
            background-color: #3a86ff;
            padding: 30px;
            color: #ffffff;
            width: 100%;
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
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
            color: #dfe6e9;
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
        
        .filter-card {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .badge-vacante {
            background-color: #dc3545;
        }
        
        .badge-affectee {
            background-color: #28a745;
        }
        
        .action-btn {
            margin: 0 3px;
        }
        
        .volume-cell {
            white-space: nowrap;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
    </style>
</head>
<body>
    <!-- Header pleine largeur -->
    <div class="header-prof">
        <div class="header-content">
            <p>Chef de département : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>
            
            <div class="header-text">
                <h2>Unités d'Enseignement Vacantes</h2>
                <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Département <?= htmlspecialchars($departement['nom']) ?></p>
            </div>
            
            <a href="chef_departement.php" class="btn-retour">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>
    
    <!-- Contenu principal -->
    <div class="main-content">
        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Unités Vacantes</h5>
                        <p class="card-text display-4"><?= count($ues_vacantes) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Filtrer les UE vacantes</h5>
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="semestre" class="form-label">Semestre</label>
                        <select name="semestre" id="semestre" class="form-select">
                            <option value="">Tous les semestres</option>
                            <?php foreach ($semestres as $sem): ?>
                                <option value="<?= htmlspecialchars($sem) ?>" <?= $filters['semestre'] == $sem ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sem) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="filiere" class="form-label">Filière</label>
                        <select name="filiere" id="filiere" class="form-select">
                            <option value="">Toutes les filières</option>
                            <?php foreach ($filieres as $filiere): ?>
                                <option value="<?= htmlspecialchars($filiere['id']) ?>" <?= $filters['filiere'] == $filiere['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($filiere['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Code ou nom de l'UE" value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                            <a href="UE_vacante.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des UE vacantes -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Code UE</th>
                        <th>Nom UE</th>
                        <th>Filière</th>
                        <th>Volume Horaire</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ues_vacantes)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Aucune unité vacante trouvée</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ues_vacantes as $ue): ?>
                            <tr>
                                <td><?= htmlspecialchars($ue['code_ue']) ?></td>
                                <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                                <td><?= htmlspecialchars($ue['nom_filiere'] ?? 'Non spécifiée') ?></td>
                                <td class="volume-cell">
                                    <?php 
                                    $volumes = [];
                                    if ($ue['volume_cm'] > 0) $volumes[] = $ue['volume_cm'] . 'h CM';
                                    if ($ue['volume_td'] > 0) $volumes[] = $ue['volume_td'] . 'h TD';
                                    if ($ue['volume_tp'] > 0) $volumes[] = $ue['volume_tp'] . 'h TP';
                                    echo implode(' / ', $volumes);
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-danger">Vacante</span>
                                </td>
                                <td>
                                    <a href="affectation.php?ue_id=<?= $ue['id'] ?>" class="btn btn-sm btn-success action-btn" title="Affecter">
                                        <i class="bi bi-person-plus"></i> Affecter
                                    </a>
                                    <a href="details_ue.php?id=<?= $ue['id'] ?>" class="btn btn-sm btn-info action-btn" title="Détails">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>