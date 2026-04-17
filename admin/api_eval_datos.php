<?php
// ============================================
// API: Datos para formulario de evaluacion docente
// GET ?action=modulos  → modulos/docentes del programa del estudiante
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

// Obtener programa del estudiante (puede estar en usuarios.programa_id o en estudiantes.programa_id via estudiante_id)
$stmt = mysqli_prepare($conexion, "
    SELECT COALESCE(u.programa_id, e.programa_id) AS programa_id
    FROM usuarios u
    LEFT JOIN estudiantes e ON u.estudiante_id = e.id
    WHERE u.id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $usuario_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);
$programa_id = $user['programa_id'] ?? null;

// Obtener periodo activo
$res_ctrl = mysqli_query($conexion, "SELECT periodo FROM eval_control WHERE activa = 1 ORDER BY id DESC LIMIT 1");
$ctrl = mysqli_fetch_assoc($res_ctrl);
$periodo_activo = $ctrl['periodo'] ?? null;

if ($action === 'modulos') {
    if (!$programa_id) {
        echo json_encode(['modulos' => [], 'msg' => 'No tienes programa asignado']);
        exit;
    }
    if (!$periodo_activo) {
        echo json_encode(['modulos' => [], 'msg' => 'No hay evaluacion activa']);
        exit;
    }

    // Obtener modulos del programa con docente asignado
    $sql = "SELECT pm.id AS programa_modulo_id,
                   mf.nombre AS modulo_nombre,
                   u.id AS docente_id,
                   u.username AS docente_nombre
            FROM programa_modulo pm
            JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
            JOIN usuarios u ON pm.docente_id = u.id
            WHERE pm.programa_id = ?
              AND pm.estado = 'activo'
              AND u.rol = 'docente'
            ORDER BY mf.nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $programa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $modulos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        // Verificar si ya evaluo a este docente en este periodo
        $chk = mysqli_prepare($conexion, "SELECT id FROM eval_docente WHERE estudiante_id = ? AND docente_id = ? AND periodo = ?");
        mysqli_stmt_bind_param($chk, 'iis', $usuario_id, $row['docente_id'], $periodo_activo);
        mysqli_stmt_execute($chk);
        $ya_evaluo = mysqli_stmt_get_result($chk)->fetch_assoc();

        $modulos[] = [
            'programa_modulo_id' => (int)$row['programa_modulo_id'],
            'modulo_nombre' => $row['modulo_nombre'],
            'docente_id' => (int)$row['docente_id'],
            'docente_nombre' => $row['docente_nombre'],
            'ya_evaluado' => $ya_evaluo ? true : false
        ];
    }

    echo json_encode(['modulos' => $modulos, 'periodo' => $periodo_activo]);
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
