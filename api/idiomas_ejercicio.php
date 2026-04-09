<?php
// ============================================================
// INTEP INGLÉS — Endpoint Gemini
// Genera ejercicios de inglés y evalúa respuestas del quiz
// ============================================================

// Suprimir cualquier output previo de config.php (headers, etc.)
ob_start();
require_once '../config.php';
ob_clean();
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

// Solo estudiantes autenticados
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$est_id = (int)$_SESSION['estudiante_id'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Leer API key de Groq
$groq_key = $_ENV['GROQ_API_KEY']
          ?? getenv('GROQ_API_KEY')
          ?: (class_exists('Config') ? Config::get('GROQ_API_KEY','') : '');

// ── Helper: llamar a Groq (OpenAI-compatible, ultra rápido, sin restricción regional) ──
function llamar_gemini(string $prompt, string $key): ?array {
    if (empty($key)) return null;

    $url  = "https://api.groq.com/openai/v1/chat/completions";
    $body = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => [
            ['role' => 'system', 'content' => 'Eres un profesor experto de inglés. Responde ÚNICAMENTE con JSON válido, sin bloques markdown, sin texto adicional.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature' => 0.7,
        'max_tokens'  => 700,
        'stream'      => false,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$raw) return null;

    $resp = json_decode($raw, true);
    $text = $resp['choices'][0]['message']['content'] ?? '';
    $text = preg_replace('/```json\s*|\s*```/s', '', trim($text));
    $text = trim($text);
    return json_decode($text, true);
}

// ── Helper: obtener nivel del estudiante ────────────────
function get_nivel(int $est_id, $db): string {
    $st = mysqli_prepare($db, "SELECT nivel_actual FROM idiomas_nivel WHERE estudiante_id = ?");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    return $r['nivel_actual'] ?? 'A1';
}

// ── Helper: últimos temas usados (evitar repetición) ────
function get_temas_recientes(int $est_id, $db): string {
    $st = mysqli_prepare($db, "SELECT tipo_ejercicio, pregunta FROM idiomas_sesiones WHERE estudiante_id = ? AND es_quiz_nivel = 0 ORDER BY created_at DESC LIMIT 10");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    if (empty($rows)) return 'ninguno';
    return implode('; ', array_map(fn($r) => substr($r['pregunta'], 0, 60), $rows));
}

// ============================================================
// ACCIÓN: generar ejercicio
// ============================================================
if ($accion === 'generar') {
    $nivel  = get_nivel($est_id, $conexion);
    $temas  = get_temas_recientes($est_id, $conexion);
    // A1/A2: sin corrige_error (demasiado difícil), más vocabulario y traducción simple
    if (in_array($nivel, ['A1','A2'])) {
        $tipos = ['vocabulario','vocabulario','fill_blank','multiple_choice','traduccion'];
    } else {
        $tipos = ['fill_blank','multiple_choice','traduccion','corrige_error','vocabulario'];
    }
    $tipo = $tipos[array_rand($tipos)];

    $desc_tipos = [
        'fill_blank'       => 'fill-in-the-blank: una frase MUY corta con un espacio en blanco y 4 opciones (A/B/C/D). Ejemplo: "I ___ a student. A. am  B. is  C. are  D. be"',
        'multiple_choice'  => 'opción múltiple: una pregunta simple de vocabulario o gramática con 4 opciones (A/B/C/D)',
        'traduccion'       => 'traducción: una frase CORTA y simple en español, el estudiante escribe en inglés',
        'corrige_error'    => 'corrige el error: una frase en inglés con un error gramatical, 4 opciones de corrección (A/B/C/D)',
        'vocabulario'      => 'vocabulario: mostrar UNA palabra en español y elegir su traducción en inglés entre 4 opciones (A/B/C/D). Ejemplo: "¿Cómo se dice PERRO en inglés? A. cat  B. dog  C. bird  D. fish"',
    ];

    // Estadísticas del estudiante para ajustar dificultad
    $st_stats = mysqli_prepare($conexion,
        "SELECT COUNT(*) as total,
                SUM(es_correcto) as correctas,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as semana
         FROM idiomas_sesiones WHERE estudiante_id = ? AND es_quiz_nivel = 0");
    mysqli_stmt_bind_param($st_stats, 'i', $est_id);
    mysqli_stmt_execute($st_stats);
    $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($st_stats));
    $total_ej   = (int)($stats['total'] ?? 0);
    $precision  = $total_ej > 0 ? round(($stats['correctas'] / $total_ej) * 100) : 0;

    // Subnivel: ajusta dificultad dentro del nivel según precisión
    $subnivel = 'básico';
    if ($precision >= 80) $subnivel = 'avanzado';
    elseif ($precision >= 60) $subnivel = 'intermedio';

    // Contexto muy específico por nivel
    $contexto_nivel = [
        'A1' => 'PRINCIPIANTE ABSOLUTO con casi cero inglés. SOLO: colores (red/blue/green), números (1-20), animales (cat/dog/bird), objetos del salón (book/pen/table), saludos (hello/goodbye/thanks), verbo to be (am/is/are), familia (mother/father/sister/brother). Frases máximo 4 palabras. Opciones obvias donde la respuesta correcta se intuye fácil.',
        'A2' => 'Conoce palabras básicas. Introduce: cuerpo humano, comidas, colores de ropa, rutinas diarias (wake up/eat breakfast/go to school), present simple con he/she/they, preguntas cortas (What is this? / Do you like...?). Frases de máximo 6 palabras.',
        'B1' => 'Base sólida. Usa: present perfect, first conditional, modal verbs (should/must/can), phrasal verbs comunes, tiempo libre y trabajo.',
        'B2' => 'Intermedio alto. Usa: second/third conditional, reported speech, passive voice, collocations, expresiones idiomáticas, vocabulario formal.',
    ];

    $prompt = <<<PROMPT
Eres un profesor de inglés para estudiantes colombianos de instituto técnico.

PERFIL:
- Nivel: $nivel ($subnivel)
- Ejercicios hechos: $total_ej | Precisión: $precision%
- {$contexto_nivel[$nivel]}

TAREA: Genera UN ejercicio tipo: {$desc_tipos[$tipo]}

REGLAS CRÍTICAS:
- Nivel A1: vocabulario de UNA sola palabra, opciones muy diferentes entre sí (no confundibles)
- Nivel A1: NUNCA uses gramática compleja, contracciones raras ni phrasal verbs
- Nivel A2: frases simples del diario vivir
- Temas recientes a evitar: $temas
- Opciones: siempre 4, etiquetadas A B C D
- Explicación: en español, máximo 1 oración, muy simple
- instruccion: en español, amigable (ej: "¿Cómo se dice en inglés?", "Completa la frase", "Traduce")

Responde SOLO con JSON válido, sin markdown:

Opciones (fill_blank/multiple_choice/corrige_error/vocabulario):
{"tipo":"$tipo","nivel":"$nivel","instruccion":"...","pregunta":"...","traduccion_ayuda":"...o null","opciones":["A. ...","B. ...","C. ...","D. ..."],"correcta":"A","respuesta_texto":"...","explicacion":"..."}

Traducción:
{"tipo":"traduccion","nivel":"$nivel","instruccion":"Escribe en inglés:","pregunta":"frase simple en español","traduccion_ayuda":null,"opciones":[],"correcta":null,"respuesta_texto":"respuesta en inglés","explicacion":"..."}
PROMPT;

    $ejercicio = llamar_gemini($prompt, $groq_key);

    if (!$ejercicio) {
        echo json_encode(['error' => 'No se pudo conectar con Gemini. Intenta de nuevo.']);
        exit;
    }

    echo json_encode(['ok' => true, 'ejercicio' => $ejercicio]);
    exit;
}

// ============================================================
// ACCIÓN: evaluar respuesta (para traducción — IA corrige)
// ============================================================
if ($accion === 'evaluar_traduccion') {
    $pregunta    = trim($_POST['pregunta'] ?? '');
    $correcta    = trim($_POST['correcta'] ?? '');
    $respuesta   = trim($_POST['respuesta'] ?? '');
    $nivel       = get_nivel($est_id, $conexion);

    if (!$respuesta || !$correcta) {
        echo json_encode(['error' => 'Datos incompletos']); exit;
    }

    $prompt = <<<PROMPT
Eres un profesor de inglés. Evalúa la respuesta de un estudiante de nivel $nivel.

Frase original (español): $pregunta
Traducción correcta: $correcta
Respuesta del estudiante: $respuesta

Determina si la respuesta del estudiante es correcta o aceptable.
Sé flexible con sinónimos válidos y pequeñas diferencias de puntuación.

Responde ÚNICAMENTE con JSON:
{
  "es_correcto": true,
  "explicacion": "breve feedback en español, máximo 2 oraciones"
}
PROMPT;

    $resultado = llamar_gemini($prompt, $groq_key);
    if (!$resultado) {
        // Fallback: comparación simple
        $resultado = [
            'es_correcto' => strtolower(trim($respuesta)) === strtolower(trim($correcta)),
            'explicacion' => 'La respuesta correcta es: ' . $correcta
        ];
    }

    echo json_encode(['ok' => true, 'resultado' => $resultado]);
    exit;
}

// ============================================================
// ACCIÓN: guardar resultado de ejercicio
// ============================================================
if ($accion === 'guardar') {
    $tipo       = $_POST['tipo'] ?? '';
    $nivel      = $_POST['nivel'] ?? 'A1';
    $pregunta   = $_POST['pregunta'] ?? '';
    $correcta   = $_POST['correcta'] ?? '';
    $dada       = $_POST['respuesta_dada'] ?? '';
    $es_ok      = (int)($_POST['es_correcto'] ?? 0);
    $explicacion= $_POST['explicacion'] ?? '';
    $es_quiz    = (int)($_POST['es_quiz'] ?? 0);

    // XP: correcto=10, incorrecto=2 (por intentar)
    $xp = $es_ok ? 10 : 2;

    // Insertar sesión
    $st = mysqli_prepare($conexion,
        "INSERT INTO idiomas_sesiones (estudiante_id, tipo_ejercicio, nivel, pregunta, respuesta_correcta, respuesta_dada, es_correcto, xp_ganado, explicacion, es_quiz_nivel)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $tipo_enum = in_array($tipo, ['fill_blank','multiple_choice','traduccion','corrige_error','vocabulario','dialogo']) ? $tipo : 'multiple_choice';
    $nivel_enum = in_array($nivel, ['A1','A2','B1','B2']) ? $nivel : 'A1';
    mysqli_stmt_bind_param($st, 'isssssiiis',
        $est_id, $tipo_enum, $nivel_enum, $pregunta, $correcta, $dada, $es_ok, $xp, $explicacion, $es_quiz);
    mysqli_stmt_execute($st);

    if (!$es_quiz) {
        // Actualizar XP y racha
        $hoy = date('Y-m-d');
        $st2 = mysqli_prepare($conexion, "SELECT id, xp_total, racha_actual, racha_maxima, ultima_sesion FROM idiomas_nivel WHERE estudiante_id = ?");
        mysqli_stmt_bind_param($st2, 'i', $est_id);
        mysqli_stmt_execute($st2);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));

        if ($row) {
            $nueva_racha = $row['racha_actual'];
            if ($row['ultima_sesion'] === $hoy) {
                // Ya practicó hoy — no sumar racha
            } elseif ($row['ultima_sesion'] === date('Y-m-d', strtotime('-1 day'))) {
                $nueva_racha++;
            } else {
                $nueva_racha = 1; // racha rota
            }
            $nuevo_xp   = $row['xp_total'] + $xp;
            $nueva_max  = max($row['racha_maxima'], $nueva_racha);

            // Calcular nivel por XP
            $nuevo_nivel = 'A1';
            if ($nuevo_xp >= 1200) $nuevo_nivel = 'B2';
            elseif ($nuevo_xp >= 700) $nuevo_nivel = 'B1';
            elseif ($nuevo_xp >= 300) $nuevo_nivel = 'A2';

            $st3 = mysqli_prepare($conexion,
                "UPDATE idiomas_nivel SET xp_total=?, racha_actual=?, racha_maxima=?, nivel_actual=?, ultima_sesion=? WHERE estudiante_id=?");
            mysqli_stmt_bind_param($st3, 'iiissi', $nuevo_xp, $nueva_racha, $nueva_max, $nuevo_nivel, $hoy, $est_id);
            mysqli_stmt_execute($st3);

            // Verificar logros
            verificar_logros($est_id, $nuevo_xp, $nueva_racha, $nuevo_nivel, $conexion);

            echo json_encode(['ok' => true, 'xp' => $nuevo_xp, 'racha' => $nueva_racha, 'nivel' => $nuevo_nivel]);
        } else {
            // Primera vez — crear registro
            $st_ins = mysqli_prepare($conexion,
                "INSERT INTO idiomas_nivel (estudiante_id, xp_total, racha_actual, racha_maxima, nivel_actual, ultima_sesion) VALUES (?,?,1,1,'A1',?)");
            mysqli_stmt_bind_param($st_ins, 'iis', $est_id, $xp, $hoy);
            mysqli_stmt_execute($st_ins);
            echo json_encode(['ok' => true, 'xp' => $xp, 'racha' => 1, 'nivel' => 'A1']);
        }
    } else {
        echo json_encode(['ok' => true]);
    }
    exit;
}

// ============================================================
// ACCIÓN: guardar apodo
// ============================================================
if ($accion === 'set_apodo') {
    $apodo = trim($_POST['apodo'] ?? '');
    $apodo = preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ _\-]/', '', $apodo);
    $apodo = substr($apodo, 0, 30);
    if (strlen($apodo) < 2) { echo json_encode(['error' => 'Apodo muy corto']); exit; }

    // Verificar si ya existe el registro
    $st = mysqli_prepare($conexion, "SELECT id FROM idiomas_nivel WHERE estudiante_id = ?");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

    if ($existe) {
        $st2 = mysqli_prepare($conexion, "UPDATE idiomas_nivel SET apodo=? WHERE estudiante_id=?");
        mysqli_stmt_bind_param($st2, 'si', $apodo, $est_id);
        mysqli_stmt_execute($st2);
    } else {
        $st2 = mysqli_prepare($conexion, "INSERT INTO idiomas_nivel (estudiante_id, apodo) VALUES (?,?)");
        mysqli_stmt_bind_param($st2, 'is', $est_id, $apodo);
        mysqli_stmt_execute($st2);
    }
    echo json_encode(['ok' => true, 'apodo' => $apodo]);
    exit;
}

