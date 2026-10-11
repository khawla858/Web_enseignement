<?php
session_start();
require_once 'config.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Récupérer les UE assignées à l'utilisateur
$assigned_ues = [];
try {
    $stmt = $pdo->prepare("
        SELECT ue.id, ue.code_ue, ue.nom_ue 
        FROM affectations a
        JOIN unites_enseignement ue ON a.ue_id = ue.id
        WHERE a.user_id = ? AND a.valide = 1
    ");
    $stmt->execute([$user_id]);
    $assigned_ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des UE assignées: " . $e->getMessage();
}

// Traitement du formulaire d'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_notes'])) {
    $ue_id = $_POST['ue_id'] ?? '';
    $session = $_POST['session'] ?? '';
    $file = $_FILES['notes_file'] ?? null;

    // Validation
    if (empty($ue_id) ){
        $error = "Veuillez sélectionner une UE";
    } elseif (empty($session)) {
        $error = "Veuillez sélectionner une session";
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = "Veuillez sélectionner un fichier valide";
    } else {
        // Vérifier que l'UE est bien assignée à l'utilisateur
        $is_valid_ue = false;
        foreach ($assigned_ues as $ue) {
            if ($ue['id'] == $ue_id) {
                $is_valid_ue = true;
                break;
            }
        }

        if (!$is_valid_ue) {
            $error = "UE non valide ou non assignée";
        } else {
            // Vérifier l'extension du fichier
            $allowed_extensions = ['pdf', 'xls', 'xlsx', 'csv'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $error = "Format de fichier non supporté. Formats acceptés: PDF, XLS, XLSX, CSV";
            } else {
                // Créer le répertoire de stockage si inexistant
                $upload_dir = 'uploads/notes/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Générer un nom de fichier unique
                $filename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.]/', '', $file['name']);
                $target_path = $upload_dir . $filename;

                // Déplacer le fichier uploadé
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    try {
                        // Enregistrer dans la base de données
                        $stmt = $pdo->prepare("
                            INSERT INTO notes (ue_id, ens_id, session, fichier_path)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$ue_id, $user_id, $session, $target_path]);
                        
                        $success = "Fichier de notes uploadé avec succès!";
                    } catch (PDOException $e) {
                        // Supprimer le fichier en cas d'erreur BD
                        unlink($target_path);
                        $error = "Erreur lors de l'enregistrement en base de données: " . $e->getMessage();
                    }
                } else {
                    $error = "Erreur lors de l'upload du fichier";
                }
            }
        }
    }
}

// Récupérer l'historique des uploads
$upload_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.*, ue.code_ue, ue.nom_ue 
        FROM notes n
        JOIN unites_enseignement ue ON n.ue_id = ue.id
        WHERE n.ens_id = ?
        ORDER BY n.date_upload DESC
    ");
    $stmt->execute([$user_id]);
    $upload_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération de l'historique: " . $e->getMessage();
}

// Après avoir récupéré les modules
$modules = $stmt->fetchAll();

// Définir l'année universitaire (simplement)
$annee_univ = "2025-2026"; 

// Récupérer dynamiquement le département du professeur
$stmtDept = $pdo->prepare("
    SELECT d.nom 
    FROM departements d
    JOIN professeurs_departements pd ON d.id = pd.departement_id
    WHERE pd.professeur_id = ?
");
$stmtDept->execute([$_SESSION['user_id']]);
$departement_nom = $stmtDept->fetchColumn();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expression des souhaits d'enseignement</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background-color: #3a86ff;
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            border-radius: 5px;/**jn njjjjjjjjjjjjjjjjjjj */
        }
        
        .header h1 {
            margin: 0;
            padding: 0;
            font-size: 24px;
            color: #2c3e50;
            text-align: center;
        }
        
        .header-info {
            display: flex;
            justify-content: space-around;
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .back-link {
            background-color: white;
            color: #3498db;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        
        .back-link:hover {
            background-color: #2980b9;
            color: white;        }
        
        .professor-info {
            font-weight: bold;
            color:rgb(250, 253, 255);
        }
        
        .card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        select, input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .file-link {
            color: #3498db;
            text-decoration: none;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #777;
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
    color: #dfe6e9; /* gris très clair pour contraste */
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
   
    <div class="container">
 
      <div class="header-prof">
     <div class="header-content">
     <p>Professeur : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>
 
         <div class="header-text">
             <h2>Uploader les notes</h2>
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
?>" class="btn-retour">Retour</a>
     </div>
 </div>
 <div class="container">
 <div class="container">
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Uploader les notes</h2>
            
            <form action="Upload_Note_prof.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="ue_id">Unité d'Enseignement</label>
                    <select id="ue_id" name="ue_id" required>
                        <option value="">-- Sélectionner une UE --</option>
                        <?php foreach ($assigned_ues as $ue): ?>
                            <option value="<?php echo htmlspecialchars($ue['id']); ?>">
                                <?php echo htmlspecialchars($ue['code_ue'] . ' - ' . $ue['nom_ue']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="session">Session</label>
                    <select id="session" name="session" required>
                        <option value="">-- Sélectionner une session --</option>
                        <option value="normale">Session Normale</option>
                        <option value="rattrapage">Session de Rattrapage</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes_file">Fichier de notes (PDF, Excel, CSV)</label>
                    <input type="file" id="notes_file" name="notes_file" accept=".pdf,.xls,.xlsx,.csv" required>
                </div>
                
                <button type="submit" name="submit_notes" class="btn">Uploader les notes</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Historique des Uploads</h2>
            
            <?php if (empty($upload_history)): ?>
                <p class="no-data">Aucun fichier uploadé pour le moment</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>UE</th>
                            <th>Session</th>
                            <th>Date d'Upload</th>
                            <th>Fichier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upload_history as $upload): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($upload['code_ue'] . ' - ' . $upload['nom_ue']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($upload['session'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($upload['date_upload'])); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($upload['fichier_path']); ?>" class="file-link" target="_blank">
                                        Télécharger
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>