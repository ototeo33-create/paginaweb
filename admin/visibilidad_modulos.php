<?php
require_once '../config.php';
require_once __DIR__ . '/../includes/modulos_visibilidad.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}
if ($_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

modulos_visibilidad_init($conexion);

$msg_ok  = $_SESSION['vm_ok']  ?? null;
$msg_err = $_SESSION['vm_err'] ?? null;
unset($_SESSION['vm_ok'], $_SESSION['vm_err']);

// ── POST: alternar o editar mensaje ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['vm_err'] = 'Token de seguridad inválido. Recarga la página.';
        header('Location: /intep/admin/visibilidad_modulos.php');
        exit;
    }

    $accion = $_POST['accion'] ?? '';
    $key    = $_POST['modulo_key'] ?? '';

    if ($accion === 'alternar' && $key) {
        $upd = mysqli_prepare($conexion,
            "UPDATE modulos_visibilidad SET habilitado = 1 - habilitado WHERE modulo_key = ?"
        );
        mysqli_stmt_bind_param($upd, 's', $key);
        if (mysqli_stmt_execute($upd)) {
            $_SESSION['vm_ok'] = 'Estado del módulo actualizado.';
        } else {
            $_SESSION['vm_err'] = 'No se pudo actualizar.';
        }
    } elseif ($accion === 'guardar_mensaje' && $key) {
        $mensaje = trim($_POST['mensaje'] ?? '');
        if ($mensaje === '') $mensaje = null;
        $upd = mysqli_prepare($conexion,
            "UPDATE modulos_visibilidad SET mensaje_bloqueo = ? WHERE modulo_key = ?"
        );
        mysqli_stmt_bind_param($upd, 'ss', $mensaje, $key);
        if (mysqli_stmt_execute($upd)) {
            $_SESSION['vm_ok'] = 'Mensaje guardado.';
        } else {
            $_SESSION['vm_err'] = 'No se pudo guardar el mensaje.';
        }
    }
    header('Location: /intep/admin/visibilidad_modulos.php');
    exit;
}

$csrf = csrf_token();

