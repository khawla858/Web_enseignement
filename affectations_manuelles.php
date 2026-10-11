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
    $_SESSION['departement_id'] = $result['departement_id'];
}

$departement_id = $_SESSION['departement_id'];
$annee_univ = date('Y').'-'.(date('Y')+1);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['valider'])) {
        // Validation d'un souhait
        $souhait_id = $_POST['souhait_id'];
       ////////////////////////////////////////////////////////////////////////////////////////

$stmt = $conn->prepare("INSERT INTO affectations (user_id, ue_id, type_enseignement, annee_univ, valide) 
                      SELECT user_id, ue_id, type_enseignement, annee_univ, TRUE FROM souhaits WHERE id = ?");         
        $stmt->bind_param('i', $souhait_id);
        $stmt->execute();
        
        update_charge_horaire($conn, $_POST['user_id']);
        
    } elseif (isset($_POST['rejeter'])) {
        // Enregistrement du rejet
        $stmt = $conn->prepare("INSERT INTO rejets (souhait_id, motif) VALUES (?, ?)");
        $stmt->bind_param('is', $_POST['souhait_id'], $_POST['motif']);
        $stmt->execute();
        
    } elseif (isset($_POST['affecter'])) {
        // Affectation manuelle
        $stmt = $conn->prepare("INSERT INTO affectations (user_id, ue_id, type_enseignement, annee_univ, valide) VALUES (?, ?, ?, ?, TRUE)");/////////
      ///  $stmt = $conn->prepare("INSERT INTO affectations (user_id, ue_id, type_enseignement, annee_univ) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $_POST['prof_id'], $_POST['ue_id'], $_POST['type_enseignement'], $annee_univ);
        $stmt->execute();
        
        update_charge_horaire($conn, $_POST['prof_id']);
        
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

// Récupérer les affectations existantes pour le filtre
$existing_assignments = [];
$stmt = $conn->prepare("SELECT ue_id, type_enseignement FROM affectations WHERE annee_univ = ?");
$stmt->bind_param('s', $annee_univ);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $existing_assignments[$row['ue_id']][] = $row['type_enseignement'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Chef de Département</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* [Vos styles CSS existants restent inchangés] */
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
            color: whi;
        }

        .btn-return {
            background-color:rgb(3, 79, 202);
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

        .avatar {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        :root {
            --primary-color: #4361ee;
            --primary-light: #4895ef;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #fca311;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --bg-light: #f8f9fa;
            --bg-white: #ffffff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .dashboard-container {
            max-width: 1300px;
            margin: 40px auto;
        }
        
        .dashboard-header {
          background-color:  #3a86ff;
            margin-bottom: 30px;
        }
        
        .dashboard-header h1 {
            color: white;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .dashboard-header p {
            color: white;
            font-size: 1.1rem;
        }
        
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--bg-white);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }
        
        .card-header h2 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.4rem;
            margin: 0;
            position: relative;
            padding-left: 15px;
        }
        
        .card-header h2:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 5px;
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-light));
            border-radius: 4px;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .alert {
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        
        /* Tableaux stylisés */
        .custom-table {
            width: 100%;
        }
        
        .custom-table th {
            background-color: rgba(67, 97, 238, 0.05);
            color: var(--primary-color);
            font-weight: 600;
            padding: 15px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .custom-table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background-color: rgba(67, 97, 238, 0.02);
        }
        
        /* Formulaires */
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: var(--transition);
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(74, 149, 239, 0.15);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        /* Boutons */
        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
            border: none;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border: none;
            box-shadow: 0 4px 10px rgba(76, 201, 240, 0.3);
        }
        
        .btn-success:hover {
            background-color: #39b8df;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border: none;
            box-shadow: 0 4px 10px rgba(247, 37, 133, 0.3);
        }
        
        .btn-danger:hover {
            background-color: #e51376;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        
        /* Badges */
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
            border-radius: 6px;
        }
        
        .badge-light {
            background-color: rgba(74, 149, 239, 0.1);
            color: var(--primary-color);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .dashboard-container {
                padding: 0 20px;
            }
        }
        
        @media (max-width: 768px) {
            .card-header h2 {
                font-size: 1.2rem;
            }
            
            .custom-table th {
                font-size: 0.8rem;
            }
            
            .actions-column {
                min-width: 200px;
            }
        }
    </style>
  <!-- Section Affectation Manuelle -->
       

  <div class="card">
    <div class="card-header">
        <h2><i class="fas fa-tasks me-2"></i> Affectation Manuelle des UE</h2>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3">
        <!-- Sélection du Professeur -->
