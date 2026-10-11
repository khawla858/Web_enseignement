<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

if ($_SESSION['role'] !== 'coordonnateur') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé']);
    exit();
}

// Fonctions de traitement de fichiers
function processCSVFile($filepath) {
    $data = [];
    if (($handle = fopen($filepath, "r")) !== false) {
        while (($row = fgetcsv($handle, 1000, ",")) !== false) {
            $data[] = $row;
        }
        fclose($handle);
    }
    return $data;
}

function processExcelFile($filepath) {
    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        return [['Notice', 'PhpSpreadsheet non installé - impossible de lire le contenu Excel']];
    }

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
        return $spreadsheet->getActiveSheet()->toArray();
    } catch (Exception $e) {
        return [['Erreur Excel', $e->getMessage()]];
    }
}

// Traitement de l'upload du fichier EDT
if ($_GET['action'] === 'upload') {
    if (!isset($_FILES['edtFile']) || $_FILES['edtFile']['error'] !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'upload du fichier']);
        exit;
    }
    
    try {
        $file = $_FILES['edtFile'];
        
        // Créer le répertoire de stockage si inexistant
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Générer un nom de fichier unique
        $filename = uniqid() . '_' . $file['name'];
        $filepath = $upload_dir . $filename;
        
        // Déplacer le fichier uploadé
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Erreur lors du déplacement du fichier');
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'filename' => $filename,
            'filesize' => $file['size'],
            'filetype' => $file['type'],
            'message' => 'Fichier chargé avec succès'
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Nouvelle action pour récupérer l'emploi du temps d'un professeur
if ($_GET['action'] === 'get_prof_edt') {
    if (!isset($_GET['prof_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'ID du professeur manquant']);
        exit;
    }
    
    $prof_id = intval($_GET['prof_id']);
    
    try {
        // Récupérer les UE affectées à ce professeur
        $stmt = $conn->prepare("
            SELECT ue.nom_ue, a.jour, a.heure, a.type_enseignement, a.salle
            FROM affectations a
            JOIN unites_enseignement ue ON a.ue_id = ue.id
            WHERE a.user_id = ? AND a.valide = 1
            ORDER BY a.jour, a.heure
        ");
        $stmt->bind_param("i", $prof_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Récupérer les informations du professeur
        $stmt_prof = $conn->prepare("SELECT nom, prenom FROM users WHERE id = ?");
        $stmt_prof->bind_param("i", $prof_id);
        $stmt_prof->execute();
        $prof_info = $stmt_prof->get_result()->fetch_assoc();
        
        $prof_name = $prof_info ? $prof_info['nom'] . ' ' . $prof_info['prenom'] : 'Professeur inconnu';
        
        // Construire le tableau d'emploi du temps
        $edt = [['UE', 'Jour', 'Heure', 'Type', 'Salle', 'Enseignant']];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $edt[] = [
                    $row['nom_ue'],
                    $row['jour'],
                    $row['heure'],
                    $row['type_enseignement'],
                    $row['salle'],
                    $prof_name
                ];
            }
        } else {
            // Aucune UE affectée, ajouter un message
            $edt[] = ['Aucune UE affectée à ce professeur', '', '', '', '', ''];
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'prof_id' => $prof_id,
            'prof_name' => $prof_name,
            'edt' => $edt
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Traitement d'une affectation individuelle
if ($_GET['action'] === 'save_single_assignment') {
    if (!isset($_POST['assignment']) || !isset($_POST['semestre'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Données manquantes']);
        exit;
    }
    
    try {
        $assignment = json_decode($_POST['assignment'], true);
        $semestre = $_POST['semestre'];
        $annee_univ = date('Y').'-'.(date('Y')+1);
        
        // Récupérer l'ID de l'UE à partir de son nom
        $stmt = $conn->prepare("SELECT id FROM unites_enseignement WHERE nom_ue = ? LIMIT 1");
        $stmt->bind_param("s", $assignment['ue']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $ue_id = $row['id'];
            
            // Vérifier si le professeur est déjà affecté à ce créneau
            $stmt = $conn->prepare("SELECT id FROM affectations 
                WHERE jour = ? AND heure = ? AND user_id = ? AND semestre = ? AND annee_univ = ?");
            $stmt->bind_param("ssiss", 
                $assignment['jour'], 
                $assignment['heure'], 
                $assignment['prof_id'], 
                $semestre, 
                $annee_univ
            );
            $stmt->execute();
            $result_check = $stmt->get_result();
            
            if ($result_check->num_rows > 0) {
                throw new Exception("Ce professeur est déjà affecté à ce créneau");
            }
            
            // Vérifier si ce créneau est déjà affecté à un autre professeur
            $stmt = $conn->prepare("SELECT id FROM affectations 
                WHERE jour = ? AND heure = ? AND ue_id = ? AND semestre = ? AND annee_univ = ?");
            $stmt->bind_param("ssiss", 
                $assignment['jour'], 
                $assignment['heure'], 
                $ue_id, 
                $semestre, 
                $annee_univ
            );
            $stmt->execute();
            $result_check = $stmt->get_result();
            
            if ($result_check->num_rows > 0) {
                // Supprimer l'ancienne affectation
                $stmt = $conn->prepare("DELETE FROM affectations 
                    WHERE jour = ? AND heure = ? AND ue_id = ? AND semestre = ? AND annee_univ = ?");
                $stmt->bind_param("ssiss", 
                    $assignment['jour'], 
                    $assignment['heure'], 
                    $ue_id, 
                    $semestre, 
                    $annee_univ
                );
                $stmt->execute();
            }
            
            // Insérer l'affectation
            $stmt = $conn->prepare("INSERT INTO affectations 
                (user_id, ue_id, type_enseignement, annee_univ, valide, semestre, jour, heure, salle) 
                VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)");
            $stmt->bind_param(
                "iisssss", 
                $assignment['prof_id'], 
                $ue_id, 
                $assignment['type'], 
                $annee_univ,
                $semestre,
                $assignment['jour'],
                $assignment['heure'],
                $assignment['salle']
            );
            $stmt->execute();
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
        } else {
            throw new Exception("UE non trouvée");
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit;
}

elseif ($action === 'get_profs_with_ues') {
    if (!isset($_GET['filiere_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID filière manquant']);
        exit;
    }

    $filiere_id = intval($_GET['filiere_id']);

    $query = "SELECT 
        u.id, u.nom, u.prenom, u.role,
        GROUP_CONCAT(CONCAT(ue.nom_ue, ' (', a.type_enseignement, ')')) AS ues_affectees
      FROM users u
      LEFT JOIN affectations a ON u.id = a.user_id AND a.valide = 1
      LEFT JOIN unites_enseignement ue ON a.ue_id = ue.id
      WHERE u.role IN ('enseignant', 'vacataire')
      AND (u.filiere_id = ? OR EXISTS (
          SELECT 1 FROM professeurs_departements pd
          JOIN departements d ON pd.departement_id = d.id
          JOIN filieres f ON d.id = f.departement_id
          WHERE pd.professeur_id = u.id AND f.id = ?
      ))
      GROUP BY u.id";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $filiere_id, $filiere_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profs = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['status' => 'success', 'profs' => $profs]);
    exit;
}

elseif ($action === 'save_assignments') {
    if (!isset($_POST['assignments']) || !isset($_POST['semestre'])) {
        echo json_encode(['status' => 'error', 'message' => 'Données manquantes']);
        exit;
    }
    
    try {
        $assignments = json_decode($_POST['assignments'], true);
        $semestre = $_POST['semestre'];
        $annee_univ = date('Y').'-'.(date('Y')+1);
        
        // Commencer une transaction
        $conn->begin_transaction();
        
        foreach ($assignments as $assignment) {
            // Récupérer l'ID de l'UE à partir de son nom
            $stmt = $conn->prepare("SELECT id FROM unites_enseignement WHERE nom_ue = ? LIMIT 1");
            $stmt->bind_param("s", $assignment['ue']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $ue_id = $row['id'];
                
                // Vérifier si le professeur est déjà affecté à ce créneau
                $stmt = $conn->prepare("SELECT id FROM affectations 
                    WHERE jour = ? AND heure = ? AND user_id = ? AND semestre = ? AND annee_univ = ?");
                $stmt->bind_param("ssiss", 
                    $assignment['jour'], 
                    $assignment['heure'], 
                    $assignment['prof_id'], 
                    $semestre, 
                    $annee_univ
                );
                $stmt->execute();
                $result_check = $stmt->get_result();
                
                if ($result_check->num_rows > 0) {
                    throw new Exception("Un professeur est déjà affecté à ce créneau");
                }
                
                // Vérifier si ce créneau est déjà affecté à un autre professeur
                $stmt = $conn->prepare("SELECT id FROM affectations 
                    WHERE jour = ? AND heure = ? AND ue_id = ? AND semestre = ? AND annee_univ = ?");
                $stmt->bind_param("ssiss", 
                    $assignment['jour'], 
                    $assignment['heure'], 
                    $ue_id, 
                    $semestre, 
                    $annee_univ
                );
                $stmt->execute();
                $result_check = $stmt->get_result();
                
                if ($result_check->num_rows > 0) {
                    // Supprimer l'ancienne affectation
                    $stmt = $conn->prepare("DELETE FROM affectations 
                        WHERE jour = ? AND heure = ? AND ue_id = ? AND semestre = ? AND annee_univ = ?");
                    $stmt->bind_param("ssiss", 
                        $assignment['jour'], 
                        $assignment['heure'], 
                        $ue_id, 
                        $semestre, 
                        $annee_univ
                    );
                    $stmt->execute();
                }
                
                // Insérer l'affectation
                $stmt = $conn->prepare("INSERT INTO affectations 
                    (user_id, ue_id, type_enseignement, annee_univ, valide, semestre, jour, heure, salle) 
                    VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "iisssss", 
                    $assignment['prof_id'], 
                    $ue_id, 
                    $assignment['type'], 
                    $annee_univ,
                    $semestre,
                    $assignment['jour'],
                    $assignment['heure'],
                    $assignment['salle']
                );
                $stmt->execute();
            }
        }
        
        // Valider la transaction
        $conn->commit();
        
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit;
}

// Traitement de l'affectation d'un professeur à l'emploi du temps
if ($_GET['action'] === 'save_professor_assignment') {
    // Log pour débogage
    error_log("POST data: " . print_r($_POST, true));
    
    if (!isset($_POST['professor_id']) || !isset($_POST['filename']) || !isset($_POST['semestre'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Données manquantes: ' . 
            (isset($_POST['professor_id']) ? '' : 'professor_id manquant, ') . 
            (isset($_POST['filename']) ? '' : 'filename manquant, ') . 
            (isset($_POST['semestre']) ? '' : 'semestre manquant')]);
        exit;
    }
    
    $professor_id = intval($_POST['professor_id']);
    $filename = $_POST['filename'];
    $semestre = $_POST['semestre'];
    $annee_univ = date('Y').'-'.(date('Y')+1);
    
    try {
        // Vérifier si le professeur existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Professeur non trouvé");
        }
        
        // Insérer dans la table emplois_du_temps
        $stmt = $conn->prepare("INSERT INTO emplois_du_temps 
                               (fichier_nom, semestre, annee_univ, user_id) 
                               VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $filename, $semestre, $annee_univ, $professor_id);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success', 
                'message' => 'Emploi du temps affecté avec succès'
            ]);
        } else {
            throw new Exception("Erreur lors de l'insertion dans la base de données: " . $conn->error);
        }
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

else {
    echo json_encode(['status' => 'error', 'message' => 'Action inconnue']);
    exit;
}
?>