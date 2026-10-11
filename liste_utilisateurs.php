<?php
session_start();
require 'config.php'; // Connexion DB

// Accès réservé à l'admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Récupérer les utilisateurs
$sql = "SELECT username, email, nom, prenom, role, specialite FROM users";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des utilisateurs</title>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4caf50;
            --hover-success: #3e8e41;
            --table-border: #dee2e6;
            --table-hover: rgba(67, 97, 238, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background: linear-gradient(135deg,rgb(53, 109, 193) 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        h2 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 2rem;
            font-size: 2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--accent-color);
            border-radius: 2px;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }
        
        thead {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--table-border);
        }
        
        th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        tr:nth-child(even) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        tr:hover {
            background-color: var(--table-hover);
            transition: background-color 0.2s ease;
        }
        
        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 200px;
            margin: 2rem auto 0;
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-back:hover {
            background-color: var(--hover-success);
        }
        
        .btn-back::before {
            content: '←';
            margin-right: 0.5rem;
        }
        
        .empty-message {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body >
    <div class="container">
        <h2>Liste des utilisateurs</h2>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Rôle</th>
                        <th>Spécialité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row["username"]) ?></td>
                                <td><?= htmlspecialchars($row["email"]) ?></td>
                                <td><?= htmlspecialchars($row["nom"]) ?></td>
                                <td><?= htmlspecialchars($row["prenom"]) ?></td>
                                <td><?= htmlspecialchars($row["role"]) ?></td>
                                <td><?= htmlspecialchars($row["specialite"]) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="empty-message">Aucun utilisateur trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <a href="admin.php" class="btn btn-back">Retour à l'accueil </a>
    </div>
</body>
</html>