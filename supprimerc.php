<?php
session_start();
require 'config.php'; // Connexion à la base de données

// Vérification d'accès (uniquement pour les admins)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);

    // Vérifie si l'utilisateur existe
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Si l'utilisateur existe, le supprimer
        $stmt_delete = $conn->prepare("DELETE FROM users WHERE username = ?");
        $stmt_delete->bind_param("s", $username);
        if ($stmt_delete->execute()) {
            $message = "<p style='color:green;'>Utilisateur supprimé avec succès.</p>";
        } else {
            $message = "<p style='color:red;'>Erreur lors de la suppression.</p>";
        }
        $stmt_delete->close();
    } else {
        $message = "<p style='color:red;'>Utilisateur introuvable.</p>";
    }

    $stmt_check->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Supprimer un utilisateur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fff4f4;
            padding: 40px;
        }
        form {
            background-color: white;
            padding: 25px;
            width: 400px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(200, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #c0392b;
        }
        label, input[type="text"] {
            display: block;
            width: 100%;
            margin-top: 10px;
        }
        input[type="submit"] {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px;
            margin-top: 20px;
            width: 100%;
            cursor: pointer;
            border-radius: 4px;
        }
        input[type="submit"]:hover {
            background-color: #c0392b;
        }
        .message {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <form method="post" action="">
        <h2>Supprimer un utilisateur</h2>
        
        <label for="username">Nom d'utilisateur à supprimer :</label>
        <input type="text" id="username" name="username" required>

        <input type="submit" value="Supprimer l'utilisateur">

        <div class="message">
            <?php echo $message; ?>
        </div>
    </form>

</body>
</html>
