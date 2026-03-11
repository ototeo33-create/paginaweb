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

$mensaje = '';
$tipo_mensaje = '';

// ===== GUARDAR NOTAS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_notas'])) {
    $estudiante_id = (int)$_POST['estudiante_id'];
    $modulo_id = (int)$_POST['modulo_id'];
    
    $parcial1 = $_POST['parcial1'] !== '' ? (float)$_POST['parcial1'] : null;
    $parcial2 = $_POST['parcial2'] !== '' ? (float)$_POST['parcial2'] : null;
    $producto1 = $_POST['producto1'] !== '' ? (float)$_POST['producto1'] : null;
    $producto2 = $_POST['producto2'] !== '' ? (float)$_POST['producto2'] : null;
    $producto3 = $_POST['producto3'] !== '' ? (float)$_POST['producto3'] : null;
    $desempeno1 = $_POST['desempeno1'] !== '' ? (float)$_POST['desempeno1'] : null;
    $desempeno2 = $_POST['desempeno2'] !== '' ? (float)$_POST['desempeno2'] : null;
    $desempeno3 = $_POST['desempeno3'] !== '' ? (float)$_POST['desempeno3'] : null;

    // Calcular promedios
    $nota_conocimiento = null;
    if ($parcial1 !== null && $parcial2 !== null) {
        $nota_conocimiento = round(($parcial1 + $parcial2) / 2, 1);
    }

    $nota_producto = null;
    $prods = array_filter([$producto1, $producto2, $producto3], function($v) { return $v !== null; });
    if (count($prods) > 0) {
        $nota_producto = round(array_sum($prods) / count($prods), 1);
    }

    $nota_desempeno = null;
    $desps = array_filter([$desempeno1, $desempeno2, $desempeno3], function($v) { return $v !== null; });
    if (count($desps) > 0) {
        $nota_desempeno = round(array_sum($desps) / count($desps), 1);
    }

    // Nota final: Conocimiento 30% + Producto 30% + Desempeño 40%
    $nota_final = null;
    if ($nota_conocimiento !== null && $nota_producto !== null && $nota_desempeno !== null) {
        $nota_final = round(($nota_conocimiento * 0.30) + ($nota_producto * 0.30) + ($nota_desempeno * 0.40), 1);
    }

    $aprobado = ($nota_final !== null && $nota_final >= 3.5) ? 1 : 0;

    // Verificar si ya existe registro
    $check = mysqli_prepare($conexion, "SELECT id FROM notas WHERE estudiante_id = ? AND modulo_id = ?");
    mysqli_stmt_bind_param($check, 'ii', $estudiante_id, $modulo_id);
    mysqli_stmt_execute($check);
    $existe = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($existe) > 0) {
        // UPDATE
        $row = mysqli_fetch_assoc($existe);
        $sql = "UPDATE notas SET 
                    parcial1 = ?, parcial2 = ?, nota_conocimiento = ?,
                    producto1 = ?, producto2 = ?, producto3 = ?, nota_producto = ?,
                    desempeno1 = ?, desempeno2 = ?, desempeno3 = ?, nota_desempeno = ?,
                    nota_final = ?, aprobado = ?
                WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        $id_nota = $row['id'];
        mysqli_stmt_bind_param($stmt, 'ddddddddddddii',
            $parcial1, $parcial2, $nota_conocimiento,
            $producto1, $producto2, $producto3, $nota_producto,
            $desempeno1, $desempeno2, $desempeno3, $nota_desempeno,
            $nota_final, $aprobado, $id_nota
        );
    } else {
        // INSERT
        $sql = "INSERT INTO notas (estudiante_id, modulo_id, parcial1, parcial2, nota_conocimiento,
                    producto1, producto2, producto3, nota_producto,
                    desempeno1, desempeno2, desempeno3, nota_desempeno,
                    nota_final, aprobado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiddddddddddddi',
            $estudiante_id, $modulo_id,
            $parcial1, $parcial2, $nota_conocimiento,
            $producto1, $producto2, $producto3, $nota_producto,
            $desempeno1, $desempeno2, $desempeno3, $nota_desempeno,
            $nota_final, $aprobado
        );
    }

    if ($stmt && mysqli_stmt_execute($stmt)) {
        $mensaje = '✅ Notas guardadas correctamente.';
        $tipo_mensaje = 'exito';
    } else {
        $mensaje = '❌ Error al guardar: ' . mysqli_error($conexion);
        $tipo_mensaje = 'error';
    }
}