// ============================================================
// ACCIÓN: guardar nivel del quiz
// ============================================================
if ($accion === 'set_nivel_quiz') {
    $nivel = $_POST['nivel'] ?? 'A1';
    if (!in_array($nivel, ['A1','A2','B1','B2'])) $nivel = 'A1';

    $st = mysqli_prepare($conexion, "SELECT id FROM idiomas_nivel WHERE estudiante_id = ?");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

    if ($existe) {
        $st2 = mysqli_prepare($conexion, "UPDATE idiomas_nivel SET nivel_actual=?, quiz_completado=1 WHERE estudiante_id=?");
        mysqli_stmt_bind_param($st2, 'si', $nivel, $est_id);
    } else {
        $st2 = mysqli_prepare($conexion, "INSERT INTO idiomas_nivel (estudiante_id, nivel_actual, quiz_completado) VALUES (?,?,1)");
        mysqli_stmt_bind_param($st2, 'is', $est_id, $nivel);
    }
    mysqli_stmt_execute($st2);
    echo json_encode(['ok' => true, 'nivel' => $nivel]);
    exit;
}

// quiz_pregunta eliminado — las preguntas van hardcodeadas en el JS (instantáneo)

// ============================================================
// ACCIÓN: guardar preferencia de ejercicios por sesión
// ============================================================
if ($accion === 'set_ejercicios_sesion') {
    $cantidad = (int)($_POST['cantidad'] ?? 15);
    if (!in_array($cantidad, [10, 15, 20])) $cantidad = 15;

    $st = mysqli_prepare($conexion, "UPDATE idiomas_nivel SET ejercicios_sesion=? WHERE estudiante_id=?");
    mysqli_stmt_bind_param($st, 'ii', $cantidad, $est_id);
    mysqli_stmt_execute($st);
    echo json_encode(['ok' => true, 'ejercicios_sesion' => $cantidad]);
    exit;
}

