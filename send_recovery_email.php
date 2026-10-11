<?php
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$username = trim($_POST['username'] ?? '');

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur requis']);
    exit;
}

try {
    // Vérifier si l'utilisateur existe
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur introuvable']);
        exit;
    }

    $user = $result->fetch_assoc();

    // Générer un token de récupération
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Vérifier si la table password_resets existe, sinon la créer
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Supprimer les anciens tokens pour cet utilisateur
    $conn->query("DELETE FROM password_resets WHERE user_id = {$user['id']}");

    // Stocker le nouveau token
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user['id'], $token, $expires);
    $stmt->execute();

    // Envoyer l'email
    $resetLink = "http://localhost/web_projet/reset_password.php?token=$token";
    $to = $user['email'];
    $subject = "Réinitialisation de votre mot de passe - UnivAssign";
    $message = "
    <html>
    <head>
        <title>Réinitialisation de mot de passe</title>
    </head>
    <body>
        <h2>Réinitialisation de votre mot de passe</h2>
        <p>Bonjour,</p>
        <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien ci-dessous pour procéder :</p>
        <p><a href='$resetLink'>$resetLink</a></p>
        <p>Ce lien expirera dans 1 heure.</p>
        <p>Si vous n'avez pas demandé cette réinitialisation, veuillez ignorer cet email.</p>
        <p>Cordialement,<br>L'équipe UnivAssign</p>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@univassign.com" . "\r\n";

    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de l\'envoi de l\'email']);
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération du mot de passe: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue. Veuillez réessayer.']);
}