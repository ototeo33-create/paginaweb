<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }
if (!in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) { header('Location: ../dashboard.php'); exit; }

$mensaje = '';

// Programas
$programas = [];
$res_p = mysqli_query($conexion, "SELECT * FROM programas ORDER BY nombre ASC");
while ($p = mysqli_fetch_assoc($res_p)) $programas[] = $p;

$programa_id = isset($_GET['programa_id']) ? (int)$_GET['programa_id'] : (!empty($programas) ? $programas[0]['id'] : 0);

// Módulos del programa (desde programa_modulo)
$modulos = [];
if ($programa_id) {
    $q = "SELECT pm.id, mf.nombre FROM programa_modulo pm
          JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
          WHERE pm.programa_id = ? AND pm.estado = 'activo'
          ORDER BY pm.bimestre, pm.orden";
    $stmt = mysqli_prepare($conexion, $q);
    mysqli_stmt_bind_param($stmt, 'i', $programa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($m = mysqli_fetch_assoc($res)) $modulos[] = $m;
}

$materia_id = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : (!empty($modulos) ? $modulos[0]['id'] : 0);

// Bimestres
$bimestres = [];
$res_bim = mysqli_query($conexion, "SELECT * FROM bimestres WHERE estado = 'activo' ORDER BY numero ASC");
while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

$bimestre_id = isset($_GET['bimestre_id']) ? (int)$_GET['bimestre_id'] : (!empty($bimestres) ? $bimestres[0]['id'] : 0);
$bimestre_actual = null;
foreach ($bimestres as $b) { if ($b['id'] == $bimestre_id) { $bimestre_actual = $b; break; } }

$materia_nombre = '';
foreach ($modulos as $m) { if ($m['id'] == $materia_id) $materia_nombre = $m['nombre']; }

// Estudiantes activos del programa
$estudiantes = [];
if ($materia_id && $programa_id) {
    $q = "SELECT id, nombre, documento FROM estudiantes
          WHERE programa_id = ? AND estado = 'activo'
          ORDER BY nombre ASC";
    $stmt = mysqli_prepare($conexion, $q);
    mysqli_stmt_bind_param($stmt, 'i', $programa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($e = mysqli_fetch_assoc($res)) $estudiantes[] = $e;
}

// Generar fechas del bimestre agrupadas por semana
$semanas = [];
$todas_fechas = [];
if ($bimestre_actual && $materia_id) {
    // Obtener días de clase para este módulo en este programa
    $dias_clase = [];
    $q_d = "SELECT DISTINCT dia FROM horarios WHERE programa_modulo_id = ? AND programa_id = ?";
    $stmt_d = mysqli_prepare($conexion, $q_d);
    mysqli_stmt_bind_param($stmt_d, 'ii', $materia_id, $programa_id);
    mysqli_stmt_execute($stmt_d);
    $res_d = mysqli_stmt_get_result($stmt_d);
    while ($d = mysqli_fetch_assoc($res_d)) $dias_clase[] = $d['dia'];
    // Fallback: si no hay horarios con programa_modulo_id, asumir todos los días laborales
    if (empty($dias_clase)) {
        $dias_clase = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
    }

    $dias_map = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes'];
    $dias_cortos = ['Lunes'=>'LUN','Martes'=>'MAR','Miércoles'=>'MIÉ','Jueves'=>'JUE','Viernes'=>'VIE'];

    $inicio = new DateTime($bimestre_actual['fecha_inicio']);
    $fin = new DateTime($bimestre_actual['fecha_fin']);
    $fin->modify('+1 day');

    // Calcular inicio de semana (lunes)
    $semana_num = 1;
    $semana_inicio = clone $inicio;
    if ($semana_inicio->format('N') != 1) {
        $semana_inicio->modify('last monday');
    }

    $current = clone $inicio;
    while ($current < $fin) {
        $dia_es = $dias_map[$current->format('l')] ?? '';
        if (in_array($dia_es, $dias_clase)) {
            // Calcular semana
            $diff = $semana_inicio->diff($current);
            $sem = intval(floor($diff->days / 7)) + 1;
            if (!isset($semanas[$sem])) $semanas[$sem] = [];
            $semanas[$sem][] = [
                'fecha' => $current->format('Y-m-d'),
                'dia' => $dia_es,
                'dia_corto' => $dias_cortos[$dia_es] ?? $dia_es,
                'dia_num' => $current->format('d/m')
            ];
            $todas_fechas[] = $current->format('Y-m-d');
        }
        $current->modify('+1 day');
    }
}

// Obtener asistencias existentes
$asist_map = []; // [estudiante_id][fecha] => estado
if (!empty($estudiantes) && !empty($todas_fechas)) {
    $est_ids = implode(',', array_map(fn($e) => (int)$e['id'], $estudiantes));
    $q_a = "SELECT * FROM asistencias WHERE estudiante_id IN ($est_ids) AND programa_modulo_id = ? AND bimestre_id = ?";
    $stmt_a = mysqli_prepare($conexion, $q_a);
    mysqli_stmt_bind_param($stmt_a, 'ii', $materia_id, $bimestre_id);
    mysqli_stmt_execute($stmt_a);
    $res_a = mysqli_stmt_get_result($stmt_a);
    while ($a = mysqli_fetch_assoc($res_a)) {
        $asist_map[$a['estudiante_id']][$a['fecha']] = $a;
    }
}

// Procesar guardado masivo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'error|Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'guardar_planilla') {
            $mat = (int)$_POST['materia_id'];
            $bim = (int)$_POST['bimestre_id'];
            $reg_por = $_SESSION['usuario_id'];
            $datos = $_POST['asist'] ?? [];
            $obs_datos = $_POST['obs'] ?? [];
            $guardados = 0;

            $q = "INSERT INTO asistencias (estudiante_id, programa_modulo_id, bimestre_id, fecha, estado, observacion, registrado_por)
                  VALUES (?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE estado = VALUES(estado), observacion = VALUES(observacion), registrado_por = VALUES(registrado_por)";
            $stmt = mysqli_prepare($conexion, $q);

            foreach ($datos as $est_id => $fechas) {
                foreach ($fechas as $fecha => $estado) {
                    if (empty($estado)) continue;
                    $est_id_i = (int)$est_id;
                    $obs = trim($obs_datos[$est_id][$fecha] ?? '');
                    mysqli_stmt_bind_param($stmt, 'iiisssi', $est_id_i, $mat, $bim, $fecha, $estado, $obs, $reg_por);
                    mysqli_stmt_execute($stmt);
                    $guardados++;
                }
            }

            $prog = (int)$_POST['programa_id'];
            header("Location: asistencia.php?programa_id=$prog&materia_id=$mat&bimestre_id=$bim&msg=" . urlencode("success|✅ $guardados registros guardados correctamente."));
            exit;
        }
    }
}

