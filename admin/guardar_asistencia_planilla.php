<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ingresar_notas.php'); exit;
}

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    header('Location: ingresar_notas.php?msg=' . urlencode('error|Token de seguridad invalido.'));
    exit;
}

$pm_id     = (int)($_POST['programa_modulo_id'] ?? 0);
$bim_id    = (int)($_POST['bimestre_id'] ?? 0);
$asist     = $_POST['asist'] ?? [];
$obs_in    = $_POST['obs']   ?? [];
$reg_por   = (int)$_SESSION['usuario_id'];

if (!$pm_id || !$bim_id) {
    header('Location: ingresar_notas.php?msg=' . urlencode('error|Faltan datos para guardar.'));
    exit;
}

// Verificar que el docente solo guarde sus propios módulos (admin puede todos)
if ($_SESSION['usuario_rol'] === 'docente') {
    $stmt_v = mysqli_prepare($conexion,
        "SELECT id FROM programa_modulo WHERE id = ? AND docente_id = ?");
    mysqli_stmt_bind_param($stmt_v, 'ii', $pm_id, $reg_por);
    mysqli_stmt_execute($stmt_v);
    $r = mysqli_stmt_get_result($stmt_v);
    if (mysqli_num_rows($r) === 0) {
        header('Location: ingresar_notas.php?msg=' . urlencode('error|No autorizado para este modulo.'));
        exit;
    }
}

$guardados = 0;
$sql = "INSERT INTO asistencias (estudiante_id, programa_modulo_id, bimestre_id, fecha, estado, observacion, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            estado = VALUES(estado),
            observacion = VALUES(observacion),
            registrado_por = VALUES(registrado_por)";
$stmt = mysqli_prepare($conexion, $sql);

foreach ($asist as $est_id => $fechas) {
    $est_id = (int)$est_id;
    if (!$est_id) continue;
    if (!is_array($fechas)) continue;
    foreach ($fechas as $fecha => $estado) {
        $estado = trim($estado);
        if ($estado === '') continue; // saltar celdas en blanco
        if (!in_array($estado, ['presente','ausente','tardanza','excusa'], true)) continue;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) continue;
        $observacion = trim($obs_in[$est_id][$fecha] ?? '');
        mysqli_stmt_bind_param($stmt, 'iiisssi',
            $est_id, $pm_id, $bim_id, $fecha, $estado, $observacion, $reg_por);
        if (mysqli_stmt_execute($stmt)) $guardados++;
    }
}

$msg = 'success|' . $guardados . ' registros de asistencia guardados.';
header("Location: ingresar_notas.php?modulo_id={$pm_id}&tab=asistencia&msg=" . urlencode($msg));
exit;
