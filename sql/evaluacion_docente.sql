-- ============================================
-- MIGRACION: Sistema de Evaluacion de Desempeno Docente
-- Base de datos: intep_portal
-- Fecha: 2026-04-14
-- ============================================

-- 1. Control global de evaluacion (admin activa/desactiva por periodo)
CREATE TABLE IF NOT EXISTS eval_control (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periodo VARCHAR(50) NOT NULL,
    activa TINYINT(1) DEFAULT 0,
    fecha_inicio DATETIME NULL,
    fecha_fin DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_periodo (periodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Evaluacion cabecera (1 registro por estudiante/docente/periodo)
CREATE TABLE IF NOT EXISTS eval_docente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    docente_id INT NOT NULL,
    programa_modulo_id INT NOT NULL,
    periodo VARCHAR(50) NOT NULL,
    comentarios_positivos TEXT NULL,
    comentarios_mejora TEXT NULL,
    puntaje_total TINYINT NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_eval_unica (estudiante_id, docente_id, periodo),
    KEY idx_docente_periodo (docente_id, periodo),
    KEY idx_periodo (periodo),
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (programa_modulo_id) REFERENCES programa_modulo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Respuestas individuales por criterio (8 filas por evaluacion)
CREATE TABLE IF NOT EXISTS eval_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluacion_id INT NOT NULL,
    criterio_id TINYINT NOT NULL COMMENT '1-8',
    calificacion TINYINT NOT NULL COMMENT '1-4',
    FOREIGN KEY (evaluacion_id) REFERENCES eval_docente(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar periodo actual por defecto (inactivo)
INSERT IGNORE INTO eval_control (periodo, activa) VALUES ('2025-2026 II', 0);
