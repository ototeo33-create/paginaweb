<?php
ob_start();
require_once '../config.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

$key = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: Config::get('GROQ_API_KEY','');

$result = [
    'key_cargada'  => !empty($key),
    'key_primeros' => substr($key, 0, 10) . '...',
    'curl_existe'  => function_exists('curl_init'),
];

if (!empty($key)) {
    $url  = "https://api.groq.com/openai/v1/chat/completions";
    $body = json_encode([
        'model'    => 'llama-3.3-70b-versatile',
        'messages' => [['role'=>'user','content'=>'Responde solo: OK']],
        'max_tokens' => 10,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.$key],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resp = json_decode($raw, true);
    $result['curl_error']   = $err;
    $result['http_code']    = $code;
    $result['respuesta_ia'] = $resp['choices'][0]['message']['content'] ?? ($raw ?: 'sin respuesta');
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
