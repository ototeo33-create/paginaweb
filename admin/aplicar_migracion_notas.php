<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$ejecutar = isset($_GET['run']) && $_GET['run'] === '1';
$resultados = [];

function correr(&$resultados, $conexion, $nombre, $sql) {
    $ok = mysqli_query($conexion, $sql);
    $resultados[] = [
        'paso'  => $nombre,
        'sql'   => $sql,
        'ok'    => (bool)$ok,
        'error' => $ok ? null : mysqli_error($conexion),
        'rows'  => $ok ? mysqli_affected_rows($conexion) : 0,
    ];
}

function existeIndice($conexion, $tabla, $nombre) {
    $sql = "SELECT COUNT(*) c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '" . mysqli_real_escape_string($conexion, $tabla) . "'
              AND INDEX_NAME = '" . mysqli_real_escape_string($conexion, $nombre) . "'";
    $r = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($r);
    return ((int)$row['c']) > 0;
}

function columnaNullable($conexion, $tabla, $col) {
    $sql = "SELECT IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '" . mysqli_real_escape_string($conexion, $tabla) . "'
              AND COLUMN_NAME = '" . mysqli_real_escape_string($conexion, $col) . "'";
    $r = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($r);
    return $row && $row['IS_NULLABLE'] === 'YES';
}

