<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: ../dashboard.php'); exit; }

$mensaje = '';

// ============================================================
// AUTO-MIGRACIÓN: garantizar que estudiante_modulo existe
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

// ============================================================
// PASO 1 — Todos los docentes
// ============================================================
$docentes = [];
$res_doc = mysqli_query($conexion,
    "SELECT u.id, u.username,
            COUNT(pm.id) AS total_modulos
     FROM usuarios u
     LEFT JOIN programa_modulo pm ON pm.docente_id = u.id AND pm.estado = 'activo'
     WHERE u.rol = 'docente' AND u.estado = 'activo'
     GROUP BY u.id
     ORDER BY u.username ASC");
while ($d = mysqli_fetch_assoc($res_doc)) $docentes[] = $d;

$docente_id = isset($_GET['docente_id']) ? (int)$_GET['docente_id'] : 0;
$docente_info = null;
foreach ($docentes as $d) {
    if ($d['id'] == $docente_id) { $docente_info = $d; break; }
}

// ============================================================
// PASO 2 — Módulos del docente seleccionado
// ============================================================
$modulos_docente = [];
if ($docente_id) {
    $res_mods = mysqli_prepare($conexion,
        "SELECT pm.id, pm.bimestre, pm.orden, pm.tipo,
                mf.nombre AS modulo_nombre,
                p.nombre  AS programa_nombre,
                COUNT(em.id) AS total_estudiantes
         FROM programa_modulo pm
         JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
         JOIN programas p ON pm.programa_id = p.id
         LEFT JOIN estudiante_modulo em ON em.programa_modulo_id = pm.id AND em.estado = 'activo'
         WHERE pm.docente_id = ? AND pm.estado = 'activo'
         GROUP BY pm.id
         ORDER BY pm.bimestre ASC, pm.orden ASC, mf.nombre ASC");
    mysqli_stmt_bind_param($res_mods, 'i', $docente_id);
    mysqli_stmt_execute($res_mods);
    $r = mysqli_stmt_get_result($res_mods);
    while ($m = mysqli_fetch_assoc($r)) $modulos_docente[] = $m;
}

$pm_id = isset($_GET['pm_id']) ? (int)$_GET['pm_id'] : 0;
$modulo_info = null;
foreach ($modulos_docente as $m) {
    if ($m['id'] == $pm_id) { $modulo_info = $m; break; }
}

// ============================================================
// PASO 3 — Estudiantes asignados al módulo seleccionado
// ============================================================
$estudiantes_modulo = [];
if ($pm_id) {
    $res_est = mysqli_prepare($conexion,
        "SELECT e.id, e.nombre, e.documento, p.nombre AS programa_nombre
         FROM estudiantes e
         JOIN estudiante_modulo em ON em.estudiante_id = e.id
         JOIN programas p ON p.id = e.programa_id
         WHERE em.programa_modulo_id = ? AND e.estado = 'activo' AND em.estado = 'activo'
         ORDER BY e.nombre ASC");
    mysqli_stmt_bind_param($res_est, 'i', $pm_id);
    mysqli_stmt_execute($res_est);
    $r = mysqli_stmt_get_result($res_est);
    while ($e = mysqli_fetch_assoc($r)) $estudiantes_modulo[] = $e;
}

// Bimestres activos
$bimestres = [];
$res_bim = mysqli_query($conexion,
    "SELECT * FROM bimestres WHERE estado = 'activo' ORDER BY anio ASC, numero ASC");
while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

// Horarios actuales del módulo
$horarios_modulo = [];
if ($pm_id) {
    $res_hor = mysqli_prepare($conexion,
        "SELECT h.dia, h.hora_inicio, h.hora_fin, h.salon, h.link_virtual,
                h.bimestre_id, b.numero AS bimestre_num,
                COUNT(h.id) AS total_est
         FROM horarios h
         LEFT JOIN bimestres b ON h.bimestre_id = b.id
         WHERE h.programa_modulo_id = ?
         GROUP BY h.dia, h.bimestre_id, h.hora_inicio, h.hora_fin, h.salon
         ORDER BY b.numero ASC,
                  FIELD(h.dia,'Lunes','Martes','Miercoles','Miércoles','Jueves','Viernes','Sabado','Sábado')");
    mysqli_stmt_bind_param($res_hor, 'i', $pm_id);
    mysqli_stmt_execute($res_hor);
    $r = mysqli_stmt_get_result($res_hor);
    while ($h = mysqli_fetch_assoc($r)) $horarios_modulo[] = $h;
}

// ============================================================
// PROCESAR POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'error|Token de seguridad invalido.';
        header("Location: gestionar_horarios.php?docente_id=$docente_id&pm_id=$pm_id&msg=" . urlencode($mensaje));
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $pm_post      = (int)$_POST['pm_id'];
        $dias_par     = $_POST['dias_par'];
        $hora_inicio  = $_POST['hora_inicio'];
        $hora_fin     = $_POST['hora_fin'];
        $salon        = trim($_POST['salon'] ?? '');
        $link_virtual = trim($_POST['link_virtual'] ?? '');
        $bimestre_id  = !empty($_POST['bimestre_id']) ? (int)$_POST['bimestre_id'] : null;
        $dias_array   = array_filter(array_map('trim', explode('-', $dias_par)));

        // Traer estudiantes del módulo
        $stmt_e = mysqli_prepare($conexion,
            "SELECT e.id, e.programa_id FROM estudiantes e
             JOIN estudiante_modulo em ON em.estudiante_id = e.id
             WHERE em.programa_modulo_id = ? AND e.estado = 'activo' AND em.estado = 'activo'");
        mysqli_stmt_bind_param($stmt_e, 'i', $pm_post);
        mysqli_stmt_execute($stmt_e);
        $ests = [];
        $r = mysqli_stmt_get_result($stmt_e);
        while ($e = mysqli_fetch_assoc($r)) $ests[] = $e;

        if (empty($ests)) {
            $mensaje = 'error|Este modulo no tiene estudiantes asignados. Ve a Modulos Estudiantes para asignarlos.';
        } else {
            $q = "INSERT IGNORE INTO horarios
                    (programa_id, estudiante_id, programa_modulo_id, dia, hora_inicio, hora_fin, salon, bimestre_id, link_virtual)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertados = 0;
            foreach ($ests as $est) {
                foreach ($dias_array as $dia) {
                    $stmt = mysqli_prepare($conexion, $q);
                    mysqli_stmt_bind_param($stmt, 'iiissssis',
                        $est['programa_id'], $est['id'], $pm_post,
                        $dia, $hora_inicio, $hora_fin, $salon, $bimestre_id, $link_virtual);
                    if (mysqli_stmt_execute($stmt)) $insertados++;
                }
            }
            $mensaje = 'success|Horario asignado a ' . count($ests) . ' estudiante(s) — ' . $insertados . ' registro(s) creado(s).';
        }

    } elseif ($accion === 'eliminar') {
        $pm_del  = (int)$_POST['pm_id_del'];
        $dia_del = $_POST['dia_del'];
        $bim_del = !empty($_POST['bimestre_id_del']) ? (int)$_POST['bimestre_id_del'] : null;
        if ($bim_del) {
            $stmt = mysqli_prepare($conexion,
                "DELETE FROM horarios WHERE programa_modulo_id = ? AND dia = ? AND bimestre_id = ?");
            mysqli_stmt_bind_param($stmt, 'isi', $pm_del, $dia_del, $bim_del);
        } else {
            $stmt = mysqli_prepare($conexion,
                "DELETE FROM horarios WHERE programa_modulo_id = ? AND dia = ? AND bimestre_id IS NULL");
            mysqli_stmt_bind_param($stmt, 'is', $pm_del, $dia_del);
        }
        mysqli_stmt_execute($stmt);
        $afectados = mysqli_affected_rows($conexion);
        $mensaje = "success|Eliminados $afectados registro(s).";
    }

    header("Location: gestionar_horarios.php?docente_id=$docente_id&pm_id=$pm_id&msg=" . urlencode($mensaje));
    exit;
}

if (isset($_GET['msg'])) $mensaje = urldecode($_GET['msg']);
$msg_parts = $mensaje ? explode('|', $mensaje, 2) : null;

$tipo_etiqueta = ['especifico' => 'Especifico', 'transversal' => 'Transversal', 'basico' => 'Basico'];
$tipo_estilo   = [
    'especifico'  => 'background:#fecaca;color:#991b1b;',
    'transversal' => 'background:#fef08a;color:#854d0e;',
    'basico'      => 'background:#bbf7d0;color:#166534;',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
        /* ── Cards ── */
        .card {
            background: rgba(255,255,255,0.78);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            border: 1px solid rgba(16,185,129,0.1);
        }
        .card h3 {
            font-size: 1rem; font-weight: 700;
            margin-bottom: 1.2rem; padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(16,185,129,0.15);
            color: #022C22;
        }
        .grid-2 { display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.5rem; }

        /* ── Pasos de selección ── */
        .pasos-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .paso-box {
            background: rgba(255,255,255,0.78);
            backdrop-filter: blur(12px);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            border: 2px solid rgba(16,185,129,0.15);
            box-shadow: 0 2px 10px rgba(5,150,105,0.06);
        }
        .paso-box.activo { border-color: #10B981; }
        .paso-box.bloqueado { opacity: 0.5; pointer-events: none; }
        .paso-label {
            font-size: 0.72rem; font-weight: 800;
            color: #10B981; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 0.5rem;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .paso-label .num {
            background: #10B981; color: white;
            width: 18px; height: 18px;
            border-radius: 50%; font-size: 0.68rem;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
        }
        .paso-label .num.gris { background: #9ca3af; }
        .paso-select {
            width: 100%; padding: 0.65rem 0.9rem;
            border: 2px solid rgba(16,185,129,0.2);
            border-radius: 10px; font-size: 0.88rem;
            outline: none; background: white; cursor: pointer;
            box-sizing: border-box;
        }
        .paso-select:focus { border-color: #10B981; }

        /* ── Banner info módulo ── */
        .modulo-banner {
            background: linear-gradient(135deg, #022C22, #064e3b);
            color: white; border-radius: 14px;
            padding: 1rem 1.5rem; margin-bottom: 1.5rem;
            display: flex; align-items: center;
            gap: 1rem; flex-wrap: wrap;
        }
        .modulo-banner .titulo { font-size: 1.05rem; font-weight: 800; }
        .modulo-banner .sub    { font-size: 0.82rem; opacity: 0.8; margin-top: 0.1rem; }
        .modulo-banner .badges { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-left: auto; }
        .badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.78rem; font-weight: 700; }
        .badge-tipo-e  { background: #fecaca; color: #991b1b; }
        .badge-tipo-t  { background: #fef08a; color: #854d0e; }
        .badge-tipo-b  { background: #bbf7d0; color: #166534; }
        .badge-est     { background: #f59e0b; color: #422006; }

        /* ── Chips estudiantes ── */
        .est-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
        .est-chip {
            background: #f0fdf4; border: 1px solid #bbf7d0;
            color: #065f46; padding: 0.3rem 0.8rem;
            border-radius: 20px; font-size: 0.78rem; font-weight: 600;
        }
        .est-chip .prog { color: #94a3b8; font-weight: 400; font-size: 0.7rem; }
        .aviso-sin-est {
            background: #fef9c3; border: 1px solid #fde68a;
            border-radius: 10px; padding: 0.9rem 1rem;
            font-size: 0.88rem; color: #78350f; margin-bottom: 1rem;
        }

        /* ── Formulario ── */
        .campo { margin-bottom: 1rem; }
        .campo label {
            display: block; font-size: 0.78rem; font-weight: 700;
            color: #555; margin-bottom: 0.3rem;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .campo input, .campo select {
            width: 100%; padding: 0.7rem 0.9rem;
            border: 2px solid rgba(16,185,129,0.2);
            border-radius: 8px; font-size: 0.9rem;
            outline: none; background: rgba(255,255,255,0.9);
            box-sizing: border-box;
        }
        .campo input:focus, .campo select:focus { border-color: #10B981; }
        .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn-guardar {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; border: none; padding: 0.9rem;
            border-radius: 10px; font-weight: 700; cursor: pointer;
            width: 100%; font-size: 0.95rem;
            transition: all 0.2s; margin-top: 0.5rem;
        }
        .btn-guardar:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(5,150,105,0.3); }
        .btn-guardar:disabled { opacity: 0.45; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ── Tabla horarios ── */
        .htable { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
        .htable th {
            background: #f0fdf4; color: #065f46; font-weight: 700;
            padding: 0.6rem 0.8rem; text-align: left;
            border-bottom: 2px solid #d1fae5;
        }
        .htable td { padding: 0.6rem 0.8rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .htable tr:hover td { background: #f9fffe; }
        .tag-bim { background: #022C22; color: #f59e0b; padding: 0.15rem 0.5rem; border-radius: 5px; font-size: 0.72rem; font-weight: 700; }
        .tag-dia { background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; }
        .btn-del { background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 0.25rem 0.6rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 700; transition: all 0.2s; }
        .btn-del:hover { background: #ef4444; color: white; }

        /* ── Alertas ── */
        .ok  { background: rgba(16,185,129,0.1); color: #065f46; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #10b981; font-size: 0.88rem; }
        .err { background: rgba(239,68,68,0.08);  color: #991b1b; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #ef4444; font-size: 0.88rem; }
        .empty { text-align: center; padding: 2.5rem 1rem; color: #9ca3af; font-size: 0.88rem; }

        @media(max-width: 820px) {
            .pasos-selector { grid-template-columns: 1fr; }
            .grid-2 { grid-template-columns: 1fr; }
            .grid-2col { grid-template-columns: 1fr; }
            .modulo-banner { flex-direction: column; gap: 0.6rem; }
            .modulo-banner .badges { margin-left: 0; }
        }
    </style>
</head>
<body data-rol="admin">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📅 Gestionar Horarios</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesion</a>
</div>

<div class="dashboard-container">

    <a href="../dashboard.php" class="btn-volver">← Volver al inicio</a>

    <?php if ($msg_parts): ?>
        <div class="<?php echo $msg_parts[0] === 'success' ? 'ok' : 'err'; ?>">
            <?php echo htmlspecialchars($msg_parts[1]); ?>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- PASO 1 y 2: Selección docente → módulo                -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="pasos-selector">

        <!-- PASO 1: Docente -->
        <div class="paso-box <?php echo $docente_id ? 'activo' : ''; ?>">
            <div class="paso-label">
                <span class="num <?php echo !$docente_id ? 'gris' : ''; ?>">1</span>
                Selecciona el docente
            </div>
            <select class="paso-select"
                    onchange="window.location.href='gestionar_horarios.php?docente_id='+this.value">
                <option value="0">-- Elige un docente --</option>
                <?php foreach ($docentes as $d): ?>
                    <option value="<?php echo $d['id']; ?>"
                            <?php echo $d['id'] == $docente_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d['username']); ?>
                        (<?php echo $d['total_modulos']; ?> modulo<?php echo $d['total_modulos'] != 1 ? 's' : ''; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- PASO 2: Módulo del docente -->
        <div class="paso-box <?php echo $pm_id ? 'activo' : ($docente_id ? '' : 'bloqueado'); ?>">
            <div class="paso-label">
                <span class="num <?php echo !$pm_id ? 'gris' : ''; ?>">2</span>
                Selecciona el modulo
            </div>
            <?php if (!$docente_id): ?>
                <select class="paso-select" disabled>
                    <option>Primero elige un docente</option>
                </select>
            <?php elseif (empty($modulos_docente)): ?>
                <select class="paso-select" disabled>
                    <option>Este docente no tiene modulos asignados</option>
                </select>
            <?php else: ?>
                <select class="paso-select"
                        onchange="window.location.href='gestionar_horarios.php?docente_id=<?php echo $docente_id; ?>&pm_id='+this.value">
                    <option value="0">-- Elige un modulo --</option>
                    <?php foreach ($modulos_docente as $m): ?>
                        <option value="<?php echo $m['id']; ?>"
                                <?php echo $m['id'] == $pm_id ? 'selected' : ''; ?>>
                            <?php if ($m['bimestre']): ?>[Bim <?php echo $m['bimestre']; ?>] <?php endif; ?>
                            <?php echo htmlspecialchars($m['modulo_nombre']); ?>
                            — <?php echo htmlspecialchars($m['programa_nombre']); ?>
                            · <?php echo $m['total_estudiantes']; ?> est.
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

    </div>

    <?php if ($pm_id && $modulo_info): ?>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- BANNER INFO DEL MÓDULO                                 -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <?php
    $tipo = $modulo_info['tipo'];
    $badge_tipo_class = $tipo === 'transversal' ? 'badge-tipo-t' : ($tipo === 'basico' ? 'badge-tipo-b' : 'badge-tipo-e');
    ?>
    <div class="modulo-banner">
        <div>
            <div class="titulo"><?php echo htmlspecialchars($modulo_info['modulo_nombre']); ?></div>
            <div class="sub">
                Programa: <?php echo htmlspecialchars($modulo_info['programa_nombre']); ?>
                &nbsp;·&nbsp; Docente: <?php echo htmlspecialchars($docente_info['username']); ?>
                <?php if ($modulo_info['bimestre']): ?>
                    &nbsp;·&nbsp; Bimestre <?php echo $modulo_info['bimestre']; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="badges">
            <span class="badge <?php echo $badge_tipo_class; ?>">
                <?php echo $tipo_etiqueta[$tipo] ?? $tipo; ?>
            </span>
            <span class="badge badge-est">
                👥 <?php echo count($estudiantes_modulo); ?> estudiante<?php echo count($estudiantes_modulo) != 1 ? 's' : ''; ?>
            </span>
        </div>
    </div>

    <div class="grid-2">

        <!-- ──────────────────────────────────────────── -->
        <!-- FORMULARIO ASIGNAR HORARIO                  -->
        <!-- ──────────────────────────────────────────── -->
        <div class="card">
            <h3>➕ Asignar Horario</h3>

            <!-- Estudiantes del módulo -->
            <?php if (empty($estudiantes_modulo)): ?>
                <div class="aviso-sin-est">
                    ⚠️ Este modulo no tiene estudiantes asignados aun.<br>
                    <a href="modulos_estudiantes.php" style="color:#92400e;font-weight:700;">
                        → Ir a Modulos Estudiantes
                    </a>
                </div>
            <?php else: ?>
                <p style="font-size:0.82rem;color:#6b7280;margin-bottom:0.5rem;">
                    El horario se aplicara a <strong><?php echo count($estudiantes_modulo); ?></strong> estudiante(s):
                </p>
                <div class="est-chips">
                    <?php foreach ($estudiantes_modulo as $e): ?>
                        <span class="est-chip">
                            <?php echo htmlspecialchars($e['nombre']); ?>
                            <span class="prog"> · <?php echo htmlspecialchars($e['programa_nombre']); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>

                <?php $est_ids_mod = array_map(fn($x) => (int)$x['id'], $estudiantes_modulo); ?>
                <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:0.8rem 0.9rem;margin:0.8rem 0 1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                    <div style="font-size:0.85rem;color:#78350F;">
                        🔔 Avisa a estos estudiantes que hay nuevo horario: hará titilar la tarjeta <strong>Mi Horario</strong> en su dashboard.
                    </div>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                        <button type="button" id="btn-titilar-horario"
                                style="background:#F59E0B;color:white;border:none;cursor:pointer;padding:0.45rem 0.9rem;border-radius:8px;font-weight:700;font-size:0.85rem;"
                                onclick='dispararAlertaHorarios(<?= json_encode($est_ids_mod) ?>, this)'>
                            🔔 Hacer titilar Horarios
                        </button>
                        <button type="button"
                                style="background:#E5E7EB;color:#374151;border:none;cursor:pointer;padding:0.45rem 0.9rem;border-radius:8px;font-weight:600;font-size:0.85rem;"
                                onclick='limpiarAlertaHorarios(<?= json_encode($est_ids_mod) ?>, this)'>
                            Apagar titileo
                        </button>
                    </div>
                </div>
                <script>
                if (!window.__csrfAlertasH) {
                    window.__csrfAlertasH = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
                }
                async function dispararAlertaHorarios(ids, btn) {
                    if (!Array.isArray(ids) || ids.length === 0) { alert('Sin estudiantes'); return; }
                    const original = btn.textContent;
                    btn.disabled = true; btn.textContent = '...';
                    try {
                        const r = await fetch('api_alertas_admin.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                modulo: 'horarios', accion: 'disparar',
                                estudiante_ids: ids, csrf_token: window.__csrfAlertasH
                            })
                        });
                        const d = await r.json();
                        if (d.csrf_token) window.__csrfAlertasH = d.csrf_token;
                        if (d.ok) {
                            btn.textContent = '✓ ' + (d.creadas || 0) + ' notificadas';
                            btn.style.background = '#10B981';
                        } else {
                            alert('Error: ' + (d.error || 'no se pudo'));
                            btn.textContent = original;
                        }
                    } catch (e) {
                        alert('Error de red.');
                        btn.textContent = original;
                    } finally {
                        setTimeout(() => { btn.disabled = false; }, 1500);
                    }
                }
                async function limpiarAlertaHorarios(ids, btn) {
                    if (!Array.isArray(ids) || ids.length === 0) return;
                    const original = btn.textContent;
                    btn.disabled = true; btn.textContent = '...';
                    try {
                        const r = await fetch('api_alertas_admin.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                modulo: 'horarios', accion: 'limpiar',
                                estudiante_ids: ids, csrf_token: window.__csrfAlertasH
                            })
                        });
                        const d = await r.json();
                        if (d.csrf_token) window.__csrfAlertasH = d.csrf_token;
                        if (d.ok) {
                            alert('Apagado: ' + (d.limpiadas || 0) + ' titileos.');
                        } else {
                            alert('Error: ' + (d.error || 'no se pudo'));
                        }
                        btn.textContent = original;
                    } catch (e) {
                        alert('Error de red.');
                        btn.textContent = original;
                    } finally {
                        setTimeout(() => { btn.disabled = false; }, 1500);
                    }
                }
                </script>
            <?php endif; ?>

            <form method="POST"
                  action="gestionar_horarios.php?docente_id=<?php echo $docente_id; ?>&pm_id=<?php echo $pm_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="accion" value="agregar">
                <input type="hidden" name="pm_id" value="<?php echo $pm_id; ?>">

                <div class="campo">
                    <label>Bimestre</label>
                    <select name="bimestre_id">
                        <option value="">Sin bimestre especifico</option>
                        <?php foreach ($bimestres as $b): ?>
                            <option value="<?php echo $b['id']; ?>">
                                Bimestre <?php echo $b['numero']; ?>
                                — <?php echo date('d/m', strtotime($b['fecha_inicio'])); ?>
                                al <?php echo date('d/m/Y', strtotime($b['fecha_fin'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label>Dias de clase</label>
                    <select name="dias_par" required>
                        <option value="">Selecciona los dias</option>
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miércoles">Miercoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                        <option value="Sábado">Sabado</option>
                        <option value="Lunes-Miércoles">Lunes y Miercoles</option>
                        <option value="Martes-Jueves">Martes y Jueves</option>
                        <option value="Lunes-Martes">Lunes y Martes</option>
                        <option value="Miércoles-Jueves">Miercoles y Jueves</option>
                        <option value="Lunes-Miércoles-Viernes">Lun, Mie y Vie</option>
                        <option value="Martes-Jueves-Sábado">Mar, Jue y Sab</option>
                    </select>
                </div>

                <div class="grid-2col">
                    <div class="campo">
                        <label>Hora inicio</label>
                        <input type="time" name="hora_inicio" value="18:30" required>
                    </div>
                    <div class="campo">
                        <label>Hora fin</label>
                        <input type="time" name="hora_fin" value="21:30" required>
                    </div>
                </div>

                <div class="campo">
                    <label>Salon</label>
                    <input type="text" name="salon"
                           placeholder="Ej: Aula 101, Virtual, Sala B">
                </div>

                <div class="campo">
                    <label>Link clase virtual (opcional)</label>
                    <input type="url" name="link_virtual"
                           placeholder="https://meet.google.com/xxx-xxx-xxx">
                </div>

                <button type="submit" class="btn-guardar"
                        <?php echo empty($estudiantes_modulo) ? 'disabled' : ''; ?>>
                    📅 Asignar horario a <?php echo count($estudiantes_modulo); ?> estudiante(s)
                </button>
            </form>
        </div>

        <!-- ──────────────────────────────────────────── -->
        <!-- HORARIOS YA ASIGNADOS A ESTE MÓDULO         -->
        <!-- ──────────────────────────────────────────── -->
        <div class="card">
            <h3>
                📋 Horarios de este modulo
                <span style="background:#e0f2fe;color:#0369a1;padding:0.15rem 0.6rem;border-radius:20px;font-size:0.75rem;margin-left:0.5rem;">
                    <?php echo count($horarios_modulo); ?> franja<?php echo count($horarios_modulo) != 1 ? 's' : ''; ?>
                </span>
            </h3>

            <?php if (empty($horarios_modulo)): ?>
                <div class="empty">
                    <div style="font-size:2.5rem; margin-bottom:0.5rem;">📅</div>
                    <p>No hay horarios asignados a este modulo aun.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="htable">
                        <thead>
                            <tr>
                                <th>Bim.</th>
                                <th>Dia</th>
                                <th>Horario</th>
                                <th>Salon</th>
                                <th>Estudiantes</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios_modulo as $h): ?>
                            <tr>
                                <td>
                                    <?php if ($h['bimestre_num']): ?>
                                        <span class="tag-bim">B<?php echo $h['bimestre_num']; ?></span>
                                    <?php else: ?>
                                        <span style="color:#aaa;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="tag-dia"><?php echo htmlspecialchars($h['dia']); ?></span></td>
                                <td style="font-weight:700; white-space:nowrap;">
                                    <?php echo substr($h['hora_inicio'],0,5); ?> – <?php echo substr($h['hora_fin'],0,5); ?>
                                </td>
                                <td style="font-size:0.82rem; color:#555;">
                                    <?php echo htmlspecialchars($h['salon'] ?: '—'); ?>
                                    <?php if (!empty($h['link_virtual'])): ?>
                                        <br>
                                        <a href="<?php echo htmlspecialchars($h['link_virtual']); ?>"
                                           target="_blank"
                                           style="color:#0369a1;font-size:0.72rem;">🔗 Link virtual</a>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span style="background:#f0fdf4;color:#059669;padding:0.2rem 0.6rem;border-radius:6px;font-size:0.78rem;font-weight:700;">
                                        👥 <?php echo $h['total_est']; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="gestionar_horarios.php?docente_id=<?php echo $docente_id; ?>&pm_id=<?php echo $pm_id; ?>"
                                          onsubmit="return confirm('¿Eliminar este horario para todos los estudiantes?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="pm_id_del" value="<?php echo $pm_id; ?>">
                                        <input type="hidden" name="dia_del"   value="<?php echo htmlspecialchars($h['dia']); ?>">
                                        <input type="hidden" name="bimestre_id_del" value="<?php echo $h['bimestre_id'] ?? ''; ?>">
                                        <button type="submit" class="btn-del" title="Eliminar">🗑</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="font-size:0.78rem;color:#9ca3af;margin-top:0.8rem;">
                    Al eliminar una franja se quita del horario de todos los estudiantes del modulo.
                </p>
            <?php endif; ?>
        </div>

    </div>

    <?php elseif ($docente_id && empty($modulos_docente)): ?>
        <div class="card" style="text-align:center; padding:2.5rem;">
            <p style="color:#9ca3af;">
                Este docente no tiene modulos asignados.<br>
                <a href="gestionar_modulos.php" style="color:#059669;font-weight:700;">
                    → Ir a Gestionar Modulos para asignarselos
                </a>
            </p>
        </div>
    <?php elseif (!$docente_id): ?>
        <div class="card" style="text-align:center; padding:3rem;">
            <div style="font-size:3rem; margin-bottom:1rem;">👆</div>
            <p style="color:#6b7280; font-size:0.95rem;">
                Comienza seleccionando un docente en el <strong>Paso 1</strong>.
            </p>
        </div>
    <?php elseif (!$pm_id): ?>
        <div class="card" style="text-align:center; padding:3rem;">
            <div style="font-size:3rem; margin-bottom:1rem;">📚</div>
            <p style="color:#6b7280; font-size:0.95rem;">
                Ahora selecciona un modulo en el <strong>Paso 2</strong>.
            </p>
        </div>
    <?php endif; ?>

</div>

<script src="/intep/sesion.js"></script>
<?php include __DIR__ . '/../partials/asistente_admin.php'; ?>
</body>
</html>
