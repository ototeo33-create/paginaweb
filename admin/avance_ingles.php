<?php
require_once '../config.php';

// ============================================
// AUTH: admin o docente
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}
$rol = $_SESSION['usuario_rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'docente') {
    header('Location: ../dashboard.php');
    exit;
}

$nombre = sanitizeInput($_SESSION['usuario_nombre'] ?? '');

// ============================================
// DETECTAR PROGRAMAS DE INGLÉS
// ============================================
$programas_ingles = [];
$res = mysqli_query($conexion, "SELECT id, nombre FROM programas WHERE LOWER(nombre) LIKE '%ingl%' ORDER BY nombre");
while ($p = mysqli_fetch_assoc($res)) {
    $programas_ingles[(int)$p['id']] = $p['nombre'];
}

// ============================================
// DETERMINAR ALCANCE SEGÚN ROL
// ============================================
$programas_permitidos = []; // ids
$programa_docente = null;

if ($rol === 'docente') {
    // Leer programa_id del docente desde usuarios
    $uid = (int)$_SESSION['usuario_id'];
    $st = mysqli_prepare($conexion, "SELECT programa_id FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($st, 'i', $uid);
    mysqli_stmt_execute($st);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    $programa_docente = $r ? (int)$r['programa_id'] : null;

    if (!$programa_docente || !isset($programas_ingles[$programa_docente])) {
        // No es docente de inglés
        http_response_code(403);
        ?>
        <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>No autorizado</title>
        <link rel="stylesheet" href="/intep/css/estilos.css"></head>
        <body style="padding:2rem;font-family:system-ui;text-align:center;">
        <h2>🚫 No autorizado</h2>
        <p>Esta sección es solo para docentes asignados a un programa de inglés.</p>
        <p><a href="../dashboard.php">← Volver al inicio</a></p>
        </body></html>
        <?php
        exit;
    }
    $programas_permitidos = [$programa_docente];
} else {
    // admin: todos los programas de inglés (con filtro opcional)
    $programas_permitidos = array_keys($programas_ingles);
    $filtro = isset($_GET['programa']) ? (int)$_GET['programa'] : 0;
    if ($filtro && isset($programas_ingles[$filtro])) {
        $programas_permitidos = [$filtro];
    }
}

if (empty($programas_permitidos)) {
    $programas_permitidos = [0]; // forzar 0 filas
}

// ============================================
// CHEQUEAR EXISTENCIA DE TABLAS DEL CURSO
// ============================================
function tabla_existe($conexion, $nombre) {
    $r = mysqli_query($conexion, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conexion, $nombre) . "'");
    return $r && mysqli_num_rows($r) > 0;
}
$tiene_idiomas = tabla_existe($conexion, 'idiomas_nivel');
$tiene_progreso = tabla_existe($conexion, 'ingles_cursos_progreso');
$tiene_quizzes = tabla_existe($conexion, 'ingles_quiz_resultados');

// ============================================
// VISTA: DETALLE DE UNA ESTUDIANTE
// ============================================
$detalle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$estudiante_detalle = null;

