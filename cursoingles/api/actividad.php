<?php

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../course_activity.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['estudiante_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$est_id = (int)$_SESSION['estudiante_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $nivel = $_GET['nivel'] ?? null;
    if ($nivel && !in_array(strtoupper($nivel), ['A1', 'A2', 'B1', 'KIDS'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nivel invalido']);
        exit;
    }

    $actividad = intepCourseGetLastActivity($conexion, $est_id, $nivel ? strtoupper($nivel) : null);
    echo json_encode(['ok' => true, 'actividad' => $actividad]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $nivel = strtoupper(trim((string)($body['nivel'] ?? 'A1')));
    $modulo_num = (int)($body['modulo_num'] ?? $body['num'] ?? 1);
    $page_path = trim((string)($body['page_path'] ?? ''));
    $page_url = trim((string)($body['page_url'] ?? $page_path));
    $page_title = trim((string)($body['page_title'] ?? ''));
    $section_id = trim((string)($body['section_id'] ?? ''));
    $section_title = trim((string)($body['section_title'] ?? ''));
    $activity_type = trim((string)($body['activity_type'] ?? 'lesson'));

    if (!in_array($nivel, ['A1', 'A2', 'B1', 'KIDS'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nivel invalido']);
        exit;
    }

    if ($modulo_num < 0 || $modulo_num > 99) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Modulo invalido']);
        exit;
    }

    if ($page_path === '' || strpos($page_path, '/intep/cursoingles/') !== 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ruta invalida']);
        exit;
    }

    if ($page_url === '') {
        $page_url = $page_path;
    }

    intepCourseEnsureActivityTable($conexion);

    $st = mysqli_prepare(
        $conexion,
        "INSERT INTO ingles_ultima_actividad
            (estudiante_id, nivel, modulo_num, page_path, page_url, page_title, section_id, section_title, activity_type)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nivel = VALUES(nivel),
            modulo_num = VALUES(modulo_num),
            page_path = VALUES(page_path),
            page_url = VALUES(page_url),
            page_title = VALUES(page_title),
            section_id = VALUES(section_id),
            section_title = VALUES(section_title),
            activity_type = VALUES(activity_type)"
    );

    if (!$st) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo preparar la consulta']);
        exit;
    }

    mysqli_stmt_bind_param(
        $st,
        'isissssss',
        $est_id,
        $nivel,
        $modulo_num,
        $page_path,
        $page_url,
        $page_title,
        $section_id,
        $section_title,
        $activity_type
    );

    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la actividad']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
