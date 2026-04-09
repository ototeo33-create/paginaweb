<?php
ini_set('display_errors', 0);
error_reporting(0);
// ============================================================
// My Teacher GUS — Streaming SSE endpoint
// ============================================================
ob_start();
require_once '../config.php';
$usr_id  = isset($_SESSION['usuario_id'])    ? (int)$_SESSION['usuario_id']    : 0;
$usr_rol = $_SESSION['usuario_rol'] ?? '';
$est_id  = isset($_SESSION['estudiante_id']) ? (int)$_SESSION['estudiante_id'] : 0;
ob_end_clean();
ini_set('display_errors', 0);
error_reporting(0);
session_write_close();

function sse(array $d): void {
    echo 'data: ' . json_encode($d, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

if (!$usr_id || $usr_rol !== 'estudiante' || !$est_id) {
    sse(['error' => 'No autorizado']); exit;
}

$leccion_id = (int)($_POST['leccion_id'] ?? 0);
$mensaje    = trim($_POST['mensaje'] ?? '');

if (!$mensaje || !$leccion_id) { sse(['error' => 'Datos incompletos']); exit; }

// Verify lesson
$st = mysqli_prepare($conexion, "SELECT tema, completada FROM ingles_lecciones WHERE id=? AND estudiante_id=?");
mysqli_stmt_bind_param($st, 'ii', $leccion_id, $est_id);
mysqli_stmt_execute($st);
$leccion = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
if (!$leccion || $leccion['completada']) { sse(['error' => 'Lección no válida']); exit; }

// Level + nombre estudiante
$stn = mysqli_prepare($conexion, "SELECT n.nivel_actual, e.nombre FROM idiomas_nivel n JOIN estudiantes e ON e.id=n.estudiante_id WHERE n.estudiante_id=?");
mysqli_stmt_bind_param($stn, 'i', $est_id);
mysqli_stmt_execute($stn);
$row_n = mysqli_fetch_assoc(mysqli_stmt_get_result($stn));
$nivel = $row_n['nivel_actual'] ?? 'A1';
$nombre_est = explode(' ', trim($row_n['nombre'] ?? 'estudiante'))[0];

// History
$sth = mysqli_prepare($conexion, "SELECT rol, mensaje FROM ingles_conversaciones WHERE leccion_id=? ORDER BY created_at ASC LIMIT 30");
mysqli_stmt_bind_param($sth, 'i', $leccion_id);
mysqli_stmt_execute($sth);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($sth), MYSQLI_ASSOC);

// Save user message
$stu = mysqli_prepare($conexion, "INSERT INTO ingles_conversaciones (estudiante_id, rol, mensaje, leccion_id) VALUES (?,?,?,?)");
$rol_u = 'user';
mysqli_stmt_bind_param($stu, 'issi', $est_id, $rol_u, $mensaje, $leccion_id);
mysqli_stmt_execute($stu);

// Groq key
$groq_key = getenv('GROQ_API_KEY') ?: '';
if (!$groq_key) {
    $env = dirname(__DIR__) . '/.env';
    if (file_exists($env)) {
        foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with($line, 'GROQ_API_KEY=')) { $groq_key = substr($line, 13); break; }
        }
    }
}
if (!$groq_key) { sse(['error' => 'API key no configurada']); exit; }

// System prompt — voz, respuestas cortas, nombre del estudiante
$system = <<<P
You are GUS, a friendly male English teacher at INTEP, Colombia. VOICE CONVERSATION MODE.
Student: $nombre_est | Level: $nivel | Topic: {$leccion['tema']}.
RULES: Max 1-2 short spoken sentences. NO markdown, NO lists, NO asterisks. Natural speech only.
A1: 70% Spanish + 30% English. A2: 50/50. B1/B2: mostly English.
Use "$nombre_est" by name often. ONE question per turn, simple and clear.
Celebrate correct answers: "¡Muy bien $nombre_est!" or "Perfect!". Correct gently in Spanish.
If student says done/finish/terminar: one spoken sentence summary, then [LECCION_COMPLETADA].
P;
P;

$messages = [['role' => 'system', 'content' => $system]];
foreach ($rows as $r) $messages[] = ['role' => $r['rol'], 'content' => $r['mensaje']];
$messages[] = ['role' => 'user', 'content' => $mensaje];

// Stream from Groq
$full = '';
$buf  = '';

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'model' => 'llama-3.3-70b-versatile',
        'messages' => $messages,
        'temperature' => 0.75,
        'max_tokens'  => 90,
        'stream'      => true,
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $groq_key],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$full, &$buf) {
        $buf .= $data;
        while (($pos = strpos($buf, "\n")) !== false) {
            $line = trim(substr($buf, 0, $pos));
            $buf  = substr($buf, $pos + 1);
            if (!str_starts_with($line, 'data: ')) continue;
            $s = substr($line, 6);
            if ($s === '[DONE]') continue;
            $c = json_decode($s, true)['choices'][0]['delta']['content'] ?? '';
            if ($c !== '') { $full .= $c; sse(['chunk' => $c]); }
        }
        return strlen($data);
    },
]);
curl_exec($ch);

// Completion
$completada = false; $xp = 0;
if (strpos($full, '[LECCION_COMPLETADA]') !== false) {
    $completada = true;
    $full = trim(str_replace('[LECCION_COMPLETADA]', '', $full));

    $sc = mysqli_prepare($conexion, "SELECT COUNT(*) as t FROM ingles_conversaciones WHERE leccion_id=? AND rol='user'");
    mysqli_stmt_bind_param($sc, 'i', $leccion_id);
    mysqli_stmt_execute($sc);
    $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($sc))['t'] ?? 0;
    $xp  = max(20, $cnt * 5);
    $now = date('Y-m-d H:i:s');

    $sd = mysqli_prepare($conexion, "UPDATE ingles_lecciones SET completada=1, xp_ganados=?, mensajes=?, completed_at=? WHERE id=?");
    mysqli_stmt_bind_param($sd, 'iisi', $xp, $cnt, $now, $leccion_id);
    mysqli_stmt_execute($sd);

    $hoy = date('Y-m-d');
    $sx = mysqli_prepare($conexion, "INSERT INTO idiomas_nivel (estudiante_id, xp_total, nivel_actual, quiz_completado, ultima_sesion) VALUES (?,?,'A1',1,?) ON DUPLICATE KEY UPDATE xp_total=xp_total+?, ultima_sesion=?");
    mysqli_stmt_bind_param($sx, 'isisi', $est_id, $xp, $hoy, $xp, $hoy);
    mysqli_stmt_execute($sx);
}

// Save GUS response
if ($full) {
    $sa = mysqli_prepare($conexion, "INSERT INTO ingles_conversaciones (estudiante_id, rol, mensaje, leccion_id) VALUES (?,?,?,?)");
    $rol_a = 'assistant';
    mysqli_stmt_bind_param($sa, 'issi', $est_id, $rol_a, $full, $leccion_id);
    mysqli_stmt_execute($sa);
}

sse(['done' => true, 'completada' => $completada, 'xp' => $xp, 'full' => $full]);
