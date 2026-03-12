<?php
/**
 * Script de Generación de Datos de Prueba - INTEP
 * Ejecutar una sola vez: php generar_datos_prueba.php
 */

require_once __DIR__ . '/config.php';

echo "=========================================\n";
echo "  GENERANDO DATOS DE PRUEBA - INTEP\n";
echo "=========================================\n\n";

$errores = [];
$usuarios_creados = [];

// ============================================
// 1. CREAR PROGRAMAS (si no existen)
// ============================================
echo "1. Verificando/creando programas...\n";

$programas = [
    ['nombre' => 'Técnico en Sistemas', 'codigo' => 'TS'],
    ['nombre' => 'Técnico en Contabilidad', 'codigo' => 'TC'],
    ['nombre' => 'Técnico en Secretariado', 'codigo' => 'TSEC'],
    ['nombre' => 'Técnico en Alimentación y Hostelería', 'codigo' => 'TAH']
];

foreach ($programas as &$prog) {
    $check = mysqli_prepare($conexion, "SELECT id FROM programas WHERE nombre = ?");
    mysqli_stmt_bind_param($check, 's', $prog['nombre']);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);
    
    if (mysqli_num_rows($res) == 0) {
        $sql = "INSERT INTO programas (nombre, codigo, estado) VALUES (?, ?, 'activo')";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $prog['nombre'], $prog['codigo']);
        mysqli_stmt_execute($stmt);
        $prog['id'] = mysqli_insert_id($conexion);
        echo "   ✓ Programa: {$prog['nombre']}\n";
    } else {
        $row = mysqli_fetch_assoc($res);
        $prog['id'] = $row['id'];
        echo "   - Programa ya existe: {$prog['nombre']}\n";
    }
}

// ============================================
// 2. CREAR MATERIAS POR PROGRAMA
// ============================================
echo "\n2. Creando materias por programa...\n";

$materias_por_programa = [
    'Técnico en Sistemas' => ['Fundamentos de TI', 'Redes Básicas', 'Sistemas Operativos', 'Programación Web', 'Base de Datos', 'Mantenimiento PC'],
    'Técnico en Contabilidad' => ['Contabilidad Básica', 'Contabilidad Financiera', 'Costos y Presupuesto', 'Normas IFRS', 'Tributación', 'Facturación'],
    'Técnico en Secretariado' => ['Ortografía', 'Arquivo', 'Atención al Cliente', 'Procesamiento de Texto', 'Comunicación Empresarial', 'Protocolo'],
    'Técnico en Alimentación y Hostelería' => ['Manipulación de Alimentos', 'Servicios de Restaurante', 'Cocina Básica', 'Nutrición', 'Gestión de Bodega', 'Higiene']
];

$todas_materias = [];

foreach ($materias_por_programa as $prog_nombre => $materias) {
    $prog_id = null;
    foreach ($programas as $p) {
        if ($p['nombre'] == $prog_nombre) {
            $prog_id = $p['id'];
            break;
        }
    }
    
    foreach ($materias as $materia) {
        $check = mysqli_prepare($conexion, "SELECT id FROM materias WHERE nombre = ? AND programa_id = ?");
        mysqli_stmt_bind_param($check, 'si', $materia, $prog_id);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        
        if (mysqli_num_rows($res) == 0) {
            $sql = "INSERT INTO materias (nombre, programa_id, estado) VALUES (?, ?, 'activo')";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $materia, $prog_id);
            mysqli_stmt_execute($stmt);
            $todas_materias[$materia] = mysqli_insert_id($conexion);
        } else {
            $row = mysqli_fetch_assoc($res);
            $todas_materias[$materia] = $row['id'];
        }
    }
    echo "   ✓ {$prog_nombre}: " . count($materias) . " materias\n";
}

// ============================================
// 3. CREAR DOCENTES (4 profesores)
// ============================================
echo "\n3. Creando 4 docentes...\n";

