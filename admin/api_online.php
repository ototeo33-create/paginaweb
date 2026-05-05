<?php
require_once '../config.php';
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    http_response_code(403); exit;
}
header('Content-Type: application/json');

$minutos = 10;
$r = mysqli_query($conexion,
    "SELECT u.id, COALESCE(e.nombre, u.username) as nombre, u.username, u.rol, u.ultima_actividad
     FROM usuarios u
     LEFT JOIN estudiantes e ON u.estudiante_id = e.id
     WHERE u.ultima_actividad >= DATE_SUB(NOW(), INTERVAL $minutos MINUTE)
     AND u.rol != 'admin'
     ORDER BY u.ultima_actividad DESC"
);
$online = [];
while ($row = mysqli_fetch_assoc($r)) {
    $seg = time() - strtotime($row['ultima_actividad']);
    $row['hace'] = $seg < 60 ? 'Ahora mismo' : round($seg/60) . ' min atrás';
    $online[] = $row;
}
echo json_encode(['total' => count($online), 'usuarios' => $online, 'ts' => date('H:i:s')]);
