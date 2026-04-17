<?php
// ============================================
// API: Datos para formulario de evaluacion docente
// GET ?action=docentes  → todos los docentes del sistema
// GET ?action=check&docente_id=X → verificar si ya evaluo a ese docente
// ============================================
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
$usuario_id = (int)$_SESSION['usuario_id'];

// Obtener periodo activo
$res_ctrl = mysqli_query($conexion, "SELECT periodo FROM eval_control WHERE activa = 1 ORDER BY id DESC LIMIT 1");
$ctrl = mysqli_fetch_assoc($res_ctrl);
$periodo_activo = $ctrl['periodo'] ?? null;

if ($action === 'docentes') {
    if (!$periodo_activo) {
        echo json_encode(['docentes' => [], 'msg' => 'No hay evaluacion activa']);
        exit;
    }

    // Todos los docentes activos del sistema
    $sql = "SELECT u.id AS docente_id,
                   u.username AS docente_nombre,
                   e.nombre AS nombre_completo,
                   GROUP_CONCAT(DISTINCT mf.nombre ORDER BY mf.nombre SEPARATOR ', ') AS modulos
            FROM usuarios u
            LEFT JOIN estudiantes e ON u.estudiante_id = e.id
            LEFT JOIN programa_modulo pm ON pm.docente_id = u.id AND pm.estado = 'activo'
            LEFT JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
            WHERE u.rol = 'docente' AND u.estado = 'activo'
            GROUP BY u.id
            ORDER BY u.username";
    $res = mysqli_query($conexion, $sql);

    $docentes = [];
    while ($row = mysqli_fetch_assoc($res)) {
        // Verificar si ya evaluo a este docente en este periodo
        $chk = mysqli_prepare($conexion, "SELECT id FROM eval_docente WHERE estudiante_id = ? AND docente_id = ? AND periodo = ?");
        mysqli_stmt_bind_param($chk, 'iis', $usuario_id, $row['docente_id'], $periodo_activo);
        mysqli_stmt_execute($chk);
        $ya_evaluo = mysqli_stmt_get_result($chk)->fetch_assoc();

        $nombre_display = $row['nombre_completo'] ?: $row['docente_nombre'];

        $docentes[] = [
            'docente_id'      => (int)$row['docente_id'],
            'docente_nombre'  => $nombre_display,
            'modulos'         => $row['modulos'] ?: 'Sin modulos asignados',
            'ya_evaluado'     => $ya_evaluo ? true : false
        ];
    }

    echo json_encode(['docentes' => $docentes, 'periodo' => $periodo_activo]);
    exit;
}

if ($action === 'check') {
    $docente_id = (int)($_GET['docente_id'] ?? 0);
    if (!$docente_id || !$periodo_activo) {
        echo json_encode(['ya_evaluado' => false]);
        exit;
    }
    $stmt = mysqli_prepare($conexion, "SELECT id FROM eval_docente WHERE estudiante_id = ? AND docente_id = ? AND periodo = ?");
    mysqli_stmt_bind_param($stmt, 'iis', $usuario_id, $docente_id, $periodo_activo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    echo json_encode(['ya_evaluado' => (bool)$res->fetch_assoc()]);
    exit;
}

echo json_encode(['error' => 'Accion no valida']);
