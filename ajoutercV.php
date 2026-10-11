<?php
require 'config.php'; // Connexion à la base de données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $nom = trim($_POST["nom"]);
    $prenom = trim($_POST["prenom"]);
    $role = $_POST["role"];
    $specialite = $_POST["specialite"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if ($password !== $confirm_password) {
        $message = "<p class='error'>Les mots de passe ne correspondent pas.</p>";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, nom, prenom, password_hash, role, specialite) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssss", $username, $email, $nom, $prenom, $password_hash, $role, $specialite);
            if ($stmt->execute()) {
                $message = "<p class='success'>Vacataire ajouté avec succès.</p>";
            } else {
                $message = "<p class='error'>Erreur lors de l'insertion : " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            $message = "<p class='error'>Erreur de préparation de la requête : " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un compte vacataire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: rgb(52, 119, 235);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 80px auto;
            background-color: #ffffffea;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 15px;
            color: #333;
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            margin-top: 25px;
            background-color: #27ae60;
            color: white;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #219653;
        }

        .success {
            color: green;
            font-weight: bold;
            text-align: center;
        }

        .error {
            color: red;
            font-weight: bold;
            text-align: center;
        }

        .back-btn {
            display: block;
            margin: 20px auto 0;
            text-align: center;
            text-decoration: none;
            color: #fff;
            background-color: #3498db;
            padding: 10px 20px;
            border-radius: 8px;
        }

        .back-btn:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-user-plus"></i> Créer un compte vacataire</h2>

        <?php if (!empty($message)) echo $message; ?>

        <form method="post" action="">
            <label for="username">Nom d'utilisateur :</label>
            <input type="text" id="username" name="username" required>

            <label for="email">Adresse e-mail :</label>
            <input type="email" id="email" name="email" required>

            <label for="nom">Nom :</label>
            <input type="text" id="nom" name="nom" required>

            <label for="prenom">Prénom :</label>
            <input type="text" id="prenom" name="prenom" required>

            <label for="role">Rôle :</label>
            <select id="role" name="role" required>
                <option value="vacataire">Vacataire</option>
            </select>

            <label for="specialite">Spécialité :</label>
            <select id="specialite" name="specialite" required>
                <option value="" disabled selected>Choisir une spécialité</option>
                <option value="Informatique">Informatique</option>
                <option value="Mathématiques">Mathématiques</option>
                <option value="Réseaux">Réseaux</option>
                <option value="Génie logiciel">Génie logiciel</option>
                <option value="Systèmes embarqués">Systèmes embarqués</option>
                <option value="Intelligence artificielle">Intelligence artificielle</option>
            </select>

            <label for="password">Mot de passe :</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirmer le mot de passe :</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <input type="submit" value="Créer l'utilisateur">
        </form>

        <a class="back-btn" href="coordonnateur.php"><i class="fas fa-arrow-left"></i> Retour à l'espace Coordinateur</a>
    </div>
</body>
</html>
