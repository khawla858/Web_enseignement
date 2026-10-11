<?php
session_start();
require 'config.php';

// Vérification des droits d'accès - CORRIGÉ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = "";

// Récupérer la liste des professeurs
$professeurs = [];
$query = "SELECT id, username, nom, prenom, role FROM users WHERE role IN ('professeur', 'vacataire', 'coordonnateur', 'chef_departement') ORDER BY nom ASC";
$result = $conn->query($query);
if ($result) {
    $professeurs = $result->fetch_all(MYSQLI_ASSOC);
}

// Traitement du formulaire de modification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])){
    $user_id = $_POST['user_id'];
    $nouveau_role = $_POST['nouveau_role'];
    
    // Validation du rôle
    $roles_autorises = ['professeur', 'vacataire', 'coordonnateur', 'chef_departement'];
    if (!in_array($nouveau_role, $roles_autorises)) {
        $message = "<div class='error'>Rôle non valide.</div>";
    } else {
        // Mise à jour du rôle
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $nouveau_role, $user_id);
        
        if ($stmt->execute()) {
            // Gestion des tables spécifiques selon le nouveau rôle
            if ($nouveau_role === 'coordonnateur') {
                // Supprimer les anciennes associations
                $conn->query("DELETE FROM coordinateurs_filieres WHERE user_id = $user_id");
                
                // Ajouter la nouvelle filière si fournie
                if (isset($_POST['filiere_id']) && !empty($_POST['filiere_id'])) {
                    $filiere_id = $_POST['filiere_id'];
                    $stmt_filiere = $conn->prepare("INSERT INTO coordinateurs_filieres (user_id, filiere_id) VALUES (?, ?)");
                    $stmt_filiere->bind_param("ii", $user_id, $filiere_id);
                    $stmt_filiere->execute();
                    $stmt_filiere->close();
                }
            } elseif ($nouveau_role === 'chef_departement') {
                // Supprimer les anciennes associations
                $conn->query("DELETE FROM chefs_departement WHERE user_id = $user_id");
                
                // Ajouter le nouveau département si fourni
                if (isset($_POST['departement_id']) && !empty($_POST['departement_id'])) {
                    $departement_id = $_POST['departement_id'];
                    $stmt_dept = $conn->prepare("INSERT INTO chefs_departement (user_id, departement_id) VALUES (?, ?)");
                    $stmt_dept->bind_param("ii", $user_id, $departement_id);
                    $stmt_dept->execute();
                    $stmt_dept->close();
                }
            }
            
            $message = "<div class='success'>Permissions mises à jour avec succès.</div>";
        } else {
            $message = "<div class='error'>Erreur lors de la mise à jour : " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Récupérer la liste des filières et départements pour les menus déroulants
$filieres = $conn->query("SELECT id, nom FROM filieres ORDER BY nom ASC")->fetch_all(MYSQLI_ASSOC);
$departements = $conn->query("SELECT id, nom FROM departements ORDER BY nom ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier les permissions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f2f4f8;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1000px;
            margin: 20px auto;
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
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
            background-color: #3498db;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .btn {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #219653;
        }
        .success {
            color: green;
            background-color: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error {
            color: red;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .role-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .role-form select {
            flex: 1;
        }
        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-btn:hover {
            background-color: #2980b9;
        }
        .welcome {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    
    <div class="main">
        <div class="header">
            <div class="welcome">Bienvenue, <?php echo $_SESSION['prenom'] . " " . $_SESSION['nom']; ?> 👋</div>
        </div>

        <div class="container">
            <h2><i class="fas fa-user-shield"></i> Modifier les permissions</h2>
            
            <?php if (!empty($message)) echo $message; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Nom d'utilisateur</th>
                        <th>Rôle actuel</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($professeurs as $prof): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prof['nom']); ?></td>
                        <td><?php echo htmlspecialchars($prof['prenom']); ?></td>
                        <td><?php echo htmlspecialchars($prof['username']); ?></td>
                        <td><?php echo htmlspecialchars($prof['role']); ?></td>
                        <td>
                            <form method="post" class="role-form">
                                <input type="hidden" name="user_id" value="<?php echo $prof['id']; ?>">
                                
                                <div style="flex: 1;">
                                    <label for="nouveau_role_<?php echo $prof['id']; ?>">Nouveau rôle</label>
                                    <select name="nouveau_role" id="nouveau_role_<?php echo $prof['id']; ?>" required>
                                        <option value="professeur" <?php echo $prof['role'] === 'professeur' ? 'selected' : ''; ?>>Professeur</option>
                                        <option value="vacataire" <?php echo $prof['role'] === 'vacataire' ? 'selected' : ''; ?>>Vacataire</option>
                                        <option value="coordonnateur" <?php echo $prof['role'] === 'coordonnateur' ? 'selected' : ''; ?>>Coordonnateur</option>
                                        <option value="chef_departement" <?php echo $prof['role'] === 'chef_departement' ? 'selected' : ''; ?>>Chef de département</option>
                                    </select>
                                </div>
                                
                                <div style="flex: 1; display: <?php echo $prof['role'] === 'coordonnateur' ? 'block' : 'none'; ?>" class="filiere-field">
                                    <label for="filiere_id_<?php echo $prof['id']; ?>">Filière</label>
                                    <select name="filiere_id" id="filiere_id_<?php echo $prof['id']; ?>">
                                        <option value="">Sélectionner une filière</option>
                                        <?php foreach ($filieres as $filiere): ?>
                                            <option value="<?php echo $filiere['id']; ?>"><?php echo htmlspecialchars($filiere['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div style="flex: 1; display: <?php echo $prof['role'] === 'chef_departement' ? 'block' : 'none'; ?>" class="departement-field">
                                    <label for="departement_id_<?php echo $prof['id']; ?>">Département</label>
                                    <select name="departement_id" id="departement_id_<?php echo $prof['id']; ?>">
                                        <option value="">Sélectionner un département</option>
                                        <?php foreach ($departements as $departement): ?>
                                            <option value="<?php echo $departement['id']; ?>"><?php echo htmlspecialchars($departement['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn">Mettre à jour</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
    </div>

    <script>
        // Afficher/masquer les champs en fonction du rôle sélectionné
        document.querySelectorAll('select[name="nouveau_role"]').forEach(select => {
            select.addEventListener('change', function() {
                const form = this.closest('form');
                const filiereField = form.querySelector('.filiere-field');
                const departementField = form.querySelector('.departement-field');
                
                if (this.value === 'coordonnateur') {
                    filiereField.style.display = 'block';
                    departementField.style.display = 'none';
                } else if (this.value === 'chef_departement') {
                    filiereField.style.display = 'none';
                    departementField.style.display = 'block';
                } else {
                    filiereField.style.display = 'none';
                    departementField.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>