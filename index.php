<?php
// Punto de entrada del sistema SISAT
// Redirige a login o al dashboard si ya hay sesión activa
session_start();
if (isset($_SESSION['usuario'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>
