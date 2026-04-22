<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ============================================================
// AUTO-MIGRACIÓN: crear tabla estudiante_modulo si no existe
// ============================================================
mysqli_query($conexion, "
    CREATE TABLE IF NOT EXISTS estudiante_modulo (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        estudiante_id      INT NOT NULL,
        programa_modulo_id INT NOT NULL,
        estado             VARCHAR(20) DEFAULT 'activo',
        created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (estudiante_id)      REFERENCES estudiantes(id) ON DELETE CASCADE,
        FOREIGN KEY (programa_modulo_id) REFERENCES programa_modulo(id) ON DELETE CASCADE,
        UNIQUE KEY uk_est_mod (estudiante_id, programa_modulo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$mensaje = '';

// ============================================================
// PROCESAR ACCIONES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'error|Token de seguridad invalido. Recarga la pagina.';
    } else {
        $accion = $_POST['accion'] ?? '';

        // --- ASIGNAR MÓDULO A ESTUDIANTE ---
        if ($accion === 'asignar_modulo') {
            $est_id  = (int)$_POST['estudiante_id'];
            $pm_id   = (int)$_POST['programa_modulo_id'];

            if (!$est_id || !$pm_id) {
                $mensaje = 'error|Selecciona un estudiante y un modulo.';
            } else {
                $stmt = mysqli_prepare($conexion,
                    "INSERT IGNORE INTO estudiante_modulo (estudiante_id, programa_modulo_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, 'ii', $est_id, $pm_id);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                    $mensaje = 'success|Modulo asignado correctamente.';
                } elseif (mysqli_stmt_affected_rows($stmt) === 0) {
                    $mensaje = 'error|El estudiante ya tiene ese modulo asignado.';
                } else {
                    $mensaje = 'error|Error al asignar el modulo.';
                }
            }

        // --- ASIGNAR TODOS LOS MÓDULOS DEL PROGRAMA DEL ESTUDIANTE ---
        } elseif ($accion === 'asignar_programa_completo') {
            $est_id = (int)$_POST['estudiante_id'];

            // Obtener programa del estudiante
            $stmt_prog = mysqli_prepare($conexion, "SELECT programa_id FROM estudiantes WHERE id = ?");
            mysqli_stmt_bind_param($stmt_prog, 'i', $est_id);
            mysqli_stmt_execute($stmt_prog);
            $row_prog = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_prog));
            $prog_id = $row_prog ? (int)$row_prog['programa_id'] : 0;

            if (!$prog_id) {
                $mensaje = 'error|El estudiante no tiene programa asignado.';
            } else {
                $res_mods = mysqli_prepare($conexion,
                    "SELECT id FROM programa_modulo WHERE programa_id = ? AND estado = 'activo'");
                mysqli_stmt_bind_param($res_mods, 'i', $prog_id);
                mysqli_stmt_execute($res_mods);
                $mods = mysqli_stmt_get_result($res_mods);
                $insertados = 0;
                while ($m = mysqli_fetch_assoc($mods)) {
                    $stmt_ins = mysqli_prepare($conexion,
                        "INSERT IGNORE INTO estudiante_modulo (estudiante_id, programa_modulo_id) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt_ins, 'ii', $est_id, $m['id']);
                    mysqli_stmt_execute($stmt_ins);
                    if (mysqli_stmt_affected_rows($stmt_ins) > 0) $insertados++;
                }
                $mensaje = 'success|Se asignaron ' . $insertados . ' modulos del programa al estudiante.';
            }

        // --- QUITAR MÓDULO A ESTUDIANTE ---
        } elseif ($accion === 'quitar_modulo') {
            $em_id = (int)$_POST['em_id'];
            $stmt = mysqli_prepare($conexion, "DELETE FROM estudiante_modulo WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $em_id);
            if (mysqli_stmt_execute($stmt)) {
                $mensaje = 'success|Modulo removido del estudiante.';
            } else {
                $mensaje = 'error|Error al quitar el modulo.';
            }

        // --- QUITAR TODOS LOS MÓDULOS DEL ESTUDIANTE ---
        } elseif ($accion === 'quitar_todos') {
            $est_id = (int)$_POST['estudiante_id'];
            $stmt = mysqli_prepare($conexion, "DELETE FROM estudiante_modulo WHERE estudiante_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $est_id);
            mysqli_stmt_execute($stmt);
            $mensaje = 'success|Se removieron todos los modulos del estudiante.';
        }
    }
}

// ============================================================
// OBTENER DATOS
// ============================================================

// Estudiantes activos
$estudiantes = [];
$res_est = mysqli_query($conexion,
    "SELECT e.id, e.nombre, e.documento, p.nombre AS programa
     FROM estudiantes e
     LEFT JOIN programas p ON e.programa_id = p.id
     WHERE e.estado = 'activo'
     ORDER BY e.nombre ASC");
while ($r = mysqli_fetch_assoc($res_est)) $estudiantes[] = $r;

// Estudiante seleccionado
$est_sel = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 0;
if (!$est_sel && !empty($estudiantes)) $est_sel = $estudiantes[0]['id'];

$estudiante_info = null;
foreach ($estudiantes as $e) {
    if ($e['id'] == $est_sel) { $estudiante_info = $e; break; }
}

// Módulos ya asignados al estudiante seleccionado
$modulos_asignados = [];
if ($est_sel) {
    $sql_asig = "SELECT em.id as em_id, em.estado as em_estado,
                        pm.id as pm_id, pm.bimestre, pm.orden, pm.tipo,
                        mf.nombre as modulo_nombre, mf.codigo,
                        p.nombre as programa_nombre,
                        u.username as docente_nombre
                 FROM estudiante_modulo em
                 JOIN programa_modulo pm ON em.programa_modulo_id = pm.id
                 JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                 JOIN programas p ON pm.programa_id = p.id
                 LEFT JOIN usuarios u ON pm.docente_id = u.id
                 WHERE em.estudiante_id = ?
                 ORDER BY pm.bimestre ASC, pm.orden ASC, mf.nombre ASC";
    $stmt_asig = mysqli_prepare($conexion, $sql_asig);
    mysqli_stmt_bind_param($stmt_asig, 'i', $est_sel);
    mysqli_stmt_execute($stmt_asig);
    $res_asig = mysqli_stmt_get_result($stmt_asig);
    while ($r = mysqli_fetch_assoc($res_asig)) $modulos_asignados[] = $r;
}

// IDs ya asignados (para filtrar el selector de agregar)
$pm_ids_asignados = array_column($modulos_asignados, 'pm_id');

// Todos los módulos disponibles (para el selector de agregar)
$todos_modulos = [];
$res_all = mysqli_query($conexion,
    "SELECT pm.id, pm.bimestre, pm.orden, pm.tipo,
            mf.nombre as modulo_nombre,
            p.nombre as programa_nombre,
            u.username as docente_nombre
     FROM programa_modulo pm
     JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
     JOIN programas p ON pm.programa_id = p.id
     LEFT JOIN usuarios u ON pm.docente_id = u.id
     WHERE pm.estado = 'activo'
     ORDER BY p.nombre ASC, pm.bimestre ASC, mf.nombre ASC");
while ($r = mysqli_fetch_assoc($res_all)) $todos_modulos[] = $r;

// Programas para el resumen de stats
$total_asignados = count($modulos_asignados);

// Parsear mensaje
$msg_parts = null;
if ($mensaje) $msg_parts = explode('|', $mensaje, 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulos por Estudiante – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1.4fr; gap: 1.5rem; }
        .card {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08), 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(16,185,129,0.1);
        }
        .card h3 {
            font-size: 1rem; font-weight: 700; margin-bottom: 1.2rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(16,185,129,0.15);
            color: #022C22;
        }
        .campo-admin { margin-bottom: 1rem; }
        .campo-admin label { display:block; font-size:0.8rem; font-weight:700; color:#666; margin-bottom:0.3rem; text-transform:uppercase; }
        .campo-admin select, .campo-admin input {
            width:100%; padding:0.7rem 0.9rem;
            border:2px solid rgba(16,185,129,0.2); border-radius:8px;
            font-size:0.9rem; outline:none; box-sizing:border-box;
            background:rgba(255,255,255,0.8);
        }
        .campo-admin select:focus, .campo-admin input:focus { border-color:#10B981; }
        .btn-crear {
            background: linear-gradient(135deg,#059669,#10B981);
            color:white; border:none; padding:0.8rem; border-radius:8px;
            font-weight:700; cursor:pointer; width:100%; font-size:0.95rem;
            margin-top:0.5rem; transition:all 0.3s;
        }
        .btn-crear:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(5,150,105,0.3); }
        .btn-secundario {
            background: linear-gradient(135deg,#0369a1,#0ea5e9);
            color:white; border:none; padding:0.7rem; border-radius:8px;
            font-weight:700; cursor:pointer; width:100%; font-size:0.88rem;
            margin-top:0.5rem; transition:all 0.3s;
        }
        .btn-secundario:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(3,105,161,0.3); }
        .btn-peligro {
            background: linear-gradient(135deg,#dc2626,#ef4444);
            color:white; border:none; padding:0.7rem; border-radius:8px;
            font-weight:700; cursor:pointer; width:100%; font-size:0.88rem;
            margin-top:0.3rem; transition:all 0.3s;
        }
        .btn-peligro:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(220,38,38,0.3); }
        .alerta-success { background:rgba(16,185,129,0.1); color:#065f46; padding:0.8rem 1rem; border-radius:8px; margin-bottom:1rem; border-left:4px solid #10b981; font-size:0.88rem; }
        .alerta-error   { background:rgba(239,68,68,0.1);  color:#991b1b; padding:0.8rem 1rem; border-radius:8px; margin-bottom:1rem; border-left:4px solid #ef4444; font-size:0.88rem; }
        .tipo-tag { padding:0.15rem 0.5rem; border-radius:4px; font-size:0.7rem; font-weight:700; text-transform:uppercase; }
        .tipo-especifico  { background:#fecaca; color:#991b1b; }
        .tipo-transversal { background:#fef08a; color:#854d0e; }
        .tipo-basico      { background:#bbf7d0; color:#166534; }
        .bimestre-tag { background:#022C22; color:#f59e0b; padding:0.2rem 0.6rem; border-radius:6px; font-size:0.75rem; font-weight:700; }
        .selector-top {
            background:rgba(255,255,255,0.75);
            backdrop-filter:blur(12px);
            border-radius:16px; padding:1.2rem 1.5rem;
            box-shadow:0 4px 20px rgba(5,150,105,0.08);
            margin-bottom:1.5rem;
            display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
            border:1px solid rgba(16,185,129,0.1);
        }
        .selector-top label { font-weight:700; font-size:0.88rem; color:#666; }
        .selector-top select {
            padding:0.6rem 1rem;
            border:2px solid rgba(16,185,129,0.2); border-radius:10px;
            outline:none; font-size:0.88rem; background:rgba(255,255,255,0.8);
            min-width:260px;
        }
        .selector-top select:focus { border-color:#10B981; }
        .info-estudiante {
            background: linear-gradient(135deg,#022C22,#064e3b);
            color:white; border-radius:12px; padding:1rem 1.5rem;
            margin-bottom:1.5rem;
            display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
        }
        .info-estudiante .nombre { font-size:1.1rem; font-weight:700; }
        .info-estudiante .detalle { font-size:0.85rem; opacity:0.8; }
        .info-estudiante .badge-total {
            background:#f59e0b; color:#422006;
            padding:0.3rem 0.8rem; border-radius:20px;
            font-weight:700; font-size:0.85rem; margin-left:auto;
        }
        table { width:100%; border-collapse:collapse; font-size:0.88rem; }
        table th { background:#f8fafc; padding:0.7rem 0.8rem; text-align:left; font-weight:700; color:#374151; font-size:0.8rem; text-transform:uppercase; border-bottom:2px solid #e5e7eb; }
        table td { padding:0.65rem 0.8rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        table tr:hover td { background:#f8fafc; }
        .btn-quitar { background:transparent; border:1px solid #ef4444; color:#ef4444; padding:0.25rem 0.6rem; border-radius:6px; cursor:pointer; font-size:0.75rem; font-weight:700; transition:all 0.2s; }
        .btn-quitar:hover { background:#ef4444; color:white; }
        .sin-modulos { text-align:center; padding:2.5rem; color:#aaa; font-size:0.9rem; }
        .buscador-modulo { width:100%; padding:0.7rem 0.9rem; border:2px solid rgba(16,185,129,0.2); border-radius:8px; font-size:0.88rem; outline:none; box-sizing:border-box; margin-bottom:0.8rem; }
        .buscador-modulo:focus { border-color:#10B981; }
        .stats-row { display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
        .stat-card { background:rgba(255,255,255,0.75); backdrop-filter:blur(12px); border-radius:12px; padding:1rem 1.5rem; border:1px solid rgba(16,185,129,0.1); text-align:center; flex:1; min-width:120px; }
        .stat-card .num { font-size:1.8rem; font-weight:800; color:#059669; }
        .stat-card .label { font-size:0.75rem; color:#666; text-transform:uppercase; font-weight:600; }
        @media(max-width:768px) {
            .grid-2 { grid-template-columns:1fr; }
            .info-estudiante { flex-direction:column; gap:0.5rem; }
            .info-estudiante .badge-total { margin-left:0; }
            .selector-top { flex-direction:column; align-items:flex-start; }
            .selector-top select { min-width:100%; }
        }
    </style>
</head>
<body data-rol="admin">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📚 Modulos por Estudiante</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesion</a>
</div>

<div class="dashboard-container">

    <a href="../dashboard.php" class="btn-volver">← Volver al inicio</a>

    <?php if ($msg_parts): ?>
        <div class="alerta-<?php echo $msg_parts[0]; ?>"><?php echo htmlspecialchars($msg_parts[1]); ?></div>
    <?php endif; ?>

    <!-- Selector de estudiante -->
    <div class="selector-top">
        <label>Estudiante:</label>
        <select onchange="window.location.href='modulos_estudiantes.php?estudiante_id='+this.value">
            <?php foreach ($estudiantes as $e): ?>
                <option value="<?php echo $e['id']; ?>" <?php echo $e['id'] == $est_sel ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre']); ?>
                    (<?php echo htmlspecialchars($e['documento']); ?> — <?php echo htmlspecialchars($e['programa'] ?? 'Sin programa'); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($estudiante_info): ?>

    <!-- Info del estudiante -->
    <div class="info-estudiante">
        <div>
            <div class="nombre"><?php echo htmlspecialchars($estudiante_info['nombre']); ?></div>
            <div class="detalle">
                Doc: <?php echo htmlspecialchars($estudiante_info['documento']); ?> &nbsp;|&nbsp;
                Programa: <?php echo htmlspecialchars($estudiante_info['programa'] ?? 'Sin programa'); ?>
            </div>
        </div>
        <div class="badge-total"><?php echo $total_asignados; ?> modulos asignados</div>
    </div>

    <!-- Estadísticas rápidas -->
    <?php
    $cnt_transversal = count(array_filter($modulos_asignados, fn($m) => $m['tipo'] === 'transversal'));
    $cnt_especifico  = count(array_filter($modulos_asignados, fn($m) => $m['tipo'] === 'especifico'));
    $cnt_basico      = count(array_filter($modulos_asignados, fn($m) => $m['tipo'] === 'basico'));
    ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?php echo $total_asignados; ?></div>
            <div class="label">Total modulos</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#854d0e;"><?php echo $cnt_transversal; ?></div>
            <div class="label">Transversales</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#991b1b;"><?php echo $cnt_especifico; ?></div>
            <div class="label">Especificos</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#166534;"><?php echo $cnt_basico; ?></div>
            <div class="label">Basicos</div>
        </div>
    </div>

    <div class="grid-2">

        <!-- Panel izquierdo: asignar módulo -->
        <div>
            <div class="card">
                <h3>➕ Asignar Modulo</h3>

                <!-- Asignar módulo individual -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="asignar_modulo">
                    <input type="hidden" name="estudiante_id" value="<?php echo $est_sel; ?>">

                    <div class="campo-admin">
                        <label>Buscar modulo</label>
                        <input type="text" class="buscador-modulo" id="buscador-asignar"
                               placeholder="Escribe nombre del modulo, programa o docente..."
                               oninput="filtrarSelectModulos()">
                    </div>

                    <div class="campo-admin">
                        <label>Selecciona modulo</label>
                        <select name="programa_modulo_id" id="select-modulo" size="8"
                                style="height:auto; min-height:200px; overflow-y:auto;">
                            <option value="">-- Selecciona un modulo --</option>
                            <?php foreach ($todos_modulos as $tm): ?>
                                <?php $ya = in_array($tm['id'], $pm_ids_asignados); ?>
                                <option value="<?php echo $tm['id']; ?>"
                                        data-label="<?php echo strtolower(htmlspecialchars($tm['modulo_nombre'] . ' ' . $tm['programa_nombre'] . ' ' . $tm['docente_nombre'])); ?>"
                                        <?php echo $ya ? 'disabled style="color:#aaa;"' : ''; ?>>
                                    <?php if ($tm['bimestre']): ?>
                                        [Bim <?php echo $tm['bimestre']; ?>]
                                    <?php else: ?>
                                        [Sin bim]
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($tm['modulo_nombre']); ?>
                                    — <?php echo htmlspecialchars($tm['programa_nombre']); ?>
                                    <?php if ($tm['docente_nombre']): ?>
                                        (<?php echo htmlspecialchars($tm['docente_nombre']); ?>)
                                    <?php endif; ?>
                                    <?php if ($tm['tipo'] === 'transversal'): ?> ★<?php endif; ?>
                                    <?php echo $ya ? ' ✓ ya asignado' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-crear">✅ Asignar modulo seleccionado</button>
                </form>

                <hr style="margin:1.5rem 0; border:none; border-top:1px solid #e5e7eb;">

                <!-- Asignar todos los módulos del programa -->
                <form method="POST" onsubmit="return confirm('¿Asignar automaticamente todos los modulos activos del programa del estudiante?')">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="asignar_programa_completo">
                    <input type="hidden" name="estudiante_id" value="<?php echo $est_sel; ?>">
                    <button type="submit" class="btn-secundario">
                        📋 Asignar todos los modulos de su programa
                    </button>
                </form>

                <!-- Quitar todos -->
                <?php if ($total_asignados > 0): ?>
                <form method="POST" onsubmit="return confirm('¿Quitar TODOS los modulos asignados a este estudiante?')">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="quitar_todos">
                    <input type="hidden" name="estudiante_id" value="<?php echo $est_sel; ?>">
                    <button type="submit" class="btn-peligro">
                        🗑 Quitar todos los modulos
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel derecho: módulos asignados -->
        <div>
            <div class="card">
                <h3>📚 Modulos asignados a <?php echo htmlspecialchars($estudiante_info['nombre']); ?></h3>

                <?php if (empty($modulos_asignados)): ?>
                    <div class="sin-modulos">
                        <p>⚠️ Este estudiante no tiene ningun modulo asignado.</p>
                        <p style="font-size:0.82rem;">Usa el panel izquierdo para asignarle modulos.</p>
                    </div>
                <?php else: ?>
                    <!-- Buscador en la tabla -->
                    <input type="text" class="buscador-modulo" id="buscador-tabla"
                           placeholder="Filtrar modulos asignados..."
                           oninput="filtrarTablaAsignados()">

                    <table>
                        <thead>
                            <tr>
                                <th>Bim</th>
                                <th>Modulo</th>
                                <th>Programa</th>
                                <th>Tipo</th>
                                <th>Docente</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-asignados">
                            <?php foreach ($modulos_asignados as $ma): ?>
                            <tr data-label="<?php echo strtolower(htmlspecialchars($ma['modulo_nombre'] . ' ' . $ma['programa_nombre'] . ' ' . $ma['docente_nombre'])); ?>">
                                <td>
                                    <?php if ($ma['bimestre']): ?>
                                        <span class="bimestre-tag"><?php echo $ma['bimestre']; ?></span>
                                    <?php else: ?>
                                        <span style="color:#aaa;font-size:0.78rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600; color:#022C22;">
                                    <?php echo htmlspecialchars($ma['modulo_nombre']); ?>
                                    <?php if ($ma['codigo']): ?>
                                        <br><span style="font-size:0.72rem;color:#888;"><?php echo htmlspecialchars($ma['codigo']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.82rem; color:#555;">
                                    <?php echo htmlspecialchars($ma['programa_nombre']); ?>
                                </td>
                                <td>
                                    <span class="tipo-tag tipo-<?php echo $ma['tipo']; ?>">
                                        <?php echo ucfirst($ma['tipo']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.82rem; color:#555;">
                                    <?php echo $ma['docente_nombre'] ? htmlspecialchars($ma['docente_nombre']) : '<span style="color:#aaa;">Sin docente</span>'; ?>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('¿Quitar este modulo del estudiante?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="quitar_modulo">
                                        <input type="hidden" name="em_id" value="<?php echo $ma['em_id']; ?>">
                                        <button type="submit" class="btn-quitar">✕</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /grid-2 -->

    <?php else: ?>
        <div class="card" style="text-align:center; padding:3rem;">
            <p style="color:#aaa; font-size:1rem;">No hay estudiantes activos registrados en el sistema.</p>
        </div>
    <?php endif; ?>

</div>

<script>
function filtrarSelectModulos() {
    const q = document.getElementById('buscador-asignar').value.toLowerCase();
    const opts = document.querySelectorAll('#select-modulo option');
    opts.forEach(opt => {
        if (!opt.value) return;
        const label = opt.dataset.label || opt.textContent.toLowerCase();
        opt.hidden = q && !label.includes(q);
    });
}

function filtrarTablaAsignados() {
    const q = document.getElementById('buscador-tabla').value.toLowerCase();
    const filas = document.querySelectorAll('#tbody-asignados tr');
    filas.forEach(fila => {
        const label = fila.dataset.label || '';
        fila.style.display = (!q || label.includes(q)) ? '' : 'none';
    });
}
</script>

</body>
</html>
