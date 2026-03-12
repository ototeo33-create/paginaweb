-- ============================================
-- DATOS DE PRUEBA PARA INTEP
-- Ejecutar en phpMyAdmin o cliente MySQL
-- ============================================

-- 1. Verificar y usar la base de datos
USE intep_portal;

-- 2. Insertar programas (si no existen)
INSERT IGNORE INTO programas (id, nombre, codigo, estado) VALUES 
(1, 'Técnico en Sistemas', 'TS', 'activo'),
(2, 'Técnico en Contabilidad', 'TC', 'activo'),
(3, 'Técnico en Secretariado', 'TSEC', 'activo'),
(4, 'Técnico en Alimentación y Hostelería', 'TAH', 'activo');

-- 3. Insertar materias por programa
INSERT IGNORE INTO materias (id, nombre, programa_id, estado) VALUES 
-- Técnico en Sistemas
(1, 'Fundamentos de TI', 1, 'activo'),
(2, 'Redes Básicas', 1, 'activo'),
(3, 'Sistemas Operativos', 1, 'activo'),
(4, 'Programación Web', 1, 'activo'),
(5, 'Base de Datos', 1, 'activo'),
(6, 'Mantenimiento PC', 1, 'activo'),
-- Técnico en Contabilidad
(7, 'Contabilidad Básica', 2, 'activo'),
(8, 'Contabilidad Financiera', 2, 'activo'),
(9, 'Costos y Presupuesto', 2, 'activo'),
(10, 'Normas IFRS', 2, 'activo'),
(11, 'Tributación', 2, 'activo'),
(12, 'Facturación', 2, 'activo'),
-- Técnico en Secretariado
(13, 'Ortografía', 3, 'activo'),
(14, 'Arquivo', 3, 'activo'),
(15, 'Atención al Cliente', 3, 'activo'),
(16, 'Procesamiento de Texto', 3, 'activo'),
(17, 'Comunicación Empresarial', 3, 'activo'),
(18, 'Protocolo', 3, 'activo'),
-- Técnico en Alimentación
(19, 'Manipulación de Alimentos', 4, 'activo'),
(20, 'Servicios de Restaurante', 4, 'activo'),
(21, 'Cocina Básica', 4, 'activo'),
(22, 'Nutrición', 4, 'activo'),
(23, 'Gestión de Bodega', 4, 'activo'),
(24, 'Higiene', 4, 'activo');

-- 4. Crear usuarios docentes (si no existen)
INSERT IGNORE INTO usuarios (id, username, password_hash, rol, estado) VALUES 
(101, 'docente1', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'docente', 'activo'),
(102, 'docente2', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'docente', 'activo'),
(103, 'docente3', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'docente', 'activo'),
(104, 'docente4', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'docente', 'activo');

-- Hash para 'docente123' = $2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm

-- 5. Crear módulos
INSERT IGNORE INTO modulos (id, materia_id, nombre, bimestre, orden, docente_id, estado) VALUES 
(1, 1, 'Fundamentos de TI - Bimestre 1', 1, 1, 101, 'activo'),
(2, 1, 'Fundamentos de TI - Bimestre 2', 2, 1, 101, 'activo'),
(3, 2, 'Redes Básicas - Bimestre 1', 1, 2, 102, 'activo'),
(4, 2, 'Redes Básicas - Bimestre 2', 2, 2, 102, 'activo'),
(5, 3, 'Sistemas Operativos - Bimestre 1', 1, 3, 103, 'activo'),
(6, 3, 'Sistemas Operativos - Bimestre 2', 2, 3, 103, 'activo'),
(7, 4, 'Programación Web - Bimestre 1', 1, 4, 104, 'activo'),
(8, 4, 'Programación Web - Bimestre 2', 2, 4, 104, 'activo'),
(9, 7, 'Contabilidad Básica - Bimestre 1', 1, 1, 101, 'activo'),
(10, 7, 'Contabilidad Básica - Bimestre 2', 2, 1, 102, 'activo'),
(11, 13, 'Ortografía - Bimestre 1', 1, 1, 103, 'activo'),
(12, 13, 'Ortografía - Bimestre 2', 2, 1, 104, 'activo'),
(13, 19, 'Manipulación de Alimentos - Bimestre 1', 1, 1, 101, 'activo'),
(14, 19, 'Manipulación de Alimentos - Bimestre 2', 2, 1, 102, 'activo');

