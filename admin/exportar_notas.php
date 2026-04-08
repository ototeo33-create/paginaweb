<?php
require_once '../config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['modulo_id'])) {
    header('Location: ingresar_notas.php');
    exit;
}

$modulo_id = (int)$_GET['modulo_id'];
$es_admin = $_SESSION['usuario_rol'] === 'admin';
$usuario_id = (int)$_SESSION['usuario_id'];

// Info del modulo (desde programa_modulo)
$stmt = mysqli_prepare($conexion, "SELECT pm.id, mf.nombre, pm.bimestre, pm.orden, pm.tipo, pm.docente_id,
    pm.programa_id, p.nombre as programa_nombre, u.username as docente_nombre
    FROM programa_modulo pm
    JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
    JOIN programas p ON pm.programa_id = p.id
    LEFT JOIN usuarios u ON pm.docente_id = u.id
    WHERE pm.id = ?");
mysqli_stmt_bind_param($stmt, 'i', $modulo_id);
mysqli_stmt_execute($stmt);
$modulo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$modulo) {
    header('Location: ingresar_notas.php');
    exit;
}

if (!$es_admin && $modulo['docente_id'] != $usuario_id) {
    header('Location: ingresar_notas.php');
    exit;
}

// Estudiantes
$stmt = mysqli_prepare($conexion, "SELECT id, nombre, documento FROM estudiantes WHERE programa_id = ? AND estado = 'activo' ORDER BY nombre");
mysqli_stmt_bind_param($stmt, 'i', $modulo['programa_id']);
mysqli_stmt_execute($stmt);
$estudiantes = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $estudiantes[] = $row;
}

// Notas
$stmt = mysqli_prepare($conexion, "SELECT * FROM notas WHERE programa_modulo_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $modulo_id);
mysqli_stmt_execute($stmt);
$notas = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $notas[$row['estudiante_id']] = $row;
}

// Asistencia
$stmt = mysqli_prepare($conexion, "SELECT * FROM asistencia WHERE programa_modulo_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $modulo_id);
mysqli_stmt_execute($stmt);
$asistencia = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $asistencia[$row['estudiante_id']] = $row;
}

// Observaciones
$stmt = mysqli_prepare($conexion, "SELECT o.*, u.username as autor_nombre FROM observaciones o JOIN usuarios u ON o.autor_id = u.id WHERE o.programa_modulo_id = ? ORDER BY o.fecha DESC");
mysqli_stmt_bind_param($stmt, 'i', $modulo_id);
mysqli_stmt_execute($stmt);
$observaciones = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $observaciones[$row['estudiante_id']][] = $row;
}

// ===== CREAR EXCEL =====
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notas y Asistencia');

