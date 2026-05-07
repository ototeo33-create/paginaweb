<?php
// Helper de alertas titilantes en módulos del estudiante.
// Requiere $conexion (mysqli) ya inicializado por config.php.

if (!defined('ALERTAS_MODULOS')) {
    define('ALERTAS_MODULOS', ['cartera', 'horarios', 'evaluacion']);
}

/**
 * Marca como vistas todas las alertas activas del estudiante para un módulo.
 * Idempotente: si no hay nada activo, no hace nada.
 */
function marcarAlertaVista(mysqli $conexion, int $estudiante_id, string $modulo): void {
    if ($estudiante_id <= 0) return;
    if (!in_array($modulo, ALERTAS_MODULOS, true)) return;

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE alertas_estudiante
            SET vista_en = NOW()
          WHERE estudiante_id = ?
            AND modulo = ?
            AND vista_en IS NULL"
    );
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'is', $estudiante_id, $modulo);
    @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Devuelve el mapa de alertas activas para un estudiante.
 * Para 'evaluacion' usa eval_control.activa como fuente global; las
 * filas en alertas_estudiante con modulo='evaluacion' permiten apagar
 * el titileo cuando el estudiante ya entró al módulo durante el periodo.
 *
 * @return array{cartera:bool,horarios:bool,evaluacion:bool}
 */
function obtenerAlertasEstudiante(mysqli $conexion, int $estudiante_id): array {
    $alertas = ['cartera' => false, 'horarios' => false, 'evaluacion' => false];
    if ($estudiante_id <= 0) return $alertas;

    // Alertas pendientes en la tabla
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT modulo
           FROM alertas_estudiante
          WHERE estudiante_id = ?
            AND vista_en IS NULL"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $estudiante_id);
        if (@mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res)) {
                $m = $r['modulo'];
                if (isset($alertas[$m])) $alertas[$m] = true;
            }
        }
        mysqli_stmt_close($stmt);
    }

    return $alertas;
}
