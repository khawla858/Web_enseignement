<?php
// config.php
$servername = "localhost";
$username = "root"; 
$password = "";    
$dbname = "base_web";

// Connexion MySQLi (inutile si tu utilises PDO uniquement)
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion MySQLi
if ($conn->connect_error) {
    die("Erreur connexion: " . $conn->connect_error);
}

// Connexion PDO
try {
    $pdo = new PDO("mysql:host=localhost;dbname=base_web", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
