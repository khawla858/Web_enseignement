<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$sql = "SELECT u.*, d.nom AS departement_nom, pd.departement_id 
        FROM users u
        LEFT JOIN professeurs_departements pd ON u.id = pd.professeur_id
        LEFT JOIN departements d ON pd.departement_id = d.id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Récupérer des informations supplémentaires selon le rôle
$additional_info = [];
switch ($user['role']) {
    case 'professeur':
    case 'vacataire':
        // Charge horaire actuelle
        $sql = "SELECT SUM(ue.volume_cm + ue.volume_td + ue.volume_tp) AS total_heures
                FROM affectations a
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                WHERE a.user_id = ? AND a.valide = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $additional_info['charge_actuelle'] = $stmt->get_result()->fetch_assoc()['total_heures'] ?? 0;
        
        // UE affectées
        $sql = "SELECT ue.*, a.type_enseignement 
                FROM affectations a
                JOIN unites_enseignement ue ON a.ue_id = ue.id
                WHERE a.user_id = ? AND a.valide = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $additional_info['ue_affectees'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'chef_departement':
        // Statistiques département
        $sql = "SELECT COUNT(*) AS nb_professeurs, 
                       (SELECT COUNT(*) FROM unites_enseignement 
                        WHERE specialite = d.nom) AS nb_ue
                FROM professeurs_departements pd
                JOIN users u ON pd.professeur_id = u.id
                JOIN departements d ON pd.departement_id = d.id
                WHERE pd.departement_id = ? AND (u.role = 'professeur' OR u.role = 'vacataire')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user['departement_id']);
        $stmt->execute();
        $additional_info['departement_stats'] = $stmt->get_result()->fetch_assoc();
        break;
        
    case 'coordinateur':
        // Filières gérées
        $sql = "SELECT * FROM filieres WHERE departement_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user['departement_id']);
        $stmt->execute();
        $additional_info['filieres'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - <?= htmlspecialchars($user['prenom'] . ' ' . htmlspecialchars($user['nom'])) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #3a86ff;
            --secondary: #1e3152;
            --light-bg: #f8f9fa;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto;
        }
        .role-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: none;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 15px;
            background-color: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary);
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- En-tête du profil -->
        <div class="profile-header text-center">
            <div class="avatar mb-3">
                <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
            </div>
            <h2><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h2>
            <span class="role-badge badge bg-light text-dark">
                <?php 
                $roles = [
                    'professeur' => 'Enseignant Titulaire',
                    'vacataire' => 'Enseignant Vacataire',
                    'chef_departement' => 'Chef de Département',
                    'coordinateur' => 'Coordinateur de Filière'
                ];
                echo $roles[$user['role']] ?? $user['role'];
                ?>
            </span>
            <?php if (!empty($user['departement_nom'])): ?>
                <div class="mt-2">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($user['departement_nom']) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($user['specialite'])): ?>
                <div class="mt-1">
                    <i class="bi bi-tags"></i> <?= htmlspecialchars($user['specialite']) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section informations personnelles -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-lines-fill"></i> Informations personnelles</span>
                <a href="edit_profile.php" class="btn btn-sm btn-outline-primary">Modifier</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="bi bi-envelope"></i> Email :</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <p><strong><i class="bi bi-person-badge"></i> Nom d'utilisateur :</strong> <?= htmlspecialchars($user['username']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><i class="bi bi-calendar"></i> Date de création :</strong> 
                            <?= date('d/m/Y', strtotime($user['date_creation'] ?? 'now')) ?></p>
                        <p><strong><i class="bi bi-shield-lock"></i> Dernière connexion :</strong> 
                            <?= date('d/m/Y H:i', strtotime($_SESSION['last_login'] ?? 'now')) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section spécifique au rôle -->
        <?php switch ($user['role']): 
            case 'professeur':
            case 'vacataire': ?>
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value"><?= $additional_info['charge_actuelle'] ?>h</div>
                            <div class="stat-label">Charge horaire actuelle</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value"><?= count($additional_info['ue_affectees']) ?></div>
                            <div class="stat-label">UE affectées</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value">
                                <?= array_sum(array_map(fn($ue) => $ue['volume_cm'] + $ue['volume_td'] + $ue['volume_tp'], $additional_info['ue_affectees'])) ?>h
                            </div>
                            <div class="stat-label">Volume total</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-journal-bookmark"></i> Unités d'enseignement affectées
                    </div>
                    <div class="card-body">
                        <?php if (!empty($additional_info['ue_affectees'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Code UE</th>
                                            <th>Intitulé</th>
                                            <th>Type</th>
                                            <th>CM</th>
                                            <th>TD</th>
                                            <th>TP</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($additional_info['ue_affectees'] as $ue): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($ue['code_ue']) ?></td>
                                                <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                                                <td><span class="badge bg-info"><?= $ue['type_enseignement'] ?></span></td>
                                                <td><?= $ue['volume_cm'] ?>h</td>
                                                <td><?= $ue['volume_td'] ?>h</td>
                                                <td><?= $ue['volume_tp'] ?>h</td>
                                                <td><strong><?= $ue['volume_cm'] + $ue['volume_td'] + $ue['volume_tp'] ?>h</strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">Aucune UE affectée pour le moment.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php break; ?>
                
            <?php case 'chef_departement': ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $additional_info['departement_stats']['nb_professeurs'] ?></div>
                            <div class="stat-label">Enseignants dans le département</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $additional_info['departement_stats']['nb_ue'] ?></div>
                            <div class="stat-label">Unités d'enseignement</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-speedometer2"></i> Tableau de bord départemental
                    </div>
                    <div class="card-body">
                        <div class="alert alert-primary">
                            <i class="bi bi-info-circle"></i> Vous pouvez gérer les affectations depuis 
                            <a href="gestion_affectations.php" class="alert-link">l'interface de gestion</a>.
                        </div>
                        <div class="text-center mt-3">
                            <a href="rapport_departement.php" class="btn btn-primary me-2">
                                <i class="bi bi-file-earmark-bar-graph"></i> Rapport départemental
                            </a>
                            <a href="liste_professeurs.php" class="btn btn-outline-primary">
                                <i class="bi bi-people"></i> Liste des enseignants
                            </a>
                        </div>
                    </div>
                </div>
                <?php break; ?>
                
            <?php case 'coordinateur': ?>
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-diagram-3"></i> Filières gérées
                    </div>
                    <div class="card-body">
                        <?php if (!empty($additional_info['filieres'])): ?>
                            <div class="row">
                                <?php foreach ($additional_info['filieres'] as $filiere): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <h5 class="card-title"><?= htmlspecialchars($filiere['nom']) ?></h5>
                                                <a href="gestion_filiere.php?id=<?= $filiere['id'] ?>" class="btn btn-sm btn-outline-primary mt-2">
                                                    Gérer la filière
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">Aucune filière assignée.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php break; ?>
        <?php endswitch; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>