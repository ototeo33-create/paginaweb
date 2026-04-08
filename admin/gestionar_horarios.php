<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: ../dashboard.php'); exit; }

$mensaje = '';

// Programas disponibles
$programas = [];
$res_prog = mysqli_query($conexion, "SELECT id, nombre FROM programas ORDER BY nombre");
while ($p = mysqli_fetch_assoc($res_prog)) $programas[] = $p;

// Programa seleccionado
$programa_id = isset($_GET['programa_id']) ? (int)$_GET['programa_id'] : (!empty($programas) ? $programas[0]['id'] : 0);
$programa_actual = null;
foreach ($programas as $p) { if ($p['id'] == $programa_id) { $programa_actual = $p; break; } }

// Módulos del programa seleccionado
$modulos = [];
if ($programa_id) {
    $stmt_mod = mysqli_prepare($conexion, "SELECT pm.id, mf.nombre, pm.tipo FROM programa_modulo pm
        JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
        WHERE pm.programa_id = ? ORDER BY pm.orden");
    mysqli_stmt_bind_param($stmt_mod, 'i', $programa_id);
    mysqli_stmt_execute($stmt_mod);
    $res_mod = mysqli_stmt_get_result($stmt_mod);
    while ($m = mysqli_fetch_assoc($res_mod)) $modulos[] = $m;
}

// Estudiantes activos del programa
$estudiantes = [];
if ($programa_id) {
    $stmt_est = mysqli_prepare($conexion, "SELECT id, nombre FROM estudiantes WHERE programa_id = ? AND estado = 'activo' ORDER BY nombre");
    mysqli_stmt_bind_param($stmt_est, 'i', $programa_id);
    mysqli_stmt_execute($stmt_est);
    $res_est = mysqli_stmt_get_result($stmt_est);
    while ($e = mysqli_fetch_assoc($res_est)) $estudiantes[] = $e;
}

// Bimestres activos
$bimestres = [];
$res_bim = mysqli_query($conexion, "SELECT * FROM bimestres WHERE estado = 'activo' ORDER BY anio ASC, numero ASC");
while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

// POST: agregar clase al programa completo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $pm_id      = (int)$_POST['materia_id'];
        $dias_par   = $_POST['dias_par'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin   = $_POST['hora_fin'];
        $salon      = trim($_POST['salon']);
        $link_virtual = trim($_POST['link_virtual'] ?? '');
        $bimestre_id  = !empty($_POST['bimestre_id']) ? (int)$_POST['bimestre_id'] : null;
        $dias_array   = explode('-', $dias_par);

        if ($pm_id && !empty($estudiantes)) {
            $q = "INSERT IGNORE INTO horarios (programa_id, estudiante_id, programa_modulo_id, dia, hora_inicio, hora_fin, salon, bimestre_id, link_virtual)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertados = 0;
            foreach ($estudiantes as $est) {
                foreach ($dias_array as $dia) {
                    $dia = trim($dia);
                    $stmt = mysqli_prepare($conexion, $q);
                    mysqli_stmt_bind_param($stmt, 'iiissssis',
                        $programa_id, $est['id'], $pm_id,
                        $dia, $hora_inicio, $hora_fin, $salon, $bimestre_id, $link_virtual);
                    if (mysqli_stmt_execute($stmt)) $insertados++;
                }
            }
            $mensaje = "success|Clase asignada a {$insertados} registro(s) para " . count($estudiantes) . " estudiante(s).";
        } else {
            $mensaje = 'error|Selecciona un módulo válido y asegúrate de que el programa tenga estudiantes.';
        }

    } elseif ($accion === 'eliminar') {
        // Eliminar todos los horarios del mismo módulo+día+programa
        $pm_id = (int)$_POST['pm_id'];
        $dia   = $_POST['dia'];
        $bim_id = !empty($_POST['bimestre_id_del']) ? (int)$_POST['bimestre_id_del'] : null;

        if ($bim_id) {
            $stmt = mysqli_prepare($conexion, "DELETE FROM horarios WHERE programa_id = ? AND programa_modulo_id = ? AND dia = ? AND bimestre_id = ?");
            mysqli_stmt_bind_param($stmt, 'iisi', $programa_id, $pm_id, $dia, $bim_id);
        } else {
            $stmt = mysqli_prepare($conexion, "DELETE FROM horarios WHERE programa_id = ? AND programa_modulo_id = ? AND dia = ? AND bimestre_id IS NULL");
            mysqli_stmt_bind_param($stmt, 'iis', $programa_id, $pm_id, $dia);
        }
        mysqli_stmt_execute($stmt);
        $afectados = mysqli_affected_rows($conexion);
        $mensaje = "success|Eliminados $afectados registro(s).";
    }

    header("Location: gestionar_horarios.php?programa_id=$programa_id&msg=" . urlencode($mensaje));
    exit;
}