$docentes = [
    ['nombre' => 'Carlos Alberto Mendoza', 'username' => 'docente1', 'password' => 'docente123'],
    ['nombre' => 'María Elena Rodríguez', 'username' => 'docente2', 'password' => 'docente123'],
    ['nombre' => 'José Luis García', 'username' => 'docente3', 'password' => 'docente123'],
    ['nombre' => 'Ana Patricia López', 'username' => 'docente4', 'password' => 'docente123']
];

foreach ($docentes as &$doc) {
    // Crear usuario docente
    $passwordHash = hashPassword($doc['password']);
    
    $check = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE username = ?");
    mysqli_stmt_bind_param($check, 's', $doc['username']);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);
    
    if (mysqli_num_rows($res) == 0) {
        $sql = "INSERT INTO usuarios (username, password_hash, rol, estado) VALUES (?, ?, 'docente', 'activo')";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $doc['username'], $passwordHash);
        mysqli_stmt_execute($stmt);
        $doc['id'] = mysqli_insert_id($conexion);
        echo "   ✓ Docente: {$doc['nombre']} (user: {$doc['username']})\n";
    } else {
        $row = mysqli_fetch_assoc($res);
        $doc['id'] = $row['id'];
        echo "   - Docente ya existe: {$doc['nombre']}\n";
    }
}

// ============================================
// 4. CREAR MÓDULOS (asignar docentes)
// ============================================
echo "\n4. Creando módulos y asignando docentes...\n";

$modulos_creados = 0;
$bimestre_actual = 1;

foreach ($todas_materias as $materia_nombre => $materia_id) {
    $prog_id = null;
    foreach ($programas as $p) {
        if (isset($materias_por_programa[$p['nombre']]) && in_array($materia_nombre, $materias_por_programa[$p['nombre']])) {
            $prog_id = $p['id'];
            break;
        }
    }
    
    // Crear 2 módulos por materia (2 bimestres)
    for ($bim = 1; $bim <= 2; $bim++) {
        $check = mysqli_prepare($conexion, "SELECT id FROM modulos WHERE materia_id = ? AND bimestre = ?");
        mysqli_stmt_bind_param($check, 'ii', $materia_id, $bim);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        
        if (mysqli_num_rows($res) == 0) {
            $nombre_modulo = "{$materia_nombre} - Bimestre {$bim}";
            $docente_asignado = $docentes[array_rand($docentes)]['id'];
            
            $sql = "INSERT INTO modulos (materia_id, nombre, bimestre, orden, docente_id, estado) VALUES (?, ?, ?, ?, ?, 'activo')";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, 'isiii', $materia_id, $nombre_modulo, $bim, $bim, $docente_asignado);
            mysqli_stmt_execute($stmt);
            $modulos_creados++;
        }
    }
}
echo "   ✓ {$modulos_creados} módulos creados/asignados\n";

// ============================================
// 5. CREAR ESTUDIANTES (5 por programa = 20 total)
// ============================================
echo "\n5. Creando 5 estudiantes por programa...\n";

$nombres_est = [
    'Juan', 'Pedro', 'Maria', 'Luisa', 'Carlos', 'Ana', 'Luis', 'Sofia', 'Miguel', 'Laura',
    'Rosa', 'Jorge', 'Carmen', 'Diego', 'Patricia', 'Fernando', 'Lorena', 'Ricardo', 'Gabriela', 'Eduardo'
];

$apellidos = [
    'González', 'Rodríguez', 'Martínez', 'López', 'Hernández', 'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Flores',
    'Rivera', 'Gómez', 'Díaz', 'Reyes', 'Cruz', 'Morales', 'Ortiz', 'Gutiérrez', 'Chávez', 'Ramos'
];

$estudiantes_creados = [];

