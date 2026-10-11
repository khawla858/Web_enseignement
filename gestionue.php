<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordonnateur') {
    header("Location: login.php");
    exit();
}

// Supprimer une UE
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM unites_enseignement WHERE id = $id");
    header("Location: gestionue.php");
    exit();
}

// Modifier une UE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_ue'])) {
    $id = intval($_POST['ue_id']);
    $code_ue = trim($_POST['code_ue']);
    $nom_ue = trim($_POST['nom_ue']);
    $volume_cm = intval($_POST['volume_cm']);
    $volume_td = intval($_POST['volume_td']);
    $volume_tp = intval($_POST['volume_tp']);
    $specialite = trim($_POST['specialite']);
    $filiere = trim($_POST['filiere']);
    $semestre = intval($_POST['semestre']);

    $stmt = $conn->prepare("UPDATE unites_enseignement SET code_ue=?, nom_ue=?, volume_cm=?, volume_td=?, volume_tp=?, specialite=?, filiere=?, semestre=? WHERE id=?");
    $stmt->bind_param("ssiiissii", $code_ue, $nom_ue, $volume_cm, $volume_td, $volume_tp, $specialite, $filiere, $semestre, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: gestionue.php");
    exit();
}

// Récupérer toutes les UE
$ues = $conn->query("SELECT * FROM unites_enseignement ORDER BY semestre ASC");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Unités d'Enseignement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            margin: 0;
            padding: 40px;
        }
        .container {
            max-width: 1300px;
            margin: auto;
            background: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0px 12px 25px rgba(0,0,0,0.1);
        }
        h1, h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        th, td {
            background: #ffffff;
            padding: 14px 18px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #3498db;
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        tr:hover td {
            background-color: #f6f6f6;
        }
        .btn {
            padding: 10px 20px;
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 30px;
            font-size: 14px;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        form {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
        }
        input, select {
            flex: 1 1 45%;
            padding: 12px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 12px;
            outline: none;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            border-color: #3498db;
        }
        .submit-btn {
            width: 100%;
            background: #2ecc71;
            color: white;
            border: none;
            padding: 15px;
            font-size: 16px;
            border-radius: 30px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .submit-btn:hover {
            background: #27ae60;
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Gestion des Unités d'Enseignement</h1>

    <h2>Liste des UE</h2>

    <table>
        <tr>
            <th>Code</th>
            <th>Nom</th>
            <th>CM</th>
            <th>TD</th>
            <th>TP</th>
            <th>Spécialité</th>
            <th>Filière</th>
            <th>Semestre</th>
            <th>Actions</th>
        </tr>
        <?php while($ue = $ues->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($ue['code_ue']) ?></td>
            <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
            <td><?= $ue['volume_cm'] ?></td>
            <td><?= $ue['volume_td'] ?></td>
            <td><?= $ue['volume_tp'] ?></td>
            <td><?= htmlspecialchars($ue['specialite']) ?></td>
            <td><?= htmlspecialchars($ue['filiere']) ?></td>
            <td><?= $ue['semestre'] ?></td>
            <td class="action-buttons">
                <a class="btn" href="?edit=<?= $ue['id'] ?>"><i class="fas fa-edit"></i> Modifier</a>
                <a class="btn btn-danger" href="?delete=<?= $ue['id'] ?>" onclick="return confirm('Confirmer la suppression ?')"><i class="fas fa-trash"></i> Supprimer</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <?php if (isset($_GET['edit'])): 
        $id = intval($_GET['edit']);
        $ue_edit = $conn->query("SELECT * FROM unites_enseignement WHERE id = $id")->fetch_assoc();
    ?>
    <h2>Modifier l'UE</h2>

    <form method="post" action="">
        <input type="hidden" name="ue_id" value="<?= $ue_edit['id'] ?>">

        <input type="text" name="code_ue" value="<?= htmlspecialchars($ue_edit['code_ue']) ?>" required placeholder="Code UE">
        <input type="text" name="nom_ue" value="<?= htmlspecialchars($ue_edit['nom_ue']) ?>" required placeholder="Nom UE">
        <input type="number" name="volume_cm" value="<?= $ue_edit['volume_cm'] ?>" required placeholder="Volume CM">
        <input type="number" name="volume_td" value="<?= $ue_edit['volume_td'] ?>" required placeholder="Volume TD">
        <input type="number" name="volume_tp" value="<?= $ue_edit['volume_tp'] ?>" required placeholder="Volume TP">
        <input type="text" name="specialite" value="<?= htmlspecialchars($ue_edit['specialite']) ?>" required placeholder="Spécialité">
        <input type="text" name="filiere" value="<?= htmlspecialchars($ue_edit['filiere']) ?>" required placeholder="Filière">
        <select name="semestre" required>
            <option value="1" <?= $ue_edit['semestre'] == 1 ? 'selected' : '' ?>>Semestre 1</option>
            <option value="2" <?= $ue_edit['semestre'] == 2 ? 'selected' : '' ?>>Semestre 2</option>
        </select>

        <input class="submit-btn" type="submit" name="modifier_ue" value="Modifier l'UE">
    </form>
    <?php endif; ?>

</div>

</body>
</html>
