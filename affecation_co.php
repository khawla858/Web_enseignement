<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Récupération du département
if (!isset($_SESSION['departement_id'])) {
    $stmt = $conn->prepare("SELECT departement_id FROM professeurs_departements WHERE professeur_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && isset($result['departement_id'])) {
        $_SESSION['departement_id'] = $result['departement_id'];
    } else {
        // Aucun département trouvé pour cet utilisateur
        // Tu peux rediriger ou afficher un message d'erreur ici
        die("Aucun département associé à cet utilisateur.");
    }
}


$departement_id = $_SESSION['departement_id'];
$annee_univ = date('Y').'-'.(date('Y')+1);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['affecter'])) {
        // Vérifier que l'UE a un responsable avant affectation
        $checkStmt = $conn->prepare("SELECT responsable_id FROM unites_enseignement WHERE id = ?");
        $checkStmt->bind_param('i', $_POST['ue_id']);
        $checkStmt->execute();
        $ue = $checkStmt->get_result()->fetch_assoc();
        
        if ($ue['responsable_id']) {
            // Affectation manuelle
            $stmt = $conn->prepare("INSERT INTO affectations (user_id, ue_id, type_enseignement, annee_univ, valide) VALUES (?, ?, ?, ?, TRUE)");
            $stmt->bind_param('iiss', $_POST['prof_id'], $_POST['ue_id'], $_POST['type_enseignement'], $annee_univ);
            $stmt->execute();
            
            update_charge_horaire($conn, $_POST['prof_id']);
        }
        
    } elseif (isset($_POST['supprimer'])) {
        // Suppression d'affectation
        $stmt = $conn->prepare("DELETE FROM affectations WHERE id = ?");
        $stmt->bind_param('i', $_POST['affectation_id']);
        $stmt->execute();
        
        update_charge_horaire($conn, $_POST['user_id']);
    }
}

