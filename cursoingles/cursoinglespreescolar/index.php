<?php
require_once __DIR__ . '/../../config.php';

// Si el usuario ya tiene sesión activa, redirigir al dashboard
if (isset($_SESSION['usuario_id']) && isset($_SESSION['estudiante_id'])) {
    header('Location: /intep/cursoingles/cursoinglespreescolar/dashboard.php');
    exit;
}

// Si no hay sesión, redirigir al login del portal
header('Location: /intep/login.php');
exit;
?>
