<?php
// ============================================================
// INTEP Kids — Dashboard (Mapa de Aventuras) con sesión real
// Solo accesible para estudiantes de Primera Infancia
// ============================================================
require_once __DIR__ . '/../../config.php';

if (empty($_SESSION['usuario_id']) || empty($_SESSION['estudiante_id'])) {
    header('Location: /intep/login.php'); exit;
}
$est_id = (int)$_SESSION['estudiante_id'];

// Datos del estudiante + programa
$st = mysqli_prepare($conexion,
    "SELECT e.nombre, p.nombre AS programa
     FROM estudiantes e LEFT JOIN programas p ON p.id = e.programa_id
     WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $est_id);
mysqli_stmt_execute($st);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?? [];

$nombre           = $est['nombre'] ?? 'Amiguito';
$programa_nombre  = $est['programa'] ?? '';
$es_pi = stripos($programa_nombre, 'primera infancia') !== false
      || stripos($programa_nombre, 'preescolar') !== false;

// Si no es primera infancia, redirigir al portal
if (!$es_pi) {
    header('Location: /intep/dashboard.php'); exit;
}

// Progreso de módulos kids
$progreso = [];
$st2 = mysqli_prepare($conexion,
    "SELECT modulo_num, completado FROM ingles_cursos_progreso
     WHERE estudiante_id = ? AND nivel = 'kids'");
if ($st2) {
    mysqli_stmt_bind_param($st2, 'i', $est_id);
    mysqli_stmt_execute($st2);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st2), MYSQLI_ASSOC) as $r) {
        $progreso[(int)$r['modulo_num']] = (bool)$r['completado'];
    }
}

// Definición de los 4 módulos
$modulos = [
    ['num'=>1,'titulo'=>'Safari de Animales','icono'=>'🦁','bg'=>'bg-pink', 'url'=>'modulo1.html','activo'=>true],
    ['num'=>2,'titulo'=>'Fiesta de Colores',  'icono'=>'🎨','bg'=>'bg-yellow','url'=>'modulo2.html','activo'=>false],
    ['num'=>3,'titulo'=>'Números Mágicos',    'icono'=>'🔢','bg'=>'bg-blue', 'url'=>'modulo3.html','activo'=>false],
    ['num'=>4,'titulo'=>'Comida Deliciosa',   'icono'=>'🍎','bg'=>'bg-green','url'=>'modulo4.html','activo'=>false],
];
// Un módulo se desbloquea cuando el anterior está completo
$modulos[0]['activo'] = true; // el primero siempre activo
for ($i = 1; $i < count($modulos); $i++) {
    $modulos[$i]['activo'] = !empty($progreso[$modulos[$i-1]['num']]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INTEP Kids | Mi Mapa de Aventuras</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/intep/cursoingles/cursoinglespreescolar/index.css">
    <link rel="stylesheet" href="/intep/cursoingles/cursoinglespreescolar/dashboard.css">
    <link rel="icon" href="/intep/favicon/favicon.svg" type="image/svg+xml">
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <span class="logo-icon">🧸</span>
            <span class="logo-text">INTEP <span class="text-highlight">Kids</span></span>
        </div>
        <div class="user-profile">
            <span class="user-name">¡Hola, <?= htmlspecialchars(explode(' ', $nombre)[0]) ?>! 🌟</span>
            <div class="avatar">👦</div>
        </div>
        <a href="/intep/dashboard.php" class="btn-secondary" style="text-decoration:none;">Salir 🚪</a>
    </nav>

    <main class="adventure-map">
        <h1 class="map-title">Tu Mapa de <span class="text-gradient">Aventuras</span> 🗺️</h1>

        <div class="path-container">
            <?php foreach ($modulos as $m):
                $activo   = $m['activo'];
                $completado = !empty($progreso[$m['num']]);
                $stars    = $completado ? '⭐⭐⭐' : ($activo ? '⭐' : '🔒 Bloqueado');
                $clases   = 'level-node' . ($activo ? ' active' : ' locked');
                $onclick  = $activo ? "onclick=\"window.location.href='{$m['url']}'\"" : '';
            ?>
            <div class="<?= $clases ?>" <?= $onclick ?>>
                <div class="node-icon <?= $m['bg'] ?> <?= ($activo ? 'wobble-hover' : '') ?>">
                    <?= $m['icono'] ?>
                    <?php if ($completado): ?>
                        <span style="position:absolute;top:-8px;right:-8px;background:#10b981;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;">✓</span>
                    <?php endif; ?>
                </div>
                <div class="node-info">
                    <h3><?= htmlspecialchars($m['titulo']) ?></h3>
                    <div class="stars"><?= $stars ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
