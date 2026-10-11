<?php
session_start();
require 'config.php';

// Vérification connexion
if (!isset($_SESSION['user_id'])) {
    echo "Vous devez être connecté.";
    exit;
}

$enseignant_id = $_SESSION['user_id'];

// Infos du professeur
$stmtUser = $pdo->prepare("SELECT nom, prenom FROM users WHERE id = ?");
$stmtUser->execute([$enseignant_id]);
$user = $stmtUser->fetch();

// Récupérer les affectations du professeur
$stmtAffectations = $pdo->prepare("
    SELECT a.annee_univ, u.nom_ue, a.type_enseignement,
        CASE 
            WHEN a.type_enseignement = 'CM' THEN u.volume_cm
            WHEN a.type_enseignement = 'TD' THEN u.volume_td
            WHEN a.type_enseignement = 'TP' THEN u.volume_tp
            ELSE 0
        END AS volume_horaire
    FROM affectations a
    JOIN unites_enseignement u ON a.ue_id = u.id
    WHERE a.user_id = ?
    ORDER BY a.annee_univ DESC, u.nom_ue ASC
");
$stmtAffectations->execute([$enseignant_id]);
$affectations = $stmtAffectations->fetchAll();





// Définir l'année universitaire (simplement)
$annee_univ = "2025-2026"; 

// Récupérer dynamiquement le département du professeur
$stmtDept = $pdo->prepare("
    SELECT d.nom 
    FROM departements d
    JOIN professeurs_departements pd ON d.id = pd.departement_id
    WHERE pd.professeur_id = ?
");
$stmtDept->execute([$_SESSION['user_id']]);
$departement_nom = $stmtDept->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ma Charge Horaire</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f0f4f8;
        }
        .top-bar {
            background-color: #3498db;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0px 4px 6px rgba(0,0,0,0.1);
        }
        .left-part {
            display: flex;
            flex-direction: column;
        }
        .left-part h1 {
            margin: 0;
            font-size: 28px;
        }
        .left-part p {
            margin: 5px 0 0 0;
            font-size: 18px;
        }
        .accueil-btn {
            background-color: white;
            color: #3498db;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .accueil-btn:hover {
            background-color: #2980b9;
            color: white;
        }
        .content {
            margin: 30px auto;
            width: 90%;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #ccc;
        }
        th {
            background-color: #2980b9;
            color: white;
            font-size: 18px;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .no-data {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 18px;
        }

        
        /* Header Prof Style */
.header-prof {
    background-color: #3a86ff;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    color: #ffffff;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-text {
    text-align: left;
}

.header-text h2 {
    margin-bottom: 10px;
    font-size: 28px;
    font-weight: bold;
}

.header-text p {
    margin-top: 5px;
    font-size: 16px;
    color: #dfe6e9; /* gris très clair pour contraste */
}

/* Bouton retour */
.btn-retour {
    background-color: white;
    color: #3a86ff;
    padding: 10px 20px;
    font-weight: bold;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.3s, color 0.3s;
}

.btn-retour:hover {
    background-color: #2563eb;
    color: white;
}

    </style>
</head>
<body>

<div class="header-prof">
     <div class="header-content">
     <p>Professeur : <strong><?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?></strong></p>
 
         <div class="header-text">
             <h2>la charge horaire </h2>
             <p>Année universitaire <?= htmlspecialchars($annee_univ) ?> | Département <?= htmlspecialchars($departement_nom) ?></p>
         </div>
 
         <a href="<?php 
    if ($_SESSION['role'] === 'vacataire') {
        echo 'vacataire.php';
    
    } elseif ($_SESSION['role'] === 'professeur') {
        echo 'professeur.php';
    } 
    
     elseif ($_SESSION['role'] === 'chef_departement') {
        echo 'chef_departement.php';
    }  elseif ($_SESSION['role'] === 'coordonnateur') {
        echo 'coordonnateur.php';
    } 
    
    else {
        echo 'login.php';
    }
?>" class="btn-retour">Retour</a>
     </div>
 </div>
        

<div class="content">
    <?php if ($affectations): ?>
        <table>
            <thead>
                <tr>
                    <th>Année universitaire</th>
                    <th>Unité d'enseignement</th>
                    <th>Type d'enseignement</th>
                    <th>Charge horaire</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($affectations as $aff): ?>
                    <tr>
                        <td><?= htmlspecialchars($aff['annee_univ']) ?></td>
                        <td><?= htmlspecialchars($aff['nom_ue']) ?></td>
                        <td><?= htmlspecialchars($aff['type_enseignement']) ?></td>
                        <td><?= htmlspecialchars($aff['volume_horaire']) ?> h</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            Aucune affectation trouvée pour vous.
        </div>
    <?php endif; ?>
</div>

</body>
</html>