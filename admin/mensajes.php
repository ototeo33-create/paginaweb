<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Auto-migración: crear tablas si no existen
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

// Auto-migración: columnas para el documento adjunto (idempotente)
@mysqli_query($conexion, "ALTER TABLE mensajes_admin ADD COLUMN IF NOT EXISTS adjunto_archivo VARCHAR(255) NULL");
@mysqli_query($conexion, "ALTER TABLE mensajes_admin ADD COLUMN IF NOT EXISTS adjunto_nombre VARCHAR(255) NULL");

$aviso = '';

// ── Enviar mensaje ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $aviso = 'error|Token de seguridad inválido. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'enviar') {
            $asunto    = trim($_POST['asunto'] ?? '');
            $contenido = trim($_POST['contenido'] ?? '');
            if ($asunto === '' || $contenido === '') {
                $aviso = 'error|El asunto y el contenido son obligatorios.';
            } else {
                // ── Procesar documento adjunto (opcional) ──
                $adj_archivo = null;
                $adj_nombre  = null;
                $err_adj     = '';
                if (!empty($_FILES['adjunto']['name']) && ($_FILES['adjunto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $f = $_FILES['adjunto'];
                    $permitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    if ($f['error'] !== UPLOAD_ERR_OK) {
                        $err_adj = 'No se pudo subir el archivo. Intenta de nuevo.';
                    } elseif (!in_array($ext, $permitidas, true)) {
                        $err_adj = 'Tipo de archivo no permitido. Usa PDF, Word o imagen.';
                    } elseif ($f['size'] > 10 * 1024 * 1024) {
                        $err_adj = 'El documento supera el límite de 10 MB.';
                    } else {
                        $dir = __DIR__ . '/../uploads/mensajes';
                        if (!is_dir($dir)) @mkdir($dir, 0775, true);
                        $nombreDisco = 'msg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (move_uploaded_file($f['tmp_name'], $dir . '/' . $nombreDisco)) {
                            $adj_archivo = $nombreDisco;
                            $adj_nombre  = $f['name'];
                        } else {
                            $err_adj = 'No se pudo guardar el documento en el servidor.';
                        }
                    }
                }

                if ($err_adj !== '') {
                    $aviso = 'error|' . $err_adj;
                } else {
                    $admin_id = (int)$_SESSION['usuario_id'];
                    $stmt = mysqli_prepare($conexion,
                        "INSERT INTO mensajes_admin (asunto, contenido, enviado_por, adjunto_archivo, adjunto_nombre)
                         VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, 'ssiss', $asunto, $contenido, $admin_id, $adj_archivo, $adj_nombre);
                    if (mysqli_stmt_execute($stmt)) {
                        $aviso = 'success|✅ Mensaje enviado a todos los estudiantes' . ($adj_archivo ? ' con el documento adjunto.' : '.');
                    } else {
                        $aviso = 'error|Error al guardar el mensaje.';
                    }
                }
            }
        }

        if ($accion === 'eliminar') {
            $msg_id = (int)($_POST['mensaje_id'] ?? 0);
            if ($msg_id > 0) {
                mysqli_query($conexion, "DELETE FROM mensajes_vistos WHERE mensaje_id = $msg_id");
                $del = mysqli_prepare($conexion, "DELETE FROM mensajes_admin WHERE id = ?");
                mysqli_stmt_bind_param($del, 'i', $msg_id);
                mysqli_stmt_execute($del);
                $aviso = 'success|🗑️ Mensaje eliminado.';
            }
        }
    }
}

// ── Conteo total de estudiantes (denominador de "visto por X/Y") ──
$r_total = mysqli_query($conexion, "SELECT COUNT(*) AS n FROM estudiantes");
$total_est = (int)(mysqli_fetch_assoc($r_total)['n'] ?? 0);

