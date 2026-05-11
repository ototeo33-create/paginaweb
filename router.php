<?php
$uri  = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

if (strpos($path, '/intep/') === 0) {
    $path = substr($path, 6);
} elseif ($path === '/intep') {
    $path = '/';
}

if ($path === '/' || $path === '') {
    $path = '/login.php';
}

$file = __DIR__ . $path;

if (is_dir($file)) {
    if (file_exists($file . '/index.php')) {
        $_SERVER['SCRIPT_FILENAME'] = $file . '/index.php';
        chdir(dirname($file . '/index.php'));
        require $file . '/index.php';
        return true;
    }
    return false;
}

if (file_exists($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        $_SERVER['SCRIPT_FILENAME'] = $file;
        chdir(dirname($file));
        require $file;
        return true;
    }
    $mime = [
        'css' => 'text/css', 'js' => 'application/javascript',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        'json' => 'application/json', 'webmanifest' => 'application/manifest+json',
        'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'webp' => 'image/webp',
    ];
    if (isset($mime[$ext])) {
        header('Content-Type: ' . $mime[$ext]);
    }
    readfile($file);
    return true;
}

http_response_code(404);
echo "404 Not Found: $path";
return true;
