<?php
session_start();
require 'config.php'; // connexion à la base de données

// Vérification d'accès
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Traitement du formulaire
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $champ = $_POST['champ'];
    $valeur = trim($_POST['valeur']);

    // Protection : autoriser uniquement certains champs
    $champs_valides = ['email', 'nom', 'prenom', 'role', 'specialite'];
    if (!in_array($champ, $champs_valides)) {
        $message = "<p style='color:red;'>Champ non autorisé.</p>";
    } else {
        // Préparer et exécuter la requête
        $stmt = $conn->prepare("UPDATE users SET $champ = ? WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $valeur, $username);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "<p style='color:green;'>Modification réussie.</p>";
            } else {
                $message = "<p style='color:red;'>Aucune modification effectuée. Vérifiez le nom d'utilisateur.</p>";
            }
            $stmt->close();
        } else {
            $message = "<p style='color:red;'>Erreur dans la requête SQL.</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un utilisateur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: rgb(52, 119, 235);
            padding: 40px;
        }
        form {
            background: white;
            padding: 30px;
            width: 400px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #2c3e50;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input[type="text"], select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        input[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px;
            margin-top: 20px;
            width: 100%;
            cursor: pointer;
            border-radius: 4px;
        }
        input[type="submit"]:hover {
            background-color: #2980b9;
        }
        .message {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <form method="post" action="">
        <h2>Modifier un utilisateur</h2>

        <label>Nom d'utilisateur :</label>
        <input type="text" name="username" required>

        <label>Champ à modifier :</label>
        <select name="champ" required>
            <option value="email">Email</option>
            <option value="nom">Nom</option>
            <option value="prenom">Prénom</option>
            <option value="role">Rôle</option>
            <option value="specialite">Spécialité</option>
        </select>

        <label>Nouvelle valeur :</label>
        <input type="text" name="valeur" required>

        <input type="submit" value="Appliquer la modification">
        
        <div class="message">
            <?php echo $message; ?>
        </div>
    </form>

</body>
</html>