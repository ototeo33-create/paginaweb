<?php
/**
 * Gateway de entrada al Curso de Seguridad y Salud en el Trabajo (SST)
 * Inyecta datos del estudiante en localStorage y redirige al curso
 */
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$nombre = $_SESSION['usuario_nombre'] ?? 'Estudiante';
$foto   = '';

if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'estudiante') {
    $est_id = (int)$_SESSION['usuario_id'];
    $conexion = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conexion) {
        mysqli_set_charset($conexion, 'utf8');
        $stmt = mysqli_prepare($conexion, "SELECT foto FROM estudiantes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $est_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $foto = $row['foto'] ?? '';
        mysqli_close($conexion);
    }
}

$nombre_js = addslashes(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
$foto_js   = addslashes(htmlspecialchars($foto,   ENT_QUOTES, 'UTF-8'));
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargando curso SST...</title>
    <style>
        body { margin: 0; background: #033d2e; display: flex; align-items: center;
               justify-content: center; height: 100vh; font-family: sans-serif; color: #fff; }
        .loader { text-align: center; }
        .spinner { width: 48px; height: 48px; border: 4px solid #1a5c4a;
                   border-top-color: #059669; border-radius: 50%;
                   animation: spin 0.8s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { color: #a7f3d0; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="loader">
        <div class="spinner"></div>
        <p>Cargando Seguridad Ocupacional...</p>
    </div>
    <script>
        try {
            localStorage.setItem('intep_student', JSON.stringify({
                nombre: '<?= $nombre_js ?>',
                foto:   '<?= $foto_js ?>'
            }));
        } catch(e) {}
        window.location.replace('CURSO_SST_INTERACTIVO.html');
    </script>
</body>
</html>
