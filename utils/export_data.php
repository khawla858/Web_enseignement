<?php
require_once __DIR__ . '/../config.php';
session_start();

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Seuls les coordinateurs et chefs de département peuvent exporter
if ($_SESSION['role'] !== 'coordonnateur' && $_SESSION['role'] !== 'chef_departement') {
    header('Location: ../unauthorized.php');
    exit();
}

// Récupération des paramètres de filtrage
$annee_univ = $_GET['annee_univ'] ?? '';
$enseignant_nom = $_GET['enseignant_nom'] ?? '';
$ue_code = $_GET['ue_code'] ?? '';

// Construction de la requête de base
$sql = "SELECT 
            ue.code_ue,
            ue.nom_ue,
            ue.semestre,
            ue.volume_cm,
            ue.volume_td,
            ue.volume_tp,
            ue.credits,
            CONCAT(u.nom, ' ', u.prenom) AS responsable,
            f.nom AS filiere
        FROM unites_enseignement ue
        LEFT JOIN users u ON ue.responsable_id = u.id
        JOIN filieres f ON ue.filiere = f.id";

// Ajout des conditions en fonction du rôle
if ($_SESSION['role'] === 'coordonnateur') {
    // Filtrer par la filière du coordinateur
    $sql .= " WHERE ue.filiere IN (
                SELECT filiere_id FROM coordinateurs_filieres 
                WHERE user_id = ?
            )";
    $params = [$_SESSION['user_id']];
    $types = "i";
} else {
    // Filtrer par le département du chef
    $sql .= " WHERE ue.filiere IN (
                SELECT id FROM filieres 
                WHERE departement_id = (
                    SELECT departement_id FROM chefs_departement 
                    WHERE user_id = ?
                )
            )";
    $params = [$_SESSION['user_id']];
    $types = "i";
}

// Ajout des filtres supplémentaires
if (!empty($annee_univ)) {
    $sql .= " AND ue.id IN (
                SELECT ue_id FROM affectations 
                WHERE annee_univ = ?
            )";
    $types .= "s";
    $params[] = $annee_univ;
}

if (!empty($enseignant_nom)) {
    $sql .= " AND ue.id IN (
                SELECT ue_id FROM affectations 
                WHERE user_id IN (
                    SELECT id FROM users 
                    WHERE (nom LIKE ? OR prenom LIKE ?)
                )
            )";
    $types .= "ss";
    $like_nom = '%' . $enseignant_nom . '%';
    $params[] = $like_nom;
    $params[] = $like_nom;
}

if (!empty($ue_code)) {
    $sql .= " AND ue.code_ue LIKE ?";
    $types .= "s";
    $params[] = '%' . $ue_code . '%';
}

// Exécution de la requête
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

// Configuration des headers pour l'export Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=export_ue_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Début du contenu Excel
echo "<table border='1'>";
echo "<tr>
        <th>Code UE</th>
        <th>Nom UE</th>
        <th>Semestre</th>
        <th>Volume CM</th>
        <th>Volume TD</th>
        <th>Volume TP</th>
        <th>Total Heures</th>
        <th>Crédits</th>
        <th>Responsable</th>
        <th>Filière</th>
      </tr>";

foreach ($data as $row) {
    $total_heures = $row['volume_cm'] + $row['volume_td'] + $row['volume_tp'];
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['code_ue']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nom_ue']) . "</td>";
    echo "<td>" . htmlspecialchars($row['semestre']) . "</td>";
    echo "<td>" . $row['volume_cm'] . "h</td>";
    echo "<td>" . $row['volume_td'] . "h</td>";
    echo "<td>" . $row['volume_tp'] . "h</td>";
    echo "<td>" . $total_heures . "h</td>";
    echo "<td>" . $row['credits'] . "</td>";
    echo "<td>" . htmlspecialchars($row['responsable'] ?? 'Non attribué') . "</td>";
    echo "<td>" . htmlspecialchars($row['filiere']) . "</td>";
    echo "</tr>";
}

echo "</table>";
exit();
?>