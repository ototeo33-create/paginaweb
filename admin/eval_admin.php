<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$nombre = sanitizeInput($_SESSION['usuario_nombre']);
$csrf = csrf_token();
$mensaje = '';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'toggle') {
        $periodo = trim($_POST['periodo'] ?? '');
        $activa = (int)($_POST['activa'] ?? 0);
        if ($periodo) {
            // Desactivar todas las anteriores
            mysqli_query($conexion, "UPDATE eval_control SET activa = 0");
            // Insertar o actualizar periodo
            $stmt = mysqli_prepare($conexion, "INSERT INTO eval_control (periodo, activa, fecha_inicio) VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE activa = VALUES(activa), updated_at = NOW(), fecha_inicio = IF(VALUES(activa)=1, NOW(), fecha_inicio)");
            mysqli_stmt_bind_param($stmt, 'si', $periodo, $activa);
            mysqli_stmt_execute($stmt);
            $mensaje = $activa ? 'success|Evaluacion activada para ' . $periodo : 'success|Evaluacion desactivada';
        }
    }

    if ($accion === 'desactivar') {
        mysqli_query($conexion, "UPDATE eval_control SET activa = 0, fecha_fin = NOW()");
        $mensaje = 'success|Evaluacion desactivada';
    }

    // Redirect PRG
    header("Location: eval_admin.php?msg=" . urlencode($mensaje));
    exit;
}

if (isset($_GET['msg'])) {
    $mensaje = $_GET['msg'];
}
$msg_parts = $mensaje ? explode('|', $mensaje, 2) : null;

// Obtener estado actual
$res_ctrl = mysqli_query($conexion, "SELECT * FROM eval_control ORDER BY id DESC LIMIT 1");
$ctrl = mysqli_fetch_assoc($res_ctrl);
$eval_activa = ($ctrl && $ctrl['activa']);
$periodo_actual = $ctrl['periodo'] ?? '';

// Estadisticas generales
$periodo_filtro = $_GET['periodo'] ?? $periodo_actual;

$stats = ['total_evaluaciones' => 0, 'docentes_evaluados' => 0, 'promedio_institucional' => 0, 'estudiantes_participaron' => 0];
if ($periodo_filtro) {
    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) as total, COUNT(DISTINCT docente_id) as docentes, COUNT(DISTINCT estudiante_id) as estudiantes, AVG(porcentaje) as promedio FROM eval_docente WHERE periodo = ?");
    mysqli_stmt_bind_param($stmt, 's', $periodo_filtro);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt)->fetch_assoc();
    $stats['total_evaluaciones'] = (int)$r['total'];
    $stats['docentes_evaluados'] = (int)$r['docentes'];
    $stats['estudiantes_participaron'] = (int)$r['estudiantes'];
    $stats['promedio_institucional'] = round((float)$r['promedio'], 1);
}

// Tabla de docentes con resultados
$docentes_resultados = [];
if ($periodo_filtro) {
    $sql = "SELECT
                ed.docente_id,
                u.username AS docente_nombre,
                COUNT(ed.id) AS total_evals,
                ROUND(AVG(ed.porcentaje), 1) AS promedio,
                MIN(ed.porcentaje) AS minimo,
                MAX(ed.porcentaje) AS maximo,
                GROUP_CONCAT(DISTINCT mf.nombre SEPARATOR ', ') AS modulos
            FROM eval_docente ed
            JOIN usuarios u ON ed.docente_id = u.id
            JOIN programa_modulo pm ON ed.programa_modulo_id = pm.id
            JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
            WHERE ed.periodo = ?
            GROUP BY ed.docente_id
            ORDER BY promedio DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 's', $periodo_filtro);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $docentes_resultados[] = $row;
    }
}

// Periodos disponibles
$periodos = [];
$rp = mysqli_query($conexion, "SELECT DISTINCT periodo FROM eval_control ORDER BY id DESC");
while ($p = mysqli_fetch_assoc($rp)) { $periodos[] = $p['periodo']; }
$rp2 = mysqli_query($conexion, "SELECT DISTINCT periodo FROM eval_docente ORDER BY periodo DESC");
while ($p2 = mysqli_fetch_assoc($rp2)) {
    if (!in_array($p2['periodo'], $periodos)) $periodos[] = $p2['periodo'];
}

