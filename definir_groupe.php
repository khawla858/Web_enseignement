<?php
session_start();
require_once 'config.php';


// Vérifie que l'utilisateur est coordinateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordonnateur') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupération de la filière du coordinateur
$sql = "SELECT f.id as filiere_id, f.nom 
        FROM coordinateurs_filieres cf
        JOIN filieres f ON f.id = cf.filiere_id
        WHERE cf.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$filiere = $stmt->get_result()->fetch_assoc();

$filiere_id = $filiere['filiere_id'] ?? null;

// Récupère les filtres
$semestre = $_GET['semestre'] ?? 'S1';
$annee_univ = $_GET['annee'] ?? date("Y") . "-" . (date("Y") + 1);

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $annee = $_POST['annee'];
    $semestre = $_POST['semestre'];
    $groupes = $_POST['groupes'] ?? [];

    $conn->begin_transaction();

    try {
        foreach ($groupes as $ue_id => $valeurs) {
            $td = intval($valeurs['td']);
            $tp = intval($valeurs['tp']);
            $cap = intval($valeurs['cap']);

            if ($td == 0 && $tp == 0) {
                $stmt = $conn->prepare("DELETE FROM groupes_td_tp WHERE ue_id=? AND annee_universitaire=? AND semestre=?");
                $stmt->bind_param("iss", $ue_id, $annee, $semestre);
                $stmt->execute();
                continue;
            }

            $stmt = $conn->prepare("INSERT INTO groupes_td_tp (ue_id, annee_universitaire, semestre, groupes_td, groupes_tp, capacite_max)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE groupes_td=?, groupes_tp=?, capacite_max=?");
            $stmt->bind_param("issiiiiii", $ue_id, $annee, $semestre, $td, $tp, $cap, $td, $tp, $cap);
            $stmt->execute();
        }

        $conn->commit();
        $success = "Données enregistrées avec succès.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// Récupération des UE de la filière
$ues = [];
if ($filiere_id) {
    $sql = "SELECT ue.id, ue.nom_ue, ue.code_ue,
                   g.groupes_td, g.groupes_tp, g.capacite_max
            FROM unites_enseignement ue
            LEFT JOIN groupes_td_tp g ON g.ue_id = ue.id AND g.annee_universitaire = ? AND g.semestre = ?
            WHERE ue.filiere = (SELECT nom FROM filieres WHERE id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $annee_univ, $semestre, $filiere_id);
    $stmt->execute();
    $ues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Définition des Groupes TD/TP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive { margin-top: 20px; }
        .badge-semestre { font-size: 1rem; }
        .form-control-sm { max-width: 80px; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2>Définition des Groupes TD/TP 
        <span class="badge bg-primary badge-semestre"><?= htmlspecialchars($semestre) ?> <?= htmlspecialchars($annee_univ) ?></span>
    </h2>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <select name="semestre" class="form-select">
                <option value="S1" <?= $semestre === 'S1' ? 'selected' : '' ?>>Semestre 1</option>
                <option value="S2" <?= $semestre === 'S2' ? 'selected' : '' ?>>Semestre 2</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="annee" class="form-control" 
                   value="<?= htmlspecialchars($annee_univ) ?>" placeholder="2023-2024">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">Filtrer</button>
        </div>
    </form>

    <!-- Formulaire principal -->
    <form method="post">
        <input type="hidden" name="semestre" value="<?= htmlspecialchars($semestre) ?>">
        <input type="hidden" name="annee" value="<?= htmlspecialchars($annee_univ) ?>">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Code UE</th>
                        <th>Nom UE</th>
                        <th class="text-center">Groupes TD</th>
                        <th class="text-center">Groupes TP</th>
                        <th class="text-center">Capacité Max/Groupe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ues as $ue): ?>
                    <tr>
                        <td><?= htmlspecialchars($ue['code_ue']) ?></td>
                        <td><?= htmlspecialchars($ue['nom_ue']) ?></td>
                        <td class="text-center">
                            <input type="number" name="groupes[<?= $ue['id'] ?>][td]" 
                                   value="<?= $ue['groupes_td'] ?? 0 ?>" 
                                   min="0" class="form-control form-control-sm mx-auto">
                        </td>
                        <td class="text-center">
                            <input type="number" name="groupes[<?= $ue['id'] ?>][tp]" 
                                   value="<?= $ue['groupes_tp'] ?? 0 ?>" 
                                   min="0" class="form-control form-control-sm mx-auto">
                        </td>
                        <td class="text-center">
                            <input type="number" name="groupes[<?= $ue['id'] ?>][cap]" 
                                   value="<?= $ue['capacite_max'] ?? 0 ?>" 
                                   min="0" class="form-control form-control-sm mx-auto">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">Enregistrer la configuration</button>
            <a href="utils/export_data.php?type=groupes&semestre=<?= urlencode($semestre) ?>&annee=<?= urlencode($annee_univ) ?>" 
   class="btn btn-secondary">
   Exporter les groupes
</a>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>