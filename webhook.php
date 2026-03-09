<?php
/**
 * GitHub Webhook Receiver
 *
 * Expuesto via Cloudflare Tunnel desde Termux.
 * Valida la firma HMAC-SHA256 de GitHub y ejecuta deploy.sh.
 *
 * Configuración:
 *   1. Definir WEBHOOK_SECRET igual al secret configurado en GitHub → Settings → Webhooks
 *   2. Ajustar REPO_PATH a la ruta real del proyecto en Termux
 *   3. Ajustar DEPLOY_SCRIPT a la ruta del script deploy.sh
 */

define('WEBHOOK_SECRET', getenv('GITHUB_WEBHOOK_SECRET') ?: 'CAMBIAR_POR_TU_SECRET');
define('REPO_PATH',      getenv('REPO_PATH')             ?: '/data/data/com.termux/files/home/intep');
define('DEPLOY_SCRIPT',  REPO_PATH . '/deploy.sh');
define('LOG_FILE',       REPO_PATH . '/webhook.log');
define('ALLOWED_BRANCH', 'refs/heads/master');

// ── Utilidades ────────────────────────────────────────────────────────────────

function log_msg(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function respond(int $status, string $msg): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $msg]);
    exit;
}

// ── Solo acepta POST ──────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Method Not Allowed');
}

// ── Leer payload crudo (necesario antes de php://input) ──────────────────────

$raw_payload = file_get_contents('php://input');

if ($raw_payload === false || $raw_payload === '') {
    log_msg('ERROR: payload vacío');
    respond(400, 'Empty payload');
}

// ── Validar firma HMAC-SHA256 de GitHub ───────────────────────────────────────

$signature_header = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if ($signature_header === '') {
    log_msg('ERROR: cabecera X-Hub-Signature-256 ausente');
    respond(401, 'Missing signature header');
}

$expected = 'sha256=' . hash_hmac('sha256', $raw_payload, WEBHOOK_SECRET);

if (!hash_equals($expected, $signature_header)) {
    log_msg('ERROR: firma inválida — posible petición no autorizada');
    respond(403, 'Invalid signature');
}

// ── Parsear evento y rama ─────────────────────────────────────────────────────

$event   = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';
$payload = json_decode($raw_payload, true);

if ($event !== 'push') {
    log_msg("INFO: evento '$event' ignorado (solo se procesa 'push')");
    respond(200, "Event '$event' ignored");
}

$ref = $payload['ref'] ?? '';

if ($ref !== ALLOWED_BRANCH) {
    log_msg("INFO: push a '$ref' ignorado (solo se despliega desde '" . ALLOWED_BRANCH . "')");
    respond(200, "Branch '$ref' ignored");
}

// ── Disparar deployment ───────────────────────────────────────────────────────

$pusher = $payload['pusher']['name']  ?? 'unknown';
$commit = substr($payload['after'] ?? '', 0, 7);
log_msg("DEPLOY: push de '$pusher' — commit $commit — ejecutando deploy.sh");

if (!file_exists(DEPLOY_SCRIPT) || !is_executable(DEPLOY_SCRIPT)) {
    log_msg('ERROR: deploy.sh no encontrado o sin permisos de ejecución');
    respond(500, 'Deploy script not found or not executable');
}

// Ejecutar en background para no bloquear la respuesta a GitHub (timeout 10 s)
$cmd    = 'bash ' . escapeshellarg(DEPLOY_SCRIPT) . ' >> ' . escapeshellarg(LOG_FILE) . ' 2>&1 &';
$output = shell_exec($cmd);

log_msg("INFO: deploy.sh lanzado en background");
respond(200, 'Deployment triggered');
