<?php
// ============================================================
// Asistente Admin "Lina" — chat con tool calling (DeepSeek)
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/alertas_helper.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']); exit;
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo administradores pueden usar el asistente']); exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos invalidos']); exit;
}

if (empty($data['csrf_token']) || !verifyCsrfToken($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de seguridad invalido. Recarga la pagina.']);
    exit;
}

$mensaje   = trim((string)($data['mensaje'] ?? ''));
$historial = is_array($data['historial'] ?? null) ? $data['historial'] : [];

if ($mensaje === '') {
    echo json_encode(['error' => 'Mensaje vacio']); exit;
}

$admin_id = (int)$_SESSION['usuario_id'];
$api_key  = $_ENV['DEEPSEEK_API_KEY'] ?? getenv('DEEPSEEK_API_KEY') ?: Config::get('DEEPSEEK_API_KEY', '');
if (!$api_key) {
    echo json_encode(['error' => 'Falta DEEPSEEK_API_KEY en .env del servidor']); exit;
}

// ============================================================
// HERRAMIENTAS (tools) que la IA puede invocar
// ============================================================
$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'enviar_alerta_modulo',
            'description' => 'Dispara una alerta titilante en un modulo del estudiante (cartera, horarios o evaluacion). Usar cuando el admin pide enviar avisos. Puede dirigirse a todos los estudiantes activos o a una lista especifica.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'modulo' => ['type' => 'string', 'enum' => ['cartera','horarios','evaluacion'], 'description' => 'Modulo donde titila la alerta'],
                    'todos' => ['type' => 'boolean', 'description' => 'Si true, se envia a todos los estudiantes activos'],
                    'estudiante_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'IDs de estudiantes destino (solo si todos=false)'],
                    'mensaje' => ['type' => 'string', 'description' => 'Mensaje opcional, max 180 caracteres'],
                ],
                'required' => ['modulo','todos'],
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'buscar_estudiante',
            'description' => 'Busca estudiantes por nombre o documento. Devuelve id, nombre, documento, programa, email y estado. Usar antes de operaciones que requieran identificar a un estudiante.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'consulta' => ['type' => 'string', 'description' => 'Texto a buscar en nombre o documento'],
                ],
                'required' => ['consulta'],
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'ver_notas_estudiante',
            'description' => 'Devuelve las notas de un estudiante (por id) en todos los modulos.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'estudiante_id' => ['type' => 'integer'],
                ],
                'required' => ['estudiante_id'],
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'ver_cartera_estudiante',
            'description' => 'Devuelve la cartera (cobros y saldos) de un estudiante.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'estudiante_id' => ['type' => 'integer'],
                ],
                'required' => ['estudiante_id'],
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'listar_morosos',
            'description' => 'Lista estudiantes con cartera vencida (saldo > 0 y fecha de vencimiento pasada). Util para enviar alertas de pago masivas.',
            'parameters' => ['type' => 'object', 'properties' => new stdClass()],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'estadisticas_generales',
            'description' => 'Devuelve estadisticas del portal: total estudiantes activos, ingresos hoy/semana/mes, morosos.',
            'parameters' => ['type' => 'object', 'properties' => new stdClass()],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'abrir_pagina',
            'description' => 'Indica al frontend que abra una pagina del panel admin en una pestania nueva. Usar cuando el admin pide "abreme/muestrame X". Paginas validas: estudiantes, cartera, notas, horarios, modulos, evaluaciones, asistencia, materiales, ingles, links_virtuales, dashboard. Si se pide la cuenta de un estudiante especifico usar cartera_estudiante con estudiante_id.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'pagina' => ['type' => 'string', 'enum' => ['estudiantes','cartera','notas','horarios','modulos','evaluaciones','asistencia','materiales','ingles','links_virtuales','dashboard','cartera_estudiante','notas_estudiante']],
                    'estudiante_id' => ['type' => 'integer', 'description' => 'Solo para cartera_estudiante o notas_estudiante'],
                ],
                'required' => ['pagina'],
            ],
        ],
    ],
];

