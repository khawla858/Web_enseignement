<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("UE ID missing.");
}

$ue_id = intval($_GET['id']);

// Fetch UE details
$stmt = $conn->prepare("SELECT * FROM unites_enseignement WHERE id = ?");
$stmt->bind_param("i", $ue_id);
$stmt->execute();
$result = $stmt->get_result();
$ue = $result->fetch_assoc();

if (!$ue) {
    die("UE not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_ue = $_POST['code_ue'];
    $nom_ue = $_POST['nom_ue'];
    $volume_cm = intval($_POST['volume_cm']);
    $volume_td = intval($_POST['volume_td']);
    $volume_tp = intval($_POST['volume_tp']);

    $stmt = $conn->prepare("UPDATE unites_enseignement 
                            SET code_ue = ?, nom_ue = ?, volume_cm = ?, volume_td = ?, volume_tp = ? 
                            WHERE id = ?");
    $stmt->bind_param("ssiiii", $code_ue, $nom_ue, $volume_cm, $volume_td, $volume_tp, $ue_id);

    if ($stmt->execute()) {
        header("Location: gestion_ue.php?success=1");
        exit();
    } else {
        $error = "Update failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit UE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color:rgb(93, 161, 229);
        }
        .card {
            max-width: 600px;
            margin: 60px auto;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="card p-4">
        <h2 class="mb-4 text-center">Edit Teaching Unit</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Code UE</label>
                <input type="text" name="code_ue" class="form-control" value="<?= htmlspecialchars($ue['code_ue']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="nom_ue" class="form-control" value="<?= htmlspecialchars($ue['nom_ue']) ?>" required>
            </div>
            <div class="row">
                <div class="mb-3 col-md-4">
                    <label class="form-label">Volume CM</label>
                    <input type="number" name="volume_cm" class="form-control" value="<?= $ue['volume_cm'] ?>" required>
                </div>
                <div class="mb-3 col-md-4">
                    <label class="form-label">Volume TD</label>
                    <input type="number" name="volume_td" class="form-control" value="<?= $ue['volume_td'] ?>" required>
                </div>
                <div class="mb-3 col-md-4">
                    <label class="form-label">Volume TP</label>
                    <input type="number" name="volume_tp" class="form-control" value="<?= $ue['volume_tp'] ?>" required>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</body>
</html>
