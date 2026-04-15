<?php
// ============================================================
// INTEP Inglés — API: Progreso de módulos interactivos
// GET  → Retorna todos los módulos del estudiante
// POST → Guarda progreso/completado de un módulo
// ============================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Auth
if (empty($_SESSION['usuario_id']) || empty($_SESSION['estudiante_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$est_id = (int)$_SESSION['estudiante_id'];

// ── GET: Devuelve progreso de todos los módulos ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $nivel = $_GET['nivel'] ?? null;
    $where = 'WHERE p.estudiante_id = ?';
    $params = [$est_id];
    $types  = 'i';

    if ($nivel && in_array($nivel, ['A1','A2','B1','kids'])) {
        $where  .= ' AND p.nivel = ?';
        $params[] = $nivel;
        $types   .= 's';
    }

    $st = mysqli_prepare($conexion,
        "SELECT p.nivel, p.modulo_num, p.porcentaje, p.completado, p.xp_ganado, p.fecha_completado
         FROM ingles_cursos_progreso p
         $where
         ORDER BY p.nivel, p.modulo_num");

    mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);

    // Indexar por "nivel_modulo" para fácil acceso en JS
    $progreso = [];
    foreach ($rows as $row) {
        $key = $row['nivel'] . '_' . $row['modulo_num'];
        $progreso[$key] = [
            'porcentaje'       => (int)$row['porcentaje'],
            'completado'       => (bool)$row['completado'],
            'xp_ganado'        => (int)$row['xp_ganado'],
            'fecha_completado' => $row['fecha_completado'],
        ];
    }

    echo json_encode(['ok' => true, 'progreso' => $progreso]);
    exit;
}

// ── POST: Guarda progreso de un módulo ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $nivel      = $body['nivel']      ?? '';
    $modulo_num = (int)($body['modulo_num'] ?? 0);
    $porcentaje = min(100, max(0, (int)($body['porcentaje'] ?? 0)));
    $completado = !empty($body['completado']) ? 1 : 0;
    $xp_ganado  = max(0, (int)($body['xp_ganado'] ?? 0));

    if (!in_array($nivel, ['A1','A2','B1','kids']) || $modulo_num < 1 || $modulo_num > 8) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
        exit;
    }

    $fecha = $completado ? date('Y-m-d H:i:s') : null;

    // UPSERT progreso del módulo
    $st = mysqli_prepare($conexion,
        "INSERT INTO ingles_cursos_progreso
            (estudiante_id, nivel, modulo_num, porcentaje, completado, xp_ganado, fecha_completado)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            porcentaje       = GREATEST(porcentaje, VALUES(porcentaje)),
            completado       = GREATEST(completado, VALUES(completado)),
            xp_ganado        = GREATEST(xp_ganado, VALUES(xp_ganado)),
            fecha_completado = IF(VALUES(completado)=1 AND fecha_completado IS NULL, VALUES(fecha_completado), fecha_completado)");

    mysqli_stmt_bind_param($st, 'isiiiss',
        $est_id, $nivel, $modulo_num, $porcentaje, $completado, $xp_ganado, $fecha);
    $ok = mysqli_stmt_execute($st);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
        exit;
    }

    // Sumar XP al sistema de idiomas existente (idiomas_nivel)
    if ($xp_ganado > 0) {
        $stx = mysqli_prepare($conexion,
            "INSERT INTO idiomas_nivel (estudiante_id, xp_total)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE xp_total = xp_total + ?");
        if ($stx) {
            mysqli_stmt_bind_param($stx, 'iii', $est_id, $xp_ganado, $xp_ganado);
            mysqli_stmt_execute($stx);
        }
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