// ============================================================
// EJECUTOR DE TOOLS (cada nombre -> funcion)
// ============================================================
function tool_enviar_alerta_modulo(array $args, mysqli $conexion, int $admin_id): array {
    $modulo = $args['modulo'] ?? '';
    if (!in_array($modulo, ALERTAS_MODULOS, true)) {
        return ['error' => 'Modulo invalido'];
    }
    $mensaje = isset($args['mensaje']) ? mb_substr(trim((string)$args['mensaje']), 0, 180) : null;
    $todos   = !empty($args['todos']);
    $ids     = [];

    if ($todos) {
        $res = mysqli_query(
            $conexion,
            "SELECT e.id FROM estudiantes e
             JOIN usuarios u ON u.estudiante_id = e.id
             WHERE u.estado='activo' AND u.rol='estudiante'"
        );
        while ($res && $r = mysqli_fetch_assoc($res)) $ids[] = (int)$r['id'];
    } else {
        $raw_ids = $args['estudiante_ids'] ?? [];
        if (!is_array($raw_ids)) $raw_ids = [];
        foreach ($raw_ids as $v) { $v = (int)$v; if ($v > 0) $ids[] = $v; }
        $ids = array_values(array_unique($ids));
    }

    if (empty($ids)) return ['error' => 'No hay estudiantes destino'];

    $stmt_check = mysqli_prepare(
        $conexion,
        "SELECT 1 FROM alertas_estudiante WHERE estudiante_id=? AND modulo=? AND vista_en IS NULL LIMIT 1"
    );
    $stmt_ins = mysqli_prepare(
        $conexion,
        "INSERT INTO alertas_estudiante (estudiante_id, modulo, creado_por, mensaje) VALUES (?,?,?,?)"
    );
    if (!$stmt_check || !$stmt_ins) return ['error' => 'No se pudo preparar la consulta'];

    $creadas = 0; $ya_activas = 0;
    foreach ($ids as $eid) {
        mysqli_stmt_bind_param($stmt_check, 'is', $eid, $modulo);
        mysqli_stmt_execute($stmt_check);
        $r = mysqli_stmt_get_result($stmt_check)->fetch_assoc();
        if ($r) { $ya_activas++; continue; }
        mysqli_stmt_bind_param($stmt_ins, 'isis', $eid, $modulo, $admin_id, $mensaje);
        if (mysqli_stmt_execute($stmt_ins)) $creadas++;
    }
    mysqli_stmt_close($stmt_check);
    mysqli_stmt_close($stmt_ins);

    return [
        'ok' => true,
        'modulo' => $modulo,
        'creadas' => $creadas,
        'ya_activas' => $ya_activas,
        'total_destino' => count($ids),
        'mensaje_adjunto' => $mensaje,
    ];
}

function tool_buscar_estudiante(array $args, mysqli $conexion): array {
    $q = trim((string)($args['consulta'] ?? ''));
    if ($q === '') return ['error' => 'Consulta vacia'];
    $like = '%' . $q . '%';
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT e.id, e.nombre, e.documento, e.email, e.estado, p.nombre as programa
         FROM estudiantes e
         LEFT JOIN programas p ON p.id = e.programa_id
         WHERE e.nombre LIKE ? OR e.documento LIKE ?
         ORDER BY e.nombre LIMIT 15"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return ['resultados' => $rows, 'total' => count($rows)];
}

