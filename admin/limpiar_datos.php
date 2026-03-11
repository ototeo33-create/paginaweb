<?php
require_once '../config.php';

// ── Solo admin puede acceder ──────────────────────────────────────────────────
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// ── Hash de contraseña maestra (desde .env) ──────────────────────────────────
define('CLAVE_LIMPIEZA_HASH', Config::get('CLAVE_LIMPIEZA_HASH', ''));

// ── Estado de la sesión de limpieza ──────────────────────────────────────────
$autenticado  = isset($_SESSION['limpiar_auth']) && $_SESSION['limpiar_auth'] === true;
$error_clave  = '';
$resultado    = null;

// ── Verificar contraseña ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    // Paso 1: Autenticarse
    if ($_POST['accion'] === 'autenticar') {
        $clave_ingresada = $_POST['clave'] ?? '';
        if (CLAVE_LIMPIEZA_HASH && password_verify($clave_ingresada, CLAVE_LIMPIEZA_HASH)) {
            $_SESSION['limpiar_auth'] = true;
            $autenticado = true;
        } else {
            $error_clave = 'Contraseña incorrecta. Intenta de nuevo.';
        }
    }

    // Paso 2: Ejecutar limpieza (requiere auth + confirmación)
    if ($_POST['accion'] === 'ejecutar' && $autenticado) {
        $confirmacion = $_POST['confirmacion'] ?? '';
        if ($confirmacion === 'ELIMINAR TODO') {

            $tablas_borrar = ['notas', 'horarios', 'pagos', 'modulos', 'estudiantes'];
            $usuarios_prueba = "DELETE FROM usuarios WHERE rol != 'admin'";

            $errores = [];

            // Deshabilitar restricciones FK temporalmente
            mysqli_query($conexion, "SET FOREIGN_KEY_CHECKS = 0");

            foreach ($tablas_borrar as $tabla) {
                if (!mysqli_query($conexion, "TRUNCATE TABLE `$tabla`")) {
                    $errores[] = "Error al limpiar tabla '$tabla': " . mysqli_error($conexion);
                }
            }

            // Borrar usuarios no admin
            if (!mysqli_query($conexion, $usuarios_prueba)) {
                $errores[] = "Error al eliminar usuarios: " . mysqli_error($conexion);
            }

            // Restaurar FK
            mysqli_query($conexion, "SET FOREIGN_KEY_CHECKS = 1");

            // Cerrar sesión de limpieza
            unset($_SESSION['limpiar_auth']);

            $resultado = [
                'exito'   => empty($errores),
                'errores' => $errores,
            ];

        } else {
            $error_clave = 'Escribe exactamente ELIMINAR TODO para confirmar.';
        }
    }

    // Cerrar sesión de panel limpieza
    if ($_POST['accion'] === 'cancelar') {
        unset($_SESSION['limpiar_auth']);
        header('Location: ../dashboard.php');
        exit;
    }
}

