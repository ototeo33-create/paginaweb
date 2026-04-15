<?php
// ============================================================
// INTEP Inglés — Dashboard A1 Beginner (PHP con sesión real)
// ============================================================
require_once __DIR__ . '/../config.php';

// Auth
if (empty($_SESSION['usuario_id']) || empty($_SESSION['estudiante_id'])) {
    header('Location: /intep/login.php'); exit;
}
$est_id = (int)$_SESSION['estudiante_id'];

// Datos del estudiante
$st = mysqli_prepare($conexion,
    "SELECT e.nombre, e.foto, p.nombre AS programa
     FROM estudiantes e LEFT JOIN programas p ON p.id = e.programa_id
     WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $est_id);
mysqli_stmt_execute($st);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?? [];

$nombre  = $est['nombre'] ?? 'Estudiante';
$inicial = strtoupper(mb_substr($nombre, 0, 1));

// Nivel y XP
$nivel_actual = 'A1'; $xp_total = 0;
$st2 = mysqli_prepare($conexion,
    "SELECT nivel_actual, xp_total FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1");
if ($st2) {
    mysqli_stmt_bind_param($st2, 'i', $est_id);
    mysqli_stmt_execute($st2);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));
    if ($row) { $nivel_actual = $row['nivel_actual']; $xp_total = (int)$row['xp_total']; }
}

// Progreso de módulos A1
$progreso = [];
$st3 = mysqli_prepare($conexion,
    "SELECT modulo_num, porcentaje, completado
     FROM ingles_cursos_progreso
     WHERE estudiante_id = ? AND nivel = 'A1'");
if ($st3) {
    mysqli_stmt_bind_param($st3, 'i', $est_id);
    mysqli_stmt_execute($st3);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st3), MYSQLI_ASSOC) as $r) {
        $progreso[(int)$r['modulo_num']] = ['pct' => (int)$r['porcentaje'], 'done' => (bool)$r['completado']];
    }
}

// Resultados de quizzes A1
$quiz_status = [];
$st4 = mysqli_prepare($conexion,
    "SELECT modulo_num, MAX(score) AS best_score, MAX(aprobado) AS aprobado
     FROM ingles_quiz_resultados
     WHERE estudiante_id = ? AND nivel = 'A1'
     GROUP BY modulo_num");
if ($st4) {
    mysqli_stmt_bind_param($st4, 'i', $est_id);
    mysqli_stmt_execute($st4);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st4), MYSQLI_ASSOC) as $r) {
        $quiz_status[(int)$r['modulo_num']] = [
            'score'    => (int)$r['best_score'],
            'aprobado' => (bool)$r['aprobado'],
        ];
    }
}
// ¿Están los 8 quizzes de módulo aprobados? → desbloquear quiz general
$quizzes_aprobados = 0;
for ($i = 1; $i <= 8; $i++) {
    if (!empty($quiz_status[$i]['aprobado'])) $quizzes_aprobados++;
}
$quiz_general_disponible = ($quizzes_aprobados >= 8);
$quiz_general_aprobado   = !empty($quiz_status[0]['aprobado']);

