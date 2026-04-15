<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../login.php');
    exit;
}

$es_admin = $_SESSION['usuario_rol'] === 'admin';
$usuario_id = (int)$_SESSION['usuario_id'];
$nombre = sanitizeInput($_SESSION['usuario_nombre']);

// Determinar docente a mostrar
if ($es_admin) {
    $docente_id = (int)($_GET['docente_id'] ?? 0);
    if (!$docente_id) {
        header('Location: eval_admin.php');
        exit;
    }
} else {
    $docente_id = $usuario_id;
}

// Obtener nombre del docente
$stmt = mysqli_prepare($conexion, "SELECT username FROM usuarios WHERE id = ? AND rol = 'docente'");
mysqli_stmt_bind_param($stmt, 'i', $docente_id);
mysqli_stmt_execute($stmt);
$doc = mysqli_stmt_get_result($stmt)->fetch_assoc();
if (!$doc) {
    header('Location: ' . ($es_admin ? 'eval_admin.php' : '../dashboard.php'));
    exit;
}
$docente_nombre = $doc['username'];

// Periodos disponibles para este docente
$stmt = mysqli_prepare($conexion, "SELECT DISTINCT periodo FROM eval_docente WHERE docente_id = ? ORDER BY periodo DESC");
mysqli_stmt_bind_param($stmt, 'i', $docente_id);
mysqli_stmt_execute($stmt);
$res_p = mysqli_stmt_get_result($stmt);
$periodos = [];
while ($p = mysqli_fetch_assoc($res_p)) { $periodos[] = $p['periodo']; }

$periodo_sel = $_GET['periodo'] ?? ($periodos[0] ?? '');

// Estadisticas generales del docente en el periodo seleccionado
$resumen = ['total' => 0, 'promedio' => 0, 'porcentaje' => 0, 'minimo' => 0, 'maximo' => 0];
if ($periodo_sel) {
    $stmt = mysqli_prepare($conexion,
        "SELECT COUNT(*) as total, ROUND(AVG(puntaje_total),2) as promedio_pts,
                ROUND(AVG(porcentaje),1) as porcentaje, MIN(porcentaje) as minimo, MAX(porcentaje) as maximo
         FROM eval_docente WHERE docente_id = ? AND periodo = ?");
    mysqli_stmt_bind_param($stmt, 'is', $docente_id, $periodo_sel);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt)->fetch_assoc();
    $resumen['total'] = (int)$r['total'];
    $resumen['promedio'] = round((float)$r['promedio_pts'], 2);
    $resumen['porcentaje'] = round((float)$r['porcentaje'], 1);
    $resumen['minimo'] = round((float)$r['minimo'], 1);
    $resumen['maximo'] = round((float)$r['maximo'], 1);
}

// Promedio por criterio
$criterios_nombres = [
    1 => 'Dominio del Contenido',
    2 => 'Claridad en la Explicacion',
    3 => 'Metodologia de Ensenanza',
    4 => 'Relacion con los Estudiantes',
    5 => 'Gestion del Aula',
    6 => 'Evaluacion y Retroalimentacion',
    7 => 'Puntualidad y Asistencia',
    8 => 'Uso de Recursos Tecnologicos'
];

$criterios_data = [];
if ($periodo_sel && $resumen['total'] > 0) {
    $stmt = mysqli_prepare($conexion,
        "SELECT er.criterio_id, ROUND(AVG(er.calificacion),2) as promedio, COUNT(*) as total,
                SUM(CASE WHEN er.calificacion = 4 THEN 1 ELSE 0 END) as c4,
                SUM(CASE WHEN er.calificacion = 3 THEN 1 ELSE 0 END) as c3,
                SUM(CASE WHEN er.calificacion = 2 THEN 1 ELSE 0 END) as c2,
                SUM(CASE WHEN er.calificacion = 1 THEN 1 ELSE 0 END) as c1
         FROM eval_respuestas er
         JOIN eval_docente ed ON er.evaluacion_id = ed.id
         WHERE ed.docente_id = ? AND ed.periodo = ?
         GROUP BY er.criterio_id
         ORDER BY er.criterio_id");
    mysqli_stmt_bind_param($stmt, 'is', $docente_id, $periodo_sel);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $criterios_data[(int)$row['criterio_id']] = $row;
    }
}

