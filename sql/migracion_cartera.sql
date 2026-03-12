-- ============================================
-- MIGRACIÓN: Reorganizar conceptos de cobro
-- Ejecutar en phpMyAdmin o cliente MySQL
-- ============================================

USE intep_portal;

-- 1. Agregar columna num_cuotas a conceptos_cobro
ALTER TABLE conceptos_cobro ADD COLUMN IF NOT EXISTS num_cuotas INT NOT NULL DEFAULT 1 AFTER tipo;

-- 2. Limpiar cobros y pagos de prueba existentes
DELETE FROM pagos;
DELETE FROM cobros;

-- 3. Limpiar conceptos de prueba
DELETE FROM conceptos_cobro;

-- 4. Insertar conceptos reales
INSERT INTO conceptos_cobro (id, nombre, descripcion, monto_base, tipo, num_cuotas, estado) VALUES
(1, 'Mensualidad', 'Cuota mensual del programa técnico (10 meses)', 212000, 'mensualidad', 10, 'activo'),
(2, 'Seminario Excel Intermedio', 'Seminario obligatorio adicional al programa', 320000, 'seminario', 1, 'activo'),
(3, 'Derechos de Grado', 'Ceremonia de grado y celebración (PROM)', 450000, 'otro', 1, 'activo'),
(4, 'Mensualidad Inglés', 'Cuota mensual del programa de inglés (4 meses por nivel)', 145000, 'mensualidad', 4, 'activo');

-- 5. Agregar programas de inglés si no existen
INSERT IGNORE INTO programas (nombre, codigo, estado) VALUES
('Inglés A1', 'ING-A1', 'activo'),
('Inglés A2', 'ING-A2', 'activo'),
('Inglés B1', 'ING-B1', 'activo');

SELECT 'Migración completada!' as resultado;
