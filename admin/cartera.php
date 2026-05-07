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

$mensaje = '';
$tipo_msg = '';
$vista = $_GET['vista'] ?? 'dashboard';
$csrf_alertas = csrf_token();

// ============================================
// ACCIONES POST
// ============================================

// --- Guardar concepto ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_concepto'])) {
    $nombre = trim($_POST['concepto_nombre']);
    $descripcion = trim($_POST['concepto_descripcion']);
    $monto = (float)$_POST['concepto_monto'];
    $tipo = $_POST['concepto_tipo'];
    $num_cuotas = max(1, (int)($_POST['concepto_cuotas'] ?? 1));

    if (empty($nombre) || $monto <= 0) {
        $mensaje = '⚠️ Nombre y monto son obligatorios.';
        $tipo_msg = 'error';
    } else {
        if (isset($_POST['concepto_id']) && $_POST['concepto_id'] > 0) {
            $id = (int)$_POST['concepto_id'];
            $sql = "UPDATE conceptos_cobro SET nombre=?, descripcion=?, monto_base=?, tipo=?, num_cuotas=? WHERE id=?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, 'ssdsii', $nombre, $descripcion, $monto, $tipo, $num_cuotas, $id);
        } else {
            $sql = "INSERT INTO conceptos_cobro (nombre, descripcion, monto_base, tipo, num_cuotas) VALUES (?,?,?,?,?)";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, 'ssdsi', $nombre, $descripcion, $monto, $tipo, $num_cuotas);
        }
        if ($stmt && mysqli_stmt_execute($stmt)) {
            $mensaje = '✅ Concepto guardado correctamente.';
            $tipo_msg = 'exito';
        } else {
            $mensaje = '❌ Error: ' . mysqli_error($conexion);
            $tipo_msg = 'error';
        }
    }
    $vista = 'conceptos';
}

// --- Generar cobros ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_cobros'])) {
    $concepto_id = (int)$_POST['cobro_concepto'];
    $programa_id = (int)$_POST['cobro_programa'];
    $fecha_venc = $_POST['cobro_vencimiento'];
    $monto_custom = !empty($_POST['cobro_monto']) ? (float)$_POST['cobro_monto'] : null;
    $generar_todas = isset($_POST['cobro_todas_cuotas']) ? true : false;

    // Obtener info del concepto
    $r = mysqli_query($conexion, "SELECT monto_base, num_cuotas FROM conceptos_cobro WHERE id=$concepto_id");
    $concepto_info = mysqli_fetch_assoc($r);
    if ($monto_custom === null) {
        $monto_custom = $concepto_info['monto_base'];
    }
    $num_cuotas = $generar_todas ? (int)$concepto_info['num_cuotas'] : 1;

    // Obtener estudiantes del programa
    $sql_est = "SELECT id FROM estudiantes WHERE estado='activo'";
    if ($programa_id > 0) {
        $sql_est .= " AND programa_id=$programa_id";
    }
    $res_est = mysqli_query($conexion, $sql_est);

    $generados = 0;
    $duplicados = 0;
    $fecha_base = new DateTime($fecha_venc);

    while ($est = mysqli_fetch_assoc($res_est)) {
        for ($cuota = 0; $cuota < $num_cuotas; $cuota++) {
            // Calcular periodo y fecha de vencimiento para cada cuota
            $fecha_cuota = clone $fecha_base;
            $fecha_cuota->modify("+$cuota months");
            $periodo = $fecha_cuota->format('Y-m');
            $fecha_venc_cuota = $fecha_cuota->format('Y-m-d');

            // Verificar que no exista cobro igual
            $check = mysqli_prepare($conexion, "SELECT id FROM cobros WHERE estudiante_id=? AND concepto_id=? AND periodo=?");
            mysqli_stmt_bind_param($check, 'iis', $est['id'], $concepto_id, $periodo);
            mysqli_stmt_execute($check);
            $existe = mysqli_stmt_get_result($check);

            if (mysqli_num_rows($existe) === 0) {
                $sql_ins = "INSERT INTO cobros (estudiante_id, concepto_id, periodo, monto, descuento, total, pagado, saldo, fecha_vencimiento, estado)
                            VALUES (?, ?, ?, ?, 0, ?, 0, ?, ?, 'pendiente')";
                $stmt_ins = mysqli_prepare($conexion, $sql_ins);
                mysqli_stmt_bind_param($stmt_ins, 'iisddds',
                    $est['id'], $concepto_id, $periodo,
                    $monto_custom, $monto_custom, $monto_custom, $fecha_venc_cuota
                );
                mysqli_stmt_execute($stmt_ins);
                $generados++;
            } else {
                $duplicados++;
            }
        }
    }
    $msg_cuotas = $num_cuotas > 1 ? " ($num_cuotas cuotas por estudiante)" : '';
    $mensaje = "✅ Se generaron $generados cobros$msg_cuotas." . ($duplicados > 0 ? " ($duplicados ya existían)" : '');
    $tipo_msg = 'exito';
    $vista = 'cobros';
}

