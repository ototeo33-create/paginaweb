<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['usuario_rol'] !== 'estudiante') { header('Location: dashboard.php'); exit; }

$estudiante_id = $_SESSION['estudiante_id'];

// Módulos del estudiante (desde asignación directa en estudiante_modulo)
$modulos = [];
$q_mod = "SELECT pm.id, mf.nombre
          FROM programa_modulo pm
          JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
          JOIN estudiante_modulo em ON em.programa_modulo_id = pm.id
          WHERE em.estudiante_id = ? AND pm.estado = 'activo' AND em.estado = 'activo'
          ORDER BY pm.bimestre, pm.orden";
$stmt = mysqli_prepare($conexion, $q_mod);
mysqli_stmt_bind_param($stmt, 'i', $estudiante_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($m = mysqli_fetch_assoc($res)) $modulos[] = $m;

$materia_id = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : (!empty($modulos) ? $modulos[0]['id'] : 0);

// Bimestres
$bimestres = [];
$res_bim = mysqli_query($conexion, "SELECT * FROM bimestres WHERE estado = 'activo' ORDER BY numero ASC");
while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

$bimestre_id = isset($_GET['bimestre_id']) ? (int)$_GET['bimestre_id'] : (!empty($bimestres) ? $bimestres[0]['id'] : 0);
$bimestre_actual = null;
foreach ($bimestres as $b) { if ($b['id'] == $bimestre_id) { $bimestre_actual = $b; break; } }

// Obtener asistencias
$asistencias = [];
if ($materia_id && $bimestre_id) {
    $q = "SELECT * FROM asistencias WHERE estudiante_id = ? AND programa_modulo_id = ? AND bimestre_id = ? ORDER BY fecha ASC";
    $stmt = mysqli_prepare($conexion, $q);
    mysqli_stmt_bind_param($stmt, 'iii', $estudiante_id, $materia_id, $bimestre_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($a = mysqli_fetch_assoc($res)) $asistencias[] = $a;
}

// Resumen
$total = count($asistencias);
$presentes = count(array_filter($asistencias, fn($a) => $a['estado'] === 'presente'));
$ausentes = count(array_filter($asistencias, fn($a) => $a['estado'] === 'ausente'));
$tardanzas = count(array_filter($asistencias, fn($a) => $a['estado'] === 'tardanza'));
$excusas = count(array_filter($asistencias, fn($a) => $a['estado'] === 'excusa'));
$porcentaje = $total > 0 ? round(($presentes + $excusas) / $total * 100) : 100;

// Carita según porcentaje
if ($porcentaje >= 90) { $carita = '😄'; $carita_msg = '¡Excelente asistencia! Sigue así.'; $carita_color = '#059669'; }
elseif ($porcentaje >= 75) { $carita = '🙂'; $carita_msg = 'Buena asistencia, pero puedes mejorar.'; $carita_color = '#10B981'; }
elseif ($porcentaje >= 60) { $carita = '😐'; $carita_msg = 'Tu asistencia está bajando. ¡Ánimo!'; $carita_color = '#d97706'; }
elseif ($porcentaje >= 40) { $carita = '😟'; $carita_msg = 'Muchas inasistencias. Necesitas mejorar.'; $carita_color = '#ea580c'; }
else { $carita = '😢'; $carita_msg = 'Asistencia muy baja. Habla con tu docente.'; $carita_color = '#ef4444'; }

$materia_nombre = '';
foreach ($modulos as $m) { if ($m['id'] == $materia_id) $materia_nombre = $m['nombre']; }

// Resumen global por todos los módulos
$resumen_global = [];
foreach ($modulos as $mod) {
    $q_g = "SELECT estado, COUNT(*) as total FROM asistencias WHERE estudiante_id = ? AND programa_modulo_id = ? AND bimestre_id = ? GROUP BY estado";
    $stmt_g = mysqli_prepare($conexion, $q_g);
    mysqli_stmt_bind_param($stmt_g, 'iii', $estudiante_id, $mod['id'], $bimestre_id);
    mysqli_stmt_execute($stmt_g);
    $res_g = mysqli_stmt_get_result($stmt_g);
    $conteos = ['presente'=>0,'ausente'=>0,'tardanza'=>0,'excusa'=>0];
    while ($r = mysqli_fetch_assoc($res_g)) $conteos[$r['estado']] = (int)$r['total'];
    $t = array_sum($conteos);
    $pct = $t > 0 ? round(($conteos['presente'] + $conteos['excusa']) / $t * 100) : 100;
    $resumen_global[] = ['nombre' => $mod['nombre'], 'id' => $mod['id'], 'total' => $t, 'porcentaje' => $pct, 'ausentes' => $conteos['ausente']];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Asistencia – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .filtros-bar {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
            border: 1px solid rgba(16,185,129,0.1);
        }
        .filtros-bar label { font-weight: 700; font-size: 0.85rem; color: #666; }
        .filtros-bar select { padding: 0.6rem 0.8rem; border: 2px solid rgba(16,185,129,0.2); border-radius: 10px; font-size: 0.88rem; outline: none; }
        .filtros-bar select:focus { border-color: #10B981; }
        .card {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            border: 1px solid rgba(16,185,129,0.1);
            margin-bottom: 1.5rem;
        }
        .card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(16,185,129,0.15); }

        /* Carita hero */
        .carita-hero {
            text-align: center; padding: 1.5rem 1rem;
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.6));
            border-radius: 16px; margin-bottom: 1.5rem;
            border: 2px solid rgba(16,185,129,0.1);
        }
        .carita-emoji { font-size: 4rem; line-height: 1; margin-bottom: 0.5rem; }
        .carita-pct { font-size: 2.5rem; font-weight: 900; margin-bottom: 0.3rem; }
        .carita-msg { font-size: 0.92rem; color: #666; }

        /* Resumen cards */
        .resumen-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; margin-bottom: 1rem; }
        .resumen-item { text-align: center; padding: 0.8rem 0.5rem; border-radius: 12px; }
        .resumen-item .num { font-size: 1.5rem; font-weight: 900; }
        .resumen-item .lbl { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .res-presente { background: rgba(16,185,129,0.1); color: #059669; }
        .res-ausente { background: rgba(239,68,68,0.1); color: #ef4444; }
        .res-tardanza { background: rgba(245,166,35,0.1); color: #d97706; }
        .res-excusa { background: rgba(59,130,246,0.1); color: #2563eb; }

        .barra-asist { height: 12px; border-radius: 99px; background: #fee2e2; overflow: hidden; }
        .barra-fill { height: 100%; border-radius: 99px; transition: width 0.8s ease; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { text-align: left; padding: 0.6rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; color: #888; border-bottom: 2px solid rgba(16,185,129,0.15); }
        td { padding: 0.55rem 0.5rem; border-bottom: 1px solid #f0f0f0; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .badge-presente { background: #d1fae5; color: #065f46; }
        .badge-ausente { background: #fee2e2; color: #991b1b; }
        .badge-tardanza { background: #fef3c7; color: #92400e; }
        .badge-excusa { background: #dbeafe; color: #1e40af; }
        .obs-text { font-size: 0.78rem; color: #888; }

        /* Módulos overview */
        .modulo-row {
            display: flex; align-items: center; gap: 0.8rem;
            padding: 0.8rem 0; border-bottom: 1px solid #f0f0f0;
            cursor: pointer; transition: background 0.2s;
        }
        .modulo-row:hover { background: rgba(16,185,129,0.04); border-radius: 8px; }
        .modulo-row:last-child { border-bottom: none; }
        .modulo-name { flex: 1; font-weight: 600; font-size: 0.88rem; }
        .modulo-bar { flex: 2; }
        .modulo-bar-inner { height: 8px; border-radius: 99px; background: #f0f0f0; overflow: hidden; }
        .modulo-bar-fill { height: 100%; border-radius: 99px; }
        .modulo-pct { font-weight: 900; font-size: 0.88rem; min-width: 45px; text-align: right; }
        .modulo-cara { font-size: 1.3rem; min-width: 30px; text-align: center; }

        @media(max-width:600px) {
            .resumen-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body data-rol="estudiante">


<div class="container" style="max-width:800px;margin:1rem auto;padding:0 1rem;">

    <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

    <!-- Filtros -->
    <div class="filtros-bar">
        <label>📚 Módulo:</label>
        <select onchange="location.href='asistencia.php?materia_id='+this.value+'&bimestre_id=<?php echo $bimestre_id; ?>'">
            <?php foreach ($modulos as $m): ?>
                <option value="<?php echo $m['id']; ?>" <?php echo $m['id'] == $materia_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($m['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>📅 Bimestre:</label>
        <select onchange="location.href='asistencia.php?materia_id=<?php echo $materia_id; ?>&bimestre_id='+this.value">
            <?php foreach ($bimestres as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $bimestre_id ? 'selected' : ''; ?>>
                    B<?php echo $b['numero']; ?> — <?php echo date('d/m', strtotime($b['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($b['fecha_fin'])); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($total > 0): ?>
    <!-- Carita Hero -->
    <div class="carita-hero">
        <div class="carita-emoji"><?php echo $carita; ?></div>
        <div class="carita-pct" style="color:<?php echo $carita_color; ?>;"><?php echo $porcentaje; ?>%</div>
        <div class="carita-msg"><?php echo $carita_msg; ?></div>
        <div style="max-width:300px;margin:0.8rem auto 0;">
            <div class="barra-asist">
                <div class="barra-fill" style="width:<?php echo $porcentaje; ?>%;background:<?php echo $carita_color; ?>;"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resumen numérico -->
    <div class="card">
        <h3>📊 <?php echo htmlspecialchars($materia_nombre); ?> — Bimestre <?php echo $bimestre_actual ? $bimestre_actual['numero'] : ''; ?></h3>

        <?php if ($total === 0): ?>
            <p style="text-align:center;color:#aaa;padding:1.5rem 0;">Aún no hay registros de asistencia para este módulo.</p>
        <?php else: ?>
            <div class="resumen-grid">
                <div class="resumen-item res-presente">
                    <div class="num"><?php echo $presentes; ?></div>
                    <div class="lbl">Presentes</div>
                </div>
                <div class="resumen-item res-ausente">
                    <div class="num"><?php echo $ausentes; ?></div>
                    <div class="lbl">Ausencias</div>
                </div>
                <div class="resumen-item res-tardanza">
                    <div class="num"><?php echo $tardanzas; ?></div>
                    <div class="lbl">Tardanzas</div>
                </div>
                <div class="resumen-item res-excusa">
                    <div class="num"><?php echo $excusas; ?></div>
                    <div class="lbl">Excusas</div>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asistencias as $a):
                            $dias_es = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb'];
                            $dia_label = ($dias_es[date('D', strtotime($a['fecha']))] ?? '') . ' ' . date('d/m/Y', strtotime($a['fecha']));
                            $badge = 'badge-' . $a['estado'];
                            $emoji = ['presente'=>'✅','ausente'=>'❌','tardanza'=>'⚠️','excusa'=>'📋'][$a['estado']] ?? '';
                        ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo $dia_label; ?></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo $emoji . ' ' . ucfirst($a['estado']); ?></span></td>
                            <td><span class="obs-text"><?php echo htmlspecialchars($a['observacion'] ?: '—'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Resumen global todos los módulos -->
    <?php if (count($resumen_global) > 1): ?>
    <div class="card">
        <h3>📋 Resumen General — Todos los Módulos</h3>
        <?php foreach ($resumen_global as $rg):
            $pct = $rg['porcentaje'];
            if ($pct >= 90) { $c_emoji = '😄'; $c_color = '#059669'; }
            elseif ($pct >= 75) { $c_emoji = '🙂'; $c_color = '#10B981'; }
            elseif ($pct >= 60) { $c_emoji = '😐'; $c_color = '#d97706'; }
            elseif ($pct >= 40) { $c_emoji = '😟'; $c_color = '#ea580c'; }
            else { $c_emoji = '😢'; $c_color = '#ef4444'; }
        ?>
            <a href="asistencia.php?materia_id=<?php echo $rg['id']; ?>&bimestre_id=<?php echo $bimestre_id; ?>" style="text-decoration:none;color:inherit;">
                <div class="modulo-row">
                    <div class="modulo-cara"><?php echo $rg['total'] > 0 ? $c_emoji : '➖'; ?></div>
                    <div class="modulo-name"><?php echo htmlspecialchars($rg['nombre']); ?></div>
                    <div class="modulo-bar">
                        <div class="modulo-bar-inner">
                            <div class="modulo-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $c_color; ?>;"></div>
                        </div>
                    </div>
                    <div class="modulo-pct" style="color:<?php echo $c_color; ?>;"><?php echo $rg['total'] > 0 ? $pct . '%' : '—'; ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script src="/intep/sesion.js"></script>
</body>
</html>
