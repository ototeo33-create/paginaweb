<?php
/**
 * Descarga/visualización segura del documento adjunto de un mensaje.
 * Solo usuarios autenticados (admin o estudiante) pueden acceder.
 */
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('No autorizado.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$stmt = mysqli_prepare($conexion, "SELECT adjunto_archivo, adjunto_nombre FROM mensajes_admin WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row || empty($row['adjunto_archivo'])) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

// basename() evita cualquier intento de path traversal
$ruta = __DIR__ . '/uploads/mensajes/' . basename($row['adjunto_archivo']);
if (!is_file($ruta)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

$nombre = $row['adjunto_nombre'] ?: basename($ruta);
$ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
// PDF e imágenes se muestran en el navegador; Word se descarga.
$inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);

header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $nombre) . '"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
