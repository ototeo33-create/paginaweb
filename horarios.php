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

// Obtener horarios del estudiante
$query = "SELECT h.*, m.nombre as materia
          FROM horarios h
          JOIN materias m ON h.materia_id = m.id
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios – INTEP</title>
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .horario-controles {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .vista-toggle {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid var(--verde-muted);
        }
        .vista-btn {
            padding: 0.6rem 1.2rem;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--gray);
            transition: all 0.2s;
        }
        .vista-btn.activo { background: var(--verde); color: white; }
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

        /* NAVEGACIÓN SEMANA */
        .semana-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 1.2rem;
            background: #022C22;
            border-radius: 16px 16px 0 0;
            color: white;
        }
        .semana-nav h3 {
            font-size: 1rem;
            font-weight: 700;
            text-align: center;
        }
        .semana-nav .subtitulo {
            font-size: 0.78rem;
            opacity: 0.7;
            text-align: center;
            margin-top: 2px;
        }
        .nav-btn {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.25);
            color: white;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 600;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .nav-btn:hover { background: var(--verde); border-color: var(--verde); }

        .calendario-semanal {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
            border-top: none;
        }
        .cal-grid {
            display: grid;
            grid-template-columns: 60px repeat(6, 1fr);
            min-width: 700px;
        }
        .cal-header {
            background: #034a34;
            color: white;
            padding: 0.7rem 0.4rem;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .cal-header .dia-nombre { font-size: 0.78rem; opacity: 0.85; }
        .cal-header .dia-num {
            font-size: 1.2rem;
            font-weight: 800;
            display: block;
            margin-top: 2px;
        }
        .cal-header .dia-num.hoy-col {
            background: var(--verde);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }
        .cal-hora {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            padding: 0.5rem;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            border-bottom: 1px solid #e8f5ee;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cal-celda {
            border-left: 1px solid #f0f4f1;
            border-bottom: 1px solid #f0f4f1;
            min-height: 52px;
            position: relative;
            transition: background 0.15s;
        }
        .cal-celda.col-hoy { background: rgba(16, 185, 129, 0.04); }
        .cal-celda:hover { background: var(--verde-muted); }
        .cal-evento {
            position: absolute;
            top: 3px; left: 3px; right: 3px;
            border-radius: 6px;
            padding: 0.3rem 0.5rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            z-index: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: transform 0.15s;
        }
        .cal-evento:hover { transform: scale(1.02); }
        .cal-scroll { overflow-x: auto; }

        /* VISTA MENSUAL */
        .calendario-mensual { display: none; }
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
        .vista-toggle {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
        }
        .seccion-titulo {
            color: #065f46;
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1>INTEP</h1>
    <span class="usuario-info">📅 Horarios</span>
    <a href="logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="dashboard-container">

    <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

    <div class="horario-controles">
        <div class="vista-toggle">
            <button class="vista-btn activo" onclick="cambiarVista('semanal', this)">📋 Semanal</button>
            <button class="vista-btn" onclick="cambiarVista('mensual', this)">📅 Mensual</button>
        </div>

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

    <!-- VISTA SEMANAL -->
    <div id="vista-semanal">
        <div class="seccion-titulo" id="semana-seccion-titulo">
            Horario Semanal
            <?php if ($rol !== 'estudiante' && $estudiante_actual): ?>
                — <?php echo htmlspecialchars($estudiante_actual['nombre']); ?>
            <?php endif; ?>
        </div>
        <?php if (empty($horarios)): ?>
            <div class="bienvenida">
                <p>No hay horarios registrados.
                <?php if (in_array($rol, ['admin','docente'])): ?>
                    <a href="admin/gestionar_horarios.php?estudiante_id=<?php echo $estudiante_id; ?>">Agregar horarios →</a>
                <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
        <!-- Navegación semana -->
        <div class="semana-nav">
            <button class="nav-btn" onclick="cambiarSemana(-1)">← Semana anterior</button>
            <div>
                <h3 id="semana-titulo"></h3>
                <div class="subtitulo" id="semana-rango"></div>
            </div>
            <button class="nav-btn" onclick="cambiarSemana(1)">Semana siguiente →</button>
        </div>
        <div class="cal-scroll">
            <div class="calendario-semanal">
                <div class="cal-grid" id="cal-grid-semanal">
                    <!-- Generado por JS -->
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- VISTA MENSUAL -->
    <div id="vista-mensual" class="calendario-mensual">
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
        <div class="modal-info"><strong>Materia:</strong> <span id="modal-materia"></span></div>
        <div class="modal-info"><strong>Día:</strong> <span id="modal-dia"></span></div>
        <div class="modal-info"><strong>Horario:</strong> <span id="modal-horario"></span></div>
        <div class="modal-info"><strong>Salón:</strong> <span id="modal-salon"></span></div>

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
const horariosData = <?php echo json_encode($horarios_json); ?>;
const colorMap = <?php echo json_encode($color_map); ?>;

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

// Semana: lunes de la semana actual
function getLunesDeHoy() {
    const hoy = new Date();
    const dia = hoy.getDay(); // 0=Dom
    const diff = dia === 0 ? -6 : 1 - dia;
    const lunes = new Date(hoy);
    lunes.setDate(hoy.getDate() + diff);
    lunes.setHours(0,0,0,0);
    return lunes;
}

let semanaBase = getLunesDeHoy();

function cambiarSemana(dir) {
    semanaBase = new Date(semanaBase);
    semanaBase.setDate(semanaBase.getDate() + dir * 7);
    renderSemanal();
}

function pad(n) { return String(n).padStart(2,'0'); }

function formatICSDate(date, hora) {
    const [h, m] = hora.split(':');
    return `${date.getFullYear()}${pad(date.getMonth()+1)}${pad(date.getDate())}T${pad(h)}${pad(m)}00`;
}

function renderSemanal() {
    const hoy = new Date();
    hoy.setHours(0,0,0,0);

    // Calcular fechas de la semana (Lun-Sáb)
    const fechasSemana = {};
    diasOrden.forEach((dia, i) => {
        const f = new Date(semanaBase);
        f.setDate(semanaBase.getDate() + i);
        fechasSemana[dia] = f;
    });

    // Título
    const primerDia = fechasSemana['Lunes'];
    const ultimoDia = fechasSemana['Sábado'];
    const mismoMes = primerDia.getMonth() === ultimoDia.getMonth();
    const titulo = mismoMes
        ? `${mesesNombres[primerDia.getMonth()]} ${primerDia.getFullYear()}`
        : `${mesesNombres[primerDia.getMonth()]} – ${mesesNombres[ultimoDia.getMonth()]} ${ultimoDia.getFullYear()}`;
    const rango = `${primerDia.getDate()} al ${ultimoDia.getDate()} de ${mismoMes ? mesesNombres[primerDia.getMonth()] : (mesesNombres[primerDia.getMonth()]+' – '+mesesNombres[ultimoDia.getMonth()])}`;

    document.getElementById('semana-titulo').textContent = titulo;
    document.getElementById('semana-rango').textContent = rango;

    const grid = document.getElementById('cal-grid-semanal');
    grid.innerHTML = '';

    // Cabecera: columna hora
    const cHora = document.createElement('div');
    cHora.className = 'cal-header';
    cHora.textContent = 'Hora';
    grid.appendChild(cHora);

    // Cabeceras días
    diasOrden.forEach(dia => {
        const fecha = fechasSemana[dia];
        const esHoy = fecha.getTime() === hoy.getTime();
        const cell = document.createElement('div');
        cell.className = 'cal-header';
        const numSpan = document.createElement('span');
        numSpan.className = 'dia-num' + (esHoy ? ' hoy-col' : '');
        numSpan.textContent = fecha.getDate();
        const nombreSpan = document.createElement('div');
        nombreSpan.className = 'dia-nombre';
        nombreSpan.textContent = dia.substring(0,3).toUpperCase();
        cell.appendChild(nombreSpan);
        cell.appendChild(numSpan);
        grid.appendChild(cell);
    });

    // Filas de horas
    horasGrilla.forEach(hora => {
        const horaCell = document.createElement('div');
        horaCell.className = 'cal-hora';
        horaCell.textContent = hora;
        grid.appendChild(horaCell);

        diasOrden.forEach(dia => {
            const fecha = fechasSemana[dia];
            const esHoy = fecha.getTime() === hoy.getTime();
            const celda = document.createElement('div');
            celda.className = 'cal-celda' + (esHoy ? ' col-hoy' : '');

            horariosData.forEach(h => {
                if (h.dia === dia) {
                    const hInicio = h.hora_inicio.substring(0,5);
                    const hFin = h.hora_fin.substring(0,5);
                    if (hInicio.substring(0,2) === hora.substring(0,2)) {
                        const color = colorMap[h.materia_id] || '#25a865';
                        const ev = document.createElement('div');
                        ev.className = 'cal-evento';
                        ev.style.background = color;
                        ev.textContent = h.materia;
                        ev.onclick = () => verDetalle(h.id, h.materia, h.dia, hInicio, hFin, h.salon, fecha);
                        celda.appendChild(ev);
                    }
                }
            });

            grid.appendChild(celda);
        });
    });
}

function cambiarVista(vista, btn) {
    document.querySelectorAll('.vista-btn').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
    document.getElementById('vista-semanal').style.display = vista === 'semanal' ? 'block' : 'none';
    document.getElementById('vista-mensual').style.display = vista === 'mensual' ? 'block' : 'none';
    if (vista === 'mensual') renderMes(mesActual, anioActual);
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

        const div = document.createElement('div');
        div.className = 'mes-dia' + (esHoy ? ' hoy' : '');

        const numDiv = document.createElement('div');
        numDiv.className = 'mes-num' + (esHoy ? ' hoy-num' : '');
        numDiv.textContent = d;
        div.appendChild(numDiv);

        horariosData.forEach(h => {
            if (diasNum[h.dia] === diaSemana) {
                const color = colorMap[h.materia_id] || '#25a865';
                const evento = document.createElement('div');
                evento.className = 'mes-evento';
                evento.style.background = color;
                evento.textContent = h.materia;
                evento.onclick = () => verDetalle(h.id, h.materia, h.dia,
                    h.hora_inicio.substring(0,5), h.hora_fin.substring(0,5), h.salon, fecha);
                div.appendChild(evento);
            }
        });

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

function verDetalle(id, materia, dia, inicio, fin, salon, fechaEvento) {
    document.getElementById('modal-materia').textContent = materia;
    document.getElementById('modal-dia').textContent = dia;
    document.getElementById('modal-horario').textContent = inicio + ' – ' + fin;
    document.getElementById('modal-salon').textContent = salon || 'No asignado';

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

// Inicializar
renderSemanal();
</script>

<script src="/intep/sesion.js"></script>
</body>
</html>