// Helper: renderiza una card de módulo
function modCard(int $num, string $titulo, string $desc, string $icono, string $url,
                 array $progreso, array $quiz_status, bool $required = false): string {
    $nivel  = 'A1';
    $p    = $progreso[$num] ?? ['pct' => 0, 'done' => false];
    $pct  = $p['pct'];
    $done = $p['done'];
    $qs   = $quiz_status[$num] ?? null;
    $border = $required ? ' style="border:2px solid var(--primary);"' : '';
    $color  = $done ? 'var(--success)' : ($pct > 0 ? 'var(--primary)' : 'var(--text-muted)');
    $label  = $done ? 'Completado' : ($pct > 0 ? 'En Progreso' : 'Sin comenzar');
    $bar    = $done ? '100%' : "$pct%";
    // Badge del quiz
    if ($qs && $qs['aprobado']) {
        $quizBadge = '<span style="background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#10b981;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;">✅ Quiz ' . $qs['score'] . '%</span>';
    } elseif ($qs) {
        $quizBadge = '<span style="background:rgba(239,68,68,0.12);border:1px solid #ef4444;color:#f87171;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;">❌ Quiz ' . $qs['score'] . '%</span>';
    } else {
        $quizBadge = '<span style="background:rgba(148,163,184,0.1);border:1px solid rgba(148,163,184,0.3);color:#94a3b8;padding:3px 10px;border-radius:20px;font-size:0.75rem;">📝 Sin quiz</span>';
    }
    $quizUrl = "/intep/cursoingles/quiz.php?nivel={$nivel}&modulo={$num}";
    return <<<HTML
        <div class="module-card-wrapper">
        <a href="{$url}" class="module-card"{$border}>
            <div class="module-header">
                <span class="module-num">Módulo {$num}</span>
                <span>{$icono}</span>
            </div>
            <h3>{$titulo}</h3>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-top:10px;">{$desc}</p>
            <div class="module-progress"><div class="progress-bar" style="width:{$bar};"></div></div>
            <p style="font-size:0.8rem;text-align:right;margin-top:5px;color:{$color};font-weight:bold;">{$label}</p>
        </a>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 4px 0;">
            {$quizBadge}
            <a href="{$quizUrl}" style="font-size:0.8rem;color:#a5b4fc;text-decoration:none;font-weight:600;padding:4px 10px;border-radius:8px;border:1px solid rgba(99,102,241,0.35);transition:background 0.2s;" onmouseover="this.style.background='rgba(99,102,241,0.15)'" onmouseout="this.style.background='transparent'">Examen →</a>
        </div>
        </div>
    HTML;
}

