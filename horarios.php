<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['usuario_rol'];
$estudiante_id = $_SESSION['estudiante_id'];

// Obtener estudiante_id según rol
if ($rol !== 'estudiante') {
    $estudiante_id = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 0;
    if (!$estudiante_id) {
        $primer_est = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id FROM estudiantes WHERE estado = 'activo' LIMIT 1"));
        $estudiante_id = $primer_est['id'] ?? 0;
    }
}

$programa_id = 0;

// Obtener programas para selector (admin/docente)
$programas = [];
if ($rol !== 'estudiante') {
    $res_prog = mysqli_query($conexion, "SELECT * FROM programas ORDER BY nombre ASC");
    while ($p = mysqli_fetch_assoc($res_prog)) $programas[] = $p;
}

// Obtener estudiantes para selector (admin/docente)
$estudiantes = [];
if ($rol !== 'estudiante') {
    $res_est = mysqli_query($conexion, "SELECT e.id, e.nombre, e.documento, p.nombre as programa
                                        FROM estudiantes e
                                        LEFT JOIN programas p ON e.programa_id = p.id
                                        WHERE e.estado = 'activo'
                                        ORDER BY e.nombre ASC");
    while ($e = mysqli_fetch_assoc($res_est)) $estudiantes[] = $e;
}

// Auto-limpiar links virtuales de clases que ya terminaron hoy
date_default_timezone_set('America/Bogota');
$dias_map_es = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$dia_hoy_es = $dias_map_es[date('l')] ?? '';
$hora_actual = date('H:i:s');
if ($dia_hoy_es) {
    $q_clean = "UPDATE horarios SET link_virtual = NULL WHERE dia = ? AND hora_fin <= ? AND link_virtual IS NOT NULL AND link_virtual != ''";
    $stmt_clean = mysqli_prepare($conexion, $q_clean);
    mysqli_stmt_bind_param($stmt_clean, 'ss', $dia_hoy_es, $hora_actual);
    mysqli_stmt_execute($stmt_clean);
}

// Obtener horarios del estudiante con datos de bimestre
$query = "SELECT h.*, m.nombre as materia, b.numero as bimestre_num, b.anio as bimestre_anio,
                 b.fecha_inicio as bim_inicio, b.fecha_fin as bim_fin
          FROM horarios h
          JOIN materias m ON h.materia_id = m.id
          LEFT JOIN bimestres b ON h.bimestre_id = b.id
          WHERE h.estudiante_id = ?
          ORDER BY FIELD(h.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), h.hora_inicio";
$stmt2 = mysqli_prepare($conexion, $query);
mysqli_stmt_bind_param($stmt2, 'i', $estudiante_id);
mysqli_stmt_execute($stmt2);
$resultado = mysqli_stmt_get_result($stmt2);

$horarios = [];
$horarios_json = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $horarios[$fila['dia']][] = $fila;
    $horarios_json[] = $fila;
}

// Obtener fechas importantes
$fechas_importantes = [];
$res_fechas = mysqli_query($conexion, "SELECT * FROM fechas_importantes ORDER BY fecha ASC");
while ($f = mysqli_fetch_assoc($res_fechas)) $fechas_importantes[] = $f;

// Obtener bimestres para el filtro
$bimestres = [];
$res_bim = mysqli_query($conexion, "SELECT * FROM bimestres WHERE estado = 'activo' ORDER BY anio DESC, numero ASC");
while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

$dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$horas = ['07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];

// Colores por materia
$colores = ['#25a865','#2d6bbf','#e84545','#f5a623','#9b59b6','#1abc9c','#e67e22','#e91e63','#3498db','#27ae60'];
$color_map = [];
$color_idx = 0;
foreach ($horarios_json as $h) {
    if (!isset($color_map[$h['materia_id']])) {
        $color_map[$h['materia_id']] = $colores[$color_idx % count($colores)];
        $color_idx++;
    }
}

// Obtener nombre del estudiante actual
$estudiante_actual = null;
if ($rol !== 'estudiante' && $estudiante_id) {
    foreach ($estudiantes as $e) {
        if ($e['id'] == $estudiante_id) {
            $estudiante_actual = $e;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .horario-controles {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .selector-estudiante {
            padding: 0.6rem 1rem;
            border: 2px solid var(--verde-muted);
            border-radius: 10px;
            font-size: 0.88rem;
            outline: none;
            background: white;
            color: var(--dark);
            cursor: pointer;
            min-width: 200px;
        }
        .selector-estudiante:focus { border-color: var(--verde-claro); }
        .estudiante-badge {
            background: var(--verde-muted);
            color: var(--verde);
            padding: 0.4rem 1rem;
            border-radius: 99px;
            font-size: 0.82rem;
            font-weight: 700;
            white-space: nowrap;
        }

        /* VISTA MENSUAL */
        .mes-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: var(--dark);
            color: white;
            border-radius: 16px 16px 0 0;
        }
        .mes-nav h3 { font-size: 1.1rem; font-weight: 700; }
        .mes-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .mes-btn:hover { background: var(--verde); border-color: var(--verde); }
        .mes-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
            border-top: none;
        }
        .mes-dia-header {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            padding: 0.6rem;
            text-align: center;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .mes-dia {
            min-height: 80px;
            padding: 0.4rem;
            border: 1px solid rgba(16, 185, 129, 0.08);
            position: relative;
            background: rgba(255, 255, 255, 0.5);
        }
        .mes-dia.otro-mes { background: rgba(16, 185, 129, 0.03); }
        .mes-dia.hoy { background: rgba(16, 185, 129, 0.1); }
        .mes-num {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--gray);
            margin-bottom: 0.3rem;
        }
        .mes-num.hoy-num {
            background: var(--verde);
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        .mes-evento {
            font-size: 0.68rem;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            color: white;
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
        }
        .mes-evento.fecha-importante {
            cursor: default;
            font-weight: 700;
            font-size: 0.65rem;
            opacity: 0.92;
        }
        .mes-dia.dia-festivo {
            background: rgba(232, 69, 69, 0.06);
        }

        /* Filtro bimestre */
        .filtro-bimestre {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .bim-chip {
            padding: 0.45rem 0.9rem;
            border-radius: 99px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid rgba(16,185,129,0.2);
            background: rgba(255,255,255,0.7);
            color: var(--gray);
            transition: all 0.2s;
        }
        .bim-chip:hover { border-color: var(--verde); color: var(--verde); }
        .bim-chip.activo {
            background: var(--verde);
            color: white;
            border-color: var(--verde);
        }
        .leyenda-fechas {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.8rem;
            padding: 0.6rem 1rem;
            background: rgba(255,255,255,0.6);
            border-radius: 10px;
            font-size: 0.75rem;
        }
        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .leyenda-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
        }

        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.abierto { display: flex; }
        .modal {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.2s ease;
        }
        .modal h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--verde-muted);
        }
        .modal-info { margin-bottom: 0.8rem; font-size: 0.9rem; }
        .modal-info strong { color: var(--verde); }

        /* Botones de agenda */
        .agenda-titulo {
            font-size: 0.78rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 1.2rem 0 0.6rem;
        }
        .agenda-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            margin-bottom: 0.8rem;
        }
        .btn-agenda {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.65rem 0.5rem;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-google {
            background: #4285F4;
            color: white;
        }
        .btn-google:hover { background: #3367d6; }
        .btn-ics {
            background: #1d1d1f;
            color: white;
        }
        .btn-ics:hover { background: #333; }
        .modal-acciones { display: flex; gap: 0.8rem; margin-top: 0.5rem; }
        .modal-close {
            flex: 1;
            background: var(--dark);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
        }
        .modal-close:hover { background: var(--verde); }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @media (max-width: 600px) {
            .horario-controles { flex-direction: column; align-items: flex-start; }
            .agenda-btns { grid-template-columns: 1fr; }
        }

        /* ── Fondo verde desvanecido ── */
        body {
            background: linear-gradient(160deg,
                #e8f8f1 0%,
                #d1fae5 30%,
                #ecfdf5 60%,
                #f0fdf4 100%);
            min-height: 100vh;
        }
.dashboard-container {
            background: transparent;
        }
        .btn-volver {
            background: rgba(255,255,255,0.6);
            border: 1px solid rgba(16,185,129,0.25);
            backdrop-filter: blur(8px);
        }
        .seccion-titulo {
            color: #065f46;
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📅 Horarios</span>
    <a href="logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="dashboard-container">

    <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

    <div class="horario-controles">
        <?php if ($rol !== 'estudiante'): ?>
        <select class="selector-estudiante" onchange="window.location.href='horarios.php?estudiante_id='+this.value">
            <option value="">Selecciona un estudiante</option>
            <?php foreach ($estudiantes as $e): ?>
                <option value="<?php echo $e['id']; ?>" <?php echo $e['id'] == $estudiante_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre']); ?> · <?php echo htmlspecialchars($e['documento']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($estudiante_actual): ?>
            <span class="estudiante-badge">📚 <?php echo htmlspecialchars($estudiante_actual['programa']); ?></span>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array($rol, ['admin','docente'])): ?>
        <a href="admin/gestionar_horarios.php?estudiante_id=<?php echo $estudiante_id; ?>"
           style="background:#059669;color:#ffffff;padding:0.6rem 1.2rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.88rem;display:inline-block;border:2px solid #047857;">
            ➕ Agregar clase
        </a>
        <?php endif; ?>
    </div>

    <!-- Filtro de bimestres -->
    <div class="filtro-bimestre" style="margin-bottom: 1rem;">
        <span style="font-size:0.82rem;font-weight:700;color:#666;">Bimestre:</span>
        <button class="bim-chip activo" onclick="filtrarBimestre(0, this)">Todos</button>
        <?php foreach ($bimestres as $b): ?>
            <button class="bim-chip" onclick="filtrarBimestre(<?php echo $b['id']; ?>, this)"
                    title="<?php echo date('d M', strtotime($b['fecha_inicio'])); ?> – <?php echo date('d M', strtotime($b['fecha_fin'])); ?>">
                B<?php echo $b['numero']; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Leyenda -->
    <div class="leyenda-fechas">
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#e84545;"></div> Festivo</div>
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#e91e63;"></div> Cultural</div>
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#059669;"></div> Institucional</div>
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#25a865;"></div> Clase</div>
    </div>

    <!-- VISTA MENSUAL -->
    <div id="vista-mensual" style="margin-top:1rem;">
        <div class="mes-nav">
            <button class="mes-btn" onclick="cambiarMes(-1)">← Anterior</button>
            <h3 id="mes-titulo"></h3>
            <button class="mes-btn" onclick="cambiarMes(1)">Siguiente →</button>
        </div>
        <div style="overflow-x:auto;">
            <div class="mes-grid" id="mes-grid" style="min-width:560px;">
                <?php
                $dias_semana = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
                foreach ($dias_semana as $d) {
                    echo "<div class='mes-dia-header'>{$d}</div>";
                }
                ?>
            </div>
        </div>
    </div>

</div>

<!-- MODAL DETALLE -->
<div class="modal-overlay" id="modal-overlay" onclick="cerrarModal(event)">
    <div class="modal">
        <h3>📚 Detalle de Clase</h3>
        <div class="modal-info"><strong>Módulo:</strong> <span id="modal-materia"></span></div>
        <div class="modal-info"><strong>Día:</strong> <span id="modal-dia"></span></div>
        <div class="modal-info"><strong>Horario:</strong> <span id="modal-horario"></span></div>
        <div class="modal-info"><strong>Salón:</strong> <span id="modal-salon"></span></div>
        <div class="modal-info"><strong>Bimestre:</strong> <span id="modal-bimestre"></span></div>
        <div id="modal-link-row" style="display:none; margin-top:12px;">
            <a id="modal-link" href="#" target="_blank" style="
                display:flex; align-items:center; gap:10px;
                background:linear-gradient(135deg,#059669,#10B981);
                color:white; text-decoration:none;
                padding:14px 20px; border-radius:12px;
                font-weight:700; font-size:0.92rem;
                box-shadow:0 4px 15px rgba(5,150,105,0.3);
                transition:all 0.2s;
            " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(5,150,105,0.4)'" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 15px rgba(5,150,105,0.3)'">
                <span style="font-size:1.4rem;">📹</span>
                <span>
                    <span style="display:block;font-size:0.72rem;opacity:0.8;font-weight:400;letter-spacing:1px;text-transform:uppercase;">Clase Virtual Disponible</span>
                    <span style="display:block;">Unirse a la clase →</span>
                </span>
            </a>
        </div>

        <div class="agenda-titulo">📆 Agregar a mi agenda</div>
        <div class="agenda-btns">
            <a id="btn-google-cal" href="#" target="_blank" class="btn-agenda btn-google">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.418 0-8-3.582-8-8s3.582-8 8-8 8 3.582 8 8-3.582 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                Google Calendar
            </a>
            <button id="btn-ics" onclick="descargarICS()" class="btn-agenda btn-ics">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                iPhone / Android
            </button>
        </div>

        <?php if (in_array($rol, ['admin','docente'])): ?>
        <div class="modal-acciones">
            <a id="modal-editar" href="#" style="flex:1;background:var(--verde);color:white;padding:0.7rem;border-radius:8px;text-align:center;text-decoration:none;font-weight:700;font-size:0.88rem;">✏️ Editar</a>
            <button onclick="cerrarModal()" class="modal-close" style="flex:1;">Cerrar</button>
        </div>
        <?php else: ?>
        <button class="modal-close" onclick="cerrarModal()">Cerrar</button>
        <?php endif; ?>
    </div>
</div>

<script>
const horariosData = <?php echo json_encode($horarios_json, JSON_UNESCAPED_UNICODE); ?>;
const colorMap = <?php echo json_encode($color_map, JSON_UNESCAPED_UNICODE); ?>;
const fechasImportantes = <?php echo json_encode($fechas_importantes, JSON_UNESCAPED_UNICODE); ?>;
const bimestresData = <?php echo json_encode($bimestres, JSON_UNESCAPED_UNICODE); ?>;

const diasNum = {
    'Lunes': 1, 'Martes': 2, 'Miércoles': 3,
    'Jueves': 4, 'Viernes': 5, 'Sábado': 6
};
const byday = {
    'Lunes':'MO','Martes':'TU','Miércoles':'WE',
    'Jueves':'TH','Viernes':'FR','Sábado':'SA'
};
const mesesNombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                      'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const horasGrilla = ['07:00','08:00','09:00','10:00','11:00','12:00',
                     '13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];
const diasOrden = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

let mesActual = new Date().getMonth();
let anioActual = new Date().getFullYear();
let bimestreFiltro = 0; // 0 = todos

function pad(n) { return String(n).padStart(2,'0'); }

function formatICSDate(date, hora) {
    const [h, m] = hora.split(':');
    return `${date.getFullYear()}${pad(date.getMonth()+1)}${pad(date.getDate())}T${pad(h)}${pad(m)}00`;
}

// Verificar si una fecha está dentro del rango de un bimestre
function fechaEnBimestre(fecha, bimInicio, bimFin) {
    if (!bimInicio || !bimFin) return true; // Sin bimestre asignado, mostrar siempre
    const f = new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate());
    const inicio = new Date(bimInicio + 'T00:00:00');
    const fin = new Date(bimFin + 'T23:59:59');
    return f >= inicio && f <= fin;
}

function filtrarBimestre(bimId, btn) {
    bimestreFiltro = bimId;
    document.querySelectorAll('.bim-chip').forEach(c => c.classList.remove('activo'));
    btn.classList.add('activo');

    // Navegar al mes de inicio del bimestre seleccionado
    if (bimId > 0) {
        const bim = bimestresData.find(b => b.id == bimId);
        if (bim) {
            const fechaInicio = new Date(bim.fecha_inicio + 'T00:00:00');
            mesActual = fechaInicio.getMonth();
            anioActual = fechaInicio.getFullYear();
        }
    }

    renderMes(mesActual, anioActual);
}

function cambiarMes(dir) {
    mesActual += dir;
    if (mesActual > 11) { mesActual = 0; anioActual++; }
    if (mesActual < 0) { mesActual = 11; anioActual--; }
    renderMes(mesActual, anioActual);
}

function renderMes(mes, anio) {
    document.getElementById('mes-titulo').textContent = mesesNombres[mes] + ' ' + anio;
    const grid = document.getElementById('mes-grid');
    const headers = Array.from(grid.querySelectorAll('.mes-dia-header'));
    grid.innerHTML = '';
    headers.forEach(h => grid.appendChild(h.cloneNode(true)));

    const primerDia = new Date(anio, mes, 1).getDay();
    const diasEnMes = new Date(anio, mes + 1, 0).getDate();
    const hoy = new Date();
    const diasMesAnterior = new Date(anio, mes, 0).getDate();

    // Indexar fechas importantes por fecha string (YYYY-MM-DD)
    const fechasIdx = {};
    fechasImportantes.forEach(f => {
        if (!fechasIdx[f.fecha]) fechasIdx[f.fecha] = [];
        fechasIdx[f.fecha].push(f);
    });

    for (let i = primerDia - 1; i >= 0; i--) {
        const div = document.createElement('div');
        div.className = 'mes-dia otro-mes';
        div.innerHTML = `<div class="mes-num">${diasMesAnterior - i}</div>`;
        grid.appendChild(div);
    }

    for (let d = 1; d <= diasEnMes; d++) {
        const fecha = new Date(anio, mes, d);
        const diaSemana = fecha.getDay();
        const esHoy = d === hoy.getDate() && mes === hoy.getMonth() && anio === hoy.getFullYear();
        const fechaStr = `${anio}-${pad(mes+1)}-${pad(d)}`;

        // Verificar si es día festivo
        const esFestivo = fechasIdx[fechaStr] && fechasIdx[fechaStr].some(f => f.tipo === 'festivo');

        const div = document.createElement('div');
        div.className = 'mes-dia' + (esHoy ? ' hoy' : '') + (esFestivo ? ' dia-festivo' : '');

        const numDiv = document.createElement('div');
        numDiv.className = 'mes-num' + (esHoy ? ' hoy-num' : '');
        numDiv.textContent = d;
        div.appendChild(numDiv);

        // Mostrar fechas importantes
        if (fechasIdx[fechaStr]) {
            fechasIdx[fechaStr].forEach(fi => {
                const evento = document.createElement('div');
                evento.className = 'mes-evento fecha-importante';
                evento.style.background = fi.color || '#e84545';
                evento.textContent = fi.nombre;
                evento.title = fi.nombre + ' (' + fi.tipo + ')';
                div.appendChild(evento);
            });
        }

        // Mostrar clases (solo si la fecha cae dentro del bimestre asignado y no es festivo)
        if (!esFestivo) {
            horariosData.forEach(h => {
                if (diasNum[h.dia] === diaSemana) {
                    // Filtrar por bimestre seleccionado
                    if (bimestreFiltro > 0 && h.bimestre_id != bimestreFiltro) return;

                    // Solo mostrar si la fecha cae dentro del rango del bimestre
                    if (h.bim_inicio && h.bim_fin) {
                        if (!fechaEnBimestre(fecha, h.bim_inicio, h.bim_fin)) return;
                    }

                    const color = colorMap[h.materia_id] || '#25a865';
                    const evento = document.createElement('div');
                    evento.className = 'mes-evento';
                    evento.style.background = color;
                    evento.textContent = h.materia;
                    evento.title = h.materia + ' · ' + h.hora_inicio.substring(0,5) + '-' + h.hora_fin.substring(0,5);
                    evento.onclick = () => verDetalle(h.id, h.materia, h.dia,
                        h.hora_inicio.substring(0,5), h.hora_fin.substring(0,5), h.salon, fecha,
                        h.bimestre_num, h.bim_inicio, h.bim_fin, h.link_virtual);
                    div.appendChild(evento);
                }
            });
        }

        grid.appendChild(div);
    }

    const totalCeldas = grid.children.length - 7;
    const celdasRestantes = 7 - (totalCeldas % 7);
    if (celdasRestantes < 7) {
        for (let i = 1; i <= celdasRestantes; i++) {
            const div = document.createElement('div');
            div.className = 'mes-dia otro-mes';
            div.innerHTML = `<div class="mes-num">${i}</div>`;
            grid.appendChild(div);
        }
    }
}

// Estado del modal
let modalData = {};

function verDetalle(id, materia, dia, inicio, fin, salon, fechaEvento, bimestreNum, bimInicio, bimFin, linkVirtual) {
    document.getElementById('modal-materia').textContent = materia;
    document.getElementById('modal-dia').textContent = dia;
    document.getElementById('modal-horario').textContent = inicio + ' – ' + fin;
    document.getElementById('modal-salon').textContent = salon || 'No asignado';

    const bimInfo = document.getElementById('modal-bimestre');
    if (bimInfo) {
        if (bimestreNum) {
            bimInfo.textContent = 'Bimestre ' + bimestreNum + ' (' + bimInicio + ' a ' + bimFin + ')';
            bimInfo.parentElement.style.display = '';
        } else {
            bimInfo.parentElement.style.display = 'none';
        }
    }

    const linkRow = document.getElementById('modal-link-row');
    const linkEl = document.getElementById('modal-link');
    if (linkVirtual) {
        linkEl.href = linkVirtual;
        linkRow.style.display = '';
    } else {
        linkRow.style.display = 'none';
    }

    const editarBtn = document.getElementById('modal-editar');
    if (editarBtn) editarBtn.href = 'admin/gestionar_horarios.php?estudiante_id=<?php echo $estudiante_id; ?>&editar=' + id;

    // Guardar datos para agenda
    modalData = { materia, dia, inicio, fin, salon, fechaEvento: fechaEvento || null };

    // Generar link Google Calendar
    generarLinkGoogle();

    document.getElementById('modal-overlay').classList.add('abierto');
}

function generarLinkGoogle() {
    const { materia, dia, inicio, fin, salon, fechaEvento } = modalData;

    // Usar la fecha del evento si está disponible, si no usar próximo día de la semana
    let fechaBase = fechaEvento ? new Date(fechaEvento) : proximaFechaDia(dia);

    const dtStart = formatICSDate(fechaBase, inicio);
    const dtEnd   = formatICSDate(fechaBase, fin);
    const byDay   = byday[dia] || 'MO';

    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: `${materia} – INTEP`,
        dates: `${dtStart}/${dtEnd}`,
        details: `Salón: ${salon || 'No asignado'}\nInstituto INTEP`,
        location: 'Instituto INTEP, Madrid, Cundinamarca',
        recur: `RRULE:FREQ=WEEKLY;BYDAY=${byDay}`
    });
    document.getElementById('btn-google-cal').href =
        'https://calendar.google.com/calendar/render?' + params.toString();
}

function proximaFechaDia(nombreDia) {
    const objetivo = diasNum[nombreDia] || 1;
    const hoy = new Date();
    const diaActual = hoy.getDay();
    let diff = objetivo - diaActual;
    if (diff <= 0) diff += 7;
    const result = new Date(hoy);
    result.setDate(hoy.getDate() + diff);
    return result;
}

function descargarICS() {
    const { materia, dia, inicio, fin, salon, fechaEvento } = modalData;
    let fechaBase = fechaEvento ? new Date(fechaEvento) : proximaFechaDia(dia);
    const byDay = byday[dia] || 'MO';

    const dtStart = formatICSDate(fechaBase, inicio);
    const dtEnd   = formatICSDate(fechaBase, fin);
    const uid     = `intep-${Date.now()}@intep.edu.co`;

    const ics = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//INTEP//Portal Estudiantil//ES',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        `UID:${uid}`,
        `DTSTART:${dtStart}`,
        `DTEND:${dtEnd}`,
        `RRULE:FREQ=WEEKLY;BYDAY=${byDay}`,
        `SUMMARY:${materia} – INTEP`,
        `LOCATION:${salon || 'Instituto INTEP'}, Madrid, Cundinamarca`,
        `DESCRIPTION:Clase semanal de ${materia}\\nSalón: ${salon || 'No asignado'}\\nInstituto INTEP`,
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');

    const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `${materia.replace(/\s+/g,'-')}-INTEP.ics`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function cerrarModal(e) {
    if (!e || e.target === document.getElementById('modal-overlay')) {
        document.getElementById('modal-overlay').classList.remove('abierto');
    }
}

// Inicializar vista mensual
renderMes(mesActual, anioActual);
</script>

<script src="/intep/sesion.js"></script>
</body>
</html>