foreach ($programas as $prog) {
    for ($i = 1; $i <= 5; $i++) {
        $nombre = $nombres_est[array_rand($nombres_est)] . ' ' . $apellidos[array_rand($apellidos)];
        $documento = $prog['codigo'] . str_pad($i, 4, '0', STR_PAD_LEFT) . '25';
        $email = strtolower(str_replace(' ', '.', $nombre)) . '@estudiante.intep.edu.co';
        $password = 'estudiante123';
        $passwordHash = hashPassword($password);
        
        $check = mysqli_prepare($conexion, "SELECT id FROM estudiantes WHERE documento = ?");
        mysqli_stmt_bind_param($check, 's', $documento);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        
        if (mysqli_num_rows($res) == 0) {
            // Crear estudiante
            $sql = "INSERT INTO estudiantes (nombre, documento, email, programa_id, fecha_ingreso, estado) VALUES (?, ?, ?, ?, '2025-01-15', 'activo')";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $documento, $email, $prog['id']);
            mysqli_stmt_execute($stmt);
            $estudiante_id = mysqli_insert_id($conexion);
            
            // Crear usuario
            $sql2 = "INSERT INTO usuarios (username, password_hash, rol, estudiante_id, estado) VALUES (?, ?, 'estudiante', ?, 'activo')";
            $stmt2 = mysqli_prepare($conexion, $sql2);
            mysqli_stmt_bind_param($stmt2, 'ssi', $documento, $passwordHash, $estudiante_id);
            mysqli_stmt_execute($stmt2);
            
            $estudiantes_creados[] = [
                'id' => $estudiante_id,
                'nombre' => $nombre,
                'documento' => $documento,
                'programa' => $prog['nombre'],
                'password' => $password
            ];
        }
    }
    echo "   ✓ Programa {$prog['nombre']}: 5 estudiantes\n";
}

// ============================================
// 6. CREAR CONCEPTOS DE COBRO
// ============================================
echo "\n6. Creando conceptos de cobro...\n";

$conceptos = [
    ['nombre' => 'Mensualidad', 'tipo' => 'mensualidad', 'monto' => 212000, 'cuotas' => 10, 'desc' => 'Cuota mensual del programa técnico (10 meses)'],
    ['nombre' => 'Seminario Excel Intermedio', 'tipo' => 'seminario', 'monto' => 320000, 'cuotas' => 1, 'desc' => 'Seminario obligatorio adicional al programa'],
    ['nombre' => 'Derechos de Grado', 'tipo' => 'otro', 'monto' => 450000, 'cuotas' => 1, 'desc' => 'Ceremonia de grado y celebración (PROM)'],
    ['nombre' => 'Mensualidad Inglés', 'tipo' => 'mensualidad', 'monto' => 145000, 'cuotas' => 4, 'desc' => 'Cuota mensual del programa de inglés (4 meses por nivel)']
];

// Agregar columna num_cuotas si no existe
mysqli_query($conexion, "ALTER TABLE conceptos_cobro ADD COLUMN num_cuotas INT NOT NULL DEFAULT 1 AFTER tipo");

// Agregar programas de inglés
$progs_ingles = [
    ['nombre' => 'Inglés A1', 'codigo' => 'ING-A1'],
    ['nombre' => 'Inglés A2', 'codigo' => 'ING-A2'],
    ['nombre' => 'Inglés B1', 'codigo' => 'ING-B1']
];
foreach ($progs_ingles as $pi) {
    $check = mysqli_prepare($conexion, "SELECT id FROM programas WHERE codigo = ?");
    mysqli_stmt_bind_param($check, 's', $pi['codigo']);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);
    if (mysqli_num_rows($res) == 0) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO programas (nombre, codigo, estado) VALUES (?, ?, 'activo')");
        mysqli_stmt_bind_param($stmt, 'ss', $pi['nombre'], $pi['codigo']);
        mysqli_stmt_execute($stmt);
    }
}

