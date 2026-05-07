<?php
// ============================================================
// API estudiante: estado de alertas + marcar como vistas
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/alertas_helper.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$estudiante_id = (int)($_SESSION['estudiante_id'] ?? 0);
if ($estudiante_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Estudiante no identificado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $alertas = obtenerAlertasEstudiante($conexion, $estudiante_id);
    echo json_encode(['ok' => true, 'alertas' => $alertas]);
    exit;
}

if ($method === 'POST') {
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

    $accion = $data['accion'] ?? '';
    $modulo = $data['modulo'] ?? '';

    if ($accion !== 'marcar_vista' || !in_array($modulo, ALERTAS_MODULOS, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parametros invalidos']);
        exit;
    }

    marcarAlertaVista($conexion, $estudiante_id, $modulo);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Metodo no permitido']);