// --- Registrar pago ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $cobro_id = (int)$_POST['pago_cobro_id'];
    $monto_pago = (float)$_POST['pago_monto'];
    $fecha_pago = $_POST['pago_fecha'];
    $metodo = $_POST['pago_metodo'];
    $referencia = trim($_POST['pago_referencia']);
    $observaciones = trim($_POST['pago_observaciones']);
    $registrado_por = $_SESSION['usuario_id'];

    // Obtener info del cobro
    $r_cobro = mysqli_prepare($conexion, "SELECT * FROM cobros WHERE id=?");
    mysqli_stmt_bind_param($r_cobro, 'i', $cobro_id);
    mysqli_stmt_execute($r_cobro);
    $cobro_info = mysqli_fetch_assoc(mysqli_stmt_get_result($r_cobro));

    if (!$cobro_info) {
        $mensaje = '❌ Cobro no encontrado.';
        $tipo_msg = 'error';
    } elseif ($monto_pago <= 0) {
        $mensaje = '⚠️ El monto debe ser mayor a 0.';
        $tipo_msg = 'error';
    } elseif ($monto_pago > $cobro_info['saldo']) {
        $mensaje = '⚠️ El monto excede el saldo pendiente ($' . number_format($cobro_info['saldo'], 0) . ').';
        $tipo_msg = 'error';
    } else {
        // Insertar pago
        $sql_pago = "INSERT INTO pagos (cobro_id, estudiante_id, monto, fecha_pago, metodo_pago, referencia, observaciones, registrado_por)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_pago = mysqli_prepare($conexion, $sql_pago);
        mysqli_stmt_bind_param($stmt_pago, 'iidssssi',
            $cobro_id, $cobro_info['estudiante_id'], $monto_pago,
            $fecha_pago, $metodo, $referencia, $observaciones, $registrado_por
        );

        if (mysqli_stmt_execute($stmt_pago)) {
            // Actualizar cobro
            $nuevo_pagado = $cobro_info['pagado'] + $monto_pago;
            $nuevo_saldo = $cobro_info['total'] - $nuevo_pagado;
            $nuevo_estado = $nuevo_saldo <= 0 ? 'pagado' : 'parcial';

            $upd = mysqli_prepare($conexion, "UPDATE cobros SET pagado=?, saldo=?, estado=? WHERE id=?");
            mysqli_stmt_bind_param($upd, 'ddsi', $nuevo_pagado, $nuevo_saldo, $nuevo_estado, $cobro_id);
            mysqli_stmt_execute($upd);

            $mensaje = '✅ Pago de $' . number_format($monto_pago, 0) . ' registrado correctamente.';
            $tipo_msg = 'exito';
        } else {
            $mensaje = '❌ Error al registrar pago: ' . mysqli_error($conexion);
            $tipo_msg = 'error';
        }
    }
    $vista = 'estado_cuenta';
    $_GET['est_id'] = $cobro_info['estudiante_id'] ?? '';
}

// --- Actualizar vencidos ---
mysqli_query($conexion, "UPDATE cobros SET estado='vencido' WHERE estado='pendiente' AND fecha_vencimiento < CURDATE()");

// ============================================
// CONSULTAS GENERALES
// ============================================