// Cargar lista
$modulos = [];
$r = mysqli_query($conexion, "SELECT * FROM modulos_visibilidad ORDER BY nombre");
while ($row = mysqli_fetch_assoc($r)) $modulos[] = $row;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visibilidad de Módulos — Admin</title>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        html, body { background: #f8f9fc; }

        .vm-container {
            max-width: 980px;
            margin: 0 auto;
            padding: 1.8rem 1.5rem 4rem;
        }
        .vm-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #10B981;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 1rem;
        }
        .vm-hero {
            background: linear-gradient(135deg, #7C3AED 0%, #A855F7 50%, #C084FC 100%);
            border-radius: 18px;
            padding: 1.8rem 2rem;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .vm-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -30px;
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }
        .vm-hero h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 0.3rem; position: relative; z-index: 1; }
        .vm-hero p { font-size: 0.9rem; opacity: 0.9; margin: 0; position: relative; z-index: 1; }

        .vm-alert-ok, .vm-alert-err {
            padding: 0.85rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .vm-alert-ok  { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .vm-alert-err { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        .info-banner {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 12px;
            padding: 1rem 1.3rem;
            margin-bottom: 1.5rem;
            font-size: 0.86rem;
            color: #1E40AF;
            line-height: 1.6;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .modulo-card {
            background: white;
            border-radius: 14px;
            border: 1px solid #E5E7EB;
            margin-bottom: 0.9rem;
            overflow: hidden;
            transition: all 0.2s;
        }
        .modulo-card.deshab {
            background: #FEF2F2;
            border-color: #FECACA;
        }

        .modulo-row {
            display: flex;
            align-items: center;
            padding: 1rem 1.3rem;
            gap: 1.2rem;
            flex-wrap: wrap;
        }
        .modulo-info { flex: 1; min-width: 240px; }
        .modulo-info h3 {
            margin: 0 0 3px;
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modulo-info p { margin: 0; font-size: 0.82rem; color: #6B7280; }
        .modulo-key {
            font-size: 0.7rem;
            color: #9CA3AF;
            background: #F3F4F6;
            padding: 1px 7px;
            border-radius: 4px;
            font-family: monospace;
        }

        /* Toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 56px;
            height: 30px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #CBD5E1;
            border-radius: 30px;
            transition: 0.3s;
        }
        .slider::before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 3px;
            top: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .switch input:checked + .slider { background: #10B981; }
        .switch input:checked + .slider::before { transform: translateX(26px); }

        .estado-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 70px;
            text-align: center;
        }
        .estado-on  { color: #059669; }
        .estado-off { color: #DC2626; }

        .modulo-mensaje {
            border-top: 1px solid #F3F4F6;
            padding: 0.9rem 1.3rem;
            background: #F9FAFB;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .modulo-mensaje input {
            flex: 1;
            min-width: 200px;
            padding: 8px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 0.85rem;
            background: white;
        }
        .modulo-mensaje input:focus {
            outline: none;
            border-color: #7C3AED;
            box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
        }
        .modulo-mensaje button {
            padding: 8px 16px;
            background: linear-gradient(135deg, #6D28D9, #7C3AED);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
        }
        .modulo-mensaje label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="vm-container">

    <a href="/intep/dashboard.php" class="vm-back">← Volver al panel</a>

    <div class="vm-hero">
        <h1>🎛️ Visibilidad de Módulos</h1>
        <p>Activa o desactiva módulos del portal para los estudiantes · Los admins siempre tienen acceso</p>
    </div>

    <?php if ($msg_ok): ?><div class="vm-alert-ok">✅ <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
    <?php if ($msg_err): ?><div class="vm-alert-err">⚠️ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

    <div class="info-banner">
        <div style="font-size:1.3rem">💡</div>
        <div>
            <strong>¿Cómo funciona?</strong> Cuando desactivas un módulo, los estudiantes ya no verán su tarjeta
            en el dashboard ni podrán acceder a la página directamente — verán un mensaje amigable.
            Útil para mantenimiento o mientras cargas información. <strong>Tú como admin sigues viendo y usando todo.</strong>
        </div>
    </div>

    <?php foreach ($modulos as $m): ?>
    <div class="modulo-card <?= $m['habilitado'] ? '' : 'deshab' ?>">
        <div class="modulo-row">
            <div class="modulo-info">
                <h3>
                    <?= htmlspecialchars($m['nombre']) ?>
                    <span class="modulo-key"><?= htmlspecialchars($m['modulo_key']) ?></span>
                </h3>
                <p><?= htmlspecialchars($m['descripcion'] ?? '') ?></p>
            </div>

            <div style="display:flex;align-items:center;gap:14px">
                <span class="estado-label <?= $m['habilitado'] ? 'estado-on' : 'estado-off' ?>">
                    <?= $m['habilitado'] ? '● Activo' : '○ Oculto' ?>
                </span>

                <form method="POST" action="/intep/admin/visibilidad_modulos.php" style="margin:0">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="accion" value="alternar">
                    <input type="hidden" name="modulo_key" value="<?= htmlspecialchars($m['modulo_key']) ?>">
                    <label class="switch">
                        <input type="checkbox" <?= $m['habilitado'] ? 'checked' : '' ?>
                               onchange="this.form.submit()">
                        <span class="slider"></span>
                    </label>
                </form>
            </div>
        </div>

        <div class="modulo-mensaje">
            <form method="POST" action="/intep/admin/visibilidad_modulos.php"
                  style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;width:100%">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="accion" value="guardar_mensaje">
                <input type="hidden" name="modulo_key" value="<?= htmlspecialchars($m['modulo_key']) ?>">
                <label>Mensaje al estudiante (opcional)</label>
                <input type="text" name="mensaje"
                       value="<?= htmlspecialchars($m['mensaje_bloqueo'] ?? '') ?>"
                       placeholder="Ej: Estamos actualizando la información, vuelve mañana.">
                <button type="submit">💾 Guardar</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

</div>
</body>
</html>