if ($detalle_id > 0) {
    $placeholders = implode(',', array_fill(0, count($programas_permitidos), '?'));
    $types = str_repeat('i', count($programas_permitidos)) . 'i';
    $params = array_merge($programas_permitidos, [$detalle_id]);

    $sql = "SELECT e.id, e.nombre, e.documento, e.foto, p.nombre AS programa, p.id AS programa_id
            FROM estudiantes e
            JOIN programas p ON p.id = e.programa_id
            WHERE e.programa_id IN ($placeholders) AND e.id = ?";
    $st = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $estudiante_detalle = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

    if (!$estudiante_detalle) {
        // No autorizado para este estudiante
        header('Location: avance_ingles.php');
        exit;
    }

    // Datos de idiomas_nivel
    $info_nivel = ['nivel_actual' => 'A1', 'xp_total' => 0, 'racha_actual' => 0, 'racha_maxima' => 0, 'ultima_sesion' => null, 'apodo' => null, 'quiz_completado' => 0];
    if ($tiene_idiomas) {
        $st = mysqli_prepare($conexion, "SELECT nivel_actual, xp_total, racha_actual, racha_maxima, ultima_sesion, apodo, quiz_completado FROM idiomas_nivel WHERE estudiante_id = ?");
        mysqli_stmt_bind_param($st, 'i', $detalle_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        if ($row) $info_nivel = array_merge($info_nivel, $row);
    }

    // Progreso por módulo
    $progreso_rows = [];
    if ($tiene_progreso) {
        $st = mysqli_prepare($conexion,
            "SELECT nivel, modulo_num, porcentaje, completado, xp_ganado, examen_aprobado, fecha_completado
             FROM ingles_cursos_progreso WHERE estudiante_id = ? ORDER BY nivel, modulo_num");
        mysqli_stmt_bind_param($st, 'i', $detalle_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($r = mysqli_fetch_assoc($res)) {
            $progreso_rows[$r['nivel'] . '_' . (int)$r['modulo_num']] = $r;
        }
    }

    // Quizzes
    $quiz_rows = [];
    if ($tiene_quizzes) {
        $st = mysqli_prepare($conexion,
            "SELECT nivel, modulo_num, score, aprobado, intento
             FROM ingles_quiz_resultados WHERE estudiante_id = ?
             ORDER BY nivel, modulo_num, intento DESC LIMIT 100");
        mysqli_stmt_bind_param($st, 'i', $detalle_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($r = mysqli_fetch_assoc($res)) $quiz_rows[] = $r;
    }
}

// ============================================
// VISTA: LISTADO
// ============================================
$listado = [];
$totales = ['total' => 0, 'iniciaron' => 0, 'aprobaron_examen' => 0];

if (!$detalle_id) {
    $placeholders = implode(',', array_fill(0, count($programas_permitidos), '?'));
    $types = str_repeat('i', count($programas_permitidos));

    // Construir query con LEFT JOIN solo si las tablas existen
    $sel_idiomas = $tiene_idiomas
        ? "in_.nivel_actual, in_.xp_total, in_.racha_actual, in_.racha_maxima, in_.ultima_sesion, in_.quiz_completado"
        : "NULL AS nivel_actual, 0 AS xp_total, 0 AS racha_actual, 0 AS racha_maxima, NULL AS ultima_sesion, 0 AS quiz_completado";
    $join_idiomas = $tiene_idiomas ? "LEFT JOIN idiomas_nivel in_ ON in_.estudiante_id = e.id" : "";

    $sub_completados = $tiene_progreso
        ? "(SELECT COUNT(*) FROM ingles_cursos_progreso cp WHERE cp.estudiante_id = e.id AND cp.completado = 1)"
        : "0";
    $sub_examen = $tiene_progreso
        ? "(SELECT MAX(cp.examen_aprobado) FROM ingles_cursos_progreso cp WHERE cp.estudiante_id = e.id)"
        : "0";

    $sql = "SELECT e.id, e.nombre, e.documento, p.nombre AS programa,
                   $sel_idiomas,
                   $sub_completados AS modulos_completados,
                   $sub_examen AS examen_aprobado
            FROM estudiantes e
            JOIN programas p ON p.id = e.programa_id
            $join_idiomas
            WHERE e.estado = 'activo' AND e.programa_id IN ($placeholders)
            ORDER BY xp_total DESC, e.nombre ASC";

    $st = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($st, $types, ...$programas_permitidos);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($res)) {
        $listado[] = $row;
        $totales['total']++;
        if ((int)$row['xp_total'] > 0 || (int)$row['quiz_completado'] === 1) $totales['iniciaron']++;
        if ((int)$row['examen_aprobado'] === 1) $totales['aprobaron_examen']++;
    }
}

// ============================================
// HELPERS DE PRESENTACIÓN
// ============================================
function franja_nivel($xp) {
    $xp = (int)$xp;
    if ($xp >= 1200) return ['B2', 1200, 2000];
    if ($xp >= 700)  return ['B1', 700, 1200];
    if ($xp >= 300)  return ['A2', 300, 700];
    return ['A1', 0, 300];
}
function badge_nivel_clase($n) {
    return match($n) {
        'A1' => 'badge-a1',
        'A2' => 'badge-a2',
        'B1' => 'badge-b1',
        'B2' => 'badge-b2',
        'kids' => 'badge-kids',
        default => 'badge-na',
    };
}
function fmt_fecha($f) {
    if (!$f) return '—';
    $t = strtotime($f);
    return $t ? date('d/m/Y', $t) : '—';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avance Inglés – INTEP</title>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        body { background:#f6f8fa; min-height:100vh; }
        .ai-wrap { max-width:1200px; margin:0 auto; padding:1.5rem 1rem 3rem; }
        .ai-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
        .ai-title { font-size:1.4rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:.5rem; }
        .ai-sub { color:#64748b; font-size:.85rem; margin-top:.2rem; }
        .ai-back { color:#0f766e; text-decoration:none; font-weight:600; font-size:.85rem; }
        .ai-back:hover { text-decoration:underline; }

        .ai-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:.8rem; margin-bottom:1.2rem; }
        .ai-stat { background:#fff; border-radius:14px; padding:1rem 1.2rem; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.04); }
        .ai-stat .num { font-size:1.6rem; font-weight:800; color:#0f172a; }
        .ai-stat .lbl { font-size:.78rem; color:#64748b; text-transform:uppercase; letter-spacing:.3px; }
        .ai-stat.verde .num { color:#10b981; }
        .ai-stat.azul .num { color:#3b82f6; }
        .ai-stat.dorado .num { color:#f59e0b; }

        .ai-card { background:#fff; border-radius:14px; padding:1.2rem; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.04); margin-bottom:1.2rem; }
        .ai-card h3 { margin:0 0 .8rem; font-size:1rem; font-weight:700; color:#0f172a; }

        .ai-filtro { display:flex; gap:.6rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap; }
        .ai-filtro select { padding:.5rem .7rem; border-radius:8px; border:1px solid #d1d5db; background:#fff; font-size:.85rem; }

        table.ai-tabla { width:100%; border-collapse:collapse; font-size:.85rem; }
        table.ai-tabla th { text-align:left; padding:.6rem .5rem; background:#f9fafb; color:#374151; font-weight:600; border-bottom:1px solid #e5e7eb; font-size:.78rem; text-transform:uppercase; letter-spacing:.3px; }
        table.ai-tabla td { padding:.7rem .5rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        table.ai-tabla tr:hover td { background:#f8fafc; }
        table.ai-tabla a.ver { color:#0f766e; font-weight:600; text-decoration:none; }
        table.ai-tabla a.ver:hover { text-decoration:underline; }

        .badge { display:inline-block; padding:.2rem .6rem; border-radius:99px; font-size:.72rem; font-weight:700; }
        .badge-a1 { background:#dbeafe; color:#1d4ed8; }
        .badge-a2 { background:#dcfce7; color:#166534; }
        .badge-b1 { background:#fef3c7; color:#92400e; }
        .badge-b2 { background:#fee2e2; color:#991b1b; }
        .badge-kids { background:#fce7f3; color:#9d174d; }
        .badge-na { background:#e5e7eb; color:#6b7280; }
        .badge-aprobado { background:#10b981; color:#fff; }

        .xp-bar { width:100%; max-width:160px; background:#f1f5f9; border-radius:99px; height:8px; overflow:hidden; }
        .xp-bar-fill { height:100%; background:linear-gradient(90deg,#10b981,#3b82f6); border-radius:99px; }

        /* Detalle */
        .detalle-head { display:flex; gap:1rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap; }
        .detalle-foto { width:64px; height:64px; border-radius:50%; background:#0f766e; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; overflow:hidden; }
        .detalle-foto img { width:100%; height:100%; object-fit:cover; }
        .detalle-info h2 { margin:0; font-size:1.2rem; color:#0f172a; }
        .detalle-info .meta { color:#64748b; font-size:.85rem; }
        .detalle-stats { display:flex; gap:.6rem; flex-wrap:wrap; margin-left:auto; }
        .mini-stat { background:#f9fafb; border-radius:10px; padding:.5rem .8rem; border:1px solid #e5e7eb; min-width:80px; text-align:center; }
        .mini-stat .v { font-size:1.1rem; font-weight:800; color:#0f172a; }
        .mini-stat .l { font-size:.7rem; color:#64748b; text-transform:uppercase; }

        .nivel-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:1rem; }
        .nivel-bloque h4 { margin:0 0 .5rem; font-size:.9rem; color:#0f172a; display:flex; align-items:center; gap:.4rem; }
        .modulo-row { display:grid; grid-template-columns:auto 1fr auto auto; gap:.5rem; align-items:center; padding:.4rem .6rem; border-radius:8px; background:#f9fafb; margin-bottom:.3rem; font-size:.82rem; }
        .modulo-row.completo { background:#ecfdf5; }
        .modulo-num { font-weight:700; color:#0f766e; min-width:55px; }
        .modulo-pct { font-weight:700; color:#0f172a; }
        .modulo-fecha { font-size:.7rem; color:#64748b; }

        .empty { text-align:center; padding:2rem; color:#94a3b8; font-size:.9rem; }
        .warn { background:#fef3c7; border:1px solid #fde68a; padding:.7rem 1rem; border-radius:10px; color:#92400e; font-size:.85rem; margin-bottom:1rem; }

        @media (max-width:640px) {
            table.ai-tabla { font-size:.75rem; }
            table.ai-tabla th, table.ai-tabla td { padding:.5rem .3rem; }
            .ocultar-mobile { display:none; }
        }
    </style>
</head>
<body>
<div class="ai-wrap">

    <div class="ai-header">
        <div>
            <h1 class="ai-title">📚 Avance del curso de Inglés</h1>
            <div class="ai-sub">
                <?php if ($rol === 'admin'): ?>
                    Vista de administrador — todos los programas de inglés
                <?php else: ?>
                    Docente: <?php echo htmlspecialchars($nombre); ?> · Programa: <?php echo htmlspecialchars($programas_ingles[$programa_docente] ?? ''); ?>
                <?php endif; ?>
            </div>
        </div>
        <a class="ai-back" href="<?php echo $rol === 'admin' ? 'index.php' : '../dashboard.php'; ?>">← Volver</a>
    </div>

    <?php if (!$tiene_idiomas && !$tiene_progreso): ?>
        <div class="warn">
            ⚠️ Aún no hay registros de progreso del curso de inglés. Los datos aparecerán cuando las estudiantes empiecen a usar el curso.
        </div>
    <?php endif; ?>

    <?php if ($detalle_id && $estudiante_detalle): ?>
        <?php
        $xp = (int)($info_nivel['xp_total'] ?? 0);
        [$niv_franja, $piso, $techo] = franja_nivel($xp);
        $rango = max(1, $techo - $piso);
        $pct_franja = max(0, min(100, round((($xp - $piso) / $rango) * 100)));
        $iniciales = strtoupper(mb_substr($estudiante_detalle['nombre'], 0, 1));
        $foto = $estudiante_detalle['foto'] ?? '';
        ?>
        <div class="ai-card">
            <div class="detalle-head">
                <div class="detalle-foto">
                    <?php if ($foto && file_exists(__DIR__ . '/../uploads/' . $foto)): ?>
                        <img src="/intep/uploads/<?php echo htmlspecialchars($foto); ?>" alt="">
                    <?php else: ?>
                        <?php echo htmlspecialchars($iniciales); ?>
                    <?php endif; ?>
                </div>
                <div class="detalle-info">
                    <h2><?php echo htmlspecialchars($estudiante_detalle['nombre']); ?></h2>
                    <div class="meta">
                        <?php echo htmlspecialchars($estudiante_detalle['documento']); ?> ·
                        <?php echo htmlspecialchars($estudiante_detalle['programa']); ?>
                        <?php if (!empty($info_nivel['apodo'])): ?>
                            · <em>«<?php echo htmlspecialchars($info_nivel['apodo']); ?>»</em>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detalle-stats">
                    <div class="mini-stat">
                        <div class="v"><span class="badge <?php echo badge_nivel_clase($info_nivel['nivel_actual'] ?? 'A1'); ?>"><?php echo htmlspecialchars($info_nivel['nivel_actual'] ?? 'A1'); ?></span></div>
                        <div class="l">Nivel</div>
                    </div>
                    <div class="mini-stat"><div class="v"><?php echo $xp; ?></div><div class="l">XP total</div></div>
                    <div class="mini-stat"><div class="v">🔥 <?php echo (int)($info_nivel['racha_actual'] ?? 0); ?></div><div class="l">Racha</div></div>
                    <div class="mini-stat"><div class="v"><?php echo (int)($info_nivel['racha_maxima'] ?? 0); ?></div><div class="l">Racha máx</div></div>
                </div>
            </div>

            <div style="margin-top:.5rem;">
                <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#64748b;margin-bottom:.3rem;">
                    <span>Progreso a <?php echo $niv_franja; ?> (<?php echo $piso; ?> – <?php echo $techo; ?> XP)</span>
                    <span><?php echo $pct_franja; ?>%</span>
                </div>
                <div class="xp-bar"><div class="xp-bar-fill" style="width:<?php echo $pct_franja; ?>%;max-width:100%"></div></div>
                <div style="font-size:.75rem;color:#94a3b8;margin-top:.3rem;">
                    Última sesión: <?php echo fmt_fecha($info_nivel['ultima_sesion'] ?? null); ?>
                </div>
            </div>
        </div>

        <div class="ai-card">
            <h3>📖 Progreso por módulo</h3>
            <?php if (!$tiene_progreso || empty($progreso_rows)): ?>
                <div class="empty">Aún no ha iniciado ningún módulo del curso interactivo.</div>
            <?php else: ?>
                <div class="nivel-grid">
                    <?php foreach (['A1','A2','B1','kids'] as $niv):
                        $modulos_niv = [];
                        for ($i = 1; $i <= 8; $i++) {
                            $k = $niv . '_' . $i;
                            if (isset($progreso_rows[$k])) $modulos_niv[$i] = $progreso_rows[$k];
                        }
                        // Examen final
                        $exam = $progreso_rows[$niv . '_99'] ?? null;
                        if (empty($modulos_niv) && !$exam) continue;
                    ?>
                        <div class="nivel-bloque">
                            <h4><span class="badge <?php echo badge_nivel_clase($niv); ?>"><?php echo $niv; ?></span> Nivel</h4>
                            <?php for ($i = 1; $i <= 8; $i++):
                                $m = $modulos_niv[$i] ?? null;
                                $pct = $m ? (int)$m['porcentaje'] : 0;
                                $comp = $m && (int)$m['completado'] === 1;
                            ?>
                                <div class="modulo-row <?php echo $comp ? 'completo' : ''; ?>">
                                    <span class="modulo-num">Mód <?php echo $i; ?></span>
                                    <div class="xp-bar" style="max-width:none;"><div class="xp-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                    <span class="modulo-pct"><?php echo $pct; ?>%</span>
                                    <span class="modulo-fecha"><?php echo $comp ? '✅ ' . fmt_fecha($m['fecha_completado']) : '—'; ?></span>
                                </div>
                            <?php endfor; ?>
                            <?php if ($exam): ?>
                                <div class="modulo-row <?php echo (int)$exam['examen_aprobado'] === 1 ? 'completo' : ''; ?>" style="border:1px dashed #10b981;">
                                    <span class="modulo-num">Examen</span>
                                    <div class="xp-bar" style="max-width:none;"><div class="xp-bar-fill" style="width:<?php echo (int)$exam['porcentaje']; ?>%"></div></div>
                                    <span class="modulo-pct">
                                        <?php if ((int)$exam['examen_aprobado'] === 1): ?>
                                            <span class="badge badge-aprobado">✓ Aprobado</span>
                                        <?php else: ?>
                                            <?php echo (int)$exam['porcentaje']; ?>%
                                        <?php endif; ?>
                                    </span>
                                    <span class="modulo-fecha"><?php echo fmt_fecha($exam['fecha_completado']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ai-card">
            <h3>🧪 Historial de quizzes</h3>
            <?php if (!$tiene_quizzes || empty($quiz_rows)): ?>
                <div class="empty">Aún no ha presentado quizzes.</div>
            <?php else: ?>
                <table class="ai-tabla">
                    <thead><tr>
                        <th>Nivel</th><th>Módulo</th><th>Score</th><th>Resultado</th><th>Intento</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($quiz_rows as $q): ?>
                        <tr>
                            <td><span class="badge <?php echo badge_nivel_clase($q['nivel']); ?>"><?php echo htmlspecialchars($q['nivel']); ?></span></td>
                            <td><?php echo (int)$q['modulo_num'] === 0 ? 'General' : 'Mód ' . (int)$q['modulo_num']; ?></td>
                            <td><strong><?php echo (int)$q['score']; ?></strong></td>
                            <td>
                                <?php if ((int)$q['aprobado'] === 1): ?>
                                    <span class="badge badge-aprobado">Aprobado</span>
                                <?php else: ?>
                                    <span class="badge badge-na">No aprobado</span>
                                <?php endif; ?>
                            </td>
                            <td>#<?php echo (int)$q['intento']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <p><a class="ai-back" href="avance_ingles.php">← Volver al listado</a></p>

    <?php else: ?>

        <div class="ai-stats">
            <div class="ai-stat azul"><div class="num"><?php echo $totales['total']; ?></div><div class="lbl">Estudiantes de inglés</div></div>
            <div class="ai-stat verde"><div class="num"><?php echo $totales['iniciaron']; ?></div><div class="lbl">Iniciaron el curso</div></div>
            <div class="ai-stat dorado"><div class="num"><?php echo $totales['aprobaron_examen']; ?></div><div class="lbl">Examen final aprobado</div></div>
        </div>

        <div class="ai-card">
            <?php if ($rol === 'admin' && count($programas_ingles) > 1): ?>
                <form method="get" class="ai-filtro">
                    <label for="programa" style="font-size:.8rem;font-weight:600;color:#374151;">Filtrar por programa:</label>
                    <select name="programa" id="programa" onchange="this.form.submit()">
                        <option value="0">Todos los programas de inglés</option>
                        <?php foreach ($programas_ingles as $pid => $pnom):
                            $sel = (isset($_GET['programa']) && (int)$_GET['programa'] === $pid) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $pid; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($pnom); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <?php if (empty($listado)): ?>
                <div class="empty">No hay estudiantes activas en los programas de inglés.</div>
            <?php else: ?>
                <table class="ai-tabla">
                    <thead><tr>
                        <th>Estudiante</th>
                        <th class="ocultar-mobile">Documento</th>
                        <th class="ocultar-mobile">Programa</th>
                        <th>Nivel</th>
                        <th>XP</th>
                        <th class="ocultar-mobile">Racha</th>
                        <th>Módulos</th>
                        <th>Examen</th>
                        <th class="ocultar-mobile">Última sesión</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($listado as $row):
                        $niv = $row['nivel_actual'] ?? '—';
                        $xp = (int)$row['xp_total'];
                        [$_, $piso, $techo] = franja_nivel($xp);
                        $rango = max(1, $techo - $piso);
                        $pct = max(0, min(100, round((($xp - $piso) / $rango) * 100)));
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                            <td class="ocultar-mobile"><?php echo htmlspecialchars($row['documento']); ?></td>
                            <td class="ocultar-mobile"><?php echo htmlspecialchars($row['programa']); ?></td>
                            <td>
                                <?php if ($niv && $niv !== '—'): ?>
                                    <span class="badge <?php echo badge_nivel_clase($niv); ?>"><?php echo htmlspecialchars($niv); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-na">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:.2rem;">
                                    <strong><?php echo $xp; ?></strong>
                                    <div class="xp-bar"><div class="xp-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                </div>
                            </td>
                            <td class="ocultar-mobile">🔥 <?php echo (int)$row['racha_actual']; ?></td>
                            <td><?php echo (int)$row['modulos_completados']; ?>/32</td>
                            <td>
                                <?php if ((int)$row['examen_aprobado'] === 1): ?>
                                    <span class="badge badge-aprobado">✓</span>
                                <?php else: ?>
                                    <span class="badge badge-na">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="ocultar-mobile"><?php echo fmt_fecha($row['ultima_sesion']); ?></td>
                            <td><a class="ver" href="?id=<?php echo (int)$row['id']; ?>">Ver detalle →</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
<?php include __DIR__ . '/../partials/asistente_admin.php'; ?>
</body>
</html>
