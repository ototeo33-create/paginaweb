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

// Asegurar que la columna examen_aprobado exista (auto-migración)
mysqli_query($conexion,
    "ALTER TABLE ingles_cursos_progreso ADD COLUMN IF NOT EXISTS examen_aprobado TINYINT(1) NOT NULL DEFAULT 0");

// Progreso de módulos kids
$progreso = [];
$examenes = [];
$st2 = mysqli_prepare($conexion,
    "SELECT modulo_num, completado, examen_aprobado FROM ingles_cursos_progreso
     WHERE estudiante_id = ? AND nivel = 'kids'");
if ($st2) {
    mysqli_stmt_bind_param($st2, 'i', $est_id);
    mysqli_stmt_execute($st2);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st2), MYSQLI_ASSOC) as $r) {
        $modulo = (int)$r['modulo_num'];
        if ($modulo >= 1 && $modulo <= 4) {
            $progreso[$modulo] = (bool)$r['completado'];
            $examenes[$modulo] = (bool)$r['examen_aprobado'];
        }
    }
}

// Verificar examen final
$examen_final_aprobado = false;
$st3 = mysqli_prepare($conexion,
    "SELECT COUNT(*) as total FROM ingles_cursos_progreso
     WHERE estudiante_id = ? AND nivel = 'kids' AND modulo_num = 99 AND completado = 1");
if ($st3) {
    mysqli_stmt_bind_param($st3, 'i', $est_id);
    mysqli_stmt_execute($st3);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($st3));
    $examen_final_aprobado = $result['total'] > 0;
}

// Definición de los 4 módulos
$modulos = [
    ['num'=>1,'titulo'=>'Safari de Animales','icono'=>'🦁','bg'=>'bg-pink', 'url'=>'modulo1.html','examen'=>'examen1.html'],
    ['num'=>2,'titulo'=>'Fiesta de Colores',  'icono'=>'🎨','bg'=>'bg-yellow','url'=>'modulo2.html','examen'=>'examen2.html'],
    ['num'=>3,'titulo'=>'Números Mágicos',    'icono'=>'🔢','bg'=>'bg-blue', 'url'=>'modulo3.html','examen'=>'examen3.html'],
    ['num'=>4,'titulo'=>'Comida Deliciosa',   'icono'=>'🍎','bg'=>'bg-green','url'=>'modulo4.html','examen'=>'examen_final.html'],
];

// Un módulo se desbloquea cuando el anterior está completo
$modulos[0]['activo'] = true;
for ($i = 1; $i < count($modulos); $i++) {
    $prevMod = $modulos[$i-1]['num'];
    $modulos[$i]['activo'] = !empty($progreso[$prevMod]);
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
    <style>
        .section-title { font-family: 'Fredoka', sans-serif; font-size: 1.5rem; color: var(--text-dark); margin: 2rem 0 1rem; display: flex; align-items: center; gap: 10px; }
        .exam-section { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; }
        .exam-node { background: white; border-radius: 20px; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.3s; border: 2px solid #e5e7eb; }
        .exam-node:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .exam-node.locked { opacity: 0.5; cursor: not-allowed; }
        .exam-node.locked:hover { transform: none; }
        .exam-icon { font-size: 1.8rem; }
        .exam-info h4 { font-family: 'Fredoka', sans-serif; font-size: 1rem; margin: 0; color: var(--text-dark); }
        .exam-info p { font-size: 0.8rem; color: var(--text-light); margin: 0; }
        .exam-node.approved { border-color: #22c55e; background: #f0fdf4; }
        .cert-section { margin-top: 2rem; text-align: center; }
        .cert-btn { display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #FFD166, #fbbf24); color: var(--text-dark); padding: 15px 30px; border-radius: 20px; font-family: 'Fredoka', sans-serif; font-size: 1.2rem; font-weight: 700; text-decoration: none; box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4); transition: all 0.3s; }
        .cert-btn:hover { transform: translateY(-3px); }
    </style>
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

        <!-- Módulos -->
        <h2 class="section-title">📚 Los Módulos</h2>
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

        <!-- Exámenes - Solo aparecen después de completar el módulo -->
        <h2 class="section-title">📝 Exámenes</h2>
        <div class="exam-section">
            <?php foreach ($modulos as $m):
                $modulo_completado = !empty($progreso[$m['num']]);
                $examen_aprobado = isset($examenes[$m['num']]) && $examenes[$m['num']];
                $clases = 'exam-node';
                if (!$modulo_completado) {
                    $clases .= ' locked';
                } elseif ($examen_aprobado) {
                    $clases .= ' approved';
                }
                $onclick = $modulo_completado ? "onclick=\"window.location.href='{$m['examen']}'\"" : '';
                $icon = $examen_aprobado ? '✅' : '📝';
                $text = $examen_aprobado ? 'Aprobado' : 'Presentar';
            ?>
            <div class="<?= $clases ?>" <?= $onclick ?>>
                <span class="exam-icon"><?= $icon ?></span>
                <div class="exam-info">
                    <h4>Examen Módulo <?= $m['num'] ?></h4>
                    <p><?= $text ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Examen Final - Solo aparece después de aprobar los 4 exámenes -->
        <?php 
        $todos_examenes_aprobados = true;
        for ($i = 1; $i <= 4; $i++) {
            if (!isset($examenes[$i]) || !$examenes[$i]) {
                $todos_examenes_aprobados = false;
                break;
            }
        }
        if ($todos_examenes_aprobados && !$examen_final_aprobado): 
        ?>
        <h2 class="section-title">🎓 Examen Final</h2>
        <div class="exam-section">
            <div class="exam-node" onclick="window.location.href='examen_final.html'">
                <span class="exam-icon">🎓</span>
                <div class="exam-info">
                    <h4>Examen Final</h4>
                    <p>¡Presentar ahora!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Certificado - Solo aparece después de aprobar el examen final -->
        <?php if ($examen_final_aprobado): ?>
        <div class="cert-section">
            <a href="/intep/cursoingles/certificado.php?nivel=kids&modulo=final" class="cert-btn" target="_blank">
                🎓 Ver Mi Certificado
            </a>
        </div>
        <?php endif; ?>

    </main>
</body>
</html>