// ===== OBTENER MÓDULOS =====
$modulos = [];
$sql_mod = "SELECT m.id, m.nombre, m.bimestre, m.orden, mat.nombre as materia_nombre, p.nombre as programa_nombre, p.id as programa_id
            FROM modulos m
            JOIN materias mat ON m.materia_id = mat.id
            JOIN programas p ON mat.programa_id = p.id
            ORDER BY p.nombre, m.bimestre, m.orden";
$res_mod = mysqli_query($conexion, $sql_mod);
while ($row = mysqli_fetch_assoc($res_mod)) {
    $modulos[] = $row;
}

// ===== OBTENER ESTUDIANTES (si ya seleccionó módulo) =====
$estudiantes = [];
$modulo_seleccionado = null;
$notas_existentes = [];

if (isset($_GET['modulo_id']) && $_GET['modulo_id'] > 0) {
    $modulo_id_sel = (int)$_GET['modulo_id'];

    // Info del módulo
    $sql_info = "SELECT m.*, mat.nombre as materia_nombre, mat.programa_id, p.nombre as programa_nombre
                 FROM modulos m
                 JOIN materias mat ON m.materia_id = mat.id
                 JOIN programas p ON mat.programa_id = p.id
                 WHERE m.id = ?";
    $stmt_info = mysqli_prepare($conexion, $sql_info);
    mysqli_stmt_bind_param($stmt_info, 'i', $modulo_id_sel);
    mysqli_stmt_execute($stmt_info);
    $modulo_seleccionado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));

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

        // Notas existentes para este módulo
        $sql_notas = "SELECT * FROM notas WHERE modulo_id = ?";
        $stmt_notas = mysqli_prepare($conexion, $sql_notas);
        mysqli_stmt_bind_param($stmt_notas, 'i', $modulo_id_sel);
        mysqli_stmt_execute($stmt_notas);
        $res_notas = mysqli_stmt_get_result($stmt_notas);
        while ($row = mysqli_fetch_assoc($res_notas)) {
            $notas_existentes[$row['estudiante_id']] = $row;
        }
    }
}