foreach ($conceptos as &$conc) {
    $check = mysqli_prepare($conexion, "SELECT id FROM conceptos_cobro WHERE nombre = ?");
    mysqli_stmt_bind_param($check, 's', $conc['nombre']);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($res) == 0) {
        $sql = "INSERT INTO conceptos_cobro (nombre, descripcion, monto_base, tipo, num_cuotas, estado) VALUES (?, ?, ?, ?, ?, 'activo')";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'ssdsi', $conc['nombre'], $conc['desc'], $conc['monto'], $conc['tipo'], $conc['cuotas']);
        mysqli_stmt_execute($stmt);
        $conc['id'] = mysqli_insert_id($conexion);
    } else {
        $row = mysqli_fetch_assoc($res);
        $conc['id'] = $row['id'];
    }
}
echo "   ✓ " . count($conceptos) . " conceptos de cobro\n";

// ============================================
// 7. GENERAR COBROS PARA ESTUDIANTES
// ============================================
echo "\n7. Generando cobros para estudiantes...\n";

$cobros_creados = 0;
$monto_mensualidad = $conceptos[0]['monto']; // $212,000
$concepto_mensualidad_id = $conceptos[0]['id'];

foreach ($estudiantes_creados as $est) {
    // Generar 4 mensualidades de prueba ($212,000 cada una)
    for ($mes = 1; $mes <= 4; $mes++) {
        $periodo = sprintf('2026-%02d', $mes);
        $fecha_venc = date('Y-m-d', strtotime("2026-01-15 +{$mes} months"));
        $sql = "INSERT INTO cobros (estudiante_id, concepto_id, periodo, monto, descuento, total, pagado, saldo, fecha_vencimiento, estado)
                VALUES (?, ?, ?, ?, 0, ?, 0, ?, ?, 'pendiente')";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iisddds', $est['id'], $concepto_mensualidad_id, $periodo,
            $monto_mensualidad, $monto_mensualidad, $monto_mensualidad, $fecha_venc);
        mysqli_stmt_execute($stmt);
        $cobros_creados++;
    }
}
echo "   ✓ {$cobros_creados} cobros generados (mensualidades $" . number_format($monto_mensualidad, 0, ',', '.') . ")\n";

// ============================================
// 8. REGISTRAR PAGOS ALEATORIOS
// ============================================
echo "\n8. Registrando pagos aleatorios...\n";

$metodos = ['efectivo', 'transferencia', 'consignacion'];
$pagos_creados = 0;

