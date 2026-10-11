<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];

// Marquer toutes les notifications comme lues
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$success = $stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => $success]);
?>