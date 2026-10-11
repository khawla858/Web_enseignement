<?php
session_start();
require_once 'config.php';

// Vérification de session et de rôle
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Récupérer la filière du coordinateur
try {
    $queryFiliere = "SELECT f.id as filiere_id, f.nom as filiere_nom 
                    FROM coordinateurs_filieres cf
                    JOIN filieres f ON cf.filiere_id = f.id
                    WHERE cf.user_id = ?";
    $stmtFiliere = $conn->prepare($queryFiliere);
    $stmtFiliere->bind_param("i", $_SESSION['user_id']);
    $stmtFiliere->execute();
    $resultFiliere = $stmtFiliere->get_result();
    $filiere = $resultFiliere->fetch_assoc();

    if (!$filiere) {
        throw new Exception("Aucune filière trouvée pour ce coordinateur.");
    }

    $filiere_id = $filiere['filiere_id'];
    $filiere_nom = htmlspecialchars($filiere['filiere_nom']);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Traitement de l'import Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier_excel'])) {
    try {
        // Validation du fichier
        if ($_FILES['fichier_excel']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors du téléchargement du fichier.");
        }

        $fileType = pathinfo($_FILES['fichier_excel']['name'], PATHINFO_EXTENSION);
        if ($fileType !== 'xlsx' && $fileType !== 'xls') {
            throw new Exception("Seuls les fichiers Excel sont autorisés.");
        }

        require 'vendor/autoload.php';
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($_FILES['fichier_excel']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();

        // Démarrer une transaction
        $conn->begin_transaction();

        // Supprimer les anciennes UE de la filière
        $deleteQuery = "DELETE FROM unites_enseignement WHERE filiere = ?";
        $stmtDelete = $conn->prepare($deleteQuery);
        $stmtDelete->bind_param("i", $filiere_id);
        $stmtDelete->execute();

        // Parcourir les lignes du fichier Excel (à partir de la ligne 3)
        $highestRow = $worksheet->getHighestRow();
        $insertedCount = 0;

        for ($row = 3; $row <= $highestRow; $row++) {
            $code = $worksheet->getCell('A' . $row)->getValue();
            $intitule = $worksheet->getCell('B' . $row)->getValue();
            $semestre = $worksheet->getCell('C' . $row)->getValue();
            $volume_cm = $worksheet->getCell('D' . $row)->getValue();
            $volume_td = $worksheet->getCell('E' . $row)->getValue();
            $volume_tp = $worksheet->getCell('F' . $row)->getValue();
            $autre = $worksheet->getCell('G' . $row)->getValue();
            $credits = $worksheet->getCell('H' . $row)->getValue();

            // Valider les données
            if (empty($code) || empty($intitule) || empty($semestre)) {
                continue;
            }

            // Insérer la nouvelle UE
            $query = "INSERT INTO unites_enseignement 
                     (code_ue, nom_ue, specialite, volume_cm, volume_td, volume_tp, credits, semestre, filiere) 
                     VALUES (?, ?, '', ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssiiiisi", $code, $intitule, $volume_cm, $volume_td, $volume_tp, $credits, $semestre, $filiere_id);
            $stmt->execute();
            $insertedCount++;
        }

        $conn->commit();
        $_SESSION['success'] = "$insertedCount UE(s) importées avec succès pour la filière $filiere_nom!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Erreur lors de l'import : " . $e->getMessage();
    }
}

// Traitement de l'ajout manuel d'une UE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajout_manuel'])) {
    try {
        $code = trim($_POST['code']);
        $intitule = trim($_POST['intitule']);
        $specialite = trim($_POST['specialite']);
        $volume_cm = intval($_POST['volume_cm']);
        $volume_td = intval($_POST['volume_td']);
        $volume_tp = intval($_POST['volume_tp']);
        $credits = intval($_POST['credits']);
        $semestre = 'S' . intval($_POST['semestre']);
        $responsable_id = !empty($_POST['responsable_id']) ? intval($_POST['responsable_id']) : NULL;

        $query = "INSERT INTO unites_enseignement 
                 (code_ue, nom_ue, specialite, volume_cm, volume_td, volume_tp, credits, semestre, filiere, responsable_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssiiiisii", $code, $intitule, $specialite, $volume_cm, $volume_td, $volume_tp, $credits, $semestre, $filiere_id, $responsable_id);

        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de l'ajout de l'UE: " . $conn->error);
        }

        $_SESSION['success'] = "UE ajoutée avec succès!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des enseignants
$queryEnseignants = "SELECT id, nom, prenom FROM users WHERE role IN ('professeur', 'vacataire') ORDER BY nom, prenom";
$resultEnseignants = $conn->query($queryEnseignants);

// Récupération des UE de la filière
$queryUE = "SELECT ue.*, CONCAT(u.nom, ' ', u.prenom) as responsable 
           FROM unites_enseignement ue
           LEFT JOIN users u ON ue.responsable_id = u.id
           WHERE ue.filiere = ?
           ORDER BY ue.semestre, ue.code_ue";
$stmtUE = $conn->prepare($queryUE);
$stmtUE->bind_param("i", $filiere_id);
$stmtUE->execute();
$resultUE = $stmtUE->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descriptif des UE - <?php echo $filiere_nom; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-import {
            border-left: 4px solid #28a745;
        }
        .card-manuel {
            border-left: 4px solid #17a2b8;
        }
        .card-liste {
            border-left: 4px solid #6f42c1;
        }
        .semestre-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .template-link {
            color: #17a2b8;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">
            <i class="fas fa-book-open me-2"></i>Descriptif des UE - <?php echo $filiere_nom; ?>
        </h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card card-import h-100">
                    <div class="card-body">
                        <h2 class="card-title h5">
                            <i class="fas fa-file-import me-2"></i>Import depuis Excel
                        </h2>
                        <p class="card-text">
                            Importez un fichier Excel pour mettre à jour l'ensemble des UE de la filière.
                            <a href="#" class="template-link" data-bs-toggle="modal" data-bs-target="#templateModal">
                                Télécharger le modèle
                            </a>
                        </p>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="fichier_excel" class="form-label">Fichier Excel</label>
                                <input class="form-control" type="file" id="fichier_excel" name="fichier_excel" accept=".xlsx,.xls" required>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload me-1"></i> Importer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card card-manuel h-100">
                    <div class="card-body">
                        <h2 class="card-title h5">
                            <i class="fas fa-plus-circle me-2"></i>Ajout manuel
                        </h2>
                        <form method="POST">
                            <input type="hidden" name="ajout_manuel" value="1">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label for="code" class="form-label">Code UE*</label>
                                        <input type="text" class="form-control" id="code" name="code" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label for="semestre" class="form-label">Semestre*</label>
                                        <select class="form-select" id="semestre" name="semestre" required>
                                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                                <option value="<?php echo $i; ?>">S<?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label for="intitule" class="form-label">Intitulé*</label>
                                <input type="text" class="form-control" id="intitule" name="intitule" required>
                            </div>
                            
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="mb-2">
                                        <label for="volume_cm" class="form-label">CM (h)*</label>
                                        <input type="number" class="form-control" id="volume_cm" name="volume_cm" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-2">
                                        <label for="volume_td" class="form-label">TD (h)*</label>
                                        <input type="number" class="form-control" id="volume_td" name="volume_td" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-2">
                                        <label for="volume_tp" class="form-label">TP (h)*</label>
                                        <input type="number" class="form-control" id="volume_tp" name="volume_tp" min="0" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label for="credits" class="form-label">Crédits*</label>
                                        <input type="number" class="form-control" id="credits" name="credits" min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label for="responsable_id" class="form-label">Responsable</label>
                                        <select class="form-select" id="responsable_id" name="responsable_id">
                                            <option value="">-- Non attribué --</option>
                                            <?php while ($enseignant = $resultEnseignants->fetch_assoc()): ?>
                                                <option value="<?php echo $enseignant['id']; ?>">
                                                    <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-2">
                                <i class="fas fa-save me-1"></i> Enregistrer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-liste">
            <div class="card-body">
                <h2 class="card-title h5">
                    <i class="fas fa-list me-2"></i>Liste des UE
                </h2>
                
                <?php if ($resultUE->num_rows > 0): ?>
                    <?php
                    // Organiser les UE par semestre
                    $uesParSemestre = [];
                    while ($ue = $resultUE->fetch_assoc()) {
                        $semestre = $ue['semestre'];
                        if (!isset($uesParSemestre[$semestre])) {
                            $uesParSemestre[$semestre] = [];
                        }
                        $uesParSemestre[$semestre][] = $ue;
                    }
                    ?>
                    
                    <?php foreach ($uesParSemestre as $semestre => $ues): ?>
                        <div class="semestre-header p-2 mb-2 rounded">
                            Semestre <?php echo substr($semestre, 1); ?>
                        </div>
                        
                        <div class="table-responsive mb-4">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Intitulé</th>
                                        <th>Volumes</th>
                                        <th>Crédits</th>
                                        <th>Responsable</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ues as $ue): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ue['code_ue']); ?></td>
                                            <td><?php echo htmlspecialchars($ue['nom_ue']); ?></td>
                                            <td>
                                                CM: <?php echo $ue['volume_cm']; ?>h<br>
                                                TD: <?php echo $ue['volume_td']; ?>h<br>
                                                TP: <?php echo $ue['volume_tp']; ?>h
                                            </td>
                                            <td><?php echo $ue['credits']; ?></td>
                                            <td><?php echo htmlspecialchars($ue['responsable'] ?? 'Non attribué'); ?></td>
                                            <td>
                                                <a href="edit_ue.php?id=<?= $ue['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Modifier">
    <i class="fas fa-edit"></i>
</a>
                                               
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            Total: <?php echo $resultUE->num_rows; ?> UE(s)
                        </div>
                        <div>
                            <button class="btn btn-primary me-2" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-1"></i> Exporter
                            </button>
                            <button class="btn btn-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Imprimer
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Aucune UE trouvée pour cette filière.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal pour le modèle Excel -->
    <div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalLabel">Modèle Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Structure requise pour le fichier Excel :</p>
                    <ul>
                        <li>Colonne A : Code UE (ex: M111)</li>
                        <li>Colonne B : Intitulé</li>
                        <li>Colonne C : Semestre (ex: S1)</li>
                        <li>Colonne D : Volume CM (heures)</li>
                        <li>Colonne E : Volume TD (heures)</li>
                        <li>Colonne F : Volume TP (heures)</li>
                        <li>Colonne G : Autre volume (heures)</li>
                        <li>Colonne H : Crédits ECTS</li>
                    </ul>
                    <p>Les deux premières lignes sont ignorées (en-têtes).</p>
                    <a href="template_ue.xlsx" class="btn btn-primary">
                        <i class="fas fa-download me-1"></i> Télécharger le modèle
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToExcel() {
    window.location.href = 'utils/export_data.php';
}
    </script>
</body>
</html>