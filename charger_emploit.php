<?php
session_start();
require_once 'config.php';

if ($_SESSION['role'] !== 'coordonnateur') {
    header("Location: login.php");
    exit();
}

// Récupérer les filières gérées par ce coordinateur
$user_id = $_SESSION['user_id'];
$filieres = [];

$stmt = $conn->prepare("SELECT f.id, f.nom FROM filieres f 
                        JOIN coordinateurs_filieres cf ON f.id = cf.filiere_id 
                        WHERE cf.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $filieres[] = $row;
}
$stmt->close();

// Récupérer les enseignants de la filière avec leurs UE affectées
$profs_with_ues = [];
if (!empty($filieres)) {
    $filiere_id = $filieres[0]['id']; // Prendre la première filière par défaut
    
    $query = "SELECT 
    u.id, u.nom, u.prenom, u.role,
    GROUP_CONCAT(DISTINCT CONCAT(ue.nom_ue, ' (', a.type_enseignement, ')') SEPARATOR ', ') AS ues_affectees,
    COUNT(a.id) AS nombre_affectations
  FROM users u
  JOIN affectations a ON u.id = a.user_id 
  JOIN unites_enseignement ue ON a.ue_id = ue.id
  WHERE u.role IN ('professeur', 'vacataire', 'coordonnateur')
  AND (u.filiere_id = ? OR EXISTS (
      SELECT 1 FROM professeurs_departements pd
      JOIN departements d ON pd.departement_id = d.id
      JOIN filieres f ON d.id = f.departement_id
      WHERE pd.professeur_id = u.id AND f.id = ?
  ))
  GROUP BY u.id
  HAVING COUNT(a.id) > 0";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $filiere_id, $filiere_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profs_with_ues = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Emplois du Temps</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        select, input { width: 100%; padding: 8px; }
        button { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color:rgb(160, 87, 69); }
        .error { color: red; }
        .success { color: green; }
        .prof-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            cursor: move;
        }
        .prof-header {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .prof-ues {
            margin-left: 20px;
            color: #555;
        }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
            min-height: 100px;
            background-color: #f8f8f8;
        }
        .drop-zone.highlight {
            border-color: #4CAF50;
            background-color: #e8f5e9;
        }
        .dropped-prof {
            background-color: #e8f5e9;
            border: 1px solid #4CAF50;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .save-button {
            display: block;
            margin: 20px auto;
            background-color: #4361ee;
            color: white;
            padding: 15px 30px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            width: 100%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .save-button:hover {
            background-color: #3a56d4;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        .assignment-buttons {
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
        }
        .save-assignment-btn {
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 3px 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .save-assignment-btn:hover {
            background-color: #3a56d4;
        }
        .remove-assignment-btn {
            background-color: #e91e63;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 3px 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .remove-assignment-btn:hover {
            background-color: #d81b60;
        }
        .prof-assignment {
            min-height: 60px;
            position: relative;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestion des Emplois du Temps</h1>
        
        <div class="section">
            <h2>Charger un Emploi du Temps</h2>
            <form id="uploadForm" action="process_edt.php?action=upload" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="semestre">Semestre:</label>
                    <select id="semestre" name="semestre" required>
                        <option value="S1">Semestre 1</option>
                        <option value="S2">Semestre 2</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edtFile">Fichier Emploi du Temps:</label>
                    <input type="file" id="edtFile" name="edtFile" accept=".csv,.xls,.xlsx,.pdf" required>
                    <small>Format attendu: UE,Jour,Heure,Type,Salle</small>
                </div>
                
                <button type="submit">Charger l'Emploi du Temps</button>
            </form>
            <div id="uploadStatus"></div>
            <div id="edtPreview"></div>
            
            <div id="dropZone" class="drop-zone">
                <p><strong>Sélectionnez un seul enseignant</strong> pour lui affecter l'emploi du temps</p>
                <p style="font-size: 0.9em; color: #666;">Glissez-déposez un enseignant ci-dessous. Un seul enseignant peut être affecté à cet emploi du temps.</p>
                <div id="droppedProfs"></div>
            </div>
        </div>

        <!-- Bouton d'enregistrement des affectations -->
        <button id="saveAffectations" class="save-button" style="display:none;">
            <i class="fas fa-save"></i> ENREGISTRER LES AFFECTATIONS
        </button>
        
        <div class="section">
            <h2>Enseignants de la filière avec leurs UE affectées</h2>
            <div id="profsList">
                <?php if (!empty($profs_with_ues)): ?>
                    <?php foreach ($profs_with_ues as $prof): ?>
                        <div class="prof-card" draggable="true" data-prof-id="<?= $prof['id'] ?>" data-prof-name="<?= $prof['nom'] ?> <?= $prof['prenom'] ?>" data-prof-role="<?= $prof['role'] ?>">
                            <div class="prof-header">
                                <?= $prof['nom'] ?> <?= $prof['prenom'] ?>
                                <?php if ($prof['role'] === 'vacataire'): ?>
                                    <span style="color:#e91e63;">(Vacataire)</span>
                                <?php endif; ?>
                            </div>
                            <div class="prof-ues">
                                <?php if (!empty($prof['ues_affectees'])): ?>
                                    <strong>UE affectées:</strong> <?= $prof['ues_affectees'] ?>
                                    <br><small>(<?= $prof['nombre_affectations'] ?> affectation(s))</small>
                                <?php else: ?>
                                    <em>Aucune UE affectée</em>
                                    <br><small>(<?= $prof['nombre_affectations'] ?> affectation(s))</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucun enseignant trouvé pour cette filière</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gérer l'upload du fichier EDT
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var uploadStatus = document.getElementById('uploadStatus');
            uploadStatus.innerHTML = '<p>Chargement en cours...</p>';
            uploadStatus.className = '';
            
            var formData = new FormData(this);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'process_edt.php?action=upload', true);
           xhr.onload = function() {
    if (xhr.status === 200) {
        try {
            var data = JSON.parse(xhr.responseText);
            if (data.status === 'success') {
                uploadStatus.innerHTML = '<p class="success">Emploi du temps chargé avec succès!</p>';
                
                // Stocker le nom de fichier dans l'élément edtPreview
                var edtPreview = document.getElementById('edtPreview');
                edtPreview.setAttribute('data-filename', data.filename);
                
                // Afficher le simple message avec le nom du fichier
                edtPreview.innerHTML = `
                    <div style="margin-top: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
                        <div style="display: flex; align-items: center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="margin-right: 10px;">
                                <path fill="#4CAF50" d="M14,2H6C4.9,2 4,2.9 4,4V20C4,21.1 4.9,22 6,22H18C19.1,22 20,21.1 20,20V8L14,2M18,20H6V4H13V9H18V20M10,13V11H18V13H10M10,17V15H18V17H10M8,9V7H11V9H8M8,13V11H9V13H8M8,17V15H9V17H8Z" />
                            </svg>
                            <div>
                                <span style="font-weight: bold;">Fichier chargé : ${data.filename}</span><br>
                                <small style="color: #666;">Prêt à être affecté à un enseignant</small>
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('saveAffectations').style.display = 'block';
            } else {
                uploadStatus.innerHTML = '<p class="error">' + data.message + '</p>';
            }
        } catch (e) {
            uploadStatus.innerHTML = '<p class="error">Erreur de traitement du fichier</p>';
        }
    } else {
        uploadStatus.innerHTML = '<p class="error">Erreur lors du chargement du fichier</p>';
    }
};
            
            xhr.onerror = function() {
                uploadStatus.innerHTML = '<p class="error">Erreur lors du chargement du fichier</p>';
            };
            
            xhr.send(formData);
        });
        
        // Fonction pour formater la taille du fichier
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' octets';
            else if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' Ko';
            else return (bytes / 1048576).toFixed(2) + ' Mo';
        }
        
        // Configuration du drag & drop
        function setupDragAndDrop() {
            var profCards = document.querySelectorAll('.prof-card');
            var dropZone = document.getElementById('dropZone');
            
            profCards.forEach(function(card) {
                card.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', JSON.stringify({
                        id: this.dataset.profId,
                        name: this.dataset.profName,
                        role: this.dataset.profRole
                    }));
                    this.classList.add('dragging');
                });
                
                card.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                });
            });
            
            // Configuration de la zone de dépôt
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('highlight');
            });
            
            dropZone.addEventListener('dragleave', function() {
                this.classList.remove('highlight');
            });
            
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('highlight');
                
                try {
                    var profData = JSON.parse(e.dataTransfer.getData('text/plain'));
                    var droppedProfsDiv = document.getElementById('droppedProfs');
                    
                    // Vérifier si un prof est déjà dans la liste
                    if (droppedProfsDiv.children.length > 0) {
                        // Remplacer le prof existant par le nouveau
                        droppedProfsDiv.innerHTML = '';
                    }
                    
                    // Ajouter le nouveau prof
                    var profElement = document.createElement('div');
                    profElement.className = 'dropped-prof';
                    profElement.dataset.profId = profData.id;
                    profElement.innerHTML = '<strong>' + profData.name + '</strong>' + 
                        (profData.role === 'vacataire' ? '<span style="color:#e91e63;">(Vacataire)</span>' : '') +
                        '<button class="remove-prof" onclick="this.parentNode.remove()">×</button>';
                    droppedProfsDiv.appendChild(profElement);
                    
                    // Afficher un message indiquant que seul ce prof sera affecté
                    var messageElement = document.createElement('p');
                    messageElement.style.marginTop = '10px';
                    messageElement.style.fontStyle = 'italic';
                    messageElement.style.color = '#666';
                    messageElement.textContent = 'Cet enseignant sera affecté à l\'emploi du temps.';
                    
                    // Supprimer l'ancien message s'il existe
                    var existingMessage = droppedProfsDiv.querySelector('.single-prof-message');
                    if (existingMessage) {
                        existingMessage.remove();
                    }
                    
                    // Ajouter la classe pour pouvoir le retrouver facilement
                    messageElement.className = 'single-prof-message';
                    droppedProfsDiv.appendChild(messageElement);
                    
                } catch (e) {
                    console.error('Erreur lors du drop:', e);
                }
            });
        }
        
        // Configuration des zones de drop dans le tableau
        function setupTableDropZones() {
            var profAssignmentCells = document.querySelectorAll('.prof-assignment');
            
            profAssignmentCells.forEach(function(cell) {
                cell.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('highlight');
                });
                
                cell.addEventListener('dragleave', function() {
                    this.classList.remove('highlight');
                });
                
                cell.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('highlight');
                    
                    try {
                        var profData = JSON.parse(e.dataTransfer.getData('text/plain'));
                        
                        // Vérifier si un prof est sélectionné dans la zone de dépôt
                        var droppedProf = document.querySelector('.dropped-prof');
                        if (!droppedProf) {
                            alert('Veuillez d\'abord sélectionner un enseignant en le glissant dans la zone d\'affectation.');
                            return;
                        }
                        
                        // Vérifier si c'est le même prof que celui sélectionné
                        var selectedProfId = droppedProf.dataset.profId;
                        if (profData.id !== selectedProfId) {
                            alert('Vous ne pouvez affecter que l\'enseignant sélectionné dans la zone d\'affectation.');
                            return;
                        }
                        
                        // Si le prof est déjà assigné à ce même créneau, ne rien faire
                        if (this.dataset.profId === profData.id) {
                            return;
                        }
                        
                        // Récupérer les informations du créneau
                        var rowId = this.dataset.rowId;
                        var row = document.querySelector('tr[data-row-id="' + rowId + '"]');
                        var cells = row.querySelectorAll('td');
                        var jour = cells[1].textContent;
                        var heure = cells[2].textContent;
                        
                        // Vérifier si le prof est déjà occupé à ce créneau (même jour et même heure)
                        var existingAssignments = document.querySelectorAll('.prof-assignment[data-prof-id="' + profData.id + '"]');
                        var conflit = false;
                        
                        existingAssignments.forEach(function(assignment) {
                            var assignmentRowId = assignment.dataset.rowId;
                            var assignmentRow = document.querySelector('tr[data-row-id="' + assignmentRowId + '"]');
                            var assignmentCells = assignmentRow.querySelectorAll('td');
                            var assignmentJour = assignmentCells[1].textContent;
                            var assignmentHeure = assignmentCells[2].textContent;
                            
                            if (jour === assignmentJour && heure === assignmentHeure) {
                                conflit = true;
                            }
                        });
                        
                        if (conflit) {
                            alert('Impossible d\'affecter ' + profData.name + ' à ce créneau : il est déjà occupé à la même heure.');
                            return;
                        }
                        
                        // Assigner le prof au créneau
                        this.innerHTML = profData.name + 
                            (profData.role === 'vacataire' ? '<span style="color:#e91e63;">(Vacataire)</span>' : '') +
                            '<input type="hidden" name="assignments[' + this.dataset.rowId + ']" value="' + profData.id + '">' +
                            '<div class="assignment-buttons">' +
                            '<button class="save-assignment-btn" onclick="saveAssignment(this)" style="margin-right:5px;background-color:#4361ee;color:white;border:none;border-radius:4px;padding:3px 8px;cursor:pointer;">' +
                            'Enregistrer</button>' +
                            '<button class="remove-assignment-btn" onclick="removeAssignment(this)" style="color:white;background-color:#e91e63;border:none;border-radius:4px;padding:3px 8px;cursor:pointer;">' +
                            'Supprimer</button>' +
                            '</div>';
                        this.dataset.profId = profData.id;
                    } catch (e) {
                        console.error('Erreur lors du drop dans le tableau:', e);
                    }
                });
            });
        }

        // Fonction pour afficher l'EDT
        function displayEdt(data, profName) {
            if (!data || data.length === 0) {
                return;
            }
            
            var edtPreview = document.getElementById('edtPreview');
            
            // Créer un tableau pour afficher les données
            var html = '<h3>Emploi du temps de ' + profName + '</h3>';
            html += '<table class="table table-bordered"><thead><tr>';
            
            // En-têtes du tableau
            data[0].forEach(function(header) {
                html += '<th>' + header + '</th>';
            });
            html += '</tr></thead><tbody>';
            
            // Lignes de données (en ignorant la première ligne d'en-tête)
            for (var i = 1; i < data.length; i++) {
                html += '<tr>';
                data[i].forEach(function(cell) {
                    html += '<td>' + cell + '</td>';
                });
                html += '</tr>';
            }
            
            html += '</tbody></table>';
            
            edtPreview.innerHTML = html;
        }

        // Fonction pour charger l'emploi du temps d'un professeur
        function loadProfessorEdt(profId) {
            fetch('process_edt.php?action=get_prof_edt&prof_id=' + profId)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        displayEdt(data.edt, data.prof_name);
                        
                        // Afficher le bouton d'enregistrement
                        document.getElementById('saveAffectations').style.display = 'block';
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // Fonction pour charger la liste des enseignants
        function loadProfessors() {
            fetch('process_edt.php?action=get_profs_with_ues')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        var selects = document.querySelectorAll('.prof-select');
                        selects.forEach(function(select) {
                            data.profs.forEach(function(prof) {
                                var option = document.createElement('option');
                                option.value = prof.id;
                                option.textContent = prof.nom + ' ' + prof.prenom;
                                select.appendChild(option);
                            });
                        });
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // Fonction pour supprimer une affectation
        window.removeAssignment = function(button) {
            var cell = button.closest('.prof-assignment');
            cell.innerHTML = 'Non assigné';
            cell.removeAttribute('data-prof-id');
        };

        // Fonction pour enregistrer une affectation individuelle
        window.saveAssignment = function(button) {
            var cell = button.closest('.prof-assignment');
            var rowId = cell.dataset.rowId;
            var profId = cell.dataset.profId;
            
            // Vérifier si un prof est sélectionné dans la zone de dépôt
            var droppedProf = document.querySelector('.dropped-prof');
            if (!droppedProf) {
                alert('Veuillez d\'abord sélectionner un enseignant en le glissant dans la zone d\'affectation.');
                return;
            }
            
            // Utiliser uniquement le prof qui a été déposé dans la zone
            var selectedProfId = droppedProf.dataset.profId;
            if (profId !== selectedProfId) {
                alert('Vous ne pouvez affecter que l\'enseignant sélectionné dans la zone d\'affectation.');
                return;
            }
            
            var row = document.querySelector('tr[data-row-id="' + rowId + '"]');
            var cells = row.querySelectorAll('td');
            
            // Récupérer les données de la ligne
            var assignment = {
                prof_id: profId,
                ue: cells[0].textContent,
                jour: cells[1].textContent,
                heure: cells[2].textContent,
                type: cells[3].textContent,
                salle: cells[4].textContent
            };
            
            // Afficher un indicateur de chargement
            button.innerHTML = 'Enregistrement...';
            button.disabled = true;
            
            // Envoyer les données au serveur
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'process_edt.php?action=save_single_assignment', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            // Changer le style du bouton pour indiquer le succès
                            button.innerHTML = 'Enregistré';
                            button.style.backgroundColor = '#4CAF50';
                            
                            // Réactiver le bouton après un délai
                            setTimeout(function() {
                                button.innerHTML = 'Enregistrer';
                                button.style.backgroundColor = '#4361ee';
                                button.disabled = false;
                            }, 2000);
                        } else {
                            alert('Erreur: ' + response.message);
                            button.innerHTML = 'Enregistrer';
                            button.disabled = false;
                        }
                    } catch (e) {
                        alert('Erreur lors du traitement de la réponse');
                        button.innerHTML = 'Enregistrer';
                        button.disabled = false;
                    }
                } else {
                    alert('Erreur de communication avec le serveur');
                    button.innerHTML = 'Enregistrer';
                    button.disabled = false;
                }
            };
            
            xhr.onerror = function() {
                alert('Erreur de communication avec le serveur');
                button.innerHTML = 'Enregistrer';
                button.disabled = false;
            };
            
            // Préparer les données à envoyer
            var data = 'assignment=' + encodeURIComponent(JSON.stringify(assignment)) + 
                       '&semestre=' + encodeURIComponent(document.getElementById('semestre').value);
            
            xhr.send(data);
        };

        // Enregistrer les affectations
        document.getElementById('saveAffectations').addEventListener('click', function() {
            // Vérifier si un professeur a été sélectionné
            var droppedProf = document.querySelector('.dropped-prof');
            if (!droppedProf) {
                alert('Veuillez d\'abord sélectionner un enseignant en le glissant dans la zone d\'affectation.');
                return;
            }
            
            // Récupérer l'ID du professeur
            var professorId = droppedProf.dataset.profId;
            
            // Récupérer le nom du fichier et le semestre
            var filename = document.querySelector('#edtPreview').getAttribute('data-filename');
            var semestre = document.getElementById('semestre').value;
            
            if (!filename) {
                alert('Aucun fichier d\'emploi du temps n\'a été chargé.');
                return;
            }
            
            // Afficher un indicateur de chargement
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement en cours...';
            this.disabled = true;
            
            // Envoyer les données au serveur
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'process_edt.php?action=save_professor_assignment', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        // Afficher la réponse brute en cas d'erreur pour le débogage
                        console.log("Réponse du serveur:", xhr.responseText);
                        
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            // Afficher un message de succès
                            alert('Emploi du temps affecté avec succès au professeur !');
                            
                            // Rediriger vers la page principale
                            window.location.href = 'dashboard.php';
                        } else {
                            alert('Erreur: ' + response.message);
                            // Réactiver le bouton
                            document.getElementById('saveAffectations').innerHTML = '<i class="fas fa-save"></i> ENREGISTRER LES AFFECTATIONS';
                            document.getElementById('saveAffectations').disabled = false;
                        }
                    } catch (e) {
                        console.error("Erreur de parsing JSON:", e);
                        console.log("Réponse brute:", xhr.responseText);
                        alert('Erreur lors du traitement de la réponse. Vérifiez la console pour plus de détails.');
                        // Réactiver le bouton
                        document.getElementById('saveAffectations').innerHTML = '<i class="fas fa-save"></i> ENREGISTRER LES AFFECTATIONS';
                        document.getElementById('saveAffectations').disabled = false;
                    }
                } else {
                    alert('Erreur de communication avec le serveur: ' + xhr.status);
                    // Réactiver le bouton
                    document.getElementById('saveAffectations').innerHTML = '<i class="fas fa-save"></i> ENREGISTRER LES AFFECTATIONS';
                    document.getElementById('saveAffectations').disabled = false;
                }
            };
            
            xhr.onerror = function() {
                alert('Erreur de communication avec le serveur');
                // Réactiver le bouton
                document.getElementById('saveAffectations').innerHTML = '<i class="fas fa-save"></i> ENREGISTRER LES AFFECTATIONS';
                document.getElementById('saveAffectations').disabled = false;
            };
            
            // Préparer les données à envoyer
            var data = 'professor_id=' + encodeURIComponent(professorId) + 
                       '&filename=' + encodeURIComponent(filename) + 
                       '&semestre=' + encodeURIComponent(semestre);
            
            xhr.send(data);
        });
        
        // Initialiser le drag & drop
        setupDragAndDrop();
    });
    </script>
</body>
</html>