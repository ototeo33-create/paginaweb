<?php
// Bottom-nav reutilizable para vistas del estudiante.
// Solo se muestra en móvil (max-width: 768px).
if (($_SESSION['usuario_rol'] ?? '') !== 'estudiante') return;
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/../includes/alertas_helper.php';
$bn_page   = basename($_SERVER['PHP_SELF']);
$bn_active = function($file) use ($bn_page) { return $bn_page === $file ? ' active' : ''; };

// Reutilizar $alertas si ya fue calculado (dashboard.php), si no, calcularlo aquí.
if (!isset($alertas) || !is_array($alertas)) {
    $alertas = obtenerAlertasEstudiante($GLOBALS['conexion'], (int)($_SESSION['estudiante_id'] ?? 0));
}
$bn_pulse = function($mod) use ($alertas) {
    return !empty($alertas[$mod]) ? ' nav-pulsing' : '';
};

// Contar mensajes no leídos para el badge de mensajería.
// Envuelto en try/catch: en PHP 8.2 mysqli lanza excepciones, así que si las
// tablas aún no existen (antes de la 1ª migración) NO se debe romper la página.
$_bn_est_id = (int)($_SESSION['estudiante_id'] ?? 0);
$_bn_no_leidos = 0;
if ($_bn_est_id > 0) {
    try {
        $_bn_stmt = mysqli_prepare($GLOBALS['conexion'],
            "SELECT COUNT(*) AS n FROM mensajes_admin m
             LEFT JOIN mensajes_vistos v
                    ON v.mensaje_id = m.id AND v.estudiante_id = ?
             WHERE v.visto_en IS NULL");
        if ($_bn_stmt) {
            mysqli_stmt_bind_param($_bn_stmt, 'i', $_bn_est_id);
            mysqli_stmt_execute($_bn_stmt);
            $_bn_res = mysqli_stmt_get_result($_bn_stmt);
            $_bn_no_leidos = (int)(mysqli_fetch_assoc($_bn_res)['n'] ?? 0);
            mysqli_stmt_close($_bn_stmt);
        }
    } catch (\Throwable $e) {
        $_bn_no_leidos = 0; // tablas aún no creadas u otro error: simplemente sin badge
    }
}
$bn_msg_pulse = $_bn_no_leidos > 0 ? ' nav-pulsing' : '';
?>
<style>
@media (max-width: 768px) {
    body { padding-bottom: 100px !important; }
    .bottom-nav { display: flex !important; }
}
.bottom-nav {
    display: none;
    position: fixed;
    bottom: calc(12px + env(safe-area-inset-bottom, 0px));
    left: 12px; right: 12px;
    height: 68px;
    background: rgba(255,255,255,0.98);
    backdrop-filter: saturate(180%) blur(20px);
    -webkit-backdrop-filter: saturate(180%) blur(20px);
    justify-content: space-around;
    align-items: center;
    padding: 0 6px;
    border-radius: 999px;
    box-shadow: 0 10px 32px rgba(0,0,0,0.14), 0 2px 8px rgba(0,0,0,0.06);
    z-index: 100;
}
.bn-item {
    flex: 1;
    min-width: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 3px;
    text-decoration: none;
    color: #374151;
    font-size: 0.66rem; font-weight: 600;
    transition: color .2s ease, transform .15s ease;
    padding: 6px 2px;
    border-radius: 18px;
}
.bn-item > span:last-child {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bn-item .bn-icon {
    width: 24px; height: 24px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: transform .2s ease;
}
.bn-item .bn-icon svg { width: 22px; height: 22px; display: block; stroke-width: 2; }
.bn-item.active { color: #059669; font-weight: 700; }
.bn-item.active .bn-icon { transform: scale(1.1); }
.bn-item:active { transform: scale(.92); }

/* Alerta titilante en bottom-nav (sutil, basada en escala del ícono) */
.bn-item.nav-pulsing .bn-icon {
    animation: bnPulse 1.6s ease-in-out infinite;
    color: #F59E0B;
}
.bn-item.nav-pulsing { color: #F59E0B; }
@keyframes bnPulse {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(245,158,11,0)); }
    50%      { transform: scale(1.18); filter: drop-shadow(0 0 6px rgba(245,158,11,0.7)); }
}
@media (prefers-reduced-motion: reduce) {
    .bn-item.nav-pulsing .bn-icon {
        animation: none;
        filter: drop-shadow(0 0 4px rgba(245,158,11,0.7));
    }
}
</style>
<nav class="bottom-nav" aria-label="Navegación principal">
    <a href="notas.php"      class="bn-item<?= $bn_active('notas.php') ?>"><span class="bn-icon"><?= icon('notas', ['size' => 22]) ?></span><span>Notas</span></a>
    <a href="horarios.php"   class="bn-item<?= $bn_active('horarios.php') . $bn_pulse('horarios') ?>"><span class="bn-icon"><?= icon('horario', ['size' => 22]) ?></span><span>Horario</span></a>
    <a href="dashboard.php"  class="bn-item<?= $bn_active('dashboard.php') ?>"><span class="bn-icon"><?= icon('home', ['size' => 22]) ?></span><span>Home</span></a>
    <a href="mi_cartera.php" class="bn-item<?= $bn_active('mi_cartera.php') . $bn_pulse('cartera') ?>"><span class="bn-icon"><?= icon('cartera', ['size' => 22]) ?></span><span>Cartera</span></a>
    <a href="mensajes.php"   class="bn-item<?= $bn_active('mensajes.php') . $bn_msg_pulse ?>" style="position:relative;">
        <span class="bn-icon"><?= icon('mensajes', ['size' => 22]) ?></span>
        <?php if ($_bn_no_leidos > 0): ?>
            <span style="position:absolute;top:4px;right:calc(50% - 18px);background:#dc2626;color:#fff;border-radius:999px;font-size:0.6rem;font-weight:800;padding:0.05rem 0.35rem;min-width:14px;text-align:center;line-height:1.4;"><?= $_bn_no_leidos ?></span>
        <?php endif; ?>
        <span>Mensajes</span>
    </a>
    <a href="perfil.php"     class="bn-item<?= $bn_active('perfil.php') ?>"><span class="bn-icon"><?= icon('perfil', ['size' => 22]) ?></span><span>Yo</span></a>
</nav>
