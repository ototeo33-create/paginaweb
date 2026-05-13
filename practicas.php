<?php
require_once 'config.php';
require_once __DIR__ . '/includes/modulos_visibilidad.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: /intep/login.php');
    exit;
}

requerir_modulo($conexion, 'practicas');

$usuario_id  = (int)$_SESSION['usuario_id'];
$estudiante_id = (int)($_SESSION['estudiante_id'] ?? 0);
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Estudiante';

// ── Verificar que el estudiante existe ──────────────────────
if (!$estudiante_id) {
    $chk = mysqli_prepare($conexion, "SELECT id FROM estudiantes WHERE id = (SELECT estudiante_id FROM usuarios WHERE id = ?)");
    mysqli_stmt_bind_param($chk, 'i', $usuario_id);
    mysqli_stmt_execute($chk);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    $estudiante_id = (int)($r['id'] ?? 0);
}

// ── Acción de la página ─────────────────────────────────────
$action = $_GET['action'] ?? 'inicio';

// ── Mensajes flash ──────────────────────────────────────────
$msg_ok  = $_SESSION['prac_ok']  ?? null;
$msg_err = $_SESSION['prac_err'] ?? null;
unset($_SESSION['prac_ok'], $_SESSION['prac_err']);

// ── Verificar que las tablas existen ───────────────────────
$tablas_ok = false;
$r_tbl = mysqli_query($conexion, "SHOW TABLES LIKE 'prac_practicas'");
if ($r_tbl && mysqli_num_rows($r_tbl) > 0) {
    $tablas_ok = true;
}

