<?php
/**
 * MIGRACIÓN: Nuevo modelo de módulos de formación
 *
 * Transforma el esquema:
 *   materias (programa_id) → modulos (materia_id)
 * Al nuevo esquema:
 *   modulos_formacion (catálogo maestro) ← programa_modulo (pivot N:N) → programas
 *
 * IMPORTANTE: Ejecutar UNA sola vez. Hacer backup de la BD antes.
 */
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    die('Acceso denegado. Solo administradores.');
}

$errores = [];
$pasos = [];

function paso($msg) {
    global $pasos;
    $pasos[] = $msg;
    echo "<p>✅ $msg</p>";
    flush();
}

function error($msg) {
    global $errores;
    $errores[] = $msg;
    echo "<p>❌ $msg</p>";
    flush();
}

// Verificar si ya se ejecutó la migración
$check = mysqli_query($conexion, "SHOW TABLES LIKE 'modulos_formacion'");
if (mysqli_num_rows($check) > 0) {
    die('<h2>⚠️ La migración ya fue ejecutada</h2><p>La tabla modulos_formacion ya existe.</p><a href="gestionar_modulos.php">← Volver</a>');
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Migración BD</title>
<style>body{font-family:monospace;padding:2rem;max-width:800px;margin:0 auto;background:#1a1a2e;color:#e0e0e0;}
h2{color:#10B981;}p{margin:0.3rem 0;}.error{color:#ef4444;}</style></head><body>';
echo '<h2>🔄 Migración: Nuevo modelo de módulos</h2>';

// ============================================
// PASO 1: Crear tabla modulos_formacion
// ============================================
$sql = "CREATE TABLE modulos_formacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(20) DEFAULT NULL,
    estado VARCHAR(20) DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (mysqli_query($conexion, $sql)) {
    paso('Tabla modulos_formacion creada.');
} else {
    error('Error creando modulos_formacion: ' . mysqli_error($conexion));
}

// ============================================
// PASO 2: Crear tabla programa_modulo (pivot)
// ============================================
$sql = "CREATE TABLE programa_modulo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    programa_id INT NOT NULL,
    modulo_formacion_id INT NOT NULL,
    bimestre INT DEFAULT NULL,
    orden INT DEFAULT 1,
    tipo ENUM('especifico','transversal','basico') DEFAULT 'especifico',
    docente_id INT DEFAULT NULL,
    estado VARCHAR(20) DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programa_id) REFERENCES programas(id),
    FOREIGN KEY (modulo_formacion_id) REFERENCES modulos_formacion(id),
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_prog_mod_bim (programa_id, modulo_formacion_id, bimestre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (mysqli_query($conexion, $sql)) {
    paso('Tabla programa_modulo creada.');
} else {
    error('Error creando programa_modulo: ' . mysqli_error($conexion));
}

// ============================================
// PASO 3: Migrar datos de materias → modulos_formacion
// ============================================
// Insertar nombres únicos de materias como módulos de formación
$sql = "INSERT IGNORE INTO modulos_formacion (nombre)
        SELECT DISTINCT nombre FROM materias WHERE nombre IS NOT NULL AND nombre != ''";
$res = mysqli_query($conexion, $sql);
if ($res) {
    $insertados = mysqli_affected_rows($conexion);
    paso("$insertados módulos únicos migrados de materias → modulos_formacion.");
} else {
    error('Error migrando materias: ' . mysqli_error($conexion));
}

// También insertar nombres únicos de la tabla modulos (pueden tener nombres diferentes)
$sql = "INSERT IGNORE INTO modulos_formacion (nombre)
        SELECT DISTINCT m.nombre FROM modulos m
        WHERE m.nombre IS NOT NULL AND m.nombre != ''
        AND m.nombre NOT IN (SELECT nombre FROM modulos_formacion)";
$res = mysqli_query($conexion, $sql);
if ($res) {
    $insertados = mysqli_affected_rows($conexion);
    paso("$insertados módulos adicionales migrados de modulos → modulos_formacion.");
} else {
    error('Error migrando modulos adicionales: ' . mysqli_error($conexion));
}

// ============================================
// PASO 4: Migrar datos de modulos → programa_modulo
// ============================================
// Crear mapeo: para cada módulo viejo, encontrar su modulo_formacion y programa
$sql = "INSERT INTO programa_modulo (programa_id, modulo_formacion_id, bimestre, orden, docente_id)
        SELECT
            mat.programa_id,
            mf.id,
            m.bimestre,
            m.orden,
            m.docente_id
        FROM modulos m
        JOIN materias mat ON m.materia_id = mat.id
        JOIN modulos_formacion mf ON m.nombre = mf.nombre
        WHERE mat.programa_id IS NOT NULL";
$res = mysqli_query($conexion, $sql);
if ($res) {
    $insertados = mysqli_affected_rows($conexion);
    paso("$insertados asignaciones programa↔módulo creadas en programa_modulo.");
} else {
    error('Error migrando a programa_modulo: ' . mysqli_error($conexion));
}

// ============================================
// PASO 5: Crear mapeo viejo_id → nuevo_id para actualizar FKs
// ============================================
// Crear tabla temporal de mapeo
$sql = "CREATE TEMPORARY TABLE mapeo_modulos AS
        SELECT m.id as modulo_viejo_id, pm.id as programa_modulo_id
        FROM modulos m
        JOIN materias mat ON m.materia_id = mat.id
        JOIN modulos_formacion mf ON m.nombre = mf.nombre
        JOIN programa_modulo pm ON pm.programa_id = mat.programa_id
            AND pm.modulo_formacion_id = mf.id
            AND pm.bimestre = m.bimestre";
if (mysqli_query($conexion, $sql)) {
    paso('Tabla de mapeo temporal creada.');
} else {
    error('Error creando mapeo: ' . mysqli_error($conexion));
}

// ============================================
// PASO 6: Agregar columna programa_modulo_id a tablas dependientes
// ============================================
$tablas_con_modulo_id = ['notas', 'asistencia', 'observaciones'];

foreach ($tablas_con_modulo_id as $tabla) {
    // Verificar si la tabla existe
    $check = mysqli_query($conexion, "SHOW TABLES LIKE '$tabla'");
    if (mysqli_num_rows($check) == 0) {
        paso("Tabla $tabla no existe, se omite.");
        continue;
    }

    // Agregar nueva columna
    $sql = "ALTER TABLE $tabla ADD COLUMN programa_modulo_id INT DEFAULT NULL";
    if (mysqli_query($conexion, $sql)) {
        paso("Columna programa_modulo_id agregada a $tabla.");
    } else {
        // Puede que ya exista
        paso("Columna programa_modulo_id ya existe en $tabla o error: " . mysqli_error($conexion));
    }

    // Actualizar con el mapeo
    $sql = "UPDATE $tabla t
            JOIN mapeo_modulos map ON t.modulo_id = map.modulo_viejo_id
            SET t.programa_modulo_id = map.programa_modulo_id";
    if (mysqli_query($conexion, $sql)) {
        $actualizados = mysqli_affected_rows($conexion);
        paso("$tabla: $actualizados registros actualizados con nuevo programa_modulo_id.");
    } else {
        error("Error actualizando $tabla: " . mysqli_error($conexion));
    }
}

// ============================================
// PASO 7: Migrar horarios (usan materia_id → programa_modulo)
// ============================================
// Los horarios usan materia_id (de la tabla materias), necesitamos mapearlos
// Primero agregar columna
$check_hor = mysqli_query($conexion, "SHOW TABLES LIKE 'horarios'");
if (mysqli_num_rows($check_hor) > 0) {
    $sql = "ALTER TABLE horarios ADD COLUMN programa_modulo_id INT DEFAULT NULL";
    if (mysqli_query($conexion, $sql)) {
        paso('Columna programa_modulo_id agregada a horarios.');
    } else {
        paso('Columna ya existe en horarios o error: ' . mysqli_error($conexion));
    }

    // Mapear: horarios.materia_id → materias.nombre → modulos_formacion → programa_modulo
    // Los horarios tienen programa_id, así que podemos hacer el match
    $sql = "UPDATE horarios h
            JOIN materias mat ON h.materia_id = mat.id
            JOIN modulos_formacion mf ON mat.nombre = mf.nombre
            JOIN programa_modulo pm ON pm.modulo_formacion_id = mf.id
                AND pm.programa_id = COALESCE(h.programa_id, mat.programa_id)
            SET h.programa_modulo_id = pm.id";
    if (mysqli_query($conexion, $sql)) {
        $actualizados = mysqli_affected_rows($conexion);
        paso("horarios: $actualizados registros mapeados al nuevo modelo.");
    } else {
        // Puede fallar si hay ambigüedades, no es crítico
        paso('horarios: mapeo parcial (puede requerir ajuste manual). ' . mysqli_error($conexion));
    }
}

// ============================================
// PASO 8: Migrar asistencias (tabla plural, usa materia_id)
// ============================================
$check_asist = mysqli_query($conexion, "SHOW TABLES LIKE 'asistencias'");
if (mysqli_num_rows($check_asist) > 0) {
    $sql = "ALTER TABLE asistencias ADD COLUMN programa_modulo_id INT DEFAULT NULL";
    if (mysqli_query($conexion, $sql)) {
        paso('Columna programa_modulo_id agregada a asistencias.');
    } else {
        paso('Columna ya existe en asistencias o error: ' . mysqli_error($conexion));
    }

    $sql = "UPDATE asistencias a
            JOIN materias mat ON a.materia_id = mat.id
            JOIN modulos_formacion mf ON mat.nombre = mf.nombre
            JOIN programa_modulo pm ON pm.modulo_formacion_id = mf.id
                AND pm.programa_id = mat.programa_id
            SET a.programa_modulo_id = pm.id";
    if (mysqli_query($conexion, $sql)) {
        $actualizados = mysqli_affected_rows($conexion);
        paso("asistencias: $actualizados registros mapeados.");
    } else {
        paso('asistencias: mapeo parcial. ' . mysqli_error($conexion));
    }
}

// ============================================
// PASO 9: Agregar FKs a programa_modulo (sin borrar columnas viejas aún)
// ============================================
foreach ($tablas_con_modulo_id as $tabla) {
    $check = mysqli_query($conexion, "SHOW TABLES LIKE '$tabla'");
    if (mysqli_num_rows($check) == 0) continue;

    $sql = "ALTER TABLE $tabla ADD FOREIGN KEY (programa_modulo_id) REFERENCES programa_modulo(id)";
    if (mysqli_query($conexion, $sql)) {
        paso("FK programa_modulo_id agregada a $tabla.");
    } else {
        paso("FK en $tabla: " . mysqli_error($conexion));
    }
}

// ============================================
// RESUMEN
// ============================================
echo '<hr>';
if (empty($errores)) {
    echo '<h2 style="color:#10B981;">✅ Migración completada exitosamente</h2>';
    echo '<p><strong>Nota:</strong> Las columnas viejas (modulo_id, materia_id) se mantienen temporalmente como respaldo.</p>';
    echo '<p>Una vez verificado que todo funciona, se pueden eliminar con una segunda migración.</p>';
} else {
    echo '<h2 style="color:#ef4444;">⚠️ Migración completada con errores</h2>';
    echo '<ul>';
    foreach ($errores as $e) echo "<li class='error'>$e</li>";
    echo '</ul>';
}

echo '<p style="margin-top:1rem;"><strong>Pasos ejecutados:</strong> ' . count($pasos) . '</p>';
echo '<p><strong>Errores:</strong> ' . count($errores) . '</p>';
echo '<br><a href="gestionar_modulos.php" style="color:#10B981;">← Ir a Gestionar Módulos</a>';
echo '</body></html>';
