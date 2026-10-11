<?php
$file = 'template_ue.xlsx';

if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
} else {
    // Rediriger vers la page précédente avec un message d'erreur
    session_start();
    $_SESSION['error'] = "Le fichier modèle n'est pas disponible pour le moment.";
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}