// Stats dashboard
$stats = [];
$r = mysqli_query($conexion, "SELECT 
    COALESCE(SUM(total), 0) as total_facturado,
    COALESCE(SUM(pagado), 0) as total_recaudado,
    COALESCE(SUM(saldo), 0) as total_pendiente
    FROM cobros WHERE estado != 'anulado'");
$stats = mysqli_fetch_assoc($r);

$r2 = mysqli_query($conexion, "SELECT COUNT(DISTINCT estudiante_id) as morosos FROM cobros WHERE estado IN ('vencido')");
$stats['morosos'] = mysqli_fetch_assoc($r2)['morosos'];

$r3 = mysqli_query($conexion, "SELECT COALESCE(SUM(monto),0) as recaudo_mes FROM pagos 
      WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE())");
$stats['recaudo_mes'] = mysqli_fetch_assoc($r3)['recaudo_mes'];

$r4 = mysqli_query($conexion, "SELECT COUNT(*) as total FROM cobros WHERE estado='vencido'");
$stats['cobros_vencidos'] = mysqli_fetch_assoc($r4)['total'];

// Programas
$programas = [];
$rp = mysqli_query($conexion, "SELECT * FROM programas ORDER BY nombre");
while ($row = mysqli_fetch_assoc($rp)) $programas[] = $row;

// Conceptos
$conceptos = [];
$rc = mysqli_query($conexion, "SELECT * FROM conceptos_cobro WHERE estado='activo' ORDER BY tipo, nombre");
while ($row = mysqli_fetch_assoc($rc)) $conceptos[] = $row;

// Porcentaje de recaudo
$pct_recaudo = $stats['total_facturado'] > 0 
    ? round(($stats['total_recaudado'] / $stats['total_facturado']) * 100, 1) 
    : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartera – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        body { background: #F0FDF4; }
        .page-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }

        /* Tabs */
        .tabs-nav {
            display: flex; gap: 0.3rem; margin-bottom: 1.5rem;
            background: white; border-radius: 12px; padding: 0.4rem;
            box-shadow: 0 2px 6px rgba(5,150,105,0.06);
            overflow-x: auto;
        }
        .tab-link {
            padding: 0.7rem 1.2rem; border-radius: 10px;
            font-size: 0.85rem; font-weight: 600;
            text-decoration: none; color: #6B7280;
            white-space: nowrap; transition: all 0.2s;
        }
        .tab-link:hover { background: #ECFDF5; color: #059669; }
        .tab-link.activo { background: #059669; color: white; }

        /* Stats */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white; border-radius: 14px; padding: 1.3rem;
            box-shadow: 0 2px 8px rgba(5,150,105,0.06);
            border-left: 4px solid #D1FAE5;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card .valor { font-size: 1.6rem; font-weight: 800; display: block; }
        .stat-card .etiqueta { font-size: 0.78rem; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card.recaudado { border-color: #10B981; }
        .stat-card.recaudado .valor { color: #10B981; }
        .stat-card.pendiente { border-color: #F59E0B; }
        .stat-card.pendiente .valor { color: #F59E0B; }
        .stat-card.vencido { border-color: #EF4444; }
        .stat-card.vencido .valor { color: #EF4444; }
        .stat-card.facturado { border-color: #059669; }
        .stat-card.facturado .valor { color: #059669; }
        .stat-card.mes { border-color: #3B82F6; }
        .stat-card.mes .valor { color: #3B82F6; }
        .stat-card.morosos { border-color: #D946A8; }
        .stat-card.morosos .valor { color: #D946A8; }

        /* Progress bar */
        .barra-recaudo { background: #ECFDF5; border-radius: 8px; height: 10px; overflow: hidden; margin-top: 0.5rem; }
        .barra-recaudo-fill { height: 100%; border-radius: 8px; background: linear-gradient(90deg, #059669, #10B981); transition: width 0.8s; }

        /* Card */
        .card { 
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px; 
            padding: 1.5rem; 
            box-shadow: 0 4px 20px rgba(5,150,105,0.08), 0 2px 8px rgba(0,0,0,0.04); 
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.12), 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .card-title { font-size: 1.05rem; font-weight: 700; color: #022C22; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

        /* Tabla */
        .tabla-wrap { overflow-x: auto; }
        .tabla-wrap table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .tabla-wrap thead { background: #022C22; color: white; }
        .tabla-wrap th { padding: 0.8rem 1rem; text-align: left; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .tabla-wrap td { padding: 0.7rem 1rem; font-size: 0.88rem; border-bottom: 1px solid #D1FAE5; }
        .tabla-wrap tr:hover { background: #F0FDF4; }
        .tabla-wrap tr:last-child td { border-bottom: none; }

        /* Badges */
        .badge-estado { padding: 0.25rem 0.7rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-pagado { background: #ECFDF5; color: #065F46; }
        .badge-pendiente { background: #FFFBEB; color: #92400E; }
        .badge-vencido { background: #FEF2F2; color: #991B1B; }
        .badge-parcial { background: #ECFDF5; color: #059669; }
        .badge-anulado { background: #F3F4F6; color: #6B7280; }

        /* Forms */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .form-grupo { margin-bottom: 1rem; }
        .form-grupo label { display: block; font-size: 0.78rem; font-weight: 700; color: #6B7280; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-grupo input, .form-grupo select, .form-grupo textarea {
            width: 100%; padding: 0.7rem 0.9rem; border: 2px solid #D1FAE5;
            border-radius: 10px; font-size: 0.9rem; outline: none; transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-grupo input:focus, .form-grupo select:focus, .form-grupo textarea:focus { border-color: #10B981; }
        .form-grupo textarea { resize: vertical; min-height: 60px; }

        /* Botones */
        .btn-primary {
            padding: 0.8rem 1.5rem; background: linear-gradient(135deg, #059669, #10B981);
            color: white; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.3); }
        .btn-sm {
            padding: 0.35rem 0.8rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.2s;
        }
        .btn-ver { background: #ECFDF5; color: #059669; }
        .btn-ver:hover { background: #059669; color: white; }
        .btn-pagar { background: #ECFDF5; color: #065F46; }
        .btn-pagar:hover { background: #10B981; color: white; }
        .btn-danger { background: #FEF2F2; color: #991B1B; }
        .btn-danger:hover { background: #EF4444; color: white; }

        /* Alerta */
        .alerta { padding: 0.9rem 1.2rem; border-radius: 10px; margin-bottom: 1.2rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
        .alerta.exito { background: #ECFDF5; color: #065F46; border-left: 4px solid #10B981; }
        .alerta.error { background: #FEF2F2; color: #991B1B; border-left: 4px solid #EF4444; }

        /* Estado cuenta header */
        .est-header {
            background: linear-gradient(135deg, #064E3B, #059669);
            color: white; border-radius: 14px; padding: 1.5rem;
            margin-bottom: 1.5rem; display: flex;
            justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
        }
        .est-header h2 { margin: 0; font-size: 1.2rem; }
        .est-header .badge-prog { background: rgba(255,255,255,0.2); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.82rem; }
        .est-saldo { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .est-saldo-item { text-align: center; }
        .est-saldo-item .num { font-size: 1.3rem; font-weight: 800; display: block; }
        .est-saldo-item .lbl { font-size: 0.72rem; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Filtros */
        .filtros { display: flex; gap: 0.8rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: end; }
        .filtros .form-grupo { margin-bottom: 0; }

        /* Concepto cards */
        .conceptos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }
        .concepto-card {
            background: white; border-radius: 12px; padding: 1.2rem;
            border: 2px solid #ECFDF5; transition: all 0.2s;
        }
        .concepto-card:hover { border-color: #A7F3D0; }
        .concepto-card .tipo-tag {
            font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 6px;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .tipo-matricula { background: #ECFDF5; color: #059669; }
        .tipo-mensualidad { background: #DBEAFE; color: #1D4ED8; }
        .tipo-seminario { background: #F3E8FF; color: #7C3AED; }
        .tipo-otro { background: #F3F4F6; color: #6B7280; }

        /* Modal overlay */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(2,44,34,0.6); z-index: 1000;
            align-items: center; justify-content: center; padding: 1rem;
        }
        .modal-overlay.activo { display: flex; }
        .modal-box {
            background: white; border-radius: 16px; padding: 2rem;
            max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-title { font-size: 1.1rem; font-weight: 700; color: #022C22; margin-bottom: 1.2rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .tabs-nav { flex-wrap: nowrap; }
            .tab-link { font-size: 0.78rem; padding: 0.6rem 0.8rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
            .est-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body data-rol="admin">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">💰 Cartera y Pagos</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="page-wrap">

    <a href="../dashboard.php" class="btn-volver">← Volver al inicio</a>

    <!-- TABS -->
    <nav class="tabs-nav">
        <a href="?vista=dashboard" class="tab-link <?php echo $vista==='dashboard'?'activo':''; ?>">📊 Resumen</a>
        <a href="?vista=estudiantes" class="tab-link <?php echo $vista==='estudiantes'?'activo':''; ?>">👥 Estudiantes</a>
        <a href="?vista=cobros" class="tab-link <?php echo $vista==='cobros'?'activo':''; ?>">📄 Generar Cobros</a>
        <a href="?vista=pagos" class="tab-link <?php echo $vista==='pagos'?'activo':''; ?>">💳 Historial Pagos</a>
        <a href="?vista=morosos" class="tab-link <?php echo $vista==='morosos'?'activo':''; ?>">🚨 Morosos</a>
        <a href="?vista=conceptos" class="tab-link <?php echo $vista==='conceptos'?'activo':''; ?>">⚙️ Conceptos</a>
    </nav>

    <?php if ($mensaje): ?>
        <div class="alerta <?php echo $tipo_msg; ?>"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- VISTA: DASHBOARD -->
    <!-- ============================================ -->
    <?php if ($vista === 'dashboard'): ?>

        <div class="stats-grid">
            <div class="stat-card facturado">
                <span class="valor">$<?php echo number_format($stats['total_facturado'], 0, ',', '.'); ?></span>
                <span class="etiqueta">Total Facturado</span>
            </div>
            <div class="stat-card recaudado">
                <span class="valor">$<?php echo number_format($stats['total_recaudado'], 0, ',', '.'); ?></span>
                <span class="etiqueta">Total Recaudado</span>
            </div>
            <div class="stat-card pendiente">
                <span class="valor">$<?php echo number_format($stats['total_pendiente'], 0, ',', '.'); ?></span>
                <span class="etiqueta">Saldo Pendiente</span>
            </div>
            <div class="stat-card mes">
                <span class="valor">$<?php echo number_format($stats['recaudo_mes'], 0, ',', '.'); ?></span>
                <span class="etiqueta">Recaudo Este Mes</span>
            </div>
            <div class="stat-card vencido">
                <span class="valor"><?php echo $stats['cobros_vencidos']; ?></span>
                <span class="etiqueta">Cobros Vencidos</span>
            </div>
            <div class="stat-card morosos">
                <span class="valor"><?php echo $stats['morosos']; ?></span>
                <span class="etiqueta">Estudiantes Morosos</span>
            </div>
        </div>

        <!-- Barra de recaudo -->
        <div class="card">
            <div class="card-title">📈 Porcentaje de Recaudo General</div>
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#6B7280;margin-bottom:0.3rem;">
                <span>Recaudado: $<?php echo number_format($stats['total_recaudado'], 0, ',', '.'); ?></span>
                <span><strong style="color:#059669;"><?php echo $pct_recaudo; ?>%</strong></span>
            </div>
            <div class="barra-recaudo">
                <div class="barra-recaudo-fill" style="width:<?php echo $pct_recaudo; ?>%"></div>
            </div>
        </div>

        <!-- Últimos pagos -->
        <div class="card">
            <div class="card-title">💳 Últimos Pagos Registrados</div>
            <?php
            $ult_pagos = mysqli_query($conexion, 
                "SELECT p.*, e.nombre as est_nombre, c.periodo, cc.nombre as concepto
                 FROM pagos p
                 JOIN estudiantes e ON p.estudiante_id = e.id
                 JOIN cobros c ON p.cobro_id = c.id
                 JOIN conceptos_cobro cc ON c.concepto_id = cc.id
                 ORDER BY p.created_at DESC LIMIT 10");
            ?>
            <div class="tabla-wrap">
                <table>
                    <thead>
                        <tr><th>Fecha</th><th>Estudiante</th><th>Concepto</th><th>Período</th><th>Monto</th><th>Método</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($ult_pagos) === 0): ?>
                            <tr><td colspan="6" style="text-align:center;color:#9CA3AF;padding:2rem;">No hay pagos registrados aún.</td></tr>
                        <?php else: while ($p = mysqli_fetch_assoc($ult_pagos)): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($p['est_nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['concepto']); ?></td>
                                <td><?php echo $p['periodo']; ?></td>
                                <td style="font-weight:700;color:#10B981;">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></td>
                                <td><?php echo ucfirst($p['metodo_pago']); ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ============================================ -->
    <!-- VISTA: ESTUDIANTES (Estado de cartera) -->
    <!-- ============================================ -->
    <?php elseif ($vista === 'estudiantes'): ?>

        <div class="card">
            <div class="card-title">👥 Estado de Cartera por Estudiante</div>

            <div class="filtros">
                <div class="form-grupo">
                    <label>Programa</label>
                    <select id="filtro-prog" onchange="filtrarEstudiantes()">
                        <option value="">Todos</option>
                        <?php foreach ($programas as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grupo">
                    <label>Buscar</label>
                    <input type="text" id="filtro-buscar" placeholder="Nombre o documento..." oninput="filtrarEstudiantes()">
                </div>
            </div>

            <?php
            $est_cartera = mysqli_query($conexion, 
                "SELECT e.id, e.nombre, e.documento, p.nombre as programa,
                        COALESCE(SUM(c.total), 0) as total_cobros,
                        COALESCE(SUM(c.pagado), 0) as total_pagado,
                        COALESCE(SUM(c.saldo), 0) as saldo,
                        SUM(CASE WHEN c.estado='vencido' THEN 1 ELSE 0 END) as vencidos
                 FROM estudiantes e
                 LEFT JOIN programas p ON e.programa_id = p.id
                 LEFT JOIN cobros c ON e.id = c.estudiante_id AND c.estado != 'anulado'
                 WHERE e.estado = 'activo'
                 GROUP BY e.id
                 ORDER BY saldo DESC, e.nombre");
            ?>
            <div class="tabla-wrap">
                <table id="tabla-estudiantes">
                    <thead>
                        <tr><th>Estudiante</th><th>Documento</th><th>Programa</th><th>Total Cobros</th><th>Pagado</th><th>Saldo</th><th>Estado</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($e = mysqli_fetch_assoc($est_cartera)): ?>
                        <tr data-prog="<?php echo $e['programa'] ?? ''; ?>" data-nombre="<?php echo strtolower($e['nombre'] . ' ' . $e['documento']); ?>">
                            <td><strong><?php echo htmlspecialchars($e['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($e['documento']); ?></td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($e['programa'] ?? 'Sin programa'); ?></td>
                            <td>$<?php echo number_format($e['total_cobros'], 0, ',', '.'); ?></td>
                            <td style="color:#10B981;font-weight:600;">$<?php echo number_format($e['total_pagado'], 0, ',', '.'); ?></td>
                            <td style="font-weight:700;color:<?php echo $e['saldo'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                                $<?php echo number_format($e['saldo'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <?php if ($e['vencidos'] > 0): ?>
                                    <span class="badge-estado badge-vencido">🚨 Mora</span>
                                <?php elseif ($e['saldo'] > 0): ?>
                                    <span class="badge-estado badge-pendiente">⏳ Debe</span>
                                <?php elseif ($e['total_cobros'] > 0): ?>
                                    <span class="badge-estado badge-pagado">✅ Paz y Salvo</span>
                                <?php else: ?>
                                    <span class="badge-estado badge-anulado">— Sin cobros</span>
                                <?php endif; ?>
                            </td>
                            <td style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                                <a href="?vista=estado_cuenta&est_id=<?php echo $e['id']; ?>" class="btn-sm btn-ver">Ver cuenta</a>
                                <button type="button" class="btn-sm" title="Hacer titilar Cartera para este estudiante"
                                        style="background:#F59E0B;color:white;border:none;cursor:pointer;padding:0.35rem 0.7rem;border-radius:8px;font-weight:600;"
                                        onclick='dispararAlertaCartera([<?= (int)$e['id'] ?>], this)'>
                                    🔔
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        // CSRF + helpers de alerta también disponibles en la pestaña estudiantes
        if (!window.__csrfAlertas) {
            window.__csrfAlertas = '<?= htmlspecialchars($csrf_alertas, ENT_QUOTES, 'UTF-8') ?>';
        }
        if (typeof dispararAlertaCartera !== 'function') {
            window.dispararAlertaCartera = async function (ids, btn) {
                if (!Array.isArray(ids) || ids.length === 0) return;
                const original = btn.textContent;
                btn.disabled = true; btn.textContent = '...';
                try {
                    const r = await fetch('api_alertas_admin.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            modulo: 'cartera', accion: 'disparar',
                            estudiante_ids: ids, csrf_token: window.__csrfAlertas
                        })
                    });
                    const d = await r.json();
                    if (d.csrf_token) window.__csrfAlertas = d.csrf_token;
                    if (d.ok) {
                        btn.textContent = '✓';
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
            };
        }
        </script>

    <!-- ============================================ -->
    <!-- VISTA: ESTADO DE CUENTA INDIVIDUAL -->
    <!-- ============================================ -->
    <?php elseif ($vista === 'estado_cuenta' && isset($_GET['est_id'])): ?>
        <?php
        $est_id = (int)$_GET['est_id'];
        $info_est = mysqli_fetch_assoc(mysqli_query($conexion, 
            "SELECT e.*, p.nombre as programa FROM estudiantes e LEFT JOIN programas p ON e.programa_id = p.id WHERE e.id = $est_id"));

        $cobros_est = mysqli_query($conexion, 
            "SELECT c.*, cc.nombre as concepto, cc.tipo
             FROM cobros c
             JOIN conceptos_cobro cc ON c.concepto_id = cc.id
             WHERE c.estudiante_id = $est_id AND c.estado != 'anulado'
             ORDER BY c.fecha_vencimiento DESC");

        $total_est = mysqli_fetch_assoc(mysqli_query($conexion, 
            "SELECT COALESCE(SUM(total),0) as total, COALESCE(SUM(pagado),0) as pagado, COALESCE(SUM(saldo),0) as saldo 
             FROM cobros WHERE estudiante_id=$est_id AND estado != 'anulado'"));
        ?>

        <div class="est-header">
            <div>
                <h2>👤 <?php echo htmlspecialchars($info_est['nombre']); ?></h2>
                <p style="margin:0.2rem 0 0;opacity:0.8;font-size:0.88rem;">Doc: <?php echo htmlspecialchars($info_est['documento']); ?></p>
                <span class="badge-prog">🎓 <?php echo htmlspecialchars($info_est['programa'] ?? 'Sin programa'); ?></span>
            </div>
            <div class="est-saldo">
                <div class="est-saldo-item">
                    <span class="num">$<?php echo number_format($total_est['total'], 0, ',', '.'); ?></span>
                    <span class="lbl">Facturado</span>
                </div>
                <div class="est-saldo-item">
                    <span class="num" style="color:#10B981;">$<?php echo number_format($total_est['pagado'], 0, ',', '.'); ?></span>
                    <span class="lbl">Pagado</span>
                </div>
                <div class="est-saldo-item">
                    <span class="num" style="color:<?php echo $total_est['saldo'] > 0 ? '#EF4444' : '#10B981'; ?>;">$<?php echo number_format($total_est['saldo'], 0, ',', '.'); ?></span>
                    <span class="lbl">Saldo</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">📋 Detalle de Cobros</div>
            <div class="tabla-wrap">
                <table>
                    <thead><tr><th>Concepto</th><th>Período</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Vence</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php while ($c = mysqli_fetch_assoc($cobros_est)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['concepto']); ?></strong></td>
                            <td><?php echo $c['periodo']; ?></td>
                            <td>$<?php echo number_format($c['total'], 0, ',', '.'); ?></td>
                            <td style="color:#10B981;">$<?php echo number_format($c['pagado'], 0, ',', '.'); ?></td>
                            <td style="font-weight:700;color:<?php echo $c['saldo'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                                $<?php echo number_format($c['saldo'], 0, ',', '.'); ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($c['fecha_vencimiento'])); ?></td>
                            <td><span class="badge-estado badge-<?php echo $c['estado']; ?>"><?php echo ucfirst($c['estado']); ?></span></td>
                            <td>
                                <?php if ($c['saldo'] > 0): ?>
                                    <button onclick="abrirModalPago(<?php echo $c['id']; ?>, <?php echo $c['saldo']; ?>, '<?php echo addslashes($c['concepto']); ?>', '<?php echo $c['periodo']; ?>')" 
                                            class="btn-sm btn-pagar">💰 Pagar</button>
                                <?php else: ?>
                                    <span style="color:#10B981;font-size:0.8rem;">✅ Pagado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Historial de pagos del estudiante -->
        <div class="card">
            <div class="card-title">💳 Historial de Pagos</div>
            <?php
            $pagos_est = mysqli_query($conexion, 
                "SELECT p.*, cc.nombre as concepto, co.periodo
                 FROM pagos p
                 JOIN cobros co ON p.cobro_id = co.id
                 JOIN conceptos_cobro cc ON co.concepto_id = cc.id
                 WHERE p.estudiante_id = $est_id
                 ORDER BY p.fecha_pago DESC");
            ?>
            <div class="tabla-wrap">
                <table>
                    <thead><tr><th>Fecha</th><th>Concepto</th><th>Período</th><th>Monto</th><th>Método</th><th>Referencia</th></tr></thead>
                    <tbody>
                        <?php if (mysqli_num_rows($pagos_est) === 0): ?>
                            <tr><td colspan="6" style="text-align:center;color:#9CA3AF;padding:1.5rem;">Sin pagos registrados.</td></tr>
                        <?php else: while ($p = mysqli_fetch_assoc($pagos_est)): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?></td>
                            <td><?php echo htmlspecialchars($p['concepto']); ?></td>
                            <td><?php echo $p['periodo']; ?></td>
                            <td style="color:#10B981;font-weight:700;">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></td>
                            <td><?php echo ucfirst($p['metodo_pago']); ?></td>
                            <td><?php echo htmlspecialchars($p['referencia'] ?: '—'); ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL REGISTRAR PAGO -->
        <div class="modal-overlay" id="modal-pago">
            <div class="modal-box">
                <div class="modal-title">💰 Registrar Pago</div>
                <form method="POST" action="?vista=estado_cuenta&est_id=<?php echo $est_id; ?>">
                    <input type="hidden" name="registrar_pago" value="1">
                    <input type="hidden" name="pago_cobro_id" id="pago_cobro_id">

                    <p id="pago-info" style="font-size:0.88rem;color:#6B7280;margin-bottom:1rem;"></p>

                    <div class="form-grupo">
                        <label>Monto a pagar ($)</label>
                        <input type="number" name="pago_monto" id="pago_monto" step="100" min="1" required>
                    </div>
                    <div class="form-grid">
                        <div class="form-grupo">
                            <label>Fecha de pago</label>
                            <input type="date" name="pago_fecha" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-grupo">
                            <label>Método de pago</label>
                            <select name="pago_metodo" required>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="consignacion">Consignación</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grupo">
                        <label>Referencia / Comprobante</label>
                        <input type="text" name="pago_referencia" placeholder="Número de recibo o transacción">
                    </div>
                    <div class="form-grupo">
                        <label>Observaciones</label>
                        <textarea name="pago_observaciones" placeholder="Notas adicionales..."></textarea>
                    </div>
                    <div style="display:flex;gap:0.8rem;margin-top:1rem;">
                        <button type="submit" class="btn-primary">💾 Registrar Pago</button>
                        <button type="button" onclick="cerrarModal()" class="btn-sm btn-danger" style="padding:0.8rem 1.2rem;">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ============================================ -->
    <!-- VISTA: GENERAR COBROS -->
    <!-- ============================================ -->
    <?php elseif ($vista === 'cobros'): ?>

        <div class="card">
            <div class="card-title">📄 Generar Cobros Masivos</div>
            <p style="font-size:0.85rem;color:#9CA3AF;margin-bottom:1.5rem;">Genera cobros para todos los estudiantes activos de un programa. Si seleccionas "Todos", se genera para toda la institución.</p>

            <form method="POST">
                <input type="hidden" name="generar_cobros" value="1">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label>Concepto de Cobro</label>
                        <select name="cobro_concepto" required>
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($conceptos as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['nombre']); ?> ($<?php echo number_format($c['monto_base'], 0, ',', '.'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Programa</label>
                        <select name="cobro_programa">
                            <option value="0">Todos los programas</option>
                            <?php foreach ($programas as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Fecha de vencimiento (1ra cuota)</label>
                        <input type="date" name="cobro_vencimiento" required>
                    </div>
                    <div class="form-grupo">
                        <label>Monto personalizado (opcional)</label>
                        <input type="number" name="cobro_monto" step="100" min="0" placeholder="Dejar vacío para usar monto base">
                    </div>
                    <div class="form-grupo" style="display:flex;align-items:center;gap:0.6rem;padding-top:1.4rem;">
                        <input type="checkbox" name="cobro_todas_cuotas" id="cobro_todas_cuotas" value="1" checked style="width:18px;height:18px;accent-color:#059669;">
                        <label for="cobro_todas_cuotas" style="margin:0;cursor:pointer;">Generar todas las cuotas automáticamente</label>
                    </div>
                </div>
                <p id="info-cuotas" style="font-size:0.82rem;color:#059669;margin:0.5rem 0;font-weight:600;display:none;"></p>
                <button type="submit" class="btn-primary" style="margin-top:0.5rem;">📄 Generar Cobros</button>
            </form>
        </div>

    <!-- ============================================ -->
    <!-- VISTA: HISTORIAL DE PAGOS -->
    <!-- ============================================ -->
    <?php elseif ($vista === 'pagos'): ?>

        <div class="card">
            <div class="card-title">💳 Historial General de Pagos</div>
            <?php
            $todos_pagos = mysqli_query($conexion, 
                "SELECT p.*, e.nombre as est_nombre, e.documento, cc.nombre as concepto, co.periodo
                 FROM pagos p
                 JOIN estudiantes e ON p.estudiante_id = e.id
                 JOIN cobros co ON p.cobro_id = co.id
                 JOIN conceptos_cobro cc ON co.concepto_id = cc.id
                 ORDER BY p.created_at DESC LIMIT 50");
            ?>
            <div class="tabla-wrap">
                <table>
                    <thead><tr><th>Fecha</th><th>Estudiante</th><th>Doc.</th><th>Concepto</th><th>Período</th><th>Monto</th><th>Método</th><th>Ref.</th></tr></thead>
                    <tbody>
                        <?php if (mysqli_num_rows($todos_pagos) === 0): ?>
                            <tr><td colspan="8" style="text-align:center;color:#9CA3AF;padding:2rem;">No hay pagos registrados.</td></tr>
                        <?php else: while ($p = mysqli_fetch_assoc($todos_pagos)): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['est_nombre']); ?></strong></td>
                            <td style="font-size:0.8rem;"><?php echo $p['documento']; ?></td>
                            <td><?php echo htmlspecialchars($p['concepto']); ?></td>
                            <td><?php echo $p['periodo']; ?></td>
                            <td style="color:#10B981;font-weight:700;">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></td>
                            <td><?php echo ucfirst($p['metodo_pago']); ?></td>
                            <td style="font-size:0.8rem;"><?php echo htmlspecialchars($p['referencia'] ?: '—'); ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ============================================ -->
    <!-- VISTA: MOROSOS -->
    <!-- ============================================ -->
    <?php elseif ($vista === 'morosos'): ?>

        <div class="card">
            <div class="card-title">🚨 Estudiantes con Cobros Vencidos</div>
            <?php
            $morosos = mysqli_query($conexion,
                "SELECT e.id, e.nombre, e.documento, p.nombre as programa,
                        COUNT(c.id) as cobros_vencidos,
                        SUM(c.saldo) as deuda_vencida,
                        MIN(c.fecha_vencimiento) as desde
                 FROM cobros c
                 JOIN estudiantes e ON c.estudiante_id = e.id
                 LEFT JOIN programas p ON e.programa_id = p.id
                 WHERE c.estado = 'vencido'
                 GROUP BY e.id
                 ORDER BY deuda_vencida DESC");
            $morosos_ids = [];
            $morosos_rows = [];
            while ($m = mysqli_fetch_assoc($morosos)) {
                $morosos_ids[] = (int)$m['id'];
                $morosos_rows[] = $m;
            }
            ?>

            <?php if (!empty($morosos_ids)): ?>
            <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:0.9rem 1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.6rem;">
                <div style="font-size:0.92rem;color:#78350F;">
                    🔔 Notifica a los <strong><?= count($morosos_ids) ?></strong> estudiantes en mora: hará titilar la tarjeta <strong>Mi Cartera</strong> en su dashboard hasta que entren al módulo.
                </div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <button type="button" class="btn-sm btn-ver" style="background:#F59E0B;color:white;border:none;cursor:pointer;"
                            onclick='dispararAlertaCartera(<?= json_encode($morosos_ids) ?>, this)'>
                        🔔 Hacer titilar a todos
                    </button>
                    <button type="button" class="btn-sm" style="background:#E5E7EB;color:#374151;border:none;cursor:pointer;padding:0.45rem 0.9rem;border-radius:8px;font-weight:600;"
                            onclick='limpiarAlertaCartera(<?= json_encode($morosos_ids) ?>, this)'>
                        Apagar titileo
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="tabla-wrap">
                <table>
                    <thead><tr><th>Estudiante</th><th>Documento</th><th>Programa</th><th>Cobros Vencidos</th><th>Deuda Vencida</th><th>En mora desde</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php if (empty($morosos_rows)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#10B981;padding:2rem;">🎉 ¡No hay estudiantes en mora!</td></tr>
                        <?php else: foreach ($morosos_rows as $m): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($m['nombre']); ?></strong></td>
                            <td><?php echo $m['documento']; ?></td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($m['programa'] ?? '—'); ?></td>
                            <td><span class="badge-estado badge-vencido"><?php echo $m['cobros_vencidos']; ?> cobro(s)</span></td>
                            <td style="font-weight:800;color:#EF4444;font-size:1rem;">$<?php echo number_format($m['deuda_vencida'], 0, ',', '.'); ?></td>
                            <td style="color:#991B1B;font-size:0.85rem;"><?php echo date('d/m/Y', strtotime($m['desde'])); ?></td>
                            <td style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                                <a href="?vista=estado_cuenta&est_id=<?php echo $m['id']; ?>" class="btn-sm btn-ver">Ver cuenta</a>
                                <button type="button" class="btn-sm" title="Hacer titilar Cartera para este estudiante"
                                        style="background:#F59E0B;color:white;border:none;cursor:pointer;padding:0.35rem 0.7rem;border-radius:8px;font-weight:600;"
                                        onclick='dispararAlertaCartera([<?= (int)$m['id'] ?>], this)'>
                                    🔔
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        (function () {
            window.__csrfAlertas = '<?= htmlspecialchars($csrf_alertas, ENT_QUOTES, 'UTF-8') ?>';
        })();

        async function dispararAlertaCartera(ids, btn) {
            if (!Array.isArray(ids) || ids.length === 0) return;
            const original = btn.textContent;
            btn.disabled = true; btn.textContent = '...';
            try {
                const r = await fetch('api_alertas_admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        modulo: 'cartera',
                        accion: 'disparar',
                        estudiante_ids: ids,
                        csrf_token: window.__csrfAlertas
                    })
                });
                const d = await r.json();
                if (d.csrf_token) window.__csrfAlertas = d.csrf_token;
                if (d.ok) {
                    btn.textContent = '✓ ' + (d.creadas || 0);
                    btn.style.background = '#10B981';
                    if (ids.length > 1) {
                        alert('Listo: ' + d.creadas + ' alertas creadas (' + d.ya_activas + ' ya estaban activas).');
                    }
                } else {
                    alert('Error: ' + (d.error || 'no se pudo'));
                    btn.textContent = original;
                }
            } catch (e) {
                alert('Error de red. Recarga la página e intenta de nuevo.');
                btn.textContent = original;
            } finally {
                setTimeout(() => { btn.disabled = false; }, 1500);
            }
        }

        async function limpiarAlertaCartera(ids, btn) {
            if (!Array.isArray(ids) || ids.length === 0) return;
            const original = btn.textContent;
            btn.disabled = true; btn.textContent = '...';
            try {
                const r = await fetch('api_alertas_admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        modulo: 'cartera',
                        accion: 'limpiar',
                        estudiante_ids: ids,
                        csrf_token: window.__csrfAlertas
                    })
                });
                const d = await r.json();
                if (d.csrf_token) window.__csrfAlertas = d.csrf_token;
                if (d.ok) {
                    btn.textContent = '✓';
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

    <!-- ============================================ -->
    <!-- VISTA: CONCEPTOS DE COBRO -->
    <!-- ============================================ -->
    <?php elseif ($vista === 'conceptos'): ?>

        <div class="card">
            <div class="card-title">⚙️ Conceptos de Cobro</div>
            <p style="font-size:0.85rem;color:#9CA3AF;margin-bottom:1.2rem;">Define los conceptos que se cobran a los estudiantes (matrícula, mensualidades, etc.)</p>

            <button onclick="document.getElementById('form-concepto').style.display='block'" class="btn-primary" style="margin-bottom:1.5rem;">
                ➕ Nuevo Concepto
            </button>

            <!-- Form nuevo/editar concepto -->
            <div id="form-concepto" style="display:none;margin-bottom:1.5rem;padding:1.5rem;background:#F0FDF4;border-radius:12px;border:2px solid #ECFDF5;">
                <form method="POST">
                    <input type="hidden" name="guardar_concepto" value="1">
                    <input type="hidden" name="concepto_id" value="">
                    <div class="form-grid">
                        <div class="form-grupo">
                            <label>Nombre</label>
                            <input type="text" name="concepto_nombre" placeholder="Ej: Matrícula Semestral" required>
                        </div>
                        <div class="form-grupo">
                            <label>Tipo</label>
                            <select name="concepto_tipo">
                                <option value="mensualidad">Mensualidad</option>
                                <option value="seminario">Seminario</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="form-grupo">
                            <label>Monto por cuota ($)</label>
                            <input type="number" name="concepto_monto" step="100" min="0" required>
                        </div>
                        <div class="form-grupo">
                            <label>Número de cuotas</label>
                            <input type="number" name="concepto_cuotas" value="1" min="1" max="12">
                        </div>
                    </div>
                    <div class="form-grupo">
                        <label>Descripción</label>
                        <textarea name="concepto_descripcion" placeholder="Descripción del concepto..."></textarea>
                    </div>
                    <div style="display:flex;gap:0.8rem;">
                        <button type="submit" class="btn-primary">💾 Guardar</button>
                        <button type="button" onclick="document.getElementById('form-concepto').style.display='none'" 
                                class="btn-sm btn-danger" style="padding:0.7rem 1rem;">Cancelar</button>
                    </div>
                </form>
            </div>

            <!-- Lista de conceptos -->
            <div class="conceptos-grid">
                <?php
                $all_conceptos = mysqli_query($conexion, "SELECT * FROM conceptos_cobro ORDER BY tipo, nombre");
                while ($c = mysqli_fetch_assoc($all_conceptos)):
                    $tipo_class = 'tipo-' . $c['tipo'];
                ?>
                <div class="concepto-card">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:0.6rem;">
                        <strong style="color:#022C22;"><?php echo htmlspecialchars($c['nombre']); ?></strong>
                        <span class="tipo-tag <?php echo $tipo_class; ?>"><?php echo ucfirst($c['tipo']); ?></span>
                    </div>
                    <p style="font-size:0.82rem;color:#9CA3AF;margin-bottom:0.6rem;"><?php echo htmlspecialchars($c['descripcion'] ?: 'Sin descripción'); ?></p>
                    <div style="font-size:1.3rem;font-weight:800;color:#059669;">
                        $<?php echo number_format($c['monto_base'], 0, ',', '.'); ?>
                    </div>
                    <?php
                    $cuotas = isset($c['num_cuotas']) ? (int)$c['num_cuotas'] : 1;
                    if ($cuotas > 1):
                        $total_concepto = $c['monto_base'] * $cuotas;
                    ?>
                    <div style="font-size:0.78rem;color:#6B7280;margin-top:0.4rem;padding-top:0.4rem;border-top:1px solid #f0f0f0;">
                        <?php echo $cuotas; ?> cuotas x $<?php echo number_format($c['monto_base'], 0, ',', '.'); ?> = <strong style="color:#022C22;">$<?php echo number_format($total_concepto, 0, ',', '.'); ?></strong>
                    </div>
                    <?php else: ?>
                    <div style="font-size:0.78rem;color:#6B7280;margin-top:0.4rem;padding-top:0.4rem;border-top:1px solid #f0f0f0;">
                        Pago único
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

    <?php endif; ?>

</div>

<script>
// Modal pago
function abrirModalPago(cobroId, saldo, concepto, periodo) {
    document.getElementById('pago_cobro_id').value = cobroId;
    document.getElementById('pago_monto').max = saldo;
    document.getElementById('pago_monto').value = saldo;
    document.getElementById('pago-info').innerHTML = '<strong>' + concepto + '</strong> — Período: ' + periodo + '<br>Saldo pendiente: <strong style="color:#EF4444;">$' + saldo.toLocaleString('es-CO') + '</strong>';
    document.getElementById('modal-pago').classList.add('activo');
}
function cerrarModal() {
    document.getElementById('modal-pago').classList.remove('activo');
}
// Cerrar modal con click fuera
document.getElementById('modal-pago')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

// Filtrar estudiantes
function filtrarEstudiantes() {
    var buscar = document.getElementById('filtro-buscar').value.toLowerCase();
    var rows = document.querySelectorAll('#tabla-estudiantes tbody tr');
    rows.forEach(function(row) {
        var nombre = row.getAttribute('data-nombre') || '';
        var match = nombre.includes(buscar);
        row.style.display = match ? '' : 'none';
    });
}

// Auto-ocultar alerta
var al = document.querySelector('.alerta');
if (al) setTimeout(function() { al.style.transition='opacity 0.5s'; al.style.opacity='0'; setTimeout(function(){ al.remove(); }, 500); }, 4000);

// Info de cuotas al seleccionar concepto en Generar Cobros
var conceptosData = <?php echo json_encode(array_map(function($c) {
    return ['id' => $c['id'], 'nombre' => $c['nombre'], 'monto' => $c['monto_base'], 'cuotas' => isset($c['num_cuotas']) ? (int)$c['num_cuotas'] : 1];
}, $conceptos)); ?>;

var selConcepto = document.querySelector('select[name="cobro_concepto"]');
if (selConcepto) {
    selConcepto.addEventListener('change', function() {
        var info = document.getElementById('info-cuotas');
        var checkCuotas = document.getElementById('cobro_todas_cuotas');
        var sel = conceptosData.find(function(c) { return c.id == selConcepto.value; });
        if (sel && sel.cuotas > 1) {
            var total = sel.monto * sel.cuotas;
            info.textContent = sel.cuotas + ' cuotas de $' + sel.monto.toLocaleString('es-CO') + ' = Total $' + total.toLocaleString('es-CO') + ' por estudiante';
            info.style.display = 'block';
            if (checkCuotas) checkCuotas.parentElement.style.display = '';
        } else {
            info.style.display = 'none';
            if (checkCuotas) checkCuotas.parentElement.style.display = 'none';
        }
    });
}
</script>
<script src="/intep/sesion.js"></script>
</body>
</html>