if (isset($_GET['msg'])) $mensaje = $_GET['msg'];

// Calcular resumen por estudiante
$resumen_est = [];
foreach ($estudiantes as $e) {
    $total = 0; $pres = 0; $aus = 0;
    if (isset($asist_map[$e['id']])) {
        foreach ($asist_map[$e['id']] as $a) {
            $total++;
            if ($a['estado'] === 'presente' || $a['estado'] === 'excusa') $pres++;
            if ($a['estado'] === 'ausente') $aus++;
        }
    }
    $resumen_est[$e['id']] = [
        'total' => $total,
        'pct' => $total > 0 ? round($pres / $total * 100) : 0,
        'ausentes' => $aus
    ];
}

$total_fechas = count($todas_fechas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencia – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .filtros-bar {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.2rem;
            display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;
            border: 1px solid rgba(16,185,129,0.1);
        }
        .filtros-bar label { font-weight: 700; font-size: 0.82rem; color: #555; }
        .filtros-bar select { padding: 0.5rem 0.7rem; border: 2px solid rgba(16,185,129,0.2); border-radius: 8px; font-size: 0.85rem; outline: none; }
        .filtros-bar select:focus { border-color: #10B981; }

        .planilla-header {
            background: linear-gradient(135deg, #072918, #0d5a2a);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 1.2rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;
        }
        .planilla-header h2 { font-size: 1.1rem; margin: 0; }
        .planilla-header .info { font-size: 0.78rem; opacity: 0.7; }
        .planilla-header .info span { background: rgba(255,255,255,0.15); padding: 0.2rem 0.6rem; border-radius: 6px; margin-left: 0.3rem; }

        .planilla-wrap {
            background: white;
            border-radius: 0 0 16px 16px;
            overflow-x: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(16,185,129,0.1);
            border-top: none;
        }

        .planilla { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 800px; }
        .planilla th { background: #f0fdf4; padding: 0.4rem 0.3rem; text-align: center; font-size: 0.7rem; font-weight: 700; color: #065f46; border: 1px solid #e0f2e8; }
        .planilla th.semana-header { background: #059669; color: white; font-size: 0.72rem; letter-spacing: 1px; text-transform: uppercase; }
        .planilla th.dia-header { background: #d1fae5; font-size: 0.68rem; color: #065f46; }
        .planilla th.num-col { width: 30px; background: #f0fdf4; }
        .planilla th.nombre-col { min-width: 180px; text-align: left; padding-left: 0.6rem; background: #f0fdf4; }
        .planilla th.pct-col { width: 50px; background: #fef3c7; color: #92400e; }

        .planilla td { padding: 0.3rem; text-align: center; border: 1px solid #f0f0f0; vertical-align: middle; }
        .planilla td.num-cell { font-weight: 700; color: #888; font-size: 0.75rem; background: #fafafa; }
        .planilla td.nombre-cell { text-align: left; padding-left: 0.6rem; font-weight: 600; font-size: 0.82rem; color: #1a1a1a; background: #fafafa; white-space: nowrap; }
        .planilla td.pct-cell { font-weight: 900; font-size: 0.82rem; background: #fffbeb; }

        .planilla tr:hover td { background: rgba(16,185,129,0.04); }
        .planilla tr:hover td.num-cell,
        .planilla tr:hover td.nombre-cell { background: rgba(16,185,129,0.08); }

        /* Celda de asistencia */
        .asist-cell { position: relative; min-width: 38px; }
        .asist-btn {
            width: 32px; height: 28px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.15s;
            background: white;
        }
        .asist-btn:hover { transform: scale(1.1); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .asist-btn[data-estado=""] { background: #fff; color: #ccc; border-color: #e8e8e8; }
        .asist-btn[data-estado="presente"] { background: #d1fae5; color: #065f46; border-color: #10B981; }
        .asist-btn[data-estado="ausente"] { background: #fee2e2; color: #991b1b; border-color: #ef4444; }
        .asist-btn[data-estado="tardanza"] { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
        .asist-btn[data-estado="excusa"] { background: #dbeafe; color: #1e40af; border-color: #3b82f6; }

        .obs-icon {
            position: absolute; top: 1px; right: 1px;
            font-size: 0.55rem; cursor: pointer; opacity: 0.5;
        }
        .obs-icon:hover { opacity: 1; }
        .obs-icon.tiene { opacity: 0.9; color: #f59e0b; }

        .btn-guardar-all {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; border: none;
            padding: 0.85rem 2.5rem;
            border-radius: 10px;
            font-weight: 700; font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(5,150,105,0.3);
        }
        .btn-guardar-all:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(5,150,105,0.4); }

        .leyenda { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; font-size: 0.78rem; color: #666; }
        .leyenda-item { display: flex; align-items: center; gap: 0.3rem; }
        .leyenda-dot { width: 14px; height: 14px; border-radius: 4px; display: inline-block; }

        .alerta-success { background: rgba(16,185,129,0.1); color: #065f46; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #10b981; font-size: 0.88rem; }
        .alerta-error { background: rgba(239,68,68,0.1); color: #991b1b; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #ef4444; font-size: 0.88rem; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: #aaa; }
        .empty-state p { font-size: 0.9rem; margin-top: 0.5rem; }

        @media(max-width:768px) {
            .filtros-bar { flex-direction: column; align-items: stretch; }
            .filtros-bar select { width: 100%; }
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📋 Control de Asistencia</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="container" style="max-width:1200px;margin:1rem auto;padding:0 0.8rem;">

    <a href="../dashboard.php" class="btn-volver" style="display:inline-block;margin-bottom:1rem;font-size:0.82rem;color:#059669;text-decoration:none;font-weight:600;">← Volver al inicio</a>

    <!-- Filtros -->
    <div class="filtros-bar">
        <label>🎓 Programa:</label>
        <select onchange="location.href='asistencia.php?programa_id='+this.value">
            <?php foreach ($programas as $p): ?>
                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $programa_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>📚 Módulo:</label>
        <select onchange="location.href='asistencia.php?programa_id=<?php echo $programa_id; ?>&materia_id='+this.value+'&bimestre_id=<?php echo $bimestre_id; ?>'">
            <?php if (empty($modulos)): ?>
                <option>Sin módulos</option>
            <?php else: ?>
                <?php foreach ($modulos as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo $m['id'] == $materia_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label>📅 Bimestre:</label>
        <select onchange="location.href='asistencia.php?programa_id=<?php echo $programa_id; ?>&materia_id=<?php echo $materia_id; ?>&bimestre_id='+this.value">
            <?php foreach ($bimestres as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $bimestre_id ? 'selected' : ''; ?>>
                    Bimestre <?php echo $b['numero']; ?> — <?php echo date('d/m', strtotime($b['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($b['fecha_fin'])); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php
    if ($mensaje) {
        $parts = explode('|', $mensaje, 2);
        $tipo = $parts[0]; $texto = $parts[1] ?? $mensaje;
        echo '<div class="alerta-' . ($tipo === 'success' ? 'success' : 'error') . '">' . htmlspecialchars($texto) . '</div>';
    }
    ?>

    <?php if (!empty($estudiantes) && !empty($semanas)): ?>

    <form method="POST" id="form-planilla">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="accion" value="guardar_planilla">
        <input type="hidden" name="programa_id" value="<?php echo $programa_id; ?>">
        <input type="hidden" name="materia_id" value="<?php echo $materia_id; ?>">
        <input type="hidden" name="bimestre_id" value="<?php echo $bimestre_id; ?>">

        <!-- Planilla header -->
        <div class="planilla-header">
            <div>
                <h2>📋 CONTROL DE ASISTENCIA — BIMESTRE <?php echo $bimestre_actual['numero']; ?> (<?php echo $bimestre_actual['anio']; ?>)</h2>
                <div class="info" style="margin-top:0.4rem;">
                    Módulo: <span><?php echo htmlspecialchars($materia_nombre); ?></span>
                    Fechas: <span><?php echo date('d/m', strtotime($bimestre_actual['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($bimestre_actual['fecha_fin'])); ?></span>
                    Estudiantes: <span><?php echo count($estudiantes); ?></span>
                </div>
            </div>
            <div>
                <div class="leyenda" style="color:rgba(255,255,255,0.85);">
                    <div class="leyenda-item"><span class="leyenda-dot" style="background:#d1fae5;border:1px solid #10B981;"></span> <strong>P</strong> &mdash; Presente</div>
                    <div class="leyenda-item"><span class="leyenda-dot" style="background:#fee2e2;border:1px solid #ef4444;"></span> <strong>A</strong> &mdash; Ausente</div>
                    <div class="leyenda-item"><span class="leyenda-dot" style="background:#fef3c7;border:1px solid #f59e0b;"></span> <strong>T</strong> &mdash; Tardanza</div>
                    <div class="leyenda-item"><span class="leyenda-dot" style="background:#dbeafe;border:1px solid #3b82f6;"></span> <strong>E</strong> &mdash; Excusa</div>
                </div>
            </div>
        </div>

        <!-- Planilla tabla -->
        <div class="planilla-wrap">
            <table class="planilla">
                <thead>
                    <!-- Fila de semanas -->
                    <tr>
                        <th class="num-col" rowspan="2">N°</th>
                        <th class="nombre-col" rowspan="2">NOMBRE DE ESTUDIANTE</th>
                        <?php foreach ($semanas as $num => $dias): ?>
                            <th class="semana-header" colspan="<?php echo count($dias); ?>"><?php echo $num; ?>ª Semana</th>
                        <?php endforeach; ?>
                        <th class="pct-col" rowspan="2">%</th>
                    </tr>
                    <!-- Fila de días -->
                    <tr>
                        <?php foreach ($semanas as $dias): ?>
                            <?php foreach ($dias as $d): ?>
                                <th class="dia-header" title="<?php echo $d['dia'] . ' ' . $d['fecha']; ?>">
                                    <?php echo $d['dia_corto']; ?><br>
                                    <span style="font-weight:400;font-size:0.6rem;"><?php echo $d['dia_num']; ?></span>
                                </th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudiantes as $idx => $est):
                        $pct = $resumen_est[$est['id']]['pct'];
                        $pct_color = $pct >= 80 ? '#059669' : ($pct >= 60 ? '#d97706' : '#ef4444');
                        if ($resumen_est[$est['id']]['total'] === 0) { $pct_color = '#ccc'; $pct_label = '—'; }
                        else { $pct_label = $pct . '%'; }
                    ?>
                    <tr>
                        <td class="num-cell"><?php echo $idx + 1; ?></td>
                        <td class="nombre-cell" title="<?php echo htmlspecialchars($est['documento']); ?>">
                            <?php echo htmlspecialchars($est['nombre']); ?>
                        </td>
                        <?php foreach ($semanas as $dias): ?>
                            <?php foreach ($dias as $d):
                                $fecha = $d['fecha'];
                                $estado_actual = $asist_map[$est['id']][$fecha]['estado'] ?? '';
                                $obs_actual = $asist_map[$est['id']][$fecha]['observacion'] ?? '';
                                $labels = ['' => '·', 'presente' => 'P', 'ausente' => 'A', 'tardanza' => 'T', 'excusa' => 'E'];
                                $label = $labels[$estado_actual] ?? '·';
                            ?>
                                <td class="asist-cell">
                                    <input type="hidden" name="asist[<?php echo $est['id']; ?>][<?php echo $fecha; ?>]" value="<?php echo $estado_actual; ?>" class="asist-input">
                                    <input type="hidden" name="obs[<?php echo $est['id']; ?>][<?php echo $fecha; ?>]" value="<?php echo htmlspecialchars($obs_actual); ?>" class="obs-input">
                                    <button type="button" class="asist-btn" data-estado="<?php echo $estado_actual; ?>"
                                            onclick="ciclarEstado(this)"><?php echo $label; ?></button>
                                    <span class="obs-icon <?php echo $obs_actual ? 'tiene' : ''; ?>" onclick="editarObs(this)" title="<?php echo htmlspecialchars($obs_actual ?: 'Agregar observación'); ?>">💬</span>
                                </td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <td class="pct-cell" style="color:<?php echo $pct_color; ?>;"><?php echo $pct_label; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Botón guardar -->
        <div style="text-align:center;margin-top:1.2rem;padding-bottom:2rem;">
            <button type="submit" class="btn-guardar-all">💾 Guardar Planilla Completa</button>
        </div>
    </form>

    <?php elseif (empty($modulos)): ?>
        <div class="empty-state">
            <div style="font-size:3rem;">📋</div>
            <p>No hay módulos con horarios asignados en este programa.</p>
        </div>
    <?php elseif (empty($estudiantes)): ?>
        <div class="empty-state">
            <div style="font-size:3rem;">👥</div>
            <p>No hay estudiantes con este módulo asignado en horarios.</p>
        </div>
    <?php endif; ?>

</div>

<script>
const estados = ['', 'presente', 'ausente', 'tardanza', 'excusa'];
const labels  = {'': '·', 'presente': 'P', 'ausente': 'A', 'tardanza': 'T', 'excusa': 'E'};

function ciclarEstado(btn) {
    const input = btn.parentElement.querySelector('.asist-input');
    let actual = input.value;
    let idx = estados.indexOf(actual);
    idx = (idx + 1) % estados.length;
    const nuevo = estados[idx];
    input.value = nuevo;
    btn.setAttribute('data-estado', nuevo);
    btn.textContent = labels[nuevo];
}

function editarObs(icon) {
    const input = icon.parentElement.querySelector('.obs-input');
    const obs = prompt('Observación:', input.value || '');
    if (obs === null) return;
    input.value = obs;
    if (obs.trim()) {
        icon.classList.add('tiene');
        icon.title = obs;
    } else {
        icon.classList.remove('tiene');
        icon.title = 'Agregar observación';
    }
}
</script>
<script src="/intep/sesion.js"></script>
<?php include __DIR__ . '/../partials/asistente_admin.php'; ?>
</body>
</html>
