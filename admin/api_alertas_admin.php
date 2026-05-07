<?php
// ============================================================
// API admin: disparar / limpiar alertas titilantes
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/alertas_helper.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos invalidos']);
    exit;
}

if (empty($data['csrf_token']) || !verifyCsrfToken($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de seguridad invalido. Recarga la pagina.']);
    exit;
}

$modulo = $data['modulo'] ?? '';
$accion = $data['accion'] ?? 'disparar';

if (!in_array($modulo, ALERTAS_MODULOS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Modulo invalido']);
    exit;
}

$admin_id = (int)$_SESSION['usuario_id'];
$mensaje  = isset($data['mensaje']) ? mb_substr(trim((string)$data['mensaje']), 0, 180) : null;

// Resolver lista de estudiantes destino
$ids = [];
if ($modulo === 'evaluacion' || ($data['todos'] ?? false) === true) {
    // Todos los estudiantes con cuenta activa
    $res = mysqli_query(
        $conexion,
        "SELECT e.id
           FROM estudiantes e
           JOIN usuarios u ON u.estudiante_id = e.id
          WHERE u.estado = 'activo' AND u.rol = 'estudiante'"
    );
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $ids[] = (int)$r['id'];
        }
    }
} else {
    $raw_ids = $data['estudiante_ids'] ?? [];
    if (!is_array($raw_ids)) $raw_ids = [];
    foreach ($raw_ids as $v) {
        $v = (int)$v;
        if ($v > 0) $ids[] = $v;
    }
    $ids = array_values(array_unique($ids));
}

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay estudiantes destino']);
    exit;
}

if ($accion === 'limpiar') {
    // Marcar como vistas las alertas activas para esos estudiantes en ese módulo
    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE alertas_estudiante
            SET vista_en = NOW()
          WHERE modulo = ?
            AND estudiante_id = ?
            AND vista_en IS NULL"
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo preparar la consulta']);
        exit;
    }
    $afectados = 0;
    foreach ($ids as $eid) {
        mysqli_stmt_bind_param($stmt, 'si', $modulo, $eid);
        if (mysqli_stmt_execute($stmt)) {
            $afectados += mysqli_stmt_affected_rows($stmt);
        }
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['ok' => true, 'limpiadas' => $afectados, 'csrf_token' => csrf_token()]);
    exit;
}

// Acción por defecto: disparar (insertar)
// Usamos INSERT IGNORE apoyándonos en el UNIQUE (estudiante_id, modulo, vista_en) — donde vista_en es NULL.
// MySQL trata NULL como distinto en UNIQUE, así que para garantizar idempotencia hacemos primero un check.
$stmt_check = mysqli_prepare(
    $conexion,
    "SELECT 1 FROM alertas_estudiante
      WHERE estudiante_id = ? AND modulo = ? AND vista_en IS NULL LIMIT 1"
);
$stmt_ins = mysqli_prepare(
    $conexion,
    "INSERT INTO alertas_estudiante (estudiante_id, modulo, creado_por, mensaje)
     VALUES (?, ?, ?, ?)"
);

if (!$stmt_check || !$stmt_ins) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo preparar la consulta']);
    exit;
}

$creadas = 0; $ya_activas = 0;
foreach ($ids as $eid) {
    mysqli_stmt_bind_param($stmt_check, 'is', $eid, $modulo);
    mysqli_stmt_execute($stmt_check);
    $r = mysqli_stmt_get_result($stmt_check)->fetch_assoc();
    if ($r) { $ya_activas++; continue; }

    mysqli_stmt_bind_param($stmt_ins, 'isis', $eid, $modulo, $admin_id, $mensaje);
    if (mysqli_stmt_execute($stmt_ins)) {
        $creadas++;
    }
}

mysqli_stmt_close($stmt_check);
mysqli_stmt_close($stmt_ins);

echo json_encode([
    'ok' => true,
    'creadas' => $creadas,
    'ya_activas' => $ya_activas,
    'total_destino' => count($ids),
    'csrf_token' => csrf_token(),
]);
