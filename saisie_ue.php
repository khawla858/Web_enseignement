<?php
session_start();
require 'config.php'; // Connexion à la base

// Vérification que l'utilisateur est connecté et est coordonnateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordonnateur') {
    header("Location: login.php");
    exit();
}

// Traitement du formulaire d'ajout d'UE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajouter_ue'])) {
    $code_ue = trim($_POST['code_ue']);
    $nom_ue = trim($_POST['nom_ue']);
    $volume_cm = intval($_POST['volume_cm']);
    $volume_td = intval($_POST['volume_td']);
    $volume_tp = intval($_POST['volume_tp']);
    $specialite = trim($_POST['specialite']);
    $filiere = trim($_POST['filiere']);
    $responsable_id = intval($_POST['responsable_id']);
    $semestre = trim($_POST['semestre']); // Garder S1 ou S2 tel quel

    // Préparation et exécution de la requête d'insertion
    $stmt = $conn->prepare("INSERT INTO unites_enseignement (code_ue, nom_ue, volume_cm, volume_td, volume_tp, specialite, filiere, responsable_id, semestre) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiissis", $code_ue, $nom_ue, $volume_cm, $volume_td, $volume_tp, $specialite, $filiere, $responsable_id, $semestre);
    $stmt->execute();
    $stmt->close();

    $message = "Unité d'enseignement ajoutée avec succès.";
}

// Charger la liste des enseignants
$enseignants = $conn->query("SELECT id, nom, prenom FROM users WHERE role IN ('professeur', 'vacataire')");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Insérer une UE - Uniassign</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: rgb(52, 119, 235); }
        .container { max-width: 800px; margin: 60px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 25px; color: #2c3e50; }
        form label { font-weight: bold; display: block; margin-top: 15px; }
        input, select { width: 100%; padding: 10px; margin-top: 5px; border-radius: 6px; border: 1px solid #ccc; }
        input[type="submit"] { background-color: #3498db; color: white; font-weight: bold; cursor: pointer; transition: 0.3s ease; }
        input[type="submit"]:hover { background-color: #2c80b4; }
        .message { text-align: center; color: green; font-weight: bold; margin-top: 20px; }
        .back-btn {
            display: inline-block;
            background:rgb(101, 197, 98);
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: background 0.3s ease;
        }
        .back-btn:hover { background: #95a5a6; }
    </style>
</head>
<body>

<div class="container">
    <a href="coordonnateur.php" class="back-btn"><i class="fas fa-arrow-left"></i> Retour au tableau de bord</a>
    <h2>Ajouter une Unité d'Enseignement</h2>

    <?php if (!empty($message)) echo "<div class='message'>$message</div>"; ?>

    <form method="post" action="">
        <label>Code UE :</label>
        <input type="text" name="code_ue" required>

        <label>Nom de l'UE :</label>
        <input type="text" name="nom_ue" required>

        <label>Volume horaire CM :</label>
        <input type="number" name="volume_cm" required>

        <label>Volume horaire TD :</label>
        <input type="number" name="volume_td" required>

        <label>Volume horaire TP :</label>
        <input type="number" name="volume_tp" required>

        <label>Spécialité :</label>
        <input type="text" name="specialite" required>

        <label>Filière :</label>
        <input type="text" name="filiere" required>

        <label>Responsable du Module :</label>
        <select name="responsable_id" required>
            <option value="" disabled selected>Choisir un enseignant</option>
            <?php while($ens = $enseignants->fetch_assoc()): ?>
                <option value="<?= $ens['id']; ?>"><?= htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']); ?></option>
            <?php endwhile; ?>
        </select>

        <label>Semestre :</label>
        <select name="semestre" required>
            <option value="S1">Semestre 1</option>
            <option value="S2">Semestre 2</option>
        </select>

        <input type="submit" name="ajouter_ue" value="Ajouter l'UE">
    </form>
</div>

</body>
</html>
