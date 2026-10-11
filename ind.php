<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UnivAssign - ENSAH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #0a2463;
            --primary-medium: #1a73e8;
            --primary-light: #3e92cc;
            --accent-color: #00c8ff;
            --text-light: rgba(255, 255, 255, 0.9);
            --text-muted: rgba(255, 255, 255, 0.7);
            --glass-bg: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.2);
            --error-color: #ff6b6b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Navbar améliorée */
        .navbar {
            width: 100%;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            background: rgba(10, 36, 99, 0.8);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1.25rem;
            color: white;
            text-decoration: none;
        }

        .navbar button {
            padding: 0.75rem 1.5rem;
            background: var(--primary-medium);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar button:hover {
            background: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 200, 255, 0.3);
        }

        /* Contenu principal centré */
        .main-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            width: 100%;
            padding-top: 6rem;
            text-align: center;
        }

        .main-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--text-light);
            text-align: center;
            width: 100%;
        }

        /* Conteneur du formulaire centré */
        .form-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .form-container {
            width: 100%;
            max-width: 420px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
            margin: 0 auto;
        }

        .form-container.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
            animation: fadeInUp 0.6s forwards;
        }

        /* Style pour le conteneur d'erreur */
        #errorContainer {
            background-color: rgba(255, 107, 107, 0.1);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--error-color);
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.5rem;
            display: none;
        }

        #errorContainer i {
            font-size: 1.1rem;
        }

        /* Icône de profil */
        .profile-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            font-size: 2.5rem;
            color: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
            color: white;
        }

        /* Champs de formulaire */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-wrapper .icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .input-wrapper input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.25rem;
            border: none;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .input-wrapper input:focus {
            background: rgba(255, 255, 255, 0.3);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 255, 0.2);
        }

        .input-wrapper input::placeholder {
            color: var(--text-muted);
        }

        /* Bouton de soumission */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-medium), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-medium));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 200, 255, 0.3);
        }

        /* Lien mot de passe oublié */
        .forgot-password {
            display: block;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-password:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-title {
                font-size: 1.5rem;
                padding: 0 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
                max-width: 90%;
                margin: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Barre de navigation -->
    <nav class="navbar">
        <a href="#" class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>UnivAssign</span>
        </a>
        <button id="loginButton">
            <i class="fas fa-sign-in-alt"></i>
            <span>Se connecter</span>
        </button>
    </nav>

    <!-- Contenu principal centré -->
    <div class="main-content">
        <h1 class="main-title">ENSAH - Université Abdelmalek Essaâdi</h1>
        
        <!-- Wrapper pour centrer le formulaire -->
        <div class="form-wrapper">
            <!-- Conteneur du formulaire (caché initialement) -->
            <div class="form-container" id="loginFormContainer">
                <div class="profile-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                
                <h2>Connexion</h2>
                
                <!-- Conteneur d'erreur -->
                <div id="errorContainer">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorMessage"></span>
                </div>
                
                <form id="loginForm">
                    <div class="form-group">
                        <div class="input-wrapper">
                            <span class="icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="username" name="username" placeholder="Nom d'utilisateur" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="input-wrapper">
                            <span class="icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" placeholder="Mot de passe" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-unlock-alt"></i>
                        <span>Se connecter</span>
                    </button>
                    
                    <a href="#" class="forgot-password">
                        <i class="fas fa-key"></i>
                        <span>Mot de passe oublié ?</span>
                    </a>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Gestion de l'affichage du formulaire
        document.getElementById('loginButton').addEventListener('click', function() {
            const formContainer = document.getElementById('loginFormContainer');
            
            // Masquer d'abord les erreurs
            document.getElementById('errorContainer').style.display = 'none';
            
            // Basculer l'affichage avec animation
            if (formContainer.classList.contains('show')) {
                formContainer.classList.remove('show');
                setTimeout(() => {
                    formContainer.style.display = 'none';
                }, 300);
            } else {
                formContainer.style.display = 'block';
                setTimeout(() => {
                    formContainer.classList.add('show');
                }, 10);
            }
        });

        // Validation du formulaire avec AJAX
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const errorContainer = document.getElementById('errorContainer');
            const errorMessage = document.getElementById('errorMessage');
            
            // Masquer l'erreur précédente
            errorContainer.style.display = 'none';
            
            // Validation côté client
            if (!username || !password) {
                showError('Veuillez remplir tous les champs');
                return;
            }
            
            try {
                // Afficher un indicateur de chargement
                const submitBtn = document.querySelector('.submit-btn');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion en cours...';
                submitBtn.disabled = true;
                
                // Envoyer les données via AJAX
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
                });
                
                // Vérifier si la réponse est OK
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirection si la connexion réussit
                    window.location.href = result.redirect;
                } else {
                    // Afficher l'erreur retournée par le serveur
                    showError(result.message || 'Nom d\'utilisateur ou mot de passe incorrect');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showError('Une erreur est survenue lors de la connexion');
            } finally {
                // Réactiver le bouton
                const submitBtn = document.querySelector('.submit-btn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-unlock-alt"></i><span>Se connecter</span>';
                    submitBtn.disabled = false;
                }
            }
        });

        // Fonction pour afficher les erreurs
        function showError(message) {
            const errorContainer = document.getElementById('errorContainer');
            const errorMessage = document.getElementById('errorMessage');
            
            if (errorContainer && errorMessage) {
                errorMessage.textContent = message;
                errorContainer.style.display = 'flex';
                errorContainer.style.alignItems = 'center';
                errorContainer.style.gap = '0.5rem';
                
                // Animation shake pour attirer l'attention
                errorContainer.style.animation = 'shake 0.5s';
                setTimeout(() => {
                    errorContainer.style.animation = '';
                }, 500);
            }
        }
    </script>
</body>
</html>