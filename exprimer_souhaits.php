
<?php
session_start();
require_once 'config.php';

// Vérification du rôle
if (!isset($_SESSION['role'])) {
    header('Location: ind.php');
    exit;
}

// Récupération des informations du professeur
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.*, d.nom AS departement_nom 
                       FROM users u
                       JOIN professeurs_departements pd ON u.id = pd.professeur_id
                       JOIN departements d ON pd.departement_id = d.id
                       WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$professeur = $stmt->get_result()->fetch_assoc();

if (!$professeur) {
    die("Erreur : Professeur non trouvé");
}

$specialite = $professeur['specialite'];
$departement_nom = $professeur['departement_nom'];
$annee_univ = date('Y') . '-' . (date('Y') + 1);

// Récupérer les UE du département
$stmt = $conn->prepare("SELECT * FROM unites_enseignement 
                       WHERE specialite = ? 
                       ORDER BY semestre, nom_ue");
$stmt->bind_param('s', $specialite);
$stmt->execute();
$ues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les souhaits existants (modifié pour gérer plusieurs types par UE)
$stmt = $conn->prepare("SELECT ue_id, type_enseignement FROM souhaits 
                       WHERE user_id = ? AND annee_univ = ?");
$stmt->bind_param('is', $user_id, $annee_univ);
$stmt->execute();
$souhaits_existants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$souhaits_par_ue = [];
foreach ($souhaits_existants as $souhait) {
    if (!isset($souhaits_par_ue[$souhait['ue_id']])) {
        $souhaits_par_ue[$souhait['ue_id']] = [];
    }
    $souhaits_par_ue[$souhait['ue_id']][] = $souhait['type_enseignement'];
}

// Traitement du formulaire
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ue_types'])) {
    // Vérifier que l'enseignant n'a pas déjà soumis des souhaits
    $stmt = $conn->prepare("SELECT COUNT(*) FROM souhaits 
                           WHERE user_id = ? AND annee_univ = ?");
$stmt->bind_param('is', $user_id, $annee_univ);
$stmt->execute();

if ($stmt->get_result()->fetch_row()[0] > 0) {
    $message = "<div class='alert alert-warning'>Vous avez déjà soumis vos souhaits pour cette année.</div>";
} else {
    // Calcul de la charge totale
    $charge_totale = 0;
    $types_enseignements = $_POST['ue_types'];
    
    // Enregistrer les nouveaux souhaits (modifié pour gérer plusieurs types)
    foreach ($types_enseignements as $ue_id => $types) {
        foreach ($types as $type) {
            if ($type !== 'none') {
                // Calculer la charge selon le type
                $stmt = $conn->prepare("SELECT volume_$type FROM unites_enseignement WHERE id = ?");
                $stmt->bind_param('i', $ue_id);
                $stmt->execute();
                $charge = $stmt->get_result()->fetch_assoc()["volume_$type"];
                $charge_totale += $charge;
                
                // Enregistrement
                $stmt = $conn->prepare("INSERT INTO souhaits 
                                     (user_id, ue_id, type_enseignement, annee_univ) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iiss', $user_id, $ue_id, $type, $annee_univ);
                $stmt->execute();
            }
        }
    }
    
    // Message de confirmation
    $message = "<div class='alert alert-success'>Vos souhaits ont été enregistrés avec succès. ";
    $message .= "Charge horaire totale : <strong>$charge_totale h</strong>.</div>";
    
    // Mettre à jour la liste des souhaits existants
    $souhaits_par_ue = $types_enseignements;
}
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expression des souhaits - <?= htmlspecialchars($professeur['prenom']) ?> <?= htmlspecialchars($professeur['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .header-prof {
            background-color: #3a86ff;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 15px 15px;
        }
        .card-ue {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: none;
        }
        .card-header-ue {
            background-color: #f1f3f5;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
        }
        .badge-semestre {
            font-size: 0.8em;
            background-color: #6c757d;
        }
        .volume-cell {
            font-weight: 500;
            text-align: center;
        }
        .btn-submit {
            background-color: #3a86ff;
            color: white;
            padding: 10px 25px;
            font-weight: 600;
        }
        .btn-submit:hover {
            background-color: #2a75ef;
            color: white;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #3a86ff;
            color: white;
        }
        .type-select {
            min-width: 120px;
        }
        .badge-cm {
            background-color: #3a86ff;
        }
        .badge-td {
            background-color: #4cc9f0;
        }
        .badge-tp {
            background-color: #4361ee;
        }
        .form-check-inline {
            margin-right: 1rem;
        }

        /* Header Prof Style */
        .header-prof {
            background-color: #3a86ff;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            color: #ffffff;
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
    </style>
</head>
<body>
<div class="header-prof">
    <div class="header-content">
    <p>Professeur : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>

        <div class="header-text">
            <h2>expression des souhaits</h2>
            <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Département <?= htmlspecialchars($departement_nom) ?></p>
        </div>

<a href="<?php 
    if ($_SESSION['role'] === 'vacataire') {
        echo 'vacataire.php';
    
    } elseif ($_SESSION['role'] === 'professeur') {
        echo 'professeur.php';
    } 
    
     elseif ($_SESSION['role'] === 'chef_departement') {
        echo 'chef_departement.php';
    }  elseif ($_SESSION['role'] === 'coordonnateur') {
        echo 'coordonnateur.php';
    } 
    
    else {
        echo 'login.php';
    }
?>" class="btn-retour">Retour</a>    </div>
</div>
    <div class="container">
        <?= $message ?>
        
        <div class="card card-ue">
            <div class="card-header card-header-ue">
                <h2 class="h5 mb-0"><i class="fas fa-info-circle me-2"></i>Instructions</h2>
            </div>
            <div class="card-body">
                <p>Veuillez sélectionner les unités d'enseignement et le type d'enseignement que vous souhaitez assurer pour l'année <?= $annee_univ ?> :</p>
                <ul>
                    <li>Pour chaque UE, vous pouvez choisir <strong>un ou plusieurs</strong> types parmi <strong>CM</strong>, <strong>TD</strong> et <strong>TP</strong></li>
                    <li>Vos souhaits seront soumis à validation par le chef de département</li>
                    <li>Une fois soumis, vous ne pourrez plus modifier vos choix</li>
                </ul>
            </div>
        </div>

        <form method="POST">
            <div class="card card-ue">
                <div class="card-header card-header-ue d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><i class="fas fa-list-check me-2"></i>Unités disponibles</h2>
                    <span class="badge bg-primary"><?= count($ues) ?> UE</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Code UE</th>
                                    <th>Nom UE</th>
                                    <th class="text-center">
                                        <span class="badge badge-cm">CM</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge badge-td">TD</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge badge-tp">TP</span>
                                    </th>
                                    <th>Types souhaités</th>
                                    <th>Charge</th>
                                    <th>Semestre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ues as $ue): 
                                    $types_choisis = $souhaits_par_ue[$ue['id']] ?? [];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ue['code_ue']) ?></td>
                                        <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                                        <td class="volume-cell"><?= $ue['volume_cm'] ?>h</td>
                                        <td class="volume-cell"><?= $ue['volume_td'] ?>h</td>
                                        <td class="volume-cell"><?= $ue['volume_tp'] ?>h</td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input type-checkbox" 
                                                       type="checkbox" 
                                                       name="ue_types[<?= $ue['id'] ?>][]" 
                                                       value="cm" 
                                                       id="cm_<?= $ue['id'] ?>"
                                                       <?= in_array('cm', $types_choisis) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="cm_<?= $ue['id'] ?>">CM</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input type-checkbox" 
                                                       type="checkbox" 
                                                       name="ue_types[<?= $ue['id'] ?>][]" 
                                                       value="td" 
                                                       id="td_<?= $ue['id'] ?>"
                                                       <?= in_array('td', $types_choisis) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="td_<?= $ue['id'] ?>">TD</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input type-checkbox" 
                                                       type="checkbox" 
                                                       name="ue_types[<?= $ue['id'] ?>][]" 
                                                       value="tp" 
                                                       id="tp_<?= $ue['id'] ?>"
                                                       <?= in_array('tp', $types_choisis) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="tp_<?= $ue['id'] ?>">TP</label>
                                            </div>
                                        </td>
                                        <td class="volume-cell fw-bold charge-cell" 
                                            id="charge-<?= $ue['id'] ?>">
                                            <?php 
                                                $charge_ue = 0;
                                                foreach ($types_choisis as $type) {
                                                    $charge_ue += $ue["volume_$type"];
                                                }
                                                echo $charge_ue > 0 ? $charge_ue . 'h' : '0h';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-semestre">
                                                <?= htmlspecialchars($ue['semestre']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary">
                                    <td colspan="5" class="text-end fw-bold">Total :</td>
                                    <td></td>
                                    <td class="volume-cell fw-bold" id="total-charge">0h</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-submit" id="submit-btn">
                    <i class="fas fa-paper-plane me-2"></i> Soumettre mes souhaits
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fonction pour mettre à jour les charges
        function updateCharges() {
            let totalCharge = 0;
            
            // Parcourir toutes les lignes UE
            document.querySelectorAll('tbody tr').forEach(function(row) {
                // Ignorer la ligne de total
                if (!row.id && row.cells.length > 7) {
                    const ueId = row.querySelector('input[type="checkbox"]')?.name.match(/\[(\d+)\]/)?.[1];
                    if (!ueId) return;
                    
                    const chargeCell = row.cells[6]; // Cellule Charge (7ème colonne)
                    let chargeRow = 0;
                    
                    // Récupérer les volumes depuis le tableau
                    const volumes = {
                        cm: parseInt(row.cells[2].textContent),
                        td: parseInt(row.cells[3].textContent),
                        tp: parseInt(row.cells[4].textContent)
                    };
                    
                    // Vérifier quels types sont sélectionnés
                    const checkboxes = row.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            const type = checkbox.value;
                            chargeRow += volumes[type];
                        }
                    });
                    
                    // Mettre à jour l'affichage
                    chargeCell.textContent = chargeRow > 0 ? chargeRow + 'h' : '0h';
                    totalCharge += chargeRow;
                }
            });
            
            // Mettre à jour le total
            const totalCell = document.getElementById('total-charge');
            if (totalCell) {
                totalCell.textContent = totalCharge + 'h';
            }
        }
        
        // Écouter les changements sur les cases à cocher
        document.querySelectorAll('.type-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', updateCharges);
        });
        
        // Calcul initial
        updateCharges();
    });
    </script>
</body>
</html>