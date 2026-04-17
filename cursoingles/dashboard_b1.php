<?php
require_once __DIR__ . '/../config.php';
$es_admin_preview = !empty($_SESSION['admin_preview']) && ($_SESSION['usuario_rol'] ?? '') === 'admin';
if (empty($_SESSION['usuario_id']) || (empty($_SESSION['estudiante_id']) && !$es_admin_preview)) {
    header('Location: /intep/login.php'); exit;
}
$est_id = $es_admin_preview ? 0 : (int)$_SESSION['estudiante_id'];

$st = mysqli_prepare($conexion, "SELECT e.nombre, e.foto FROM estudiantes e WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $est_id);
mysqli_stmt_execute($st);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?? [];
$nombre  = $est['nombre'] ?? 'Estudiante';
$inicial = strtoupper(mb_substr($nombre, 0, 1));

$nivel_actual = 'B1'; $xp_total = 0;
$st2 = mysqli_prepare($conexion,
    "SELECT nivel_actual, xp_total FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1");
if ($st2) {
    mysqli_stmt_bind_param($st2, 'i', $est_id);
    mysqli_stmt_execute($st2);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));
    if ($row) { $nivel_actual = $row['nivel_actual']; $xp_total = (int)$row['xp_total']; }
}

$progreso = [];
$st3 = mysqli_prepare($conexion,
    "SELECT modulo_num, porcentaje, completado FROM ingles_cursos_progreso
     WHERE estudiante_id = ? AND nivel = 'B1'");
if ($st3) {
    mysqli_stmt_bind_param($st3, 'i', $est_id);
    mysqli_stmt_execute($st3);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st3), MYSQLI_ASSOC) as $r) {
        $progreso[(int)$r['modulo_num']] = ['pct' => (int)$r['porcentaje'], 'done' => (bool)$r['completado']];
    }
}

// Resultados de quizzes B1
$quiz_status = [];
$st4 = mysqli_prepare($conexion,
    "SELECT modulo_num, MAX(score) AS best_score, MAX(aprobado) AS aprobado
     FROM ingles_quiz_resultados
     WHERE estudiante_id = ? AND nivel = 'B1'
     GROUP BY modulo_num");
if ($st4) {
    mysqli_stmt_bind_param($st4, 'i', $est_id);
    mysqli_stmt_execute($st4);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st4), MYSQLI_ASSOC) as $r) {
        $quiz_status[(int)$r['modulo_num']] = ['score' => (int)$r['best_score'], 'aprobado' => (bool)$r['aprobado']];
    }
}
$quizzes_aprobados = 0;
for ($i = 1; $i <= 8; $i++) { if (!empty($quiz_status[$i]['aprobado'])) $quizzes_aprobados++; }
$quiz_general_disponible = ($quizzes_aprobados >= 8);
$quiz_general_aprobado   = !empty($quiz_status[0]['aprobado']);

