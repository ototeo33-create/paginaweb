<?php
require_once 'config.php';
require_once __DIR__ . '/partials/icons.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'estudiante') {
    header('Location: login.php');
    exit;
}

$nombre = $_SESSION['usuario_nombre'];
$est_id = $_SESSION['estudiante_id'] ?? 0;

$q = "SELECT e.nombre, e.documento, e.email, e.foto, p.nombre AS programa
      FROM estudiantes e
      LEFT JOIN programas p ON e.programa_id = p.id
      WHERE e.id = ?";
$stmt = mysqli_prepare($conexion, $q);
mysqli_stmt_bind_param($stmt, 'i', $est_id);
mysqli_stmt_execute($stmt);
$info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];

// Stats académicos
$qs = "SELECT COUNT(*) AS total,
              SUM(CASE WHEN aprobado = 1 THEN 1 ELSE 0 END) AS aprobados,
              ROUND(AVG(CASE WHEN nota_final > 0 THEN nota_final END), 1) AS promedio
       FROM notas WHERE estudiante_id = ?";
$ss = mysqli_prepare($conexion, $qs);
mysqli_stmt_bind_param($ss, 'i', $est_id);
mysqli_stmt_execute($ss);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($ss)) ?: ['total'=>0,'aprobados'=>0,'promedio'=>0];

$total      = (int)$stats['total'];
$aprobados  = (int)$stats['aprobados'];
$promedio   = (float)($stats['promedio'] ?? 0);
$avance_pct = $total > 0 ? min(100, round($aprobados * 100 / $total)) : 0;
// Promedio sobre escala 5.0
$prom_pct = $promedio > 0 ? min(100, round($promedio * 100 / 5)) : 0;

// Racha de inglés (si existe)
$racha = 0;
$xp = 0;
$nivel = '';
$qr = mysqli_prepare($conexion, "SELECT racha_actual, xp_total, nivel_actual FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1");
if ($qr) {
    mysqli_stmt_bind_param($qr, 'i', $est_id);
    mysqli_stmt_execute($qr);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($qr));
    if ($r) { $racha = (int)$r['racha_actual']; $xp = (int)$r['xp_total']; $nivel = $r['nivel_actual']; }
}

$bn_page   = basename($_SERVER['PHP_SELF']);
$bn_active = function($file) use ($bn_page) { return $bn_page === $file ? ' active' : ''; };

