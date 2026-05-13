<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/modulos_visibilidad.php';
requerir_modulo($conexion, 'cursoingles');

if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['estudiante_id'])) {
    $est_id = (int)$_SESSION['estudiante_id'];
    $nivel  = 'A1';
    $st = mysqli_prepare($conexion,
        "SELECT nivel_actual FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1");
    if ($st) {
        mysqli_stmt_bind_param($st, 'i', $est_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        if ($row) $nivel = $row['nivel_actual'];
    }
    $dest = match($nivel) {
        'A2'      => 'dashboard_a2.php',
        'B1','B2' => 'dashboard_b1.php',
        default   => 'dashboard.php',
    };
    header("Location: /intep/cursoingles/$dest");
    exit;
}

header('Location: /intep/login.php');
exit;
