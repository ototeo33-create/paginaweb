<?php
// Redirigir al login o al dashboard según sesión
require_once __DIR__ . '/config.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: /intep/dashboard.php');
} else {
    header('Location: /intep/login.php');
}
exit;