// Comentarios
$comentarios = [];
if ($periodo_sel && $resumen['total'] > 0) {
    $stmt = mysqli_prepare($conexion,
        "SELECT comentarios_positivos, comentarios_mejora, created_at
         FROM eval_docente WHERE docente_id = ? AND periodo = ?
         AND (comentarios_positivos != '' OR comentarios_mejora != '')
         ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, 'is', $docente_id, $periodo_sel);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) { $comentarios[] = $row; }
}

// Tendencia por periodo (todos los periodos)
$tendencia = [];
$stmt = mysqli_prepare($conexion,
    "SELECT periodo, ROUND(AVG(porcentaje),1) as promedio, COUNT(*) as total
     FROM eval_docente WHERE docente_id = ? GROUP BY periodo ORDER BY periodo");
mysqli_stmt_bind_param($stmt, 'i', $docente_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) { $tendencia[] = $row; }

// Promedio institucional para comparativa
$promedio_inst = 0;
if ($periodo_sel) {
    $stmt = mysqli_prepare($conexion, "SELECT ROUND(AVG(porcentaje),1) as p FROM eval_docente WHERE periodo = ?");
    mysqli_stmt_bind_param($stmt, 's', $periodo_sel);
    mysqli_stmt_execute($stmt);
    $promedio_inst = (float)(mysqli_stmt_get_result($stmt)->fetch_assoc()['p'] ?? 0);
}

function badgeClase($pct) {
    if ($pct >= 90) return 'excellent';
    if ($pct >= 75) return 'good';
    if ($pct >= 50) return 'regular';
    return 'insufficient';
}
function badgeTexto($pct) {
    if ($pct >= 90) return 'Excelente';
    if ($pct >= 75) return 'Bueno';
    if ($pct >= 50) return 'Regular';
    return 'Insuficiente';
}
function barColor($avg) {
    if ($avg >= 3.5) return 'var(--green)';
    if ($avg >= 2.5) return '#3B82F6';
    if ($avg >= 1.5) return '#f59e0b';
    return '#ef4444';
}
function motivacional($pct) {
    if ($pct >= 90) return ['icon' => 'fa-trophy', 'class' => 'excellent', 'titulo' => 'Felicidades! Eres un docente excepcional!', 'msg' => 'Tu dedicacion y compromiso se reflejan en cada evaluacion. Tus estudiantes reconocen tu excelente labor pedagogica. Sigue asi!'];
    if ($pct >= 75) return ['icon' => 'fa-star', 'class' => 'good', 'titulo' => 'Muy bien! Estas en el camino correcto', 'msg' => 'Tus estudiantes valoran tu trabajo. Con algunos ajustes puedes alcanzar la excelencia. Tu esfuerzo se nota!'];
    if ($pct >= 50) return ['icon' => 'fa-chart-line', 'class' => 'regular', 'titulo' => 'Tienes potencial, animate a mejorar!', 'msg' => 'Hay areas de oportunidad que puedes fortalecer. Revisa los criterios con menor puntuacion y busca estrategias de mejora. El INTEP te apoya!'];
    return ['icon' => 'fa-seedling', 'class' => 'insufficient', 'titulo' => 'Este es el momento de reinventarte', 'msg' => 'Los resultados indican que hay aspectos importantes a trabajar. Acercate a la coordinacion academica para recibir apoyo y capacitacion.'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados Evaluacion - <?php echo sanitizeInput($docente_nombre); ?> - INTEP</title>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --green:#059669; --green-light:#10B981; --green-dark:#047857; --green-pale:#ECFDF5;
            --purple:#4A1942; --purple-mid:#6B3FA0; --purple-light:#9B6FCF; --purple-pale:#f3ecf8;
            --cream:#F5F2EC; --text:#2D2235; --text-mid:#5A4D66; --text-light:#8A7D96; --radius:16px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Exo 2',sans-serif; background:var(--cream); color:var(--text); min-height:100vh; }

        .topbar {
            background:linear-gradient(135deg,#033d2e 0%,var(--green-dark) 50%,#0a5c3f 100%);
            color:white; padding:15px 25px; display:flex; align-items:center; justify-content:space-between;
            position:sticky; top:0; z-index:100;
        }
        .topbar-left { display:flex; align-items:center; gap:12px; }
        .topbar-left a { color:white; text-decoration:none; font-size:1.4em; }
        .topbar-left h1 { font-size:1.1em; font-weight:700; }
        .topbar-right { font-size:0.85em; opacity:0.8; }

        .container { max-width:1000px; margin:0 auto; padding:25px 20px 40px; }

        .card {
            background:white; border-radius:var(--radius); padding:30px; margin-bottom:25px;
            box-shadow:0 4px 20px rgba(74,25,66,0.06); border:1px solid rgba(74,25,66,0.05);
        }
        .card-title { font-size:1.15em; font-weight:700; color:var(--purple); margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .card-title i { color:var(--green); }

        /* Docente header */
        .docente-header { display:flex; align-items:center; gap:20px; margin-bottom:20px; flex-wrap:wrap; }
        .docente-avatar {
            width:60px; height:60px; background:var(--green); color:white; border-radius:50%;
            display:flex; align-items:center; justify-content:center; font-size:1.5em; font-weight:700;
        }
        .docente-info h2 { font-size:1.4em; color:var(--purple); }
        .docente-info p { color:var(--text-mid); font-size:0.9em; }

        /* Score cards */
        .scores-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px; }
        .score-card {
            border-radius:var(--radius); padding:25px; text-align:center; color:white;
        }
        .score-card.green-gradient { background:linear-gradient(135deg,#033d2e,var(--green-dark)); }
        .score-card.purple-gradient { background:linear-gradient(135deg,var(--purple),var(--purple-mid)); }
        .score-big { font-size:3em; font-weight:800; line-height:1; }
        .score-sub { font-size:0.85em; opacity:0.8; margin-top:5px; }
        .score-badge { display:inline-block; padding:5px 15px; border-radius:20px; font-weight:600; font-size:0.85em; margin-top:10px; }
        .score-badge.excellent { background:rgba(255,255,255,0.2); }
        .score-badge.good { background:rgba(255,255,255,0.2); }
        .score-badge.regular { background:rgba(255,255,255,0.2); }
        .score-badge.insufficient { background:rgba(255,255,255,0.2); }

        /* Comparativa institucional */
        .comparativa {
            display:flex; gap:20px; align-items:center; padding:15px 20px; border-radius:12px;
            background:var(--purple-pale); margin-bottom:25px; flex-wrap:wrap;
        }
        .comparativa .item { text-align:center; flex:1; min-width:120px; }
        .comparativa .item .val { font-size:1.8em; font-weight:800; }
        .comparativa .item .lbl { font-size:0.8em; color:var(--text-mid); }
        .comparativa .vs { font-size:1.5em; color:var(--purple-mid); font-weight:700; }

        /* Periodo selector */
        .periodo-selector { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .periodo-btn {
            padding:8px 18px; border:2px solid #e0e0e0; border-radius:20px; background:white;
            cursor:pointer; font-family:'Exo 2',sans-serif; font-weight:600; font-size:0.85em;
            text-decoration:none; color:var(--text); transition:0.3s;
        }
        .periodo-btn.active { border-color:var(--green); background:var(--green-pale); color:var(--green-dark); }
        .periodo-btn:hover { border-color:var(--green); }

        /* Criterios */
        .criterio-item { padding:15px 0; border-bottom:1px solid #eee; }
        .criterio-item:last-child { border-bottom:none; }
        .criterio-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
        .criterio-name { font-weight:600; font-size:0.95em; }
        .criterio-score { font-weight:700; font-size:0.95em; }
        .progress-bar { height:10px; background:#eee; border-radius:5px; overflow:hidden; }
        .progress-fill { height:100%; border-radius:5px; transition:width 0.8s ease; }
        .distribucion { display:flex; gap:10px; margin-top:8px; font-size:0.75em; color:var(--text-light); }
        .distribucion span { display:flex; align-items:center; gap:3px; }

        /* Comentarios */
        .comment-item {
            padding:15px; border-radius:12px; margin-bottom:12px; border-left:4px solid;
        }
        .comment-item.positive { background:var(--green-pale); border-color:var(--green); }
        .comment-item.improvement { background:#fef3c7; border-color:#f59e0b; }
        .comment-label { font-size:0.8em; font-weight:600; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
        .comment-label.positive { color:var(--green-dark); }
        .comment-label.improvement { color:#b45309; }
        .comment-text { font-size:0.9em; color:var(--text); line-height:1.5; }

        /* Motivacional */
        .motivacional {
            border-radius:var(--radius); padding:35px; text-align:center;
        }
        .motivacional.excellent { background:linear-gradient(135deg,var(--green-pale),#d1fae5); border:2px solid var(--green); }
        .motivacional.good { background:linear-gradient(135deg,#dbeafe,#bfdbfe); border:2px solid #3B82F6; }
        .motivacional.regular { background:linear-gradient(135deg,#fef3c7,#fde68a); border:2px solid #f59e0b; }
        .motivacional.insufficient { background:linear-gradient(135deg,var(--purple-pale),#e9d5f5); border:2px solid var(--purple-mid); }
        .motivacional .icon { font-size:3em; margin-bottom:15px; }
        .motivacional.excellent .icon { color:var(--green); }
        .motivacional.good .icon { color:#3B82F6; }
        .motivacional.regular .icon { color:#f59e0b; }
        .motivacional.insufficient .icon { color:var(--purple-mid); }
        .motivacional h3 { font-size:1.3em; color:var(--purple); margin-bottom:10px; }
        .motivacional p { color:var(--text-mid); line-height:1.6; max-width:600px; margin:0 auto; }

        /* Tendencia */
        .tendencia-grid { display:flex; gap:15px; flex-wrap:wrap; }
        .tendencia-item {
            flex:1; min-width:120px; text-align:center; padding:15px; border-radius:12px;
            border:1px solid #eee; background:white;
        }
        .tendencia-item .periodo-label { font-size:0.8em; color:var(--text-mid); margin-bottom:5px; }
        .tendencia-item .t-val { font-size:1.8em; font-weight:800; }
        .tendencia-item .t-count { font-size:0.75em; color:var(--text-light); }

        .empty-state { text-align:center; padding:40px; color:var(--text-light); }
        .empty-state i { font-size:3em; margin-bottom:15px; display:block; }

        @media (max-width:768px) {
            .scores-row { grid-template-columns:1fr; }
            .comparativa { flex-direction:column; }
            .comparativa .vs { display:none; }
        }

        @media print {
            .topbar { display:none; }
            .card { box-shadow:none; border:1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <a href="<?php echo $es_admin ? 'eval_admin.php' : '../dashboard.php'; ?>"><i class="fas fa-arrow-left"></i></a>
            <h1>Resultados de Evaluacion</h1>
        </div>
        <div class="topbar-right"><?php echo $nombre; ?> &middot; <?php echo ucfirst($_SESSION['usuario_rol']); ?></div>
    </div>

    <div class="container">
        <!-- Info del docente -->
        <div class="card">
            <div class="docente-header">
                <div class="docente-avatar"><?php echo strtoupper(substr($docente_nombre, 0, 1)); ?></div>
                <div class="docente-info">
                    <h2><?php echo sanitizeInput($docente_nombre); ?></h2>
                    <p><i class="fas fa-chalkboard-teacher"></i> Docente INTEP &middot; Periodo: <?php echo sanitizeInput($periodo_sel ?: 'Sin evaluaciones'); ?></p>
                </div>
            </div>

            <?php if (count($periodos) > 1): ?>
            <div class="periodo-selector">
                <?php foreach ($periodos as $p): ?>
                    <a href="?docente_id=<?php echo $docente_id; ?>&periodo=<?php echo urlencode($p); ?>" class="periodo-btn <?php echo $p === $periodo_sel ? 'active' : ''; ?>">
                        <?php echo sanitizeInput($p); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($resumen['total'] === 0): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Sin evaluaciones</h3>
                    <p>Este docente no tiene evaluaciones registradas<?php echo $periodo_sel ? ' para el periodo ' . sanitizeInput($periodo_sel) : ''; ?>.</p>
                </div>
            </div>
        <?php else: ?>

        <!-- Puntajes principales -->
        <div class="scores-row">
            <div class="score-card green-gradient">
                <div class="score-big"><?php echo $resumen['porcentaje']; ?>%</div>
                <div class="score-sub">Nota Final (basada en <?php echo $resumen['total']; ?> evaluaciones)</div>
                <div class="score-badge <?php echo badgeClase($resumen['porcentaje']); ?>"><?php echo badgeTexto($resumen['porcentaje']); ?></div>
            </div>
            <div class="score-card purple-gradient">
                <div class="score-big"><?php echo round($resumen['promedio'] / 8, 2); ?>/4</div>
                <div class="score-sub">Promedio General por Criterio</div>
                <div class="score-badge"><?php echo $resumen['promedio']; ?>/32 pts totales</div>
            </div>
        </div>

        <!-- Comparativa institucional -->
        <div class="comparativa">
            <div class="item">
                <div class="val" style="color:var(--green);"><?php echo $resumen['porcentaje']; ?>%</div>
                <div class="lbl">Este docente</div>
            </div>
            <div class="vs">vs</div>
            <div class="item">
                <div class="val" style="color:var(--purple);"><?php echo $promedio_inst; ?>%</div>
                <div class="lbl">Promedio Institucional</div>
            </div>
            <div class="item">
                <div class="val" style="color:<?php echo $resumen['porcentaje'] >= $promedio_inst ? 'var(--green)' : '#ef4444'; ?>;">
                    <?php $diff = round($resumen['porcentaje'] - $promedio_inst, 1); echo ($diff >= 0 ? '+' : '') . $diff; ?>%
                </div>
                <div class="lbl">Diferencia</div>
            </div>
        </div>

        <!-- Resultados por criterio -->
        <div class="card">
            <div class="card-title"><i class="fas fa-clipboard-check"></i> Resultados por Criterio</div>
            <?php for ($i = 1; $i <= 8; $i++):
                $cd = $criterios_data[$i] ?? null;
                $avg = $cd ? round((float)$cd['promedio'], 2) : 0;
                $pct_bar = $avg > 0 ? round(($avg / 4) * 100) : 0;
            ?>
            <div class="criterio-item">
                <div class="criterio-top">
                    <span class="criterio-name"><?php echo $criterios_nombres[$i]; ?></span>
                    <span class="criterio-score" style="color:<?php echo barColor($avg); ?>"><?php echo $avg; ?>/4</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?php echo $pct_bar; ?>%;background:<?php echo barColor($avg); ?>;"></div>
                </div>
                <?php if ($cd): ?>
                <div class="distribucion">
                    <span style="color:var(--green);">Exc: <?php echo $cd['c4']; ?></span>
                    <span style="color:#3B82F6;">Bueno: <?php echo $cd['c3']; ?></span>
                    <span style="color:#f59e0b;">Reg: <?php echo $cd['c2']; ?></span>
                    <span style="color:#ef4444;">Insuf: <?php echo $cd['c1']; ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Tendencia por periodo -->
        <?php if (count($tendencia) > 1): ?>
        <div class="card">
            <div class="card-title"><i class="fas fa-chart-line"></i> Tendencia por Periodo</div>
            <div class="tendencia-grid">
                <?php foreach ($tendencia as $t): ?>
                <div class="tendencia-item">
                    <div class="periodo-label"><?php echo sanitizeInput($t['periodo']); ?></div>
                    <div class="t-val" style="color:<?php echo $t['promedio'] >= 75 ? 'var(--green)' : ($t['promedio'] >= 50 ? '#f59e0b' : '#ef4444'); ?>">
                        <?php echo $t['promedio']; ?>%
                    </div>
                    <div class="t-count"><?php echo $t['total']; ?> evaluaciones</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Comentarios -->
        <div class="card">
            <div class="card-title"><i class="fas fa-comments"></i> Comentarios de Estudiantes</div>
            <?php if (empty($comentarios)): ?>
                <p style="color:var(--text-light);text-align:center;padding:20px;">No hay comentarios para este periodo.</p>
            <?php else: ?>
                <?php foreach ($comentarios as $c): ?>
                    <?php if (!empty($c['comentarios_positivos'])): ?>
                    <div class="comment-item positive">
                        <div class="comment-label positive"><i class="fas fa-thumbs-up"></i> Aspecto positivo</div>
                        <div class="comment-text"><?php echo sanitizeInput($c['comentarios_positivos']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['comentarios_mejora'])): ?>
                    <div class="comment-item improvement">
                        <div class="comment-label improvement"><i class="fas fa-lightbulb"></i> Area de mejora</div>
                        <div class="comment-text"><?php echo sanitizeInput($c['comentarios_mejora']); ?></div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Mensaje motivacional -->
        <?php $motiv = motivacional($resumen['porcentaje']); ?>
        <div class="motivacional <?php echo $motiv['class']; ?>">
            <div class="icon"><i class="fas <?php echo $motiv['icon']; ?>"></i></div>
            <h3><?php echo $motiv['titulo']; ?></h3>
            <p><?php echo $motiv['msg']; ?></p>
            <p style="margin-top:15px;font-weight:700;color:var(--purple);">Tu puntuacion: <?php echo $resumen['porcentaje']; ?>%</p>
        </div>

        <?php endif; ?>
    </div>

<script src="/intep/sesion.js"></script>
</body>
</html>