function tool_ver_notas_estudiante(array $args, mysqli $conexion): array {
    $eid = (int)($args['estudiante_id'] ?? 0);
    if ($eid <= 0) return ['error' => 'estudiante_id invalido'];

    $stmt = mysqli_prepare($conexion, "SELECT id, nombre, documento FROM estudiantes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $est = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$est) return ['error' => 'Estudiante no encontrado'];

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT n.nota_final, n.aprobado, m.nombre as modulo, mat.nombre as materia
         FROM notas n
         JOIN modulos m ON n.modulo_id = m.id
         LEFT JOIN materias mat ON mat.id = m.materia_id
         WHERE n.estudiante_id = ?
         ORDER BY mat.nombre, m.bimestre, m.orden"
    );
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $notas = [];
    while ($r = mysqli_fetch_assoc($res)) $notas[] = $r;

    return ['estudiante' => $est, 'notas' => $notas, 'total_notas' => count($notas)];
}

function tool_ver_cartera_estudiante(array $args, mysqli $conexion): array {
    $eid = (int)($args['estudiante_id'] ?? 0);
    if ($eid <= 0) return ['error' => 'estudiante_id invalido'];

    $stmt = mysqli_prepare($conexion, "SELECT id, nombre, documento FROM estudiantes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $est = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$est) return ['error' => 'Estudiante no encontrado'];

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT c.periodo, c.total, c.pagado, c.saldo, c.fecha_vencimiento, c.estado, cc.nombre as concepto
         FROM cobros c
         LEFT JOIN conceptos_cobro cc ON cc.id = c.concepto_id
         WHERE c.estudiante_id = ?
         ORDER BY c.fecha_vencimiento DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cobros = []; $saldo_total = 0; $vencidos = 0;
    while ($r = mysqli_fetch_assoc($res)) {
        $cobros[] = $r;
        $saldo_total += (float)$r['saldo'];
        if ($r['estado'] === 'vencido') $vencidos++;
    }
    return [
        'estudiante' => $est,
        'cobros' => $cobros,
        'saldo_total' => $saldo_total,
        'vencidos' => $vencidos,
    ];
}

function tool_listar_morosos(mysqli $conexion): array {
    $sql = "SELECT e.id, e.nombre, e.documento, SUM(c.saldo) as saldo_total, COUNT(c.id) as cobros_vencidos
            FROM cobros c
            JOIN estudiantes e ON e.id = c.estudiante_id
            WHERE c.saldo > 0 AND (c.estado = 'vencido' OR c.fecha_vencimiento < CURDATE())
            GROUP BY e.id, e.nombre, e.documento
            ORDER BY saldo_total DESC
            LIMIT 50";
    $res = mysqli_query($conexion, $sql);
    $rows = [];
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return ['morosos' => $rows, 'total' => count($rows)];
}

function tool_estadisticas_generales(mysqli $conexion): array {
    $stats = [];
    $r = mysqli_query($conexion, "SELECT COUNT(*) n FROM estudiantes WHERE estado='activo'");
    $stats['estudiantes_activos'] = (int)(mysqli_fetch_assoc($r)['n'] ?? 0);

    $r = mysqli_query($conexion, "SELECT COUNT(*) n FROM usuarios WHERE rol='estudiante' AND DATE(ultimo_login)=CURDATE()");
    $stats['ingresos_hoy'] = (int)(mysqli_fetch_assoc($r)['n'] ?? 0);

    $r = mysqli_query($conexion, "SELECT COUNT(*) n FROM usuarios WHERE rol='estudiante' AND ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['ingresos_semana'] = (int)(mysqli_fetch_assoc($r)['n'] ?? 0);

    $r = mysqli_query($conexion, "SELECT COUNT(*) n FROM usuarios WHERE rol='estudiante' AND ultimo_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['ingresos_mes'] = (int)(mysqli_fetch_assoc($r)['n'] ?? 0);

    $r = mysqli_query($conexion, "SELECT COUNT(DISTINCT estudiante_id) n FROM cobros WHERE saldo > 0 AND (estado='vencido' OR fecha_vencimiento < CURDATE())");
    $stats['estudiantes_morosos'] = (int)(mysqli_fetch_assoc($r)['n'] ?? 0);

    $r = mysqli_query($conexion, "SELECT IFNULL(SUM(saldo),0) s FROM cobros WHERE saldo > 0 AND (estado='vencido' OR fecha_vencimiento < CURDATE())");
    $stats['cartera_vencida_total'] = (float)(mysqli_fetch_assoc($r)['s'] ?? 0);

    return $stats;
}

function tool_abrir_pagina(array $args): array {
    $mapa = [
        'estudiantes'        => '/intep/admin/estudiantes.php',
        'cartera'            => '/intep/admin/cartera.php',
        'notas'              => '/intep/admin/ingresar_notas.php',
        'horarios'           => '/intep/admin/gestionar_horarios.php',
        'modulos'            => '/intep/admin/gestionar_modulos.php',
        'evaluaciones'       => '/intep/admin/eval_admin.php',
        'asistencia'         => '/intep/admin/asistencia.php',
        'materiales'         => '/intep/admin/materiales.php',
        'ingles'             => '/intep/admin/avance_ingles.php',
        'links_virtuales'    => '/intep/admin/links_virtuales.php',
        'dashboard'          => '/intep/admin/index.php',
        'cartera_estudiante' => '/intep/admin/cartera.php?vista=estado_cuenta&est_id=',
        'notas_estudiante'   => '/intep/admin/cartera.php?vista=estado_cuenta&est_id=',
    ];
    $p = $args['pagina'] ?? '';
    if (!isset($mapa[$p])) return ['error' => 'Pagina no reconocida'];
    $url = $mapa[$p];
    if ($p === 'cartera_estudiante' || $p === 'notas_estudiante') {
        $eid = (int)($args['estudiante_id'] ?? 0);
        if ($eid <= 0) return ['error' => 'Falta estudiante_id'];
        $url .= $eid;
    }
    return ['ok' => true, 'url' => $url, 'pagina' => $p];
}

function ejecutar_tool(string $name, array $args, mysqli $conexion, int $admin_id): array {
    switch ($name) {
        case 'enviar_alerta_modulo':    return tool_enviar_alerta_modulo($args, $conexion, $admin_id);
        case 'buscar_estudiante':       return tool_buscar_estudiante($args, $conexion);
        case 'ver_notas_estudiante':    return tool_ver_notas_estudiante($args, $conexion);
        case 'ver_cartera_estudiante':  return tool_ver_cartera_estudiante($args, $conexion);
        case 'listar_morosos':          return tool_listar_morosos($conexion);
        case 'estadisticas_generales':  return tool_estadisticas_generales($conexion);
        case 'abrir_pagina':            return tool_abrir_pagina($args);
        default:                        return ['error' => "Tool desconocido: $name"];
    }
}

// ============================================================
// LLAMADA A DEEPSEEK (API compatible OpenAI)
// ============================================================
function llamar_modelo(array $messages, array $tools, string $key): array {
    $url  = 'https://api.deepseek.com/chat/completions';
    $body = json_encode([
        'model'       => 'deepseek-chat',
        'messages'    => $messages,
        'tools'       => $tools,
        'tool_choice' => 'auto',
        'temperature' => 0.4,
        'max_tokens'  => 800,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$raw) return ['_error' => "cURL: $err"];
    $resp = json_decode($raw, true);
    if (!isset($resp['choices'][0]['message'])) {
        return ['_error' => "API ($code): " . substr($resp['error']['message'] ?? $raw, 0, 200)];
    }
    return $resp['choices'][0]['message'];
}

// ============================================================
// CONVERSACION (loop con tool calls)
// ============================================================
$system_prompt = <<<'PROMPT'
Eres "Lina", la asistente administrativa del portal INTEP. Hablas en espanol, breve y al grano.
El usuario es un administrador autenticado, asi que puedes ejecutar acciones (enviar alertas, consultar datos, abrir paginas) usando las herramientas disponibles.

REGLAS:
- Cuando el admin diga "envia/manda/dispara una alerta de X a todos", usa enviar_alerta_modulo con todos=true.
- Cuando diga "alerta de pago/cartera a los morosos", primero llama listar_morosos y luego enviar_alerta_modulo con esos IDs (modulo=cartera).
- Cuando diga "abreme/muestrame las notas/cartera de Juan Perez", primero busca con buscar_estudiante, y si hay un solo match usa abrir_pagina (notas_estudiante o cartera_estudiante). Si hay varios matches, lista los resultados y pregunta cual.
- Cuando diga "abre el modulo de X" o "llevame a X", usa abrir_pagina con la pagina correspondiente.
- Si te piden estadisticas/resumen, usa estadisticas_generales.
- SIEMPRE confirma al usuario lo que hiciste con un mensaje corto (1-3 frases). Si abriste una pagina, di "Abriendo X" para que sepa que se abrira en otra pestania.
- NO inventes IDs ni datos. Si necesitas un estudiante, busquelo primero.
- Si el admin pide algo destructivo o ambiguo (eliminar masivo, cambiar precios), pidele confirmacion explicita antes de ejecutar.
PROMPT;

// Construir historial: system + historial previo (user/assistant) + nuevo mensaje
$messages = [['role' => 'system', 'content' => $system_prompt]];
foreach ($historial as $m) {
    $rol = $m['role'] ?? '';
    $cnt = $m['content'] ?? '';
    if (in_array($rol, ['user','assistant'], true) && is_string($cnt) && $cnt !== '') {
        $messages[] = ['role' => $rol, 'content' => mb_substr($cnt, 0, 2000)];
    }
}
$messages[] = ['role' => 'user', 'content' => mb_substr($mensaje, 0, 2000)];

$acciones = []; // acciones para frontend (ej. abrir url)
$tools_usadas = [];

// Loop de tool calling (max 5 iteraciones)
for ($i = 0; $i < 5; $i++) {
    $msg = llamar_modelo($messages, $tools, $api_key);

    if (isset($msg['_error'])) {
        echo json_encode(['error' => 'Lina no respondio: ' . $msg['_error']]); exit;
    }

    $messages[] = $msg; // agregar respuesta del modelo

    // Si no hay tool_calls, devolver el contenido al usuario
    if (empty($msg['tool_calls'])) {
        $contenido = trim($msg['content'] ?? '');
        if ($contenido === '') $contenido = 'Listo.';
        echo json_encode([
            'ok'           => true,
            'respuesta'    => $contenido,
            'acciones'     => $acciones,
            'tools_usadas' => $tools_usadas,
            'csrf_token'   => csrf_token(),
        ]);
        exit;
    }

    // Ejecutar cada tool_call y devolver el resultado al modelo
    foreach ($msg['tool_calls'] as $call) {
        $name = $call['function']['name'] ?? '';
        $args_raw = $call['function']['arguments'] ?? '{}';
        $args = json_decode($args_raw, true);
        if (!is_array($args)) $args = [];

        $resultado = ejecutar_tool($name, $args, $conexion, $admin_id);
        $tools_usadas[] = ['tool' => $name, 'args' => $args, 'resultado' => $resultado];

        // Si la tool produjo una accion para el frontend (abrir URL), capturarla
        if ($name === 'abrir_pagina' && !empty($resultado['ok']) && !empty($resultado['url'])) {
            $acciones[] = ['type' => 'abrir', 'url' => $resultado['url']];
        }

        $messages[] = [
            'role'         => 'tool',
            'tool_call_id' => $call['id'] ?? '',
            'name'         => $name,
            'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
        ];
    }
}

// Si se agotaron las iteraciones
echo json_encode([
    'ok'           => true,
    'respuesta'    => 'Operacion compleja, intenta dividirla en pasos.',
    'acciones'     => $acciones,
    'tools_usadas' => $tools_usadas,
    'csrf_token'   => csrf_token(),
]);
