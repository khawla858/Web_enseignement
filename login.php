<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Vérification des champs vides
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Veuillez remplir tous les champs'
        ]);
        exit();
    }

    // Préparation requête SQL
    $stm = $conn->prepare("SELECT id, username, email, nom, prenom, password_hash, role FROM users WHERE username = ?");
    $stm->bind_param("s", $username);
    $stm->execute();
    $result = $stm->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Vérification du mot de passe
        if (password_verify($password, $user['password_hash'])) {
            // Stocker les informations de l'utilisateur dans la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['role'] = $user['role'];

            // Déterminer la page de redirection selon le rôle
            $redirect = match($user['role']) {
                'admin' => 'admin.php',
                'chef_departement' => 'chef_departement.php',
                'coordonnateur' => 'coordonnateur.php',
                'professeur' => 'professeur.php',
                'vacataire' => 'vacataire.php',
                default => 'index.html'
            };

            echo json_encode([
                'success' => true,
                'redirect' => $redirect,
                'message' => 'Connexion réussie'
            ]);
            exit();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Mot de passe incorrect'
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ]);
        exit();
    }
}

// Si la méthode n'est pas POST
echo json_encode([
    'success' => false,
    'message' => 'Méthode non autorisée'
]);
exit();
?>