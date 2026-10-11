<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Départements et Filières</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .navbar {
            background-color: var(--secondary-color) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            margin-bottom: 25px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        
        .table-responsive {
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }
        
        table {
            width: 100%;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .badge-department {
            background-color: #e74c3c;
            color: white;
        }
        
        .badge-program {
            background-color: #2ecc71;
            color: white;
        }
        
        .user-info {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .section-title {
            color: var(--secondary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 8px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-university me-2"></i>Gestion Universitaire
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin.php"><i class="fas fa-home me-1"></i> Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profil.php"><i class="fas fa-user me-1"></i> Profil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ind.php"><i class="fas fa-sign-out-alt me-1"></i> Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="user-info mb-4">
            <div class="row">
                <div class="col-md-6">
                    <h4><i class="fas fa-user-circle me-2"></i>Informations de l'utilisateur</h4>
                    <p class="mb-1"><strong>ID :</strong> <?php echo htmlspecialchars($user_id); ?></p>
                    <p class="mb-1"><strong>Connecté en tant que :</strong> Administrateur</p>
                </div>
                <div class="col-md-6 text-md-end">
    <div class="action-buttons">
        <a href="ajouter_departement.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Ajouter Département
        </a>
        <a href="ajouter_filiere.php" class="btn btn-success btn-sm">
            <i class="fas fa-plus me-1"></i> Ajouter Filière
        </a>
    </div>
</div>
            </div>
        </div>

        <?php
        // Récupération des départements
        $queryDepartements = "SELECT * FROM departements";
        $resultDepartements = $conn->query($queryDepartements);
        
        // Récupération des filières avec jointure sur les départements
        $queryFilieres = "SELECT f.*, d.nom as departement_nom 
                         FROM filieres f
                         LEFT JOIN departements d ON f.departement_id = d.id
                         ORDER BY d.nom, f.nom";
        $resultFilieres = $conn->query($queryFilieres);
        ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-building me-2"></i>Départements</span>
                        <span class="badge bg-light text-dark"><?php echo $resultDepartements->num_rows; ?> départements</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($departement = $resultDepartements->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($departement['id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($departement['nom']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($departement['description'] ?? 'Aucune description'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-graduation-cap me-2"></i>Filières</span>
                        <span class="badge bg-light text-dark"><?php echo $resultFilieres->num_rows; ?> filières</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Département</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($filiere = $resultFilieres->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($filiere['id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($filiere['nom']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($filiere['departement_nom'] ?? 'Non attribué'); ?></td>
                                    <td><?php echo htmlspecialchars($filiere['description'] ?? 'Aucune description'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">© 2023 Système de Gestion Universitaire. Tous droits réservés.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script pour activer les tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>