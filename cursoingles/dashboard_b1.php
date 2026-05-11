<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/course_activity.php';
require_once __DIR__ . '/practice_sidebar.php';

$es_admin_preview = !empty($_SESSION['admin_preview']) && ($_SESSION['usuario_rol'] ?? '') === 'admin';
if (empty($_SESSION['usuario_id']) || (empty($_SESSION['estudiante_id']) && !$es_admin_preview)) {
    header('Location: /intep/login.php');
    exit;
}

$est_id = $es_admin_preview ? 0 : (int)$_SESSION['estudiante_id'];

$st = mysqli_prepare($conexion, "SELECT e.nombre, e.foto FROM estudiantes e WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $est_id);
mysqli_stmt_execute($st);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?? [];

$nombre = $est['nombre'] ?? 'Estudiante';
$foto = $est['foto'] ?? '';
$inicial = strtoupper(mb_substr($nombre, 0, 1));

$nivel_actual = 'B1';
$xp_total = 0;
$st2 = mysqli_prepare($conexion, "SELECT nivel_actual, xp_total FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1");
if ($st2) {
    mysqli_stmt_bind_param($st2, 'i', $est_id);
    mysqli_stmt_execute($st2);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));
    if ($row) {
        $nivel_actual = $row['nivel_actual'];
        $xp_total = (int)$row['xp_total'];
    }
}

$progreso = [];
$st3 = mysqli_prepare(
    $conexion,
    "SELECT modulo_num, porcentaje, completado FROM ingles_cursos_progreso
     WHERE estudiante_id = ? AND nivel = 'B1'"
);
if ($st3) {
    mysqli_stmt_bind_param($st3, 'i', $est_id);
    mysqli_stmt_execute($st3);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st3), MYSQLI_ASSOC) as $r) {
        $progreso[(int)$r['modulo_num']] = ['pct' => (int)$r['porcentaje'], 'done' => (bool)$r['completado']];
    }
}

$quiz_status = [];
$st4 = mysqli_prepare(
    $conexion,
    "SELECT modulo_num, MAX(score) AS best_score, MAX(aprobado) AS aprobado
     FROM ingles_quiz_resultados
     WHERE estudiante_id = ? AND nivel = 'B1'
     GROUP BY modulo_num"
);
if ($st4) {
    mysqli_stmt_bind_param($st4, 'i', $est_id);
    mysqli_stmt_execute($st4);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st4), MYSQLI_ASSOC) as $r) {
        $quiz_status[(int)$r['modulo_num']] = ['score' => (int)$r['best_score'], 'aprobado' => (bool)$r['aprobado']];
    }
}

$modulos = [
    [1, 'Time Continues', 'Present perfect continuous para acciones que siguen.', '⏳', 'modulo1_b1.html'],
    [2, 'Wish & Regret', 'Deseos, arrepentimientos y estructuras mas complejas.', '💫', 'modulo2_b1.html'],
    [3, 'Passive Voice', 'Transforma acciones en voz pasiva con naturalidad.', '🔄', 'modulo3_b1.html', true],
    [4, 'Reported Speech', 'Cuenta lo que dijo otra persona con claridad.', '💬', 'modulo4_b1.html'],
    [5, 'Linking Ideas', 'Une ideas con conectores mas sofisticados.', '🔗', 'modulo5_b1.html'],
    [6, 'Formal Writing', 'Escritura formal, correos y lenguaje academico.', '✉️', 'modulo6_b1.html'],
    [7, 'Debating', 'Argumenta, defiende ideas y estructura opiniones.', '🎯', 'modulo7_b1.html'],
    [8, 'Mixed Tenses', 'Consolida los tiempos verbales en contexto real.', '📋', 'modulo8_b1.html'],
];

$quizzes_aprobados = 0;
for ($i = 1; $i <= 8; $i++) {
    if (!empty($quiz_status[$i]['aprobado'])) {
        $quizzes_aprobados++;
    }
}

$quiz_general_disponible = ($quizzes_aprobados >= 8);
$quiz_general_aprobado = !empty($quiz_status[0]['aprobado']);

$completed_count = 0;
$progress_sum = 0;
$current_focus = '';
foreach ($modulos as $mod) {
    $num = $mod[0];
    $pct = $progreso[$num]['pct'] ?? 0;
    $done = $progreso[$num]['done'] ?? false;
    $progress_sum += $done ? 100 : $pct;
    if ($done) {
        $completed_count++;
    }
    if ($current_focus === '' && !$done) {
        $current_focus = $mod[1];
    }
}

