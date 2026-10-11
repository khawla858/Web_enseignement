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
    $departement_id = $_POST['departement_id'];

    if (!empty($nom) && !empty($coordinateur_id) && !empty($departement_id)) {
        // Ajouter la filière dans la table 'filieres' avec le département
        $stmt = $conn->prepare("INSERT INTO filieres (nom, departement_id) VALUES (?, ?)");
        $stmt->bind_param("si", $nom, $departement_id);
        
        if ($stmt->execute()) {
            // Récupérer l'ID de la filière nouvellement insérée
            $filiere_id = $conn->insert_id;

            // Ajouter le coordinateur à la table 'coordinateurs_filieres'
            $stmt2 = $conn->prepare("INSERT INTO coordinateurs_filieres (user_id, filiere_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $coordinateur_id, $filiere_id);
            
            if ($stmt2->execute()) {
                $message = "✅ Filière ajoutée et attribuée avec succès.";
            } else {
                $message = "❌ Erreur lors de l'attribution du coordinateur : " . $conn->error;
            }
        } else {
            $message = "❌ Erreur lors de l'ajout de la filière : " . $conn->error;
        }
    } else {
        $message = "❗ Veuillez remplir tous les champs.";
    }
}

// Récupérer tous les professeurs (coordinateurs potentiels)
$coordinateurs_query = "SELECT id, prenom, nom FROM users WHERE role = 'professeur'";
$coordinateurs_result = $conn->query($coordinateurs_query);

// Récupérer tous les départements
$departements_query = "SELECT id, nom FROM departements";
$departements_result = $conn->query($departements_query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une filière</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f39c12;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
            --dark-gray: #333;
            --white: #ffffff;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark-gray);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 700px;
            background-color: var(--white);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin: 20px;
        }

        h2 {
            text-align: center;
            font-size: 28px;
            margin-bottom: 25px;
            color: var(--primary-dark);
            position: relative;
            padding-bottom: 10px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--dark-gray);
            font-weight: 500;
        }

        input[type="text"],
        select {
            padding: 14px;
            font-size: 16px;
            border: 1px solid var(--medium-gray);
            border-radius: 8px;
            transition: all 0.3s ease;
            background-color: var(--light-gray);
        }

        input[type="text"]:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            background-color: var(--white);
        }

        button {
            padding: 14px;
            font-size: 16px;
            color: var(--white);
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            letter-spacing: 0.5px;
            margin-top: 10px;
        }

        button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .message {
            text-align: center;
            margin: 20px 0;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .message.success {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .message.error {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .message.warning {
            background-color: rgba(243, 156, 18, 0.2);
            color: var(--warning-color);
            border: 1px solid var(--warning-color);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            margin-top: 25px;
            font-size: 16px;
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
            gap: 5px;
        }

        .back-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            color: var(--dark-gray);
            pointer-events: none;
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 100%;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 15px;
            }
            
            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Ajouter une filière</h2>
        <form method="POST">
            <div class="form-group">
                <label for="nom">Nom de la filière :</label>
                <input type="text" name="nom" id="nom" required placeholder="Entrez le nom de la filière">
            </div>

            <div class="form-group">
                <label for="departement">Département :</label>
                <div class="select-wrapper">
                    <select name="departement_id" id="departement" required>
                        <option value="">-- Choisir un département --</option>
                        <?php while ($departement = $departements_result->fetch_assoc()): ?>
                            <option value="<?php echo $departement['id']; ?>">
                                <?php echo htmlspecialchars($departement['nom']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="coordinateur">Coordinateur :</label>
                <div class="select-wrapper">
                    <select name="coordinateur_id" id="coordinateur" required>
                        <option value="">-- Choisir un coordinateur --</option>
                        <?php while ($coordinateur = $coordinateurs_result->fetch_assoc()): ?>
                            <option value="<?php echo $coordinateur['id']; ?>">
                                <?php echo htmlspecialchars($coordinateur['prenom']) . " " . htmlspecialchars($coordinateur['nom']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <button type="submit">
                <i class="fas fa-plus-circle"></i> Ajouter la filière
            </button>
        </form>

        <?php if ($message): ?>
            <div class="message <?php 
                if (strpos($message, '✅') !== false) echo 'success';
                elseif (strpos($message, '❌') !== false) echo 'error';
                else echo 'warning';
            ?>">
                <?php 
                    $icon = strpos($message, '✅') !== false ? 'fa-check-circle' : 
                           (strpos($message, '❌') !== false ? 'fa-times-circle' : 'fa-exclamation-circle');
                    echo '<i class="fas ' . $icon . '"></i> ' . $message; 
                ?>
            </div>
        <?php endif; ?>

        <a href="admin_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

</body>
</html>