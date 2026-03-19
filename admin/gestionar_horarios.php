<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../dashboard.php');
    exit;
}

$mensaje = '';

// Obtener todos los estudiantes
$estudiantes = [];
$q_est = "SELECT e.*, p.nombre as programa 
          FROM estudiantes e 
          LEFT JOIN programas p ON e.programa_id = p.id 
          ORDER BY e.nombre ASC";
$res_est = mysqli_query($conexion, $q_est);
while ($e = mysqli_fetch_assoc($res_est)) $estudiantes[] = $e;

// Estudiante seleccionado
$estudiante_id = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 
                 (!empty($estudiantes) ? $estudiantes[0]['id'] : 0);

// Obtener datos del estudiante seleccionado
$estudiante_actual = null;
foreach ($estudiantes as $e) {
    if ($e['id'] == $estudiante_id) {
        $estudiante_actual = $e;
        break;
    }
}

// Obtener módulos del programa del estudiante
$modulos = [];
if ($estudiante_actual) {
    $q_mod = "SELECT * FROM materias WHERE programa_id = ? ORDER BY nombre ASC";
    $stmt_mod = mysqli_prepare($conexion, $q_mod);
    mysqli_stmt_bind_param($stmt_mod, 'i', $estudiante_actual['programa_id']);
    mysqli_stmt_execute($stmt_mod);
    $res_mod = mysqli_stmt_get_result($stmt_mod);
    while ($m = mysqli_fetch_assoc($res_mod)) $modulos[] = $m;
}

// AJAX: Agregar nuevo módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_modulo') {
    header('Content-Type: application/json; charset=utf-8');
    $nombre_modulo = trim($_POST['nombre_modulo'] ?? '');
    $prog_id = (int)($_POST['programa_id'] ?? 0);
    if ($nombre_modulo && $prog_id) {
        // Verificar que no exista ya
        $q_check = "SELECT id FROM materias WHERE nombre = ? AND programa_id = ?";
        $stmt_check = mysqli_prepare($conexion, $q_check);
        mysqli_stmt_bind_param($stmt_check, 'si', $nombre_modulo, $prog_id);
        mysqli_stmt_execute($stmt_check);
        $res_check = mysqli_stmt_get_result($stmt_check);
        if ($existente = mysqli_fetch_assoc($res_check)) {
            echo json_encode(['ok' => true, 'id' => $existente['id'], 'nombre' => $nombre_modulo, 'existia' => true]);
        } else {
            $q_ins = "INSERT INTO materias (nombre, programa_id) VALUES (?, ?)";
            $stmt_ins = mysqli_prepare($conexion, $q_ins);
            mysqli_stmt_bind_param($stmt_ins, 'si', $nombre_modulo, $prog_id);
            mysqli_stmt_execute($stmt_ins);
            $nuevo_id = mysqli_insert_id($conexion);
            echo json_encode(['ok' => true, 'id' => $nuevo_id, 'nombre' => $nombre_modulo, 'existia' => false]);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    }
    exit;
}

// AJAX: Eliminar módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_modulo') {
    header('Content-Type: application/json; charset=utf-8');
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    if ($modulo_id) {
        // Verificar si tiene horarios asignados
        $q_uso = "SELECT COUNT(*) as total FROM horarios WHERE materia_id = ?";
        $stmt_uso = mysqli_prepare($conexion, $q_uso);
        mysqli_stmt_bind_param($stmt_uso, 'i', $modulo_id);
        mysqli_stmt_execute($stmt_uso);
        $res_uso = mysqli_stmt_get_result($stmt_uso);
        $uso = mysqli_fetch_assoc($res_uso);
        if ($uso['total'] > 0) {
            echo json_encode(['ok' => false, 'error' => 'Este módulo tiene ' . $uso['total'] . ' clase(s) asignada(s). Elimínalas primero.']);
        } else {
            $q_del = "DELETE FROM materias WHERE id = ?";
            $stmt_del = mysqli_prepare($conexion, $q_del);
            mysqli_stmt_bind_param($stmt_del, 'i', $modulo_id);
            mysqli_stmt_execute($stmt_del);
            echo json_encode(['ok' => true]);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'ID de módulo inválido']);
    }
    exit;
}

// Obtener bimestres activos
$bimestres = [];
$res_bim = mysqli_query($conexion, "SELECT * FROM bimestres WHERE estado = 'activo' ORDER BY anio DESC, numero ASC");
while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