-- 6. Crear conceptos de cobro
-- Agregar columna num_cuotas si no existe
-- ALTER TABLE conceptos_cobro ADD COLUMN IF NOT EXISTS num_cuotas INT NOT NULL DEFAULT 1 AFTER tipo;
INSERT IGNORE INTO conceptos_cobro (id, nombre, descripcion, monto_base, tipo, num_cuotas, estado) VALUES
(1, 'Mensualidad', 'Cuota mensual del programa técnico (10 meses)', 212000, 'mensualidad', 10, 'activo'),
(2, 'Seminario Excel Intermedio', 'Seminario obligatorio adicional al programa', 320000, 'seminario', 1, 'activo'),
(3, 'Derechos de Grado', 'Ceremonia de grado y celebración (PROM)', 450000, 'otro', 1, 'activo'),
(4, 'Mensualidad Inglés', 'Cuota mensual del programa de inglés (4 meses por nivel)', 145000, 'mensualidad', 4, 'activo');

-- 6b. Agregar programas de inglés
INSERT IGNORE INTO programas (nombre, codigo, estado) VALUES
('Inglés A1', 'ING-A1', 'activo'),
('Inglés A2', 'ING-A2', 'activo'),
('Inglés B1', 'ING-B1', 'activo');

-- 7. Crear estudiantes (20 estudiantes = 5 por programa)
INSERT IGNORE INTO estudiantes (id, nombre, documento, email, programa_id, fecha_ingreso, estado) VALUES 
(1, 'Juan González', 'TS000125', 'juan.gonzalez@estudiante.intep.edu.co', 1, '2025-01-15', 'activo'),
(2, 'María Rodríguez', 'TS000225', 'maria.rodriguez@estudiante.intep.edu.co', 1, '2025-01-15', 'activo'),
(3, 'Carlos Martínez', 'TS000325', 'carlos.martinez@estudiante.intep.edu.co', 1, '2025-01-15', 'activo'),
(4, 'Ana López', 'TS000425', 'ana.lopez@estudiante.intep.edu.co', 1, '2025-01-15', 'activo'),
(5, 'Pedro Hernández', 'TS000525', 'pedro.hernandez@estudiante.intep.edu.co', 1, '2025-01-15', 'activo'),
(6, 'Laura Pérez', 'TC000125', 'laura.perez@estudiante.intep.edu.co', 2, '2025-01-15', 'activo'),
(7, 'Jorge Sánchez', 'TC000225', 'jorge.sanchez@estudiante.intep.edu.co', 2, '2025-01-15', 'activo'),
(8, 'Sofia Ramírez', 'TC000325', 'sofia.ramirez@estudiante.intep.edu.co', 2, '2025-01-15', 'activo'),
(9, 'Miguel Torres', 'TC000425', 'miguel.torres@estudiante.intep.edu.co', 2, '2025-01-15', 'activo'),
(10, 'Carmen Flores', 'TC000525', 'carmen.flores@estudiante.intep.edu.co', 2, '2025-01-15', 'activo'),
(11, 'Diego Rivera', 'TSEC00125', 'diego.rivera@estudiante.intep.edu.co', 3, '2025-01-15', 'activo'),
(12, 'Patricia Gómez', 'TSEC00225', 'patricia.gomez@estudiante.intep.edu.co', 3, '2025-01-15', 'activo'),
(13, 'Fernando Díaz', 'TSEC00325', 'fernando.diaz@estudiante.intep.edu.co', 3, '2025-01-15', 'activo'),
(14, 'Lorena Reyes', 'TSEC00425', 'lorena.reyes@estudiante.intep.edu.co', 3, '2025-01-15', 'activo'),
(15, 'Ricardo Cruz', 'TSEC00525', 'ricardo.cruz@estudiante.intep.edu.co', 3, '2025-01-15', 'activo'),
(16, 'Gabriela Morales', 'TAH00125', 'gabriela.morales@estudiante.intep.edu.co', 4, '2025-01-15', 'activo'),
(17, 'Eduardo Ortiz', 'TAH00225', 'eduardo.ortiz@estudiante.intep.edu.co', 4, '2025-01-15', 'activo'),
(18, 'Rosa Gutiérrez', 'TAH00325', 'rosa.gutierrez@estudiante.intep.edu.co', 4, '2025-01-15', 'activo'),
(19, 'Luis Chávez', 'TAH00425', 'luis.chavez@estudiante.intep.edu.co', 4, '2025-01-15', 'activo'),
(20, 'Elena Ramos', 'TAH00525', 'elena.ramos@estudiante.intep.edu.co', 4, '2025-01-15', 'activo');

-- Hash para 'estudiante123' = $2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm

-- 8. Crear usuarios de estudiantes
INSERT IGNORE INTO usuarios (id, username, password_hash, rol, estudiante_id, estado) VALUES 
(201, 'TS000125', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 1, 'activo'),
(202, 'TS000225', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 2, 'activo'),
(203, 'TS000325', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 3, 'activo'),
(204, 'TS000425', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 4, 'activo'),
(205, 'TS000525', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 5, 'activo'),
(206, 'TC000125', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 6, 'activo'),
(207, 'TC000225', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 7, 'activo'),
(208, 'TC000325', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 8, 'activo'),
(209, 'TC000425', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 9, 'activo'),
(210, 'TC000525', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 10, 'activo'),
(211, 'TSEC00125', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 11, 'activo'),
(212, 'TSEC00225', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 12, 'activo'),
(213, 'TSEC00325', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 13, 'activo'),
(214, 'TSEC00425', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 14, 'activo'),
(215, 'TSEC00525', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 15, 'activo'),
(216, 'TAH00125', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 16, 'activo'),
(217, 'TAH00225', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 17, 'activo'),
(218, 'TAH00325', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 18, 'activo'),
(219, 'TAH00425', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 19, 'activo'),
(220, 'TAH00525', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm', 'estudiante', 20, 'activo');

-- 9. Generar cobros para estudiantes (mensualidades $212,000 x 10 cuotas)
INSERT IGNORE INTO cobros (id, estudiante_id, concepto_id, periodo, monto, descuento, total, pagado, saldo, fecha_vencimiento, estado) VALUES
-- Estudiante 1 - TS000125 (3 cuotas pagadas, 1 parcial)
(1, 1, 1, '2026-01', 212000, 0, 212000, 212000, 0, '2026-02-15', 'pagado'),
(2, 1, 1, '2026-02', 212000, 0, 212000, 212000, 0, '2026-03-15', 'pagado'),
(3, 1, 1, '2026-03', 212000, 0, 212000, 100000, 112000, '2026-04-15', 'parcial'),
(4, 1, 1, '2026-04', 212000, 0, 212000, 0, 212000, '2026-05-15', 'pendiente'),
-- Estudiante 2 - TS000225 (3 cuotas pagadas)
(5, 2, 1, '2026-01', 212000, 0, 212000, 212000, 0, '2026-02-15', 'pagado'),
(6, 2, 1, '2026-02', 212000, 0, 212000, 212000, 0, '2026-03-15', 'pagado'),
(7, 2, 1, '2026-03', 212000, 0, 212000, 212000, 0, '2026-04-15', 'pagado'),
(8, 2, 1, '2026-04', 212000, 0, 212000, 0, 212000, '2026-05-15', 'pendiente'),
-- Estudiante 3 - TS000325 (moroso, nada pagado)
(9, 3, 1, '2026-01', 212000, 0, 212000, 0, 212000, '2026-02-15', 'vencido'),
(10, 3, 1, '2026-02', 212000, 0, 212000, 0, 212000, '2026-03-15', 'vencido'),
(11, 3, 1, '2026-03', 212000, 0, 212000, 0, 212000, '2026-04-15', 'pendiente'),
(12, 3, 1, '2026-04', 212000, 0, 212000, 0, 212000, '2026-05-15', 'pendiente'),
-- Estudiante 4 - TS000425 (2 cuotas pagadas)
(13, 4, 1, '2026-01', 212000, 0, 212000, 212000, 0, '2026-02-15', 'pagado'),
(14, 4, 1, '2026-02', 212000, 0, 212000, 212000, 0, '2026-03-15', 'pagado'),
(15, 4, 1, '2026-03', 212000, 0, 212000, 0, 212000, '2026-04-15', 'pendiente'),
(16, 4, 1, '2026-04', 212000, 0, 212000, 0, 212000, '2026-05-15', 'pendiente'),
-- Estudiante 5 - TS000525 (1 cuota pagada, 1 parcial)
(17, 5, 1, '2026-01', 212000, 0, 212000, 212000, 0, '2026-02-15', 'pagado'),
(18, 5, 1, '2026-02', 212000, 0, 212000, 106000, 106000, '2026-03-15', 'parcial'),
(19, 5, 1, '2026-03', 212000, 0, 212000, 0, 212000, '2026-04-15', 'pendiente'),
(20, 5, 1, '2026-04', 212000, 0, 212000, 0, 212000, '2026-05-15', 'pendiente');

-- Completar cobros para estudiantes 6-20 (mensualidades)
INSERT INTO cobros (estudiante_id, concepto_id, periodo, monto, descuento, total, pagado, saldo, fecha_vencimiento, estado)
SELECT
    e.id, 1,
    CONCAT('2026-', LPAD(m.id, 2, '0')),
    212000, 0, 212000,
    CASE
        WHEN m.id <= 2 AND e.id % 3 != 0 THEN 212000
        WHEN m.id = 1 AND e.id % 3 = 0 THEN 212000
        ELSE 0
    END,
    CASE
        WHEN m.id <= 2 AND e.id % 3 != 0 THEN 0
        WHEN m.id = 1 AND e.id % 3 = 0 THEN 0
        ELSE 212000
    END,
    DATE_ADD('2026-01-15', INTERVAL m.id MONTH),
    CASE
        WHEN m.id <= 2 AND e.id % 3 != 0 THEN 'pagado'
        WHEN m.id = 1 AND e.id % 3 = 0 THEN 'pagado'
        WHEN m.id <= 2 AND e.id % 3 = 0 THEN 'vencido'
        ELSE 'pendiente'
    END
FROM estudiantes e
CROSS JOIN (SELECT 1 as id UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) m
WHERE e.id >= 6 AND e.id <= 20
AND NOT EXISTS (SELECT 1 FROM cobros c WHERE c.estudiante_id = e.id AND c.concepto_id = 1 AND c.periodo = CONCAT('2026-', LPAD(m.id, 2, '0')));

-- 10. Crear horarios
INSERT IGNORE INTO horarios (id, modulo_id, dia, hora_inicio, hora_fin, aula, docente_id, estado) VALUES 
(1, 1, 'Lunes', '06:45:00', '08:45:00', 'Aula-1', 101, 'activo'),
(2, 1, 'Miércoles', '06:45:00', '08:45:00', 'Aula-1', 101, 'activo'),
(3, 2, 'Martes', '08:45:00', '10:45:00', 'Aula-2', 101, 'activo'),
(4, 2, 'Jueves', '08:45:00', '10:45:00', 'Aula-2', 101, 'activo'),
(5, 3, 'Lunes', '08:45:00', '10:45:00', 'Aula-3', 102, 'activo'),
(6, 3, 'Miércoles', '08:45:00', '10:45:00', 'Aula-3', 102, 'activo'),
(7, 9, 'Martes', '06:45:00', '08:45:00', 'Aula-1', 101, 'activo'),
(8, 9, 'Jueves', '06:45:00', '08:45:00', 'Aula-1', 101, 'activo'),
(9, 11, 'Lunes', '10:45:00', '12:45:00', 'Aula-4', 103, 'activo'),
(10, 11, 'Miércoles', '10:45:00', '12:45:00', 'Aula-4', 103, 'activo'),
(11, 13, 'Viernes', '06:45:00', '08:45:00', 'Aula-5', 101, 'activo'),
(12, 13, 'Viernes', '08:45:00', '10:45:00', 'Aula-5', 102, 'activo');

-- 11. Crear pagos (mensualidades de $212,000)
INSERT IGNORE INTO pagos (id, cobro_id, estudiante_id, monto, fecha_pago, metodo_pago, referencia, observaciones, registrado_por) VALUES
(1, 1, 1, 212000, '2026-02-10', 'transferencia', 'TRF00001', 'Cuota 1', 1),
(2, 2, 1, 212000, '2026-03-10', 'efectivo', 'EFE00002', 'Cuota 2', 1),
(3, 3, 1, 100000, '2026-04-05', 'consignacion', 'CON00003', 'Abono cuota 3', 1),
(4, 5, 2, 212000, '2026-02-08', 'consignacion', 'CON00004', 'Cuota 1', 1),
(5, 6, 2, 212000, '2026-03-05', 'transferencia', 'TRF00005', 'Cuota 2', 1),
(6, 7, 2, 212000, '2026-04-12', 'efectivo', 'EFE00006', 'Cuota 3', 1),
(7, 13, 4, 212000, '2026-02-15', 'transferencia', 'TRF00007', 'Cuota 1', 1),
(8, 14, 4, 212000, '2026-03-08', 'efectivo', 'EFE00008', 'Cuota 2', 1),
(9, 17, 5, 212000, '2026-02-12', 'consignacion', 'CON00009', 'Cuota 1', 1),
(10, 18, 5, 106000, '2026-03-15', 'transferencia', 'TRF00010', 'Abono cuota 2', 1);

SELECT 'Datos de prueba generados correctamente!' as resultado;
