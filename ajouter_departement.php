<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $coordinateur_id = $_POST['coordinateur_id'];

    if (!empty($nom) && !empty($coordinateur_id)) {
        // Ajouter le département dans la table 'departements'
        $stmt = $conn->prepare("INSERT INTO departements (nom) VALUES (?)");
        $stmt->bind_param("s", $nom);
        
        if ($stmt->execute()) {
            // Récupérer l'ID du département nouvellement inséré
            $departement_id = $conn->insert_id;

            // Ajouter le coordinateur à la table 'chefs_departement'
            $stmt2 = $conn->prepare("INSERT INTO chefs_departement (user_id, departement_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $coordinateur_id, $departement_id);
            
            if ($stmt2->execute()) {
                $message = "✅ Département ajouté et attribué au coordinateur avec succès.";
            } else {
                $message = "❌ Erreur : " . $conn->error;
            }
        } else {
            $message = "❌ Erreur lors de l'ajout du département : " . $conn->error;
        }
    } else {
        $message = "❗ Veuillez remplir tous les champs.";
    }
}

// Récupérer tous les utilisateurs sauf ceux ayant le rôle 'vacataire'
$query = "SELECT id, prenom, nom FROM users WHERE role = 'professeur'";
$coordinateurs_result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un Département</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: rgb(52, 119, 235);
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 40px auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #2980b9;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 16px;
            margin-bottom: 10px;
            color: #555;
        }

        input[type="text"] {
            padding: 12px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 20px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus {
            border-color: #2980b9;
            outline: none;
        }

        select {
            padding: 12px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        button {
            padding: 12px;
            font-size: 16px;
            color: white;
            background-color: #2980b9;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #3498db;
        }

        .message {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }

        .message.success {
            color: green;
        }

        .message.error {
            color: red;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            text-align: center;
            font-size: 16px;
            color: #2980b9;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Ajouter un Département</h2>
        <form method="POST">
            <label for="nom">Nom du département :</label>
            <input type="text" name="nom" id="nom" required placeholder="Entrez le nom du département">

            <label for="coordinateur">Sélectionner un coordinateur :</label>
            <select name="coordinateur_id" id="coordinateur" required>
                <option value="">-- Choisir un chef de département --</option>
                <?php while ($coordinateur = $coordinateurs_result->fetch_assoc()): ?>
                    <option value="<?php echo $coordinateur['id']; ?>">
                        <?php echo $coordinateur['prenom'] . " " . $coordinateur['nom']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit">Ajouter</button>
        </form>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <a href="admin_dashboard.php">← Retour au tableau de bord</a>
    </div>

</body>
</html>
