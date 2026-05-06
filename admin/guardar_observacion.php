<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pm_id = isset($input['programa_modulo_id']) ? (int)$input['programa_modulo_id'] : (isset($input['modulo_id']) ? (int)$input['modulo_id'] : 0);
if (!$input || !isset($input['estudiante_id']) || !$pm_id || !isset($input['observacion'])) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

$estudiante_id = (int)$input['estudiante_id'];
$programa_modulo_id = $pm_id;
$observacion = trim($input['observacion']);
$autor_id = (int)$_SESSION['usuario_id'];

if ($observacion === '') {
    echo json_encode(['ok' => false, 'error' => 'La observación no puede estar vacía']);
    exit;
}

// Validacion: docente solo puede observar SUS modulos asignados
if ($_SESSION['usuario_rol'] === 'docente') {
    $stmt_v = mysqli_prepare($conexion,
        "SELECT id FROM programa_modulo WHERE id = ? AND docente_id = ? AND estado = 'activo'");
    mysqli_stmt_bind_param($stmt_v, 'ii', $programa_modulo_id, $autor_id);
    mysqli_stmt_execute($stmt_v);
    $r_v = mysqli_stmt_get_result($stmt_v);
    if (mysqli_num_rows($r_v) === 0) {
        echo json_encode(['ok' => false, 'error' => 'No autorizado para este modulo']);
        exit;
    }
}

$stmt = mysqli_prepare($conexion, "INSERT INTO observaciones (estudiante_id, programa_modulo_id, observacion, autor_id) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iisi', $estudiante_id, $programa_modulo_id, $observacion, $autor_id);

if (mysqli_stmt_execute($stmt)) {
    $id = mysqli_insert_id($conexion);
    // Obtener nombre del autor
    $autor_stmt = mysqli_prepare($conexion, "SELECT username FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($autor_stmt, 'i', $autor_id);
    mysqli_stmt_execute($autor_stmt);
    $autor = mysqli_fetch_assoc(mysqli_stmt_get_result($autor_stmt));

    echo json_encode([
        'ok' => true,
        'observacion' => [
            'id' => $id,
            'observacion' => $observacion,
            'fecha' => date('Y-m-d H:i:s'),
            'autor' => $autor['username'] ?? 'Desconocido'
        ]
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
}
