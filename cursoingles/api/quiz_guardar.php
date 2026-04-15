<?php
// INTEP Inglés — Guardar resultado de Quiz
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

if (empty($_SESSION['estudiante_id'])) { echo json_encode(['ok'=>false,'msg'=>'Sin sesión']); exit; }
$est_id = (int)$_SESSION['estudiante_id'];

$body = json_decode(file_get_contents('php://input'), true);
$nivel     = in_array($body['nivel'] ?? '', ['A1','A2','B1']) ? $body['nivel'] : null;
$modulo    = isset($body['modulo_num']) ? (int)$body['modulo_num'] : null;
$score     = isset($body['score'])     ? max(0, min(100, (int)$body['score'])) : 0;
$aprobado  = $score >= 70 ? 1 : 0;

if (!$nivel || $modulo === null) { echo json_encode(['ok'=>false,'msg'=>'Parámetros inválidos']); exit; }

// Contar intentos previos
$st = mysqli_prepare($conexion, "SELECT COUNT(*) FROM ingles_quiz_resultados WHERE estudiante_id=? AND nivel=? AND modulo_num=?");
mysqli_stmt_bind_param($st,'isi',$est_id,$nivel,$modulo);
mysqli_stmt_execute($st);
mysqli_stmt_bind_result($st,$intentos_prev); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
$intento = (int)$intentos_prev + 1;

// Insertar resultado
$ins = mysqli_prepare($conexion,
    "INSERT INTO ingles_quiz_resultados (estudiante_id,nivel,modulo_num,score,aprobado,intento) VALUES (?,?,?,?,?,?)");
mysqli_stmt_bind_param($ins,'sisiii',$est_id,$nivel,$modulo,$score,$aprobado,$intento);
mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

// Si aprobó, actualizar/insertar progreso del módulo
if ($aprobado && $modulo > 0) {
    $upd = mysqli_prepare($conexion,
        "INSERT INTO ingles_cursos_progreso (estudiante_id,nivel,modulo_num,porcentaje,completado,xp_ganado,fecha_completado)
         VALUES (?,?,?,100,1,50,NOW())
         ON DUPLICATE KEY UPDATE porcentaje=100,completado=1,fecha_completado=IFNULL(fecha_completado,NOW())");
    mysqli_stmt_bind_param($upd,'isii',$est_id,$nivel,$modulo,50);
    mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
}

// Si aprobó el quiz GENERAL (modulo_num=0), dar XP extra
if ($aprobado && $modulo === 0) {
    $xp_extra = 200;
    $xpq = mysqli_prepare($conexion,
        "INSERT INTO idiomas_nivel (estudiante_id,nivel_actual,xp_total,racha_actual) VALUES (?,?,?,0)
         ON DUPLICATE KEY UPDATE xp_total=xp_total+?");
    mysqli_stmt_bind_param($xpq,'isii',$est_id,$nivel,$xp_extra,$xp_extra);
    mysqli_stmt_execute($xpq); mysqli_stmt_close($xpq);
}

echo json_encode(['ok'=>true,'aprobado'=>(bool)$aprobado,'score'=>$score,'intento'=>$intento]);
