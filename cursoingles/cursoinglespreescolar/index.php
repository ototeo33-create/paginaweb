<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/modulos_visibilidad.php';
requerir_modulo($conexion, 'intep_kids');

// Si el usuario ya tiene sesión activa, redirigir al dashboard
if (isset($_SESSION['usuario_id']) && isset($_SESSION['estudiante_id'])) {
    header('Location: /intep/cursoingles/cursoinglespreescolar/dashboard.php');
    exit;
}

// Si no hay sesión, redirigir al login del portal
header('Location: /intep/login.php');
exit;
?>