$iniciales = '';
if (!empty($info['nombre'])) {
    $partes = preg_split('/\s+/', trim($info['nombre']));
    $iniciales = strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mi perfil – INTEP</title>
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#059669">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        html, body {
            min-height: 100%;
            margin: 0;
            background: #f4f7f9;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #022C22;
            -webkit-font-smoothing: antialiased;
        }

        /* ═══════════════ HERO ═══════════════ */
        .pf-hero {
            position: relative;
            background:
                radial-gradient(120% 80% at 80% 0%, rgba(255,255,255,0.18) 0%, transparent 60%),
                radial-gradient(80% 50% at 0% 100%, rgba(0,0,0,0.18) 0%, transparent 60%),
                linear-gradient(135deg, #059669 0%, #10B981 50%, #34D399 100%);
            color: white;
            padding: 1.4rem 1.2rem 4.5rem;
            border-bottom-left-radius: 32px;
            border-bottom-right-radius: 32px;
            overflow: hidden;
        }
        .pf-hero::before, .pf-hero::after {
            content: ''; position: absolute; border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.18);
            pointer-events: none;
        }
        .pf-hero::before { width: 240px; height: 240px; top: -120px; right: -80px; }
        .pf-hero::after  { width: 140px; height: 140px; bottom: -50px; left: -40px; opacity: .55; }

        .pf-hero-top {
            display: flex; align-items: center; justify-content: space-between;
            position: relative; z-index: 2;
        }
        .pf-hero-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.6px;
            font-weight: 700;
            opacity: 0.85;
        }
        .pf-hero-action {
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.28);
            border-radius: 50%;
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            color: white; text-decoration: none;
            backdrop-filter: blur(8px);
        }
        .pf-hero-action:active { transform: scale(.92); }

        /* ═══════════════ FOTO + ANILLO PROGRESO ═══════════════ */
        .pf-avatar-wrap {
            margin: 1.4rem auto 0;
            width: 130px;
            height: 130px;
            position: relative;
            z-index: 2;
        }
        .pf-avatar-ring {
            position: absolute; inset: 0;
            transform: rotate(-90deg);
        }
        .pf-avatar-ring circle {
            fill: none;
            stroke-width: 4;
            stroke-linecap: round;
        }
        .pf-avatar-ring .ring-bg { stroke: rgba(255,255,255,0.18); }
        .pf-avatar-ring .ring-fill {
            stroke: #FACC15;
            stroke-dasharray: 377; /* 2π * 60 */
            stroke-dashoffset: 377;
            animation: ring-draw 1.6s cubic-bezier(.34,1.1,.64,1) forwards .25s;
        }
        @keyframes ring-draw {
            to { stroke-dashoffset: var(--ring-target, 377); }
        }
        .pf-avatar {
            position: absolute;
            inset: 10px;
            border-radius: 50%;
            background: white;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            font-size: 2rem;
            font-weight: 800;
            color: #059669;
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
        }
        .pf-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .pf-avatar-badge {
            position: absolute;
            bottom: 6px; right: 6px;
            background: white;
            border-radius: 50%;
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            color: #059669;
            text-decoration: none;
        }
        .pf-avatar-badge:active { transform: scale(.9); }

        .pf-name {
            text-align: center;
            margin: 0.9rem 0 0.2rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.3px;
            position: relative; z-index: 2;
        }
        .pf-program {
            text-align: center;
            display: inline-block;
            margin: 0 auto;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(8px);
            padding: 4px 14px;
            border-radius: 14px;
            position: relative; z-index: 2;
        }
        .pf-program-wrap { text-align: center; position: relative; z-index: 2; }

        /* ═══════════════ STATS DESPUÉS DEL HERO ═══════════════ */
        .pf-content {
            margin: -3.5rem 0.9rem 0;
            position: relative;
            z-index: 3;
            padding-bottom: 100px;
        }

        .pf-progress-card {
            background: white;
            border-radius: 22px;
            padding: 1.3rem 1.2rem 1.1rem;
            box-shadow: 0 8px 28px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
        }
        .pf-progress-card h3 {
            margin: 0 0 0.9rem;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #6B7280;
            font-weight: 700;
        }
        .pf-progress-row { margin-bottom: 1rem; }
        .pf-progress-row:last-child { margin-bottom: 0; }
        .pf-progress-label {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: #1F2937;
            font-weight: 600;
        }
        .pf-progress-label .pf-val {
            font-size: 1rem;
            font-weight: 800;
            color: #059669;
            font-variant-numeric: tabular-nums;
        }
        .pf-progress-bar {
            height: 9px;
            background: #F3F4F6;
            border-radius: 50px;
            overflow: hidden;
            position: relative;
        }
        .pf-progress-fill {
            height: 100%;
            border-radius: 50px;
            background: linear-gradient(90deg, #10B981, #34D399);
            width: 0;
            position: relative;
            transition: width 1.4s cubic-bezier(.34,1.1,.64,1);
        }
        .pf-progress-fill.amarillo { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
        .pf-progress-fill.azul     { background: linear-gradient(90deg, #2563EB, #3B82F6); }

        /* Brillo deslizante dentro de la barra */
        .pf-progress-fill::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.55) 50%, transparent 100%);
            transform: translateX(-100%);
            animation: shimmer 2.6s ease-in-out infinite 1.6s;
        }
        @keyframes shimmer { to { transform: translateX(100%); } }

        /* ═══════════════ MINI STATS ═══════════════ */
        .pf-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.6rem;
            margin-bottom: 1rem;
        }
        .pf-stat {
            background: white;
            border-radius: 16px;
            padding: 0.9rem 0.6rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        .pf-stat-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: #ECFDF5;
            color: #059669;
            margin: 0 auto 0.4rem;
            display: flex; align-items: center; justify-content: center;
        }
        .pf-stat.azul .pf-stat-icon { background: #EFF6FF; color: #2563EB; }
        .pf-stat.naranja .pf-stat-icon { background: #FFF7ED; color: #EA580C; }
        .pf-stat-num {
            font-size: 1.25rem;
            font-weight: 800;
            color: #022C22;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }
        .pf-stat-label {
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #6B7280;
            margin-top: 4px;
            font-weight: 600;
        }

        /* ═══════════════ ACCIONES ═══════════════ */
        .pf-section-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: #6B7280;
            font-weight: 700;
            margin: 1.4rem 0.4rem 0.6rem;
        }
        .pf-actions {
            background: white;
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .pf-action {
            display: flex; align-items: center; gap: 0.9rem;
            padding: 0.95rem 1rem;
            text-decoration: none;
            color: #022C22;
            font-weight: 600;
            font-size: 0.9rem;
            border-bottom: 1px solid #F1F5F9;
            transition: background .15s ease;
        }
        .pf-action:last-child { border-bottom: none; }
        .pf-action:active { background: #F8FAFC; }
        .pf-action-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: #ECFDF5;
            color: #059669;
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .pf-action.azul   .pf-action-icon { background: #EFF6FF; color: #2563EB; }
        .pf-action.morado .pf-action-icon { background: #F5F3FF; color: #7C3AED; }
        .pf-action.naranja .pf-action-icon { background: #FFF7ED; color: #EA580C; }
        .pf-action.danger .pf-action-icon { background: #FEF2F2; color: #DC2626; }
        .pf-action.danger { color: #DC2626; }
        .pf-action-arrow {
            margin-left: auto;
            color: #CBD5E1;
            display: flex;
        }

        /* En desktop: limita ancho y oculta bottom-nav */
        @media (min-width: 769px) {
            .pf-content { max-width: 480px; margin-left: auto; margin-right: auto; padding-bottom: 2rem; }
            .pf-hero { max-width: 560px; margin: 0 auto; border-radius: 0 0 32px 32px; }
        }
    </style>
</head>
<body data-rol="estudiante">

    <header class="pf-hero">
        <div class="pf-hero-top">
            <span class="pf-hero-title">Mi perfil</span>
            <a href="dashboard.php" class="pf-hero-action" aria-label="Volver"><?= icon('home', ['size' => 18]) ?></a>
        </div>

        <div class="pf-avatar-wrap">
            <svg class="pf-avatar-ring" viewBox="0 0 130 130">
                <circle class="ring-bg"   cx="65" cy="65" r="60"/>
                <circle class="ring-fill" cx="65" cy="65" r="60"
                        style="--ring-target: <?= 377 - round(377 * $avance_pct / 100) ?>"/>
            </svg>
            <div class="pf-avatar">
                <?php if (!empty($info['foto'])): ?>
                    <img src="<?= htmlspecialchars($info['foto']) ?>" alt="">
                <?php else: ?>
                    <span><?= htmlspecialchars($iniciales ?: '🙂') ?></span>
                <?php endif; ?>
            </div>
            <a href="mi_foto.php" class="pf-avatar-badge" aria-label="Cambiar foto">
                <?= icon('camera', ['size' => 16]) ?>
            </a>
        </div>

        <h1 class="pf-name"><?= htmlspecialchars($info['nombre'] ?? $nombre) ?></h1>
        <?php if (!empty($info['programa'])): ?>
            <div class="pf-program-wrap">
                <span class="pf-program"><?= htmlspecialchars($info['programa']) ?></span>
            </div>
        <?php endif; ?>
    </header>

    <div class="pf-content">

        <div class="pf-progress-card">
            <h3>Tu progreso académico</h3>

            <div class="pf-progress-row">
                <div class="pf-progress-label">
                    <span>Módulos aprobados</span>
                    <span class="pf-val"><?= $aprobados ?>/<?= $total ?></span>
                </div>
                <div class="pf-progress-bar">
                    <div class="pf-progress-fill" data-target="<?= $avance_pct ?>"></div>
                </div>
            </div>

            <div class="pf-progress-row">
                <div class="pf-progress-label">
                    <span>Promedio acumulado</span>
                    <span class="pf-val"><?= $promedio > 0 ? number_format($promedio, 1) : '—' ?></span>
                </div>
                <div class="pf-progress-bar">
                    <div class="pf-progress-fill amarillo" data-target="<?= $prom_pct ?>"></div>
                </div>
            </div>

            <?php if ($nivel): ?>
            <div class="pf-progress-row">
                <div class="pf-progress-label">
                    <span>Inglés · Nivel <?= htmlspecialchars($nivel) ?></span>
                    <span class="pf-val"><?= $xp ?> XP</span>
                </div>
                <?php
                    $xp_map = ['A1'=>[0,300],'A2'=>[300,700],'B1'=>[700,1200],'B2'=>[1200,2000]];
                    $rg = $xp_map[$nivel] ?? [0,300];
                    $xp_pct = max(0, min(100, round(($xp - $rg[0]) * 100 / ($rg[1] - $rg[0]))));
                ?>
                <div class="pf-progress-bar">
                    <div class="pf-progress-fill azul" data-target="<?= $xp_pct ?>"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="pf-stats">
            <div class="pf-stat">
                <div class="pf-stat-icon"><?= icon('check', ['size' => 18]) ?></div>
                <div class="pf-stat-num"><?= $aprobados ?></div>
                <div class="pf-stat-label">Aprobados</div>
            </div>
            <div class="pf-stat azul">
                <div class="pf-stat-icon"><?= icon('notas', ['size' => 18]) ?></div>
                <div class="pf-stat-num"><?= $total ?></div>
                <div class="pf-stat-label">Módulos</div>
            </div>
            <div class="pf-stat naranja">
                <div class="pf-stat-icon"><?= icon('flame', ['size' => 18]) ?></div>
                <div class="pf-stat-num"><?= $racha ?></div>
                <div class="pf-stat-label">Racha</div>
            </div>
        </div>

        <div class="pf-section-label">Información personal</div>
        <div class="pf-actions">
            <?php if (!empty($info['documento'])): ?>
            <div class="pf-action">
                <span class="pf-action-icon"><?= icon('perfil', ['size' => 18]) ?></span>
                <span>Documento</span>
                <span style="margin-left:auto;color:#6B7280;font-weight:500;"><?= htmlspecialchars($info['documento']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($info['email'])): ?>
            <div class="pf-action">
                <span class="pf-action-icon azul" style="background:#EFF6FF;color:#2563EB"><?= icon('bell', ['size' => 18]) ?></span>
                <span>Email</span>
                <span style="margin-left:auto;color:#6B7280;font-weight:500;font-size:0.78rem;max-width:55%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($info['email']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="pf-section-label">Cuenta</div>
        <div class="pf-actions">
            <a href="mi_foto.php" class="pf-action">
                <span class="pf-action-icon"><?= icon('camera', ['size' => 18]) ?></span>
                <span>Cambiar foto de perfil</span>
                <span class="pf-action-arrow"><?= icon('arrow', ['size' => 16]) ?></span>
            </a>
            <a href="cambiar_password.php" class="pf-action azul">
                <span class="pf-action-icon"><?= icon('lock', ['size' => 18]) ?></span>
                <span>Cambiar contraseña</span>
                <span class="pf-action-arrow"><?= icon('arrow', ['size' => 16]) ?></span>
            </a>
            <a href="evaluar_docente.php" class="pf-action morado">
                <span class="pf-action-icon"><?= icon('evaluar', ['size' => 18]) ?></span>
                <span>Evaluar docentes</span>
                <span class="pf-action-arrow"><?= icon('arrow', ['size' => 16]) ?></span>
            </a>
            <a href="solicitudes.php" class="pf-action naranja">
                <span class="pf-action-icon"><?= icon('solicitudes', ['size' => 18]) ?></span>
                <span>Mis solicitudes</span>
                <span class="pf-action-arrow"><?= icon('arrow', ['size' => 16]) ?></span>
            </a>
            <a href="logout.php" class="pf-action danger">
                <span class="pf-action-icon"><?= icon('logout', ['size' => 18]) ?></span>
                <span>Cerrar sesión</span>
                <span class="pf-action-arrow"><?= icon('arrow', ['size' => 16]) ?></span>
            </a>
        </div>
    </div>

    <?php include __DIR__ . '/partials/student_bottom_nav.php'; ?>

    <script>
    // Animación de las barras al cargar
    (function() {
        function aplicar() {
            document.querySelectorAll('.pf-progress-fill').forEach(function(el) {
                var target = el.getAttribute('data-target') || 0;
                el.style.width = target + '%';
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() { setTimeout(aplicar, 250); });
        } else {
            setTimeout(aplicar, 250);
        }
    })();
    </script>
</body>
</html>
