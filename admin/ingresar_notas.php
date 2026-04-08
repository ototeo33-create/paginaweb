<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../dashboard.php');
    exit;
}

$es_admin = $_SESSION['usuario_rol'] === 'admin';
$usuario_id = (int)$_SESSION['usuario_id'];

// ===== OBTENER MÓDULOS (desde programa_modulo) =====
$modulos = [];
if ($es_admin) {
    $sql_mod = "SELECT pm.id, mf.nombre, pm.bimestre, pm.orden, pm.tipo,
                       p.nombre as programa_nombre, p.id as programa_id, u.username as docente_nombre
                FROM programa_modulo pm
                JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                JOIN programas p ON pm.programa_id = p.id
                LEFT JOIN usuarios u ON pm.docente_id = u.id
                ORDER BY p.nombre, pm.bimestre, pm.orden";
    $res_mod = mysqli_query($conexion, $sql_mod);
} else {
    $sql_mod = "SELECT pm.id, mf.nombre, pm.bimestre, pm.orden, pm.tipo,
                       p.nombre as programa_nombre, p.id as programa_id, u.username as docente_nombre
                FROM programa_modulo pm
                JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                JOIN programas p ON pm.programa_id = p.id
                LEFT JOIN usuarios u ON pm.docente_id = u.id
                WHERE pm.docente_id = ?
                ORDER BY p.nombre, pm.bimestre, pm.orden";
    $stmt_mod = mysqli_prepare($conexion, $sql_mod);
    mysqli_stmt_bind_param($stmt_mod, 'i', $usuario_id);
    mysqli_stmt_execute($stmt_mod);
    $res_mod = mysqli_stmt_get_result($stmt_mod);
}
while ($row = mysqli_fetch_assoc($res_mod)) {
    $modulos[] = $row;
}

// ===== OBTENER ESTUDIANTES Y DATOS (si ya seleccionó módulo) =====
$estudiantes = [];
$modulo_seleccionado = null;
$notas_existentes = [];
$asistencia_existente = [];
$observaciones_existentes = [];

if (isset($_GET['modulo_id']) && $_GET['modulo_id'] > 0) {
    $modulo_id_sel = (int)$_GET['modulo_id'];

    $sql_info = "SELECT pm.id, mf.nombre, pm.bimestre, pm.orden, pm.tipo, pm.docente_id,
                        pm.programa_id, p.nombre as programa_nombre, u.username as docente_nombre
                 FROM programa_modulo pm
                 JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                 JOIN programas p ON pm.programa_id = p.id
                 LEFT JOIN usuarios u ON pm.docente_id = u.id
                 WHERE pm.id = ?";
    $stmt_info = mysqli_prepare($conexion, $sql_info);
    mysqli_stmt_bind_param($stmt_info, 'i', $modulo_id_sel);
    mysqli_stmt_execute($stmt_info);
    $modulo_seleccionado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));

    // Verificar permiso docente
    if (!$es_admin && $modulo_seleccionado && $modulo_seleccionado['docente_id'] != $usuario_id) {
        $modulo_seleccionado = null;
    }

    if ($modulo_seleccionado) {
        // Estudiantes del programa
        $sql_est = "SELECT id, nombre, documento FROM estudiantes
                    WHERE programa_id = ? AND estado = 'activo'
                    ORDER BY nombre";
        $stmt_est = mysqli_prepare($conexion, $sql_est);
        mysqli_stmt_bind_param($stmt_est, 'i', $modulo_seleccionado['programa_id']);
        mysqli_stmt_execute($stmt_est);
        $res_est = mysqli_stmt_get_result($stmt_est);
        while ($row = mysqli_fetch_assoc($res_est)) {
            $estudiantes[] = $row;
        }

        // Notas existentes
        $sql_notas = "SELECT * FROM notas WHERE programa_modulo_id = ?";
        $stmt_notas = mysqli_prepare($conexion, $sql_notas);
        mysqli_stmt_bind_param($stmt_notas, 'i', $modulo_id_sel);
        mysqli_stmt_execute($stmt_notas);
        $res_notas = mysqli_stmt_get_result($stmt_notas);
        while ($row = mysqli_fetch_assoc($res_notas)) {
            $notas_existentes[$row['estudiante_id']] = $row;
        }

        // Asistencia existente
        $sql_asist = "SELECT * FROM asistencia WHERE programa_modulo_id = ?";
        $stmt_asist = mysqli_prepare($conexion, $sql_asist);
        mysqli_stmt_bind_param($stmt_asist, 'i', $modulo_id_sel);
        mysqli_stmt_execute($stmt_asist);
        $res_asist = mysqli_stmt_get_result($stmt_asist);
        while ($row = mysqli_fetch_assoc($res_asist)) {
            $asistencia_existente[$row['estudiante_id']] = $row;
        }

        // Observaciones existentes
        $sql_obs = "SELECT o.*, u.username as autor_nombre
                    FROM observaciones o
                    JOIN usuarios u ON o.autor_id = u.id
                    WHERE o.programa_modulo_id = ?
                    ORDER BY o.fecha DESC";
        $stmt_obs = mysqli_prepare($conexion, $sql_obs);
        mysqli_stmt_bind_param($stmt_obs, 'i', $modulo_id_sel);
        mysqli_stmt_execute($stmt_obs);
        $res_obs = mysqli_stmt_get_result($stmt_obs);
        while ($row = mysqli_fetch_assoc($res_obs)) {
            $observaciones_existentes[$row['estudiante_id']][] = $row;
        }
    }
}

