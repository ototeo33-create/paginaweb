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
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 3px;
    text-decoration: none;
    color: #374151;
    font-size: 0.72rem; font-weight: 600;
    transition: color .2s ease, transform .15s ease;
    padding: 6px 4px;
    border-radius: 18px;
}
.bn-item .bn-icon {
    width: 26px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: transform .2s ease;
}
.bn-item .bn-icon svg { width: 24px; height: 24px; display: block; stroke-width: 2; }
.bn-item.active { color: #059669; font-weight: 700; }
.bn-item.active .bn-icon { transform: scale(1.1); }
.bn-item:active { transform: scale(.92); }
</style>
<nav class="bottom-nav" aria-label="Navegación principal">
    <a href="notas.php"      class="bn-item<?= $bn_active('notas.php') ?>"><span class="bn-icon"><?= icon('notas', ['size' => 24]) ?></span><span>Notas</span></a>
    <a href="horarios.php"   class="bn-item<?= $bn_active('horarios.php') ?>"><span class="bn-icon"><?= icon('horario', ['size' => 24]) ?></span><span>Horario</span></a>
    <a href="dashboard.php"  class="bn-item<?= $bn_active('dashboard.php') ?>"><span class="bn-icon"><?= icon('home', ['size' => 24]) ?></span><span>Home</span></a>
    <a href="mi_cartera.php" class="bn-item<?= $bn_active('mi_cartera.php') ?>"><span class="bn-icon"><?= icon('cartera', ['size' => 24]) ?></span><span>Cartera</span></a>
    <a href="perfil.php"     class="bn-item<?= $bn_active('perfil.php') ?>"><span class="bn-icon"><?= icon('perfil', ['size' => 24]) ?></span><span>Yo</span></a>
</nav>
