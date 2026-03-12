<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Diagnóstico Encoding</title>
    <style>
        body { font-family: monospace; padding: 2rem; background: #f5f5f5; }
        .section { background: white; padding: 1.5rem; margin: 1rem 0; border-radius: 8px; border: 1px solid #ddd; }
        h2 { color: #059669; margin-top: 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: left; font-size: 0.85rem; }
        th { background: #f0f0f0; }
        .ok { color: green; font-weight: bold; }
        .bad { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Encoding — INTEP</h1>

    <div class="section">
        <h2>1. Variables de charset MySQL</h2>
        <table>
            <tr><th>Variable</th><th>Valor</th><th>Estado</th></tr>
            <?php
            $res = mysqli_query($conexion, "SHOW VARIABLES LIKE 'character_set%'");
            while ($row = mysqli_fetch_assoc($res)) {
                $ok = in_array($row['Value'], ['utf8mb4', 'utf8', 'binary']);
                echo "<tr><td>{$row['Variable_name']}</td><td>{$row['Value']}</td>";
                echo "<td class='" . ($ok ? 'ok' : 'bad') . "'>" . ($ok ? '✅' : '❌ PROBLEMA') . "</td></tr>";
            }
            ?>
        </table>
    </div>

    <div class="section">
        <h2>2. Collation MySQL</h2>
        <table>
            <tr><th>Variable</th><th>Valor</th></tr>
            <?php
            $res = mysqli_query($conexion, "SHOW VARIABLES LIKE 'collation%'");
            while ($row = mysqli_fetch_assoc($res)) {
                echo "<tr><td>{$row['Variable_name']}</td><td>{$row['Value']}</td></tr>";
            }
            ?>
        </table>
    </div>

    <div class="section">
        <h2>3. HTTP Headers enviados</h2>
        <table>
            <tr><th>Header</th><th>Valor</th></tr>
            <?php
            foreach (headers_list() as $h) {
                echo "<tr><td colspan='2'>$h</td></tr>";
            }
            ?>
        </table>
        <p><strong>PHP default_charset:</strong> <?php echo ini_get('default_charset'); ?></p>
        <p><strong>mb_internal_encoding:</strong> <?php echo mb_internal_encoding(); ?></p>
    </div>

    <div class="section">
        <h2>4. Primeros 10 estudiantes — Análisis de bytes</h2>
        <?php
        $res = mysqli_query($conexion, "SELECT id, nombre, HEX(nombre) as hex_nombre FROM estudiantes ORDER BY id LIMIT 10");
        if (mysqli_num_rows($res) === 0) {
            echo "<p class='bad'>⚠️ No hay estudiantes en la base de datos.</p>";
        } else {
        ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Nombre (raw)</th>
                <th>HEX primeros bytes</th>
                <th>utf8_decode()</th>
                <th>mb_convert latin1→utf8</th>
                <th>mb_convert utf8→latin1</th>
                <th>Doble decode</th>
            </tr>
            <?php while ($row = mysqli_fetch_assoc($res)): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td style="font-size:0.7rem; word-break:break-all;"><?php echo substr($row['hex_nombre'], 0, 60) . '...'; ?></td>
                <td><?php echo htmlspecialchars(@utf8_decode($row['nombre'])); ?></td>
                <td><?php echo htmlspecialchars(@mb_convert_encoding($row['nombre'], 'UTF-8', 'ISO-8859-1')); ?></td>
                <td><?php echo htmlspecialchars(@mb_convert_encoding($row['nombre'], 'ISO-8859-1', 'UTF-8')); ?></td>
                <td><?php
                    $decoded = @mb_convert_encoding($row['nombre'], 'ISO-8859-1', 'UTF-8');
                    echo htmlspecialchars(@mb_convert_encoding($decoded, 'ISO-8859-1', 'UTF-8'));
                ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php } ?>
    </div>

    <div class="section">
        <h2>5. Test directo de caracteres</h2>
        <p>Si esta línea se ve bien, el encoding HTML→navegador funciona:</p>
        <p style="font-size:1.2rem; font-weight:bold;">
            á é í ó ú ñ Á É Í Ó Ú Ñ — José María Andrés Beltrán Técnico
        </p>
    </div>

    <div class="section">
        <h2>6. Tabla programas (nombres)</h2>
        <table>
            <tr><th>ID</th><th>Nombre (raw)</th><th>HEX primeros 40</th><th>utf8→latin1</th></tr>
            <?php
            $res = mysqli_query($conexion, "SELECT id, nombre, HEX(nombre) as hx FROM programas LIMIT 10");
            while ($row = mysqli_fetch_assoc($res)):
            ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td style="font-size:0.7rem;"><?php echo substr($row['hx'], 0, 50); ?></td>
                <td><?php echo htmlspecialchars(@mb_convert_encoding($row['nombre'], 'ISO-8859-1', 'UTF-8')); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <p><a href="index.php">← Volver al panel</a></p>
</body>
</html>