<div class="col-md-4">
    <select name="prof_id" class="form-select" required>
        <option value="">Choisir un enseignant...</option>
        <?php
        // Modifier la requête pour inclure les coordinateurs et chefs de département du même département
        $stmt = $conn->prepare("SELECT u.* FROM users u
                              LEFT JOIN professeurs_departements pd ON u.id = pd.professeur_id
                              WHERE (pd.departement_id = ? OR u.role IN ('coordinateur', 'chef_departement'))
                              AND u.role IN ('professeur', 'coordinateur', 'chef_departement')");
        $stmt->bind_param('i', $departement_id);
        $stmt->execute();
        $profs = $stmt->get_result();
        
        while ($prof = $profs->fetch_assoc()):
            $selected = (isset($_POST['prof_id']) && $_POST['prof_id'] == $prof['id']) ? 'selected' : '';
            $role_label = '';
            if ($prof['role'] == 'coordinateur') {
                $role_label = ' (Coordinateur)';
            } elseif ($prof['role'] == 'chef_departement') {
                $role_label = ' (Chef de Département)';
            }
        ?>
            <option value="<?= $prof['id'] ?>" <?= $selected ?>>
                <?= htmlspecialchars($prof['prenom']) ?> <?= htmlspecialchars($prof['nom']) ?><?= $role_label ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>
            
            <!-- Sélection de l'UE avec indicateur d'affectation -->
            <div class="col-md-3">
                <select name="ue_id" class="form-select" required >
                    <option value="">Choisir une UE...</option>
                    <?php
                    $stmt = $conn->prepare("SELECT ue.* FROM unites_enseignement ue
                                          WHERE ue.specialite = (SELECT nom FROM departements WHERE id = ?)");
                    $stmt->bind_param('i', $departement_id);
                    $stmt->execute();
                    $ues = $stmt->get_result();
                    
                    while ($ue = $ues->fetch_assoc()):
                        // Récupérer les affectations existantes pour cette UE
                        $stmt = $conn->prepare("SELECT a.type_enseignement, u.prenom, u.nom 
                                              FROM affectations a
                                              JOIN users u ON a.user_id = u.id
                                              WHERE a.ue_id = ? AND a.annee_univ = ?");
                        $stmt->bind_param('is', $ue['id'], $annee_univ);
                        $stmt->execute();
                        $affectations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        
                        $types_affectes = array_column($affectations, 'type_enseignement');
                        $is_complet = (count($types_affectes)) >= 3; // CM, TD, TP
                        
                        $selected = (isset($_POST['ue_id']) && $_POST['ue_id'] == $ue['id']) ? 'selected' : '';
                        $disabled = $is_complet ? 'disabled' : '';
                        $style = $is_complet ? 'style="color:#ccc; font-style:italic;"' : '';
                        
                        // Texte d'affichage avec état
                        $affectation_text = "";
                        if (!empty($affectations)) {
                            $affectation_text = " (Affecté: ";
                            $parts = [];
                            foreach ($affectations as $aff) {
                                $parts[] = $aff['type_enseignement'] . " à " . $aff['prenom'] . " " . $aff['nom'];
                            }
                            $affectation_text .= implode(", ", $parts) . ")";
                        }
                    ?>
                        <option value="<?= $ue['id'] ?>" <?= $selected ?> <?= $disabled ?> <?= $style ?>>
                            <?= htmlspecialchars($ue['nom_ue']) ?>
                            <?= $is_complet ? "[COMPLET]" : "" ?>
                            <?= $affectation_text ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Sélection du Type d'Enseignement -->
<div class="col-md-3">
    <select name="type_enseignement" class="form-select" required id="typeSelect">
        <option value="">Type d'enseignement...</option>
        <?php
        // Toujours afficher les options de base, même sans UE sélectionnée
        $types_disponibles = [
            'CM' => 'Cours Magistral (CM)',
            'TD' => 'Travaux Dirigés (TD)', 
            'TP' => 'Travaux Pratiques (TP)'
        ];
        
        if (isset($_POST['ue_id']) && $_POST['ue_id'] != '') {
            $ue_id = $_POST['ue_id'];
            
            // Récupérer les types déjà affectés
            $stmt = $conn->prepare("SELECT type_enseignement FROM affectations 
                                  WHERE ue_id = ? AND annee_univ = ?");
            $stmt->bind_param('is', $ue_id, $annee_univ);
            $stmt->execute();
            $types_affectes = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'type_enseignement');
            
            // Filtrer les types disponibles
            foreach ($types_disponibles as $code => $libelle) {
                if (!in_array($code, $types_affectes)) {
                    echo "<option value='$code'>$libelle</option>";
                }
            }
            
            if (count($types_affectes) >= 3) {
                echo "<option value='' disabled>Aucun type disponible - Affectation complète</option>";
            }
        } else {
            // Afficher tous les types par défaut
            foreach ($types_disponibles as $code => $libelle) {
                echo "<option value='$code'>$libelle</option>";
            }
        }
        ?>
    </select>
</div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" name="affecter" class="btn btn-primary w-100">
                    <i class="fas fa-plus me-1"></i> Affecter
                </button>
            </div>
        </form>
        <hr class="my-4">
                
                <h5 class="mb-3">Affectations actuelles</h5>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Professeur</th>
                                <th>UE Affectée</th>
                                <th>Type</th>
                                <th>Charge</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT a.id, a.user_id, a.type_enseignement, 
                                     u.nom, u.prenom, ue.nom_ue, ue.code_ue,
                                     CASE 
                                         WHEN a.type_enseignement = 'CM' THEN ue.volume_cm
                                         WHEN a.type_enseignement = 'TD' THEN ue.volume_td
                                         WHEN a.type_enseignement = 'TP' THEN ue.volume_tp
                                     END as volume
                                     FROM affectations a
                                     JOIN users u ON a.user_id = u.id
                                     JOIN unites_enseignement ue ON a.ue_id = ue.id
                                     WHERE ue.specialite = (SELECT nom FROM departements WHERE id = ?)
                                     AND a.annee_univ = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param('is', $departement_id, $annee_univ);
                            $stmt->execute();
                            $affectations = $stmt->get_result();
                            
                            while ($aff = $affectations->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($aff['prenom']) ?> <?= htmlspecialchars($aff['nom']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($aff['nom_ue']) ?></strong>
                                    <div class="text-muted small"><?= $aff['code_ue'] ?></div>
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
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ueSelect = document.getElementById('ueSelect');
        const typeSelect = document.getElementById('typeSelect');
        
        // Synchroniser les sélections UE/Type
        ueSelect.addEventListener('change', function() {
            // Réinitialiser les options
            typeSelect.innerHTML = '<option value="">Type d\'enseignement...</option>';
            
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const availableTypes = selectedOption.getAttribute('data-available-types').split(',');
                
                // Ajouter les types disponibles
                availableTypes.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    typeSelect.appendChild(option);
                });
            }
        });
        
        // Empêcher la modification manuelle du type
        typeSelect.addEventListener('mousedown', function(e) {
            if (ueSelect.value) {
                e.preventDefault();
                return false;
            }
        });
        
        // Réinitialiser le formulaire après soumission
        const form = document.getElementById('assignmentForm');
        if (form) {
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    form.reset();
                    typeSelect.innerHTML = '<option value="">Type d\'enseignement...</option>';
                }, 100);
            });
        }
    });
    </script>
</body>
</html>