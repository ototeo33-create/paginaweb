<?php
// ============================================
// API: Guardar evaluacion docente (POST AJAX)
// ============================================
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos invalidos']);
    exit;
}

// Verificar CSRF
if (empty($data['csrf_token']) || !verifyCsrfToken($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de seguridad invalido. Recarga la pagina.']);
    exit;
}

$estudiante_id = (int)$_SESSION['usuario_id'];
$docente_id = (int)($data['docente_id'] ?? 0);
$programa_modulo_id = (int)($data['programa_modulo_id'] ?? 0);
$calificaciones = $data['calificaciones'] ?? [];
$comentarios_positivos = trim($data['comentarios_positivos'] ?? '');
$comentarios_mejora = trim($data['comentarios_mejora'] ?? '');

// Validaciones
$criterios_validos = [1,2,3,4,5,6,7,8];
if (!$docente_id || !$programa_modulo_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Selecciona un docente y modulo']);
    exit;
}

if (count($calificaciones) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Debes calificar los 8 criterios']);
    exit;
}

foreach ($calificaciones as $c) {
    if (!isset($c['criterio_id'], $c['calificacion']) ||
        !in_array((int)$c['criterio_id'], $criterios_validos) ||
        (int)$c['calificacion'] < 1 || (int)$c['calificacion'] > 4) {
        http_response_code(400);
        echo json_encode(['error' => 'Calificaciones invalidas']);
        exit;
    }
}

// Verificar evaluacion activa
$res_ctrl = mysqli_query($conexion, "SELECT periodo FROM eval_control WHERE activa = 1 ORDER BY id DESC LIMIT 1");
$ctrl = mysqli_fetch_assoc($res_ctrl);
if (!$ctrl) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay evaluacion activa en este momento']);
    exit;
}
$periodo = $ctrl['periodo'];

// Verificar que el docente existe y es docente
$stmt = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE id = ? AND rol = 'docente'");
mysqli_stmt_bind_param($stmt, 'i', $docente_id);
mysqli_stmt_execute($stmt);
if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    http_response_code(400);
    echo json_encode(['error' => 'Docente no valido']);
    exit;
}

// Verificar que el programa_modulo existe y corresponde al docente
$stmt = mysqli_prepare($conexion, "SELECT id FROM programa_modulo WHERE id = ? AND docente_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $programa_modulo_id, $docente_id);
mysqli_stmt_execute($stmt);
if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    http_response_code(400);
    echo json_encode(['error' => 'Modulo no corresponde al docente']);
    exit;
}

// Verificar duplicado
$stmt = mysqli_prepare($conexion, "SELECT id FROM eval_docente WHERE estudiante_id = ? AND docente_id = ? AND periodo = ?");
mysqli_stmt_bind_param($stmt, 'iis', $estudiante_id, $docente_id, $periodo);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    http_response_code(409);
    echo json_encode(['error' => 'Ya evaluaste a este docente en este periodo']);
    exit;
}

// Calcular puntaje
$puntaje_total = 0;
foreach ($calificaciones as $c) {
    $puntaje_total += (int)$c['calificacion'];
}
$porcentaje = round(($puntaje_total / 32) * 100, 2);

// Insertar evaluacion
mysqli_begin_transaction($conexion);
try {
    $stmt = mysqli_prepare($conexion,
        "INSERT INTO eval_docente (estudiante_id, docente_id, programa_modulo_id, periodo, comentarios_positivos, comentarios_mejora, puntaje_total, porcentaje)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'iiisssid',
        $estudiante_id, $docente_id, $programa_modulo_id, $periodo,
        $comentarios_positivos, $comentarios_mejora, $puntaje_total, $porcentaje
    );
    mysqli_stmt_execute($stmt);
    $evaluacion_id = mysqli_insert_id($conexion);

    // Insertar 8 respuestas
    $stmt_r = mysqli_prepare($conexion,
        "INSERT INTO eval_respuestas (evaluacion_id, criterio_id, calificacion) VALUES (?, ?, ?)"
    );
    foreach ($calificaciones as $c) {
        $crit = (int)$c['criterio_id'];
        $cal = (int)$c['calificacion'];
        mysqli_stmt_bind_param($stmt_r, 'iii', $evaluacion_id, $crit, $cal);
        mysqli_stmt_execute($stmt_r);
    }

    mysqli_commit($conexion);
    echo json_encode([
        'ok' => true,
        'msg' => 'Evaluacion guardada exitosamente',
        'puntaje' => $puntaje_total,
        'porcentaje' => $porcentaje
    ]);
} catch (Exception $e) {
    mysqli_rollback($conexion);
    error_log("Error guardando evaluacion: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar. Intenta de nuevo.']);
}