// Estudiante seleccionado para editar
$est_seleccionado = null;
$nota_actual = null;
if (isset($_GET['estudiante_id']) && isset($_GET['modulo_id'])) {
    $est_id_sel = (int)$_GET['estudiante_id'];
    $mod_id_sel = (int)$_GET['modulo_id'];
    
    // Info estudiante
    $sql_e = "SELECT id, nombre, documento FROM estudiantes WHERE id = ?";
    $stmt_e = mysqli_prepare($conexion, $sql_e);
    mysqli_stmt_bind_param($stmt_e, 'i', $est_id_sel);
    mysqli_stmt_execute($stmt_e);
    $est_seleccionado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_e));

    // Nota existente
    if (isset($notas_existentes[$est_id_sel])) {
        $nota_actual = $notas_existentes[$est_id_sel];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar Notas – INTEP</title>
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

        .page-container { max-width: 1000px; margin: 2rem auto; padding: 0 1.5rem; }

        .page-title {
            font-size: 1.3rem; font-weight: 800; color: #022C22;
            margin-bottom: 1.5rem; padding-bottom: 0.8rem;
            border-bottom: 2px solid #ECFDF5;
            display: flex; align-items: center; gap: 0.5rem;
        }

        /* Alerta */
        .alerta {
            padding: 0.9rem 1.2rem; border-radius: 10px;
            margin-bottom: 1.5rem; font-size: 0.9rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .alerta.exito { background: #ECFDF5; color: #065F46; border-left: 4px solid #10B981; }
        .alerta.error { background: #FEF2F2; color: #991B1B; border-left: 4px solid #EF4444; }

        /* Selector de módulo */
        .selector-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        .selector-card h3 {
            font-size: 1rem; color: #059669; margin-bottom: 1rem;
        }
        .selector-card select {
            width: 100%; padding: 0.8rem 1rem;
            border: 2px solid rgba(16, 185, 129, 0.2); border-radius: 10px;
            font-size: 0.95rem; outline: none;
            transition: border-color 0.2s;
            background: rgba(255,255,255,0.8);
        }
        .selector-card select:focus { border-color: #10B981; }

        /* Info del módulo seleccionado */
        .modulo-info {
            background: linear-gradient(135deg, #064E3B, #059669);
            color: white; border-radius: 14px; padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 1rem;
        }
        .modulo-info h2 { font-size: 1.2rem; margin: 0; }
        .modulo-info .mod-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 1rem; border-radius: 20px;
            font-size: 0.85rem;
        }

        /* Lista de estudiantes */
        .tabla-estudiantes {
            background: white; border-radius: 14px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            box-shadow: 0 2px 8px rgba(5,150,105,0.06);
            margin-bottom: 1.5rem;
        }
        .tabla-estudiantes table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .tabla-estudiantes thead { background: #022C22; color: white; }
        .tabla-estudiantes th {
            padding: 0.9rem 1.2rem; text-align: left;
            font-size: 0.8rem; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tabla-estudiantes td {
            padding: 0.8rem 1.2rem; font-size: 0.9rem;
            border-bottom: 1px solid #D1FAE5;
        }
        .tabla-estudiantes tr:last-child td { border-bottom: none; }
        .tabla-estudiantes tr:hover { background: #F0FDF4; }

        .nota-badge {
            display: inline-block; padding: 0.2rem 0.7rem;
            border-radius: 20px; font-size: 0.8rem; font-weight: 600;
        }
        .nota-badge.alta { background: #ECFDF5; color: #065F46; }
        .nota-badge.media { background: #FFFBEB; color: #92400E; }
        .nota-badge.baja { background: #FEF2F2; color: #991B1B; }
        .nota-badge.pendiente { background: #ECFDF5; color: #059669; }

        .btn-calificar {
            padding: 0.4rem 1rem; border-radius: 8px;
            font-size: 0.82rem; font-weight: 600;
            text-decoration: none; transition: all 0.2s;
            display: inline-block;
            background: #ECFDF5; color: #059669;
        }
        .btn-calificar:hover {
            background: #059669; color: white;
        }

        /* Formulario de notas */
        .form-notas {
            background: white; border-radius: 14px; padding: 2rem;
            box-shadow: 0 2px 8px rgba(5,150,105,0.06);
            margin-bottom: 1.5rem;
        }
        .form-notas h3 {
            font-size: 1.1rem; color: #022C22; margin-bottom: 0.3rem;
        }
        .form-notas .est-doc {
            font-size: 0.85rem; color: #9CA3AF; margin-bottom: 1.5rem;
        }

        .evidencias-form {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem; margin-bottom: 1.5rem;
        }

        .ev-bloque {
            background: #F0FDF4; border-radius: 12px; padding: 1.2rem;
            border: 2px solid #ECFDF5;
        }
        .ev-bloque h4 {
            font-size: 0.85rem; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 1rem;
            padding-bottom: 0.5rem; border-bottom: 2px solid #D1FAE5;
        }
        .ev-bloque.conocimiento h4 { color: #3B82F6; border-color: #BFDBFE; }
        .ev-bloque.producto h4 { color: #D946A8; border-color: #F9A8D4; }
        .ev-bloque.desempeno h4 { color: #10B981; border-color: #6EE7B7; }

        .campo-nota {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 0.8rem;
        }
        .campo-nota label {
            font-size: 0.88rem; color: #4B5563;
        }
        .campo-nota input {
            width: 80px; padding: 0.5rem 0.7rem;
            border: 2px solid #D1FAE5; border-radius: 8px;
            font-size: 0.95rem; text-align: center;
            outline: none; transition: border-color 0.2s;
        }
        .campo-nota input:focus { border-color: #10B981; }

        .ev-resultado {
            display: flex; justify-content: space-between;
            align-items: center; padding-top: 0.8rem;
            margin-top: 0.5rem; border-top: 2px solid #D1FAE5;
            font-weight: 700;
        }
        .ev-resultado .auto-calc {
            font-size: 1.1rem; color: #059669;
        }

        /* Resultado final form */
        .resultado-final-form {
            background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
            border-radius: 12px; padding: 1.2rem 1.5rem;
            display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 1rem;
            margin-bottom: 1.5rem;
            border: 2px solid #A7F3D0;
        }
        .resultado-final-form .nota-grande {
            font-size: 2rem; font-weight: 800; color: #059669;
        }
        .resultado-final-form .formula {
            font-size: 0.82rem; color: #7C6B99;
        }

        /* Botones */
        .btn-guardar {
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; border: none; border-radius: 10px;
            font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-guardar:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(5,150,105,0.3);
        }

        .btn-cancelar {
            padding: 0.9rem 2rem;
            background: white; color: #6B7280;
            border: 2px solid #D1FAE5; border-radius: 10px;
            font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            text-decoration: none; display: inline-block;
        }
        .btn-cancelar:hover { border-color: #A7F3D0; color: #059669; }

        .form-actions {
            display: flex; gap: 1rem; align-items: center;
        }

        /* Sin estudiantes */
        .sin-datos {
            text-align: center; padding: 3rem; color: #9CA3AF;
        }
        .sin-datos .icono { font-size: 3rem; margin-bottom: 1rem; }

        /* Scroll hint for mobile tables */
        .scroll-hint {
            display: none;
            text-align: center;
            font-size: 0.78rem;
            color: #9CA3AF;
            padding: 0.4rem 0;
            margin-top: -0.5rem;
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .evidencias-form { grid-template-columns: 1fr; }
            .modulo-info { flex-direction: column; text-align: center; }
            .resultado-final-form { flex-direction: column; text-align: center; }
            .form-actions { flex-direction: column; }
            .btn-guardar, .btn-cancelar { width: 100%; text-align: center; }
            .page-container { padding: 0 0.8rem; }
            .tabla-estudiantes th { padding: 0.7rem 0.8rem; font-size: 0.75rem; white-space: nowrap; }
            .tabla-estudiantes td { padding: 0.6rem 0.8rem; font-size: 0.85rem; white-space: nowrap; }
            .scroll-hint { display: block; }
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

    <div class="dashboard-header">
        <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
        <span class="usuario-info">📝 Ingresar Notas</span>
        <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
    </div>

    <div class="page-container">

        <a href="../dashboard.php" class="btn-volver">← Volver al inicio</a>

        <div class="page-title">📝 Ingresar Notas</div>

        <?php if ($mensaje): ?>
            <div class="alerta <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- ===== PASO 1: Seleccionar Módulo ===== -->
        <div class="selector-card">
            <h3>📚 Seleccionar Módulo</h3>
            <select onchange="if(this.value) window.location='?modulo_id='+this.value">
                <option value="">— Selecciona un módulo —</option>
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
                        Bim. <?php echo $mod['bimestre']; ?> — <?php echo htmlspecialchars($mod['nombre']); ?> (<?php echo htmlspecialchars($mod['materia_nombre']); ?>)
                    </option>
                <?php endforeach; ?>
                <?php if ($prog_actual !== '') echo '</optgroup>'; ?>
            </select>
        </div>

        <?php if ($modulo_seleccionado): ?>

            <!-- Info del módulo -->
            <div class="modulo-info">
                <div>
                    <h2>📘 <?php echo htmlspecialchars($modulo_seleccionado['nombre']); ?></h2>
                    <p style="margin:0.3rem 0 0;opacity:0.8;font-size:0.9rem;">
                        <?php echo htmlspecialchars($modulo_seleccionado['materia_nombre']); ?> · 
                        <?php echo htmlspecialchars($modulo_seleccionado['programa_nombre']); ?>
                    </p>
                </div>
                <span class="mod-badge">Bimestre <?php echo $modulo_seleccionado['bimestre']; ?></span>
            </div>

            <?php if ($est_seleccionado): ?>

                <!-- ===== FORMULARIO DE NOTAS ===== -->
                <form method="POST" action="?modulo_id=<?php echo $modulo_id_sel; ?>" class="form-notas" id="form-notas">
                    <input type="hidden" name="guardar_notas" value="1">
                    <input type="hidden" name="estudiante_id" value="<?php echo $est_seleccionado['id']; ?>">
                    <input type="hidden" name="modulo_id" value="<?php echo $modulo_id_sel; ?>">

                    <h3>👤 <?php echo htmlspecialchars($est_seleccionado['nombre']); ?></h3>
                    <p class="est-doc">Doc: <?php echo htmlspecialchars($est_seleccionado['documento']); ?></p>

                    <div class="evidencias-form">
                        <!-- Conocimiento 30% -->
                        <div class="ev-bloque conocimiento">
                            <h4>📝 Conocimiento (30%)</h4>
                            <div class="campo-nota">
                                <label>Parcial 1 (sem.4)</label>
                                <input type="number" step="0.1" min="0" max="5" name="parcial1" class="nota-input"
                                    data-grupo="conocimiento"
                                    value="<?php echo $nota_actual['parcial1'] ?? ''; ?>">
                            </div>
                            <div class="campo-nota">
                                <label>Parcial 2 (sem.8)</label>
                                <input type="number" step="0.1" min="0" max="5" name="parcial2" class="nota-input"
                                    data-grupo="conocimiento"
                                    value="<?php echo $nota_actual['parcial2'] ?? ''; ?>">
                            </div>
                            <div class="ev-resultado">
                                <span>Promedio</span>
                                <span class="auto-calc" id="prom-conocimiento">—</span>
                            </div>
                        </div>

                        <!-- Producto 30% -->
                        <div class="ev-bloque producto">
                            <h4>📂 Producto (30%)</h4>
                            <div class="campo-nota">
                                <label>Trabajo 1</label>
                                <input type="number" step="0.1" min="0" max="5" name="producto1" class="nota-input"
                                    data-grupo="producto"
                                    value="<?php echo $nota_actual['producto1'] ?? ''; ?>">
                            </div>
                            <div class="campo-nota">
                                <label>Trabajo 2</label>
                                <input type="number" step="0.1" min="0" max="5" name="producto2" class="nota-input"
                                    data-grupo="producto"
                                    value="<?php echo $nota_actual['producto2'] ?? ''; ?>">
                            </div>
                            <div class="campo-nota">
                                <label>Trabajo 3</label>
                                <input type="number" step="0.1" min="0" max="5" name="producto3" class="nota-input"
                                    data-grupo="producto"
                                    value="<?php echo $nota_actual['producto3'] ?? ''; ?>">
                            </div>
                            <div class="ev-resultado">
                                <span>Promedio</span>
                                <span class="auto-calc" id="prom-producto">—</span>
                            </div>
                        </div>

                        <!-- Desempeño 40% -->
                        <div class="ev-bloque desempeno">
                            <h4>🔧 Desempeño (40%)</h4>
                            <div class="campo-nota">
                                <label>Taller 1</label>
                                <input type="number" step="0.1" min="0" max="5" name="desempeno1" class="nota-input"
                                    data-grupo="desempeno"
                                    value="<?php echo $nota_actual['desempeno1'] ?? ''; ?>">
                            </div>
                            <div class="campo-nota">
                                <label>Taller 2</label>
                                <input type="number" step="0.1" min="0" max="5" name="desempeno2" class="nota-input"
                                    data-grupo="desempeno"
                                    value="<?php echo $nota_actual['desempeno2'] ?? ''; ?>">
                            </div>
                            <div class="campo-nota">
                                <label>Taller 3</label>
                                <input type="number" step="0.1" min="0" max="5" name="desempeno3" class="nota-input"
                                    data-grupo="desempeno"
                                    value="<?php echo $nota_actual['desempeno3'] ?? ''; ?>">
                            </div>
                            <div class="ev-resultado">
                                <span>Promedio</span>
                                <span class="auto-calc" id="prom-desempeno">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Resultado final -->
                    <div class="resultado-final-form">
                        <div>
                            <span class="formula">Conocimiento (30%) + Producto (30%) + Desempeño (40%)</span>
                            <div style="margin-top:0.3rem;">
                                <span style="font-size:0.9rem;color:#7C6B99;">Nota Final:</span>
                                <span class="nota-grande" id="nota-final-calc">—</span>
                            </div>
                        </div>
                        <div id="estado-badge" style="font-size:1rem;">⏳ Pendiente</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-guardar">💾 Guardar Notas</button>
                        <a href="?modulo_id=<?php echo $modulo_id_sel; ?>" class="btn-cancelar">Cancelar</a>
                    </div>
                </form>

            <?php else: ?>

                <!-- ===== PASO 2: Lista de Estudiantes ===== -->
                <?php if (empty($estudiantes)): ?>
                    <div class="sin-datos">
                        <div class="icono">👥</div>
                        <p>No hay estudiantes activos en este programa.</p>
                    </div>
                <?php else: ?>
                    <div class="scroll-hint">👆 Desliza para ver más columnas</div>
                    <div class="tabla-estudiantes">
                        <table>
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th>Documento</th>
                                    <th>Nota Final</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $est): 
                                    $nota_est = $notas_existentes[$est['id']] ?? null;
                                    $nf = $nota_est['nota_final'] ?? null;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($est['nombre']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($est['documento']); ?></td>
                                    <td>
                                        <?php if ($nf !== null && $nf > 0): ?>
                                            <span class="nota-badge <?php echo $nf >= 3.5 ? 'alta' : ($nf >= 3.0 ? 'media' : 'baja'); ?>">
                                                <?php echo number_format($nf, 1); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="nota-badge pendiente">Sin nota</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($nf !== null && $nf > 0): ?>
                                            <?php echo $nf >= 3.5 ? '✅ Aprobado' : '❌ Reprobado'; ?>
                                        <?php else: ?>
                                            ⏳ Pendiente
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?modulo_id=<?php echo $modulo_id_sel; ?>&estudiante_id=<?php echo $est['id']; ?>"
                                           class="btn-calificar">
                                            <?php echo $nota_est ? '✏️ Editar' : '📝 Calificar'; ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        <?php else: ?>
            <div class="sin-datos">
                <div class="icono">📋</div>
                <p>Selecciona un módulo para ver los estudiantes y calificar.</p>
            </div>
        <?php endif; ?>

    </div>

    <script>
    // ===== CÁLCULO AUTOMÁTICO DE NOTAS =====
    function calcularPromedios() {
        var inputs = document.querySelectorAll('.nota-input');
        var grupos = { conocimiento: [], producto: [], desempeno: [] };

        inputs.forEach(function(input) {
            var grupo = input.getAttribute('data-grupo');
            var val = parseFloat(input.value);
            if (!isNaN(val) && val >= 0 && val <= 5) {
                grupos[grupo].push(val);
            }
        });

        // Promedios por grupo
        var promConoc = grupos.conocimiento.length > 0
            ? (grupos.conocimiento.reduce(function(a,b){return a+b}, 0) / grupos.conocimiento.length).toFixed(1)
            : null;
        var promProd = grupos.producto.length > 0
            ? (grupos.producto.reduce(function(a,b){return a+b}, 0) / grupos.producto.length).toFixed(1)
            : null;
        var promDesemp = grupos.desempeno.length > 0
            ? (grupos.desempeno.reduce(function(a,b){return a+b}, 0) / grupos.desempeno.length).toFixed(1)
            : null;

        document.getElementById('prom-conocimiento').textContent = promConoc !== null ? promConoc : '—';
        document.getElementById('prom-producto').textContent = promProd !== null ? promProd : '—';
        document.getElementById('prom-desempeno').textContent = promDesemp !== null ? promDesemp : '—';

        // Nota final
        var notaFinalEl = document.getElementById('nota-final-calc');
        var estadoBadge = document.getElementById('estado-badge');

        if (promConoc !== null && promProd !== null && promDesemp !== null) {
            var nf = (parseFloat(promConoc) * 0.30 + parseFloat(promProd) * 0.30 + parseFloat(promDesemp) * 0.40).toFixed(1);
            notaFinalEl.textContent = nf;

            if (parseFloat(nf) >= 3.5) {
                notaFinalEl.style.color = '#10B981';
                estadoBadge.textContent = '✅ Aprobado';
                estadoBadge.style.color = '#10B981';
            } else {
                notaFinalEl.style.color = '#EF4444';
                estadoBadge.textContent = '❌ Reprobado';
                estadoBadge.style.color = '#EF4444';
            }
        } else {
            notaFinalEl.textContent = '—';
            notaFinalEl.style.color = '#059669';
            estadoBadge.textContent = '⏳ Pendiente';
            estadoBadge.style.color = '#9CA3AF';
        }
    }

    // Escuchar cambios en todos los inputs
    var notaInputs = document.querySelectorAll('.nota-input');
    notaInputs.forEach(function(input) {
        input.addEventListener('input', calcularPromedios);
    });

    // Calcular al cargar si hay valores
    if (notaInputs.length > 0) calcularPromedios();

    // Auto-ocultar alerta
    var alerta = document.querySelector('.alerta');
    if (alerta) {
        setTimeout(function() {
            alerta.style.transition = 'opacity 0.5s';
            alerta.style.opacity = '0';
            setTimeout(function() { alerta.remove(); }, 500);
        }, 4000);
    }
    </script>
    <script src="/intep/sesion.js"></script>
</body>
</html>