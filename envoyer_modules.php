<?php
session_start();
require 'config.php';

// Vérification utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordonnateur') {
    header("Location: login.php");
    exit();
}

// Envoyer un module à un enseignant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_module'])) {
    $module_id = intval($_POST['module_id']);
    $enseignant_id = intval($_POST['enseignant_id']);

    // Mise à jour du responsable_id
    $stmt = $conn->prepare("UPDATE unites_enseignement SET responsable_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $enseignant_id, $module_id);
    $stmt->execute();
    $stmt->close();

    $message = "Module affecté à l'enseignant avec succès.";
}

// Récupérer tous les modules
$modules = $conn->query("SELECT * FROM unites_enseignement ORDER BY semestre ASC");

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Envoyer Modules - Uniassign</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f2f4f8;
            margin: 0;
            padding: 30px;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .message {
            background-color: #2ecc71;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #3498db;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        td.semestre {
            background-color: #f9f9f9;
            font-weight: bold;
            color: #2c3e50;
        }
        form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            align-items: center;
            margin-top: 15px;
        }
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 10px;
            min-width: 180px;
            text-align: center;
        }
        .btn {
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Affecter les Modules aux Enseignants</h1>

    <?php if (!empty($message)) echo "<div class='message'>$message</div>"; ?>

    <table>
        <tr>
            <th>Code UE</th>
            <th>Nom UE</th>
            <th>Semestre</th>
            <th>Responsable actuel</th>
            <th>Affecter à</th>
            <th>Action</th>
        </tr>

        <?php while($module = $modules->fetch_assoc()): ?>
        <tr>
            <form method="post" action="">
                <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                <td><?= htmlspecialchars($module['code_ue']) ?></td>
                <td><?= htmlspecialchars($module['nom_ue']) ?></td>
                <td class="semestre"><?= htmlspecialchars($module['semestre']) ?></td>
                <td>
                    <?php
                    if (!empty($module['responsable_id'])) {
                        $res = $conn->query("SELECT prenom, nom FROM users WHERE id = " . intval($module['responsable_id']));
                        if ($res && $r = $res->fetch_assoc()) {
                            echo htmlspecialchars($r['prenom'] . ' ' . $r['nom']);
                        } else {
                            echo "Non défini";
                        }
                    } else {
                        echo "Non défini";
                    }
                    ?>
                </td>
                <td>
                    <select name="enseignant_id" required>
                        <option value="">-- Choisir Enseignant --</option>
                        <?php
                        // Recharge la liste des enseignants pour chaque module
                        $enseignants = $conn->query("SELECT id, prenom, nom FROM users WHERE role IN ('professeur', 'vacataire')");
                        while($ens = $enseignants->fetch_assoc()):
                        ?>
                        <option value="<?= $ens['id'] ?>"><?= htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </td>
                <td><button class="btn" type="submit" name="envoyer_module">Envoyer</button></td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
