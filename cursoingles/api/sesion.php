<?php
// ============================================================
// INTEP Inglés — API: Estado de sesión del estudiante
// GET /intep/cursoingles/api/sesion.php
// Retorna JSON con datos de sesión para los módulos HTML
// ============================================================

// Silenciar errores en respuesta JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
// Permitir CORS desde el mismo dominio (necesario para fetch desde .html)
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');

// Sin sesión activa → 401
if (empty($_SESSION['usuario_id']) || empty($_SESSION['estudiante_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$est_id = (int)$_SESSION['estudiante_id'];

// Datos del estudiante + nombre del programa
$st = mysqli_prepare($conexion,
    "SELECT e.nombre, e.foto, p.nombre AS programa
     FROM estudiantes e
     LEFT JOIN programas p ON p.id = e.programa_id
     WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $est_id);
mysqli_stmt_execute($st);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

if (!$est) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

// Nivel y XP del sistema de idiomas (tabla existente)
$nivel_actual = 'A1';
$xp_total     = 0;
$racha        = 0;
$st2 = mysqli_prepare($conexion,
    "SELECT nivel_actual, xp_total, racha_actual FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1");
if ($st2) {
    mysqli_stmt_bind_param($st2, 'i', $est_id);
    mysqli_stmt_execute($st2);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));
    if ($row) {
        $nivel_actual = $row['nivel_actual'];
        $xp_total     = (int)$row['xp_total'];
        $racha        = (int)$row['racha_actual'];
    }
}

// ¿Es estudiante de Primera Infancia?
$programa_nombre    = $est['programa'] ?? '';
$es_primera_infancia = stripos($programa_nombre, 'primera infancia') !== false
                    || stripos($programa_nombre, 'preescolar') !== false;

echo json_encode([
    'ok'                  => true,
    'estudiante_id'       => $est_id,
    'nombre'              => $est['nombre'] ?? '',
    'inicial'             => strtoupper(mb_substr($est['nombre'] ?? 'E', 0, 1)),
    'programa'            => $programa_nombre,
    'nivel'               => $nivel_actual,
    'xp'                  => $xp_total,
    'racha'               => $racha,
    'es_primera_infancia' => $es_primera_infancia,
]);
