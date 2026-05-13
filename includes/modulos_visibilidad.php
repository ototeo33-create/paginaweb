<?php
// ============================================================
// SISTEMA DE VISIBILIDAD DE MÓDULOS
// Permite al admin activar/desactivar módulos del portal sin
// tocar código. Se aplica solo a roles no-admin.
// ============================================================

if (!function_exists('modulos_visibilidad_init')) {

    /**
     * Crea la tabla si no existe y siembra los módulos por defecto.
     * Se llama una vez por request si es necesario.
     */
    function modulos_visibilidad_init($conexion) {
        static $inicializado = false;
        if ($inicializado) return;
        $inicializado = true;

        // Crear tabla si no existe
        $chk = mysqli_query($conexion, "SHOW TABLES LIKE 'modulos_visibilidad'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            mysqli_query($conexion, "
                CREATE TABLE IF NOT EXISTS modulos_visibilidad (
                    modulo_key VARCHAR(50) PRIMARY KEY,
                    nombre VARCHAR(100) NOT NULL,
                    descripcion VARCHAR(255) NULL,
                    habilitado TINYINT(1) NOT NULL DEFAULT 1,
                    mensaje_bloqueo VARCHAR(255) NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Sembrar módulos por defecto
        $defaults = [
            ['cartera',       'Cartera y Pagos',         'Estado de cuenta, recibos y cuotas'],
            ['practicas',     'Prácticas Profesionales', 'Guía de tipos y seguimiento mensual'],
            ['materiales',    'Materiales de Estudio',   'Descarga de PDFs y recursos por módulo'],
            ['solicitudes',   'Solicitudes',             'Certificados, constancias y trámites'],
            ['idiomas',       'INTEP Inglés',            'Ejercicios con IA, ranking y niveles'],
            ['cursoingles',   'Módulos de Inglés',       'Flashcards, juegos y misiones de rol'],
            ['intep_kids',    'INTEP Kids',              'Inglés para Primera Infancia'],
            ['almacenamiento','Curso Almacenamiento',    'Técnicas de almacenamiento y bodega'],
            ['evaluacion',    'Evaluación Docente',      'Encuesta de evaluación a docentes'],
        ];

        foreach ($defaults as $d) {
            $stmt = mysqli_prepare($conexion,
                "INSERT IGNORE INTO modulos_visibilidad (modulo_key, nombre, descripcion, habilitado) VALUES (?, ?, ?, 1)"
            );
            mysqli_stmt_bind_param($stmt, 'sss', $d[0], $d[1], $d[2]);
            mysqli_stmt_execute($stmt);
        }
    }

    /**
     * Carga todos los estados de módulos en cache.
     */
    function modulos_visibilidad_cargar($conexion) {
        static $cache = null;
        if ($cache !== null) return $cache;

        modulos_visibilidad_init($conexion);
        $cache = [];

        $r = mysqli_query($conexion, "SELECT modulo_key, nombre, descripcion, habilitado, mensaje_bloqueo FROM modulos_visibilidad");
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $cache[$row['modulo_key']] = $row;
            }
        }
        return $cache;
    }

    /**
     * Devuelve true si el módulo está habilitado.
     * Los admins ven todo siempre.
     */
    function modulo_habilitado($conexion, $modulo_key) {
        if (($_SESSION['usuario_rol'] ?? '') === 'admin') return true;
        $cache = modulos_visibilidad_cargar($conexion);
        if (!isset($cache[$modulo_key])) return true; // por defecto visible si no está registrado
        return (bool)$cache[$modulo_key]['habilitado'];
    }

    /**
     * Mensaje personalizado que el admin definió para mostrar
     * cuando el módulo está deshabilitado.
     */
    function modulo_mensaje_bloqueo($conexion, $modulo_key) {
        $cache = modulos_visibilidad_cargar($conexion);
        return $cache[$modulo_key]['mensaje_bloqueo'] ?? null;
    }

    /**
     * Devuelve nombre legible del módulo.
     */
    function modulo_nombre($conexion, $modulo_key) {
        $cache = modulos_visibilidad_cargar($conexion);
        return $cache[$modulo_key]['nombre'] ?? $modulo_key;
    }

    /**
     * Si el módulo NO está habilitado, muestra una página de bloqueo y termina.
     * Llamar al inicio de cualquier página de módulo (mi_cartera.php, practicas.php, etc).
     */
    function requerir_modulo($conexion, $modulo_key) {
        if (modulo_habilitado($conexion, $modulo_key)) return;

        $nombre  = modulo_nombre($conexion, $modulo_key);
        $mensaje = modulo_mensaje_bloqueo($conexion, $modulo_key)
                 ?: 'Este módulo no está disponible en este momento. Vuelve a intentarlo más tarde.';

        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($nombre) ?> — No disponible</title>
            <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
            <link rel="stylesheet" href="/intep/css/estilos.css">
            <style>
                body { background: #f8f9fc; }
                .bloq-wrap {
                    max-width: 540px;
                    margin: 5rem auto;
                    background: white;
                    border-radius: 20px;
                    padding: 3rem 2.5rem;
                    text-align: center;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
                }
                .bloq-icon {
                    width: 80px; height: 80px;
                    margin: 0 auto 1.5rem;
                    background: linear-gradient(135deg,#FEF3C7,#FDE68A);
                    border-radius: 50%;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 2.5rem;
                }
                .bloq-wrap h1 {
                    font-size: 1.4rem;
                    font-weight: 800;
                    color: #111827;
                    margin: 0 0 0.6rem;
                }
                .bloq-wrap p {
                    color: #6B7280;
                    font-size: 0.92rem;
                    line-height: 1.6;
                    margin: 0 0 2rem;
                }
                .bloq-btn {
                    display: inline-block;
                    background: linear-gradient(135deg, #059669, #10B981);
                    color: white;
                    padding: 12px 28px;
                    border-radius: 50px;
                    text-decoration: none;
                    font-weight: 700;
                    font-size: 0.9rem;
                    transition: transform 0.2s;
                }
                .bloq-btn:hover { transform: translateY(-2px); }
            </style>
        </head>
        <body>
            <div class="bloq-wrap">
                <div class="bloq-icon">🔒</div>
                <h1><?= htmlspecialchars($nombre) ?> no está disponible</h1>
                <p><?= htmlspecialchars($mensaje) ?></p>
                <a href="/intep/dashboard.php" class="bloq-btn">← Volver al inicio</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
