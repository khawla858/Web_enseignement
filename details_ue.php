<?php
session_start();
require 'config.php';

// Vérification du rôle chef de département
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef_departement') {
    header("Location: login.php");
    exit();
}

// Récupérer l'ID de l'UE depuis l'URL
$ue_id = isset($_GET['ue_id']) ? intval($_GET['ue_id']) : 0;

// Requête pour récupérer les détails de l'UE
$ue_details = [];
$responsable = [];
$enseignants = [];

if ($stmt = $conn->prepare("
    SELECT ue.*, 
           CONCAT(u.nom, ' ', u.prenom) AS responsable_nom,
           u.email AS responsable_email
    FROM unites_enseignement ue
    LEFT JOIN users u ON ue.responsable_id = u.id
    WHERE ue.id = ?
")) {
    $stmt->bind_param("i", $ue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ue_details = $result->fetch_assoc();
    $stmt->close();
}

if (!$ue_details) {
    die("UE introuvable ou non autorisée");
}

// Récupérer les enseignants affectés (version corrigée pour votre structure de table)
if ($stmt = $conn->prepare("
    SELECT a.type_enseignement, 
           CONCAT(u.nom, ' ', u.prenom) AS nom_complet,
           u.email,
           COUNT(a.id) AS nombre_seances
    FROM affectations a
    JOIN users u ON a.user_id = u.id
    WHERE a.ue_id = ?
    GROUP BY a.user_id, a.type_enseignement
")) {
    $stmt->bind_param("i", $ue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $enseignants[$row['nom_complet']][$row['type_enseignement']] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails UE - <?= htmlspecialchars($ue_details['nom_ue']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .ue-header {
            background: linear-gradient(135deg, #3a86ff 0%, #6f42c1 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .badge-horaire {
            font-size: 1.1em;
            padding: 0.6em 1em;
            border-radius: 10px;
        }
        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 12px;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .teacher-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .responsable-card {
            border-left: 4px solid #3a86ff;
            background-color: #f8fbff;
        }
        .horaire-breakdown {
            background-color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- En-tête -->
        <div class="ue-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="display-5 fw-bold mb-3">
                        <?= htmlspecialchars($ue_details['code_ue']) ?> - <?= htmlspecialchars($ue_details['nom_ue']) ?>
                    </h1>
                    <div class="d-flex gap-3 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-journal-bookmark fs-5 me-2"></i>
                            Semestre S<?= htmlspecialchars($ue_details['semestre']) ?>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-building fs-5 me-2"></i>
                            Département <?= htmlspecialchars($_SESSION['departement'] ?? '') ?>
                        </div>
                    </div>
                </div>
                <a href="liste_ues.php" class="btn btn-light btn-lg">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
            
            <!-- Badges horaires -->
            <div class="d-flex gap-4 mt-4">
                <div class="badge-horaire bg-success">
                    <div class="h5 mb-0"><?= $ue_details['volume_cm'] ?>h</div>
                    <small>CM</small>
                </div>
                <div class="badge-horaire bg-info">
                    <div class="h5 mb-0"><?= $ue_details['volume_td'] ?>h</div>
                    <small>TD</small>
                </div>
                <div class="badge-horaire bg-warning text-dark">
                    <div class="h5 mb-0"><?= $ue_details['volume_tp'] ?>h</div>
                    <small>TP</small>
                </div>
            </div>
        </div>

        <!-- Responsable UE -->
        <div class="card mb-4 responsable-card card-hover">
            <div class="card-body">
                <h3 class="card-title mb-4">
                    <i class="bi bi-person-gear me-2"></i>Responsable de l'UE
                </h3>
                <?php if ($ue_details['responsable_nom']): ?>
                    <div class="d-flex align-items-center">
                        <div class="avatar bg-primary text-white rounded-circle me-3" 
                             style="width: 60px; height: 60px; line-height: 60px; text-align: center; font-size: 24px">
                            <?= strtoupper(substr($ue_details['responsable_nom'], 0, 1)) ?>
                        </div>
                        <div>
                            <h4 class="mb-1"><?= htmlspecialchars($ue_details['responsable_nom']) ?></h4>
                            <p class="text-muted mb-0">
                                <i class="bi bi-envelope me-2"></i>
                                <?= htmlspecialchars($ue_details['responsable_email']) ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Aucun responsable assigné à cette UE
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enseignants affectés -->
        <div class="card card-hover">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-people-fill me-2"></i>Enseignants affectés
                    </h3>
                    <a href="affecter.php?ue_id=<?= $ue_id ?>" class="btn btn-primary">
                        <i class="bi bi-person-add me-2"></i>Affecter un enseignant
                    </a>
                </div>

                <?php if (!empty($enseignants)): ?>
                    <div class="table-responsive">
                        <table class="table teacher-table">
                            <thead>
                                <tr>
                                    <th>Enseignant</th>
                                    <th>Type</th>
                                    <th>Séances</th>
                                    <th>Année</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enseignants as $nom => $types): ?>
                                    <?php foreach ($types as $type => $details): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($nom) ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?= $type === 'CM' ? 'bg-success' : 
                                                       ($type === 'TD' ? 'bg-info' : 'bg-warning') ?>">
                                                    <?= $type ?>
                                                </span>
                                            </td>
                                            <td><?= $details['nombre_seances'] ?></td>
                                            <td><?= $details['annee_univ'] ?? '2025-2026' ?></td>
                                            <td>
                                                <a href="mailto:<?= $details['email'] ?>" 
                                                   class="text-decoration-none">
                                                    <i class="bi bi-envelope me-2"></i>
                                                    Contacter
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun enseignant n'a encore été affecté à cette UE
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Répartition horaire -->
        <div class="horaire-breakdown mt-4">
            <h4 class="mb-4">
                <i class="bi bi-pie-chart-fill me-2"></i>Répartition horaire
            </h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success rounded-circle me-3" 
                             style="width: 20px; height: 20px"></div>
                        CM : <?= $ue_details['volume_cm'] ?>h
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info rounded-circle me-3" 
                             style="width: 20px; height: 20px"></div>
                        TD : <?= $ue_details['volume_td'] ?>h
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning rounded-circle me-3" 
                             style="width: 20px; height: 20px"></div>
                        TP : <?= $ue_details['volume_tp'] ?>h
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>