// Obtener horarios del estudiante seleccionado
$horarios = [];
$q_hor = "SELECT h.*, m.nombre as materia, b.numero as bimestre_num, b.anio as bimestre_anio,
                 b.fecha_inicio as bim_inicio, b.fecha_fin as bim_fin
          FROM horarios h
          JOIN materias m ON h.materia_id = m.id
          LEFT JOIN bimestres b ON h.bimestre_id = b.id
          WHERE h.estudiante_id = ?
          ORDER BY b.numero ASC, FIELD(h.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), h.hora_inicio";
$stmt_hor = mysqli_prepare($conexion, $q_hor);
mysqli_stmt_bind_param($stmt_hor, 'i', $estudiante_id);
mysqli_stmt_execute($stmt_hor);
$res_hor = mysqli_stmt_get_result($stmt_hor);
while ($h = mysqli_fetch_assoc($res_hor)) $horarios[] = $h;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];

    if ($accion === 'agregar') {
        $materia_id = (int)$_POST['materia_id'];
        $dias_par = $_POST['dias_par'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];
        $salon = trim($_POST['salon']);
        $bimestre_id = !empty($_POST['bimestre_id']) ? (int)$_POST['bimestre_id'] : null;

        // Separar el par de días e insertar uno por cada día
        $dias_array = explode('-', $dias_par);
        $q = "INSERT INTO horarios (programa_id, estudiante_id, materia_id, dia, hora_inicio, hora_fin, salon, bimestre_id)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        foreach ($dias_array as $dia) {
            $dia = trim($dia);
            $stmt = mysqli_prepare($conexion, $q);
            mysqli_stmt_bind_param($stmt, 'iiissssi',
                $estudiante_actual['programa_id'], $estudiante_id,
                $materia_id, $dia, $hora_inicio, $hora_fin, $salon, $bimestre_id);
            mysqli_stmt_execute($stmt);
        }
        $mensaje = 'success|Módulo agregado correctamente (' . implode(' y ', $dias_array) . ').';

    } elseif ($accion === 'eliminar') {
        $id = (int)$_POST['horario_id'];
        $q = "DELETE FROM horarios WHERE id = ? AND estudiante_id = ?";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $estudiante_id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Clase eliminada correctamente.';
    }

    // Recargar horarios
    mysqli_stmt_execute($stmt_hor);
    $res_hor2 = mysqli_stmt_get_result($stmt_hor);
    $horarios = [];
    while ($h = mysqli_fetch_assoc($res_hor2)) $horarios[] = $h;
}