if (!$tablas_ok) {
    // Mostrar pantalla de espera amigable
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Prácticas - INTEP</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{margin:0;background:#0f172a;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;color:#fff;}
  .box{text-align:center;padding:2rem;background:#1e293b;border-radius:1rem;max-width:420px;}
  .icon{font-size:3rem;margin-bottom:1rem;}
  h2{color:#f59e0b;margin:0 0 .5rem;}
  p{color:#94a3b8;font-size:.95rem;}
  a{display:inline-block;margin-top:1.5rem;padding:.7rem 1.5rem;background:#334155;color:#fff;text-decoration:none;border-radius:.5rem;}
</style>
</head>
<body>
<div class="box">
  <div class="icon">🔧</div>
  <h2>Módulo en preparación</h2>
  <p>El módulo de prácticas está siendo configurado por el administrador. Vuelve en unos minutos.</p>
  <a href="/intep/dashboard.php">← Volver al portal</a>
</div>
</body>
</html>
<?php
    exit;
}

// ── Obtener práctica activa del estudiante ──────────────────
$practica = null;
if ($estudiante_id) {
    $qp = mysqli_prepare($conexion, "
        SELECT pp.*,
               pe.razon_social, pe.nit as empresa_nit, pe.direccion as empresa_dir,
               pt.nombres as tutor_nombres, pt.apellidos as tutor_apellidos, pt.cargo as tutor_cargo, pt.email as tutor_email,
               pm.nombres as monitor_nombres, pm.apellidos as monitor_apellidos, pm.email as monitor_email
        FROM prac_practicas pp
        LEFT JOIN prac_empresas pe ON pp.empresa_id = pe.id
        LEFT JOIN prac_tutores pt ON pp.tutor_id = pt.id
        LEFT JOIN prac_monitores pm ON pp.monitor_id = pm.id
        WHERE pp.estudiante_id = ? AND pp.estado IN ('activa','pendiente')
        ORDER BY pp.created_at DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($qp, 'i', $estudiante_id);
    mysqli_stmt_execute($qp);
    $practica = mysqli_fetch_assoc(mysqli_stmt_get_result($qp));
}

// ── Manejar POST: Solicitar tipo de práctica ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'solicitar') {
    $tipo = $_POST['tipo_practica'] ?? '';
    $tipos_validos = ['constancia_laboral','pasantias','unidad_productiva','simulacion','contrato_aprendizaje'];

    if (!in_array($tipo, $tipos_validos, true)) {
        $_SESSION['prac_err'] = 'Selecciona un tipo de práctica válido.';
        header('Location: /intep/practicas.php');
        exit;
    }
    if (!$estudiante_id) {
        $_SESSION['prac_err'] = 'Tu perfil de estudiante no está configurado. Contacta al administrador.';
        header('Location: /intep/practicas.php');
        exit;
    }

    $obs_extra = '';
    if ($tipo === 'constancia_laboral') {
        $empresa_nombre = trim($_POST['empresa_nombre'] ?? '');
        $cargo_actual   = trim($_POST['cargo_actual'] ?? '');
        $obs_extra = "Empresa: $empresa_nombre | Cargo: $cargo_actual";
    } elseif ($tipo === 'unidad_productiva') {
        $desc = trim($_POST['descripcion_negocio'] ?? '');
        $obs_extra = "Descripción negocio: $desc";
    }

    $ins = mysqli_prepare($conexion, "
        INSERT INTO prac_practicas
            (estudiante_id, institucion_id, tipo_practica, estado, observaciones,
             empresa_nombre, cargo_actual, descripcion_negocio)
        VALUES (?, 1, ?, 'pendiente', ?, ?, ?, ?)
    ");
    $emp_nom = trim($_POST['empresa_nombre'] ?? '');
    $cargo   = trim($_POST['cargo_actual'] ?? '');
    $desc_neg = trim($_POST['descripcion_negocio'] ?? '');
    mysqli_stmt_bind_param($ins, 'isssss', $estudiante_id, $tipo, $obs_extra, $emp_nom, $cargo, $desc_neg);

    if (mysqli_stmt_execute($ins)) {
        $_SESSION['prac_ok'] = 'Tu solicitud de práctica fue registrada. El coordinador la revisará y te notificará.';
    } else {
        $_SESSION['prac_err'] = 'Error al registrar la solicitud: ' . mysqli_error($conexion);
    }
    header('Location: /intep/practicas.php');
    exit;
}

// ── Manejar POST: Registrar seguimiento mensual ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'seguimiento' && $practica) {
    $mes    = (int)($_POST['mes'] ?? date('n'));
    $anio   = (int)($_POST['anio'] ?? date('Y'));
    $horas  = (int)($_POST['horas_cumplidas'] ?? 0);
    $avance = trim($_POST['avance_descripcion'] ?? '');
    $dific  = trim($_POST['dificultades'] ?? '');
    $apren  = trim($_POST['aprendizajes'] ?? '');
    $req_at = isset($_POST['requiere_atencion']) ? 1 : 0;
    $archivo_ruta = null;

    if (empty($avance)) {
        $_SESSION['prac_err'] = 'Debes describir tu avance del período.';
        header('Location: /intep/practicas.php?action=seguimiento');
        exit;
    }

    // Subir archivo evidencia si existe
    if (!empty($_FILES['evidencia']['name']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/uploads/practicas/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION));
        $exts_ok = ['pdf','jpg','jpeg','png','doc','docx'];
        if (in_array($ext, $exts_ok, true) && $_FILES['evidencia']['size'] < 5 * 1024 * 1024) {
            $fname = time() . '_' . $usuario_id . '.' . $ext;
            if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $dir . $fname)) {
                $archivo_ruta = 'uploads/practicas/' . $fname;
            }
        }
    }

    $ins2 = mysqli_prepare($conexion, "
        INSERT INTO prac_seguimientos
            (practica_id, usuario_id, mes, anio, horas_cumplidas, avance_descripcion,
             dificultades, aprendizajes, requiere_atencion, archivo_evidencia)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            horas_cumplidas = VALUES(horas_cumplidas),
            avance_descripcion = VALUES(avance_descripcion),
            dificultades = VALUES(dificultades),
            aprendizajes = VALUES(aprendizajes),
            requiere_atencion = VALUES(requiere_atencion),
            archivo_evidencia = COALESCE(VALUES(archivo_evidencia), archivo_evidencia),
            updated_at = NOW()
    ");
    mysqli_stmt_bind_param($ins2, 'iiiiisssis',
        $practica['id'], $usuario_id, $mes, $anio, $horas,
        $avance, $dific, $apren, $req_at, $archivo_ruta
    );
    if (mysqli_stmt_execute($ins2)) {
        $_SESSION['prac_ok'] = 'Avance mensual registrado correctamente.';
    } else {
        $_SESSION['prac_err'] = 'Error al registrar el avance: ' . mysqli_error($conexion);
    }
    header('Location: /intep/practicas.php');
    exit;
}

// ── Últimos seguimientos ────────────────────────────────────
$seguimientos = [];
if ($practica) {
    $qs = mysqli_prepare($conexion, "
        SELECT * FROM prac_seguimientos
        WHERE practica_id = ?
        ORDER BY anio DESC, mes DESC
        LIMIT 6
    ");
    mysqli_stmt_bind_param($qs, 'i', $practica['id']);
    mysqli_stmt_execute($qs);
    $seguimientos = mysqli_fetch_all(mysqli_stmt_get_result($qs), MYSQLI_ASSOC);
}

// ── Nombres legibles de tipos de práctica ──────────────────
$tipos_nombres = [
    'constancia_laboral'  => 'Constancia Laboral',
    'pasantias'           => 'Pasantías',
    'unidad_productiva'   => 'Unidad Productiva',
    'simulacion'          => 'Simulación de Prácticas',
    'contrato_aprendizaje'=> 'Contrato de Aprendizaje',
];
$estados_nombres = [
    'pendiente'   => 'Pendiente de aprobación',
    'activa'      => 'Activa',
    'suspendida'  => 'Suspendida',
    'finalizada'  => 'Finalizada',
    'cancelada'   => 'Cancelada',
];
$meses_nombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Prácticas – INTEP</title>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        html, body { background: #f8f9fc; min-height: 100%; }

        .prac-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        .prac-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #10B981;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 1.5rem;
        }
        .prac-back:hover { color: #059669; }

        .prac-hero {
            background: linear-gradient(135deg, #059669 0%, #10B981 60%, #34D399 100%);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .prac-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .prac-hero h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 0.3rem; }
        .prac-hero p  { font-size: 0.88rem; opacity: 0.85; margin: 0; }

        /* Alertas */
        .prac-alert-ok, .prac-alert-err {
            padding: 0.85rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .prac-alert-ok  { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .prac-alert-err { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        /* Estado de práctica */
        .prac-estado {
            background: white;
            border-radius: 16px;
            border: 1px solid #E5E7EB;
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .prac-estado-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }
        .prac-tipo-badge {
            font-size: 0.75rem;
            font-weight: 800;
            padding: 4px 14px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-pendiente   { background: #FEF3C7; color: #92400E; }
        .badge-activa      { background: #D1FAE5; color: #065F46; }
        .badge-suspendida  { background: #FEE2E2; color: #991B1B; }
        .badge-finalizada  { background: #E0E7FF; color: #3730A3; }

        .prac-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .prac-info-item label {
            font-size: 0.73rem;
            color: #6B7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 3px;
        }
        .prac-info-item span {
            font-size: 0.9rem;
            color: #111827;
            font-weight: 600;
        }

        /* Acciones rápidas */
        .prac-acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .btn-prac-primary {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white;
            padding: 10px 22px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
            transition: all 0.2s;
        }
        .btn-prac-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(5,150,105,0.4); }

        .btn-prac-outline {
            background: white;
            color: #374151;
            padding: 10px 22px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #D1D5DB;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s;
        }
        .btn-prac-outline:hover { border-color: #10B981; color: #059669; }

        /* Tabla seguimientos */
        .prac-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #E5E7EB;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .prac-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .prac-card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .prac-table {
            width: 100%;
            border-collapse: collapse;
        }
        .prac-table th {
            background: #F9FAFB;
            padding: 10px 16px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        .prac-table td {
            padding: 12px 16px;
            font-size: 0.85rem;
            color: #374151;
            border-top: 1px solid #F3F4F6;
        }
        .prac-table tr:hover td { background: #F9FAFB; }

        /* Guía de tipos */
        .guia-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .guia-card {
            background: white;
            border: 2px solid #E5E7EB;
            border-radius: 16px;
            padding: 1.4rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .guia-card:hover, .guia-card.selected {
            border-color: #10B981;
            box-shadow: 0 4px 16px rgba(16,185,129,0.15);
        }
        .guia-card.selected { background: #ECFDF5; }
        .guia-card input[type=radio] { position: absolute; opacity: 0; }
        .guia-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 0.8rem;
        }
        .guia-card h4 {
            font-size: 0.9rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 0.4rem;
        }
        .guia-card p {
            font-size: 0.78rem;
            color: #6B7280;
            margin: 0 0 0.6rem;
            line-height: 1.5;
        }
        .guia-decreto {
            font-size: 0.68rem;
            color: #9CA3AF;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .guia-check {
            position: absolute;
            top: 14px; right: 14px;
            width: 20px; height: 20px;
            border-radius: 50%;
            border: 2px solid #D1D5DB;
            display: flex; align-items: center; justify-content: center;
            font-size: 0;
            transition: all 0.2s;
        }
        .guia-card.selected .guia-check {
            background: #10B981;
            border-color: #10B981;
            font-size: 12px;
            color: white;
        }

        /* Formulario seguimiento */
        .form-prac label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #374151;
            display: block;
            margin-bottom: 5px;
        }
        .form-prac input, .form-prac textarea, .form-prac select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 0.88rem;
            color: #111827;
            background: white;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-prac input:focus, .form-prac textarea:focus, .form-prac select:focus {
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media(max-width:600px) {
            .form-grid-2 { grid-template-columns: 1fr; }
            .prac-hero { padding: 1.5rem; }
            .prac-hero h1 { font-size: 1.3rem; }
        }

        /* Sección extra por tipo */
        .tipo-extra { display: none; margin-top: 1rem; }
        .tipo-extra.visible { display: block; }

        /* Info pendiente */
        .prac-pendiente-info {
            background: #FEF3C7;
            border: 1px solid #FDE68A;
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .prac-pendiente-info .icon { font-size: 1.3rem; flex-shrink: 0; margin-top: 2px; }
        .prac-pendiente-info p { margin: 0; font-size: 0.85rem; color: #92400E; line-height: 1.5; }
    </style>
</head>
<body>
<div class="prac-container">

    <a href="/intep/dashboard.php" class="prac-back">
        ← Volver al inicio
    </a>

    <div class="prac-hero">
        <div style="position:relative;z-index:1">
            <h1>📋 Mis Prácticas</h1>
            <p>Gestiona tu práctica profesional según el Decreto 0223 de 2026 · INTEP</p>
        </div>
    </div>

    <?php if ($msg_ok): ?>
    <div class="prac-alert-ok">✅ <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="prac-alert-err">⚠️ <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

<?php if (!$estudiante_id): ?>
    <!-- Sin perfil de estudiante -->
    <div class="prac-alert-err">
        ⚠️ Tu cuenta no tiene un perfil de estudiante asociado. Contacta al coordinador de prácticas.
    </div>

<?php elseif ($practica && $action !== 'seguimiento'): ?>
    <!-- ══════════════════════════════════════════════════
         VISTA: PRÁCTICA REGISTRADA
    ══════════════════════════════════════════════════ -->

    <?php if ($practica['estado'] === 'pendiente'): ?>
    <div class="prac-pendiente-info">
        <div class="icon">⏳</div>
        <p><strong>Tu solicitud está en revisión.</strong><br>
        El coordinador de prácticas la revisará y te notificará una vez aprobada.
        Mientras tanto, ya puedes comenzar a preparar tu documentación.</p>
    </div>
    <?php endif; ?>

    <div class="prac-estado">
        <div class="prac-estado-top">
            <div>
                <div style="font-size:0.75rem;color:#6B7280;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Tipo de práctica</div>
                <div style="font-size:1.1rem;font-weight:800;color:#111827">
                    <?= $tipos_nombres[$practica['tipo_practica']] ?? $practica['tipo_practica'] ?>
                </div>
            </div>
            <span class="prac-tipo-badge badge-<?= $practica['estado'] ?>">
                <?= $estados_nombres[$practica['estado']] ?? $practica['estado'] ?>
            </span>
        </div>

        <div class="prac-info-grid">
            <?php if ($practica['fecha_inicio']): ?>
            <div class="prac-info-item">
                <label>Fecha inicio</label>
                <span><?= date('d/m/Y', strtotime($practica['fecha_inicio'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($practica['fecha_fin']): ?>
            <div class="prac-info-item">
                <label>Fecha fin</label>
                <span><?= date('d/m/Y', strtotime($practica['fecha_fin'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($practica['razon_social']): ?>
            <div class="prac-info-item">
                <label>Empresa</label>
                <span><?= htmlspecialchars($practica['razon_social']) ?></span>
            </div>
            <?php elseif ($practica['empresa_nombre']): ?>
            <div class="prac-info-item">
                <label>Empresa</label>
                <span><?= htmlspecialchars($practica['empresa_nombre']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($practica['tutor_nombres']): ?>
            <div class="prac-info-item">
                <label>Tutor asignado</label>
                <span><?= htmlspecialchars($practica['tutor_nombres'] . ' ' . $practica['tutor_apellidos']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($practica['monitor_nombres']): ?>
            <div class="prac-info-item">
                <label>Monitor INTEP</label>
                <span><?= htmlspecialchars($practica['monitor_nombres'] . ' ' . $practica['monitor_apellidos']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($practica['estipendio_monto']): ?>
            <div class="prac-info-item">
                <label>Estipendio mensual</label>
                <span style="color:#059669">$ <?= number_format($practica['estipendio_monto'], 0, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            <?php if ($practica['horas_semanales']): ?>
            <div class="prac-info-item">
                <label>Horas semanales</label>
                <span><?= (int)$practica['horas_semanales'] ?>h</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($practica['estado'] === 'activa'): ?>
    <div class="prac-acciones">
        <a href="/intep/practicas.php?action=seguimiento" class="btn-prac-primary">
            ✏️ Registrar avance mensual
        </a>
    </div>
    <?php endif; ?>

    <!-- Últimos seguimientos -->
    <div class="prac-card">
        <div class="prac-card-header">
            <h3>📊 Mis avances registrados</h3>
            <span style="font-size:0.78rem;color:#9CA3AF"><?= count($seguimientos) ?> registro(s)</span>
        </div>
        <?php if ($seguimientos): ?>
        <table class="prac-table">
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Horas</th>
                    <th>Avance</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($seguimientos as $s): ?>
                <tr>
                    <td><strong><?= $meses_nombres[(int)$s['mes']] ?> <?= $s['anio'] ?></strong></td>
                    <td><?= (int)$s['horas_cumplidas'] ?>h</td>
                    <td style="max-width:280px">
                        <?= htmlspecialchars(mb_substr($s['avance_descripcion'] ?? '', 0, 80)) ?>
                        <?= mb_strlen($s['avance_descripcion'] ?? '') > 80 ? '…' : '' ?>
                    </td>
                    <td>
                        <?php if ($s['requiere_atencion']): ?>
                            <span style="background:#FEE2E2;color:#991B1B;padding:2px 10px;border-radius:50px;font-size:0.72rem;font-weight:700">Requiere atención</span>
                        <?php elseif ($s['validado_admin']): ?>
                            <span style="background:#D1FAE5;color:#065F46;padding:2px 10px;border-radius:50px;font-size:0.72rem;font-weight:700">Validado</span>
                        <?php else: ?>
                            <span style="background:#F3F4F6;color:#6B7280;padding:2px 10px;border-radius:50px;font-size:0.72rem;font-weight:700">En revisión</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="padding:2rem;text-align:center;color:#9CA3AF;font-size:0.85rem">
            Aún no has registrado avances. Una vez tu práctica esté activa, podrás hacerlo mensualmente.
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'seguimiento' && $practica && $practica['estado'] === 'activa'): ?>
    <!-- ══════════════════════════════════════════════════
         VISTA: FORMULARIO DE SEGUIMIENTO
    ══════════════════════════════════════════════════ -->
    <div style="margin-bottom:1rem">
        <a href="/intep/practicas.php" class="prac-back" style="margin-bottom:0">← Volver a mi práctica</a>
    </div>

    <div class="prac-card">
        <div class="prac-card-header">
            <h3>✏️ Registrar avance mensual</h3>
            <span style="font-size:0.78rem;color:#9CA3AF">Práctica en: <?= htmlspecialchars($practica['razon_social'] ?: $practica['empresa_nombre'] ?: 'INTEP') ?></span>
        </div>
        <div style="padding:1.5rem">
            <form method="POST" action="/intep/practicas.php?action=seguimiento" enctype="multipart/form-data" class="form-prac">
                <div class="form-grid-2">
                    <div>
                        <label>Mes *</label>
                        <select name="mes" required>
                            <?php foreach ($meses_nombres as $i => $nm):
                                if ($i === 0) continue; ?>
                            <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>><?= $nm ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Año *</label>
                        <input type="number" name="anio" value="<?= date('Y') ?>" min="2024" max="2030" required>
                    </div>
                    <div>
                        <label>Horas cumplidas este mes</label>
                        <input type="number" name="horas_cumplidas" value="0" min="0" max="300">
                    </div>
                </div>

                <div style="margin-top:1rem">
                    <label>Descripción del avance *</label>
                    <textarea name="avance_descripcion" rows="5" required
                        placeholder="Describe las actividades que realizaste este mes, proyectos en los que participaste, herramientas que aprendiste..."></textarea>
                </div>

                <div class="form-grid-2" style="margin-top:1rem">
                    <div>
                        <label>Dificultades encontradas</label>
                        <textarea name="dificultades" rows="3"
                            placeholder="¿Algún obstáculo o problema que hayas tenido?"></textarea>
                    </div>
                    <div>
                        <label>Aprendizajes y logros</label>
                        <textarea name="aprendizajes" rows="3"
                            placeholder="¿Qué aprendiste? ¿Qué metas alcanzaste?"></textarea>
                    </div>
                </div>

                <div style="margin-top:1rem;display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="requiere_atencion" id="req_at" value="1" style="width:auto">
                    <label for="req_at" style="margin:0;font-size:0.85rem;cursor:pointer">
                        Requiero atención del tutor o monitor (marcar si tienes un problema que necesite intervención)
                    </label>
                </div>

                <div style="margin-top:1rem">
                    <label>Evidencia (opcional · PDF, imagen, Word · máx. 5 MB)</label>
                    <input type="file" name="evidencia" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>

                <div style="margin-top:1.5rem;display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" class="btn-prac-primary">📤 Enviar avance</button>
                    <a href="/intep/practicas.php" class="btn-prac-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- ══════════════════════════════════════════════════
         VISTA: GUÍA — Elegir tipo de práctica
    ══════════════════════════════════════════════════ -->

    <div class="prac-card" style="margin-bottom:1.5rem">
        <div class="prac-card-header">
            <h3>¿Qué tipo de práctica te aplica?</h3>
        </div>
        <div style="padding:1rem 1.5rem;font-size:0.85rem;color:#6B7280;line-height:1.6">
            Según el <strong>Decreto 0223 del 5 de marzo de 2026</strong> del Ministerio del Trabajo,
            existen 5 modalidades de práctica. Selecciona la que mejor describe tu situación actual
            y registra tu solicitud. El coordinador la revisará y te confirmará.
        </div>
    </div>

    <form method="POST" action="/intep/practicas.php?action=solicitar" id="form-solicitar">
    <div class="guia-grid">

        <!-- 1. Constancia Laboral -->
        <label class="guia-card" for="tipo_cl" id="card_constancia_laboral">
            <input type="radio" name="tipo_practica" id="tipo_cl" value="constancia_laboral"
                   onchange="seleccionarTipo(this)">
            <div class="guia-check">✓</div>
            <div class="guia-icon" style="background:#FEF3C7;color:#D97706">💼</div>
            <h4>Constancia Laboral</h4>
            <p>Ya tienes un empleo formal relacionado con tu programa de formación y aportas los documentos que lo demuestran.</p>
            <div class="guia-decreto">Decreto 0223 · Vinculación Formativa</div>
        </label>

        <!-- 2. Pasantías -->
        <label class="guia-card" for="tipo_pas" id="card_pasantias">
            <input type="radio" name="tipo_practica" id="tipo_pas" value="pasantias"
                   onchange="seleccionarTipo(this)">
            <div class="guia-check">✓</div>
            <div class="guia-icon" style="background:#EDE9FE;color:#7C3AED">🏢</div>
            <h4>Pasantías</h4>
            <p>Te vincularás a una empresa o entidad para aprender en ambiente real, con supervisión de un tutor empresarial.</p>
            <div class="guia-decreto">Decreto 0223 · Vinculación Formativa</div>
        </label>

        <!-- 3. Unidad Productiva -->
        <label class="guia-card" for="tipo_up" id="card_unidad_productiva">
            <input type="radio" name="tipo_practica" id="tipo_up" value="unidad_productiva"
                   onchange="seleccionarTipo(this)">
            <div class="guia-check">✓</div>
            <div class="guia-icon" style="background:#D1FAE5;color:#059669">🌱</div>
            <h4>Unidad Productiva</h4>
            <p>Tienes tu propio negocio o emprendimiento relacionado con tu área de formación que puedes validar como práctica.</p>
            <div class="guia-decreto">Decreto 0223 · Vinculación Formativa</div>
        </label>

        <!-- 4. Simulación -->
        <label class="guia-card" for="tipo_sim" id="card_simulacion">
            <input type="radio" name="tipo_practica" id="tipo_sim" value="simulacion"
                   onchange="seleccionarTipo(this)">
            <div class="guia-check">✓</div>
            <div class="guia-icon" style="background:#DBEAFE;color:#2563EB">🎓</div>
            <h4>Simulación de Prácticas</h4>
            <p>Realizas tus prácticas dentro de la institución educativa, en talleres, laboratorios o proyectos internos de INTEP.</p>
            <div class="guia-decreto">Decreto 0223 · Vinculación Formativa</div>
        </label>

        <!-- 5. Contrato de Aprendizaje -->
        <label class="guia-card" for="tipo_ca" id="card_contrato_aprendizaje">
            <input type="radio" name="tipo_practica" id="tipo_ca" value="contrato_aprendizaje"
                   onchange="seleccionarTipo(this)">
            <div class="guia-check">✓</div>
            <div class="guia-icon" style="background:#FEE2E2;color:#DC2626">📄</div>
            <h4>Contrato de Aprendizaje</h4>
            <p>Firmas un contrato formal con una empresa patrocinadora (cuota SENA), con estipendio económico y duración definida.</p>
            <div class="guia-decreto">Decreto 0223 · Contrato de Aprendizaje</div>
        </label>

    </div>

    <!-- Campos extra según tipo -->
    <div id="extra_constancia_laboral" class="tipo-extra">
        <div class="prac-card" style="border-color:#FDE68A">
            <div class="prac-card-header">
                <h3>💼 Datos de tu empleo actual</h3>
            </div>
            <div style="padding:1.5rem" class="form-prac">
                <div class="form-grid-2">
                    <div>
                        <label>Nombre de la empresa</label>
                        <input type="text" name="empresa_nombre" placeholder="Ej: Empresa XYZ S.A.S.">
                    </div>
                    <div>
                        <label>Tu cargo actual</label>
                        <input type="text" name="cargo_actual" placeholder="Ej: Auxiliar administrativo">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="extra_unidad_productiva" class="tipo-extra">
        <div class="prac-card" style="border-color:#A7F3D0">
            <div class="prac-card-header">
                <h3>🌱 Describe tu negocio o emprendimiento</h3>
            </div>
            <div style="padding:1.5rem" class="form-prac">
                <label>Descripción del negocio</label>
                <textarea name="descripcion_negocio" rows="3"
                    placeholder="Describe brevemente tu negocio: qué ofreces, cómo se relaciona con tu programa de formación..."></textarea>
            </div>
        </div>
    </div>

    <div id="seccion-enviar" style="display:none;margin-top:1.5rem">
        <div class="prac-card" style="background:#F0FDF4;border-color:#A7F3D0">
            <div style="padding:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
                <div>
                    <div style="font-weight:700;color:#065F46;margin-bottom:4px">¿Confirmás tu selección?</div>
                    <div style="font-size:0.82rem;color:#6B7280">
                        El coordinador de prácticas revisará tu solicitud y te contactará para confirmar el proceso.
                    </div>
                </div>
                <button type="submit" class="btn-prac-primary" style="font-size:0.9rem;padding:12px 28px">
                    📩 Enviar solicitud
                </button>
            </div>
        </div>
    </div>

    </form>

<?php endif; ?>

</div><!-- /.prac-container -->

<script>
function seleccionarTipo(radio) {
    // Quitar selected de todas las cards
    document.querySelectorAll('.guia-card').forEach(c => c.classList.remove('selected'));
    // Marcar la seleccionada
    const card = document.getElementById('card_' + radio.value);
    if (card) card.classList.add('selected');
    // Ocultar todos los extras
    document.querySelectorAll('.tipo-extra').forEach(e => e.classList.remove('visible'));
    // Mostrar el extra del tipo seleccionado
    const extra = document.getElementById('extra_' + radio.value);
    if (extra) extra.classList.add('visible');
    // Mostrar botón de enviar
    document.getElementById('seccion-enviar').style.display = 'block';
}
</script>
</body>
</html>
