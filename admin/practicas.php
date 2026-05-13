<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}
if ($_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// ── Verificar que las tablas existan ────────────────────────
$chk = mysqli_query($conexion, "SHOW TABLES LIKE 'prac_practicas'");
$tablas_listas = mysqli_num_rows($chk) > 0;

$msg_ok  = $_SESSION['adm_prac_ok']  ?? null;
$msg_err = $_SESSION['adm_prac_err'] ?? null;
unset($_SESSION['adm_prac_ok'], $_SESSION['adm_prac_err']);

$accion = $_GET['action'] ?? 'lista';
$practica_id = (int)($_GET['id'] ?? 0);

// ── INSTALAR TABLAS: ejecutar migración desde el navegador ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'instalar_tablas') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['adm_prac_err'] = 'Token de seguridad inválido. Recarga la página.';
        header('Location: /intep/admin/practicas.php');
        exit;
    }

    $sql_path = __DIR__ . '/../sql/practicas_migration.sql';
    if (!file_exists($sql_path)) {
        $_SESSION['adm_prac_err'] = 'No se encontró el archivo SQL: ' . $sql_path;
        header('Location: /intep/admin/practicas.php');
        exit;
    }

    $sql_content = file_get_contents($sql_path);
    // Quitar USE intep_portal porque ya estamos conectados a la BD correcta
    $sql_content = preg_replace('/USE\s+\w+\s*;/i', '', $sql_content);

    // Dividir por sentencias y ejecutar
    mysqli_multi_query($conexion, $sql_content);
    do {
        if ($result = mysqli_store_result($conexion)) mysqli_free_result($result);
    } while (mysqli_more_results($conexion) && mysqli_next_result($conexion));

    $err = mysqli_error($conexion);

    // Verificar de nuevo
    $chk = mysqli_query($conexion, "SHOW TABLES LIKE 'prac_practicas'");
    if (mysqli_num_rows($chk) > 0) {
        $_SESSION['adm_prac_ok'] = '✅ Tablas creadas correctamente. El módulo de prácticas ya está activo.';
    } else {
        $_SESSION['adm_prac_err'] = 'Error al crear las tablas: ' . ($err ?: 'Causa desconocida');
    }
    header('Location: /intep/admin/practicas.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// POST: Aprobar / suspender / finalizar / cancelar práctica
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tablas_listas) {

    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['adm_prac_err'] = 'Token de seguridad inválido. Recarga la página.';
        header('Location: /intep/admin/practicas.php');
        exit;
    }

    $accion_post = $_POST['accion'] ?? '';
    $pid = (int)($_POST['practica_id'] ?? 0);

    if ($accion_post === 'actualizar_practica' && $pid) {
        $estado      = $_POST['estado'] ?? 'pendiente';
        $fecha_ini   = $_POST['fecha_inicio'] ?: null;
        $fecha_fin   = $_POST['fecha_fin'] ?: null;
        $horas_sem   = (int)($_POST['horas_semanales'] ?? 40);
        $estipendio  = $_POST['estipendio_monto'] !== '' ? (float)$_POST['estipendio_monto'] : null;
        $modalidad_p = $_POST['modalidad_presencial'] ?? 'presencial';
        $obs         = trim($_POST['observaciones'] ?? '');

        $upd = mysqli_prepare($conexion, "
            UPDATE prac_practicas SET
                estado = ?, fecha_inicio = ?, fecha_fin = ?,
                horas_semanales = ?, estipendio_monto = ?,
                modalidad_presencial = ?, observaciones = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($upd, 'sssidssi',
            $estado, $fecha_ini, $fecha_fin, $horas_sem, $estipendio,
            $modalidad_p, $obs, $pid
        );

        if (mysqli_stmt_execute($upd)) {
            $_SESSION['adm_prac_ok'] = 'Práctica actualizada correctamente.';
        } else {
            $_SESSION['adm_prac_err'] = 'Error al actualizar: ' . mysqli_error($conexion);
        }
        header('Location: /intep/admin/practicas.php?action=ver&id=' . $pid);
        exit;
    }

    if ($accion_post === 'validar_seguimiento') {
        $sid = (int)($_POST['seguimiento_id'] ?? 0);
        $obs_admin = trim($_POST['observacion_admin'] ?? '');
        $upd = mysqli_prepare($conexion,
            "UPDATE prac_seguimientos SET validado_admin = 1, observacion_admin = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($upd, 'si', $obs_admin, $sid);
        if (mysqli_stmt_execute($upd)) {
            $_SESSION['adm_prac_ok'] = 'Avance validado correctamente.';
        } else {
            $_SESSION['adm_prac_err'] = 'Error al validar.';
        }
        header('Location: /intep/admin/practicas.php?action=ver&id=' . $pid);
        exit;
    }
}

$csrf = csrf_token();

// ════════════════════════════════════════════════════════════
// FILTROS DE LISTADO
// ════════════════════════════════════════════════════════════
$f_estado = $_GET['f_estado'] ?? '';
$f_tipo   = $_GET['f_tipo']   ?? '';
$f_buscar = trim($_GET['q']   ?? '');

// ════════════════════════════════════════════════════════════
// DATOS
// ════════════════════════════════════════════════════════════
$tipos_nombres = [
    'constancia_laboral'  => 'Constancia Laboral',
    'pasantias'           => 'Pasantías',
    'unidad_productiva'   => 'Unidad Productiva',
    'simulacion'          => 'Simulación',
    'contrato_aprendizaje'=> 'Contrato Aprendizaje',
];
$estados_nombres = [
    'pendiente'   => 'Pendiente',
    'activa'      => 'Activa',
    'suspendida'  => 'Suspendida',
    'finalizada'  => 'Finalizada',
    'cancelada'   => 'Cancelada',
];
$meses_nombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// Estadísticas globales
$stats = ['total' => 0, 'pendientes' => 0, 'activas' => 0, 'finalizadas' => 0, 'requiere_atencion' => 0];
if ($tablas_listas) {
    $r = mysqli_query($conexion, "SELECT estado, COUNT(*) c FROM prac_practicas GROUP BY estado");
    while ($row = mysqli_fetch_assoc($r)) {
        $stats['total'] += (int)$row['c'];
        if ($row['estado'] === 'pendiente')  $stats['pendientes']  = (int)$row['c'];
        if ($row['estado'] === 'activa')     $stats['activas']     = (int)$row['c'];
        if ($row['estado'] === 'finalizada') $stats['finalizadas'] = (int)$row['c'];
    }
    $r = mysqli_query($conexion,
        "SELECT COUNT(*) c FROM prac_seguimientos WHERE requiere_atencion = 1 AND validado_admin = 0");
    $stats['requiere_atencion'] = (int)mysqli_fetch_assoc($r)['c'];
}

// Detalle individual si action=ver
$practica = null;
$seguimientos = [];
if ($accion === 'ver' && $practica_id && $tablas_listas) {
    $q = mysqli_prepare($conexion, "
        SELECT pp.*,
               e.nombre as est_nombre, e.documento as est_doc, e.email as est_email,
               pg.nombre as programa,
               pe.razon_social, pe.nit as empresa_nit,
               pt.nombres as tutor_nombres, pt.apellidos as tutor_apellidos, pt.email as tutor_email,
               pm.nombres as monitor_nombres, pm.apellidos as monitor_apellidos
        FROM prac_practicas pp
        JOIN estudiantes e ON pp.estudiante_id = e.id
        LEFT JOIN programas pg ON e.programa_id = pg.id
        LEFT JOIN prac_empresas pe ON pp.empresa_id = pe.id
        LEFT JOIN prac_tutores pt ON pp.tutor_id = pt.id
        LEFT JOIN prac_monitores pm ON pp.monitor_id = pm.id
        WHERE pp.id = ?
    ");
    mysqli_stmt_bind_param($q, 'i', $practica_id);
    mysqli_stmt_execute($q);
    $practica = mysqli_fetch_assoc(mysqli_stmt_get_result($q));

    if ($practica) {
        $qs = mysqli_prepare($conexion, "
            SELECT * FROM prac_seguimientos WHERE practica_id = ?
            ORDER BY anio DESC, mes DESC
        ");
        mysqli_stmt_bind_param($qs, 'i', $practica_id);
        mysqli_stmt_execute($qs);
        $seguimientos = mysqli_fetch_all(mysqli_stmt_get_result($qs), MYSQLI_ASSOC);
    }
}

// Listado con filtros
$practicas_list = [];
if ($accion === 'lista' && $tablas_listas) {
    $where = "1=1";
    $params = [];
    $types  = '';

    if ($f_estado && isset($estados_nombres[$f_estado])) {
        $where .= " AND pp.estado = ?";
        $params[] = $f_estado;
        $types .= 's';
    }
    if ($f_tipo && isset($tipos_nombres[$f_tipo])) {
        $where .= " AND pp.tipo_practica = ?";
        $params[] = $f_tipo;
        $types .= 's';
    }
    if ($f_buscar !== '') {
        $where .= " AND (e.nombre LIKE ? OR e.documento LIKE ?)";
        $like = '%' . $f_buscar . '%';
        $params[] = $like; $params[] = $like;
        $types .= 'ss';
    }

    $sql = "
        SELECT pp.id, pp.tipo_practica, pp.estado, pp.fecha_inicio, pp.fecha_fin, pp.created_at,
               e.nombre as est_nombre, e.documento,
               pg.nombre as programa,
               pe.razon_social, pp.empresa_nombre,
               (SELECT COUNT(*) FROM prac_seguimientos s WHERE s.practica_id = pp.id) seg_total,
               (SELECT COUNT(*) FROM prac_seguimientos s WHERE s.practica_id = pp.id AND s.requiere_atencion = 1 AND s.validado_admin = 0) seg_atencion
        FROM prac_practicas pp
        JOIN estudiantes e ON pp.estudiante_id = e.id
        LEFT JOIN programas pg ON e.programa_id = pg.id
        LEFT JOIN prac_empresas pe ON pp.empresa_id = pe.id
        WHERE $where
        ORDER BY pp.estado = 'pendiente' DESC, pp.created_at DESC
        LIMIT 200
    ";

    $stmt = mysqli_prepare($conexion, $sql);
    if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $practicas_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prácticas — Admin</title>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        html, body { background: #f8f9fc; min-height: 100%; }

        .adm-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.8rem 1.5rem 4rem;
        }
        .adm-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #10B981;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 1rem;
        }
        .adm-hero {
            background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 50%, #3B82F6 100%);
            border-radius: 18px;
            padding: 1.8rem 2rem;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .adm-hero::before {
            content: '';
            position: absolute;
            top: -50px; right: -30px;
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }
        .adm-hero h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 0.2rem; position: relative; z-index: 1; }
        .adm-hero p { font-size: 0.85rem; opacity: 0.85; margin: 0; position: relative; z-index: 1; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 1rem 1.2rem;
            border: 1px solid #E5E7EB;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .stat-card .num {
            font-size: 1.8rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }
        .stat-card .lbl {
            font-size: 0.75rem;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
            font-weight: 700;
        }
        .stat-card.warn { border-color: #FCD34D; background: linear-gradient(180deg, #FFFBEB, white); }
        .stat-card.warn .num { color: #D97706; }
        .stat-card.danger { border-color: #FCA5A5; background: linear-gradient(180deg, #FEF2F2, white); }
        .stat-card.danger .num { color: #DC2626; }
        .stat-card.success { border-color: #6EE7B7; background: linear-gradient(180deg, #ECFDF5, white); }
        .stat-card.success .num { color: #059669; }

        /* Alerta */
        .adm-alert-ok, .adm-alert-err {
            padding: 0.85rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .adm-alert-ok  { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .adm-alert-err { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        /* Filtros */
        .filtros {
            background: white;
            padding: 1rem 1.2rem;
            border-radius: 14px;
            border: 1px solid #E5E7EB;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            align-items: end;
        }
        .filtros .f-item { flex: 1; min-width: 130px; }
        .filtros label {
            display: block;
            font-size: 0.72rem;
            color: #6B7280;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
            letter-spacing: 0.4px;
        }
        .filtros select, .filtros input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 0.86rem;
            background: white;
            outline: none;
            box-sizing: border-box;
        }
        .filtros select:focus, .filtros input:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .filtros button {
            padding: 9px 18px;
            background: linear-gradient(135deg, #1E40AF, #2563EB);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }

        /* Tabla */
        .tabla-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #E5E7EB;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .tabla-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tabla-card-header h3 { margin: 0; font-size: 0.95rem; font-weight: 700; }

        table.lista {
            width: 100%;
            border-collapse: collapse;
        }
        table.lista th {
            background: #F9FAFB;
            padding: 10px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        table.lista td {
            padding: 12px 14px;
            font-size: 0.85rem;
            color: #374151;
            border-top: 1px solid #F3F4F6;
        }
        table.lista tr:hover td { background: #F9FAFB; }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-pendiente   { background: #FEF3C7; color: #92400E; }
        .badge-activa      { background: #D1FAE5; color: #065F46; }
        .badge-suspendida  { background: #FEE2E2; color: #991B1B; }
        .badge-finalizada  { background: #E0E7FF; color: #3730A3; }
        .badge-cancelada   { background: #F3F4F6; color: #4B5563; }

        .row-atencion td { background: #FEF2F2 !important; }
        .alert-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #DC2626;
            margin-right: 6px;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .btn-ver {
            background: white;
            color: #2563EB;
            border: 1px solid #DBEAFE;
            padding: 5px 14px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .btn-ver:hover { background: #EFF6FF; border-color: #2563EB; }

        .empty {
            padding: 3rem 1rem;
            text-align: center;
            color: #9CA3AF;
        }
        .empty h4 { font-size: 1rem; font-weight: 700; color: #4B5563; margin: 0 0 6px; }

        /* ── Detalle ─────────── */
        .det-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        @media(max-width:900px) { .det-grid { grid-template-columns: 1fr; } }
        .det-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .det-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #F3F4F6;
        }
        .det-card-header h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #111827; }
        .det-card-body { padding: 1.3rem 1.5rem; }
        .det-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
        }
        .det-info label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .det-info span {
            font-size: 0.88rem;
            color: #111827;
            font-weight: 600;
        }

        .form-edit label {
            font-size: 0.78rem; font-weight: 700; color: #374151;
            display: block; margin-bottom: 5px;
        }
        .form-edit input, .form-edit select, .form-edit textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 0.86rem;
            background: white;
            box-sizing: border-box;
            outline: none;
        }
        .form-edit input:focus, .form-edit select:focus, .form-edit textarea:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .btn-prim {
            background: linear-gradient(135deg, #1E40AF, #2563EB);
            color: white;
            padding: 10px 22px;
            border-radius: 50px;
            border: none;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-prim:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }

        .seg-item {
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            margin-bottom: 0.8rem;
            background: white;
        }
        .seg-item.atencion { border-color: #FCA5A5; background: #FEF2F2; }
        .seg-item.validado { border-color: #6EE7B7; }
        .seg-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
            flex-wrap: wrap;
            gap: 6px;
        }
        .seg-titulo { font-weight: 800; color: #111827; font-size: 0.95rem; }
        .seg-meta { font-size: 0.78rem; color: #6B7280; }
        .seg-body p { margin: 0.3rem 0; font-size: 0.83rem; color: #4B5563; line-height: 1.5; }
        .seg-body p strong { color: #111827; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.4px; }
    </style>
</head>
<body>
<div class="adm-container">

    <a href="/intep/dashboard.php" class="adm-back">← Volver al panel</a>

    <div class="adm-hero">
        <h1>📋 Gestión de Prácticas</h1>
        <p>Administra y haz seguimiento a las prácticas profesionales · Decreto 0223 de 2026</p>
    </div>

    <?php if ($msg_ok): ?>
    <div class="adm-alert-ok">✅ <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="adm-alert-err">⚠️ <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

    <?php if (!$tablas_listas): ?>
        <div class="det-card" style="border:2px solid #FCD34D;background:linear-gradient(180deg,#FFFBEB,white)">
            <div class="det-card-body" style="padding:2rem">
                <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:1.5rem">
                    <div style="font-size:2.5rem;line-height:1">🛠️</div>
                    <div>
                        <h2 style="margin:0 0 0.5rem;font-size:1.3rem;color:#92400E">Instalación pendiente</h2>
                        <p style="margin:0;font-size:0.9rem;color:#6B7280;line-height:1.6">
                            El módulo de prácticas necesita crear sus tablas en la base de datos.
                            Esto incluye: empresas, tutores, monitores, prácticas, seguimientos y seguridad social.
                            Es <strong>seguro</strong> ejecutar — no modifica datos existentes.
                        </p>
                    </div>
                </div>

                <form method="POST" action="/intep/admin/practicas.php"
                      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='⏳ Instalando…';">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="accion" value="instalar_tablas">
                    <button type="submit" class="btn-prim" style="font-size:0.95rem;padding:14px 32px">
                        🚀 Crear tablas ahora
                    </button>
                </form>

                <p style="margin:1.2rem 0 0;font-size:0.78rem;color:#9CA3AF">
                    Alternativa: ejecuta manualmente el script <code style="background:#F3F4F6;padding:2px 6px;border-radius:4px">sql/practicas_migration.sql</code>
                </p>
            </div>
        </div>

    <?php elseif ($accion === 'ver' && $practica): ?>

        <a href="/intep/admin/practicas.php" class="adm-back">← Volver al listado</a>

        <div class="det-grid">
            <!-- COLUMNA IZQUIERDA -->
            <div>
                <div class="det-card">
                    <div class="det-card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                        <h3>👤 <?= htmlspecialchars($practica['est_nombre']) ?></h3>
                        <span class="badge badge-<?= $practica['estado'] ?>"><?= $estados_nombres[$practica['estado']] ?></span>
                    </div>
                    <div class="det-card-body">
                        <div class="det-info-grid">
                            <div class="det-info">
                                <label>Documento</label>
                                <span><?= htmlspecialchars($practica['est_doc']) ?></span>
                            </div>
                            <div class="det-info">
                                <label>Programa</label>
                                <span><?= htmlspecialchars($practica['programa'] ?? '—') ?></span>
                            </div>
                            <div class="det-info">
                                <label>Email</label>
                                <span><?= htmlspecialchars($practica['est_email'] ?? '—') ?></span>
                            </div>
                            <div class="det-info">
                                <label>Tipo de práctica</label>
                                <span><?= $tipos_nombres[$practica['tipo_practica']] ?></span>
                            </div>
                            <?php if ($practica['empresa_nombre'] || $practica['razon_social']): ?>
                            <div class="det-info">
                                <label>Empresa</label>
                                <span><?= htmlspecialchars($practica['razon_social'] ?: $practica['empresa_nombre']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($practica['cargo_actual']): ?>
                            <div class="det-info">
                                <label>Cargo actual</label>
                                <span><?= htmlspecialchars($practica['cargo_actual']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($practica['descripcion_negocio']): ?>
                            <div class="det-info" style="grid-column:1/-1">
                                <label>Descripción del negocio</label>
                                <span style="font-weight:500"><?= nl2br(htmlspecialchars($practica['descripcion_negocio'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Seguimientos del estudiante -->
                <div class="det-card" style="margin-top:1.5rem">
                    <div class="det-card-header" style="display:flex;justify-content:space-between;align-items:center">
                        <h3>📊 Avances mensuales del estudiante</h3>
                        <span style="font-size:0.78rem;color:#9CA3AF"><?= count($seguimientos) ?> registro(s)</span>
                    </div>
                    <div class="det-card-body">
                        <?php if (!$seguimientos): ?>
                            <div class="empty">
                                <h4>Sin avances registrados aún</h4>
                                <p style="font-size:0.85rem">El estudiante podrá registrar avances una vez la práctica esté activa.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($seguimientos as $s):
                                $cls = '';
                                if ($s['requiere_atencion'] && !$s['validado_admin']) $cls = 'atencion';
                                elseif ($s['validado_admin']) $cls = 'validado';
                            ?>
                            <div class="seg-item <?= $cls ?>">
                                <div class="seg-head">
                                    <div>
                                        <div class="seg-titulo"><?= $meses_nombres[(int)$s['mes']] ?> <?= $s['anio'] ?></div>
                                        <div class="seg-meta">
                                            <?= (int)$s['horas_cumplidas'] ?>h cumplidas ·
                                            Registrado <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($s['requiere_atencion'] && !$s['validado_admin']): ?>
                                            <span class="badge" style="background:#FEE2E2;color:#991B1B">⚠️ Requiere atención</span>
                                        <?php elseif ($s['validado_admin']): ?>
                                            <span class="badge" style="background:#D1FAE5;color:#065F46">✓ Validado</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:#F3F4F6;color:#6B7280">En revisión</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="seg-body">
                                    <?php if ($s['avance_descripcion']): ?>
                                        <p><strong>Avance:</strong><br><?= nl2br(htmlspecialchars($s['avance_descripcion'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($s['dificultades']): ?>
                                        <p><strong>Dificultades:</strong><br><?= nl2br(htmlspecialchars($s['dificultades'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($s['aprendizajes']): ?>
                                        <p><strong>Aprendizajes:</strong><br><?= nl2br(htmlspecialchars($s['aprendizajes'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($s['archivo_evidencia']): ?>
                                        <p><strong>Evidencia:</strong>
                                            <a href="/intep/<?= htmlspecialchars($s['archivo_evidencia']) ?>" target="_blank" style="color:#2563EB;text-decoration:underline">Ver archivo adjunto</a>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($s['observacion_admin']): ?>
                                        <p style="background:#ECFDF5;padding:8px 12px;border-radius:8px;border-left:3px solid #10B981">
                                            <strong>Observación del coordinador:</strong><br>
                                            <?= nl2br(htmlspecialchars($s['observacion_admin'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$s['validado_admin']): ?>
                                <form method="POST" action="/intep/admin/practicas.php" style="margin-top:0.8rem;display:flex;gap:6px;flex-wrap:wrap">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="accion" value="validar_seguimiento">
                                    <input type="hidden" name="practica_id" value="<?= $practica_id ?>">
                                    <input type="hidden" name="seguimiento_id" value="<?= $s['id'] ?>">
                                    <input type="text" name="observacion_admin" placeholder="Observación (opcional)"
                                           style="flex:1;min-width:200px;padding:7px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:0.82rem">
                                    <button type="submit" class="btn-prim" style="padding:7px 16px;font-size:0.8rem">✓ Validar</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA — Editor -->
            <div>
                <div class="det-card">
                    <div class="det-card-header">
                        <h3>⚙️ Gestionar práctica</h3>
                    </div>
                    <div class="det-card-body">
                        <form method="POST" action="/intep/admin/practicas.php" class="form-edit">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="accion" value="actualizar_practica">
                            <input type="hidden" name="practica_id" value="<?= $practica_id ?>">

                            <div style="margin-bottom:0.9rem">
                                <label>Estado</label>
                                <select name="estado">
                                    <?php foreach ($estados_nombres as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $practica['estado'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-bottom:0.9rem">
                                <label>Fecha inicio</label>
                                <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($practica['fecha_inicio'] ?? '') ?>">
                            </div>

                            <div style="margin-bottom:0.9rem">
                                <label>Fecha fin</label>
                                <input type="date" name="fecha_fin" value="<?= htmlspecialchars($practica['fecha_fin'] ?? '') ?>">
                            </div>

                            <div style="margin-bottom:0.9rem">
                                <label>Horas semanales</label>
                                <input type="number" name="horas_semanales" min="1" max="48"
                                       value="<?= (int)($practica['horas_semanales'] ?? 40) ?>">
                            </div>

                            <div style="margin-bottom:0.9rem">
                                <label>Estipendio mensual (COP)</label>
                                <input type="number" name="estipendio_monto" step="1000" min="0"
                                       value="<?= htmlspecialchars($practica['estipendio_monto'] ?? '') ?>"
                                       placeholder="Ej: 1067625">
                            </div>

                            <div style="margin-bottom:0.9rem">
                                <label>Modalidad</label>
                                <select name="modalidad_presencial">
                                    <?php foreach (['presencial','hibrida','virtual'] as $m): ?>
                                    <option value="<?= $m ?>" <?= ($practica['modalidad_presencial'] ?? 'presencial') === $m ? 'selected' : '' ?>>
                                        <?= ucfirst($m) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-bottom:0.9rem">
                                <label>Observaciones</label>
                                <textarea name="observaciones" rows="3"><?= htmlspecialchars($practica['observaciones'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn-prim" style="width:100%;justify-content:center">
                                💾 Guardar cambios
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($practica['tutor_nombres']): ?>
                <div class="det-card" style="margin-top:1rem">
                    <div class="det-card-header"><h3>👨‍💼 Tutor empresarial</h3></div>
                    <div class="det-card-body">
                        <div class="det-info">
                            <label>Nombre</label>
                            <span><?= htmlspecialchars($practica['tutor_nombres'] . ' ' . $practica['tutor_apellidos']) ?></span>
                        </div>
                        <?php if ($practica['tutor_email']): ?>
                        <div class="det-info" style="margin-top:0.5rem">
                            <label>Email</label>
                            <span style="font-weight:500"><?= htmlspecialchars($practica['tutor_email']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- ═══════════════════ LISTADO ═══════════════════ -->

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="num"><?= $stats['total'] ?></div>
                <div class="lbl">Total prácticas</div>
            </div>
            <div class="stat-card warn">
                <div class="num"><?= $stats['pendientes'] ?></div>
                <div class="lbl">Por aprobar</div>
            </div>
            <div class="stat-card success">
                <div class="num"><?= $stats['activas'] ?></div>
                <div class="lbl">Activas</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $stats['finalizadas'] ?></div>
                <div class="lbl">Finalizadas</div>
            </div>
            <div class="stat-card danger">
                <div class="num"><?= $stats['requiere_atencion'] ?></div>
                <div class="lbl">Requieren atención</div>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" action="/intep/admin/practicas.php" class="filtros">
            <div class="f-item">
                <label>Buscar estudiante</label>
                <input type="text" name="q" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Nombre o documento">
            </div>
            <div class="f-item">
                <label>Estado</label>
                <select name="f_estado">
                    <option value="">Todos</option>
                    <?php foreach ($estados_nombres as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $f_estado === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f-item">
                <label>Tipo</label>
                <select name="f_tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos_nombres as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $f_tipo === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">🔍 Filtrar</button>
        </form>

        <!-- Listado -->
        <div class="tabla-card">
            <div class="tabla-card-header">
                <h3>Prácticas registradas (<?= count($practicas_list) ?>)</h3>
            </div>

            <?php if (!$practicas_list): ?>
                <div class="empty">
                    <h4>No hay prácticas que coincidan</h4>
                    <p style="font-size:0.85rem">Cuando los estudiantes registren sus prácticas, aparecerán aquí.</p>
                </div>
            <?php else: ?>
            <table class="lista">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Programa</th>
                        <th>Tipo</th>
                        <th>Empresa</th>
                        <th>Estado</th>
                        <th>Avances</th>
                        <th>Solicitada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($practicas_list as $p): ?>
                    <tr class="<?= $p['seg_atencion'] > 0 ? 'row-atencion' : '' ?>">
                        <td>
                            <?php if ($p['seg_atencion'] > 0): ?><span class="alert-dot" title="Requiere atención"></span><?php endif; ?>
                            <strong><?= htmlspecialchars($p['est_nombre']) ?></strong><br>
                            <span style="font-size:0.75rem;color:#9CA3AF"><?= htmlspecialchars($p['documento']) ?></span>
                        </td>
                        <td style="font-size:0.82rem"><?= htmlspecialchars($p['programa'] ?? '—') ?></td>
                        <td>
                            <span style="font-size:0.78rem;font-weight:700"><?= $tipos_nombres[$p['tipo_practica']] ?? $p['tipo_practica'] ?></span>
                        </td>
                        <td style="font-size:0.82rem"><?= htmlspecialchars($p['razon_social'] ?: $p['empresa_nombre'] ?: '—') ?></td>
                        <td><span class="badge badge-<?= $p['estado'] ?>"><?= $estados_nombres[$p['estado']] ?></span></td>
                        <td style="text-align:center">
                            <strong><?= (int)$p['seg_total'] ?></strong>
                            <?php if ($p['seg_atencion'] > 0): ?>
                                <br><span style="font-size:0.7rem;color:#DC2626;font-weight:700"><?= (int)$p['seg_atencion'] ?> ⚠️</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.78rem;color:#6B7280"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                        <td>
                            <a href="/intep/admin/practicas.php?action=ver&id=<?= $p['id'] ?>" class="btn-ver">Ver →</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
</body>
</html>