if ($ejecutar) {
    // ----- NOTAS -----
    correr($resultados, $conexion, 'Limpiar duplicados notas',
        "DELETE n1 FROM notas n1
         INNER JOIN notas n2
           ON n1.estudiante_id = n2.estudiante_id
          AND n1.programa_modulo_id = n2.programa_modulo_id
          AND n1.id > n2.id
         WHERE n1.programa_modulo_id IS NOT NULL");

    if (existeIndice($conexion, 'notas', 'unico_nota')) {
        correr($resultados, $conexion, 'DROP unico_nota (legado)',
            "ALTER TABLE notas DROP INDEX unico_nota");
    } else {
        $resultados[] = ['paso' => 'DROP unico_nota (legado)', 'sql' => '-', 'ok' => true, 'error' => 'no existe (skip)', 'rows' => 0];
    }

    if (!existeIndice($conexion, 'notas', 'uk_notas_pm')) {
        correr($resultados, $conexion, 'ADD UNIQUE uk_notas_pm',
            "ALTER TABLE notas ADD UNIQUE KEY uk_notas_pm (estudiante_id, programa_modulo_id)");
    } else {
        $resultados[] = ['paso' => 'ADD UNIQUE uk_notas_pm', 'sql' => '-', 'ok' => true, 'error' => 'ya existe (skip)', 'rows' => 0];
    }

    if (!existeIndice($conexion, 'notas', 'idx_notas_pm')) {
        correr($resultados, $conexion, 'ADD INDEX idx_notas_pm',
            "ALTER TABLE notas ADD KEY idx_notas_pm (programa_modulo_id)");
    } else {
        $resultados[] = ['paso' => 'ADD INDEX idx_notas_pm', 'sql' => '-', 'ok' => true, 'error' => 'ya existe (skip)', 'rows' => 0];
    }

    // ----- ASISTENCIAS -----
    correr($resultados, $conexion, 'Limpiar duplicados asistencias',
        "DELETE a1 FROM asistencias a1
         INNER JOIN asistencias a2
           ON a1.estudiante_id = a2.estudiante_id
          AND a1.programa_modulo_id = a2.programa_modulo_id
          AND a1.fecha = a2.fecha
          AND a1.id > a2.id
         WHERE a1.programa_modulo_id IS NOT NULL");

    if (existeIndice($conexion, 'asistencias', 'unique_asistencia')) {
        correr($resultados, $conexion, 'DROP unique_asistencia (legado)',
            "ALTER TABLE asistencias DROP INDEX unique_asistencia");
    } else {
        $resultados[] = ['paso' => 'DROP unique_asistencia (legado)', 'sql' => '-', 'ok' => true, 'error' => 'no existe (skip)', 'rows' => 0];
    }

    if (!existeIndice($conexion, 'asistencias', 'uk_asist_pm')) {
        correr($resultados, $conexion, 'ADD UNIQUE uk_asist_pm',
            "ALTER TABLE asistencias ADD UNIQUE KEY uk_asist_pm (estudiante_id, programa_modulo_id, fecha)");
    } else {
        $resultados[] = ['paso' => 'ADD UNIQUE uk_asist_pm', 'sql' => '-', 'ok' => true, 'error' => 'ya existe (skip)', 'rows' => 0];
    }

    if (!existeIndice($conexion, 'asistencias', 'idx_asist_pm_bim')) {
        correr($resultados, $conexion, 'ADD INDEX idx_asist_pm_bim',
            "ALTER TABLE asistencias ADD KEY idx_asist_pm_bim (programa_modulo_id, bimestre_id)");
    } else {
        $resultados[] = ['paso' => 'ADD INDEX idx_asist_pm_bim', 'sql' => '-', 'ok' => true, 'error' => 'ya existe (skip)', 'rows' => 0];
    }

    // ----- PROGRAMA_MODULO -----
    if (!columnaNullable($conexion, 'programa_modulo', 'programa_id')) {
        correr($resultados, $conexion, 'MODIFY programa_id NULLABLE',
            "ALTER TABLE programa_modulo MODIFY programa_id INT(11) NULL");
    } else {
        $resultados[] = ['paso' => 'MODIFY programa_id NULLABLE', 'sql' => '-', 'ok' => true, 'error' => 'ya nullable (skip)', 'rows' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migracion BD - INTEP</title>
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        body { background:#F0FDF4; padding:2rem; font-family:system-ui,sans-serif; }
        .container { max-width:900px; margin:auto; background:white; padding:2rem; border-radius:14px; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
        h1 { color:#064E3B; margin-bottom:1rem; }
        .alert { padding:1rem; border-radius:8px; margin:1rem 0; }
        .alert.warn { background:#FEF3C7; color:#92400E; border-left:4px solid #F59E0B; }
        .alert.info { background:#DBEAFE; color:#1E40AF; border-left:4px solid #3B82F6; }
        .btn { display:inline-block; padding:0.7rem 1.4rem; background:#059669; color:white; border-radius:8px; text-decoration:none; font-weight:700; }
        .btn:hover { background:#047857; }
        table { width:100%; border-collapse:collapse; margin-top:1rem; }
        th, td { padding:0.6rem; text-align:left; border-bottom:1px solid #E5E7EB; font-size:0.88rem; }
        th { background:#064E3B; color:white; }
        tr.ok td { background:#F0FDF4; }
        tr.err td { background:#FEF2F2; color:#991B1B; }
        code { background:#F3F4F6; padding:0.2rem 0.4rem; border-radius:4px; font-size:0.82rem; }
    </style>
</head>
<body>
<div class="container">
    <h1>Migracion BD - Notas y Asistencias</h1>
    <p><a href="../dashboard.php">&larr; Volver</a></p>

    <?php if (!$ejecutar): ?>
        <div class="alert warn">
            <strong>Atencion:</strong> Esta migracion ajusta los indices de las tablas <code>notas</code>,
            <code>asistencias</code> y la nullabilidad de <code>programa_modulo.programa_id</code>.
            Limpia duplicados antes de aplicar UNIQUE KEYs. <strong>Es idempotente</strong> (puedes correrla varias veces).
        </div>
        <a class="btn" href="?run=1">Aplicar migracion ahora</a>
    <?php else: ?>
        <div class="alert info">Resultados de la migracion:</div>
        <table>
            <thead>
                <tr><th>Paso</th><th>Resultado</th><th>Filas</th><th>Detalle</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultados as $r): ?>
                <tr class="<?php echo $r['ok'] ? 'ok' : 'err'; ?>">
                    <td><?php echo htmlspecialchars($r['paso']); ?></td>
                    <td><?php echo $r['ok'] ? 'OK' : 'ERROR'; ?></td>
                    <td><?php echo (int)$r['rows']; ?></td>
                    <td><?php echo htmlspecialchars($r['error'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:1.5rem;">
            <a class="btn" href="../dashboard.php">Volver al dashboard</a>
            <a class="btn" href="?run=1" style="background:#6B7280;">Re-ejecutar</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
