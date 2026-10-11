<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est un chef de département
if ($_SESSION['role'] !== 'chef_departement') {
    header("Location: login.php");
    exit();
}

$chef_id = $_SESSION['user_id'];

// 1. Vérifier que le chef est bien assigné à un département
$stmt_check = $conn->prepare("SELECT departement_id FROM chefs_departement WHERE user_id = ?");
$stmt_check->bind_param("i", $chef_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    die("<div class='alert alert-danger'>Vous n'êtes pas associé à un département.</div>");
}

$departement = $result_check->fetch_assoc();
$departement_id = $departement['departement_id'];

// 2. Requête principale pour les professeurs
$sql = "SELECT 
            u.id,
            u.nom,
            u.prenom,
            u.specialite,
            u.role,
            COUNT(DISTINCT a.ue_id) AS nb_ue_affectees,
            COALESCE(SUM(ue.volume_cm + ue.volume_td + ue.volume_tp), 0) AS charge_actuelle
        FROM users u
        JOIN professeurs_departements pd ON u.id = pd.professeur_id
        LEFT JOIN affectations a ON u.id = a.user_id AND a.valide = 1
        LEFT JOIN unites_enseignement ue ON a.ue_id = ue.id
        WHERE pd.departement_id = ?
        GROUP BY u.id
        ORDER BY charge_actuelle ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $departement_id);
$stmt->execute();
$result = $stmt->get_result();

$professeurs = [];
while ($row = $result->fetch_assoc()) {
    $professeurs[] = $row;
}

// 3. Récupération des UE vacantes pour le département
$sql_vacantes = "SELECT ue.* 
                FROM unites_enseignement ue
                LEFT JOIN affectations a ON ue.id = a.ue_id AND a.valide = 1
                WHERE a.ue_id IS NULL 
                AND ue.specialite = (SELECT nom FROM departements WHERE id = ?)";