$csrf = csrf_token();

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Evaluacion Docente - INTEP</title>
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

        .container { max-width:1100px; margin:0 auto; padding:25px 20px 40px; }

        .alert { padding:12px 20px; border-radius:10px; margin-bottom:20px; font-weight:500; }
        .alert-success { background:var(--green-pale); color:var(--green-dark); border:1px solid var(--green); }
        .alert-danger { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }

        .card {
            background:white; border-radius:var(--radius); padding:30px; margin-bottom:25px;
            box-shadow:0 4px 20px rgba(74,25,66,0.06); border:1px solid rgba(74,25,66,0.05);
        }
        .card-title { font-size:1.2em; font-weight:700; color:var(--purple); margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .card-title i { color:var(--green); }

        /* Control section */
        .control-row { display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
        .control-row input[type=text] {
            padding:10px 15px; border:2px solid #e0e0e0; border-radius:10px; font-family:'Exo 2',sans-serif;
            font-size:0.95em; width:250px;
        }
        .control-row input:focus { outline:none; border-color:var(--green); }

        .toggle-switch { position:relative; width:56px; height:30px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider {
            position:absolute; cursor:pointer; inset:0; background:#ccc; border-radius:30px; transition:0.3s;
        }
        .toggle-slider:before {
            content:''; position:absolute; height:24px; width:24px; left:3px; bottom:3px;
            background:white; border-radius:50%; transition:0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background:var(--green); }
        .toggle-switch input:checked + .toggle-slider:before { transform:translateX(26px); }

        .status-badge {
            display:inline-block; padding:5px 15px; border-radius:20px; font-weight:600; font-size:0.85em;
        }
        .status-badge.active { background:var(--green-pale); color:var(--green-dark); }
        .status-badge.inactive { background:#fef2f2; color:#dc2626; }

        .btn {
            padding:10px 25px; border:none; border-radius:10px; font-size:0.9em; font-weight:600;
            cursor:pointer; font-family:'Exo 2',sans-serif; transition:all 0.3s;
        }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:var(--green-dark); }
        .btn-red { background:#ef4444; color:white; }
        .btn-red:hover { background:#dc2626; }
        .btn-purple { background:var(--purple); color:white; }
        .btn-purple:hover { background:var(--purple-mid); }

        /* Stats */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; }
        .stat-card {
            text-align:center; padding:20px; border-radius:12px; border:1px solid #eee;
        }
        .stat-card .num { font-size:2.5em; font-weight:800; line-height:1; }
        .stat-card .label { font-size:0.85em; color:var(--text-mid); margin-top:8px; }
        .stat-card.green .num { color:var(--green); }
        .stat-card.blue .num { color:#3B82F6; }
        .stat-card.purple .num { color:var(--purple); }
        .stat-card.orange .num { color:#f59e0b; }

        /* Table */
        .table-container { overflow-x:auto; }
        table.data-table { width:100%; border-collapse:collapse; }
        table.data-table th {
            background:var(--purple); color:white; padding:12px 15px; text-align:left; font-weight:600; font-size:0.9em;
        }
        table.data-table th:first-child { border-radius:10px 0 0 0; }
        table.data-table th:last-child { border-radius:0 10px 0 0; }
        table.data-table td { padding:12px 15px; border-bottom:1px solid #eee; font-size:0.9em; }
        table.data-table tr:nth-child(even) { background:var(--cream); }
        table.data-table tr:hover { background:var(--green-pale); }

        .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:0.8em; font-weight:600; }
        .badge.excellent { background:var(--green-pale); color:var(--green-dark); }
        .badge.good { background:#dbeafe; color:#1d4ed8; }
        .badge.regular { background:#fef3c7; color:#b45309; }
        .badge.insufficient { background:#fef2f2; color:#dc2626; }

        .progress-mini { width:100px; height:8px; background:#eee; border-radius:4px; overflow:hidden; display:inline-block; vertical-align:middle; margin-right:8px; }
        .progress-mini-fill { height:100%; border-radius:4px; transition:width 0.5s; }

        .periodo-selector { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .periodo-btn {
            padding:8px 18px; border:2px solid #e0e0e0; border-radius:20px; background:white;
            cursor:pointer; font-family:'Exo 2',sans-serif; font-weight:600; font-size:0.85em; transition:0.3s;
        }
        .periodo-btn.active { border-color:var(--green); background:var(--green-pale); color:var(--green-dark); }
        .periodo-btn:hover { border-color:var(--green); }

        @media (max-width:768px) {
            .control-row { flex-direction:column; align-items:flex-start; }
            .stats-grid { grid-template-columns:1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <a href="../dashboard.php"><i class="fas fa-arrow-left"></i></a>
            <h1><i class="fas fa-chart-line" style="margin-right:8px;"></i>Evaluacion Docente - Admin</h1>
        </div>
        <div class="topbar-right"><?php echo $nombre; ?> &middot; Admin</div>
    </div>

    <div class="container">
        <?php if ($msg_parts): ?>
            <div class="alert alert-<?php echo $msg_parts[0] === 'success' ? 'success' : 'danger'; ?>">
                <?php echo sanitizeInput($msg_parts[1] ?? ''); ?>
            </div>
        <?php endif; ?>

        <!-- Control de evaluacion -->
        <div class="card">
            <div class="card-title"><i class="fas fa-power-off"></i> Control de Evaluacion</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion" value="toggle">
                <div class="control-row">
                    <div>
                        <label style="font-weight:600;font-size:0.9em;display:block;margin-bottom:6px;">Periodo</label>
                        <input type="text" name="periodo" value="<?php echo sanitizeInput($periodo_actual); ?>" placeholder="Ej: 2025-2026 II" required>
                    </div>
                    <div style="text-align:center;">
                        <label style="font-weight:600;font-size:0.9em;display:block;margin-bottom:6px;">Estado</label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="activa" value="1" <?php echo $eval_activa ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:0.9em;display:block;margin-bottom:6px;">&nbsp;</label>
                        <span class="status-badge <?php echo $eval_activa ? 'active' : 'inactive'; ?>">
                            <?php echo $eval_activa ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:0.9em;display:block;margin-bottom:6px;">&nbsp;</label>
                        <button type="submit" class="btn btn-green"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </div>
            </form>
            <?php if ($eval_activa): ?>
            <div style="margin-top:15px;">
                <a href="../evaluar_docente.php" target="_blank" class="btn btn-purple" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-external-link-alt"></i> Ver formulario
                </a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="desactivar">
                    <button type="submit" class="btn btn-red" style="margin-left:10px;" onclick="return confirm('Desactivar la evaluacion?')">
                        <i class="fas fa-stop"></i> Desactivar ahora
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Estadisticas -->
        <div class="card">
            <div class="card-title"><i class="fas fa-chart-pie"></i> Estadisticas <?php echo $periodo_filtro ? '- ' . sanitizeInput($periodo_filtro) : ''; ?></div>

            <?php if (count($periodos) > 1): ?>
            <div class="periodo-selector">
                <?php foreach ($periodos as $p): ?>
                    <a href="?periodo=<?php echo urlencode($p); ?>" class="periodo-btn <?php echo $p === $periodo_filtro ? 'active' : ''; ?>">
                        <?php echo sanitizeInput($p); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="num"><?php echo $stats['total_evaluaciones']; ?></div>
                    <div class="label">Evaluaciones</div>
                </div>
                <div class="stat-card blue">
                    <div class="num"><?php echo $stats['docentes_evaluados']; ?></div>
                    <div class="label">Docentes Evaluados</div>
                </div>
                <div class="stat-card purple">
                    <div class="num"><?php echo $stats['estudiantes_participaron']; ?></div>
                    <div class="label">Estudiantes Participaron</div>
                </div>
                <div class="stat-card orange">
                    <div class="num"><?php echo $stats['promedio_institucional']; ?>%</div>
                    <div class="label">Promedio Institucional</div>
                </div>
            </div>
        </div>

        <!-- Tabla de resultados por docente -->
        <div class="card">
            <div class="card-title"><i class="fas fa-users"></i> Resultados por Docente</div>
            <?php if (empty($docentes_resultados)): ?>
                <p style="color:var(--text-light);text-align:center;padding:30px 0;">
                    <i class="fas fa-inbox" style="font-size:2em;display:block;margin-bottom:10px;"></i>
                    No hay evaluaciones registradas para este periodo.
                </p>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Docente</th>
                                <th>Modulos</th>
                                <th>Evaluaciones</th>
                                <th>Promedio</th>
                                <th>Rango</th>
                                <th>Estado</th>
                                <th>Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($docentes_resultados as $i => $d): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo sanitizeInput($d['docente_nombre']); ?></strong></td>
                                <td style="max-width:200px;font-size:0.85em;color:var(--text-mid);"><?php echo sanitizeInput($d['modulos']); ?></td>
                                <td><?php echo (int)$d['total_evals']; ?></td>
                                <td>
                                    <div class="progress-mini">
                                        <div class="progress-mini-fill" style="width:<?php echo $d['promedio']; ?>%;background:<?php
                                            echo $d['promedio'] >= 90 ? 'var(--green)' : ($d['promedio'] >= 75 ? '#3B82F6' : ($d['promedio'] >= 50 ? '#f59e0b' : '#ef4444'));
                                        ?>;"></div>
                                    </div>
                                    <strong><?php echo $d['promedio']; ?>%</strong>
                                </td>
                                <td style="font-size:0.85em;color:var(--text-mid);"><?php echo $d['minimo']; ?>% - <?php echo $d['maximo']; ?>%</td>
                                <td><span class="badge <?php echo badgeClase($d['promedio']); ?>"><?php echo badgeTexto($d['promedio']); ?></span></td>
                                <td>
                                    <a href="eval_resultados.php?docente_id=<?php echo $d['docente_id']; ?>&periodo=<?php echo urlencode($periodo_filtro); ?>" class="btn btn-green" style="padding:6px 15px;font-size:0.85em;text-decoration:none;">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script src="/intep/sesion.js"></script>
</body>
</html>
