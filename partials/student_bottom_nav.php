<?php
// Bottom-nav reutilizable para vistas del estudiante.
// Solo se muestra en móvil (max-width: 768px).
if (($_SESSION['usuario_rol'] ?? '') !== 'estudiante') return;
require_once __DIR__ . '/icons.php';
$bn_page   = basename($_SERVER['PHP_SELF']);
$bn_active = function($file) use ($bn_page) { return $bn_page === $file ? ' active' : ''; };
?>
<style>
@media (max-width: 768px) {
    body { padding-bottom: 90px !important; }
    .bottom-nav { display: flex !important; }
}
.bottom-nav {
    display: none;
    position: fixed; bottom: 0; left: 0; right: 0; height: 64px;
    background: rgba(255,255,255,0.96);
    backdrop-filter: saturate(180%) blur(14px);
    -webkit-backdrop-filter: saturate(180%) blur(14px);
    justify-content: space-around; align-items: stretch;
    padding: 0 8px calc(env(safe-area-inset-bottom, 0px)) 8px;
    border-top: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 -6px 24px rgba(0,0,0,0.06);
    z-index: 100;
}
.bn-item {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 2px;
    text-decoration: none; color: #9CA3AF;
    font-size: 0.7rem; font-weight: 600;
    transition: color .2s ease, transform .15s ease;
    position: relative; padding-top: 6px;
}
.bn-item .bn-icon {
    width: 26px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: transform .2s ease;
}
.bn-item .bn-icon svg { width: 24px; height: 24px; display: block; }
.bn-item.active { color: #059669; }
.bn-item.active .bn-icon { transform: translateY(-2px) scale(1.08); }
.bn-item:active { transform: scale(.92); }
.bn-item.active:not(.bn-home)::before {
    content: ''; position: absolute; top: 4px;
    width: 4px; height: 4px; border-radius: 50%;
    background: #059669;
}
.bn-item.bn-home { transform: translateY(-22px); }
.bn-item.bn-home .bn-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
    box-shadow: 0 6px 18px rgba(5,150,105,0.45), 0 0 0 4px rgba(255,255,255,0.95);
    transition: transform .25s cubic-bezier(.34,1.56,.64,1);
}
.bn-item.bn-home .bn-icon svg { width: 28px; height: 28px; color: white; }
.bn-item.bn-home.active .bn-icon { transform: rotate(-4deg) scale(1.06); }
.bn-item.bn-home span:last-child { margin-top: 4px; font-size: 0.68rem; }
.bn-item.bn-home:active { transform: translateY(-22px) scale(.92); }
</style>
<nav class="bottom-nav" aria-label="Navegación principal">
    <a href="notas.php"      class="bn-item<?= $bn_active('notas.php') ?>"><span class="bn-icon"><?= icon('notas', ['size' => 24]) ?></span><span>Notas</span></a>
    <a href="horarios.php"   class="bn-item<?= $bn_active('horarios.php') ?>"><span class="bn-icon"><?= icon('horario', ['size' => 24]) ?></span><span>Horario</span></a>
    <a href="dashboard.php"  class="bn-item bn-home<?= $bn_active('dashboard.php') ?>"><span class="bn-icon"><?= icon('home', ['size' => 28]) ?></span><span>Home</span></a>
    <a href="mi_cartera.php" class="bn-item<?= $bn_active('mi_cartera.php') ?>"><span class="bn-icon"><?= icon('cartera', ['size' => 24]) ?></span><span>Cartera</span></a>
    <a href="perfil.php"     class="bn-item<?= $bn_active('perfil.php') ?>"><span class="bn-icon"><?= icon('perfil', ['size' => 24]) ?></span><span>Yo</span></a>
</nav>
