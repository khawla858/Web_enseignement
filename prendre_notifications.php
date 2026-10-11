<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die('Accès non autorisé');
}

// Marquer les notifications comme lues
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);

// Récupérer les notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

if (count($notifications) > 0) {
    foreach ($notifications as $notif) {
        echo '<div class="notification-item">';
        echo '<div class="notification-title">'.htmlspecialchars($notif['title']).'</div>';
        echo '<div class="notification-message">'.htmlspecialchars($notif['message']).'</div>';
        echo '<div class="notification-time">'.date('d/m/Y H:i', strtotime($notif['created_at'])).'</div>';
        echo '</div>';
    }
} else {
    echo '<div class="no-notifications">Aucune notification</div>';
}
?>