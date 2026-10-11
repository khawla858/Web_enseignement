<?php
session_start();
require_once __DIR__ . '/config.php';

// Vérification connexion DB
if (!isset($conn) || $conn->connect_error) {
    die("Erreur de connexion à la base de données : " . $conn->connect_error);
}

// Vérification session et rôle
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'chef_departement') {
    header('Location: login.php');
    exit;
}

// Récupération département
if (!isset($_SESSION['departement_id'])) {
    $stmt = $conn->prepare("SELECT departement_id FROM professeurs_departements WHERE professeur_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['departement_id'] = $result->fetch_assoc()['departement_id'];
    } else {
        die("Aucun département associé à cet utilisateur");
    }
}

$departement_id = $_SESSION['departement_id'];
$annee_univ = date('Y').'-'.(date('Y')+1);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['valider'])) {
        // Validation d'un souhait
        $souhait_id = (int)$_POST['souhait_id'];
        $user_id = (int)$_POST['user_id'];

        $conn->begin_transaction();
        try {
            // Récupération infos souhait
            $stmt = $conn->prepare("SELECT ue_id, type_enseignement FROM souhaits WHERE id = ?");
            $stmt->bind_param("i", $souhait_id);
            $stmt->execute();
            $souhait = $stmt->get_result()->fetch_assoc();

            if (!$souhait) {
                throw new Exception("Souhait introuvable");
            }

            // Vérification existence affectation
            $stmt = $conn->prepare("SELECT id FROM affectations WHERE user_id = ? AND ue_id = ? AND type_enseignement = ? AND annee_univ = ?");
            $stmt->bind_param("iiss", $user_id, $souhait['ue_id'], $souhait['type_enseignement'], $annee_univ);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows == 0) {
                // Ajout affectation
                $stmt = $conn->prepare("INSERT INTO affectations (user_id, ue_id, type_enseignement, annee_univ, valide) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param("iiss", $user_id, $souhait['ue_id'], $souhait['type_enseignement'], $annee_univ);
                $stmt->execute();
            }

            // Suppression souhait
            $stmt = $conn->prepare("DELETE FROM souhaits WHERE id = ?");
            $stmt->bind_param("i", $souhait_id);
            $stmt->execute();

            $conn->commit();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            die("Erreur lors de la validation : " . $e->getMessage());
        }

    } elseif (isset($_POST['rejeter'])) {
        // Rejet d'un souhait
        $souhait_id = (int)$_POST['souhait_id'];
        $motif = trim($_POST['motif']);

        if (empty($motif)) {
            die("Le motif de rejet ne peut pas être vide");
        }

        $conn->begin_transaction();
        try {
            // 1. Récupérer les infos nécessaires avant suppression
            $stmt = $conn->prepare("SELECT s.user_id, ue.nom_ue, s.type_enseignement 
                                  FROM souhaits s
                                  JOIN unites_enseignement ue ON s.ue_id = ue.id
                                  WHERE s.id = ?");
            $stmt->bind_param("i", $souhait_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Souhait introuvable");
            }
            
            $data = $result->fetch_assoc();
            $prof_id = $data['user_id'];
            $nom_ue = $data['nom_ue'];
            $type_enseignement = $data['type_enseignement'];

            // 2. Enregistrer le rejet
            $stmt = $conn->prepare("INSERT INTO rejets (souhait_id, motif) VALUES (?, ?)");
            $stmt->bind_param("is", $souhait_id, $motif);
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'enregistrement du rejet");
            }

            // 3. Créer la notification
            $title = "Souhait rejeté";
            $message = "Votre demande pour l'UE $nom_ue ($type_enseignement) a été rejetée. Motif: $motif";
            
            $stmt = $conn->prepare("INSERT INTO notifications 
                                  (user_id, title, message, type, is_read, related_entity_type, related_entity_id) 
                                  VALUES (?, ?, ?, 'warning', FALSE, 'souhait', ?)");
            $stmt->bind_param("issi", $prof_id, $title, $message, $souhait_id);
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de la création de la notification");
            }

            // 4. Supprimer le souhait (avec désactivation temporaire des contraintes)
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            $stmt = $conn->prepare("DELETE FROM souhaits WHERE id = ?");
            $stmt->bind_param("i", $souhait_id);
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de la suppression du souhait");
            }
            $conn->query("SET FOREIGN_KEY_CHECKS=1");

            $conn->commit();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $conn->query("SET FOREIGN_KEY_CHECKS=1"); // Réactiver les checks en cas d'erreur
            die("Erreur lors du rejet : " . $e->getMessage());
        }
    }
}

