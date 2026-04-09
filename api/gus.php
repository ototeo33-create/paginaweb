<?php
// ============================================================
// My Teacher GUS — Endpoint conversacional con Groq
// Solo para estudiantes de programas de Inglés (IDs 16-19)
// ============================================================
ob_start();
require_once '../config.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']); exit;
}

$est_id = (int)$_SESSION['estudiante_id'];
$accion = $_POST['accion'] ?? '';

// Verificar que es estudiante de inglés
$st_prog = mysqli_prepare($conexion,
    "SELECT p.id, p.nombre FROM estudiantes e JOIN programas p ON e.programa_id = p.id WHERE e.id = ?");
mysqli_stmt_bind_param($st_prog, 'i', $est_id);
mysqli_stmt_execute($st_prog);
$prog = mysqli_fetch_assoc(mysqli_stmt_get_result($st_prog));
$es_ingles = in_array((int)($prog['id'] ?? 0), [16,17,18,19]);

if (!$es_ingles) {
    echo json_encode(['error' => 'Solo disponible para estudiantes de inglés']); exit;
}

// API key Groq
$groq_key = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: Config::get('GROQ_API_KEY','');

// Nivel del estudiante en el módulo de inglés
function get_nivel_estudiante(int $est_id, $db): string {
    $st = mysqli_prepare($db, "SELECT nivel_actual FROM idiomas_nivel WHERE estudiante_id = ?");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    return $r['nivel_actual'] ?? 'A1';
}

// Llamada a Groq con historial
// Retorna [content, error_msg]
function llamar_groq(array $messages, string $key): array {
    $url  = "https://api.groq.com/openai/v1/chat/completions";
    $body = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => $messages,
        'temperature' => 0.75,
        'max_tokens'  => 500,
        'stream'      => false,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.$key],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $raw  = curl_exec($ch);
    $cerr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$raw) return [null, "cURL error: $cerr"];
    $resp = json_decode($raw, true);
    $content = $resp['choices'][0]['message']['content'] ?? null;
    if ($content === null) {
        $api_err = $resp['error']['message'] ?? $raw;
        return [null, "API ($code): " . substr($api_err, 0, 120)];
    }
    return [$content, null];
}