$modulos = [
    [1,'Time Continues',       'Present Perfect Continuous: acciones que siguen.',          '⏳','modulo1_b1.html'],
    [2,'Wish & Regret',        'Condicionales mixtos y expresar deseos con "wish".',         '💫','modulo2_b1.html'],
    [3,'Passive Voice',        'La voz pasiva: de "I did it" a "It was done".',              '🔄','modulo3_b1.html'],
    [4,'Reported Speech',      'Reportar lo que dijo alguien: "She said that...".',          '💬','modulo4_b1.html'],
    [5,'Linking Ideas',        'Conectores avanzados: although, despite, whereas...',        '🔗','modulo5_b1.html'],
    [6,'Formal Writing',       'Cartas formales, emails y vocabulario corporativo.',         '✉️','modulo6_b1.html'],
    [7,'Debating',             'Argumentar, persuadir y defender un punto de vista.',        '🎯','modulo7_b1.html'],
    [8,'Mixed Tenses',         'Consolidación: todos los tiempos en contexto real.',         '📋','modulo8_b1.html'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard B1 | INTEP Inglés</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/intep/cursoingles/lesson.css">
    <link rel="icon" href="/intep/favicon/favicon.svg" type="image/svg+xml">
    <style>
        body{margin:0;}
        .dashboard-layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh;color:white;}
        .sidebar{background:rgba(15,23,42,0.6);backdrop-filter:blur(15px);border-right:1px solid rgba(255,255,255,0.1);padding:2rem;display:flex;flex-direction:column;gap:2rem;}
        .profile-widget{display:flex;align-items:center;gap:15px;background:rgba(255,255,255,0.1);padding:15px;border-radius:15px;}
        .profile-avatar{width:50px;height:50px;background:#eab308;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:bold;font-family:'Outfit',sans-serif;color:#1a1a1a;overflow:hidden;flex-shrink:0;}
        .profile-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
        .main-content{padding:3rem;}
        .modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:25px;margin-top:2rem;}
        .module-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border-radius:20px;padding:25px;border:1px solid rgba(255,255,255,0.15);transition:all 0.3s;text-decoration:none;color:white;display:block;}
        .module-card:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(0,0,0,0.3);border-color:#fde047;background:rgba(255,255,255,0.1);}
        .module-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
        .module-progress{width:100%;background:rgba(255,255,255,0.1);height:8px;border-radius:10px;margin-top:20px;overflow:hidden;}
        .progress-bar{height:100%;background:linear-gradient(90deg,#eab308,#f59e0b);border-radius:10px;transition:width 0.8s ease;}
        .nav-link{color:#cbd5e1;text-decoration:none;padding:12px;display:block;transition:color 0.3s;border-radius:8px;}
        .nav-link:hover{color:white;background:rgba(255,255,255,0.05);}
        .nav-link.active{color:#fde047;font-weight:bold;}
        @media(max-width:768px){.dashboard-layout{grid-template-columns:1fr;}.sidebar{display:none;}}
    </style>
</head>
<body>
<div class="dashboard-layout">
    <aside class="sidebar">
        <div><img src="https://institutointep.edu.co/logointep.png" alt="INTEP" style="height:45px;filter:drop-shadow(0 0 10px rgba(255,255,255,0.3));"></div>
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
                <p style="font-size:0.8rem;color:#cbd5e1;margin:4px 0 0;">Nivel: <?= htmlspecialchars($nivel_actual) ?> · <span style="background:rgba(234,179,8,0.2);border:1px solid rgba(234,179,8,0.4);color:#fde047;padding:2px 8px;border-radius:10px;font-size:0.75rem;"><?= $xp_total ?> XP</span></p>
            </div>
        </div>
        <nav style="display:flex;flex-direction:column;gap:6px;">
            <div style="background:rgba(255,255,255,0.1);border-radius:10px;padding:5px;">
                <span style="color:white;padding:12px;display:block;font-weight:bold;">📚 Mis Cursos</span>
                <div style="display:flex;flex-direction:column;padding-left:20px;gap:4px;margin-bottom:8px;">
                    <a href="/intep/cursoingles/dashboard.php" class="nav-link" style="font-size:0.9rem;">🔸 A1 Beginner</a>
                    <a href="/intep/cursoingles/dashboard_a2.php" class="nav-link" style="font-size:0.9rem;">🔸 A2 Elementary</a>
                    <a href="/intep/cursoingles/dashboard_b1.php" class="nav-link active" style="font-size:0.9rem;">🔸 B1 Intermediate</a>
                </div>
            </div>
            <a href="/intep/cursoingles/minijuego.html" class="nav-link">🎮 Constructor</a>
            <a href="/intep/cursoingles/musica.html" class="nav-link">🎧 Escucha y Aprende</a>
            <a href="/intep/dashboard.php" class="nav-link" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;">← Portal INTEP</a>
        </nav>
    </aside>
    <main class="main-content">
        <h1>Tu Plan de <span style="background:linear-gradient(to right,#eab308,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Estudios B1</span></h1>
        <p style="color:var(--text-muted);font-size:1.1rem;">Nivel Intermediate. Gramática avanzada y comunicación fluida.</p>
        <div class="modules-grid">
            <?php foreach ($modulos as [$num,$titulo,$desc,$icono,$url]):
                $pct  = $progreso[$num]['pct']  ?? 0;
                $done = $progreso[$num]['done'] ?? false;
                $qs   = $quiz_status[$num] ?? null;
                $color  = $done ? 'var(--success)' : ($pct>0 ? '#eab308' : 'var(--text-muted)');
                $label  = $done ? 'Completado' : ($pct>0 ? 'En Progreso' : 'Sin comenzar');
                $bar    = $done ? '100%' : "{$pct}%";
                if ($qs && $qs['aprobado']) {
                    $quizBadge = '<span style="background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#10b981;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;">✅ Quiz ' . $qs['score'] . '%</span>';
                } elseif ($qs) {
                    $quizBadge = '<span style="background:rgba(239,68,68,0.12);border:1px solid #ef4444;color:#f87171;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;">❌ Quiz ' . $qs['score'] . '%</span>';
                } else {
                    $quizBadge = '<span style="background:rgba(148,163,184,0.1);border:1px solid rgba(148,163,184,0.3);color:#94a3b8;padding:3px 10px;border-radius:20px;font-size:0.75rem;">📝 Sin quiz</span>';
                }
                $quizUrl = "/intep/cursoingles/quiz.php?nivel=B1&modulo={$num}";
            ?>
                <div style="display:flex;flex-direction:column;">
                <a href="<?= $url ?>" class="module-card">
                    <div class="module-header">
                        <span style="background:rgba(234,179,8,0.1);color:#fde047;padding:6px 12px;border-radius:10px;font-weight:600;font-size:0.9rem;">Módulo <?= $num ?></span>
                        <span><?= $icono ?></span>
                    </div>
                    <h3><?= $titulo ?></h3>
                    <p style="color:var(--text-muted);font-size:0.9rem;margin-top:10px;"><?= $desc ?></p>
                    <div class="module-progress"><div class="progress-bar" style="width:<?= $bar ?>;"></div></div>
                    <p style="font-size:0.8rem;text-align:right;margin-top:5px;color:<?= $color ?>;font-weight:bold;"><?= $label ?></p>
                </a>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 4px 0;">
                    <?= $quizBadge ?>
                    <a href="<?= $quizUrl ?>" style="font-size:0.8rem;color:#fde047;text-decoration:none;font-weight:600;padding:4px 10px;border-radius:8px;border:1px solid rgba(234,179,8,0.35);transition:background 0.2s;" onmouseover="this.style.background='rgba(234,179,8,0.15)'" onmouseout="this.style.background='transparent'">Examen →</a>
                </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Quiz General B1 -->
        <div style="margin-top:3rem;padding:2rem;border-radius:20px;border:2px solid <?= $quiz_general_disponible ? '#eab308' : 'rgba(255,255,255,0.1)' ?>;background:<?= $quiz_general_disponible ? 'rgba(234,179,8,0.08)' : 'rgba(255,255,255,0.03)' ?>;">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div style="font-size:2.5rem;"><?= $quiz_general_aprobado ? '🏆' : ($quiz_general_disponible ? '📋' : '🔒') ?></div>
                <div style="flex:1;">
                    <h3 style="margin:0 0 4px;color:<?= $quiz_general_disponible ? 'white' : '#64748b' ?>;">Quiz General B1</h3>
                    <p style="margin:0;font-size:0.9rem;color:<?= $quiz_general_disponible ? '#94a3b8' : '#475569' ?>;">
                        <?php if ($quiz_general_aprobado): ?>
                            ✅ Aprobado con <?= $quiz_status[0]['score'] ?>% · ¡Nivel B1 dominado!
                        <?php elseif ($quiz_general_disponible): ?>
                            ¡Todos los módulos completados! Pon a prueba todo tu inglés B1.
                        <?php else: ?>
                            Aprueba los quizzes de los <?= 8 - $quizzes_aprobados ?> módulo(s) restante(s) para desbloquear.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($quiz_general_disponible): ?>
                    <a href="/intep/cursoingles/quiz.php?nivel=B1&modulo=0" style="padding:12px 28px;border-radius:12px;background:<?= $quiz_general_aprobado ? 'rgba(16,185,129,0.2)' : 'linear-gradient(135deg,#eab308,#f59e0b)' ?>;color:<?= $quiz_general_aprobado ? '#10b981' : '#1a1a1a' ?>;font-weight:700;text-decoration:none;border:<?= $quiz_general_aprobado ? '1px solid #10b981' : 'none' ?>;">
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
