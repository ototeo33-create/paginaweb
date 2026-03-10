<?php
// ============================================
// CARGADOR DE VARIABLES DE ENTORNO — INTEP
// ============================================

class Config {
    private static $loaded = false;
    private static $vars = [];

    public static function load() {
        if (self::$loaded) return;

        $envFile = __DIR__ . '/.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Quitar comillas si existen
                $value = trim($value, '"\'');
                
                self::$vars[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        self::load();
        return self::$vars[$key] ?? $default;
    }

    public static function all() {
        self::load();
        return self::$vars;
    }
}

// Cargar configuración al incluir
Config::load();
