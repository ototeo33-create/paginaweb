<?php
require_once '../config.php';

// Protección por contraseña (sin requerir sesión)
$clave_migrar = 'Ngs123456789.';
$autenticado = false;
$msg_auth = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave_acceso'])) {
    if ($_POST['clave_acceso'] === $clave_migrar) {
        $autenticado = true;
    } else {
        $msg_auth = 'Contraseña incorrecta.';
    }
}

if (!$autenticado && !isset($_POST['ejecutar_migracion'])):
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración Cartera – INTEP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #F0FDF4; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-box { background: white; border-radius: 16px; padding: 2rem; width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #022C22; margin-bottom: 0.5rem; }
        p { color: #6B7280; font-size: 0.88rem; margin-bottom: 1.5rem; }
        input { width: 100%; padding: 0.8rem; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; margin-bottom: 1rem; }
        input:focus { border-color: #059669; outline: none; }
        button { width: 100%; padding: 0.8rem; background: #059669; color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; }
        .error { color: #EF4444; font-size: 0.85rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Migración de Cartera</h2>
        <p>Ingresa la contraseña para continuar</p>
        <?php if ($msg_auth): ?><div class="error"><?php echo $msg_auth; ?></div><?php endif; ?>
        <form method="POST">
            <input type="password" name="clave_acceso" placeholder="Contraseña" autofocus required>
            <button type="submit">Acceder</button>
        </form>
    </div>
</body>
</html>
<?php exit; endif;

// Si llegó aquí con POST de migración, verificar clave incluida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ejecutar_migracion'])) {
    if ($_POST['clave_hidden'] !== $clave_migrar) {
        header('Location: migrar_cartera.php');
        exit;
    }
}

$resultados = [];
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ejecutar_migracion'])) {

    // 1. Agregar columna num_cuotas si no existe
    $check_col = mysqli_query($conexion, "SHOW COLUMNS FROM conceptos_cobro LIKE 'num_cuotas'");
    if (mysqli_num_rows($check_col) == 0) {
        $r = mysqli_query($conexion, "ALTER TABLE conceptos_cobro ADD COLUMN num_cuotas INT NOT NULL DEFAULT 1 AFTER tipo");
        $resultados[] = $r ? '✅ Columna num_cuotas agregada' : '❌ Error agregando columna: ' . mysqli_error($conexion);
    } else {
        $resultados[] = '✅ Columna num_cuotas ya existe';
    }

    // 2. Limpiar datos de prueba
    mysqli_query($conexion, "DELETE FROM pagos");
    $resultados[] = '✅ Pagos eliminados (' . mysqli_affected_rows($conexion) . ' registros)';

    mysqli_query($conexion, "DELETE FROM cobros");
    $resultados[] = '✅ Cobros eliminados (' . mysqli_affected_rows($conexion) . ' registros)';

    mysqli_query($conexion, "DELETE FROM conceptos_cobro");
    $resultados[] = '✅ Conceptos anteriores eliminados (' . mysqli_affected_rows($conexion) . ' registros)';

    // 3. Insertar conceptos reales
    $conceptos = [
        [1, 'Mensualidad', 'Cuota mensual del programa técnico (10 meses)', 212000, 'mensualidad', 10],
        [2, 'Seminario Excel Intermedio', 'Seminario obligatorio adicional al programa', 320000, 'seminario', 1],
        [3, 'Derechos de Grado', 'Ceremonia de grado y celebración (PROM)', 450000, 'otro', 1],
        [4, 'Mensualidad Inglés', 'Cuota mensual del programa de inglés (4 meses por nivel)', 145000, 'mensualidad', 4],
    ];

    $stmt = mysqli_prepare($conexion, "INSERT INTO conceptos_cobro (id, nombre, descripcion, monto_base, tipo, num_cuotas, estado) VALUES (?, ?, ?, ?, ?, ?, 'activo')");
    foreach ($conceptos as $c) {
        mysqli_stmt_bind_param($stmt, 'issdsi', $c[0], $c[1], $c[2], $c[3], $c[4], $c[5]);
        $r = mysqli_stmt_execute($stmt);
        $resultados[] = $r ? "✅ Concepto: {$c[1]} - \${$c[3]} ({$c[5]} cuotas)" : "❌ Error: " . mysqli_error($conexion);
    }

    // 4. Agregar programas de inglés
    $progs = [
        ['Inglés A1', 'ING-A1'],
        ['Inglés A2', 'ING-A2'],
        ['Inglés B1', 'ING-B1'],
    ];
    foreach ($progs as $p) {
        $check = mysqli_prepare($conexion, "SELECT id FROM programas WHERE codigo = ?");
        mysqli_stmt_bind_param($check, 's', $p[1]);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        if (mysqli_num_rows($res) == 0) {
            $ins = mysqli_prepare($conexion, "INSERT INTO programas (nombre, codigo, estado) VALUES (?, ?, 'activo')");
            mysqli_stmt_bind_param($ins, 'ss', $p[0], $p[1]);
            $r = mysqli_stmt_execute($ins);
            $resultados[] = $r ? "✅ Programa: {$p[0]}" : "❌ Error: " . mysqli_error($conexion);
        } else {
            $resultados[] = "✅ Programa {$p[0]} ya existe";
        }
    }

    // Reset auto_increment
    mysqli_query($conexion, "ALTER TABLE cobros AUTO_INCREMENT = 1");
    mysqli_query($conexion, "ALTER TABLE pagos AUTO_INCREMENT = 1");
    $resultados[] = '✅ Auto-increment reiniciado';
}

// Mostrar estado actual
$conceptos_actuales = [];
$r = mysqli_query($conexion, "SELECT * FROM conceptos_cobro ORDER BY id");
if ($r) while ($row = mysqli_fetch_assoc($r)) $conceptos_actuales[] = $row;

$total_cobros = 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM cobros");
if ($r) $total_cobros = mysqli_fetch_assoc($r)['total'];

$total_pagos = 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM pagos");
if ($r) $total_pagos = mysqli_fetch_assoc($r)['total'];

$programas_actuales = [];
$r = mysqli_query($conexion, "SELECT * FROM programas ORDER BY nombre");
if ($r) while ($row = mysqli_fetch_assoc($r)) $programas_actuales[] = $row;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración Cartera – INTEP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #F0FDF4; padding: 2rem; color: #022C22; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .subtitle { color: #6B7280; font-size: 0.9rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .card-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th, td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #F0FDF4; font-weight: 700; font-size: 0.78rem; text-transform: uppercase; color: #059669; }
        .btn { display: inline-block; padding: 0.8rem 1.5rem; border: none; border-radius: 10px; font-weight: 700; font-size: 0.95rem; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #059669; color: white; }
        .btn-danger { background: #EF4444; color: white; }
        .btn-back { background: #F3F4F6; color: #374151; font-size: 0.88rem; padding: 0.6rem 1rem; margin-bottom: 1rem; }
        .resultado { padding: 0.5rem 0.8rem; margin: 0.3rem 0; border-radius: 8px; font-size: 0.88rem; background: #F0FDF4; }
        .resultado.error { background: #FEF2F2; }
        .stat { display: inline-block; padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; margin-right: 0.5rem; }
        .stat-verde { background: #ECFDF5; color: #059669; }
        .stat-azul { background: #DBEAFE; color: #1D4ED8; }
        .stat-gris { background: #F3F4F6; color: #6B7280; }
        .warning { background: #FFFBEB; border: 2px solid #F59E0B; border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .tag { font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700; text-transform: uppercase; }
        .tag-mensualidad { background: #DBEAFE; color: #1D4ED8; }
        .tag-seminario { background: #F3E8FF; color: #7C3AED; }
        .tag-otro { background: #F3F4F6; color: #6B7280; }
        .tag-matricula { background: #ECFDF5; color: #059669; }
    </style>
</head>
<body>
<div class="container">
    <a href="cartera.php" class="btn btn-back">← Volver a Cartera</a>
    <h1>Migración de Cartera</h1>
    <p class="subtitle">Reorganizar conceptos de cobro según estructura real del INTEP</p>

    <?php if (!empty($resultados)): ?>
    <div class="card">
        <div class="card-title">Resultado de la migración</div>
        <?php foreach ($resultados as $r): ?>
            <div class="resultado <?php echo strpos($r, '❌') !== false ? 'error' : ''; ?>"><?php echo $r; ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Estado actual -->
    <div class="card">
        <div class="card-title">Estado actual de la base de datos</div>
        <div style="margin-bottom:1rem;">
            <span class="stat stat-verde"><?php echo count($conceptos_actuales); ?> conceptos</span>
            <span class="stat stat-azul"><?php echo $total_cobros; ?> cobros</span>
            <span class="stat stat-gris"><?php echo $total_pagos; ?> pagos</span>
            <span class="stat stat-verde"><?php echo count($programas_actuales); ?> programas</span>
        </div>

        <?php if (!empty($conceptos_actuales)): ?>
        <h3 style="font-size:0.95rem;margin:1rem 0 0.5rem;">Conceptos de Cobro</h3>
        <table>
            <tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Monto</th><th>Cuotas</th><th>Total</th></tr>
            <?php foreach ($conceptos_actuales as $c):
                $cuotas = isset($c['num_cuotas']) ? (int)$c['num_cuotas'] : 1;
                $total = $c['monto_base'] * $cuotas;
                $tag_class = 'tag-' . $c['tipo'];
            ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($c['nombre']); ?></strong></td>
                <td><span class="tag <?php echo $tag_class; ?>"><?php echo $c['tipo']; ?></span></td>
                <td>$<?php echo number_format($c['monto_base'], 0, ',', '.'); ?></td>
                <td><?php echo $cuotas; ?></td>
                <td><strong>$<?php echo number_format($total, 0, ',', '.'); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if (!empty($programas_actuales)): ?>
        <h3 style="font-size:0.95rem;margin:1rem 0 0.5rem;">Programas</h3>
        <table>
            <tr><th>ID</th><th>Nombre</th><th>Código</th><th>Estado</th></tr>
            <?php foreach ($programas_actuales as $p): ?>
            <tr>
                <td><?php echo $p['id']; ?></td>
                <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                <td><?php echo htmlspecialchars($p['codigo']); ?></td>
                <td><?php echo $p['estado']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <!-- Ejecutar migración -->
    <div class="card">
        <div class="card-title">Nuevos conceptos a crear</div>
        <table>
            <tr><th>Nombre</th><th>Tipo</th><th>Monto/cuota</th><th>Cuotas</th><th>Total</th></tr>
            <tr><td><strong>Mensualidad</strong></td><td><span class="tag tag-mensualidad">mensualidad</span></td><td>$212.000</td><td>10</td><td><strong>$2.120.000</strong></td></tr>
            <tr><td><strong>Seminario Excel Intermedio</strong></td><td><span class="tag tag-seminario">seminario</span></td><td>$320.000</td><td>1</td><td><strong>$320.000</strong></td></tr>
            <tr><td><strong>Derechos de Grado</strong></td><td><span class="tag tag-otro">otro</span></td><td>$450.000</td><td>1</td><td><strong>$450.000</strong></td></tr>
            <tr><td><strong>Mensualidad Inglés</strong></td><td><span class="tag tag-mensualidad">mensualidad</span></td><td>$145.000</td><td>4</td><td><strong>$580.000</strong></td></tr>
        </table>
        <p style="font-size:0.82rem;color:#6B7280;margin-top:0.8rem;">+ Programas: Inglés A1, Inglés A2, Inglés B1</p>

        <div class="warning" style="margin-top:1.5rem;">
            ⚠️ <strong>Esta acción eliminará:</strong> todos los cobros, pagos y conceptos actuales. Los conceptos serán reemplazados por los nuevos. Los estudiantes y programas técnicos NO se tocan.
        </div>

        <form method="POST" onsubmit="return confirm('¿Estás seguro? Se eliminarán todos los cobros, pagos y conceptos actuales.');">
            <input type="hidden" name="ejecutar_migracion" value="1">
            <input type="hidden" name="clave_hidden" value="<?php echo htmlspecialchars($clave_migrar); ?>">
            <button type="submit" class="btn btn-danger" style="margin-top:0.5rem;">🔄 Ejecutar Migración</button>
        </form>
    </div>
</div>
</body>
</html>