$stmt_vacantes = $conn->prepare($sql_vacantes);
$stmt_vacantes->bind_param("i", $departement_id);
$stmt_vacantes->execute();
$ue_vacantes = $stmt_vacantes->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupération du nom du département
$stmt_nom = $conn->prepare("SELECT nom FROM departements WHERE id = ?");
$stmt_nom->bind_param("i", $departement_id);
$stmt_nom->execute();
$result_nom = $stmt_nom->get_result();
$departement_nom = $result_nom->fetch_assoc()['nom'];
$stmt_nom->close();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Enseignants - <?= htmlspecialchars($departement_nom) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            line-height: 1.6;
            background-color: #f5f5f5;
        }

        h1 {
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            background-color: #3a86ff;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px; /* Espace ajouté au-dessus du tableau */
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #3a86ff;
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .warning { background-color: #fff3f3; }
        .success { background-color: #f3fff3; }
        .charge-faible { background-color: #fff3f3; }
        .charge-critique { background-color: #fff8e1; }

        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            height: 42px;
            box-sizing: border-box;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-align: center;
            font-size: 1em;
            text-decoration: none;
            color: white !important;
            line-height: 1.2;
            border: 2px solid white;
        }

        button:hover {
            background-color: #2b6cda;
        }

        .statut {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .warning .statut {
            background-color: #ff4444;
            color: white;
        }

        .success .statut {
            background-color: #4CAF50;
            color: white;
        }

        a {
            color: #3a86ff;
            text-decoration: none;
        }

        .table-container {
            margin: 30px 0; /* Espace augmenté autour du tableau */
            border-radius: 8px;
            overflow-x: auto;
        }

        .button {
            display: inline-block;
            padding: 10px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-align: center;
            font-size: 1em;
            text-decoration: none;
            color: white !important;
            line-height: 1.5;
        }

        .btn-excel {
            background-color: rgb(83, 146, 35);
            margin-left: 380px;
            border: 1px solid white;
        }

        .btn-excel:hover {
            background-color: rgb(106, 181, 109);
        }

        .btn-retour {
            background-color: rgb(3, 79, 202);
            margin-right: 20px;
            border: 1px solid white;
        }

        .btn-retour:hover {
            background-color: rgb(48, 99, 181);
        }

        .btn-group {
            justify-content: space-between;
            align-items: center;
            background-color: #3a86ff;
            display: flex;
            gap: 15px;
            margin-bottom: 30px; /* Espace augmenté sous le header */
            border-radius: 15px;
            padding: 0 20px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-size: 0.9em;
        }

        .bg-primary {
            background-color: #3a86ff;
        }

        .bg-secondary {
            background-color: #6c757d;
        }

        .bg-success {
            background-color: #28a745;
        }

        .bg-warning {
            background-color: #ffc107;
        }

        .bg-danger {
            background-color: #dc3545;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px; /* Espace augmenté entre les cartes */
            overflow: hidden;
        }

        .card-header {
            padding: 15px 20px;
            background-color: #3a86ff;
            color: white;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .col-md-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 0 15px;
            margin-bottom: 30px; /* Espace augmenté entre les cartes d'UE */
        }

        .section-spacer {
            margin-bottom: 40px; /* Espace supplémentaire entre les sections */
        }

        @media (max-width: 768px) {
            .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="btn-group">
    <h1>Gestion des Enseignants - <?= htmlspecialchars($departement_nom) ?></h1>
    
    <div style="display: flex; gap: 10px;">
        <!-- Bouton Export -->
        <a href="utils/export_data.php" class="button btn-excel">
            📥 Exporter liste des enseignants
        </a>

        <!-- Bouton Accueil -->
        <a href="chef_departement.php" class="button btn-retour">  
            <i class="fas fa-sign-out-alt"></i> Accueil
        </a>
    </div>
</div>



    <!-- Espace ajouté après le header -->
    <div class="section-spacer"></div>

    <!-- Tableau des professeurs -->
    <div class="card">
        <div class="card-header">
            Liste des enseignants
        </div>
        <div class="card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Spécialité</th>
                            <th>Statut</th>
                            <th>Charge</th>
                            <th>UE</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($professeurs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">
                                    Aucun professeur dans ce département
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($professeurs as $prof): 
                                $charge = $prof['charge_actuelle'];
                                $charge_class = '';
                                if($charge < 150) $charge_class = 'charge-faible';
                                if($charge > 300) $charge_class = 'charge-critique';
                            ?>
                            <tr class="<?= $charge_class ?>">
                                <td><?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?></td>
                                <td><?= htmlspecialchars($prof['specialite']) ?></td>
                                <td>
                                    <span class="badge <?= $prof['role'] === 'vacataire' ? 'bg-secondary' : 'bg-primary' ?>">
                                        <?= $prof['role'] === 'vacataire' ? 'Vacataire' : 'Titulaire' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $charge < 150 ? 'bg-warning' : ($charge > 300 ? 'bg-danger' : 'bg-success') ?>">
                                        <?= $charge ?>h
                                    </span>
                                </td>
                                <td><?= $prof['nb_ue_affectees'] ?></td>
                                <td>
                                    <a href="affectations_manuelles.php?id=<?= $prof['id'] ?>" class="button" style="background-color: #28a745; padding: 5px 10px;">
                                        <i class="fas fa-plus"></i> Affecter
                                    </a>
                                    <a href="profil.php?id=<?= $prof['id'] ?>" class="button" style="background-color: #17a2b8; padding: 5px 10px;">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Espace ajouté entre les sections -->
    <div class="section-spacer"></div>

    
    <!-- Modal Ajout Professeur -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000;" id="addProfessorModal">
        <div style="background: white; padding: 20px; border-radius: 8px; width: 500px; max-width: 90%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Nouveau professeur</h3>
                <button onclick="document.getElementById('addProfessorModal').style.display='none'" style="background: none; border: none; font-size: 1.5em; cursor: pointer;">&times;</button>
            </div>
            <form action="ajouter_professeur.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Nom complet</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="prenom" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" placeholder="Prénom" required>
                        <input type="text" name="nom" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" placeholder="Nom" required>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Spécialité</label>
                    <select name="specialite" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" required>
                        <option value="<?= htmlspecialchars($departement_nom) ?>" selected>
                            <?= htmlspecialchars($departement_nom) ?>
                        </option>
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px;">Type de contrat</label>
                    <select name="role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" required>
                        <option value="professeur">Titulaire</option>
                        <option value="vacataire">Vacataire</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="button" style="background-color: #28a745;">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('addProfessorModal').style.display = 'flex';
        }
    </script>
</body>
</html>