// ── Obtener mensajes enviados con su conteo de vistos ───────
$mensajes = [];
$res = mysqli_query($conexion,
    "SELECT m.id, m.asunto, m.contenido, m.fecha_envio,
            m.adjunto_archivo, m.adjunto_nombre,
            COUNT(v.estudiante_id) AS vistos
     FROM mensajes_admin m
     LEFT JOIN mensajes_vistos v ON v.mensaje_id = m.id
     GROUP BY m.id
     ORDER BY m.fecha_envio DESC");
while ($row = mysqli_fetch_assoc($res)) {
    $mensajes[] = $row;
}

[$aviso_tipo, $aviso_texto] = $aviso ? explode('|', $aviso, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mensajes — Admin INTEP</title>
<link rel="stylesheet" href="/intep/css/estilos.css">
<style>
.msg-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 1.2rem 1.4rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.msg-card .msg-asunto {
    font-weight: 700;
    font-size: 1rem;
    color: #111827;
    margin-bottom: 0.35rem;
}
.msg-card .msg-contenido {
    color: #374151;
    white-space: pre-wrap;
    font-size: 0.92rem;
    line-height: 1.6;
    margin-bottom: 0.7rem;
}
.msg-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.78rem;
    color: #6b7280;
    flex-wrap: wrap;
}
.msg-visto-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.18rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #d1fae5;
    color: #065f46;
}
.msg-visto-badge.parcial {
    background: #fef3c7;
    color: #92400e;
}
.msg-visto-badge.ninguno {
    background: #f3f4f6;
    color: #6b7280;
}
.msg-adjunto {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    margin-bottom: 0.7rem;
}
.msg-adjunto:hover { background: #dbeafe; }
.compose-box {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.compose-box h2 {
    margin: 0 0 1rem;
    font-size: 1.05rem;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.form-field { margin-bottom: 0.9rem; }
.form-field label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.3rem;
}
.form-field input, .form-field textarea {
    width: 100%;
    padding: 0.6rem 0.8rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.92rem;
    font-family: inherit;
    color: #111827;
    resize: vertical;
    transition: border-color .2s;
    box-sizing: border-box;
}
.form-field input:focus, .form-field textarea:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5,150,105,0.12);
}
.btn-send {
    background: #059669;
    color: #fff;
    border: none;
    padding: 0.65rem 1.6rem;
    border-radius: 10px;
    font-size: 0.92rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s, transform .15s;
}
.btn-send:hover { background: #047857; }
.btn-send:active { transform: scale(.97); }
.btn-del {
    background: none;
    border: 1px solid #fca5a5;
    color: #dc2626;
    padding: 0.28rem 0.8rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
.btn-del:hover { background: #fee2e2; }
.aviso {
    padding: 0.75rem 1rem;
    border-radius: 10px;
    margin-bottom: 1.2rem;
    font-size: 0.9rem;
    font-weight: 600;
}
.aviso.success { background: #d1fae5; color: #065f46; }
.aviso.error   { background: #fee2e2; color: #991b1b; }
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #9ca3af;
    font-size: 0.95rem;
}
</style>
</head>
<body>
<?php require_once '../partials/header_admin.php'; ?>

<main class="main-content" style="max-width:760px;margin:0 auto;padding:2rem 1rem;">

    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.4rem;font-weight:800;color:#111827;margin:0 0 0.2rem;">
            💬 Mensajes a Estudiantes
        </h1>
        <p style="color:#6b7280;font-size:0.88rem;margin:0;">
            Los mensajes que envíes aquí llegarán a todos los estudiantes en su portal.
        </p>
    </div>

    <?php if ($aviso_texto): ?>
        <div class="aviso <?= $aviso_tipo ?>"><?= htmlspecialchars($aviso_texto) ?></div>
    <?php endif; ?>

    <!-- Formulario de redacción -->
    <div class="compose-box">
        <h2>✉️ Nuevo mensaje</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="accion" value="enviar">
            <div class="form-field">
                <label for="asunto">Asunto</label>
                <input type="text" id="asunto" name="asunto" maxlength="255"
                       placeholder="Ej: Inicio de clases junio 2026" required>
            </div>
            <div class="form-field">
                <label for="contenido">Mensaje</label>
                <textarea id="contenido" name="contenido" rows="6"
                          placeholder="Escribe aquí el contenido del mensaje..." required></textarea>
            </div>
            <div class="form-field">
                <label for="adjunto">📎 Documento oficial (opcional)</label>
                <input type="file" id="adjunto" name="adjunto" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <small style="display:block;margin-top:0.3rem;color:#9ca3af;font-size:0.76rem;">
                    PDF, Word o imagen · máx. 10 MB. Ej.: el acta o la circular en PDF.
                </small>
            </div>
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <button type="submit" class="btn-send">Enviar a todos los estudiantes</button>
                <span style="font-size:0.8rem;color:#6b7280;">
                    📊 <?= $total_est ?> estudiante<?= $total_est !== 1 ? 's' : '' ?> registrado<?= $total_est !== 1 ? 's' : '' ?>
                </span>
            </div>
        </form>
    </div>

    <!-- Mensajes enviados -->
    <h2 style="font-size:1rem;font-weight:700;color:#374151;margin:0 0 1rem;">
        📨 Mensajes enviados (<?= count($mensajes) ?>)
    </h2>

    <?php if (empty($mensajes)): ?>
        <div class="empty-state">
            Aún no has enviado ningún mensaje.<br>
            <span style="font-size:0.82rem;">Usa el formulario de arriba para enviar el primero.</span>
        </div>
    <?php else: ?>
        <?php foreach ($mensajes as $m):
            $vistos   = (int)$m['vistos'];
            $total    = $total_est;
            $badgeCls = $vistos === 0 ? 'ninguno' : ($total > 0 && $vistos >= $total ? '' : 'parcial');
            $badgeTxt = $vistos === 0
                ? '👁 Sin leer'
                : ($total > 0 && $vistos >= $total ? "✅ Visto por todos" : "👁 Visto por $vistos/$total");
        ?>
        <div class="msg-card">
            <div class="msg-asunto"><?= htmlspecialchars($m['asunto']) ?></div>
            <div class="msg-contenido"><?= htmlspecialchars($m['contenido']) ?></div>
            <?php if (!empty($m['adjunto_archivo'])): ?>
            <a href="/intep/descargar_mensaje.php?id=<?= (int)$m['id'] ?>" target="_blank" class="msg-adjunto">
                📎 <?= htmlspecialchars($m['adjunto_nombre'] ?: 'Documento adjunto') ?>
            </a>
            <?php endif; ?>
            <div class="msg-meta">
                <span>🕒 <?= date('d/m/Y · H:i', strtotime($m['fecha_envio'])) ?></span>
                <span class="msg-visto-badge <?= $badgeCls ?>"><?= $badgeTxt ?></span>
                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar este mensaje?')">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="mensaje_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="btn-del">🗑 Eliminar</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>
</body>
</html>
