<?php
// logout.php - Cerrar sesión
session_start();
session_destroy();

// Limpiar cookies si existen
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
}

// Redireccionar al login con mensaje de éxito
header('Location: login.html?success=' . urlencode('Sesión cerrada correctamente'));
exit();
?>