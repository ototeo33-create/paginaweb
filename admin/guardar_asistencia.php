<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pm_id = isset($input['programa_modulo_id']) ? (int)$input['programa_modulo_id'] : (isset($input['modulo_id']) ? (int)$input['modulo_id'] : 0);
if (!$input || !$pm_id || !isset($input['asistencias']) || !is_array($input['asistencias'])) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

$programa_modulo_id = $pm_id;
$guardados = 0;

foreach ($input['asistencias'] as $a) {
    $estudiante_id = (int)$a['estudiante_id'];
    $total_clases = (int)$a['total_clases'];
    $total_asistencias = (int)$a['total_asistencias'];
    $total_inasistencias = max(0, $total_clases - $total_asistencias);
    $porcentaje = $total_clases > 0 ? round(($total_asistencias / $total_clases) * 100, 1) : 0;

    $check = mysqli_prepare($conexion, "SELECT id FROM asistencia WHERE estudiante_id = ? AND programa_modulo_id = ?");
    mysqli_stmt_bind_param($check, 'ii', $estudiante_id, $programa_modulo_id);
    mysqli_stmt_execute($check);
    $existe = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($existe) > 0) {
        $row = mysqli_fetch_assoc($existe);
        $stmt = mysqli_prepare($conexion, "UPDATE asistencia SET total_clases=?, total_asistencias=?, total_inasistencias=?, porcentaje_asistencia=? WHERE id=?");
        $id = $row['id'];
        mysqli_stmt_bind_param($stmt, 'iiidi', $total_clases, $total_asistencias, $total_inasistencias, $porcentaje, $id);
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO asistencia (estudiante_id, programa_modulo_id, total_clases, total_asistencias, total_inasistencias, porcentaje_asistencia) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iiiiid', $estudiante_id, $programa_modulo_id, $total_clases, $total_asistencias, $total_inasistencias, $porcentaje);
    }

    if ($stmt && mysqli_stmt_execute($stmt)) {
        $guardados++;
    }
}

echo json_encode(['ok' => true, 'guardados' => $guardados]);