// ============================================================
// ACCIÓN: iniciar o retomar lección
// ============================================================
if ($accion === 'iniciar') {
    $nivel = get_nivel_estudiante($est_id, $conexion);

    // ¿Hay lección activa sin completar?
    $st = mysqli_prepare($conexion,
        "SELECT id, tema FROM ingles_lecciones WHERE estudiante_id=? AND completada=0 ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $leccion_activa = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

    // Historial reciente (últimas 6 lecciones completadas) para evitar temas repetidos
    $st2 = mysqli_prepare($conexion,
        "SELECT tema FROM ingles_lecciones WHERE estudiante_id=? AND completada=1 ORDER BY created_at DESC LIMIT 6");
    mysqli_stmt_bind_param($st2, 'i', $est_id);
    mysqli_stmt_execute($st2);
    $temas_pasados = mysqli_fetch_all(mysqli_stmt_get_result($st2), MYSQLI_ASSOC);
    $temas_str = implode(', ', array_column($temas_pasados, 'tema')) ?: 'ninguno';

    // Última conversación para contexto
    $leccion_id = $leccion_activa['id'] ?? null;
    $historial  = [];
    if ($leccion_id) {
        $st3 = mysqli_prepare($conexion,
            "SELECT rol, mensaje FROM ingles_conversaciones WHERE leccion_id=? ORDER BY created_at ASC LIMIT 20");
        mysqli_stmt_bind_param($st3, 'i', $leccion_id);
        mysqli_stmt_execute($st3);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($st3), MYSQLI_ASSOC);
        foreach ($rows as $r) {
            $historial[] = ['role' => $r['rol'], 'content' => $r['mensaje']];
        }
    }

    // System prompt de GUS
    $system = <<<PROMPT
You are "My Teacher GUS" (Great Understanding System), a warm, encouraging and expert English teacher at INTEP institute in Colombia.

STUDENT PROFILE:
- English level: $nivel (CEFR)
- Program: {$prog['nombre']}
- Recent lesson topics (DO NOT repeat): $temas_str

YOUR PERSONALITY:
- Friendly, patient, slightly humorous
- You celebrate progress enthusiastically
- You correct mistakes gently and explain WHY in Spanish
- You always encourage, never make the student feel bad

YOUR RULES:
- Conduct the lesson MOSTLY in English (80%)
- Use Spanish ONLY to explain grammar rules or when student seems confused
- Keep lessons focused on ONE topic, 5-10 minutes long
- Ask questions to make the student practice speaking/writing
- After every student response: acknowledge it, gently correct if needed, then continue
- If student writes in Spanish, kindly ask them to try in English first

LEVEL GUIDELINES:
- A1: Very basic. Greetings, numbers, colors, family, simple present. Short sentences.
- A2: Daily routines, shopping, directions, past simple, comparatives.
- B1: Travel, work, present perfect, conditionals, opinions.
- B2: Complex topics, idioms, reported speech, formal/informal register.

LESSON FORMAT:
1. Greet the student warmly by name if known
2. Propose ONE specific lesson topic appropriate for their level
3. Teach through conversation — ask questions, don't just explain
4. After 8-12 exchanges, wrap up with a summary of what was practiced
5. End with: "Great lesson! Type 'done' when you're ready to finish."

If the student types 'done' or 'finish' or 'terminar', respond with a lesson summary and add the tag [LECCION_COMPLETADA] at the very end of your message.
PROMPT;

    $messages = [['role' => 'system', 'content' => $system]];

    if (!empty($historial)) {
        // Retomar lección activa
        $messages = array_merge($messages, $historial);
        echo json_encode([
            'ok'         => true,
            'leccion_id' => $leccion_id,
            'tema'       => $leccion_activa['tema'],
            'retomando'  => true,
            'historial'  => $historial,
        ]);
    } else {
        // Nueva lección — GUS saluda y propone tema
        $messages[] = ['role' => 'user', 'content' => 'Hello GUS, I\'m ready for my English lesson!'];
        [$respuesta, $err_groq] = llamar_groq($messages, $groq_key);
        if (!$respuesta) { echo json_encode(['error' => 'GUS no disponible: ' . ($err_groq ?: 'sin respuesta')]); exit; }

        // Extraer tema propuesto (primera línea o frase del saludo)
        $tema = 'English Conversation';
        if (preg_match("/(?:today|lesson|practice|work on|focus on)[^.!]*[:.]\s*[\"']?([A-Za-z\s&\/]+)[\"']?/i", $respuesta, $m)) {
            $tema = trim(substr($m[1], 0, 80));
        }

        // Crear lección en DB
        $st_ins = mysqli_prepare($conexion,
            "INSERT INTO ingles_lecciones (estudiante_id, tema, nivel) VALUES (?,?,?)");
        mysqli_stmt_bind_param($st_ins, 'iss', $est_id, $tema, $nivel);
        mysqli_stmt_execute($st_ins);
        $leccion_id = mysqli_insert_id($conexion);

        // Guardar saludo de GUS
        $st_msg = mysqli_prepare($conexion,
            "INSERT INTO ingles_conversaciones (estudiante_id, rol, mensaje, leccion_id) VALUES (?,?,?,?)");
        $rol_a = 'assistant';
        mysqli_stmt_bind_param($st_msg, 'issi', $est_id, $rol_a, $respuesta, $leccion_id);
        mysqli_stmt_execute($st_msg);

        echo json_encode([
            'ok'         => true,
            'leccion_id' => $leccion_id,
            'tema'       => $tema,
            'retomando'  => false,
            'mensaje'    => $respuesta,
        ]);
    }
    exit;
}

// ============================================================
// ACCIÓN: enviar mensaje a GUS
// ============================================================
if ($accion === 'mensaje') {
    $leccion_id = (int)($_POST['leccion_id'] ?? 0);
    $mensaje    = trim($_POST['mensaje'] ?? '');

    if (!$mensaje || !$leccion_id) { echo json_encode(['error' => 'Datos incompletos']); exit; }

    $nivel = get_nivel_estudiante($est_id, $conexion);

    // Verificar que la lección pertenece al estudiante
    $st_v = mysqli_prepare($conexion, "SELECT tema, completada FROM ingles_lecciones WHERE id=? AND estudiante_id=?");
    mysqli_stmt_bind_param($st_v, 'ii', $leccion_id, $est_id);
    mysqli_stmt_execute($st_v);
    $leccion = mysqli_fetch_assoc(mysqli_stmt_get_result($st_v));
    if (!$leccion || $leccion['completada']) { echo json_encode(['error' => 'Lección no válida']); exit; }

    // Cargar historial completo
    $st_h = mysqli_prepare($conexion,
        "SELECT rol, mensaje FROM ingles_conversaciones WHERE leccion_id=? ORDER BY created_at ASC LIMIT 30");
    mysqli_stmt_bind_param($st_h, 'i', $leccion_id);
    mysqli_stmt_execute($st_h);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st_h), MYSQLI_ASSOC);

    // System prompt
    $system = <<<PROMPT
