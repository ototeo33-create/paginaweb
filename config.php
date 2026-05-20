<?php
// ============================================
// CONFIGURACIÓN GENERAL — INTEP Portal
// ============================================

require_once __DIR__ . '/config_env.php';

// --- Codificación UTF-8 forzada ---
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
}

// --- Base de datos ---
define('DB_HOST', Config::get('DB_HOST', 'localhost'));
define('DB_USER', Config::get('DB_USER', 'root'));
define('DB_PASS', Config::get('DB_PASS', ''));
define('DB_NAME', Config::get('DB_NAME', 'intep_portal'));

// --- Tiempos de inactividad (en segundos) ---
// Admin: sin límite (0 = desactivado)
// Docente: sin límite (0 = desactivado) — pueden estar mucho tiempo ingresando notas
// Estudiante: 30 minutos = 1800 segundos
define('TIMEOUT_ADMIN', (int)Config::get('TIMEOUT_ADMIN', 0));
define('TIMEOUT_DOCENTE', (int)Config::get('TIMEOUT_DOCENTE', 0));
define('TIMEOUT_ESTUDIANTE', (int)Config::get('TIMEOUT_ESTUDIANTE', 1800));

// --- Configuración de seguridad ---
define('SESSION_LIFETIME', (int)Config::get('SESSION_LIFETIME', 7200));

// ============================================
// CONEXIÓN A BASE DE DATOS (MySQLi con prepared statements)
// ============================================
// Forzar UTF-8 en la salida HTTP
header('Content-Type: text/html; charset=utf-8');

$conexion = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conexion) {
    error_log("Error de conexión a BD: " . mysqli_connect_error());
    die("Error de conexión. Contacte al administrador.");
}

mysqli_set_charset($conexion, 'utf8mb4');

// Forzar charset UTF-8 en toda la conexión MySQL
mysqli_query($conexion, "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
mysqli_query($conexion, "SET CHARACTER_SET_RESULTS = 'utf8mb4'");
mysqli_query($conexion, "SET CHARACTER_SET_CLIENT = 'utf8mb4'");
mysqli_query($conexion, "SET CHARACTER_SET_CONNECTION = 'utf8mb4'");

// ============================================
// SESIÓN SEGURA
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',  // Strict bloqueaba cookies en WebViews/Telegram
    ]);
    session_start();
}

// Primera vez: iniciar sesión sin borrar la cookie anterior
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(false); // false = no borrar sesión vieja
    $_SESSION['initiated'] = true;
    $_SESSION['created_at'] = time();
}

// Regenerar ID cada 2 horas (no cada 15 min — causaba "No autorizado" en WebViews)
if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > 7200) {
    session_regenerate_id(false); // false = mantener datos mientras cookie se actualiza
    $_SESSION['created_at'] = time();
}

// ============================================
// FUNCIONES DE SEGURIDAD
// ============================================

/**
 * Verifica credenciales con hash seguro (solo bcrypt)
 */
function verificarPassword($password, $hash, $conexion) {
    // Solo aceptar hashes bcrypt válidos
    if (substr($hash, 0, 4) !== '$2y$' && substr($hash, 0, 2) !== '$2') {
        // Hash legacy detectado: no permitir login, forzar reset por admin
        return false;
    }
    return password_verify($password, $hash);
}

/**
 * Hashea una contraseña usando bcrypt
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifica si un hash necesita ser rehasheado
 */
function needsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Valida complejidad de contraseña
 * Retorna true si es válida, o un string con el mensaje de error
 */
function validarPassword($password) {
    if (strlen($password) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Z]/', $password)) return 'Debe contener al menos una letra mayúscula.';
    if (!preg_match('/[a-z]/', $password)) return 'Debe contener al menos una letra minúscula.';
    if (!preg_match('/[0-9]/', $password)) return 'Debe contener al menos un número.';
    return true;
}

/**
 * Limpia y sanitiza input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera token CSRF
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica token CSRF y lo regenera después de uso exitoso
 */
function verifyCsrfToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // Regenerar token después de uso exitoso para prevenir reutilización
        unset($_SESSION['csrf_token']);
        return true;
    }
    return false;
}

/**
 * Registra intento de login fallido
 */
function registrarIntentoFallido($username, $conexion) {
    // Verificar si existe la tabla
    $result = mysqli_query($conexion, "SHOW TABLES LIKE 'intentos_login'");
    if (mysqli_num_rows($result) === 0) return;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = mysqli_prepare($conexion, 
        "INSERT INTO intentos_login (username, ip, user_agent, fecha) VALUES (?, ?, ?, NOW())"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sss', $username, $ip, $userAgent);
        mysqli_stmt_execute($stmt);
    }
}

/**
 * Verifica si hay demasiados intentos de login
 */
function verificarIntentosFallidos($username, $conexion, $maxIntentos = 5, $minutos = 15) {
    // Verificar si existe la tabla
    $result = mysqli_query($conexion, "SHOW TABLES LIKE 'intentos_login'");
    if (mysqli_num_rows($result) === 0) return false;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = mysqli_prepare($conexion,
        "SELECT COUNT(*) as intentos FROM intentos_login 
         WHERE (username = ? OR ip = ?) 
         AND fecha > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param($stmt, 'ssi', $username, $ip, $minutos);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['intentos'] >= $maxIntentos;
}

/**
 * Limpia intentos de login antiguos
 */
function limpiarIntentosFallidos($conexion) {
    mysqli_query($conexion, "DELETE FROM intentos_login WHERE fecha < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

// ============================================
// VERIFICAR INACTIVIDAD (según rol)
// ============================================
if (isset($_SESSION['usuario_id'])) {
    $rol = $_SESSION['usuario_rol'] ?? 'estudiante';

    // Obtener timeout según rol
    switch ($rol) {
        case 'admin':
            $timeout = TIMEOUT_ADMIN;
            break;
        case 'docente':
            $timeout = TIMEOUT_DOCENTE;
            break;
        default:
            $timeout = TIMEOUT_ESTUDIANTE;
            break;
    }

    // Si timeout es 0, no se verifica inactividad (admin)
    if ($timeout > 0) {
        $ultimo_acceso = $_SESSION['ultimo_acceso'] ?? time();
        $inactivo = time() - $ultimo_acceso;

        if ($inactivo > $timeout) {
            session_destroy();
            header('Location: /intep/login.php?sesion=expirada');
            exit;
        }
    }

    // Siempre actualizar último acceso
    $_SESSION['ultimo_acceso'] = time();

    // Registrar actividad en BD cada 2 minutos para no saturar queries
    $ahora = time();
    if (!isset($_SESSION['_actividad_ts']) || ($ahora - $_SESSION['_actividad_ts']) >= 120) {
        $uid = (int)$_SESSION['usuario_id'];
        mysqli_query($conexion, "UPDATE usuarios SET ultima_actividad = NOW() WHERE id = $uid");
        $_SESSION['_actividad_ts'] = $ahora;
    }
}