// ── Obtener preview de lo que se va a borrar ──────────────────────────────────
$preview = [];
if ($autenticado && $resultado === null) {
    $tablas = ['estudiantes', 'usuarios', 'modulos', 'notas', 'horarios', 'pagos'];
    foreach ($tablas as $t) {
        $r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `$t`");
        if ($r) {
            $row = mysqli_fetch_assoc($r);
            $preview[$t] = (int)$row['total'];
        }
    }
    // Usuarios no-admin
    $r2 = mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios WHERE rol != 'admin'");
    $preview['usuarios_a_eliminar'] = $r2 ? (int)mysqli_fetch_assoc($r2)['total'] : 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiar Datos de Prueba – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        body { background: #F0FDF4; }

        .limpiar-wrapper {
            max-width: 680px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        /* ── Cabecera ── */
        .limpiar-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .limpiar-header .icon-big {
            font-size: 3rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        .limpiar-header h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #022C22;
            margin: 0 0 0.4rem;
        }
        .limpiar-header p {
            color: #888;
            font-size: 0.9rem;
        }

        /* ── Card ── */
        .limpiar-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
        }

        /* ── Advertencia ── */
        .alerta-danger {
            background: rgba(254, 242, 242, 0.8);
            border-left: 4px solid #EF4444;
            border-radius: 8px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #7F1D1D;
            line-height: 1.6;
        }
        .alerta-success {
            background: #F0FDF4;
            border-left: 4px solid #22C55E;
            border-radius: 8px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #14532D;
        }

        /* ── Formulario ── */
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #444;
            margin-bottom: 0.4rem;
        }
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        input:focus { border-color: #10B981; }
        .error-msg {
            color: #EF4444;
            font-size: 0.82rem;
            margin-top: 0.4rem;
        }

        /* ── Botones ── */
        .btn-group {
            display: flex;
            gap: 0.8rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .btn-danger {
            background: #EF4444;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-danger:hover { background: #DC2626; }
        .btn-secondary {
            background: #F3F4F6;
            color: #555;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-secondary:hover { background: #E5E7EB; }
        .btn-primary {
            background: #10B981;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #047857; }

        /* ── Preview tabla ── */
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.2rem 0;
            font-size: 0.9rem;
        }
        .preview-table th {
            text-align: left;
            padding: 0.6rem 0.8rem;
            background: #F9FAFB;
            color: #666;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #E5E7EB;
        }
        .preview-table td {
            padding: 0.7rem 0.8rem;
            border-bottom: 1px solid #F3F4F6;
            color: #333;
        }
        .preview-table td .badge {
            display: inline-block;
            background: #FEE2E2;
            color: #991B1B;
            padding: 0.15rem 0.6rem;
            border-radius: 99px;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .preview-table td .badge.cero {
            background: #F0FDF4;
            color: #166534;
        }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1px solid #F0F0F0;
            margin: 1.5rem 0;
        }

        .volver-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #10B981;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .volver-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="dashboard-header">
        <h1>INTEP</h1>
        <span class="usuario-info"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?> · Admin</span>
        <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
    </div>

    <div class="limpiar-wrapper">

        <div class="limpiar-header">
            <span class="icon-big">🗑️</span>
            <h1>Limpiar Datos de Prueba</h1>
            <p>Zona restringida — Acción irreversible</p>
        </div>

        <div class="limpiar-card">

            <?php if ($resultado !== null): ?>
                <!-- ── RESULTADO ── -->
                <?php if ($resultado['exito']): ?>
                    <div class="alerta-success">
                        ✅ <strong>Limpieza completada con éxito.</strong><br>
                        Todos los datos de prueba han sido eliminados. El sistema está listo para iniciar con usuarios reales.
                    </div>
                    <a href="../dashboard.php" class="btn-primary" style="display:inline-block;text-decoration:none;padding:0.75rem 1.5rem;border-radius:8px;">Ir al Dashboard</a>
                <?php else: ?>
                    <div class="alerta-danger">
                        ❌ <strong>Ocurrieron errores durante la limpieza:</strong><br><br>
                        <?php foreach ($resultado['errores'] as $e): ?>
                            • <?php echo htmlspecialchars($e); ?><br>
                        <?php endforeach; ?>
                    </div>
                    <a href="limpiar_datos.php" class="btn-secondary" style="display:inline-block;text-decoration:none;padding:0.75rem 1.5rem;border-radius:8px;">Reintentar</a>
                <?php endif; ?>

            <?php elseif (!$autenticado): ?>
                <!-- ── PASO 1: PEDIR CONTRASEÑA ── -->
                <div class="alerta-danger">
                    ⚠️ <strong>Atención:</strong> Esta acción eliminará <u>permanentemente</u> todos los usuarios, estudiantes, notas, módulos, horarios y pagos registrados hasta ahora. Esta operación <strong>no se puede deshacer</strong>. Solo úsala cuando vayas a poner el sistema en producción.
                </div>

                <form method="POST">
                    <input type="hidden" name="accion" value="autenticar">
                    <label for="clave">🔐 Contraseña de administrador</label>
                    <input type="password" id="clave" name="clave" placeholder="Ingresa la contraseña maestra" autocomplete="off" required>
                    <?php if ($error_clave): ?>
                        <p class="error-msg">❌ <?php echo htmlspecialchars($error_clave); ?></p>
                    <?php endif; ?>
                    <div class="btn-group">
                        <button type="submit" class="btn-danger">Verificar contraseña</button>
                        <a href="../dashboard.php" class="btn-secondary" style="text-decoration:none;display:flex;align-items:center;">Cancelar</a>
                    </div>
                </form>

            <?php else: ?>
                <!-- ── PASO 2: PREVIEW + CONFIRMACIÓN ── -->
                <p style="font-weight:700;color:#333;margin-bottom:0.5rem;">📋 Resumen de lo que se va a eliminar:</p>

                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Tabla / Datos</th>
                            <th>Registros actuales</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>👥 Estudiantes</td>
                            <td><span class="badge <?php echo $preview['estudiantes'] == 0 ? 'cero' : ''; ?>"><?php echo $preview['estudiantes']; ?></span></td>
                            <td>Se eliminan todos</td>
                        </tr>
                        <tr>
                            <td>🔑 Usuarios (no-admin)</td>
                            <td><span class="badge <?php echo $preview['usuarios_a_eliminar'] == 0 ? 'cero' : ''; ?>"><?php echo $preview['usuarios_a_eliminar']; ?></span></td>
                            <td>Se eliminan todos</td>
                        </tr>
                        <tr>
                            <td>📦 Módulos</td>
                            <td><span class="badge <?php echo $preview['modulos'] == 0 ? 'cero' : ''; ?>"><?php echo $preview['modulos']; ?></span></td>
                            <td>Se vacía la tabla</td>
                        </tr>
                        <tr>
                            <td>📊 Notas</td>
                            <td><span class="badge <?php echo $preview['notas'] == 0 ? 'cero' : ''; ?>"><?php echo $preview['notas']; ?></span></td>
                            <td>Se vacía la tabla</td>
                        </tr>
                        <tr>
                            <td>📅 Horarios</td>
                            <td><span class="badge <?php echo $preview['horarios'] == 0 ? 'cero' : ''; ?>"><?php echo $preview['horarios']; ?></span></td>
                            <td>Se vacía la tabla</td>
                        </tr>
                        <tr>
                            <td>💳 Pagos / Cartera</td>
                            <td><span class="badge <?php echo ($preview['pagos'] ?? 0) == 0 ? 'cero' : ''; ?>"><?php echo $preview['pagos'] ?? 0; ?></span></td>
                            <td>Se vacía la tabla</td>
                        </tr>
                    </tbody>
                </table>

                <div class="alerta-danger">
                    🚨 <strong>Última advertencia:</strong> Esta acción es <u>irreversible</u>. La cuenta de administrador <strong>se conservará</strong>. Todo lo demás será eliminado.
                </div>

                <hr class="divider">

                <form method="POST">
                    <input type="hidden" name="accion" value="ejecutar">
                    <label for="confirmacion">Para confirmar, escribe exactamente: <strong>ELIMINAR TODO</strong></label>
                    <input type="text" id="confirmacion" name="confirmacion" placeholder="ELIMINAR TODO" autocomplete="off" required>
                    <?php if ($error_clave): ?>
                        <p class="error-msg">❌ <?php echo htmlspecialchars($error_clave); ?></p>
                    <?php endif; ?>
                    <div class="btn-group">
                        <button type="submit" class="btn-danger">🗑️ Eliminar todo y empezar en limpio</button>
                        <button type="submit" name="accion" value="cancelar" class="btn-secondary">Cancelar</button>
                    </div>
                </form>

            <?php endif; ?>

        </div><!-- /.limpiar-card -->

        <?php if ($resultado === null): ?>
        <a href="../dashboard.php" class="volver-link">← Volver al Dashboard</a>
        <?php endif; ?>

    </div><!-- /.limpiar-wrapper -->

</body>
</html>