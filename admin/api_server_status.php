<?php
require_once '../config.php';
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    http_response_code(403); exit;
}
header('Content-Type: application/json');

function fmt_bytes($b) {
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024) return round($b / 1024, 1) . ' KB';
    return (int)$b . ' B';
}

function read_mem() {
    if (!is_readable('/proc/meminfo')) return null;
    $raw = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $raw, $t);
    preg_match('/MemAvailable:\s+(\d+)/', $raw, $a);
    preg_match('/SwapTotal:\s+(\d+)/', $raw, $st);
    preg_match('/SwapFree:\s+(\d+)/', $raw, $sf);
    if (!$t || !$a) return null;
    $total = (int)$t[1] * 1024;
    $avail = (int)$a[1] * 1024;
    $used = max(0, $total - $avail);
    $swap_total = isset($st[1]) ? (int)$st[1] * 1024 : 0;
    $swap_free  = isset($sf[1]) ? (int)$sf[1] * 1024 : 0;
    return [
        'total' => $total, 'used' => $used, 'free' => $avail,
        'percent' => $total > 0 ? round($used / $total * 100, 1) : 0,
        'total_h' => fmt_bytes($total), 'used_h' => fmt_bytes($used), 'free_h' => fmt_bytes($avail),
        'swap_total_h' => fmt_bytes($swap_total),
        'swap_used_h' => fmt_bytes(max(0, $swap_total - $swap_free)),
        'swap_percent' => $swap_total > 0 ? round(($swap_total - $swap_free) / $swap_total * 100, 1) : 0,
    ];
}

function read_temp() {
    $zones = @glob('/sys/class/thermal/thermal_zone*/temp');
    if (!$zones) return null;
    $zonas = [];
    $max = 0;
    foreach ($zones as $z) {
        $val = @file_get_contents($z);
        if ($val === false) continue;
        $val = (float)trim($val);
        if ($val <= 0) continue;
        if ($val > 1000) $val = $val / 1000.0;
        $type = @file_get_contents(dirname($z) . '/type');
        $zonas[] = ['type' => $type ? trim($type) : 'zone', 'value' => round($val, 1)];
        if ($val > $max) $max = $val;
    }
    if (empty($zonas)) return null;
    return ['main' => round($max, 1), 'zones' => $zonas];
}

function read_uptime() {
    if (!is_readable('/proc/uptime')) return null;
    $parts = explode(' ', trim(file_get_contents('/proc/uptime')));
    $u = (int)floatval($parts[0]);
    $d = floor($u / 86400);
    $h = floor(($u % 86400) / 3600);
    $m = floor(($u % 3600) / 60);
    $human = ($d > 0 ? "{$d}d " : '') . ($h > 0 || $d > 0 ? "{$h}h " : '') . "{$m}m";
    return ['seconds' => $u, 'human' => trim($human)];
}

function read_load() {
    if (!is_readable('/proc/loadavg')) return null;
    $l = explode(' ', trim(file_get_contents('/proc/loadavg')));
    return ['1m' => (float)$l[0], '5m' => (float)$l[1], '15m' => (float)$l[2]];
}

function read_cpu() {
    if (!is_readable('/proc/stat')) return null;
    $a = @file('/proc/stat');
    if (!$a) return null;
    usleep(150000);
    $b = @file('/proc/stat');
    if (!$b) return null;
    $pa = preg_split('/\s+/', trim($a[0]));
    $pb = preg_split('/\s+/', trim($b[0]));
    array_shift($pa); array_shift($pb);
    $sa = array_sum(array_map('intval', $pa));
    $sb = array_sum(array_map('intval', $pb));
    $idle_a = (int)$pa[3]; $idle_b = (int)$pb[3];
    $td = $sb - $sa; $id = $idle_b - $idle_a;
    if ($td <= 0) return null;
    return round((1 - $id / $td) * 100, 1);
}

function read_cores() {
    if (!is_readable('/proc/cpuinfo')) return null;
    $n = 0;
    foreach (file('/proc/cpuinfo') as $line) {
        if (strpos($line, 'processor') === 0) $n++;
    }
    return $n > 0 ? $n : null;
}

function read_disk() {
    $candidatos = [
        '/data/data/com.termux/files/home',
        dirname(__DIR__),
        __DIR__,
        '/',
    ];
    $path = null;
    foreach ($candidatos as $c) {
        if (@is_dir($c)) { $path = $c; break; }
    }
    if (!$path) return null;
    $total = @disk_total_space($path);
    $free  = @disk_free_space($path);
    if (!$total) return null;
    $used = $total - $free;
    return [
        'total' => $total, 'used' => $used, 'free' => $free,
        'percent' => round($used / $total * 100, 1),
        'total_h' => fmt_bytes($total), 'used_h' => fmt_bytes($used), 'free_h' => fmt_bytes($free),
        'mount' => $path,
    ];
}

function read_db_stat($conexion) {
    $r = @mysqli_query($conexion, "SHOW STATUS WHERE Variable_name IN ('Threads_connected','Uptime','Queries')");
    if (!$r) return null;
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) $out[$row['Variable_name']] = $row['Value'];
    return $out;
}

$data = [
    'ts' => date('H:i:s'),
    'host' => php_uname('n'),
    'os' => php_uname('s') . ' ' . php_uname('r'),
    'cpu' => read_cpu(),
    'cores' => read_cores(),
    'load' => read_load(),
    'mem' => read_mem(),
    'temp' => read_temp(),
    'disk' => read_disk(),
    'uptime' => read_uptime(),
    'php' => PHP_VERSION,
    'db' => read_db_stat($conexion),
];

echo json_encode($data);
