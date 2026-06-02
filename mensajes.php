<?php
require_once 'config.php';
require_once 'partials/icons.php';

// Solo estudiantes logueados
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: dashboard.php');
    exit;
}

// Auto-migración: asegurar que las tablas existen (idempotente)
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS mensajes_admin (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    asunto      VARCHAR(255) NOT NULL,
    contenido   TEXT NOT NULL,
    enviado_por INT NOT NULL,
    fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS mensajes_vistos (
    mensaje_id    INT NOT NULL,
    estudiante_id INT NOT NULL,
    visto_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (mensaje_id, estudiante_id),
    INDEX idx_estudiante (estudiante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Auto-migración: columnas del documento adjunto (idempotente)
@mysqli_query($conexion, "ALTER TABLE mensajes_admin ADD COLUMN IF NOT EXISTS adjunto_archivo VARCHAR(255) NULL");
@mysqli_query($conexion, "ALTER TABLE mensajes_admin ADD COLUMN IF NOT EXISTS adjunto_nombre VARCHAR(255) NULL");

$est_id = (int)($_SESSION['estudiante_id'] ?? 0);

// 1) Leer mensajes + estado de lectura del estudiante (ANTES de marcarlos)
$mensajes = [];
$stmt = mysqli_prepare($conexion,
    "SELECT m.id, m.asunto, m.contenido, m.fecha_envio,
            m.adjunto_archivo, m.adjunto_nombre, v.visto_en
     FROM mensajes_admin m
     LEFT JOIN mensajes_vistos v ON v.mensaje_id = m.id AND v.estudiante_id = ?
     ORDER BY m.fecha_envio DESC");
mysqli_stmt_bind_param($stmt, 'i', $est_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $mensajes[] = $row;
}

// Cuántos eran nuevos en esta visita (para el encabezado)
$no_leidos = count(array_filter($mensajes, fn($m) => $m['visto_en'] === null));

// 2) Marcar como vistos los que estaban sin leer (al abrir la bandeja)
if ($est_id > 0 && $no_leidos > 0) {
    $ins = mysqli_prepare($conexion,
        "INSERT IGNORE INTO mensajes_vistos (mensaje_id, estudiante_id) VALUES (?, ?)");
    foreach ($mensajes as $m) {
        if ($m['visto_en'] === null) {
            mysqli_stmt_bind_param($ins, 'ii', $m['id'], $est_id);
            mysqli_stmt_execute($ins);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mensajes — INTEP</title>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <link rel="stylesheet" href="/intep/css/estilos.css">
<style>
.msg-intro {
    color: #6b7280;
    font-size: 0.9rem;
    margin: 0 0 1.2rem;
}
.msg-list { display: flex; flex-direction: column; gap: 0.85rem; }
.msg-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.1rem 1.15rem 0.95rem;
    box-shadow: 0 1px 6px rgba(0,0,0,0.07);
    border-left: 4px solid #e5e7eb;
    transition: border-color .2s, background .2s;
    cursor: pointer;
}
.msg-card.nuevo {
    border-left-color: #059669;
    background: #f0fdf4;
}
.msg-card .asunto {
    font-weight: 700;
    font-size: 0.98rem;
    color: #111827;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.dot-nuevo {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #059669;
    flex-shrink: 0;
    display: inline-block;
}
.msg-card .preview {
    font-size: 0.88rem;
    color: #6b7280;
    line-height: 1.55;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    margin-bottom: 0.7rem;
}
.msg-card.expandido .preview {
    display: block;
    -webkit-line-clamp: unset;
    color: #374151;
    white-space: pre-wrap;
}
.msg-meta {
    font-size: 0.76rem;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.visto-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.74rem;
    font-weight: 600;
    color: #059669;
}
.msg-adjunto {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    padding: 0.45rem 0.85rem;
    border-radius: 9px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    margin-bottom: 0.7rem;
}
.msg-adjunto:active { background: #dbeafe; }
.msg-empty {
    text-align: center;
    padding: 3.5rem 1rem 2rem;
    color: #9ca3af;
}
.msg-empty svg { opacity: .35; margin-bottom: 1rem; }
.msg-empty p { margin: 0; font-size: 0.95rem; }
.titulo-badge {
    background: #059669;
    color: #fff;
    border-radius: 999px;
    padding: 0.12rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    margin-left: 0.5rem;
    vertical-align: middle;
}
</style>
</head>
<body>

    <div class="dashboard-header">
        <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
        <span class="usuario-info">💬 Mensajes</span>
        <a href="logout.php" class="btn-salir">Cerrar sesión</a>
    </div>

    <div class="dashboard-container">

        <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

        <h2 style="font-size:1.25rem;font-weight:800;color:#111827;margin:0.5rem 0 0.3rem;">
            Mensajes
            <?php if ($no_leidos > 0): ?>
                <span class="titulo-badge"><?= $no_leidos ?> nuevo<?= $no_leidos !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </h2>
        <p class="msg-intro">Comunicados y avisos enviados por la institución.</p>

        <?php if (empty($mensajes)): ?>
            <div class="msg-empty">
                <?= icon('mensajes', ['size' => 56]) ?>
                <p>Aún no tienes mensajes.</p>
                <p style="font-size:0.82rem;margin-top:0.4rem;color:#d1d5db;">
                    Cuando la institución envíe un comunicado aparecerá aquí.
                </p>
            </div>
        <?php else: ?>
            <div class="msg-list">
                <?php foreach ($mensajes as $m):
                    $esNuevo = ($m['visto_en'] === null); // estado original (antes de marcar)
                ?>
                <div class="msg-card<?= $esNuevo ? ' nuevo' : '' ?>" onclick="toggleMsg(this)">
                    <div class="asunto">
                        <?php if ($esNuevo): ?><span class="dot-nuevo"></span><?php endif; ?>
                        <?= htmlspecialchars($m['asunto']) ?>
                    </div>
                    <div class="preview"><?= htmlspecialchars($m['contenido']) ?></div>
                    <?php if (!empty($m['adjunto_archivo'])): ?>
                    <a href="/intep/descargar_mensaje.php?id=<?= (int)$m['id'] ?>" target="_blank" class="msg-adjunto" onclick="event.stopPropagation()">
                        📎 Ver documento<?= $m['adjunto_nombre'] ? ' · ' . htmlspecialchars($m['adjunto_nombre']) : '' ?>
                    </a>
                    <?php endif; ?>
                    <div class="msg-meta">
                        <span>🕒 <?= date('d/m/Y · H:i', strtotime($m['fecha_envio'])) ?></span>
                        <?php if ($esNuevo): ?>
                            <span class="visto-label">✓ Nuevo</span>
                        <?php else: ?>
                            <span class="visto-label">✓✓ Visto</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

<?php require_once 'partials/student_bottom_nav.php'; ?>

<script>
function toggleMsg(el) {
    el.classList.toggle('expandido');
    el.classList.remove('nuevo');
    var dot = el.querySelector('.dot-nuevo');
    if (dot) dot.style.display = 'none';
}
</script>
</body>
</html>