$bimestre = $modulo['bimestre'];
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '064E3B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$subHeaderStyle = [
    'font' => ['bold' => true, 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
];
$cellStyle = [
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'font' => ['size' => 9],
];
$titleStyle = [
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '064E3B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];

// ===== SECCION 1: ASISTENCIA =====
$row = 2;
$sheet->mergeCells("A{$row}:F{$row}");
$sheet->setCellValue("A{$row}", "CONTROL DE ASISTENCIA BIMESTRE {$bimestre} - " . date('Y'));
$sheet->getStyle("A{$row}")->applyFromArray($titleStyle);

$row = 3;
$sheet->setCellValue("A{$row}", "Docente:");
$sheet->setCellValue("B{$row}", $modulo['docente_nombre'] ?? 'Sin asignar');
$sheet->setCellValue("D{$row}", "Modulo:");
$sheet->setCellValue("E{$row}", $modulo['nombre']);
$sheet->getStyle("A{$row}:A{$row}")->getFont()->setBold(true);
$sheet->getStyle("D{$row}:D{$row}")->getFont()->setBold(true);

$row = 4;
$sheet->setCellValue("A{$row}", "Programa:");
$sheet->setCellValue("B{$row}", $modulo['programa_nombre']);
$sheet->getStyle("A{$row}")->getFont()->setBold(true);

$row = 6;
$headers = ['N', 'Estudiante', 'Total Clases', 'Asistencias', 'Inasistencias', '% Asistencia'];
foreach ($headers as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue("{$col}{$row}", $h);
}
$sheet->getStyle("A{$row}:F{$row}")->applyFromArray($headerStyle);

$row = 7;
foreach ($estudiantes as $i => $est) {
    $a = $asistencia[$est['id']] ?? [];
    $sheet->setCellValue("A{$row}", $i + 1);
    $sheet->setCellValue("B{$row}", $est['nombre']);
    $sheet->setCellValue("C{$row}", $a['total_clases'] ?? '');
    $sheet->setCellValue("D{$row}", $a['total_asistencias'] ?? '');
    $sheet->setCellValue("E{$row}", $a['total_inasistencias'] ?? '');
    $pct = $a['porcentaje_asistencia'] ?? '';
    $sheet->setCellValue("F{$row}", $pct !== '' ? $pct . '%' : '');
    $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($cellStyle);
    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $row++;
}

// ===== SECCION 2: CALIFICACIONES =====
$row += 2;
$calRow = $row;
$sheet->mergeCells("A{$row}:S{$row}");
$sheet->setCellValue("A{$row}", "CONTROL DE CALIFICACIONES BIMESTRE {$bimestre} - " . date('Y'));
$sheet->getStyle("A{$row}")->applyFromArray($titleStyle);

$row++;
$sheet->setCellValue("A{$row}", "Docente:");
$sheet->setCellValue("B{$row}", $modulo['docente_nombre'] ?? 'Sin asignar');
$sheet->setCellValue("D{$row}", "Modulo:");
$sheet->setCellValue("E{$row}", $modulo['nombre']);
$sheet->getStyle("A{$row}")->getFont()->setBold(true);
$sheet->getStyle("D{$row}")->getFont()->setBold(true);

$row += 2;
// Grupo headers
$sheet->mergeCells("C{$row}:H{$row}");
$sheet->setCellValue("C{$row}", "EVIDENCIA DE CONOCIMIENTO (30%)");
$sheet->getStyle("C{$row}:H{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);

$sheet->mergeCells("I{$row}:M{$row}");
$sheet->setCellValue("I{$row}", "EVIDENCIA DE PRODUCTO (30%)");
$sheet->getStyle("I{$row}:M{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);

$sheet->mergeCells("N{$row}:R{$row}");
$sheet->setCellValue("N{$row}", "EVIDENCIA DE DESEMPENO (40%)");
$sheet->getStyle("N{$row}:R{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047857']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);

$row++;
$calHeaders = ['N', 'Estudiante', '1er Parcial', '2do Parcial', 'Nota EC', 'x30', 'Prod 1', 'Prod 2', 'Prod 3', 'Nota EP', 'x30', 'Desemp 1', 'Desemp 2', 'Desemp 3', 'Nota ED', 'x40', 'NOTA DEF.', 'Estado'];
$cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S'];
// Actually we have: N, Estudiante, P1, P2, NotaEC, x30, Prod1, Prod2, Prod3, NotaEP, x30, D1, D2, D3, NotaED, x40, NotaDef, Estado
// Columns: A through S (19 columns) but we have 18 headers, let me adjust
$calHeaders = ['N', 'Estudiante', '1er Parcial', '2do Parcial', 'Nota EC', 'x0.30', 'Prod 1', 'Prod 2', 'Prod 3', 'Nota EP', 'x0.30', 'Desemp 1', 'Desemp 2', 'Desemp 3', 'Nota ED', 'x0.40', 'NOTA DEF.'];

for ($i = 0; $i < count($calHeaders); $i++) {
    $col = chr(65 + $i);
    if ($i > 25) $col = 'A' . chr(65 + $i - 26);
    $sheet->setCellValue("{$col}{$row}", $calHeaders[$i]);
}
$lastCol = chr(65 + count($calHeaders) - 1);
$sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($subHeaderStyle);

$row++;
foreach ($estudiantes as $i => $est) {
    $n = $notas[$est['id']] ?? [];
    $ec = $n['nota_conocimiento'] ?? null;
    $ep = $n['nota_producto'] ?? null;
    $ed = $n['nota_desempeno'] ?? null;
    $nf = $n['nota_final'] ?? null;

    $sheet->setCellValue("A{$row}", $i + 1);
    $sheet->setCellValue("B{$row}", $est['nombre']);
    $sheet->setCellValue("C{$row}", $n['parcial1'] ?? '');
    $sheet->setCellValue("D{$row}", $n['parcial2'] ?? '');
    $sheet->setCellValue("E{$row}", $ec !== null ? number_format($ec, 1) : '');
    $sheet->setCellValue("F{$row}", $ec !== null ? number_format($ec * 0.30, 2) : '');
    $sheet->setCellValue("G{$row}", $n['producto1'] ?? '');
    $sheet->setCellValue("H{$row}", $n['producto2'] ?? '');
    $sheet->setCellValue("I{$row}", $n['producto3'] ?? '');
    $sheet->setCellValue("J{$row}", $ep !== null ? number_format($ep, 1) : '');
    $sheet->setCellValue("K{$row}", $ep !== null ? number_format($ep * 0.30, 2) : '');
    $sheet->setCellValue("L{$row}", $n['desempeno1'] ?? '');
    $sheet->setCellValue("M{$row}", $n['desempeno2'] ?? '');
    $sheet->setCellValue("N{$row}", $n['desempeno3'] ?? '');
    $sheet->setCellValue("O{$row}", $ed !== null ? number_format($ed, 1) : '');
    $sheet->setCellValue("P{$row}", $ed !== null ? number_format($ed * 0.40, 2) : '');
    $sheet->setCellValue("Q{$row}", $nf !== null ? number_format($nf, 1) : '');

    $sheet->getStyle("A{$row}:Q{$row}")->applyFromArray($cellStyle);
    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Color nota final
    if ($nf !== null) {
        $color = $nf >= 3.5 ? '059669' : 'EF4444';
        $sheet->getStyle("Q{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($color));
        $sheet->getStyle("Q{$row}")->getFont()->setBold(true);
    }

    $row++;
}

// ===== SECCION 3: OBSERVACIONES =====
$row += 2;
$sheet->mergeCells("A{$row}:F{$row}");
$sheet->setCellValue("A{$row}", "OBSERVACIONES");
$sheet->getStyle("A{$row}")->applyFromArray($titleStyle);

$row++;
$sheet->setCellValue("A{$row}", "N");
$sheet->setCellValue("B{$row}", "Estudiante");
$sheet->setCellValue("C{$row}", "Observacion");
$sheet->setCellValue("D{$row}", "Autor");
$sheet->setCellValue("E{$row}", "Fecha");
$sheet->getStyle("A{$row}:E{$row}")->applyFromArray($headerStyle);

$row++;
$num = 1;
foreach ($estudiantes as $est) {
    $obs = $observaciones[$est['id']] ?? [];
    if (empty($obs)) continue;
    foreach ($obs as $j => $o) {
        $sheet->setCellValue("A{$row}", $j === 0 ? $num : '');
        $sheet->setCellValue("B{$row}", $j === 0 ? $est['nombre'] : '');
        $sheet->setCellValue("C{$row}", $o['observacion']);
        $sheet->setCellValue("D{$row}", $o['autor_nombre']);
        $sheet->setCellValue("E{$row}", date('d/m/Y', strtotime($o['fecha'])));
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($cellStyle);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        $row++;
    }
    $num++;
}

// Column widths
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(30);
for ($c = 'C'; $c <= 'Q'; $c++) {
    $sheet->getColumnDimension($c)->setWidth(12);
}

// Download
$filename = 'Notas_Bimestre' . $bimestre . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $modulo['nombre']) . '_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
