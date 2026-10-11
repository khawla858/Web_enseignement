<?php
session_start();
include('config.php');

// Vérification du mot de passe actuel
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Récupérer le mot de passe actuel de la base de données
    $query = "SELECT mot_de_passe FROM utilisateurs WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);

    // Vérifier si le mot de passe actuel est correct
    if (password_verify($current_password, $user['mot_de_passe'])) {
        // Vérifier si les nouveaux mots de passe correspondent
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE utilisateurs SET mot_de_passe = '$hashed_password' WHERE id = $user_id";
            if (mysqli_query($conn, $update_query)) {
                echo "<p class='success'>Mot de passe mis à jour avec succès.</p>";
            } else {
                echo "<p class='error'>Erreur lors de la mise à jour.</p>";
            }
        } else {
            echo "<p class='error'>Les nouveaux mots de passe ne correspondent pas.</p>";
        }
    } else {
        echo "<p class='error'>Le mot de passe actuel est incorrect.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Changer le mot de passe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 50%;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            font-size: 16px;
            color: #555;
            margin-bottom: 5px;
        }
        input[type="password"] {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        input[type="submit"] {
            padding: 12px 20px;
            background-color: #4CAF50;
            color: white;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        .success {
            color: green;
            text-align: center;
            font-weight: bold;
        }
        .error {
            color: red;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Changer mon mot de passe</h1>
        <form method="POST">
            <label for="current_password">Mot de passe actuel:</label>
            <input type="password" id="current_password" name="current_password" required><br>

            <label for="new_password">Nouveau mot de passe:</label>
            <input type="password" id="new_password" name="new_password" required><br>

            <label for="confirm_password">Confirmer le mot de passe:</label>
            <input type="password" id="confirm_password" name="confirm_password" required><br>

            <input type="submit" value="Changer le mot de passe">
        </form>
    </div>
</body>
</html>
