<?php
ini_set('display_errors', 0);
error_reporting(0);
// ============================================================
// INTEP — Endpoint TTS (Kokoro via microservicio Python)
// ============================================================
ob_start();
require_once '../config.php';
ob_clean();

// Solo usuarios autenticados
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403); exit;
}

$text = trim($_GET['text'] ?? '');
if (empty($text)) { http_response_code(400); exit; }

// Sanear texto: emojis, markdown, tokens internos
$text = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text);
$text = preg_replace('/[*_`#\[\]<>]/u', '', $text);
$text = str_replace('[LECCION_COMPLETADA]', '', $text);
$text = preg_replace('/\s+/', ' ', $text);
$text = trim(substr($text, 0, 500));

if (empty($text)) { http_response_code(400); exit; }

// Cache: si ya existe el WAV, servirlo directamente
$cache_dir  = dirname(__DIR__) . '/tts_cache/';
if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);

$hash       = md5($text);
$cache_file = $cache_dir . $hash . '.mp3';

if (!file_exists($cache_file) || filesize($cache_file) < 500) {
    // Llamar al microservicio Edge TTS en localhost:5001
    $ch = curl_init('http://127.0.0.1:5001/tts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['text' => $text]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $audio = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $audio && strlen($audio) > 500) {
        file_put_contents($cache_file, $audio);
    }
}

if (file_exists($cache_file) && filesize($cache_file) > 500) {
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . filesize($cache_file));
    header('Cache-Control: public, max-age=86400');
    header('Accept-Ranges: bytes');
    readfile($cache_file);
} else {
    // Kokoro no disponible — cliente usará Web Speech API como fallback
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'TTS no disponible']);
}