$total_est = count($estudiantes);
$calificados = 0;
foreach ($estudiantes as $e) {
    if (isset($notas_existentes[$e['id']]) && $notas_existentes[$e['id']]['nota_final'] !== null) {
        $calificados++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla de Notas - INTEP</title>
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
        .page-container { max-width: 1400px; margin: 2rem auto; padding: 0 1.5rem; }
        .page-title {
            font-size: 1.3rem; font-weight: 800; color: #022C22;
            margin-bottom: 1.5rem; padding-bottom: 0.8rem;
            border-bottom: 2px solid #ECFDF5;
            display: flex; align-items: center; gap: 0.5rem;
        }

        /* Selector de modulo */
        .selector-card {
            background: rgba(255,255,255,0.75); backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.5rem; border: 1px solid rgba(16,185,129,0.1);
        }
        .selector-card h3 { font-size: 1rem; color: #059669; margin-bottom: 1rem; }
        .selector-card select {
            width: 100%; padding: 0.8rem 1rem;
            border: 2px solid rgba(16,185,129,0.2); border-radius: 10px;
            font-size: 0.95rem; outline: none; transition: border-color 0.2s;
            background: rgba(255,255,255,0.8);
        }
        .selector-card select:focus { border-color: #10B981; }

        /* Info del modulo + acciones */
        .modulo-header {
            background: linear-gradient(135deg, #064E3B, #059669);
            color: white; border-radius: 14px; padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
        }
        .modulo-header h2 { font-size: 1.15rem; margin: 0; }
        .modulo-header .meta { margin: 0.3rem 0 0; opacity: 0.85; font-size: 0.88rem; }
        .modulo-header .acciones { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
        .mod-badge {
            background: rgba(255,255,255,0.2); padding: 0.35rem 0.9rem;
            border-radius: 20px; font-size: 0.82rem;
        }
        .btn-exportar {
            background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s; text-decoration: none;
        }
        .btn-exportar:hover { background: rgba(255,255,255,0.35); }
        .progreso-badge {
            background: rgba(255,255,255,0.15); padding: 0.4rem 0.8rem;
            border-radius: 8px; font-size: 0.82rem;
        }

        /* Tabs */
        .tabs-container {
            background: white; border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            border: 1px solid rgba(16,185,129,0.1);
        }
        .tabs-nav {
            display: flex; background: #022C22; overflow-x: auto;
        }
        .tab-btn {
            padding: 0.9rem 1.5rem; color: rgba(255,255,255,0.6);
            font-size: 0.88rem; font-weight: 600; cursor: pointer;
            border: none; background: none; white-space: nowrap;
            transition: all 0.2s; border-bottom: 3px solid transparent;
        }
        .tab-btn:hover { color: rgba(255,255,255,0.85); }
        .tab-btn.active { color: white; border-bottom-color: #10B981; background: rgba(16,185,129,0.15); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Planilla table */
        .planilla-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .planilla {
            width: 100%; border-collapse: collapse; font-size: 0.82rem;
        }
        .planilla thead { background: #064E3B; color: white; position: sticky; top: 0; z-index: 2; }
        .planilla th {
            padding: 0.6rem 0.4rem; text-align: center; font-size: 0.72rem;
            text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap;
        }
        .planilla th.col-nombre { text-align: left; min-width: 180px; position: sticky; left: 0; background: #064E3B; z-index: 3; }
        .planilla th.col-num { width: 30px; }
        .planilla th.grupo-conocimiento { background: #1E40AF; }
        .planilla th.grupo-producto { background: #7C3AED; }
        .planilla th.grupo-desempeno { background: #047857; }
        .planilla th.col-final { background: #92400E; }

        .planilla td {
            padding: 0.3rem; text-align: center; border-bottom: 1px solid #E5E7EB;
            vertical-align: middle;
        }
        .planilla td.col-nombre {
            text-align: left; padding-left: 0.6rem; font-weight: 500;
            position: sticky; left: 0; background: white; z-index: 1;
            white-space: nowrap; max-width: 200px; overflow: hidden; text-overflow: ellipsis;
        }
        .planilla tr:hover td { background: #F0FDF4; }
        .planilla tr:hover td.col-nombre { background: #F0FDF4; }

        .planilla input[type="number"] {
            width: 48px; padding: 0.35rem 0.2rem; text-align: center;
            border: 1.5px solid #E5E7EB; border-radius: 6px; font-size: 0.82rem;
            outline: none; transition: border-color 0.2s;
        }
        .planilla input[type="number"]:focus { border-color: #10B981; box-shadow: 0 0 0 2px rgba(16,185,129,0.15); }
        .planilla input[type="number"].modified { border-color: #F59E0B; background: #FFFBEB; }

        .calc-cell { font-weight: 700; font-size: 0.85rem; color: #374151; }
        .calc-cell.conocimiento { color: #1E40AF; }
        .calc-cell.producto { color: #7C3AED; }
        .calc-cell.desempeno { color: #047857; }
        .nota-final-cell { font-weight: 800; font-size: 0.9rem; }
        .nota-final-cell.aprobado { color: #059669; }
        .nota-final-cell.reprobado { color: #EF4444; }
        .nota-final-cell.pendiente { color: #9CA3AF; }

        .estado-cell .badge {
            display: inline-block; padding: 0.15rem 0.5rem; border-radius: 10px;
            font-size: 0.72rem; font-weight: 600;
        }
        .badge.aprobado { background: #ECFDF5; color: #065F46; }
        .badge.reprobado { background: #FEF2F2; color: #991B1B; }
        .badge.pendiente { background: #F3F4F6; color: #6B7280; }

        /* Grupo headers row */
        .planilla .grupo-header th {
            padding: 0.4rem; font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Boton guardar flotante */
        .save-bar {
            padding: 1rem 1.5rem; background: #F0FDF4; border-top: 2px solid #D1FAE5;
            display: flex; justify-content: space-between; align-items: center;
        }
        .save-bar .info { font-size: 0.85rem; color: #6B7280; }
        .save-bar .info .unsaved { color: #F59E0B; font-weight: 600; }
        .btn-guardar {
            padding: 0.7rem 1.8rem;
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; border: none; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-guardar:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.3); }
        .btn-guardar:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Asistencia table */
        .planilla-asist input[type="number"] { width: 60px; }
        .porcentaje-cell { font-weight: 700; }
        .porcentaje-cell.alta { color: #059669; }
        .porcentaje-cell.media { color: #F59E0B; }
        .porcentaje-cell.baja { color: #EF4444; }

        /* Observaciones */
        .obs-list { padding: 1.5rem; }
        .obs-estudiante {
            border: 1px solid #E5E7EB; border-radius: 12px;
            margin-bottom: 1rem; overflow: hidden;
        }
        .obs-header {
            background: #F9FAFB; padding: 0.8rem 1.2rem;
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; user-select: none;
        }
        .obs-header:hover { background: #F0FDF4; }
        .obs-header .nombre { font-weight: 600; font-size: 0.9rem; color: #1F2937; }
        .obs-header .count { font-size: 0.8rem; color: #9CA3AF; }
        .obs-body { display: none; padding: 1rem 1.2rem; border-top: 1px solid #E5E7EB; }
        .obs-body.open { display: block; }
        .obs-form { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .obs-form textarea {
            flex: 1; padding: 0.6rem 0.8rem; border: 2px solid #E5E7EB;
            border-radius: 8px; font-size: 0.85rem; resize: vertical;
            min-height: 60px; outline: none; font-family: inherit;
        }
        .obs-form textarea:focus { border-color: #10B981; }
        .obs-form .btn-obs {
            padding: 0.6rem 1rem; background: #059669; color: white;
            border: none; border-radius: 8px; font-size: 0.82rem;
            font-weight: 600; cursor: pointer; align-self: flex-end;
            white-space: nowrap;
        }
        .obs-form .btn-obs:hover { background: #047857; }
        .obs-historial { max-height: 300px; overflow-y: auto; }
        .obs-item {
            padding: 0.6rem 0; border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
        }
        .obs-item:last-child { border-bottom: none; }
        .obs-item .obs-meta { font-size: 0.75rem; color: #9CA3AF; margin-top: 0.2rem; }
        .obs-item .obs-texto { color: #374151; }

        /* Alerta toast */
        .toast {
            position: fixed; bottom: 2rem; right: 2rem; z-index: 1000;
            padding: 0.9rem 1.5rem; border-radius: 12px; font-size: 0.9rem;
            font-weight: 600; box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transform: translateY(100px); opacity: 0; transition: all 0.3s;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.exito { background: #059669; color: white; }
        .toast.error { background: #EF4444; color: white; }

        /* Sin datos */
        .sin-datos { text-align: center; padding: 3rem; color: #9CA3AF; }
        .sin-datos .icono { font-size: 3rem; margin-bottom: 1rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .page-container { padding: 0 0.5rem; }
            .modulo-header { flex-direction: column; text-align: center; }
            .modulo-header .acciones { justify-content: center; }
            .tab-btn { padding: 0.7rem 1rem; font-size: 0.8rem; }
            .planilla input[type="number"] { width: 40px; padding: 0.3rem 0.1rem; font-size: 0.78rem; }
            .planilla th, .planilla td { padding: 0.25rem 0.2rem; }
            .obs-form { flex-direction: column; }
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

    <div class="dashboard-header">
        <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
        <span class="usuario-info">Planilla de Notas</span>
        <a href="../logout.php" class="btn-salir">Cerrar sesion</a>
    </div>

    <div class="page-container">
        <a href="../dashboard.php" class="btn-volver">&larr; Volver al inicio</a>
        <div class="page-title">Planilla de Notas y Asistencia</div>

        <!-- Selector de modulo -->
        <div class="selector-card">
            <h3>Seleccionar Modulo</h3>
            <select onchange="if(this.value) window.location='?modulo_id='+this.value">
                <option value="">-- Selecciona un modulo --</option>
                <?php
                $prog_actual = '';
                foreach ($modulos as $mod):
                    if ($mod['programa_nombre'] !== $prog_actual):
                        if ($prog_actual !== '') echo '</optgroup>';
                        $prog_actual = $mod['programa_nombre'];
                        echo '<optgroup label="' . htmlspecialchars($prog_actual) . '">';
                    endif;
                ?>
                    <option value="<?php echo $mod['id']; ?>"
                        <?php echo (isset($_GET['modulo_id']) && $_GET['modulo_id'] == $mod['id']) ? 'selected' : ''; ?>>
                        Bim. <?php echo $mod['bimestre']; ?> -- <?php echo htmlspecialchars($mod['nombre']); ?> (<?php echo htmlspecialchars($mod['tipo'] ?? ''); ?>)
                    </option>
                <?php endforeach; ?>
                <?php if ($prog_actual !== '') echo '</optgroup>'; ?>
            </select>
        </div>

        <?php if ($modulo_seleccionado): ?>

            <!-- Header del modulo -->
            <div class="modulo-header">
                <div>
                    <h2><?php echo htmlspecialchars($modulo_seleccionado['nombre']); ?></h2>
                    <p class="meta">
                        <?php echo htmlspecialchars($modulo_seleccionado['tipo'] ?? ''); ?> &middot;
                        <?php echo htmlspecialchars($modulo_seleccionado['programa_nombre']); ?>
                        <?php if ($modulo_seleccionado['docente_nombre']): ?>
                            &middot; Docente: <?php echo htmlspecialchars($modulo_seleccionado['docente_nombre']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="acciones">
                    <span class="progreso-badge"><?php echo $calificados; ?>/<?php echo $total_est; ?> calificados</span>
                    <span class="mod-badge">Bimestre <?php echo $modulo_seleccionado['bimestre']; ?></span>
                    <a href="exportar_notas.php?modulo_id=<?php echo $modulo_id_sel; ?>" class="btn-exportar">Exportar Excel</a>
                </div>
            </div>

            <?php if (empty($estudiantes)): ?>
                <div class="sin-datos">
                    <div class="icono">&#128101;</div>
                    <p>No hay estudiantes activos en este programa.</p>
                </div>
            <?php else: ?>

                <!-- Tabs -->
                <div class="tabs-container">
                    <div class="tabs-nav">
                        <button class="tab-btn active" data-tab="calificaciones">Calificaciones</button>
                        <button class="tab-btn" data-tab="asistencia">Asistencia</button>
                        <button class="tab-btn" data-tab="observaciones">Observaciones</button>
                    </div>

                    <!-- ===== TAB CALIFICACIONES ===== -->
                    <div class="tab-content active" id="tab-calificaciones">
                        <div class="planilla-wrapper">
                            <table class="planilla" id="planilla-notas">
                                <thead>
                                    <tr class="grupo-header">
                                        <th colspan="2"></th>
                                        <th colspan="3" class="grupo-conocimiento">Conocimiento (30%)</th>
                                        <th colspan="4" class="grupo-producto">Producto (30%)</th>
                                        <th colspan="4" class="grupo-desempeno">Desempe&ntilde;o (40%)</th>
                                        <th colspan="2" class="col-final">Resultado</th>
                                    </tr>
                                    <tr>
                                        <th class="col-num">N&deg;</th>
                                        <th class="col-nombre">Estudiante</th>
                                        <th class="grupo-conocimiento">P1</th>
                                        <th class="grupo-conocimiento">P2</th>
                                        <th class="grupo-conocimiento">EC</th>
                                        <th class="grupo-producto">T1</th>
                                        <th class="grupo-producto">T2</th>
                                        <th class="grupo-producto">T3</th>
                                        <th class="grupo-producto">EP</th>
                                        <th class="grupo-desempeno">D1</th>
                                        <th class="grupo-desempeno">D2</th>
                                        <th class="grupo-desempeno">D3</th>
                                        <th class="grupo-desempeno">ED</th>
                                        <th class="col-final">Final</th>
                                        <th class="col-final">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $i => $est):
                                        $n = $notas_existentes[$est['id']] ?? [];
                                        $nf = $n['nota_final'] ?? null;
                                    ?>
                                    <tr data-estudiante-id="<?php echo $est['id']; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td class="col-nombre" title="<?php echo htmlspecialchars($est['nombre']); ?>">
                                            <?php echo htmlspecialchars($est['nombre']); ?>
                                        </td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="parcial1" value="<?php echo $n['parcial1'] ?? ''; ?>"></td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="parcial2" value="<?php echo $n['parcial2'] ?? ''; ?>"></td>
                                        <td class="calc-cell conocimiento" data-calc="ec">--</td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="producto1" value="<?php echo $n['producto1'] ?? ''; ?>"></td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="producto2" value="<?php echo $n['producto2'] ?? ''; ?>"></td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="producto3" value="<?php echo $n['producto3'] ?? ''; ?>"></td>
                                        <td class="calc-cell producto" data-calc="ep">--</td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="desempeno1" value="<?php echo $n['desempeno1'] ?? ''; ?>"></td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="desempeno2" value="<?php echo $n['desempeno2'] ?? ''; ?>"></td>
                                        <td><input type="number" step="0.1" min="0" max="5" class="nota-input" data-field="desempeno3" value="<?php echo $n['desempeno3'] ?? ''; ?>"></td>
                                        <td class="calc-cell desempeno" data-calc="ed">--</td>
                                        <td class="nota-final-cell <?php echo $nf !== null ? ($nf >= 3.5 ? 'aprobado' : 'reprobado') : 'pendiente'; ?>" data-calc="final">
                                            <?php echo $nf !== null ? number_format($nf, 1) : '--'; ?>
                                        </td>
                                        <td class="estado-cell">
                                            <?php if ($nf !== null): ?>
                                                <span class="badge <?php echo $nf >= 3.5 ? 'aprobado' : 'reprobado'; ?>">
                                                    <?php echo $nf >= 3.5 ? 'Aprobado' : 'Reprobado'; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge pendiente">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="save-bar">
                            <div class="info">
                                <span id="unsaved-count"></span>
                            </div>
                            <button class="btn-guardar" id="btn-guardar-notas" onclick="guardarNotas()">Guardar Notas</button>
                        </div>
                    </div>

                    <!-- ===== TAB ASISTENCIA ===== -->
                    <div class="tab-content" id="tab-asistencia">
                        <div class="planilla-wrapper">
                            <table class="planilla planilla-asist" id="planilla-asistencia">
                                <thead>
                                    <tr>
                                        <th class="col-num">N&deg;</th>
                                        <th class="col-nombre">Estudiante</th>
                                        <th>Total Clases</th>
                                        <th>Asistencias</th>
                                        <th>Inasistencias</th>
                                        <th>% Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $i => $est):
                                        $a = $asistencia_existente[$est['id']] ?? [];
                                    ?>
                                    <tr data-estudiante-id="<?php echo $est['id']; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td class="col-nombre"><?php echo htmlspecialchars($est['nombre']); ?></td>
                                        <td><input type="number" min="0" max="200" class="asist-input" data-field="total_clases" value="<?php echo $a['total_clases'] ?? ''; ?>"></td>
                                        <td><input type="number" min="0" max="200" class="asist-input" data-field="total_asistencias" value="<?php echo $a['total_asistencias'] ?? ''; ?>"></td>
                                        <td class="calc-cell" data-calc="inasistencias">
                                            <?php
                                            if (isset($a['total_inasistencias'])) echo $a['total_inasistencias'];
                                            else echo '--';
                                            ?>
                                        </td>
                                        <td class="porcentaje-cell <?php
                                            $p = $a['porcentaje_asistencia'] ?? null;
                                            echo $p !== null ? ($p >= 80 ? 'alta' : ($p >= 60 ? 'media' : 'baja')) : '';
                                        ?>" data-calc="porcentaje">
                                            <?php echo $p !== null ? number_format($p, 1) . '%' : '--'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="save-bar">
                            <div class="info"></div>
                            <button class="btn-guardar" id="btn-guardar-asist" onclick="guardarAsistencia()">Guardar Asistencia</button>
                        </div>
                    </div>

                    <!-- ===== TAB OBSERVACIONES ===== -->
                    <div class="tab-content" id="tab-observaciones">
                        <div class="obs-list">
                            <?php foreach ($estudiantes as $i => $est):
                                $obs = $observaciones_existentes[$est['id']] ?? [];
                            ?>
                            <div class="obs-estudiante" data-estudiante-id="<?php echo $est['id']; ?>">
                                <div class="obs-header" onclick="toggleObs(this)">
                                    <span class="nombre"><?php echo ($i+1) . '. ' . htmlspecialchars($est['nombre']); ?></span>
                                    <span class="count" data-obs-count="<?php echo $est['id']; ?>"><?php echo count($obs); ?> observacion(es)</span>
                                </div>
                                <div class="obs-body">
                                    <div class="obs-form">
                                        <textarea placeholder="Escribir observacion..." id="obs-text-<?php echo $est['id']; ?>"></textarea>
                                        <button class="btn-obs" onclick="guardarObservacion(<?php echo $est['id']; ?>)">Agregar</button>
                                    </div>
                                    <div class="obs-historial" id="obs-hist-<?php echo $est['id']; ?>">
                                        <?php foreach ($obs as $o): ?>
                                        <div class="obs-item">
                                            <div class="obs-texto"><?php echo htmlspecialchars($o['observacion']); ?></div>
                                            <div class="obs-meta"><?php echo htmlspecialchars($o['autor_nombre']); ?> &middot; <?php echo date('d/m/Y H:i', strtotime($o['fecha'])); ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($obs)): ?>
                                            <p style="color:#9CA3AF;font-size:0.85rem;text-align:center;">Sin observaciones</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div><!-- tabs-container -->

            <?php endif; ?>

        <?php else: ?>
            <div class="sin-datos">
                <div class="icono">&#128203;</div>
                <p>Selecciona un modulo para ver la planilla de notas.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script>
    var MODULO_ID = <?php echo $modulo_id_sel ?? 0; ?>;
    var unsavedNotas = 0;

    // ===== TABS =====
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        });
    });

    // ===== TOAST =====
    function showToast(msg, tipo) {
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + tipo + ' show';
        setTimeout(function() { t.classList.remove('show'); }, 3000);
    }

    // ===== CALCULO AUTOMATICO NOTAS =====
    function calcularFila(row) {
        var get = function(field) {
            var inp = row.querySelector('input[data-field="' + field + '"]');
            var v = inp ? parseFloat(inp.value) : NaN;
            return (!isNaN(v) && v >= 0 && v <= 5) ? v : null;
        };

        var p1 = get('parcial1'), p2 = get('parcial2');
        var t1 = get('producto1'), t2 = get('producto2'), t3 = get('producto3');
        var d1 = get('desempeno1'), d2 = get('desempeno2'), d3 = get('desempeno3');

        var ec = (p1 !== null && p2 !== null) ? ((p1 + p2) / 2) : null;
        var prods = [t1, t2, t3].filter(function(v) { return v !== null; });
        var ep = prods.length > 0 ? prods.reduce(function(a,b){return a+b;},0) / prods.length : null;
        var desps = [d1, d2, d3].filter(function(v) { return v !== null; });
        var ed = desps.length > 0 ? desps.reduce(function(a,b){return a+b;},0) / desps.length : null;

        row.querySelector('[data-calc="ec"]').textContent = ec !== null ? ec.toFixed(1) : '--';
        row.querySelector('[data-calc="ep"]').textContent = ep !== null ? ep.toFixed(1) : '--';
        row.querySelector('[data-calc="ed"]').textContent = ed !== null ? ed.toFixed(1) : '--';

        var finalCell = row.querySelector('[data-calc="final"]');
        var estadoCell = row.querySelector('.estado-cell');

        if (ec !== null && ep !== null && ed !== null) {
            var nf = (ec * 0.30) + (ep * 0.30) + (ed * 0.40);
            finalCell.textContent = nf.toFixed(1);
            finalCell.className = 'nota-final-cell ' + (nf >= 3.5 ? 'aprobado' : 'reprobado');
            estadoCell.innerHTML = '<span class="badge ' + (nf >= 3.5 ? 'aprobado' : 'reprobado') + '">' + (nf >= 3.5 ? 'Aprobado' : 'Reprobado') + '</span>';
        } else {
            finalCell.textContent = '--';
            finalCell.className = 'nota-final-cell pendiente';
            estadoCell.innerHTML = '<span class="badge pendiente">Pendiente</span>';
        }
    }

    // Escuchar cambios en inputs de notas
    document.querySelectorAll('#planilla-notas .nota-input').forEach(function(inp) {
        inp.addEventListener('input', function() {
            inp.classList.add('modified');
            calcularFila(inp.closest('tr'));
            updateUnsaved();
        });
    });

    function updateUnsaved() {
        var count = document.querySelectorAll('#planilla-notas .nota-input.modified').length;
        var el = document.getElementById('unsaved-count');
        if (el) {
            el.innerHTML = count > 0 ? '<span class="unsaved">' + count + ' cambio(s) sin guardar</span>' : 'Todo guardado';
        }
    }

    // Calcular al cargar
    document.querySelectorAll('#planilla-notas tbody tr').forEach(calcularFila);
    updateUnsaved();

    // ===== CALCULO AUTOMATICO ASISTENCIA =====
    function calcularAsistFila(row) {
        var tc = parseInt(row.querySelector('input[data-field="total_clases"]').value) || 0;
        var ta = parseInt(row.querySelector('input[data-field="total_asistencias"]').value) || 0;
        var ina = Math.max(0, tc - ta);
        var pct = tc > 0 ? ((ta / tc) * 100) : 0;

        row.querySelector('[data-calc="inasistencias"]').textContent = tc > 0 ? ina : '--';
        var pctCell = row.querySelector('[data-calc="porcentaje"]');
        if (tc > 0) {
            pctCell.textContent = pct.toFixed(1) + '%';
            pctCell.className = 'porcentaje-cell ' + (pct >= 80 ? 'alta' : (pct >= 60 ? 'media' : 'baja'));
        } else {
            pctCell.textContent = '--';
            pctCell.className = 'porcentaje-cell';
        }
    }

    document.querySelectorAll('#planilla-asistencia .asist-input').forEach(function(inp) {
        inp.addEventListener('input', function() { calcularAsistFila(inp.closest('tr')); });
    });
    document.querySelectorAll('#planilla-asistencia tbody tr').forEach(calcularAsistFila);

    // ===== GUARDAR NOTAS (AJAX) =====
    function guardarNotas() {
        var btn = document.getElementById('btn-guardar-notas');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        var notas = [];
        document.querySelectorAll('#planilla-notas tbody tr').forEach(function(row) {
            var estId = row.dataset.estudianteId;
            var data = { estudiante_id: parseInt(estId) };
            row.querySelectorAll('.nota-input').forEach(function(inp) {
                data[inp.dataset.field] = inp.value !== '' ? parseFloat(inp.value) : null;
            });
            notas.push(data);
        });

        fetch('guardar_notas_masivo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ programa_modulo_id: MODULO_ID, notas: notas })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                showToast('Notas guardadas (' + data.guardados + ' estudiantes)', 'exito');
                document.querySelectorAll('#planilla-notas .nota-input.modified').forEach(function(inp) {
                    inp.classList.remove('modified');
                });
                updateUnsaved();
            } else {
                showToast('Error: ' + (data.error || 'Desconocido'), 'error');
            }
            btn.disabled = false;
            btn.textContent = 'Guardar Notas';
        })
        .catch(function(err) {
            showToast('Error de conexion', 'error');
            btn.disabled = false;
            btn.textContent = 'Guardar Notas';
        });
    }

    // ===== GUARDAR ASISTENCIA (AJAX) =====
    function guardarAsistencia() {
        var btn = document.getElementById('btn-guardar-asist');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        var asistencias = [];
        document.querySelectorAll('#planilla-asistencia tbody tr').forEach(function(row) {
            asistencias.push({
                estudiante_id: parseInt(row.dataset.estudianteId),
                total_clases: parseInt(row.querySelector('input[data-field="total_clases"]').value) || 0,
                total_asistencias: parseInt(row.querySelector('input[data-field="total_asistencias"]').value) || 0
            });
        });

        fetch('guardar_asistencia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ programa_modulo_id: MODULO_ID, asistencias: asistencias })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                showToast('Asistencia guardada (' + data.guardados + ' estudiantes)', 'exito');
            } else {
                showToast('Error: ' + (data.error || 'Desconocido'), 'error');
            }
            btn.disabled = false;
            btn.textContent = 'Guardar Asistencia';
        })
        .catch(function() {
            showToast('Error de conexion', 'error');
            btn.disabled = false;
            btn.textContent = 'Guardar Asistencia';
        });
    }

    // ===== OBSERVACIONES =====
    function toggleObs(header) {
        header.nextElementSibling.classList.toggle('open');
    }

    function guardarObservacion(estId) {
        var textarea = document.getElementById('obs-text-' + estId);
        var texto = textarea.value.trim();
        if (!texto) return;

        fetch('guardar_observacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ estudiante_id: estId, programa_modulo_id: MODULO_ID, observacion: texto })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                textarea.value = '';
                var hist = document.getElementById('obs-hist-' + estId);
                // Remove "sin observaciones" message
                var empty = hist.querySelector('p');
                if (empty) empty.remove();
                // Add new obs at top
                var div = document.createElement('div');
                div.className = 'obs-item';
                div.innerHTML = '<div class="obs-texto">' + escapeHtml(data.observacion.observacion) + '</div>' +
                    '<div class="obs-meta">' + escapeHtml(data.observacion.autor) + ' &middot; ' + formatDate(data.observacion.fecha) + '</div>';
                hist.insertBefore(div, hist.firstChild);
                // Update count
                var countEl = document.querySelector('[data-obs-count="' + estId + '"]');
                var current = parseInt(countEl.textContent) || 0;
                countEl.textContent = (current + 1) + ' observacion(es)';
                showToast('Observacion agregada', 'exito');
            } else {
                showToast('Error: ' + (data.error || 'Desconocido'), 'error');
            }
        })
        .catch(function() { showToast('Error de conexion', 'error'); });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        var d = new Date(dateStr);
        return d.toLocaleDateString('es-CO') + ' ' + d.toLocaleTimeString('es-CO', {hour:'2-digit', minute:'2-digit'});
    }

    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (document.querySelectorAll('#planilla-notas .nota-input.modified').length > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    </script>
    <script src="/intep/sesion.js"></script>
</body>
</html>
