<?php
/**
 * descargar_material.php
 * Sirve archivos de material de clase de forma segura.
 * Valida sesión activa y que el usuario tenga acceso al archivo.
 */
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol        = $_SESSION['usuario_rol'];
$usuario_id = (int)$_SESSION['usuario_id'];
$mat_id     = (int)($_GET['id'] ?? 0);

if ($mat_id <= 0) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

// Verificar que la tabla existe
$tabla = mysqli_query($conexion, "SHOW TABLES LIKE 'material_clase'");
if (mysqli_num_rows($tabla) === 0) {
    http_response_code(404);
    exit('Recurso no encontrado.');
}

// ============================================================
// PERMISOS DE ACCESO
// ============================================================
if ($rol === 'admin') {
    // Admin: acceso total
    $stmt = mysqli_prepare($conexion,
        "SELECT mc.*, pm.programa_id
         FROM material_clase mc
         JOIN programa_modulo pm ON mc.programa_modulo_id = pm.id
         WHERE mc.id = ? AND mc.activo = 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $mat_id);

} elseif ($rol === 'docente') {
    // Docente: solo puede descargar sus propios materiales
    $stmt = mysqli_prepare($conexion,
        "SELECT mc.*, pm.programa_id
         FROM material_clase mc
         JOIN programa_modulo pm ON mc.programa_modulo_id = pm.id
         WHERE mc.id = ? AND mc.docente_id = ? AND mc.activo = 1"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $mat_id, $usuario_id);

} else {
    // Estudiante: solo materiales de su programa
    $est_id = (int)($_SESSION['estudiante_id'] ?? 0);
    if (!$est_id) {
        http_response_code(403);
        exit('Acceso denegado.');
    }
    $stmt = mysqli_prepare($conexion,
        "SELECT mc.*, pm.programa_id
         FROM material_clase mc
         JOIN programa_modulo pm ON mc.programa_modulo_id = pm.id
         JOIN estudiantes e ON e.programa_id = pm.programa_id
         WHERE mc.id = ? AND e.id = ? AND mc.activo = 1"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $mat_id, $est_id);
}

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$mat = mysqli_fetch_assoc($res);

if (!$mat) {
    http_response_code(403);
    exit('No tienes permiso para acceder a este archivo o no existe.');
}

// ============================================================
// CONSTRUIR RUTA REAL DEL ARCHIVO
// ============================================================
// archivo_path guardado como: /intep/uploads/materiales/mat_xxx.pdf
// Convertir a ruta absoluta del servidor
$ruta_relativa = $mat['archivo_path']; // /intep/uploads/materiales/archivo.ext
$nombre_archivo = basename($ruta_relativa);

// Seguridad: evitar path traversal
if ($nombre_archivo !== basename($nombre_archivo) || str_contains($nombre_archivo, '..')) {
    http_response_code(400);
    exit('Nombre de archivo inválido.');
}

$ruta_fisica = __DIR__ . '/uploads/materiales/' . $nombre_archivo;

if (!file_exists($ruta_fisica) || !is_file($ruta_fisica)) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor.');
}

// ============================================================
// SERVIR EL ARCHIVO
// ============================================================
$mime = $mat['archivo_tipo'] ?: 'application/octet-stream';
$nombre_descarga = $mat['archivo_nombre']; // nombre original para el usuario

// Limpiar cualquier salida previa
while (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . addslashes($nombre_descarga) . '"');
header('Content-Length: ' . filesize($ruta_fisica));
header('Cache-Control: private, no-cache');
header('X-Content-Type-Options: nosniff');

readfile($ruta_fisica);
exit;
