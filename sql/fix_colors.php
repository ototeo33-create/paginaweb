<?php
// Script to replace all purple colors with green across all PHP files
$files = [
    'C:/xampp/htdocs/intep/dashboard.php',
    'C:/xampp/htdocs/intep/login.php',
    'C:/xampp/htdocs/intep/notas.php',
    'C:/xampp/htdocs/intep/horarios.php',
    'C:/xampp/htdocs/intep/mi_cartera.php',
    'C:/xampp/htdocs/intep/mi_foto.php',
    'C:/xampp/htdocs/intep/solicitudes.php',
    'C:/xampp/htdocs/intep/admin/index.php',
    'C:/xampp/htdocs/intep/admin/cartera.php',
    'C:/xampp/htdocs/intep/admin/ingresar_notas.php',
    'C:/xampp/htdocs/intep/admin/gestionar_horarios.php',
    'C:/xampp/htdocs/intep/admin/gestionar_modulos.php',
    'C:/xampp/htdocs/intep/admin/limpiar_datos.php',
];

$replacements = [
    // Primary purple → primary green
    '#6B3FA0' => '#059669',
    '#6b3fa0' => '#059669',
    // Light purple → light green
    '#8B5CF6' => '#10B981',
    '#8b5cf6' => '#10B981',
    // Dark purple → dark green
    '#4C2882' => '#047857',
    '#4c2882' => '#047857',
    // Lighter purple → lighter green
    '#A78BFA' => '#34D399',
    '#a78bfa' => '#34D399',
    // Another purple
    '#7C3AED' => '#047857',
    '#7c3aed' => '#047857',
    // Very light purple bg → very light green bg
    '#F3EEFF' => '#ECFDF5',
    '#f3eeff' => '#ECFDF5',
    // Soft purple bg → soft green bg
    '#EDE5FF' => '#D1FAE5',
    '#ede5ff' => '#D1FAE5',
    // Page backgrounds
    '#F8F6FF' => '#F0FDF4',
    '#f8f6ff' => '#F0FDF4',
    '#F8F7FF' => '#F0FDF4',
    '#f8f7ff' => '#F0FDF4',
    '#FAF8FF' => '#F0FDF4',
    '#faf8ff' => '#F0FDF4',
    // Dark purples → dark greens
    '#1A1033' => '#022C22',
    '#1a1033' => '#022C22',
    '#1E1333' => '#022C22',
    '#1e1333' => '#022C22',
    '#2D1854' => '#064E3B',
    '#2d1854' => '#064E3B',
    '#2D2145' => '#064E3B',
    '#2d2145' => '#064E3B',
    // Borders
    '#D1C4E9' => '#A7F3D0',
    '#d1c4e9' => '#A7F3D0',
    '#D4BFFF' => '#A7F3D0',
    '#d4bfff' => '#A7F3D0',
    '#C4B5FD' => '#6EE7B7',
    '#c4b5fd' => '#6EE7B7',
    '#C4B5D9' => '#6EE7B7',
    '#c4b5d9' => '#6EE7B7',
    // Dividers/input borders
    '#F0ECF5' => '#D1FAE5',
    '#f0ecf5' => '#D1FAE5',
    '#E5E0F0' => '#D1FAE5',
    '#e5e0f0' => '#D1FAE5',
    '#E0D5F0' => '#D1FAE5',
    '#e0d5f0' => '#D1FAE5',
    // RGBA values (with spaces)
    'rgba(139, 92, 246' => 'rgba(16, 185, 129',
    'rgba(107, 63, 160' => 'rgba(5, 150, 105',
    'rgba(26, 16, 51' => 'rgba(2, 44, 34',
    // RGBA values (without spaces)
    'rgba(139,92,246' => 'rgba(16,185,129',
    'rgba(107,63,160' => 'rgba(5,150,105',
    'rgba(26,16,51' => 'rgba(2,44,34',
    // Other purple-ish
    'rgba(230, 225, 250' => 'rgba(209, 250, 229',
    'rgba(243, 238, 255' => 'rgba(209, 250, 229',
    '#E6E1FA' => '#D1FAE5',
    // CSS variable references
    'var(--morado)' => 'var(--verde-primary)',
    'var(--morado-claro)' => 'var(--verde-claro)',
    'var(--morado-oscuro)' => 'var(--verde-oscuro)',
    'var(--morado-muted)' => 'var(--verde-muted)',
    'var(--morado-soft)' => 'var(--verde-soft)',
];

$totalReplacements = 0;
foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "SKIP: $file (not found)\n";
        continue;
    }
    $content = file_get_contents($file);
    $original = $content;
    $fileReplacements = 0;

    foreach ($replacements as $search => $replace) {
        $count = 0;
        $content = str_replace($search, $replace, $content, $count);
        $fileReplacements += $count;
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "OK: $file ($fileReplacements replacements)\n";
        $totalReplacements += $fileReplacements;
    } else {
        echo "NO CHANGES: $file\n";
    }
}

echo "\n=== Total: $totalReplacements replacements across all files ===\n";
