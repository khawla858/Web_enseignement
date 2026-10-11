<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($current_password, $user['password_hash'])) {
        $error = "Le mot de passe actuel est incorrect.";
    } 
    elseif ($new_password !== $confirm_password) {
        $error = "Les nouveaux mots de passe ne correspondent pas.";
    } 
    elseif (password_verify($new_password, $user['password_hash'])) {
        $error = "Le nouveau mot de passe doit être différent de l'actuel.";
    } 
    elseif (strlen($new_password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } 
    else {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_password_hash, $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $success = "Votre mot de passe a été changé avec succès!";
        } else {
            $error = "Une erreur est survenue lors de la mise à jour du mot de passe.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe | SecureApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .password-card {
            max-width: 500px;
            margin: 2rem auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .password-card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: var(--primary-color);
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .card-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 2rem;
            background-color: white;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .password-strength {
            height: 6px;
            margin-top: 10px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.4s ease, background-color 0.4s ease;
        }
        
        .password-requirements {
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .requirement i {
            margin-right: 8px;
            font-size: 0.7rem;
        }
        
        .requirement.met {
            color: var(--primary-color);
        }
        
        .requirement.met i {
            color: #28a745;
        }
        
        #passwordMatch {
            font-size: 0.85rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        .input-group {
            position: relative;
        }
        
        @media (max-width: 576px) {
            .password-card {
                margin: 1rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="password-card">
            <div class="card-header">
                <h3><i class="fas fa-key me-2"></i>Changer le mot de passe</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="post" id="passwordForm">
                    <div class="mb-4">
                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <span class="password-toggle" onclick="togglePassword('current_password')">
                                <i class="far fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <span class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="far fa-eye"></i>
                            </span>
                        </div>
                        <div class="password-strength mt-2">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement" id="lengthReq">
                                <i class="far fa-circle"></i> Au moins 8 caractères
                            </div>
                            <div class="requirement" id="upperReq">
                                <i class="far fa-circle"></i> Au moins une majuscule
                            </div>
                            <div class="requirement" id="lowerReq">
                                <i class="far fa-circle"></i> Au moins une minuscule
                            </div>
                            <div class="requirement" id="numberReq">
                                <i class="far fa-circle"></i> Au moins un chiffre
                            </div>
                            <div class="requirement" id="specialReq">
                                <i class="far fa-circle"></i> Au moins un caractère spécial
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="far fa-eye"></i>
                            </span>
                        </div>
                        <div id="passwordMatch" class="mt-2"></div>
                    </div>
                    
                    <div class="d-grid gap-3">
                        <button type="submit" class="btn btn-primary py-2" id="submitBtn">
                            <i class="fas fa-save me-2"></i> Enregistrer les modifications
                        </button>
                        <a href="profile.php" class="btn btn-outline-secondary py-2">
                            <i class="fas fa-times me-2"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const passwordMatch = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            newPassword.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Vérification des critères
                const hasLength = password.length >= 8;
                const hasUpper = /[A-Z]/.test(password);
                const hasLower = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[^A-Za-z0-9]/.test(password);
                
                // Mise à jour visuelle des critères
                document.getElementById('lengthReq').classList.toggle('met', hasLength);
                document.getElementById('upperReq').classList.toggle('met', hasUpper);
                document.getElementById('lowerReq').classList.toggle('met', hasLower);
                document.getElementById('numberReq').classList.toggle('met', hasNumber);
                document.getElementById('specialReq').classList.toggle('met', hasSpecial);
                
                // Calcul de la force
                if (hasLength) strength += 20;
                if (hasUpper) strength += 20;
                if (hasLower) strength += 20;
                if (hasNumber) strength += 20;
                if (hasSpecial) strength += 20;
                
                // Mise à jour de la barre de force
                strengthBar.style.width = strength + '%';
                
                // Couleur de la barre
                if (strength < 40) {
                    strengthBar.style.backgroundColor = '#dc3545';
                } else if (strength < 80) {
                    strengthBar.style.backgroundColor = '#ffc107';
                } else {
                    strengthBar.style.backgroundColor = '#28a745';
                }
            });
            
            // Vérification de la correspondance des mots de passe
            confirmPassword.addEventListener('input', function() {
                if (this.value && this.value !== newPassword.value) {
                    passwordMatch.innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Les mots de passe ne correspondent pas';
                } else if (this.value) {
                    passwordMatch.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Les mots de passe correspondent';
                } else {
                    passwordMatch.innerHTML = '';
                }
            });
            
            // Validation avant soumission
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    passwordMatch.innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Veuillez faire correspondre les mots de passe';
                    passwordMatch.scrollIntoView({ behavior: 'smooth' });
                } else if (newPassword.value.length < 8) {
                    e.preventDefault();
                    alert('Le mot de passe doit contenir au moins 8 caractères');
                }
            });
        });
    </script>
</body>
</html>