// Suite du code HTML...
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des Souhaits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-light: #4895ef;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #fca311;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --bg-light: #f8f9fa;
            --bg-white: #ffffff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', sans-serif;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 15px;
        }

        .dashboard-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: var(--bg-white);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
            font-size: 1.25rem;
        }

        .badge-cm { background-color: var(--primary-color); }
        .badge-td { background-color: var(--success-color); }
        .badge-tp { background-color: var(--danger-color); }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table th {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            font-weight: 600;
        }

        .empty-state {
            padding: 3rem;
            text-align: center;
            color: var(--text-secondary);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .actions-cell {
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-tasks me-2"></i>Validation des Souhaits</h1>
                    <p class="mb-0">Département: <?= htmlspecialchars($_SESSION['departement_nom'] ?? 'Informatique') ?></p>
                </div>
                <a href="chef_departement.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-clipboard-list me-2"></i>Souhaits en attente</h2>
                <?php
                $stmt = $conn->prepare("SELECT COUNT(*) FROM souhaits s
                                      JOIN unites_enseignement ue ON s.ue_id = ue.id
                                      WHERE ue.specialite = (SELECT nom FROM departements WHERE id = ?)");
                $stmt->bind_param('i', $departement_id);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_row()[0];
                ?>
                <span class="badge bg-primary"><?= $count ?> demandes</span>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Enseignant</th>
                                <th>UE</th>
                                <th>Type</th>
                                <th>Volume Horaire</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare("SELECT s.id AS souhait_id, s.user_id, s.type_enseignement, 
                                                   u.nom, u.prenom, ue.nom_ue, ue.volume_cm, ue.volume_td, ue.volume_tp
                                            FROM souhaits s
                                            JOIN users u ON s.user_id = u.id
                                            JOIN unites_enseignement ue ON s.ue_id = ue.id
                                            WHERE ue.specialite = (SELECT nom FROM departements WHERE id = ?)");
                            $stmt->bind_param('i', $departement_id);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-check-circle fa-2x mb-3" style="color: #4cc9f0;"></i>
                                        <p class="mb-0">Aucun souhait en attente de validation</p>
                                    </td>
                                </tr>
                            <?php else:
                                while ($row = $result->fetch_assoc()): 
                                    $volume = match($row['type_enseignement']) {
                                        'CM' => $row['volume_cm'],
                                        'TD' => $row['volume_td'],
                                        'TP' => $row['volume_tp'],
                                        default => 0
                                    };
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['prenom'] . ' ' . htmlspecialchars($row['nom']) )?></td>
                                    <td><?= htmlspecialchars($row['nom_ue']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($row['type_enseignement']) ?>">
                                            <?= htmlspecialchars($row['type_enseignement']) ?>
                                        </span>
                                    </td>
                                    <td><?= $volume ?>h</td>
                                    <td class="actions-cell">
                                        <form method="post" class="d-inline" onsubmit="return confirm('Valider cette affectation ?')">
                                            <input type="hidden" name="souhait_id" value="<?= $row['souhait_id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                            <button type="submit" name="valider" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Valider
                                            </button>
                                        </form>

                                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejetModal<?= $row['souhait_id'] ?>">
                                            <i class="fas fa-times"></i> Rejeter
                                        </button>

                                        <!-- Modal Rejet -->
                                        <div class="modal fade" id="rejetModal<?= $row['souhait_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">Motif du rejet</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Rejet du souhait :</p>
                                                            <ul class="mb-3">
                                                                <li>UE: <strong><?= htmlspecialchars($row['nom_ue']) ?></strong></li>
                                                                <li>Type: <strong><?= htmlspecialchars($row['type_enseignement']) ?></strong></li>
                                                                <li>Enseignant: <strong><?= htmlspecialchars($row['prenom'].' '.$row['nom']) ?></strong></li>
                                                            </ul>
                                                            <div class="mb-3">
                                                                <label for="motif" class="form-label">Motif :</label>
                                                                <textarea name="motif" class="form-control" rows="3" required></textarea>
                                                            </div>
                                                            <input type="hidden" name="souhait_id" value="<?= $row['souhait_id'] ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" name="rejeter" class="btn btn-danger">Confirmer le rejet</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confirmation avant suppression
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm(this.getAttribute('data-confirm') || 'Confirmer cette action ?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>