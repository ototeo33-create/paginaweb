<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: dashboard.php');
    exit;
}

$estudiante_id = $_SESSION['estudiante_id'];
$mensaje = '';
$tipo_msg = '';
$vista = $_GET['vista'] ?? 'estado';

// Info del estudiante
$info_est = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT e.*, p.nombre as programa FROM estudiantes e 
     LEFT JOIN programas p ON e.programa_id = p.id 
     WHERE e.id = $estudiante_id"));

// ===== DATOS DE CARTERA =====
// Resumen financiero
$resumen = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COALESCE(SUM(total),0) as total_cobros,
            COALESCE(SUM(pagado),0) as total_pagado,
            COALESCE(SUM(saldo),0) as saldo_total,
            SUM(CASE WHEN estado='vencido' THEN 1 ELSE 0 END) as vencidos,
            SUM(CASE WHEN estado='pagado' THEN 1 ELSE 0 END) as pagados,
            SUM(CASE WHEN estado IN ('pendiente','parcial') THEN 1 ELSE 0 END) as pendientes
     FROM cobros WHERE estudiante_id = $estudiante_id AND estado != 'anulado'"));

// Cobros detallados
$cobros = mysqli_query($conexion,
    "SELECT c.*, cc.nombre as concepto, cc.tipo as concepto_tipo
     FROM cobros c
     JOIN conceptos_cobro cc ON c.concepto_id = cc.id
     WHERE c.estudiante_id = $estudiante_id AND c.estado != 'anulado'
     ORDER BY c.fecha_vencimiento DESC");

// Pagos realizados
$pagos = mysqli_query($conexion,
    "SELECT p.*, cc.nombre as concepto, co.periodo
     FROM pagos p
     JOIN cobros co ON p.cobro_id = co.id
     JOIN conceptos_cobro cc ON co.concepto_id = cc.id
     WHERE p.estudiante_id = $estudiante_id
     ORDER BY p.fecha_pago DESC");

$paz_salvo = ($resumen['saldo_total'] <= 0 && $resumen['total_cobros'] > 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cartera – INTEP</title>
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

        .page-wrap { max-width: 1000px; margin: 0 auto; padding: 1.5rem; }

        /* Header estudiante */
        .est-banner {
            background: linear-gradient(135deg, #064E3B, #059669, #10B981);
            color: white; border-radius: 16px; padding: 1.8rem;
            margin-bottom: 1.5rem; position: relative; overflow: hidden;
        }
        .est-banner::before {
            content: ''; position: absolute; top: -50px; right: -50px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .est-banner-top {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem; margin-bottom: 1.2rem;
        }
        .est-banner h2 { margin: 0; font-size: 1.25rem; position: relative; }
        .est-banner .prog-badge {
            background: rgba(255,255,255,0.15); padding: 0.3rem 0.8rem;
            border-radius: 20px; font-size: 0.82rem; position: relative;
        }
        .est-saldos {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.8rem; position: relative;
        }
        .saldo-item {
            background: rgba(255,255,255,0.1); border-radius: 12px;
            padding: 0.8rem; text-align: center;
            backdrop-filter: blur(10px);
        }
        .saldo-item .num { font-size: 1.3rem; font-weight: 800; display: block; }
        .saldo-item .lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7; }

        /* Paz y salvo badge */
        .paz-salvo-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.3);
            color: #A7F3D0; padding: 0.4rem 1rem; border-radius: 20px;
            font-size: 0.82rem; font-weight: 700; position: relative;
        }
        .mora-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.3);
            color: #FCA5A5; padding: 0.4rem 1rem; border-radius: 20px;
            font-size: 0.82rem; font-weight: 700; position: relative;
        }

        /* Tabs */
        .tabs-nav {
            display: flex; gap: 0.3rem; margin-bottom: 1.5rem;
            background: white; border-radius: 12px; padding: 0.4rem;
            box-shadow: 0 2px 6px rgba(5,150,105,0.06);
            overflow-x: auto;
        }
        .tab-link {
            padding: 0.65rem 1rem; border-radius: 10px;
            font-size: 0.84rem; font-weight: 600;
            text-decoration: none; color: #6B7280;
            white-space: nowrap; transition: all 0.2s;
        }
        .tab-link:hover { background: #ECFDF5; color: #059669; }
        .tab-link.activo { background: #059669; color: white; }

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.12);
        }
        
        .card-title {
            font-size: 1.05rem; font-weight: 700; color: #022C22;
            margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
        }

        /* Tabla */
        .tabla-wrap { overflow-x: auto; }
        .tabla-wrap table { width: 100%; border-collapse: collapse; min-width: 550px; }
        .tabla-wrap thead { background: #022C22; color: white; }
        .tabla-wrap th { padding: 0.75rem 1rem; text-align: left; font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .tabla-wrap td { padding: 0.7rem 1rem; font-size: 0.88rem; border-bottom: 1px solid #D1FAE5; }
        .tabla-wrap tr:hover { background: #F0FDF4; }
        .tabla-wrap tr:last-child td { border-bottom: none; }

        /* Badges */
        .badge { display: inline-block; padding: 0.2rem 0.65rem; border-radius: 20px; font-size: 0.73rem; font-weight: 600; }
        .badge-pagado { background: #ECFDF5; color: #065F46; }
        .badge-pendiente { background: #FFFBEB; color: #92400E; }
        .badge-vencido { background: #FEF2F2; color: #991B1B; }
        .badge-parcial { background: #ECFDF5; color: #059669; }
        .badge-anulado { background: #F3F4F6; color: #6B7280; }

        .badge-sol-pendiente { background: #FFFBEB; color: #92400E; }
        .badge-sol-en_proceso { background: #DBEAFE; color: #1D4ED8; }
        .badge-sol-completada { background: #ECFDF5; color: #065F46; }
        .badge-sol-rechazada { background: #FEF2F2; color: #991B1B; }

        /* Info cards grid */
        .info-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }
        .info-card {
            background: white; border-radius: 12px; padding: 1.2rem;
            box-shadow: 0 2px 6px rgba(5,150,105,0.06);
            text-align: center; border-top: 3px solid #D1FAE5;
        }
        .info-card .num { font-size: 1.8rem; font-weight: 800; display: block; margin-bottom: 0.2rem; }
        .info-card .lbl { font-size: 0.78rem; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-card.total .num { color: #059669; }
        .info-card.total { border-color: #059669; }
        .info-card.pagado .num { color: #10B981; }
        .info-card.pagado { border-color: #10B981; }
        .info-card.saldo .num { color: #EF4444; }
        .info-card.saldo { border-color: #EF4444; }

        /* Barra progreso pago */
        .barra-pago { background: #ECFDF5; border-radius: 8px; height: 8px; overflow: hidden; margin-top: 0.5rem; }
        .barra-pago-fill { height: 100%; border-radius: 8px; background: linear-gradient(90deg, #10B981, #34D399); }

        /* Form */
        .form-grupo { margin-bottom: 1rem; }
        .form-grupo label { display: block; font-size: 0.78rem; font-weight: 700; color: #6B7280; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-grupo select, .form-grupo textarea {
            width: 100%; padding: 0.75rem 0.9rem; border: 2px solid #D1FAE5;
            border-radius: 10px; font-size: 0.9rem; outline: none; transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-grupo select:focus, .form-grupo textarea:focus { border-color: #10B981; }
        .form-grupo textarea { resize: vertical; min-height: 80px; }

        .btn-primary {
            padding: 0.8rem 1.5rem; background: linear-gradient(135deg, #059669, #10B981);
            color: white; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.3); }

        /* Alerta */
        .alerta { padding: 0.9rem 1.2rem; border-radius: 10px; margin-bottom: 1.2rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
        .alerta.exito { background: #ECFDF5; color: #065F46; border-left: 4px solid #10B981; }
        .alerta.error { background: #FEF2F2; color: #991B1B; border-left: 4px solid #EF4444; }

        /* Solicitudes opciones */
        .sol-opciones {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }
        .sol-opcion {
            background: #F0FDF4; border: 2px solid #ECFDF5;
            border-radius: 12px; padding: 1.2rem; text-align: center;
            cursor: pointer; transition: all 0.2s;
        }
        .sol-opcion:hover { border-color: #10B981; background: #ECFDF5; }
        .sol-opcion.seleccionada { border-color: #10B981; background: #ECFDF5; box-shadow: 0 0 0 3px rgba(16,185,129,0.15); }
        .sol-opcion .sol-icono { font-size: 2rem; margin-bottom: 0.5rem; }
        .sol-opcion .sol-nombre { font-weight: 700; color: #022C22; font-size: 0.88rem; }
        .sol-opcion .sol-desc { font-size: 0.78rem; color: #9CA3AF; margin-top: 0.3rem; }
        .sol-opcion .sol-precio { font-weight: 800; color: #059669; margin-top: 0.5rem; font-size: 0.9rem; }

        /* Sin datos */
        .sin-datos { text-align: center; padding: 2.5rem 1rem; color: #9CA3AF; }
        .sin-datos .icono { font-size: 2.5rem; margin-bottom: 0.8rem; }

        /* Timeline de pagos */
        .pago-timeline { padding: 0; }
        .pago-item {
            display: flex; gap: 1rem; padding: 1rem 0;
            border-bottom: 1px solid #D1FAE5;
        }
        .pago-item:last-child { border-bottom: none; }
        .pago-fecha {
            min-width: 70px; text-align: center;
            background: #ECFDF5; border-radius: 10px; padding: 0.5rem;
            font-size: 0.8rem; color: #059669; font-weight: 600;
        }
        .pago-fecha .dia { font-size: 1.3rem; font-weight: 800; display: block; }
        .pago-detalle { flex: 1; }
        .pago-detalle .concepto { font-weight: 700; color: #022C22; }
        .pago-detalle .meta { font-size: 0.82rem; color: #9CA3AF; margin-top: 0.2rem; }
        .pago-monto { font-size: 1.1rem; font-weight: 800; color: #10B981; min-width: 100px; text-align: right; }

        /* Responsive */
        @media (max-width: 768px) {
            .est-banner-top { flex-direction: column; text-align: center; }
            .est-saldos { grid-template-columns: repeat(2, 1fr); }
            .sol-opciones { grid-template-columns: 1fr; }
            .pago-item { flex-direction: column; gap: 0.5rem; }
            .pago-monto { text-align: left; }
        }
    </style>
</head>
<body>

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">💰 Mi Cartera</span>
    <a href="logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="page-wrap">

    <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

    <!-- Banner del estudiante -->
    <div class="est-banner">
        <div class="est-banner-top">
            <div>
                <h2>👤 <?php echo htmlspecialchars($info_est['nombre']); ?></h2>
                <span class="prog-badge">🎓 <?php echo htmlspecialchars($info_est['programa'] ?? 'Sin programa'); ?></span>
            </div>
            <?php if ($paz_salvo): ?>
                <span class="paz-salvo-badge">✅ Paz y Salvo</span>
            <?php elseif ($resumen['vencidos'] > 0): ?>
                <span class="mora-badge">🚨 En Mora (<?php echo $resumen['vencidos']; ?> cobro<?php echo $resumen['vencidos'] > 1 ? 's' : ''; ?> vencido<?php echo $resumen['vencidos'] > 1 ? 's' : ''; ?>)</span>
            <?php endif; ?>
        </div>
        <div class="est-saldos">
            <div class="saldo-item">
                <span class="num">$<?php echo number_format($resumen['total_cobros'], 0, ',', '.'); ?></span>
                <span class="lbl">Total cobros</span>
            </div>
            <div class="saldo-item">
                <span class="num" style="color:#A7F3D0;">$<?php echo number_format($resumen['total_pagado'], 0, ',', '.'); ?></span>
                <span class="lbl">Total pagado</span>
            </div>
            <div class="saldo-item">
                <span class="num" style="color:<?php echo $resumen['saldo_total'] > 0 ? '#FCA5A5' : '#A7F3D0'; ?>;">
                    $<?php echo number_format($resumen['saldo_total'], 0, ',', '.'); ?>
                </span>
                <span class="lbl">Saldo pendiente</span>
            </div>
            <div class="saldo-item">
                <span class="num"><?php echo ($resumen['pagados'] ?? 0); ?>/<?php echo ($resumen['pagados'] ?? 0) + ($resumen['pendientes'] ?? 0) + ($resumen['vencidos'] ?? 0); ?></span>
                <span class="lbl">Cobros pagados</span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <nav class="tabs-nav">
        <a href="?vista=estado" class="tab-link <?php echo $vista==='estado'?'activo':''; ?>">📋 Estado de Cuenta</a>
        <a href="?vista=pagos" class="tab-link <?php echo $vista==='pagos'?'activo':''; ?>">💳 Mis Pagos</a>
    </nav>

    <?php if ($mensaje): ?>
        <div class="alerta <?php echo $tipo_msg; ?>"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- VISTA: ESTADO DE CUENTA -->
    <!-- ============================================ -->
    <?php if ($vista === 'estado'): ?>

        <?php
        $pct = $resumen['total_cobros'] > 0 
            ? round(($resumen['total_pagado'] / $resumen['total_cobros']) * 100) 
            : 0;
        ?>

        <!-- Progreso de pago -->
        <div class="card">
            <div class="card-title">📈 Progreso de Pago</div>
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#6B7280;margin-bottom:0.3rem;">
                <span>Pagado: $<?php echo number_format($resumen['total_pagado'], 0, ',', '.'); ?></span>
                <span><strong style="color:#10B981;"><?php echo $pct; ?>%</strong></span>
            </div>
            <div class="barra-pago">
                <div class="barra-pago-fill" style="width:<?php echo $pct; ?>%"></div>
            </div>
        </div>

        <!-- Detalle de cobros -->
        <div class="card">
            <div class="card-title">📋 Detalle de Cobros</div>

            <?php if (mysqli_num_rows($cobros) === 0): ?>
                <div class="sin-datos">
                    <div class="icono">📄</div>
                    <p>No tienes cobros registrados aún.</p>
                </div>
            <?php else: ?>
                <div class="tabla-wrap">
                    <table>
                        <thead>
                            <tr><th>Concepto</th><th>Período</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Vence</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($c = mysqli_fetch_assoc($cobros)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($c['concepto']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['periodo']); ?></td>
                                <td>$<?php echo number_format($c['total'], 0, ',', '.'); ?></td>
                                <td style="color:#10B981;font-weight:600;">$<?php echo number_format($c['pagado'], 0, ',', '.'); ?></td>
                                <td style="font-weight:700;color:<?php echo $c['saldo'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                                    $<?php echo number_format($c['saldo'], 0, ',', '.'); ?>
                                </td>
                                <td style="font-size:0.82rem;"><?php echo date('d/m/Y', strtotime($c['fecha_vencimiento'])); ?></td>
                                <td><span class="badge badge-<?php echo $c['estado']; ?>"><?php 
                                    $estados_label = ['pagado'=>'✅ Pagado','pendiente'=>'⏳ Pendiente','vencido'=>'🚨 Vencido','parcial'=>'🔄 Parcial','anulado'=>'— Anulado'];
                                    echo $estados_label[$c['estado']] ?? ucfirst($c['estado']); 
                                ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Aviso importante -->
        <?php if ($resumen['vencidos'] > 0): ?>
        <div style="background:#FEF2F2;border:2px solid #FECACA;border-radius:12px;padding:1.2rem;margin-bottom:1.5rem;">
            <p style="margin:0;color:#991B1B;font-size:0.9rem;">
                <strong>⚠️ Tienes <?php echo $resumen['vencidos']; ?> cobro(s) vencido(s).</strong><br>
                <span style="font-size:0.82rem;">Acércate a secretaría para regularizar tu situación financiera y evitar inconvenientes con tu matrícula.</span>
            </p>
        </div>
        <?php endif; ?>

        <!-- Info de pago -->
        <div class="card" style="background:#F0FDF4;border:2px solid #ECFDF5;">
            <div class="card-title" style="color:#059669;">ℹ️ ¿Cómo pagar?</div>
            <div style="font-size:0.88rem;color:#6B7280;line-height:1.8;">
                <p style="margin:0 0 0.5rem;">Puedes realizar tus pagos por los siguientes medios:</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                    <div style="background:white;padding:1rem;border-radius:10px;">
                        <strong style="color:#022C22;">🏦 Consignación Bancaria</strong><br>
                        <span style="font-size:0.82rem;">Banco: CREDI FLORES<br>Cuenta: 0123-4567-8901<br>Tipo: Ahorros</span>
                    </div>
                    <div style="background:white;padding:1rem;border-radius:10px;">
                        <strong style="color:#022C22;">🏢 Presencial</strong><br>
                        <span style="font-size:0.82rem;">Secretaría INTEP<br>Lunes a Viernes<br>4:00 PM - 9:30 PM</span>
                    </div>
                    <div style="background:white;padding:1rem;border-radius:10px;">
                        <strong style="color:#022C22;">📱 Transferencia</strong><br>
                        <span style="font-size:0.82rem;">Nequi <br>Cel: 316-630-7633<br>A nombre de John Eduardo</span> <br>    
                        <span style="font-size:0.82rem;">Davivienda <br>Cel: 316-630-7633<br>A nombre de John Eduardo</span>
                    </div>
                </div>
                <p style="margin:1rem 0 0;font-size:0.8rem;color:#9CA3AF;">
                    💡 Después de pagar, presenta tu comprobante en secretaría para que sea registrado en el sistema gracias.
                </p>
            </div>
        </div>

    <!-- ============================================ -->
    <!-- VISTA: MIS PAGOS -->
    <!-- ============================================ -->
    <?php elseif ($vista === 'pagos'): ?>

        <div class="card">
            <div class="card-title">💳 Historial de Pagos Realizados</div>

            <?php if (mysqli_num_rows($pagos) === 0): ?>
                <div class="sin-datos">
                    <div class="icono">💳</div>
                    <p>Aún no tienes pagos registrados.</p>
                    <p style="font-size:0.82rem;">Cuando realices un pago y sea registrado por secretaría, aparecerá aquí.</p>
                </div>
            <?php else: ?>
                <div class="pago-timeline">
                    <?php while ($p = mysqli_fetch_assoc($pagos)): 
                        $fecha = strtotime($p['fecha_pago']);
                        $meses_es = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                    ?>
                    <div class="pago-item">
                        <div class="pago-fecha">
                            <span class="dia"><?php echo date('d', $fecha); ?></span>
                            <?php echo $meses_es[(int)date('n', $fecha)] . ' ' . date('Y', $fecha); ?>
                        </div>
                        <div class="pago-detalle">
                            <div class="concepto"><?php echo htmlspecialchars($p['concepto']); ?></div>
                            <div class="meta">
                                Período: <?php echo htmlspecialchars($p['periodo']); ?> · 
                                Método: <?php echo ucfirst($p['metodo_pago']); ?>
                                <?php if ($p['referencia']): ?>
                                    · Ref: <?php echo htmlspecialchars($p['referencia']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pago-monto">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<script>
// Selección de tipo de solicitud
// Auto-ocultar alerta
var al = document.querySelector('.alerta');
if (al) setTimeout(function() { al.style.transition='opacity 0.5s'; al.style.opacity='0'; setTimeout(function(){ al.remove(); }, 500); }, 5000);
</script>
<script src="/intep/sesion.js"></script>
<?php include __DIR__ . '/partials/student_bottom_nav.php'; ?>
</body>
</html>