function update_charge_horaire($conn, $user_id) {
    $query = "SELECT SUM(
        CASE 
            WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
            WHEN a.type_enseignement = 'TD' THEN ue.volume_td 
            WHEN a.type_enseignement = 'TP' THEN ue.volume_tp 
        END
    ) as total 
    FROM affectations a 
    JOIN unites_enseignement ue ON a.ue_id = ue.id 
    WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    $annee_univ = date('Y') . '-' . (date('Y') + 1);

    $stmt = $conn->prepare("REPLACE INTO charges_horaires (enseignant_id, annee_universitaire, total_heures) 
                           VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $user_id, $annee_univ, $total);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Vacataires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .badge-cm { background-color: #4361ee; }
        .badge-td { background-color: #4cc9f0; }
        .badge-tp { background-color: #f72585; }
        :root {
            --primary-color: #4361ee;
            --primary-light: #4895ef;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --bg-light: #f8f9fa;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .dashboard-header {
            background: #3a86ff;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            color: white;
        }

        .dashboard-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .btn-return {
            background-color: rgb(3, 79, 202);
            border: 1px solid white;
        }

        .btn-return:hover {
            background: white;
            color: var(--primary-color) !important;
        }

        .custom-table {
            border-radius: 10px;
            overflow: hidden;
        }

        .custom-table th {
            background: var(--primary-light);
            color: white;
        }
        
        .ue-option {
            display: flex;
            justify-content: space-between;
        }
        
        .ue-code {
            font-weight: bold;
        }
        
        .ue-affectations {
            font-size: 0.8em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>Gestion des Vacataires</h1>
                    <p class="mb-0">Département <?= htmlspecialchars($_SESSION['departement_nom'] ?? '') ?></p>
                </div>
                <a href="coordonnateur.php" class="btn btn-light btn-return text-white">
                    <i class="fas fa-arrow-left me-2"></i> Retour
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-tasks me-2"></i> Affectation des Vacataires</h2>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <!-- Sélection des Vacataires du département -->
                    <div class="col-md-4">
                        <select name="prof_id" class="form-select" required>
                            <option value="">Choisir un vacataire...</option>
                            <?php
                            $stmt = $conn->prepare("SELECT u.* FROM users u
                                                  JOIN professeurs_departements pd ON u.id = pd.professeur_id
                                                  WHERE pd.departement_id = ?
                                                  AND u.role = 'vacataire'");
                            $stmt->bind_param('i', $departement_id);
                            $stmt->execute();
                            $profs = $stmt->get_result();
                            
                            while ($prof = $profs->fetch_assoc()):
                                $selected = (isset($_POST['prof_id']) && $_POST['prof_id'] == $prof['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $prof['id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($prof['prenom']) ?> <?= htmlspecialchars($prof['nom']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Sélection de l'UE vacante avec responsable -->
                    <div class="col-md-4">
                        <select name="ue_id" class="form-select" required>
                            <option value="">Choisir une UE vacante...</option>
                            <?php
                            $stmt = $conn->prepare("SELECT 
                                                  ue.id, ue.nom_ue, ue.code_ue,
                                                  GROUP_CONCAT(DISTINCT CONCAT(a.type_enseignement, ':', u.prenom, ' ', u.nom) SEPARATOR '|') as affectations
                                                  FROM unites_enseignement ue
                                                  LEFT JOIN affectations a ON ue.id = a.ue_id AND a.annee_univ = ? AND a.valide = 1
                                                  LEFT JOIN users u ON a.user_id = u.id
                                                  WHERE ue.specialite = (SELECT nom FROM departements WHERE id = ?)
                                                  AND ue.responsable_id IS NOT NULL
                                                  GROUP BY ue.id
                                                  HAVING COUNT(a.id) < 3 OR 
                                                         SUM(a.type_enseignement = 'CM') = 0 OR 
                                                         SUM(a.type_enseignement = 'TD') = 0 OR 
                                                         SUM(a.type_enseignement = 'TP') = 0
                                                  ORDER BY ue.nom_ue");
                            $stmt->bind_param('si', $annee_univ, $departement_id);
                            $stmt->execute();
                            $ues = $stmt->get_result();
                            
                            while ($ue = $ues->fetch_assoc()):
                                $affectations_info = "";
                                if (!empty($ue['affectations'])) {
                                    $affects = explode('|', $ue['affectations']);
                                    $affectations_info = " (";
                                    foreach ($affects as $aff) {
                                        if (!empty($aff)) {
                                            list($type, $prof) = explode(':', $aff, 2);
                                            $affectations_info .= "$type: $prof, ";
                                        }
                                    }
                                    $affectations_info = rtrim($affectations_info, ', ') . ")";
                                }
                            ?>
                                <option value="<?= $ue['id'] ?>">
                                    <span class="ue-option">
                                        <span>
                                            <?= htmlspecialchars($ue['nom_ue']) ?> 
                                            <span class="ue-code"><?= $ue['code_ue'] ?></span>
                                        </span>
                                        <?php if (!empty($affectations_info)): ?>
                                            <span class="ue-affectations"><?= $affectations_info ?></span>
                                        <?php endif; ?>
                                    </span>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Sélection du Type d'Enseignement -->
                    <div class="col-md-2">
                        <select name="type_enseignement" class="form-select" required>
                            <option value="">Type d'enseignement...</option>
                            <option value="CM">Cours Magistral (CM)</option>
                            <option value="TD">Travaux Dirigés (TD)</option>
                            <option value="TP">Travaux Pratiques (TP)</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="affecter" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i> Affecter
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                
                <h5 class="mb-3">Affectations des Vacataires</h5>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Vacataire</th>
                                <th>UE Affectée</th>
                                <th>Responsable</th>
                                <th>Type</th>
                                <th>Charge</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT a.id, a.user_id, a.type_enseignement, 
                                     u.nom, u.prenom, ue.nom_ue, ue.code_ue, ue.responsable_id,
                                     CASE 
                                         WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
                                         WHEN a.type_enseignement = 'TD' THEN ue.volume_td
                                         WHEN a.type_enseignement = 'TP' THEN ue.volume_tp
                                     END as volume
                                     FROM affectations a
                                     JOIN users u ON a.user_id = u.id
                                     JOIN unites_enseignement ue ON a.ue_id = ue.id
                                     JOIN professeurs_departements pd ON u.id = pd.professeur_id
                                     WHERE pd.departement_id = ?
                                     AND a.annee_univ = ?
                                     AND a.valide = 1
                                     AND u.role = 'vacataire'
                                     AND ue.responsable_id IS NOT NULL";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param('is', $departement_id, $annee_univ);
                            $stmt->execute();
                            $affectations = $stmt->get_result();
                            
                            if ($affectations->num_rows === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-info-circle me-2"></i>Aucune affectation de vacataire enregistrée
                                    </td>
                                </tr>
                            <?php else:
                                while ($aff = $affectations->fetch_assoc()): 
                                    // Récupération du responsable
                                    $respStmt = $conn->prepare("SELECT nom, prenom FROM users WHERE id = ?");
                                    $respStmt->bind_param('i', $aff['responsable_id']);
                                    $respStmt->execute();
                                    $responsable = $respStmt->get_result()->fetch_assoc();
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($aff['prenom']) ?> <?= htmlspecialchars($aff['nom']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($aff['nom_ue']) ?></strong>
                                        <div class="text-muted small"><?= $aff['code_ue'] ?></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($responsable['prenom']) ?> <?= htmlspecialchars($responsable['nom']) ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($aff['type_enseignement']) ?>">
                                            <?= $aff['type_enseignement'] ?>
                                        </span>
                                    </td>
                                    <td><?= $aff['volume'] ?>h</td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="affectation_id" value="<?= $aff['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $aff['user_id'] ?>">
                                            <button type="submit" name="supprimer" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash me-1"></i> Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>