if (isset($_GET['msg'])) $mensaje = urldecode($_GET['msg']);
$msg_parts = $mensaje ? explode('|', $mensaje, 2) : null;

// Horarios actuales del programa (agrupados por módulo+día, únicos)
$horarios = [];
if ($programa_id) {
    $res_hor = mysqli_query($conexion, "SELECT pm.id as pm_id, mf.nombre as modulo, h.dia, h.hora_inicio, h.hora_fin, h.salon, h.link_virtual,
        h.bimestre_id, b.numero as bimestre_num,
        COUNT(h.id) as total_estudiantes
        FROM horarios h
        JOIN programa_modulo pm ON h.programa_modulo_id = pm.id
        JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
        LEFT JOIN bimestres b ON h.bimestre_id = b.id
        WHERE h.programa_id = $programa_id
        GROUP BY pm.id, h.dia, h.bimestre_id
        ORDER BY b.numero ASC, pm.orden ASC, FIELD(h.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado')");
    while ($h = mysqli_fetch_assoc($res_hor)) $horarios[] = $h;
}

$tipo_labels = ['especifico' => 'Específico', 'transversal' => 'Transversal', 'basico' => 'Básico'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Horarios – INTEP</title>
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1.4fr; gap: 1.5rem; }
        .card {
            background: rgba(255,255,255,0.78); backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            border: 1px solid rgba(16,185,129,0.1);
        }
        .card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1.2rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(16,185,129,0.15); }
        .campo-admin { margin-bottom: 1rem; }
        .campo-admin label { display: block; font-size: 0.78rem; font-weight: 700; color: #555; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .campo-admin input, .campo-admin select { width: 100%; padding: 0.7rem 0.9rem; border: 2px solid rgba(16,185,129,0.2); border-radius: 8px; font-size: 0.9rem; outline: none; background: rgba(255,255,255,0.9); }
        .campo-admin input:focus, .campo-admin select:focus { border-color: #10B981; }
        .grid-horas { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn-agregar { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; padding: 0.85rem; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 0.95rem; transition: all 0.2s; margin-top: 0.5rem; }
        .btn-agregar:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(5,150,105,0.3); }
        .btn-eliminar { background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.9rem; padding: 0.3rem 0.5rem; border-radius: 6px; transition: background 0.2s; }
        .btn-eliminar:hover { background: #fee2e2; }

        .selector-bar {
            background: rgba(255,255,255,0.78); backdrop-filter: blur(12px);
            border-radius: 14px; padding: 1rem 1.5rem;
            display: flex; align-items: center; gap: 1.2rem; flex-wrap: wrap;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(5,150,105,0.07);
            border: 1px solid rgba(16,185,129,0.1);
        }
        .selector-bar label { font-weight: 700; font-size: 0.88rem; color: #555; white-space: nowrap; }
        .selector-bar select { flex: 1; padding: 0.65rem 1rem; border: 2px solid rgba(16,185,129,0.2); border-radius: 10px; font-size: 0.9rem; outline: none; min-width: 220px; background: white; }
        .selector-bar select:focus { border-color: #10B981; }

        .badge-prog { background: #d1fae5; color: #065f46; padding: 0.3rem 0.9rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
        .badge-est { background: #e0f2fe; color: #0369a1; padding: 0.3rem 0.9rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }

        .horarios-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
        .horarios-table th { background: #f0fdf4; color: #065f46; font-weight: 700; padding: 0.6rem 0.8rem; text-align: left; border-bottom: 2px solid #d1fae5; }
        .horarios-table td { padding: 0.6rem 0.8rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .horarios-table tr:hover td { background: #f9fffe; }
        .tag-bim { background: #d1fae5; color: #065f46; padding: 0.15rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.72rem; }
        .tag-dia { background: #e0f2fe; color: #0369a1; padding: 0.15rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 600; }

        .alerta-success { background: rgba(16,185,129,0.1); color: #065f46; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #10b981; font-size: 0.88rem; }
        .alerta-error { background: rgba(239,68,68,0.08); color: #991b1b; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #ef4444; font-size: 0.88rem; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: #9ca3af; font-size: 0.9rem; }

        .estudiantes-list { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.8rem; }
        .est-chip { background: #f0fdf4; border: 1px solid #bbf7d0; color: #065f46; padding: 0.25rem 0.7rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }

        @media(max-width:800px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body data-rol="admin">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📅 Gestionar Horarios</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="dashboard-container">

    <a href="../dashboard.php" class="btn-volver">← Volver al inicio</a>

    <?php if ($msg_parts): ?>
        <div class="alerta-<?php echo $msg_parts[0] === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($msg_parts[1]); ?>
        </div>
    <?php endif; ?>

    <!-- Selector de programa -->
    <div class="selector-bar">
        <label>🎓 Programa:</label>
        <select onchange="window.location.href='gestionar_horarios.php?programa_id='+this.value">
            <?php foreach ($programas as $p): ?>
                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $programa_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($programa_actual): ?>
            <span class="badge-prog">📚 <?php echo count($modulos); ?> módulos</span>
            <span class="badge-est">👥 <?php echo count($estudiantes); ?> estudiante<?php echo count($estudiantes) != 1 ? 's' : ''; ?></span>
        <?php endif; ?>
    </div>

    <?php if ($programa_id): ?>

    <div class="grid-2">

        <!-- Formulario agregar clase -->
        <div class="card">
            <h3>➕ Agregar Clase al Programa</h3>

            <?php if (empty($estudiantes)): ?>
                <p style="color:#9ca3af;font-size:0.88rem;">Este programa no tiene estudiantes activos aún.</p>
            <?php elseif (empty($modulos)): ?>
                <p style="color:#9ca3af;font-size:0.88rem;">Este programa no tiene módulos asignados.</p>
            <?php else: ?>

            <p style="font-size:0.82rem;color:#6b7280;margin-bottom:1rem;">
                La clase se asignará automáticamente a <strong><?php echo count($estudiantes); ?> estudiante<?php echo count($estudiantes)!=1?'s':''; ?></strong>:
            </p>
            <div class="estudiantes-list" style="margin-bottom:1.2rem;">
                <?php foreach ($estudiantes as $e): ?>
                    <span class="est-chip"><?php echo htmlspecialchars($e['nombre']); ?></span>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="?programa_id=<?php echo $programa_id; ?>">
                <input type="hidden" name="accion" value="agregar">

                <div class="campo-admin">
                    <label>Bimestre</label>
                    <select name="bimestre_id" required>
                        <option value="">Selecciona un bimestre</option>
                        <?php foreach ($bimestres as $b): ?>
                            <option value="<?php echo $b['id']; ?>">
                                Bimestre <?php echo $b['numero']; ?> — <?php echo date('d/m', strtotime($b['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($b['fecha_fin'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo-admin">
                    <label>Módulo</label>
                    <select name="materia_id" required>
                        <option value="">Selecciona un módulo</option>
                        <?php
                        $tipo_actual = '';
                        foreach ($modulos as $m):
                            $tipo = $m['tipo'];
                            if ($tipo !== $tipo_actual) {
                                if ($tipo_actual !== '') echo '</optgroup>';
                                echo '<optgroup label="— ' . htmlspecialchars($tipo_labels[$tipo] ?? $tipo) . ' —">';
                                $tipo_actual = $tipo;
                            }
                        ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                        <?php endforeach; ?>
                        <?php if ($tipo_actual !== '') echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="campo-admin">
                    <label>Días</label>
                    <select name="dias_par" required>
                        <option value="">Selecciona los días</option>
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miércoles">Miércoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                        <option value="Lunes-Martes">Lunes y Martes</option>
                        <option value="Miércoles-Jueves">Miércoles y Jueves</option>
                        <option value="Lunes-Miércoles">Lunes y Miércoles</option>
                        <option value="Martes-Jueves">Martes y Jueves</option>
                    </select>
                </div>

                <div class="grid-horas">
                    <div class="campo-admin">
                        <label>Hora inicio</label>
                        <input type="time" name="hora_inicio" value="18:30" required>
                    </div>
                    <div class="campo-admin">
                        <label>Hora fin</label>
                        <input type="time" name="hora_fin" value="21:30" required>
                    </div>
                </div>

                <div class="campo-admin">
                    <label>Salón</label>
                    <input type="text" name="salon" placeholder="Ej: Aula 101">
                </div>

                <div class="campo-admin">
                    <label>🔗 Link clase virtual (opcional)</label>
                    <input type="url" name="link_virtual" placeholder="https://meet.google.com/xxx-xxx-xxx">
                </div>

                <button type="submit" class="btn-agregar">➕ Asignar clase a todos los estudiantes</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Horarios actuales del programa -->
        <div class="card">
            <h3>📋 Horario del Programa (<?php echo count($horarios); ?> clases)</h3>

            <?php if (empty($horarios)): ?>
                <div class="empty-state">
                    <div style="font-size:2.5rem;">📅</div>
                    <p>No hay clases asignadas a este programa aún.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="horarios-table">
                        <thead>
                            <tr>
                                <th>Bim.</th>
                                <th>Módulo</th>
                                <th>Día</th>
                                <th>Horario</th>
                                <th>Salón</th>
                                <th>Estudiantes</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios as $h): ?>
                            <tr>
                                <td><span class="tag-bim"><?php echo $h['bimestre_num'] ? 'B'.$h['bimestre_num'] : '–'; ?></span></td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($h['modulo']); ?></td>
                                <td><span class="tag-dia"><?php echo $h['dia']; ?></span></td>
                                <td><?php echo substr($h['hora_inicio'],0,5); ?> – <?php echo substr($h['hora_fin'],0,5); ?></td>
                                <td><?php echo htmlspecialchars($h['salon'] ?? '–'); ?></td>
                                <td style="text-align:center;">
                                    <span style="background:#f0fdf4;color:#059669;padding:0.2rem 0.6rem;border-radius:6px;font-size:0.78rem;font-weight:700;">
                                        👥 <?php echo $h['total_estudiantes']; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="?programa_id=<?php echo $programa_id; ?>"
                                          onsubmit="return confirm('¿Eliminar esta clase para todos los estudiantes del programa?')">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="pm_id" value="<?php echo $h['pm_id']; ?>">
                                        <input type="hidden" name="dia" value="<?php echo htmlspecialchars($h['dia']); ?>">
                                        <input type="hidden" name="bimestre_id_del" value="<?php echo $h['bimestre_id'] ?? ''; ?>">
                                        <button type="submit" class="btn-eliminar" title="Eliminar para todos">🗑</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>

</div>

<script src="/intep/sesion.js"></script>
</body>
</html>
