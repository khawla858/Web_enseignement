<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordonnateur') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Compte - Uniassign</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #74ebd5 0%, #ACB6E5 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .profile-card i {
            font-size: 80px;
            color: #3498db;
            margin-bottom: 20px;
        }
        .profile-card h2 {
            margin: 10px 0;
            font-size: 28px;
            color: #333;
        }
        .profile-card p {
            margin: 8px 0;
            font-size: 18px;
            color: #555;
        }
        .edit-btn {
            margin-top: 20px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .edit-btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>

<div class="profile-card">
    <i class="fas fa-user-circle"></i>
    <h2><?php echo htmlspecialchars($_SESSION['prenom'] . " " . $_SESSION['nom']); ?></h2>
    <p><strong>Rôle :</strong> Coordonnateur</p>
    <p><strong>Email :</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>

    <button class="edit-btn">Modifier mon compte</button>
</div>

</body>
</html>