You are "My Teacher GUS" (Great Understanding System), a warm English teacher at INTEP institute in Colombia.
Student level: $nivel (CEFR). Current lesson topic: {$leccion['tema']}.
Teach in English (80%), explain grammar corrections in Spanish.
Keep responses concise (2-4 sentences max) — this is a CONVERSATION, not a lecture.
Gently correct mistakes, celebrate correct answers.
If student types 'done', 'finish', or 'terminar': give a brief lesson summary and add [LECCION_COMPLETADA] at the very end.
PROMPT;

    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($rows as $r) {
        $messages[] = ['role' => $r['rol'], 'content' => $r['mensaje']];
    }
    $messages[] = ['role' => 'user', 'content' => $mensaje];

    // Guardar mensaje del estudiante
    $st_u = mysqli_prepare($conexion,
        "INSERT INTO ingles_conversaciones (estudiante_id, rol, mensaje, leccion_id) VALUES (?,?,?,?)");
    $rol_u = 'user';
    mysqli_stmt_bind_param($st_u, 'issi', $est_id, $rol_u, $mensaje, $leccion_id);
    mysqli_stmt_execute($st_u);

    // Llamar a Groq
    [$respuesta, $err_groq] = llamar_groq($messages, $groq_key);
    if (!$respuesta) { echo json_encode(['error' => 'GUS no responde: ' . ($err_groq ?: 'intenta de nuevo')]); exit; }

    // ¿Lección completada?
    $completada = false;
    if (strpos($respuesta, '[LECCION_COMPLETADA]') !== false) {
        $completada = true;
        $respuesta  = str_replace('[LECCION_COMPLETADA]', '', $respuesta);
        $respuesta  = trim($respuesta);

        // Contar mensajes y dar XP (5 XP por mensaje del estudiante, mín 20)
        $st_cnt = mysqli_prepare($conexion,
            "SELECT COUNT(*) as total FROM ingles_conversaciones WHERE leccion_id=? AND rol='user'");
        mysqli_stmt_bind_param($st_cnt, 'i', $leccion_id);
        mysqli_stmt_execute($st_cnt);
        $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($st_cnt))['total'] ?? 0;
        $xp  = max(20, $cnt * 5);

        // Marcar lección completa
        $now = date('Y-m-d H:i:s');
        $st_done = mysqli_prepare($conexion,
            "UPDATE ingles_lecciones SET completada=1, xp_ganados=?, mensajes=?, completed_at=? WHERE id=?");
        mysqli_stmt_bind_param($st_done, 'iisi', $xp, $cnt, $now, $leccion_id);
        mysqli_stmt_execute($st_done);

        // Sumar XP al perfil de inglés
        $hoy = date('Y-m-d');
        $st_xp = mysqli_prepare($conexion,
            "INSERT INTO idiomas_nivel (estudiante_id, xp_total, nivel_actual, quiz_completado, ultima_sesion)
             VALUES (?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE xp_total = xp_total + ?, ultima_sesion = ?");
        mysqli_stmt_bind_param($st_xp, 'isisiss', $est_id, $xp, 'A1', $hoy, $xp, $hoy);
        mysqli_stmt_execute($st_xp);
    }

    // Guardar respuesta de GUS
    $rol_a = 'assistant';
    $st_a = mysqli_prepare($conexion,
        "INSERT INTO ingles_conversaciones (estudiante_id, rol, mensaje, leccion_id) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($st_a, 'issi', $est_id, $rol_a, $respuesta, $leccion_id);
    mysqli_stmt_execute($st_a);

    echo json_encode([
        'ok'         => true,
        'mensaje'    => $respuesta,
        'completada' => $completada,
        'xp'         => $completada ? $xp : 0,
    ]);
    exit;
}

// ============================================================
// ACCIÓN: historial de lecciones
// ============================================================
if ($accion === 'historial') {
    $st = mysqli_prepare($conexion,
        "SELECT tema, nivel, completada, xp_ganados, mensajes, created_at
         FROM ingles_lecciones WHERE estudiante_id=? ORDER BY created_at DESC LIMIT 10");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $lecciones = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    echo json_encode(['ok' => true, 'lecciones' => $lecciones]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