// ── Verificar y otorgar logros ──────────────────────────
function verificar_logros(int $est_id, int $xp, int $racha, string $nivel, $db): void {
    $logros = [
        ['key' => 'primer_ejercicio', 'nombre' => 'Primer paso',       'icon' => '🌟', 'cond' => $xp >= 10],
        ['key' => 'racha_7',          'nombre' => 'Racha de 7 días',    'icon' => '🔥', 'cond' => $racha >= 7],
        ['key' => 'racha_30',         'nombre' => 'Un mes seguido',     'icon' => '🏅', 'cond' => $racha >= 30],
        ['key' => 'nivel_a2',         'nombre' => 'Nivel A2 alcanzado', 'icon' => '📗', 'cond' => in_array($nivel, ['A2','B1','B2'])],
        ['key' => 'nivel_b1',         'nombre' => 'Nivel B1 alcanzado', 'icon' => '📘', 'cond' => in_array($nivel, ['B1','B2'])],
        ['key' => 'nivel_b2',         'nombre' => 'Nivel B2 alcanzado', 'icon' => '🏆', 'cond' => $nivel === 'B2'],
        ['key' => 'xp_500',           'nombre' => '500 XP acumulados',  'icon' => '⭐', 'cond' => $xp >= 500],
    ];

    foreach ($logros as $l) {
        if (!$l['cond']) continue;
        $st = mysqli_prepare($db,
            "INSERT IGNORE INTO idiomas_logros (estudiante_id, logro_key, logro_nombre, logro_icon) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($st, 'isss', $est_id, $l['key'], $l['nombre'], $l['icon']);
        mysqli_stmt_execute($st);
    }
}

echo json_encode(['error' => 'Acción no reconocida']);
