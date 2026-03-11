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

// Obtener materias del programa del estudiante
$materias = [];
if ($estudiante_actual) {
    $q_mat = "SELECT * FROM materias WHERE programa_id = ? ORDER BY nombre ASC";
    $stmt_mat = mysqli_prepare($conexion, $q_mat);
    mysqli_stmt_bind_param($stmt_mat, 'i', $estudiante_actual['programa_id']);
    mysqli_stmt_execute($stmt_mat);
    $res_mat = mysqli_stmt_get_result($stmt_mat);
    while ($m = mysqli_fetch_assoc($res_mat)) $materias[] = $m;
}

// Obtener horarios del estudiante seleccionado
$horarios = [];
$q_hor = "SELECT h.*, m.nombre as materia 
          FROM horarios h 
          JOIN materias m ON h.materia_id = m.id 
          WHERE h.estudiante_id = ?
          ORDER BY FIELD(h.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), h.hora_inicio";
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
        $dia = $_POST['dia'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];
        $salon = trim($_POST['salon']);

        $q = "INSERT INTO horarios (programa_id, estudiante_id, materia_id, dia, hora_inicio, hora_fin, salon) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'iiissss', 
            $estudiante_actual['programa_id'], $estudiante_id, 
            $materia_id, $dia, $hora_inicio, $hora_fin, $salon);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Clase agregada correctamente al horario del estudiante.';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Horarios – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
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
        @media(max-width:700px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1>INTEP</h1>
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
                    <label>Materia</label>
                    <select name="materia_id" required>
                        <option value="">Selecciona una materia</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-admin">
                    <label>Día</label>
                    <select name="dia" required>
                        <option value="">Selecciona un día</option>
                        <?php foreach ($dias as $d): ?>
                            <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-horas">
                    <div class="campo-admin">
                        <label>Hora inicio</label>
                        <input type="time" name="hora_inicio" required>
                    </div>
                    <div class="campo-admin">
                        <label>Hora fin</label>
                        <input type="time" name="hora_fin" required>
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
                                <th>Materia</th>
                                <th>Día</th>
                                <th>Horario</th>
                                <th>Salón</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios as $h): ?>
                            <tr>
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

<script src="/intep/sesion.js"></script>
</body>
</html>