<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['modulo_id']) || !isset($input['notas']) || !is_array($input['notas'])) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

$modulo_id = (int)$input['modulo_id'];
$guardados = 0;
$errores = 0;

foreach ($input['notas'] as $nota) {
    $estudiante_id = (int)$nota['estudiante_id'];
    $parcial1 = $nota['parcial1'] !== '' && $nota['parcial1'] !== null ? (float)$nota['parcial1'] : null;
    $parcial2 = $nota['parcial2'] !== '' && $nota['parcial2'] !== null ? (float)$nota['parcial2'] : null;
    $producto1 = $nota['producto1'] !== '' && $nota['producto1'] !== null ? (float)$nota['producto1'] : null;
    $producto2 = $nota['producto2'] !== '' && $nota['producto2'] !== null ? (float)$nota['producto2'] : null;
    $producto3 = $nota['producto3'] !== '' && $nota['producto3'] !== null ? (float)$nota['producto3'] : null;
    $desempeno1 = $nota['desempeno1'] !== '' && $nota['desempeno1'] !== null ? (float)$nota['desempeno1'] : null;
    $desempeno2 = $nota['desempeno2'] !== '' && $nota['desempeno2'] !== null ? (float)$nota['desempeno2'] : null;
    $desempeno3 = $nota['desempeno3'] !== '' && $nota['desempeno3'] !== null ? (float)$nota['desempeno3'] : null;

    // Calcular promedios
    $nota_conocimiento = null;
    if ($parcial1 !== null && $parcial2 !== null) {
        $nota_conocimiento = round(($parcial1 + $parcial2) / 2, 1);
    }

    $nota_producto = null;
    $prods = array_filter([$producto1, $producto2, $producto3], function($v) { return $v !== null; });
    if (count($prods) > 0) {
        $nota_producto = round(array_sum($prods) / count($prods), 1);
    }

    $nota_desempeno = null;
    $desps = array_filter([$desempeno1, $desempeno2, $desempeno3], function($v) { return $v !== null; });
    if (count($desps) > 0) {
        $nota_desempeno = round(array_sum($desps) / count($desps), 1);
    }

    $nota_final = null;
    if ($nota_conocimiento !== null && $nota_producto !== null && $nota_desempeno !== null) {
        $nota_final = round(($nota_conocimiento * 0.30) + ($nota_producto * 0.30) + ($nota_desempeno * 0.40), 1);
    }

    $aprobado = ($nota_final !== null && $nota_final >= 3.5) ? 1 : 0;

    // Upsert
    $check = mysqli_prepare($conexion, "SELECT id FROM notas WHERE estudiante_id = ? AND modulo_id = ?");
    mysqli_stmt_bind_param($check, 'ii', $estudiante_id, $modulo_id);
    mysqli_stmt_execute($check);
    $existe = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($existe) > 0) {
        $row = mysqli_fetch_assoc($existe);
        $sql = "UPDATE notas SET parcial1=?, parcial2=?, nota_conocimiento=?,
                producto1=?, producto2=?, producto3=?, nota_producto=?,
                desempeno1=?, desempeno2=?, desempeno3=?, nota_desempeno=?,
                nota_final=?, aprobado=? WHERE id=?";
        $stmt = mysqli_prepare($conexion, $sql);
        $id_nota = $row['id'];
        mysqli_stmt_bind_param($stmt, 'ddddddddddddii',
            $parcial1, $parcial2, $nota_conocimiento,
            $producto1, $producto2, $producto3, $nota_producto,
            $desempeno1, $desempeno2, $desempeno3, $nota_desempeno,
            $nota_final, $aprobado, $id_nota
        );
    } else {
        $sql = "INSERT INTO notas (estudiante_id, modulo_id, parcial1, parcial2, nota_conocimiento,
                producto1, producto2, producto3, nota_producto,
                desempeno1, desempeno2, desempeno3, nota_desempeno,
                nota_final, aprobado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiddddddddddddi',
            $estudiante_id, $modulo_id,
            $parcial1, $parcial2, $nota_conocimiento,
            $producto1, $producto2, $producto3, $nota_producto,
            $desempeno1, $desempeno2, $desempeno3, $nota_desempeno,
            $nota_final, $aprobado
        );
    }

    if ($stmt && mysqli_stmt_execute($stmt)) {
        $guardados++;
    } else {
        $errores++;
    }
}

echo json_encode(['ok' => true, 'guardados' => $guardados, 'errores' => $errores]);