if ($current_focus === '') {
    $current_focus = 'Quiz general';
}

$overall_progress = (int)round($progress_sum / max(count($modulos), 1));
$ultima_actividad = intepCourseGetLastActivity($conexion, $est_id, 'B1');

function renderQuizPillB1(?array $quiz): string
{
    if ($quiz && !empty($quiz['aprobado'])) {
        return '<span class="quiz-pill success">Quiz ' . (int)$quiz['score'] . '%</span>';
    }
    if ($quiz) {
        return '<span class="quiz-pill fail">Quiz ' . (int)$quiz['score'] . '%</span>';
    }
    return '<span class="quiz-pill neutral">Sin quiz</span>';
}

function renderModuleCardB1(array $modulo, array $progreso, array $quiz_status): string
{
    [$num, $titulo, $desc, $icono, $url] = $modulo;
    $featured = !empty($modulo[5]);
    $pct = $progreso[$num]['pct'] ?? 0;
    $done = $progreso[$num]['done'] ?? false;
    $progress = $done ? 100 : $pct;
    $quiz = $quiz_status[$num] ?? null;

    $status_class = $done ? 'is-complete' : ($progress > 0 ? 'is-active' : 'is-idle');
    $status_label = $done ? 'Completado' : ($progress > 0 ? 'En progreso' : 'Por comenzar');
    $tag = $featured
        ? '<span class="module-tag featured">Modulo central</span>'
        : '<span class="module-tag">Practica avanzada</span>';

    $link = '/intep/cursoingles/' . $url;
    $quiz_link = '/intep/cursoingles/quiz.php?nivel=B1&modulo=' . $num;

    return '<article class="dashboard-module">'
        . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" class="module-card">'
        . '<div>'
        . '<div class="module-top">'
        . '<span class="module-num">Modulo ' . $num . '</span>'
        . '<span class="module-emoji">' . $icono . '</span>'
        . '</div>'
        . $tag
        . '<div class="module-copy">'
        . '<h3>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h3>'
        . '<p>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div>'
        . '</div>'
        . '<div>'
        . '<div class="module-progress"><div class="progress-bar" style="width:' . $progress . '%;"></div></div>'
        . '<p class="module-status ' . $status_class . '">' . $status_label . '</p>'
        . '</div>'
        . '</a>'
        . '<div class="module-footer">'
        . renderQuizPillB1($quiz)
        . '<a href="' . htmlspecialchars($quiz_link, ENT_QUOTES, 'UTF-8') . '" class="quiz-link">Ver quiz</a>'
        . '</div>'
        . '</article>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard B1 | INTEP Ingles</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/intep/cursoingles/lesson.css">
    <link rel="icon" href="/intep/favicon/favicon.svg" type="image/svg+xml">
</head>
<body class="dashboard-page level-b1">
    <div class="dashboard-shell">
        <div class="dashboard-layout">
            <aside class="dashboard-sidebar">
                <a href="/intep/cursoingles/index.php" class="dashboard-logo">
                    <img src="https://institutointep.edu.co/logointep.png" alt="INTEP">
                </a>

                <section class="dashboard-profile">
                    <div class="dashboard-avatar">
                        <?php if (!empty($foto)): ?>
                            <img src="<?= htmlspecialchars($foto) ?>" alt="Foto del estudiante">
                        <?php else: ?>
                            <?= htmlspecialchars($inicial) ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <p class="dashboard-overline">Estudiante</p>
                        <h2 class="dashboard-name"><?= htmlspecialchars($nombre) ?></h2>
                        <p class="dashboard-meta">Nivel actual: <?= htmlspecialchars($nivel_actual) ?></p>
                        <span class="dashboard-xp"><?= $xp_total ?> XP</span>
                    </div>
                </section>

                <section class="dashboard-nav-section">
                    <p class="dashboard-section-label">Mis rutas</p>
                    <nav class="dashboard-nav">
                        <a href="/intep/cursoingles/dashboard.php" class="dashboard-nav-link">
                            <span>A1 Beginner</span>
                        </a>
                        <a href="/intep/cursoingles/dashboard_a2.php" class="dashboard-nav-link">
                            <span>A2 Elementary</span>
                        </a>
                        <a href="/intep/cursoingles/dashboard_b1.php" class="dashboard-nav-link active">
                            <span>B1 Intermediate</span>
                            <span class="dashboard-nav-note">Ahora</span>
                        </a>
                    </nav>
                </section>

                <section class="dashboard-nav-section">
                    <p class="dashboard-section-label">Practica extra</p>
                    <p class="practice-intro"><?= htmlspecialchars(intepGetPracticeSidebarIntro('B1')) ?></p>
                    <?= intepRenderPracticeSidebar('B1') ?>
                </section>

                <div class="dashboard-portal-link">
                    <a href="/intep/dashboard.php" class="dashboard-nav-link">
                        <span>Volver al portal INTEP</span>
                    </a>
                </div>
            </aside>

            <main class="dashboard-main">
                <span class="dashboard-eyebrow">Nivel B1 · Intermediate</span>
                <h1 class="dashboard-title">Tu tramo mas fuerte para comunicar ideas con <span class="accent">seguridad y madurez</span>.</h1>
                <p class="dashboard-lead">
                    En B1 el curso se vuelve mas preciso: writing, conectores, reported speech, passive voice y consolidacion real.
                </p>

                <section class="dashboard-stats">
                    <article class="dashboard-stat">
                        <p class="dashboard-stat-label">Progreso total</p>
                        <p class="dashboard-stat-value"><?= $overall_progress ?>%</p>
                        <p class="dashboard-stat-sub"><?= $completed_count ?> de 8 modulos completados</p>
                    </article>

                    <article class="dashboard-stat">
                        <p class="dashboard-stat-label">Quizzes aprobados</p>
                        <p class="dashboard-stat-value"><?= $quizzes_aprobados ?>/8</p>
                        <p class="dashboard-stat-sub">Tu cierre B1 depende de este bloque final</p>
                    </article>

                    <article class="dashboard-stat">
                        <p class="dashboard-stat-label">Enfoque actual</p>
                        <p class="dashboard-stat-value"><?= $completed_count === 8 ? 'Listo' : htmlspecialchars($current_focus) ?></p>
                        <p class="dashboard-stat-sub">Siguiente punto recomendado</p>
                    </article>
                </section>

                <?php if ($ultima_actividad && !empty($ultima_actividad['page_url'])): ?>
                    <section class="dashboard-summary continue-summary">
                        <div class="summary-icon">📱</div>
                        <div class="summary-copy">
                            <h3>Continua donde lo dejaste</h3>
                            <p>
                                <?= htmlspecialchars($ultima_actividad['page_title'] ?: 'Modulo en progreso') ?>
                                <?php if (!empty($ultima_actividad['section_title'])): ?>
                                    · <?= htmlspecialchars($ultima_actividad['section_title']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="<?= htmlspecialchars($ultima_actividad['page_url']) ?>" class="summary-action">
                            Reanudar modulo
                        </a>
                    </section>
                <?php endif; ?>

                <section class="dashboard-modules">
                    <?php foreach ($modulos as $modulo): ?>
                        <?= renderModuleCardB1($modulo, $progreso, $quiz_status) ?>
                    <?php endforeach; ?>
                </section>

                <section class="dashboard-summary">
                    <div class="summary-icon"><?= $quiz_general_aprobado ? '🏆' : ($quiz_general_disponible ? '📘' : '🔒') ?></div>

                    <div class="summary-copy">
                        <h3>Quiz general B1</h3>
                        <p>
                            <?php if ($quiz_general_aprobado): ?>
                                Aprobado con <?= (int)$quiz_status[0]['score'] ?>%. Tu nivel B1 ya esta consolidado.
                            <?php elseif ($quiz_general_disponible): ?>
                                Ya completaste todos los modulos B1. Solo falta cerrar la etapa con el quiz general.
                            <?php else: ?>
                                Aprueba los quizzes de los <?= 8 - $quizzes_aprobados ?> modulo(s) restantes para desbloquearlo.
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($quiz_general_disponible): ?>
                        <a href="/intep/cursoingles/quiz.php?nivel=B1&modulo=0" class="summary-action <?= $quiz_general_aprobado ? 'is-complete' : '' ?>">
                            <?= $quiz_general_aprobado ? 'Repetir quiz general' : 'Comenzar quiz general' ?>
                        </a>
                    <?php else: ?>
                        <span class="summary-chip"><?= $quizzes_aprobados ?>/8 quizzes listos</span>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