$msg_parts = $mensaje ? explode('|', $mensaje) : null;
$dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Horarios – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1.2fr; gap: 1.5rem; }
        .card { 
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px; 
            padding: 1.5rem; 
            box-shadow: 0 4px 20px rgba(5,150,105,0.08), 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.12), 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1.2rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(16, 185, 129, 0.15); }
        .campo-admin { margin-bottom: 1rem; }
        .campo-admin label { display: block; font-size: 0.8rem; font-weight: 600; color: #666; margin-bottom: 0.3rem; text-transform: uppercase; }
        .campo-admin input, .campo-admin select { width: 100%; padding: 0.7rem 0.9rem; border: 2px solid rgba(16, 185, 129, 0.2); border-radius: 8px; font-size: 0.9rem; outline: none; background: rgba(255,255,255,0.8); }
        .campo-admin input:focus, .campo-admin select:focus { border-color: #10B981; }
        .grid-horas { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn-agregar { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; padding: 0.8rem; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 0.95rem; transition: all 0.3s; }
        .btn-agregar:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(5,150,105,0.3); }
        .btn-eliminar { background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 6px; transition: background 0.2s; }
        .btn-eliminar:hover { background: #ffe0e0; }
        .alerta-success { background: rgba(16, 185, 129, 0.1); color: #065f46; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #10b981; font-size: 0.88rem; }
        .estudiante-selector { 
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            border-radius: 16px; 
            padding: 1.2rem 1.5rem; 
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            flex-wrap: wrap;
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        .estudiante-selector label { font-weight: 700; font-size: 0.9rem; color: #666; white-space: nowrap; }
        .estudiante-selector select { flex: 1; padding: 0.7rem 1rem; border: 2px solid var(--verde-muted); border-radius: 10px; font-size: 0.9rem; outline: none; min-width: 200px; }
        .estudiante-selector select:focus { border-color: var(--verde-claro); }
        .estudiante-badge { background: var(--verde-muted); color: var(--verde); padding: 0.4rem 1rem; border-radius: 99px; font-size: 0.82rem; font-weight: 700; white-space: nowrap; }
        .btn-nuevo-modulo { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; width: 38px; height: 38px; border-radius: 8px; font-size: 1.3rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; }
        .btn-nuevo-modulo:hover { transform: scale(1.08); box-shadow: 0 3px 10px rgba(5,150,105,0.3); }
        .btn-guardar-modulo { background: #059669; color: white; border: none; padding: 0.6rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background 0.2s; }
        .btn-guardar-modulo:hover { background: #047857; }
        .btn-eliminar-modulo { background: #fee2e2; border: 2px solid #fca5a5; width: 38px; height: 38px; border-radius: 8px; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; }
        .btn-eliminar-modulo:hover { background: #fecaca; border-color: #ef4444; }
        @media(max-width:700px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📅 Gestionar Horarios</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="dashboard-container">

    <a href="../horarios.php" class="btn-volver">← Volver al calendario</a>

    <?php if ($msg_parts): ?>
        <div class="alerta-<?php echo $msg_parts[0]; ?>"><?php echo htmlspecialchars($msg_parts[1]); ?></div>
    <?php endif; ?>

    <!-- Selector de estudiante -->
    <div class="estudiante-selector">
        <label>👤 Estudiante:</label>
        <select onchange="window.location.href='gestionar_horarios.php?estudiante_id='+this.value">
            <?php foreach ($estudiantes as $e): ?>
                <option value="<?php echo $e['id']; ?>" <?php echo $e['id'] == $estudiante_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre']); ?> · <?php echo htmlspecialchars($e['documento']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($estudiante_actual): ?>
            <span class="estudiante-badge">
                📚 <?php echo htmlspecialchars($estudiante_actual['programa']); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($estudiantes)): ?>
        <div class="bienvenida">
            <p>No hay estudiantes registrados. <a href="index.php">Crear estudiante →</a></p>
        </div>
    <?php else: ?>

    <div class="grid-2">

        <!-- Formulario agregar clase -->
        <div class="card">
            <h3>➕ Agregar Clase a <?php echo htmlspecialchars($estudiante_actual['nombre'] ?? ''); ?></h3>
            <form method="POST" action="?estudiante_id=<?php echo $estudiante_id; ?>">
                <input type="hidden" name="accion" value="agregar">
                <div class="campo-admin">
                    <label>Bimestre</label>
                    <select name="bimestre_id" required>
                        <option value="">Selecciona un bimestre</option>
                        <?php foreach ($bimestres as $b): ?>
                            <option value="<?php echo $b['id']; ?>">
                                Bimestre <?php echo $b['numero']; ?> (<?php echo date('d M', strtotime($b['fecha_inicio'])); ?> – <?php echo date('d M Y', strtotime($b['fecha_fin'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-admin">
                    <label>Módulo</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <select name="materia_id" id="select-modulo" required style="flex:1;">
                            <option value="">Selecciona un módulo</option>
                            <?php foreach ($modulos as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="toggleNuevoModulo()" class="btn-nuevo-modulo" title="Agregar nuevo módulo">+</button>
                        <button type="button" onclick="eliminarModulo()" class="btn-eliminar-modulo" title="Eliminar módulo seleccionado">🗑</button>
                    </div>
                    <div id="nuevo-modulo-form" style="display:none;margin-top:0.5rem;">
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <input type="text" id="input-nuevo-modulo" placeholder="Nombre del módulo" style="flex:1;padding:0.6rem 0.8rem;border:2px solid rgba(16,185,129,0.3);border-radius:8px;font-size:0.88rem;">
                            <button type="button" onclick="guardarModulo()" class="btn-guardar-modulo">Guardar</button>
                        </div>
                        <small id="modulo-msg" style="display:none;margin-top:0.3rem;font-size:0.78rem;"></small>
                    </div>
                </div>
                <div class="campo-admin">
                    <label>Días</label>
                    <select name="dias_par" required>
                        <option value="">Selecciona los días</option>
                        <option value="Lunes-Martes">Lunes y Martes</option>
                        <option value="Miércoles-Jueves">Miércoles y Jueves</option>
                        <option value="Viernes">Viernes</option>
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
                <button type="submit" class="btn-agregar">➕ Agregar Clase</button>
            </form>
        </div>

        <!-- Horario actual del estudiante -->
        <div class="card">
            <h3>📋 Horario de <?php echo htmlspecialchars($estudiante_actual['nombre'] ?? ''); ?> (<?php echo count($horarios); ?> clases)</h3>
            <?php if (empty($horarios)): ?>
                <p style="color:var(--gray);font-size:0.9rem;">Este estudiante no tiene clases asignadas aún.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Bim.</th>
                                <th>Módulo</th>
                                <th>Día</th>
                                <th>Horario</th>
                                <th>Salón</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios as $h): ?>
                            <tr>
                                <td><span style="background:var(--verde-muted);color:var(--verde);padding:0.2rem 0.5rem;border-radius:6px;font-size:0.75rem;font-weight:700;"><?php echo $h['bimestre_num'] ? 'B'.$h['bimestre_num'] : '–'; ?></span></td>
                                <td style="font-size:0.82rem;"><?php echo htmlspecialchars($h['materia']); ?></td>
                                <td><?php echo $h['dia']; ?></td>
                                <td><?php echo substr($h['hora_inicio'],0,5); ?> – <?php echo substr($h['hora_fin'],0,5); ?></td>
                                <td><?php echo htmlspecialchars($h['salon']); ?></td>
                                <td>
                                    <form method="POST" action="?estudiante_id=<?php echo $estudiante_id; ?>" 
                                          onsubmit="return confirm('¿Eliminar esta clase?')">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="horario_id" value="<?php echo $h['id']; ?>">
                                        <button type="submit" class="btn-eliminar">🗑</button>
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

<script>
function toggleNuevoModulo() {
    const form = document.getElementById('nuevo-modulo-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') document.getElementById('input-nuevo-modulo').focus();
}

function guardarModulo() {
    const nombre = document.getElementById('input-nuevo-modulo').value.trim();
    const msg = document.getElementById('modulo-msg');
    if (!nombre) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'Escribe el nombre del módulo';
        return;
    }
    const programaId = <?php echo $estudiante_actual ? (int)$estudiante_actual['programa_id'] : 0; ?>;
    if (!programaId) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'Selecciona un estudiante primero';
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'agregar_modulo');
    formData.append('nombre_modulo', nombre);
    formData.append('programa_id', programaId);

    fetch('gestionar_horarios.php?estudiante_id=<?php echo $estudiante_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const select = document.getElementById('select-modulo');
            // Verificar si ya existe en el select
            let existe = false;
            for (let opt of select.options) {
                if (opt.value == data.id) { existe = true; opt.selected = true; break; }
            }
            if (!existe) {
                const opt = new Option(data.nombre, data.id, true, true);
                select.appendChild(opt);
            }
            msg.style.display = 'block';
            msg.style.color = '#059669';
            msg.textContent = data.existia ? 'Módulo ya existía, seleccionado.' : 'Módulo guardado correctamente.';
            document.getElementById('input-nuevo-modulo').value = '';
            setTimeout(() => { document.getElementById('nuevo-modulo-form').style.display = 'none'; msg.style.display = 'none'; }, 1500);
        } else {
            msg.style.display = 'block';
            msg.style.color = '#ef4444';
            msg.textContent = data.error || 'Error al guardar';
        }
    })
    .catch(() => {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'Error de conexión';
    });
}

function eliminarModulo() {
    const select = document.getElementById('select-modulo');
    const moduloId = select.value;
    const moduloNombre = select.options[select.selectedIndex]?.text;
    if (!moduloId) {
        alert('Selecciona un módulo para eliminar.');
        return;
    }
    if (!confirm('¿Eliminar el módulo "' + moduloNombre + '"?\nEsto solo es posible si no tiene clases asignadas.')) return;

    const formData = new FormData();
    formData.append('accion', 'eliminar_modulo');
    formData.append('modulo_id', moduloId);

    fetch('gestionar_horarios.php?estudiante_id=<?php echo $estudiante_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            select.options[select.selectedIndex].remove();
            select.value = '';
            alert('Módulo eliminado correctamente.');
        } else {
            alert(data.error || 'Error al eliminar');
        }
    })
    .catch(() => alert('Error de conexión'));
}

// Permitir guardar con Enter
document.getElementById('input-nuevo-modulo')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); guardarModulo(); }
});
</script>
<script src="/intep/sesion.js"></script>
</body>
</html>