$modulos = [
    [1, 'Nice to meet you!', 'Aprende el verbo To Be, saludos y cómo presentarte como un profesional.', '⭐', 'modulo1.html'],
    [2, 'My World',          'Tu familia, tus pertenencias y el verbo "Have".', '🏠', 'modulo2.html'],
    [3, 'Daily Routines',    'Habla sobre tu día y domina el Presente Simple.', '⏰', 'lesson_rutinas.html', true],
    [4, 'I can do that!',    'Habla de tus habilidades, talentos y hobbies usando "Can".', '⭐', 'modulo4.html'],
    [5, 'City Life',         'Muévete por la ciudad usando "There is / There are".', '🏙️', 'modulo5.html'],
    [6, 'Shopping & Food',   'Compras, ropa y pedir correctamente en un restaurante.', '🛍️', 'modulo6.html'],
    [7, 'What are you doing?','Describe el momento exacto usando Presente Continuo.', '💬', 'modulo7.html'],
    [8, 'The Past Weekend',  'Habla sobre lo que hiciste ayer. Conoce a Was / Were.', '📅', 'modulo8.html'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard A1 | INTEP Inglés</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/intep/cursoingles/lesson.css">
    <link rel="icon" href="/intep/favicon/favicon.svg" type="image/svg+xml">
    <style>
        body { margin: 0; }
        .dashboard-layout { display:grid; grid-template-columns:280px 1fr; min-height:100vh; color:white; }
        .sidebar { background:rgba(15,23,42,0.6); backdrop-filter:blur(15px); border-right:1px solid rgba(255,255,255,0.1); padding:2rem; display:flex; flex-direction:column; gap:2rem; }
        .sidebar-logo img { height:45px; filter:drop-shadow(0 0 10px rgba(255,255,255,0.3)); }
        .profile-widget { display:flex; align-items:center; gap:15px; background:rgba(255,255,255,0.1); padding:15px; border-radius:15px; }
        .profile-avatar { width:50px; height:50px; background:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:bold; font-family:'Outfit',sans-serif; overflow:hidden; flex-shrink:0; }
        .profile-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
        .main-content { padding:3rem; background:transparent; }
        .modules-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:25px; margin-top:2rem; }
        .module-card { background:rgba(255,255,255,0.05); backdrop-filter:blur(10px); border-radius:20px; padding:25px; border:1px solid rgba(255,255,255,0.15); transition:all 0.3s; cursor:pointer; text-decoration:none; color:white; display:block; }
        .module-card:hover { transform:translateY(-5px); box-shadow:0 10px 25px rgba(0,0,0,0.3); border-color:var(--primary-light); background:rgba(255,255,255,0.1); }
        .module-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        .module-num { background:rgba(99,102,241,0.1); color:var(--primary-dark); padding:6px 12px; border-radius:10px; font-weight:600; font-size:0.9rem; }
        .module-progress { width:100%; background:rgba(255,255,255,0.1); height:8px; border-radius:10px; margin-top:20px; overflow:hidden; }
        .progress-bar { height:100%; background:linear-gradient(90deg,var(--primary),var(--secondary)); border-radius:10px; transition:width 0.8s ease; }
        .nav-link { color:#cbd5e1; text-decoration:none; padding:12px; display:block; transition:color 0.3s; border-radius:8px; }
        .nav-link:hover { color:white; background:rgba(255,255,255,0.05); }
        .nav-link.active { color:var(--primary-light); font-weight:bold; }
        .xp-chip { background:rgba(99,102,241,0.2); border:1px solid rgba(99,102,241,0.4); color:#a5b4fc; padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        .module-card-wrapper { display:flex; flex-direction:column; }
        .module-card-wrapper .module-card { flex:1; }
        @media(max-width:768px){ .dashboard-layout{grid-template-columns:1fr;} .sidebar{display:none;} }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="https://institutointep.edu.co/logointep.png" alt="INTEP">
        </div>

        <div class="profile-widget">
            <div class="profile-avatar">
                <?php if (!empty($est['foto'])): ?>
                    <img src="<?= htmlspecialchars($est['foto']) ?>" alt="Foto">
                <?php else: ?>
                    <?= htmlspecialchars($inicial) ?>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="font-size:1rem;margin:0;"><?= htmlspecialchars($nombre) ?></h3>
                <p style="font-size:0.8rem;color:#cbd5e1;margin:4px 0 0;">Nivel: <?= htmlspecialchars($nivel_actual) ?> · <span class="xp-chip"><?= $xp_total ?> XP</span></p>
            </div>
        </div>

        <nav style="display:flex;flex-direction:column;gap:6px;">
            <div style="background:rgba(255,255,255,0.1);border-radius:10px;padding:5px;">
                <span style="color:white;padding:12px;display:block;font-weight:bold;">📚 Mis Cursos</span>
                <div style="display:flex;flex-direction:column;padding-left:20px;gap:4px;margin-bottom:8px;">
                    <a href="/intep/cursoingles/dashboard.php" class="nav-link active" style="font-size:0.9rem;">🔸 A1 Beginner</a>
                    <a href="/intep/cursoingles/dashboard_a2.php" class="nav-link" style="font-size:0.9rem;">🔸 A2 Elementary</a>
                    <a href="/intep/cursoingles/dashboard_b1.php" class="nav-link" style="font-size:0.9rem;">🔸 B1 Intermediate</a>
                </div>
            </div>
            <div style="background:rgba(255,255,255,0.05);border-radius:10px;padding:5px;">
                <span style="color:#10b981;padding:12px;display:block;font-weight:bold;">🎮 Arcade</span>
                <div style="display:flex;flex-direction:column;padding-left:20px;gap:4px;margin-bottom:8px;">
                    <a href="/intep/cursoingles/minijuego.html" class="nav-link" style="font-size:0.9rem;">🔸 Constructor</a>
                    <a href="/intep/cursoingles/match.html" class="nav-link" style="font-size:0.9rem;">🔸 Parejas</a>
                    <a href="/intep/cursoingles/visual.html" class="nav-link" style="font-size:0.9rem;">🔸 Visual <span style="background:#ef4444;color:white;font-size:0.6rem;padding:2px 6px;border-radius:10px;font-weight:bold;margin-left:4px;">NUEVO</span></a>
                </div>
            </div>
            <a href="/intep/cursoingles/musica.html" class="nav-link" style="font-weight:bold;">🎧 Escucha y Aprende <span style="background:#8b5cf6;color:white;font-size:0.6rem;padding:2px 6px;border-radius:10px;font-weight:bold;margin-left:4px;">NUEVO</span></a>
            <a href="/intep/cursoingles/logros.html" class="nav-link">🏆 Logros</a>
            <a href="/intep/idiomas.php" class="nav-link">🤖 Ejercicios con IA</a>
            <a href="/intep/dashboard.php" class="nav-link" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;">← Portal INTEP</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <h1>Tu Plan de <span class="text-gradient">Estudios A1</span></h1>
        <p style="color:var(--text-muted);font-size:1.1rem;">Sigue tu progreso paso a paso. Recuerda: 15 minutos al día.</p>

        <div class="modules-grid" id="modulesGrid">
            <?php foreach ($modulos as $m):
                [$num, $titulo, $desc, $icono, $url] = $m;
                $required = isset($m[5]) ? $m[5] : ($num === 3);
                echo modCard($num, $titulo, $desc, $icono, $url, $progreso, $quiz_status, $required);
            endforeach; ?>
        </div>

        <!-- Quiz General -->
        <div style="margin-top:3rem;padding:2rem;border-radius:20px;border:2px solid <?= $quiz_general_disponible ? '#6366f1' : 'rgba(255,255,255,0.1)' ?>;background:<?= $quiz_general_disponible ? 'rgba(99,102,241,0.08)' : 'rgba(255,255,255,0.03)' ?>;">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div style="font-size:2.5rem;"><?= $quiz_general_aprobado ? '🏆' : ($quiz_general_disponible ? '📋' : '🔒') ?></div>
                <div style="flex:1;">
                    <h3 style="margin:0 0 4px;color:<?= $quiz_general_disponible ? 'white' : '#64748b' ?>;">Quiz General A1</h3>
                    <p style="margin:0;font-size:0.9rem;color:<?= $quiz_general_disponible ? '#94a3b8' : '#475569' ?>;">
                        <?php if ($quiz_general_aprobado): ?>
                            ✅ Aprobado con <?= $quiz_status[0]['score'] ?>% · ¡Nivel A1 dominado!
                        <?php elseif ($quiz_general_disponible): ?>
                            Todos los módulos completados. ¡Pon a prueba todo lo que aprendiste en A1!
                        <?php else: ?>
                            Aprueba los quizzes de los <?= 8 - $quizzes_aprobados ?> módulo(s) restante(s) para desbloquear.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($quiz_general_disponible): ?>
                    <a href="/intep/cursoingles/quiz.php?nivel=A1&modulo=0" style="padding:12px 28px;border-radius:12px;background:<?= $quiz_general_aprobado ? 'rgba(16,185,129,0.2)' : 'linear-gradient(135deg,#6366f1,#8b5cf6)' ?>;color:<?= $quiz_general_aprobado ? '#10b981' : 'white' ?>;font-weight:700;text-decoration:none;border:<?= $quiz_general_aprobado ? '1px solid #10b981' : 'none' ?>;">
                        <?= $quiz_general_aprobado ? '🔄 Repetir' : '🚀 Comenzar' ?>
                    </a>
                <?php else: ?>
                    <span style="padding:12px 28px;border-radius:12px;background:rgba(255,255,255,0.05);color:#475569;font-weight:700;"><?= $quizzes_aprobados ?>/8 ✓</span>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="/intep/cursoingles/ufo.js"></script>
<script src="/intep/cursoingles/universe_bg.js"></script>
</body>
</html>
