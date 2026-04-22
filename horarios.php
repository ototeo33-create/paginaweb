<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['usuario_rol'];
$estudiante_id = $_SESSION['estudiante_id'] ?? 0;

// ============================================================
// FLUJO ADMIN: Asignación de horarios (docente → módulo → form)
// ============================================================
$mensaje_asig = '';

if ($rol === 'admin') {
    // Garantizar tabla estudiante_modulo
    mysqli_query($conexion, "
        CREATE TABLE IF NOT EXISTS estudiante_modulo (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            estudiante_id      INT NOT NULL,
            programa_modulo_id INT NOT NULL,
            estado             VARCHAR(20) DEFAULT 'activo',
            created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (estudiante_id)      REFERENCES estudiantes(id) ON DELETE CASCADE,
            FOREIGN KEY (programa_modulo_id) REFERENCES programa_modulo(id) ON DELETE CASCADE,
            UNIQUE KEY uk_est_mod (estudiante_id, programa_modulo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // PROCESAR POST de asignación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_horario'])) {
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            $mensaje_asig = 'error|Token de seguridad invalido.';
        } else {
            $accion = $_POST['accion_horario'];

            if ($accion === 'agregar') {
                $pm_post      = (int)$_POST['pm_id'];
                $dias_par     = $_POST['dias_par'];
                $hora_inicio  = $_POST['hora_inicio'];
                $hora_fin     = $_POST['hora_fin'];
                $salon        = trim($_POST['salon'] ?? '');
                $link_virtual = trim($_POST['link_virtual'] ?? '');
                $bimestre_id  = !empty($_POST['bimestre_id']) ? (int)$_POST['bimestre_id'] : null;
                $dias_array   = array_filter(array_map('trim', explode('-', $dias_par)));

                $stmt_e = mysqli_prepare($conexion,
                    "SELECT e.id, e.programa_id FROM estudiantes e
                     JOIN estudiante_modulo em ON em.estudiante_id = e.id
                     WHERE em.programa_modulo_id = ? AND e.estado = 'activo' AND em.estado = 'activo'");
                mysqli_stmt_bind_param($stmt_e, 'i', $pm_post);
                mysqli_stmt_execute($stmt_e);
                $ests = [];
                $r = mysqli_stmt_get_result($stmt_e);
                while ($e = mysqli_fetch_assoc($r)) $ests[] = $e;

                if (empty($ests)) {
                    $mensaje_asig = 'error|Este modulo no tiene estudiantes asignados.';
                } else {
                    $q = "INSERT IGNORE INTO horarios
                            (programa_id, estudiante_id, programa_modulo_id, dia, hora_inicio, hora_fin, salon, bimestre_id, link_virtual)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertados = 0;
                    foreach ($ests as $est) {
                        foreach ($dias_array as $dia) {
                            $stmt = mysqli_prepare($conexion, $q);
                            mysqli_stmt_bind_param($stmt, 'iiissssis',
                                $est['programa_id'], $est['id'], $pm_post,
                                $dia, $hora_inicio, $hora_fin, $salon, $bimestre_id, $link_virtual);
                            if (mysqli_stmt_execute($stmt)) $insertados++;
                        }
                    }
                    $mensaje_asig = 'success|Horario asignado a ' . count($ests) . ' estudiante(s) — ' . $insertados . ' registro(s) creado(s).';
                }

            } elseif ($accion === 'eliminar') {
                $pm_del  = (int)$_POST['pm_id_del'];
                $dia_del = $_POST['dia_del'];
                $bim_del = !empty($_POST['bimestre_id_del']) ? (int)$_POST['bimestre_id_del'] : null;
                if ($bim_del) {
                    $stmt = mysqli_prepare($conexion,
                        "DELETE FROM horarios WHERE programa_modulo_id = ? AND dia = ? AND bimestre_id = ?");
                    mysqli_stmt_bind_param($stmt, 'isi', $pm_del, $dia_del, $bim_del);
                } else {
                    $stmt = mysqli_prepare($conexion,
                        "DELETE FROM horarios WHERE programa_modulo_id = ? AND dia = ? AND bimestre_id IS NULL");
                    mysqli_stmt_bind_param($stmt, 'is', $pm_del, $dia_del);
                }
                mysqli_stmt_execute($stmt);
                $afectados = mysqli_affected_rows($conexion);
                $mensaje_asig = "success|Eliminados $afectados registro(s).";
            }
        }

        $docente_r = isset($_POST['docente_id_r']) ? (int)$_POST['docente_id_r'] : 0;
        $pm_r      = isset($_POST['pm_id_r'])      ? (int)$_POST['pm_id_r']      : 0;
        $est_r     = isset($_POST['estudiante_id_r']) ? (int)$_POST['estudiante_id_r'] : 0;
        header("Location: horarios.php?docente_id=$docente_r&pm_id=$pm_r&estudiante_id=$est_r&msg=" . urlencode($mensaje_asig));
        exit;
    }

    if (isset($_GET['msg'])) $mensaje_asig = urldecode($_GET['msg']);

    // Paso 1: Docentes
    $docentes_asig = [];
    $res_doc = mysqli_query($conexion,
        "SELECT u.id, u.username, COUNT(pm.id) AS total_modulos
         FROM usuarios u
         LEFT JOIN programa_modulo pm ON pm.docente_id = u.id AND pm.estado = 'activo'
         WHERE u.rol = 'docente' AND u.estado = 'activo'
         GROUP BY u.id ORDER BY u.username ASC");
    while ($d = mysqli_fetch_assoc($res_doc)) $docentes_asig[] = $d;

    $docente_sel = isset($_GET['docente_id']) ? (int)$_GET['docente_id'] : 0;

    // Paso 2: Módulos del docente
    $modulos_asig = [];
    if ($docente_sel) {
        $res_mods = mysqli_prepare($conexion,
            "SELECT pm.id, pm.bimestre, pm.tipo,
                    mf.nombre AS modulo_nombre,
                    p.nombre  AS programa_nombre,
                    COUNT(em.id) AS total_estudiantes
             FROM programa_modulo pm
             JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
             JOIN programas p ON pm.programa_id = p.id
             LEFT JOIN estudiante_modulo em ON em.programa_modulo_id = pm.id AND em.estado = 'activo'
             WHERE pm.docente_id = ? AND pm.estado = 'activo'
             GROUP BY pm.id ORDER BY pm.bimestre ASC, mf.nombre ASC");
        mysqli_stmt_bind_param($res_mods, 'i', $docente_sel);
        mysqli_stmt_execute($res_mods);
        $r = mysqli_stmt_get_result($res_mods);
        while ($m = mysqli_fetch_assoc($r)) $modulos_asig[] = $m;
    }

    $pm_sel = isset($_GET['pm_id']) ? (int)$_GET['pm_id'] : 0;
    $modulo_sel_info = null;
    foreach ($modulos_asig as $m) {
        if ($m['id'] == $pm_sel) { $modulo_sel_info = $m; break; }
    }

    // Paso 3: Estudiantes del módulo
    $estudiantes_modulo_asig = [];
    if ($pm_sel) {
        $res_est2 = mysqli_prepare($conexion,
            "SELECT e.id, e.nombre, e.documento, p.nombre AS programa_nombre
             FROM estudiantes e
             JOIN estudiante_modulo em ON em.estudiante_id = e.id
             JOIN programas p ON p.id = e.programa_id
             WHERE em.programa_modulo_id = ? AND e.estado = 'activo' AND em.estado = 'activo'
             ORDER BY e.nombre ASC");
        mysqli_stmt_bind_param($res_est2, 'i', $pm_sel);
        mysqli_stmt_execute($res_est2);
        $r = mysqli_stmt_get_result($res_est2);
        while ($e = mysqli_fetch_assoc($r)) $estudiantes_modulo_asig[] = $e;
    }

    // Bimestres activos para el formulario
    $bimestres_form = [];
    $res_bimf = mysqli_query($conexion, "SELECT * FROM bimestres WHERE estado='activo' ORDER BY anio ASC, numero ASC");
    while ($b = mysqli_fetch_assoc($res_bimf)) $bimestres_form[] = $b;

    // Horarios ya asignados al módulo seleccionado
    $horarios_modulo_asig = [];
    if ($pm_sel) {
        $res_hor2 = mysqli_prepare($conexion,
            "SELECT h.dia, h.hora_inicio, h.hora_fin, h.salon, h.link_virtual,
                    h.bimestre_id, b.numero AS bimestre_num,
                    COUNT(h.id) AS total_est
             FROM horarios h
             LEFT JOIN bimestres b ON h.bimestre_id = b.id
             WHERE h.programa_modulo_id = ?
             GROUP BY h.dia, h.bimestre_id, h.hora_inicio, h.hora_fin, h.salon
             ORDER BY b.numero ASC,
                      FIELD(h.dia,'Lunes','Martes','Miercoles','Miércoles','Jueves','Viernes','Sabado','Sábado')");
        mysqli_stmt_bind_param($res_hor2, 'i', $pm_sel);
        mysqli_stmt_execute($res_hor2);
        $r = mysqli_stmt_get_result($res_hor2);
        while ($h = mysqli_fetch_assoc($r)) $horarios_modulo_asig[] = $h;
    }

    $docente_info_sel = null;
    foreach ($docentes_asig as $d) {
        if ($d['id'] == $docente_sel) { $docente_info_sel = $d; break; }
    }
}

// ============================================================
// CALENDARIO: Obtener estudiante_id según rol
// ============================================================
if ($rol !== 'estudiante') {
    $estudiante_id = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 0;
    if (!$estudiante_id) {
        $primer_est = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id FROM estudiantes WHERE estado = 'activo' LIMIT 1"));
        $estudiante_id = $primer_est['id'] ?? 0;
    }
}

$programa_id = 0;

// Obtener programas para selector (admin/docente)
$programas = [];
if ($rol !== 'estudiante') {
    $res_prog = mysqli_query($conexion, "SELECT * FROM programas ORDER BY nombre ASC");
    while ($p = mysqli_fetch_assoc($res_prog)) $programas[] = $p;
}

// Obtener estudiantes para selector (admin/docente)
$estudiantes = [];
if ($rol !== 'estudiante') {
    $res_est = mysqli_query($conexion, "SELECT e.id, e.nombre, e.documento, p.nombre as programa
                                        FROM estudiantes e
                                        LEFT JOIN programas p ON e.programa_id = p.id
                                        WHERE e.estado = 'activo'
                                        ORDER BY e.nombre ASC");
    while ($e = mysqli_fetch_assoc($res_est)) $estudiantes[] = $e;
}

// Auto-limpiar links virtuales de clases que ya terminaron hoy
date_default_timezone_set('America/Bogota');
$dias_map_es = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$dia_hoy_es = $dias_map_es[date('l')] ?? '';
$hora_actual = date('H:i:s');
if ($dia_hoy_es) {
    $q_clean = "UPDATE horarios SET link_virtual = NULL WHERE dia = ? AND hora_fin <= ? AND link_virtual IS NOT NULL AND link_virtual != ''";
    $stmt_clean = mysqli_prepare($conexion, $q_clean);
    mysqli_stmt_bind_param($stmt_clean, 'ss', $dia_hoy_es, $hora_actual);
    mysqli_stmt_execute($stmt_clean);
}

// Obtener horarios del estudiante con datos de bimestre
$query = "SELECT h.*, mf.nombre as materia, b.numero as bimestre_num, b.anio as bimestre_anio,
                 b.fecha_inicio as bim_inicio, b.fecha_fin as bim_fin
          FROM horarios h
          JOIN programa_modulo pm ON h.programa_modulo_id = pm.id
          JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
          LEFT JOIN bimestres b ON h.bimestre_id = b.id
          WHERE h.estudiante_id = ?
          ORDER BY FIELD(h.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), h.hora_inicio";
$stmt2 = mysqli_prepare($conexion, $query);
mysqli_stmt_bind_param($stmt2, 'i', $estudiante_id);
mysqli_stmt_execute($stmt2);
$resultado = mysqli_stmt_get_result($stmt2);

$horarios = [];
$horarios_json = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $horarios[$fila['dia']][] = $fila;
    $horarios_json[] = $fila;
}

// Obtener fechas importantes
$fechas_importantes = [];
$res_fechas = mysqli_query($conexion, "SELECT * FROM fechas_importantes ORDER BY fecha ASC");
while ($f = mysqli_fetch_assoc($res_fechas)) $fechas_importantes[] = $f;

// Obtener bimestres para el filtro
$bimestres = [];
$res_bim = mysqli_query($conexion, "SELECT * FROM bimestres WHERE estado = 'activo' ORDER BY anio DESC, numero ASC");
while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

$dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$horas = ['07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];

// Colores por materia
$colores = ['#25a865','#2d6bbf','#e84545','#f5a623','#9b59b6','#1abc9c','#e67e22','#e91e63','#3498db','#27ae60'];
$color_map = [];
$color_idx = 0;
foreach ($horarios_json as $h) {
    $clave = $h['programa_modulo_id'] ?? $h['materia_id'] ?? 0;
    if ($clave && !isset($color_map[$clave])) {
        $color_map[$clave] = $colores[$color_idx % count($colores)];
        $color_idx++;
    }
}

// Obtener nombre del estudiante actual
$estudiante_actual = null;
if ($rol !== 'estudiante' && $estudiante_id) {
    foreach ($estudiantes as $e) {
        if ($e['id'] == $estudiante_id) {
            $estudiante_actual = $e;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .horario-controles {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .selector-estudiante {
            padding: 0.6rem 1rem;
            border: 2px solid var(--verde-muted);
            border-radius: 10px;
            font-size: 0.88rem;
            outline: none;
            background: white;
            color: var(--dark);
            cursor: pointer;
            min-width: 200px;
        }
        .selector-estudiante:focus { border-color: var(--verde-claro); }
        .estudiante-badge {
            background: var(--verde-muted);
            color: var(--verde);
            padding: 0.4rem 1rem;
            border-radius: 99px;
            font-size: 0.82rem;
            font-weight: 700;
            white-space: nowrap;
        }

        /* VISTA MENSUAL */
        .mes-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: var(--dark);
            color: white;
            border-radius: 16px 16px 0 0;
        }
        .mes-nav h3 { font-size: 1.1rem; font-weight: 700; }
        .mes-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .mes-btn:hover { background: var(--verde); border-color: var(--verde); }
        .mes-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
            border-top: none;
        }
        .mes-dia-header {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            padding: 0.6rem;
            text-align: center;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .mes-dia {
            min-height: 80px;
            padding: 0.4rem;
            border: 1px solid rgba(16, 185, 129, 0.08);
            position: relative;
            background: rgba(255, 255, 255, 0.5);
        }
        .mes-dia.otro-mes { background: rgba(16, 185, 129, 0.03); }
        .mes-dia.hoy { background: rgba(16, 185, 129, 0.1); }
        .mes-num {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--gray);
            margin-bottom: 0.3rem;
        }
        .mes-num.hoy-num {
            background: var(--verde);
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        .mes-evento {
            font-size: 0.68rem;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            color: white;
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
        }
        .mes-evento.fecha-importante {
            cursor: default;
            font-weight: 700;
            font-size: 0.65rem;
            opacity: 0.92;
        }
        .mes-dia.dia-festivo {
            background: rgba(232, 69, 69, 0.06);
        }

        /* Filtro bimestre */
        .filtro-bimestre {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .bim-chip {
            padding: 0.45rem 0.9rem;
            border-radius: 99px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid rgba(16,185,129,0.2);
            background: rgba(255,255,255,0.7);
            color: var(--gray);
            transition: all 0.2s;
        }
        .bim-chip:hover { border-color: var(--verde); color: var(--verde); }
        .bim-chip.activo {
            background: var(--verde);
            color: white;
            border-color: var(--verde);
        }
        .leyenda-fechas {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.8rem;
            padding: 0.6rem 1rem;
            background: rgba(255,255,255,0.6);
            border-radius: 10px;
            font-size: 0.75rem;
        }
        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .leyenda-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
        }

        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.abierto { display: flex; }
        .modal {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.2s ease;
        }
        .modal h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--verde-muted);
        }
        .modal-info { margin-bottom: 0.8rem; font-size: 0.9rem; }
        .modal-info strong { color: var(--verde); }

        /* Botones de agenda */
        .agenda-titulo {
            font-size: 0.78rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 1.2rem 0 0.6rem;
        }
        .agenda-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            margin-bottom: 0.8rem;
        }
        .btn-agenda {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.65rem 0.5rem;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-google {
            background: #4285F4;
            color: white;
        }
        .btn-google:hover { background: #3367d6; }
        .btn-ics {
            background: #1d1d1f;
            color: white;
        }
        .btn-ics:hover { background: #333; }
        .modal-acciones { display: flex; gap: 0.8rem; margin-top: 0.5rem; }
        .modal-close {
            flex: 1;
            background: var(--dark);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
        }
        .modal-close:hover { background: var(--verde); }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @media (max-width: 600px) {
            .horario-controles { flex-direction: column; align-items: flex-start; }
            .agenda-btns { grid-template-columns: 1fr; }
        }

        /* ══ Panel de asignación (solo admin) ══ */
        .asig-section {
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            border: 1px solid rgba(16,185,129,0.15);
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.8rem;
            overflow: hidden;
        }
        .asig-header {
            background: linear-gradient(135deg,#022C22,#064e3b);
            color: white;
            padding: 1rem 1.4rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }
        .asig-header h2 { font-size: 1rem; font-weight: 800; margin: 0; }
        .asig-toggle { font-size: 1.1rem; transition: transform 0.25s; }
        .asig-toggle.open { transform: rotate(180deg); }
        .asig-body { padding: 1.4rem; display: none; }
        .asig-body.open { display: block; }

        .pasos-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.4rem;
        }
        .paso-box {
            background: rgba(255,255,255,0.9);
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            border: 2px solid rgba(16,185,129,0.15);
        }
        .paso-box.activo { border-color: #10B981; }
        .paso-box.bloqueado { opacity: 0.5; pointer-events: none; }
        .paso-label {
            font-size: 0.7rem; font-weight: 800;
            color: #10B981; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 0.45rem;
            display: flex; align-items: center; gap: 0.35rem;
        }
        .paso-label .num {
            background: #10B981; color: white;
            width: 17px; height: 17px;
            border-radius: 50%; font-size: 0.65rem;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
        }
        .paso-label .num.gris { background: #9ca3af; }
        .paso-select {
            width: 100%; padding: 0.6rem 0.8rem;
            border: 2px solid rgba(16,185,129,0.2);
            border-radius: 9px; font-size: 0.86rem;
            outline: none; background: white; cursor: pointer;
            box-sizing: border-box;
        }
        .paso-select:focus { border-color: #10B981; }

        .modulo-banner-inline {
            background: linear-gradient(135deg,#022C22,#064e3b);
            color: white; border-radius: 12px;
            padding: 0.9rem 1.2rem; margin-bottom: 1.2rem;
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .modulo-banner-inline .titulo { font-size: 0.98rem; font-weight: 800; }
        .modulo-banner-inline .sub { font-size: 0.78rem; opacity: 0.8; margin-top: 0.1rem; }
        .modulo-banner-inline .badges { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-left: auto; }
        .asig-badge { padding: 0.25rem 0.7rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .asig-badge-e  { background:#fecaca; color:#991b1b; }
        .asig-badge-t  { background:#fef08a; color:#854d0e; }
        .asig-badge-b  { background:#bbf7d0; color:#166534; }
        .asig-badge-est { background:#f59e0b; color:#422006; }

        .asig-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.2rem; }
        .asig-card {
            background: rgba(240,253,244,0.7);
            border-radius: 12px; padding: 1.2rem;
            border: 1px solid rgba(16,185,129,0.12);
        }
        .asig-card h4 {
            font-size: 0.88rem; font-weight: 700;
            color: #022C22; margin-bottom: 1rem;
            padding-bottom: 0.4rem;
            border-bottom: 2px solid rgba(16,185,129,0.15);
        }
        .est-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.9rem; }
        .est-chip {
            background: #f0fdf4; border: 1px solid #bbf7d0;
            color: #065f46; padding: 0.25rem 0.7rem;
            border-radius: 20px; font-size: 0.76rem; font-weight: 600;
        }
        .asig-campo { margin-bottom: 0.9rem; }
        .asig-campo label {
            display: block; font-size: 0.74rem; font-weight: 700;
            color: #555; margin-bottom: 0.25rem;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .asig-campo input, .asig-campo select {
            width: 100%; padding: 0.62rem 0.8rem;
            border: 2px solid rgba(16,185,129,0.2);
            border-radius: 8px; font-size: 0.87rem;
            outline: none; background: white;
            box-sizing: border-box;
        }
        .asig-campo input:focus, .asig-campo select:focus { border-color: #10B981; }
        .asig-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .btn-asig-guardar {
            background: linear-gradient(135deg,#059669,#10B981);
            color: white; border: none; padding: 0.8rem;
            border-radius: 9px; font-weight: 700; cursor: pointer;
            width: 100%; font-size: 0.92rem; margin-top: 0.4rem;
            transition: all 0.2s;
        }
        .btn-asig-guardar:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(5,150,105,0.3); }
        .btn-asig-guardar:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

        .htable { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .htable th {
            background: #f0fdf4; color: #065f46; font-weight: 700;
            padding: 0.55rem 0.7rem; text-align: left;
            border-bottom: 2px solid #d1fae5;
        }
        .htable td { padding: 0.55rem 0.7rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .tag-bim { background:#022C22; color:#f59e0b; padding:0.12rem 0.45rem; border-radius:4px; font-size:0.7rem; font-weight:700; }
        .tag-dia { background:#e0f2fe; color:#0369a1; padding:0.18rem 0.5rem; border-radius:5px; font-size:0.76rem; font-weight:700; }
        .btn-del { background:transparent; border:1px solid #ef4444; color:#ef4444; padding:0.22rem 0.55rem; border-radius:5px; cursor:pointer; font-size:0.78rem; font-weight:700; transition:all 0.2s; }
        .btn-del:hover { background:#ef4444; color:white; }

        .asig-ok  { background:rgba(16,185,129,0.1); color:#065f46; padding:0.7rem 1rem; border-radius:8px; margin-bottom:1rem; border-left:4px solid #10b981; font-size:0.86rem; }
        .asig-err { background:rgba(239,68,68,0.08); color:#991b1b; padding:0.7rem 1rem; border-radius:8px; margin-bottom:1rem; border-left:4px solid #ef4444; font-size:0.86rem; }
        .asig-empty { text-align:center; padding:2rem 1rem; color:#9ca3af; font-size:0.84rem; }

        @media(max-width:820px) {
            .pasos-selector { grid-template-columns: 1fr; }
            .asig-grid { grid-template-columns: 1fr; }
            .asig-2col { grid-template-columns: 1fr; }
        }

        /* ── Fondo verde desvanecido ── */
        body {
            background: linear-gradient(160deg,
                #e8f8f1 0%,
                #d1fae5 30%,
                #ecfdf5 60%,
                #f0fdf4 100%);
            min-height: 100vh;
        }
.dashboard-container {
            background: transparent;
        }
        .btn-volver {
            background: rgba(255,255,255,0.6);
            border: 1px solid rgba(16,185,129,0.25);
            backdrop-filter: blur(8px);
        }
        .seccion-titulo {
            color: #065f46;
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📅 Horarios</span>
    <a href="logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="dashboard-container">

    <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

    <?php if ($rol === 'admin'): ?>
    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- PANEL DE ASIGNACIÓN DE HORARIOS (solo admin)             -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <div class="asig-section">
        <div class="asig-header" onclick="toggleAsig()">
            <h2>📅 Asignar Horarios</h2>
            <span class="asig-toggle <?php echo ($docente_sel || $mensaje_asig) ? 'open' : ''; ?>" id="asig-toggle-icon">▼</span>
        </div>
        <div class="asig-body <?php echo ($docente_sel || $mensaje_asig) ? 'open' : ''; ?>" id="asig-body">

            <?php if ($mensaje_asig):
                $mp = explode('|', $mensaje_asig, 2); ?>
                <div class="<?php echo $mp[0] === 'success' ? 'asig-ok' : 'asig-err'; ?>">
                    <?php echo htmlspecialchars($mp[1]); ?>
                </div>
            <?php endif; ?>

            <!-- Paso 1 y 2: Selección docente → módulo -->
            <div class="pasos-selector">

                <!-- Paso 1 -->
                <div class="paso-box <?php echo $docente_sel ? 'activo' : ''; ?>">
                    <div class="paso-label">
                        <span class="num <?php echo !$docente_sel ? 'gris' : ''; ?>">1</span>
                        Selecciona el docente
                    </div>
                    <select class="paso-select"
                            onchange="window.location.href='horarios.php?docente_id='+this.value">
                        <option value="0">-- Elige un docente --</option>
                        <?php foreach ($docentes_asig as $d): ?>
                            <option value="<?php echo $d['id']; ?>"
                                    <?php echo $d['id'] == $docente_sel ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['username']); ?>
                                (<?php echo $d['total_modulos']; ?> módulo<?php echo $d['total_modulos'] != 1 ? 's' : ''; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Paso 2 -->
                <div class="paso-box <?php echo $pm_sel ? 'activo' : ($docente_sel ? '' : 'bloqueado'); ?>">
                    <div class="paso-label">
                        <span class="num <?php echo !$pm_sel ? 'gris' : ''; ?>">2</span>
                        Selecciona el módulo
                    </div>
                    <?php if (!$docente_sel): ?>
                        <select class="paso-select" disabled><option>Primero elige un docente</option></select>
                    <?php elseif (empty($modulos_asig)): ?>
                        <select class="paso-select" disabled><option>Este docente no tiene módulos</option></select>
                    <?php else: ?>
                        <select class="paso-select"
                                onchange="window.location.href='horarios.php?docente_id=<?php echo $docente_sel; ?>&pm_id='+this.value">
                            <option value="0">-- Elige un módulo --</option>
                            <?php foreach ($modulos_asig as $m): ?>
                                <option value="<?php echo $m['id']; ?>"
                                        <?php echo $m['id'] == $pm_sel ? 'selected' : ''; ?>>
                                    <?php if ($m['bimestre']): ?>[Bim <?php echo $m['bimestre']; ?>] <?php endif; ?>
                                    <?php echo htmlspecialchars($m['modulo_nombre']); ?>
                                    — <?php echo htmlspecialchars($m['programa_nombre']); ?>
                                    · <?php echo $m['total_estudiantes']; ?> est.
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($pm_sel && $modulo_sel_info): ?>

            <!-- Banner del módulo -->
            <?php
            $tipo = $modulo_sel_info['tipo'];
            $bcls = $tipo === 'transversal' ? 'asig-badge-t' : ($tipo === 'basico' ? 'asig-badge-b' : 'asig-badge-e');
            $blbl = ['especifico'=>'Específico','transversal'=>'Transversal','basico'=>'Básico'][$tipo] ?? $tipo;
            ?>
            <div class="modulo-banner-inline">
                <div>
                    <div class="titulo"><?php echo htmlspecialchars($modulo_sel_info['modulo_nombre']); ?></div>
                    <div class="sub">
                        <?php echo htmlspecialchars($modulo_sel_info['programa_nombre']); ?>
                        &nbsp;·&nbsp; <?php echo htmlspecialchars($docente_info_sel['username']); ?>
                        <?php if ($modulo_sel_info['bimestre']): ?> &nbsp;·&nbsp; Bimestre <?php echo $modulo_sel_info['bimestre']; ?><?php endif; ?>
                    </div>
                </div>
                <div class="badges">
                    <span class="asig-badge <?php echo $bcls; ?>"><?php echo $blbl; ?></span>
                    <span class="asig-badge asig-badge-est">👥 <?php echo count($estudiantes_modulo_asig); ?> estudiante<?php echo count($estudiantes_modulo_asig) != 1 ? 's' : ''; ?></span>
                </div>
            </div>

            <div class="asig-grid">

                <!-- Formulario asignar -->
                <div class="asig-card">
                    <h4>➕ Asignar Horario</h4>

                    <?php if (empty($estudiantes_modulo_asig)): ?>
                        <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:9px;padding:0.8rem;font-size:0.84rem;color:#78350f;margin-bottom:0.9rem;">
                            ⚠️ Este módulo no tiene estudiantes asignados.<br>
                            <a href="admin/modulos_estudiantes.php" style="color:#92400e;font-weight:700;">→ Ir a Módulos Estudiantes</a>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.78rem;color:#6b7280;margin-bottom:0.4rem;">
                            Se aplicará a <strong><?php echo count($estudiantes_modulo_asig); ?></strong> estudiante(s):
                        </p>
                        <div class="est-chips">
                            <?php foreach ($estudiantes_modulo_asig as $e): ?>
                                <span class="est-chip"><?php echo htmlspecialchars($e['nombre']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="horarios.php?docente_id=<?php echo $docente_sel; ?>&pm_id=<?php echo $pm_sel; ?>">
                        <input type="hidden" name="csrf_token"       value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="accion_horario"   value="agregar">
                        <input type="hidden" name="pm_id"            value="<?php echo $pm_sel; ?>">
                        <input type="hidden" name="docente_id_r"     value="<?php echo $docente_sel; ?>">
                        <input type="hidden" name="pm_id_r"          value="<?php echo $pm_sel; ?>">
                        <input type="hidden" name="estudiante_id_r"  value="<?php echo $estudiante_id; ?>">

                        <div class="asig-campo">
                            <label>Bimestre</label>
                            <select name="bimestre_id">
                                <option value="">Sin bimestre específico</option>
                                <?php foreach ($bimestres_form as $b): ?>
                                    <option value="<?php echo $b['id']; ?>">
                                        Bimestre <?php echo $b['numero']; ?>
                                        — <?php echo date('d/m', strtotime($b['fecha_inicio'])); ?>
                                        al <?php echo date('d/m/Y', strtotime($b['fecha_fin'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="asig-campo">
                            <label>Días de clase</label>
                            <select name="dias_par" required>
                                <option value="">Selecciona los días</option>
                                <option value="Lunes">Lunes</option>
                                <option value="Martes">Martes</option>
                                <option value="Miércoles">Miércoles</option>
                                <option value="Jueves">Jueves</option>
                                <option value="Viernes">Viernes</option>
                                <option value="Sábado">Sábado</option>
                                <option value="Lunes-Miércoles">Lunes y Miércoles</option>
                                <option value="Martes-Jueves">Martes y Jueves</option>
                                <option value="Lunes-Martes">Lunes y Martes</option>
                                <option value="Miércoles-Jueves">Miércoles y Jueves</option>
                                <option value="Lunes-Miércoles-Viernes">Lun, Mié y Vie</option>
                                <option value="Martes-Jueves-Sábado">Mar, Jue y Sáb</option>
                            </select>
                        </div>

                        <div class="asig-2col">
                            <div class="asig-campo">
                                <label>Hora inicio</label>
                                <input type="time" name="hora_inicio" value="18:30" required>
                            </div>
                            <div class="asig-campo">
                                <label>Hora fin</label>
                                <input type="time" name="hora_fin" value="21:30" required>
                            </div>
                        </div>

                        <div class="asig-campo">
                            <label>Salón</label>
                            <input type="text" name="salon" placeholder="Ej: Aula 101, Virtual">
                        </div>

                        <div class="asig-campo">
                            <label>Link virtual (opcional)</label>
                            <input type="url" name="link_virtual" placeholder="https://meet.google.com/...">
                        </div>

                        <button type="submit" class="btn-asig-guardar"
                                <?php echo empty($estudiantes_modulo_asig) ? 'disabled' : ''; ?>>
                            📅 Asignar a <?php echo count($estudiantes_modulo_asig); ?> estudiante(s)
                        </button>
                    </form>
                </div>

                <!-- Horarios ya asignados -->
                <div class="asig-card">
                    <h4>📋 Horarios asignados
                        <span style="background:#e0f2fe;color:#0369a1;padding:0.12rem 0.5rem;border-radius:15px;font-size:0.72rem;margin-left:0.4rem;">
                            <?php echo count($horarios_modulo_asig); ?> franja<?php echo count($horarios_modulo_asig) != 1 ? 's' : ''; ?>
                        </span>
                    </h4>

                    <?php if (empty($horarios_modulo_asig)): ?>
                        <div class="asig-empty">
                            <div style="font-size:2rem;margin-bottom:0.4rem;">📅</div>
                            <p>Sin horarios asignados aún.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="htable">
                                <thead>
                                    <tr>
                                        <th>Bim.</th><th>Día</th><th>Horario</th><th>Salón</th><th>Est.</th><th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($horarios_modulo_asig as $h): ?>
                                    <tr>
                                        <td><?php echo $h['bimestre_num'] ? "<span class='tag-bim'>B{$h['bimestre_num']}</span>" : '<span style="color:#aaa;">—</span>'; ?></td>
                                        <td><span class="tag-dia"><?php echo htmlspecialchars($h['dia']); ?></span></td>
                                        <td style="font-weight:700;white-space:nowrap;">
                                            <?php echo substr($h['hora_inicio'],0,5); ?> – <?php echo substr($h['hora_fin'],0,5); ?>
                                        </td>
                                        <td style="font-size:0.79rem;color:#555;"><?php echo htmlspecialchars($h['salon'] ?: '—'); ?></td>
                                        <td style="text-align:center;">
                                            <span style="background:#f0fdf4;color:#059669;padding:0.15rem 0.5rem;border-radius:5px;font-size:0.76rem;font-weight:700;">
                                                <?php echo $h['total_est']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="horarios.php?docente_id=<?php echo $docente_sel; ?>&pm_id=<?php echo $pm_sel; ?>"
                                                  onsubmit="return confirm('¿Eliminar este horario para todos los estudiantes?')">
                                                <input type="hidden" name="csrf_token"       value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="accion_horario"   value="eliminar">
                                                <input type="hidden" name="pm_id_del"        value="<?php echo $pm_sel; ?>">
                                                <input type="hidden" name="dia_del"          value="<?php echo htmlspecialchars($h['dia']); ?>">
                                                <input type="hidden" name="bimestre_id_del"  value="<?php echo $h['bimestre_id'] ?? ''; ?>">
                                                <input type="hidden" name="docente_id_r"     value="<?php echo $docente_sel; ?>">
                                                <input type="hidden" name="pm_id_r"          value="<?php echo $pm_sel; ?>">
                                                <input type="hidden" name="estudiante_id_r"  value="<?php echo $estudiante_id; ?>">
                                                <button type="submit" class="btn-del" title="Eliminar">🗑</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p style="font-size:0.75rem;color:#9ca3af;margin-top:0.6rem;">
                            Eliminar una franja la quita del horario de todos los estudiantes del módulo.
                        </p>
                    <?php endif; ?>
                </div>

            </div><!-- .asig-grid -->

            <?php elseif ($docente_sel && empty($modulos_asig)): ?>
                <div style="text-align:center;padding:1.5rem;color:#9ca3af;font-size:0.9rem;">
                    Este docente no tiene módulos asignados.
                    <a href="admin/gestionar_modulos.php" style="color:#059669;font-weight:700;">→ Gestionar Módulos</a>
                </div>
            <?php elseif (!$docente_sel): ?>
                <div style="text-align:center;padding:1.5rem;color:#9ca3af;font-size:0.9rem;">
                    👆 Selecciona un docente en el <strong>Paso 1</strong> para comenzar.
                </div>
            <?php elseif (!$pm_sel): ?>
                <div style="text-align:center;padding:1.5rem;color:#9ca3af;font-size:0.9rem;">
                    📚 Ahora selecciona un módulo en el <strong>Paso 2</strong>.
                </div>
            <?php endif; ?>

        </div><!-- .asig-body -->
    </div><!-- .asig-section -->
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- CALENDARIO                                               -->
    <!-- ══════════════════════════════════════════════════════════ -->

    <div class="horario-controles">
        <?php if ($rol !== 'estudiante'): ?>
        <select class="selector-estudiante" onchange="window.location.href='horarios.php?estudiante_id='+this.value">
            <option value="">Selecciona un estudiante</option>
            <?php foreach ($estudiantes as $e): ?>
                <option value="<?php echo $e['id']; ?>" <?php echo $e['id'] == $estudiante_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre']); ?> · <?php echo htmlspecialchars($e['documento']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($estudiante_actual): ?>
            <span class="estudiante-badge">📚 <?php echo htmlspecialchars($estudiante_actual['programa']); ?></span>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array($rol, ['admin','docente'])): ?>
        <a href="admin/gestionar_horarios.php"
           style="background:#059669;color:#ffffff;padding:0.6rem 1.2rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.88rem;display:inline-block;border:2px solid #047857;">
            ➕ Agregar clase
        </a>
        <?php endif; ?>
    </div>

    <!-- Filtro de bimestres -->
    <div class="filtro-bimestre" style="margin-bottom: 1rem;">
        <span style="font-size:0.82rem;font-weight:700;color:#666;">Bimestre:</span>
        <button class="bim-chip activo" onclick="filtrarBimestre(0, this)">Todos</button>
        <?php foreach ($bimestres as $b): ?>
            <button class="bim-chip" onclick="filtrarBimestre(<?php echo $b['id']; ?>, this)"
                    title="<?php echo date('d M', strtotime($b['fecha_inicio'])); ?> – <?php echo date('d M', strtotime($b['fecha_fin'])); ?>">
                B<?php echo $b['numero']; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Leyenda -->
    <div class="leyenda-fechas">
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#e84545;"></div> Festivo</div>
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#e91e63;"></div> Cultural</div>
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#059669;"></div> Institucional</div>
        <div class="leyenda-item"><div class="leyenda-dot" style="background:#25a865;"></div> Clase</div>
    </div>

    <!-- VISTA MENSUAL -->
    <div id="vista-mensual" style="margin-top:1rem;">
        <div class="mes-nav">
            <button class="mes-btn" onclick="cambiarMes(-1)">← Anterior</button>
            <h3 id="mes-titulo"></h3>
            <button class="mes-btn" onclick="cambiarMes(1)">Siguiente →</button>
        </div>
        <div style="overflow-x:auto;">
            <div class="mes-grid" id="mes-grid" style="min-width:560px;">
                <?php
                $dias_semana = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
                foreach ($dias_semana as $d) {
                    echo "<div class='mes-dia-header'>{$d}</div>";
                }
                ?>
            </div>
        </div>
    </div>

</div>

<!-- MODAL DETALLE -->
<div class="modal-overlay" id="modal-overlay" onclick="cerrarModal(event)">
    <div class="modal">
        <h3>📚 Detalle de Clase</h3>
        <div class="modal-info"><strong>Módulo:</strong> <span id="modal-materia"></span></div>
        <div class="modal-info"><strong>Día:</strong> <span id="modal-dia"></span></div>
        <div class="modal-info"><strong>Horario:</strong> <span id="modal-horario"></span></div>
        <div class="modal-info"><strong>Salón:</strong> <span id="modal-salon"></span></div>
        <div class="modal-info"><strong>Bimestre:</strong> <span id="modal-bimestre"></span></div>
        <div id="modal-link-row" style="display:none; margin-top:12px;">
            <a id="modal-link" href="#" target="_blank" style="
                display:flex; align-items:center; gap:10px;
                background:linear-gradient(135deg,#059669,#10B981);
                color:white; text-decoration:none;
                padding:14px 20px; border-radius:12px;
                font-weight:700; font-size:0.92rem;
                box-shadow:0 4px 15px rgba(5,150,105,0.3);
                transition:all 0.2s;
            " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(5,150,105,0.4)'" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 15px rgba(5,150,105,0.3)'">
                <span style="font-size:1.4rem;">📹</span>
                <span>
                    <span style="display:block;font-size:0.72rem;opacity:0.8;font-weight:400;letter-spacing:1px;text-transform:uppercase;">Clase Virtual Disponible</span>
                    <span style="display:block;">Unirse a la clase →</span>
                </span>
            </a>
        </div>

        <div class="agenda-titulo">📆 Agregar a mi agenda</div>
        <div class="agenda-btns">
            <a id="btn-google-cal" href="#" target="_blank" class="btn-agenda btn-google">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.418 0-8-3.582-8-8s3.582-8 8-8 8 3.582 8 8-3.582 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                Google Calendar
            </a>
            <button id="btn-ics" onclick="descargarICS()" class="btn-agenda btn-ics">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                iPhone / Android
            </button>
        </div>

        <?php if (in_array($rol, ['admin','docente'])): ?>
        <div class="modal-acciones">
            <a id="modal-editar" href="#" style="flex:1;background:var(--verde);color:white;padding:0.7rem;border-radius:8px;text-align:center;text-decoration:none;font-weight:700;font-size:0.88rem;">✏️ Editar</a>
            <button onclick="cerrarModal()" class="modal-close" style="flex:1;">Cerrar</button>
        </div>
        <?php else: ?>
        <button class="modal-close" onclick="cerrarModal()">Cerrar</button>
        <?php endif; ?>
    </div>
</div>

<script>
const horariosData = <?php echo json_encode($horarios_json, JSON_UNESCAPED_UNICODE); ?>;
const colorMap = <?php echo json_encode($color_map, JSON_UNESCAPED_UNICODE); ?>;
const fechasImportantes = <?php echo json_encode($fechas_importantes, JSON_UNESCAPED_UNICODE); ?>;
const bimestresData = <?php echo json_encode($bimestres, JSON_UNESCAPED_UNICODE); ?>;

const diasNum = {
    'Lunes': 1, 'Martes': 2, 'Miércoles': 3,
    'Jueves': 4, 'Viernes': 5, 'Sábado': 6
};
const byday = {
    'Lunes':'MO','Martes':'TU','Miércoles':'WE',
    'Jueves':'TH','Viernes':'FR','Sábado':'SA'
};
const mesesNombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                      'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const horasGrilla = ['07:00','08:00','09:00','10:00','11:00','12:00',
                     '13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];
const diasOrden = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

let mesActual = new Date().getMonth();
let anioActual = new Date().getFullYear();
let bimestreFiltro = 0; // 0 = todos

function pad(n) { return String(n).padStart(2,'0'); }

function formatICSDate(date, hora) {
    const [h, m] = hora.split(':');
    return `${date.getFullYear()}${pad(date.getMonth()+1)}${pad(date.getDate())}T${pad(h)}${pad(m)}00`;
}

// Verificar si una fecha está dentro del rango de un bimestre
function fechaEnBimestre(fecha, bimInicio, bimFin) {
    if (!bimInicio || !bimFin) return true; // Sin bimestre asignado, mostrar siempre
    const f = new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate());
    const inicio = new Date(bimInicio + 'T00:00:00');
    const fin = new Date(bimFin + 'T23:59:59');
    return f >= inicio && f <= fin;
}

function filtrarBimestre(bimId, btn) {
    bimestreFiltro = bimId;
    document.querySelectorAll('.bim-chip').forEach(c => c.classList.remove('activo'));
    btn.classList.add('activo');

    // Navegar al mes de inicio del bimestre seleccionado
    if (bimId > 0) {
        const bim = bimestresData.find(b => b.id == bimId);
        if (bim) {
            const fechaInicio = new Date(bim.fecha_inicio + 'T00:00:00');
            mesActual = fechaInicio.getMonth();
            anioActual = fechaInicio.getFullYear();
        }
    }

    renderMes(mesActual, anioActual);
}

function cambiarMes(dir) {
    mesActual += dir;
    if (mesActual > 11) { mesActual = 0; anioActual++; }
    if (mesActual < 0) { mesActual = 11; anioActual--; }
    renderMes(mesActual, anioActual);
}

function renderMes(mes, anio) {
    document.getElementById('mes-titulo').textContent = mesesNombres[mes] + ' ' + anio;
    const grid = document.getElementById('mes-grid');
    const headers = Array.from(grid.querySelectorAll('.mes-dia-header'));
    grid.innerHTML = '';
    headers.forEach(h => grid.appendChild(h.cloneNode(true)));

    const primerDia = new Date(anio, mes, 1).getDay();
    const diasEnMes = new Date(anio, mes + 1, 0).getDate();
    const hoy = new Date();
    const diasMesAnterior = new Date(anio, mes, 0).getDate();

    // Indexar fechas importantes por fecha string (YYYY-MM-DD)
    const fechasIdx = {};
    fechasImportantes.forEach(f => {
        if (!fechasIdx[f.fecha]) fechasIdx[f.fecha] = [];
        fechasIdx[f.fecha].push(f);
    });

    for (let i = primerDia - 1; i >= 0; i--) {
        const div = document.createElement('div');
        div.className = 'mes-dia otro-mes';
        div.innerHTML = `<div class="mes-num">${diasMesAnterior - i}</div>`;
        grid.appendChild(div);
    }

    for (let d = 1; d <= diasEnMes; d++) {
        const fecha = new Date(anio, mes, d);
        const diaSemana = fecha.getDay();
        const esHoy = d === hoy.getDate() && mes === hoy.getMonth() && anio === hoy.getFullYear();
        const fechaStr = `${anio}-${pad(mes+1)}-${pad(d)}`;

        // Verificar si es día festivo
        const esFestivo = fechasIdx[fechaStr] && fechasIdx[fechaStr].some(f => f.tipo === 'festivo');

        const div = document.createElement('div');
        div.className = 'mes-dia' + (esHoy ? ' hoy' : '') + (esFestivo ? ' dia-festivo' : '');

        const numDiv = document.createElement('div');
        numDiv.className = 'mes-num' + (esHoy ? ' hoy-num' : '');
        numDiv.textContent = d;
        div.appendChild(numDiv);

        // Mostrar fechas importantes
        if (fechasIdx[fechaStr]) {
            fechasIdx[fechaStr].forEach(fi => {
                const evento = document.createElement('div');
                evento.className = 'mes-evento fecha-importante';
                evento.style.background = fi.color || '#e84545';
                evento.textContent = fi.nombre;
                evento.title = fi.nombre + ' (' + fi.tipo + ')';
                div.appendChild(evento);
            });
        }

        // Mostrar clases (solo si la fecha cae dentro del bimestre asignado y no es festivo)
        if (!esFestivo) {
            horariosData.forEach(h => {
                if (diasNum[h.dia] === diaSemana) {
                    // Filtrar por bimestre seleccionado
                    if (bimestreFiltro > 0 && h.bimestre_id != bimestreFiltro) return;

                    // Solo mostrar si la fecha cae dentro del rango del bimestre
                    if (h.bim_inicio && h.bim_fin) {
                        if (!fechaEnBimestre(fecha, h.bim_inicio, h.bim_fin)) return;
                    }

                    const color = colorMap[h.programa_modulo_id || h.materia_id] || '#25a865';
                    const evento = document.createElement('div');
                    evento.className = 'mes-evento';
                    evento.style.background = color;
                    evento.textContent = h.materia;
                    evento.title = h.materia + ' · ' + h.hora_inicio.substring(0,5) + '-' + h.hora_fin.substring(0,5);
                    evento.onclick = () => verDetalle(h.id, h.materia, h.dia,
                        h.hora_inicio.substring(0,5), h.hora_fin.substring(0,5), h.salon, fecha,
                        h.bimestre_num, h.bim_inicio, h.bim_fin, h.link_virtual);
                    div.appendChild(evento);
                }
            });
        }

        grid.appendChild(div);
    }

    const totalCeldas = grid.children.length - 7;
    const celdasRestantes = 7 - (totalCeldas % 7);
    if (celdasRestantes < 7) {
        for (let i = 1; i <= celdasRestantes; i++) {
            const div = document.createElement('div');
            div.className = 'mes-dia otro-mes';
            div.innerHTML = `<div class="mes-num">${i}</div>`;
            grid.appendChild(div);
        }
    }
}

// Estado del modal
let modalData = {};

function verDetalle(id, materia, dia, inicio, fin, salon, fechaEvento, bimestreNum, bimInicio, bimFin, linkVirtual) {
    document.getElementById('modal-materia').textContent = materia;
    document.getElementById('modal-dia').textContent = dia;
    document.getElementById('modal-horario').textContent = inicio + ' – ' + fin;
    document.getElementById('modal-salon').textContent = salon || 'No asignado';

    const bimInfo = document.getElementById('modal-bimestre');
    if (bimInfo) {
        if (bimestreNum) {
            bimInfo.textContent = 'Bimestre ' + bimestreNum + ' (' + bimInicio + ' a ' + bimFin + ')';
            bimInfo.parentElement.style.display = '';
        } else {
            bimInfo.parentElement.style.display = 'none';
        }
    }

    const linkRow = document.getElementById('modal-link-row');
    const linkEl = document.getElementById('modal-link');
    if (linkVirtual) {
        linkEl.href = linkVirtual;
        linkRow.style.display = '';
    } else {
        linkRow.style.display = 'none';
    }

    const editarBtn = document.getElementById('modal-editar');
    if (editarBtn) editarBtn.href = 'admin/gestionar_horarios.php?estudiante_id=<?php echo $estudiante_id; ?>&editar=' + id;

    // Guardar datos para agenda
    modalData = { materia, dia, inicio, fin, salon, fechaEvento: fechaEvento || null };

    // Generar link Google Calendar
    generarLinkGoogle();

    document.getElementById('modal-overlay').classList.add('abierto');
}

function generarLinkGoogle() {
    const { materia, dia, inicio, fin, salon, fechaEvento } = modalData;

    // Usar la fecha del evento si está disponible, si no usar próximo día de la semana
    let fechaBase = fechaEvento ? new Date(fechaEvento) : proximaFechaDia(dia);

    const dtStart = formatICSDate(fechaBase, inicio);
    const dtEnd   = formatICSDate(fechaBase, fin);
    const byDay   = byday[dia] || 'MO';

    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: `${materia} – INTEP`,
        dates: `${dtStart}/${dtEnd}`,
        details: `Salón: ${salon || 'No asignado'}\nInstituto INTEP`,
        location: 'Instituto INTEP, Madrid, Cundinamarca',
        recur: `RRULE:FREQ=WEEKLY;BYDAY=${byDay}`
    });
    document.getElementById('btn-google-cal').href =
        'https://calendar.google.com/calendar/render?' + params.toString();
}

function proximaFechaDia(nombreDia) {
    const objetivo = diasNum[nombreDia] || 1;
    const hoy = new Date();
    const diaActual = hoy.getDay();
    let diff = objetivo - diaActual;
    if (diff <= 0) diff += 7;
    const result = new Date(hoy);
    result.setDate(hoy.getDate() + diff);
    return result;
}

function descargarICS() {
    const { materia, dia, inicio, fin, salon, fechaEvento } = modalData;
    let fechaBase = fechaEvento ? new Date(fechaEvento) : proximaFechaDia(dia);
    const byDay = byday[dia] || 'MO';

    const dtStart = formatICSDate(fechaBase, inicio);
    const dtEnd   = formatICSDate(fechaBase, fin);
    const uid     = `intep-${Date.now()}@intep.edu.co`;

    const ics = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//INTEP//Portal Estudiantil//ES',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        `UID:${uid}`,
        `DTSTART:${dtStart}`,
        `DTEND:${dtEnd}`,
        `RRULE:FREQ=WEEKLY;BYDAY=${byDay}`,
        `SUMMARY:${materia} – INTEP`,
        `LOCATION:${salon || 'Instituto INTEP'}, Madrid, Cundinamarca`,
        `DESCRIPTION:Clase semanal de ${materia}\\nSalón: ${salon || 'No asignado'}\\nInstituto INTEP`,
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');

    const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `${materia.replace(/\s+/g,'-')}-INTEP.ics`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function cerrarModal(e) {
    if (!e || e.target === document.getElementById('modal-overlay')) {
        document.getElementById('modal-overlay').classList.remove('abierto');
    }
}

// Inicializar vista mensual
renderMes(mesActual, anioActual);

function toggleAsig() {
    const body = document.getElementById('asig-body');
    const icon = document.getElementById('asig-toggle-icon');
    body.classList.toggle('open');
    icon.classList.toggle('open');
}

</script>

<script src="/intep/sesion.js"></script>
</body>
</html>