foreach ($estudiantes_creados as $est) {
    // Obtener cobros del estudiante
    $sql = "SELECT id, total FROM cobros WHERE estudiante_id = ? AND estado != 'anulado'";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $est['id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    while ($cobro = mysqli_fetch_assoc($res)) {
        // 70% de probabilidad de tener pago
        if (rand(1, 100) <= 70) {
            $monto_pagado = rand(1, 2) == 1 ? $cobro['total'] : $cobro['total'] * 0.5;
            $estado = $monto_pagado >= $cobro['total'] ? 'pagado' : 'parcial';
            $metodo = $metodos[array_rand($metodos)];
            $referencia = strtoupper($metodo) . rand(10000, 99999);
            $fecha = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
            
            $sql_pago = "INSERT INTO pagos (cobro_id, estudiante_id, monto, fecha_pago, metodo_pago, referencia, observaciones, registrado_por) 
                        VALUES (?, ?, ?, ?, ?, ?, 'Pago registrado', 1)";
            $stmt_pago = mysqli_prepare($conexion, $sql_pago);
            mysqli_stmt_bind_param($stmt_pago, 'iidsss', $cobro['id'], $est['id'], $monto_pagado, $fecha, $metodo, $referencia);
            mysqli_stmt_execute($stmt_pago);
            
            // Actualizar cobro
            $nuevo_pagado = $monto_pagado;
            $nuevo_saldo = $cobro['total'] - $monto_pagado;
            $upd = mysqli_prepare($conexion, "UPDATE cobros SET pagado = ?, saldo = ?, estado = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'ddsi', $nuevo_pagado, $nuevo_saldo, $estado, $cobro['id']);
            mysqli_stmt_execute($upd);
            
            $pagos_creados++;
        }
    }
}
echo "   ✓ {$pagos_creados} pagos registrados\n";

// ============================================
// 9. CREAR HORARIOS
// ============================================
echo "\n9. Creando horarios de clase...\n";

$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
$horas = ['06:45:00', '07:30:00', '08:15:00', '09:00:00', '09:45:00', '10:30:00', '11:15:00'];
$horarios_creados = 0;

// Obtener módulos
$sql_mod = "SELECT id, nombre, docente_id FROM modulos LIMIT 20";
$res_mod = mysqli_query($conexion, $sql_mod);

while ($mod = mysqli_fetch_assoc($res_mod)) {
    // Asignar 2-3 horarios por módulo
    $num_horarios = rand(2, 3);
    for ($h = 0; $h < $num_horarios; $h++) {
        $dia = $dias_semana[array_rand($dias_semana)];
        $hora_inicio = $horas[array_rand($horas)];
        
        // Calcular hora fin (2 horas después)
        $hora_dt = new DateTime($hora_inicio);
        $hora_dt->modify('+2 hours');
        $hora_fin = $hora_dt->format('H:i:s');
        
        $check = mysqli_prepare($conexion, 
            "SELECT id FROM horarios WHERE modulo_id = ? AND dia = ? AND hora_inicio = ?");
        mysqli_stmt_bind_param($check, 'iss', $mod['id'], $dia, $hora_inicio);
        mysqli_stmt_execute($check);
        $res_check = mysqli_stmt_get_result($check);
        
        if (mysqli_num_rows($res_check) == 0) {
            $sql = "INSERT INTO horarios (modulo_id, dia, hora_inicio, hora_fin, aula, docente_id, estado) 
                    VALUES (?, ?, ?, ?, 'Aula-' . ?, ?, 'activo')";
            $aula = rand(1, 10);
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, 'isssii', $mod['id'], $dia, $hora_inicio, $hora_fin, $aula, $mod['docente_id']);
            mysqli_stmt_execute($stmt);
            $horarios_creados++;
        }
    }
}
echo "   ✓ {$horarios_creados} horarios creados\n";

// ============================================
// RESUMEN
// ============================================
echo "\n=========================================\n";
echo "           RESUMEN DE DATOS\n";
echo "=========================================\n";
echo "Programas:     " . count($programas) . "\n";
echo "Materias:      " . count($todas_materias) . "\n";
echo "Docentes:      " . count($docentes) . "\n";
echo "Módulos:       " . $modulos_creados . "\n";
echo "Estudiantes:   " . count($estudiantes_creados) . "\n";
echo "Conceptos:     " . count($conceptos) . "\n";
echo "Cobros:        " . $cobros_creados . "\n";
echo "Pagos:         " . $pagos_creados . "\n";
echo "Horarios:      " . $horarios_creados . "\n";

echo "\n=========================================\n";
echo "         CREDENCIALES DE ACCESO\n";
echo "=========================================\n";

echo "\n--- ADMINISTRADOR ---\n";
echo "Usuario: admin\n";
echo "Contraseña: admin123\n";

echo "\n--- DOCENTES ---\n";
foreach ($docentes as $d) {
    echo "{$d['nombre']}: user={$d['username']} pass={$d['password']}\n";
}

echo "\n--- ESTUDIANTES (primeros 10) ---\n";
for ($i = 0; $i < 10; $i++) {
    $e = $estudiantes_creados[$i];
    echo "{$e['nombre']} ({$e['programa']}): user={$e['documento']} pass={$e['password']}\n";
}

echo "\n=========================================\n";
echo "   ¡DATOS DE PRUEBA GENERADOS!\n";
echo "=========================================\n";
