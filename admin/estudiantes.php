<?php
require_once '../config.php';
require_once '../mail_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$mensaje = '';

// Auto-migración: agregar programa_id a usuarios si no existe
$col_check = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE 'programa_id'");
if (mysqli_num_rows($col_check) === 0) {
    mysqli_query($conexion, "ALTER TABLE usuarios ADD COLUMN programa_id INT NULL DEFAULT NULL");
}

// ============================================
// PROCESAR ACCIONES POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF en todas las acciones POST
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'error|Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
    $accion = $_POST['accion'] ?? '';

    // --- CREAR ESTUDIANTE ---
    if ($accion === 'crear_estudiante') {
        $nombre = trim($_POST['nombre']);
        $documento = trim($_POST['documento']);
        $email = trim($_POST['email']);
        $programa_id = (int)$_POST['programa_id'];
        $fecha_ingreso = $_POST['fecha_ingreso'];
        $password = trim($_POST['password']);

        // Validar complejidad de contraseña
        $validacion = validarPassword($password);
        if ($validacion !== true) {
            $mensaje = 'error|' . $validacion;
        } else {

        // Verificar documento duplicado
        $check = mysqli_prepare($conexion, "SELECT id FROM estudiantes WHERE documento = ?");
        mysqli_stmt_bind_param($check, 's', $documento);
        mysqli_stmt_execute($check);
        $check_res = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_res) > 0) {
            $mensaje = 'error|El documento ya existe en el sistema.';
        } else {
            $q1 = "INSERT INTO estudiantes (nombre, documento, email, programa_id, fecha_ingreso, estado) 
                   VALUES (?, ?, ?, ?, ?, 'activo')";
            $stmt1 = mysqli_prepare($conexion, $q1);
            mysqli_stmt_bind_param($stmt1, 'sssis', $nombre, $documento, $email, $programa_id, $fecha_ingreso);

            if (mysqli_stmt_execute($stmt1)) {
                $nuevo_id = mysqli_insert_id($conexion);
                $passwordHash = hashPassword($password);
                $q2 = "INSERT INTO usuarios (username, password_hash, rol, estudiante_id, estado) 
                       VALUES (?, ?, 'estudiante', ?, 'activo')";
                $stmt2 = mysqli_prepare($conexion, $q2);
                mysqli_stmt_bind_param($stmt2, 'ssi', $documento, $passwordHash, $nuevo_id);
                mysqli_stmt_execute($stmt2);
                // Enviar correo de bienvenida (no interrumpe el flujo si falla)
                enviarCorreoBienvenida($nombre, $email, $documento, $password);
                $mensaje = 'success|✅ Estudiante "' . $nombre . '" creado correctamente.';
            } else {
                $mensaje = 'error|Error al crear el estudiante. Intenta de nuevo.';
            }
        }
        } // fin validación contraseña

    // --- EDITAR ESTUDIANTE ---
    } elseif ($accion === 'editar_estudiante') {
        $id = (int)$_POST['estudiante_id'];
        $nombre = trim($_POST['nombre']);
        $documento = trim($_POST['documento']);
        $email = trim($_POST['email']);
        $programa_id = (int)$_POST['programa_id'];

        // Verificar documento duplicado (excluyendo el actual)
        $check = mysqli_prepare($conexion, "SELECT id FROM estudiantes WHERE documento = ? AND id != ?");
        mysqli_stmt_bind_param($check, 'si', $documento, $id);
        mysqli_stmt_execute($check);
        $check_res = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_res) > 0) {
            $mensaje = 'error|El documento ya está en uso por otro estudiante.';
        } else {
            $q = "UPDATE estudiantes SET nombre = ?, documento = ?, email = ?, programa_id = ? WHERE id = ?";
            $stmt = mysqli_prepare($conexion, $q);
            mysqli_stmt_bind_param($stmt, 'sssii', $nombre, $documento, $email, $programa_id, $id);
            if (mysqli_stmt_execute($stmt)) {
                // Actualizar username en usuarios
                $q2 = "UPDATE usuarios SET username = ? WHERE estudiante_id = ?";
                $stmt2 = mysqli_prepare($conexion, $q2);
                mysqli_stmt_bind_param($stmt2, 'si', $documento, $id);
                mysqli_stmt_execute($stmt2);
                $mensaje = 'success|✅ Datos de "' . $nombre . '" actualizados.';
            } else {
                $mensaje = 'error|Error al actualizar datos.';
            }
        }

    // --- RESETEAR CONTRASEÑA ---
    } elseif ($accion === 'reset_password') {
        $id = (int)$_POST['estudiante_id'];
        $nueva_password = trim($_POST['nueva_password']);
        $validacion = validarPassword($nueva_password);
        if ($validacion !== true) {
            $mensaje = 'error|' . $validacion;
        } else {
            $passwordHash = hashPassword($nueva_password);
            $q = "UPDATE usuarios SET password_hash = ? WHERE estudiante_id = ?";
            $stmt = mysqli_prepare($conexion, $q);
            mysqli_stmt_bind_param($stmt, 'si', $passwordHash, $id);
            if (mysqli_stmt_execute($stmt)) {
                $mensaje = 'success|🔑 Contraseña reseteada correctamente.';
            } else {
                $mensaje = 'error|Error al resetear contraseña.';
            }
        }

    // --- DESACTIVAR ESTUDIANTE ---
    } elseif ($accion === 'desactivar') {
        $id = (int)$_POST['estudiante_id'];
        $q = "UPDATE estudiantes SET estado = 'inactivo' WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $q2 = "UPDATE usuarios SET estado = 'inactivo' WHERE estudiante_id = ?";
        $stmt2 = mysqli_prepare($conexion, $q2);
        mysqli_stmt_bind_param($stmt2, 'i', $id);
        mysqli_stmt_execute($stmt2);
        $mensaje = 'success|Estudiante desactivado correctamente.';

    // --- ACTIVAR ESTUDIANTE ---
    } elseif ($accion === 'activar') {
        $id = (int)$_POST['estudiante_id'];
        $q = "UPDATE estudiantes SET estado = 'activo' WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $q2 = "UPDATE usuarios SET estado = 'activo' WHERE estudiante_id = ?";
        $stmt2 = mysqli_prepare($conexion, $q2);
        mysqli_stmt_bind_param($stmt2, 'i', $id);
        mysqli_stmt_execute($stmt2);
        $mensaje = 'success|Estudiante reactivado correctamente.';

    // --- ELIMINAR ESTUDIANTE PERMANENTEMENTE ---
    } elseif ($accion === 'eliminar_estudiante') {
        $id = (int)$_POST['estudiante_id'];
        // Eliminar registros dependientes
        $tablas = ['notas', 'asistencia', 'observaciones', 'horarios', 'pagos'];
        foreach ($tablas as $tabla) {
            $stmt = mysqli_prepare($conexion, "DELETE FROM $tabla WHERE estudiante_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
            }
        }
        // Eliminar usuario asociado
        $stmt = mysqli_prepare($conexion, "DELETE FROM usuarios WHERE estudiante_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        // Eliminar estudiante
        $stmt = mysqli_prepare($conexion, "DELETE FROM estudiantes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|🗑 Estudiante eliminado permanentemente.';

    // --- CREAR DOCENTE ---
    } elseif ($accion === 'crear_docente') {
        $nombre_doc = trim($_POST['nombre_docente']);
        $username_doc = trim($_POST['username_docente']);
        $password_doc = trim($_POST['password_docente']);
        $programa_id_doc = !empty($_POST['programa_id_docente']) ? (int)$_POST['programa_id_docente'] : null;

        $validacion = validarPassword($password_doc);
        if ($validacion !== true) {
            $mensaje = 'error|' . $validacion;
        } else {

        $check = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE username = ?");
        mysqli_stmt_bind_param($check, 's', $username_doc);
        mysqli_stmt_execute($check);
        $check_res = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_res) > 0) {
            $mensaje = 'error|El nombre de usuario ya existe.';
        } else {
            $passwordHashDoc = hashPassword($password_doc);
            $q = "INSERT INTO usuarios (username, password_hash, rol, estado, programa_id) VALUES (?, ?, 'docente', 'activo', ?)";
            $stmt = mysqli_prepare($conexion, $q);
            mysqli_stmt_bind_param($stmt, 'ssi', $username_doc, $passwordHashDoc, $programa_id_doc);
            if (mysqli_stmt_execute($stmt)) {
                $mensaje = 'success|✅ Docente "' . $nombre_doc . '" creado correctamente.';
            } else {
                $mensaje = 'error|Error al crear docente.';
            }
        }
        } // fin validación contraseña docente

    // --- DESACTIVAR DOCENTE ---
    } elseif ($accion === 'desactivar_docente') {
        $id = (int)$_POST['docente_id'];
        $q = "UPDATE usuarios SET estado = 'inactivo' WHERE id = ? AND rol = 'docente'";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Docente desactivado.';

    // --- ACTIVAR DOCENTE ---
    } elseif ($accion === 'activar_docente') {
        $id = (int)$_POST['docente_id'];
        $q = "UPDATE usuarios SET estado = 'activo' WHERE id = ? AND rol = 'docente'";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Docente reactivado.';

    // --- ELIMINAR DOCENTE PERMANENTEMENTE ---
    } elseif ($accion === 'eliminar_docente') {
        $id = (int)$_POST['docente_id'];
        // Desasignar de programa_modulo (nuevo modelo)
        $stmt = mysqli_prepare($conexion, "UPDATE programa_modulo SET docente_id = NULL WHERE docente_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        // Desasignar de modulos (tabla legacy - evita FK constraint al borrar usuario)
        $stmt = mysqli_prepare($conexion, "UPDATE modulos SET docente_id = NULL WHERE docente_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        // Eliminar el usuario docente
        $stmt = mysqli_prepare($conexion, "DELETE FROM usuarios WHERE id = ? AND rol = 'docente'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|🗑 Docente eliminado permanentemente.';

    // --- RESET PASSWORD DOCENTE ---
    } elseif ($accion === 'reset_password_docente') {
        $id = (int)$_POST['docente_id'];
        $nueva_password = trim($_POST['nueva_password']);
        $validacion = validarPassword($nueva_password);
        if ($validacion !== true) {
            $mensaje = 'error|' . $validacion;
        } else {
        $passwordHashDoc = hashPassword($nueva_password);
        $q = "UPDATE usuarios SET password_hash = ? WHERE id = ? AND rol = 'docente'";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'si', $passwordHashDoc, $id);
        if (mysqli_stmt_execute($stmt)) {
            $mensaje = 'success|🔑 Contraseña de docente reseteada.';
        }
        } // fin validación contraseña docente reset

    // --- CAMBIAR CONTRASEÑA ADMIN ---
    }
    } // fin verificación CSRF
}

// ============================================
// OBTENER DATOS
// ============================================

// Estudiantes
$estudiantes_activos = [];
$estudiantes_inactivos = [];
$q_est = "SELECT e.*, p.nombre as programa 
          FROM estudiantes e 
          LEFT JOIN programas p ON e.programa_id = p.id 
          ORDER BY e.nombre ASC";
$res_est = mysqli_query($conexion, $q_est);
while ($e = mysqli_fetch_assoc($res_est)) {
    if ($e['estado'] === 'activo') {
        $estudiantes_activos[] = $e;
    } else {
        $estudiantes_inactivos[] = $e;
    }
}

// Programas
$programas = [];
$res_prog = mysqli_query($conexion, "SELECT * FROM programas ORDER BY nombre ASC");
while ($p = mysqli_fetch_assoc($res_prog)) $programas[] = $p;

// Bimestres
$bimestres = [];
$res_bim = mysqli_query($conexion, "SELECT * FROM bimestres ORDER BY anio ASC, numero ASC");
if ($res_bim) while ($b = mysqli_fetch_assoc($res_bim)) $bimestres[] = $b;

// Docentes
$docentes = [];
$q_doc = "SELECT u.*, p.nombre as programa_nombre
          FROM usuarios u
          LEFT JOIN programas p ON u.programa_id = p.id
          WHERE u.rol = 'docente' ORDER BY u.username ASC";
$res_doc = mysqli_query($conexion, $q_doc);
while ($d = mysqli_fetch_assoc($res_doc)) $docentes[] = $d;

$msg_parts = $mensaje ? explode('|', $mensaje) : null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creación de Estudiantes – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        /* ===== FONDO CON DIFUMINACIÓN SUAVE ===== */
        html, body {
            min-height: 100%;
            background: #f8f9fc;
            background-attachment: fixed;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 600px 400px at 5% 10%, rgba(16,185,129,0.06) 0%, transparent 50%),
                radial-gradient(ellipse 400px 300px at 95% 20%, rgba(217,70,168,0.04) 0%, transparent 50%),
                radial-gradient(ellipse 300px 250px at 85% 85%, rgba(59,130,246,0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
            animation: fondo-mover 20s ease-in-out infinite;
        }

        @keyframes fondo-mover {
            0%, 100% { transform: translate(0,0); }
            50% { transform: translate(-10px, 5px); }
        }

        body::after {
            content: '';
            position: fixed;
            top: 20%;
            right: 5%;
            width: 200px;
            height: 200px;
            border: 1px solid rgba(16,185,129,0.08);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: circulo-float 15s ease-in-out infinite;
        }

        @keyframes circulo-float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        .dashboard-container {
            position: relative;
            z-index: 1;
        }

        /* ===== TABS ===== */
        .tabs-admin {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            background: #f0f4f2;
            padding: 0.4rem;
            border-radius: 12px;
        }

        .tab-admin {
            padding: 0.6rem 1rem;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.82rem;
            background: transparent;
            color: #666;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .tab-admin.activo {
            background: #059669;
            color: white;
            box-shadow: 0 2px 8px rgba(5,150,105,0.3);
        }

        .tab-admin:hover:not(.activo) {
            background: rgba(5,150,105,0.1);
            color: #059669;
        }

        .panel-admin { display: none; }
        .panel-admin.activo { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ===== CONTADORES ===== */
        .contador {
            background: rgba(255,255,255,0.3);
            padding: 0.1rem 0.5rem;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .tab-admin:not(.activo) .contador {
            background: rgba(5,150,105,0.15);
            color: #059669;
        }

        .contador-rojo {
            background: rgba(230,57,70,0.15);
            color: #e63946;
            padding: 0.1rem 0.5rem;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .tab-admin.activo .contador-rojo {
            background: rgba(255,255,255,0.3);
            color: white;
        }

        /* ===== CARDS ===== */
        .card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.08), 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.12), 0 2px 10px rgba(0,0,0,0.05);
        }

        .card h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            padding-bottom: 0.6rem;
            border-bottom: 2px solid rgba(16, 185, 129, 0.15);
            color: #022C22;
        }

        /* ===== GRID LAYOUTS ===== */
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(5, 150, 105, 0.06);
            border: 1px solid rgba(16, 185, 129, 0.08);
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .stat-card:hover { 
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.12);
        }
        .stat-card.verde { border-bottom-color: #10B981; }
        .stat-card.rojo { border-bottom-color: #e63946; }
        .stat-card.azul { border-bottom-color: #3B82F6; }
        .stat-card.amarillo { border-bottom-color: #F59E0B; }

        .stat-card .stat-numero {
            font-size: 2rem;
            font-weight: 800;
            display: block;
        }

        .stat-card.verde .stat-numero { color: #10B981; }
        .stat-card.rojo .stat-numero { color: #e63946; }
        .stat-card.azul .stat-numero { color: #3B82F6; }
        .stat-card.amarillo .stat-numero { color: #F59E0B; }

        .stat-card .stat-label {
            font-size: 0.78rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 0.2rem;
        }

        /* ===== FORMULARIOS ===== */
        .campo-admin {
            margin-bottom: 1rem;
        }

        .campo-admin label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .campo-admin input,
        .campo-admin select {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 2px solid #D1FAE5;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .campo-admin input:focus,
        .campo-admin select:focus {
            border-color: #10B981;
        }

        .campos-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn-crear {
            background: #059669;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            font-size: 0.95rem;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-crear:hover { background: #10B981; }
        .btn-crear:active { transform: scale(0.98); }

        /* ===== BUSCADOR ===== */
        .barra-herramientas {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .buscador-input {
            flex: 1;
            min-width: 200px;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: 2px solid #D1FAE5;
            border-radius: 10px;
            font-size: 0.9rem;
            outline: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23999' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 0.8rem center;
            transition: border-color 0.2s;
        }

        .buscador-input:focus { border-color: #10B981; }

        .filtro-select {
            padding: 0.6rem 1rem;
            border: 2px solid #D1FAE5;
            border-radius: 10px;
            font-size: 0.85rem;
            outline: none;
            background: white;
            min-width: 180px;
        }

        .filtro-select:focus { border-color: #10B981; }

        /* ===== TABLA ===== */
        .tabla-responsive {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #eee;
        }

        .tabla-responsive table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        .tabla-responsive thead th {
            background: #F0FDF4;
            padding: 0.8rem 1rem;
            text-align: left;
            font-size: 0.78rem;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #ECFDF5;
            white-space: nowrap;
        }

        .tabla-responsive tbody td {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .tabla-responsive tbody tr:hover {
            background: #F0FDF4;
        }

        .tabla-responsive tbody tr.fila-oculta {
            display: none;
        }

        .nombre-estudiante {
            font-weight: 600;
            color: #333;
        }

        .doc-estudiante {
            color: #888;
            font-size: 0.82rem;
            font-family: monospace;
        }

        .programa-tag {
            background: #eef6ff;
            color: #3B82F6;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ===== BADGES ===== */
        .badge-activo {
            background: #ECFDF5;
            color: #065F46;
            padding: 0.2rem 0.7rem;
            border-radius: 99px;
            font-size: 0.73rem;
            font-weight: 700;
        }

        .badge-inactivo {
            background: #ffe0e0;
            color: #c0392b;
            padding: 0.2rem 0.7rem;
            border-radius: 99px;
            font-size: 0.73rem;
            font-weight: 700;
        }

        /* ===== BOTONES DE ACCIÓN ===== */
        .acciones-cell {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .btn-accion {
            border: none;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-editar {
            background: #eef6ff;
            color: #3B82F6;
        }
        .btn-editar:hover { background: #3B82F6; color: white; }

        .btn-key {
            background: #fff3cd;
            color: #856404;
        }
        .btn-key:hover { background: #F59E0B; color: white; }

        .btn-desactivar {
            background: #ffe0e0;
            color: #e63946;
        }
        .btn-desactivar:hover { background: #e63946; color: white; }

        .btn-activar {
            background: #ECFDF5;
            color: #065F46;
        }
        .btn-activar:hover { background: #10B981; color: white; }

        /* ===== ALERTAS ===== */
        .alerta {
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: 0.88rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .alerta-success {
            background: #ECFDF5;
            color: #059669;
            border-left: 4px solid #10B981;
        }

        .alerta-error {
            background: #ffe0e0;
            color: #c0392b;
            border-left: 4px solid #e63946;
        }

        .alerta-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            padding: 0;
        }

        .alerta-close:hover { opacity: 1; }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }

        .modal-overlay.activo {
            display: flex;
            animation: fadeIn 0.2s ease;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #333;
        }

        .modal-close {
            background: #f0f0f0;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .modal-close:hover { background: #ddd; }

        /* ===== PROGRAMAS STATS ===== */
        .programa-stats-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .programa-stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 1rem;
            background: #F0FDF4;
            border-radius: 8px;
            font-size: 0.88rem;
        }

        .programa-stat-item .prog-name {
            font-weight: 600;
            color: #333;
            flex: 1;
            margin-right: 1rem;
        }

        .programa-stat-item .prog-count {
            background: #059669;
            color: white;
            padding: 0.2rem 0.7rem;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 700;
            min-width: 30px;
            text-align: center;
        }

        .barra-progreso {
            flex: 0.5;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin: 0 1rem;
            overflow: hidden;
        }

        .barra-progreso-fill {
            height: 100%;
            background: #10B981;
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        /* ===== SIN RESULTADOS ===== */
        .sin-resultados {
            text-align: center;
            padding: 2rem;
            color: #aaa;
            font-size: 0.9rem;
            display: none;
        }

        /* ===== DOCENTE CARD ===== */
        .docente-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .docente-card {
            background: #F0FDF4;
            border-radius: 10px;
            padding: 1.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: box-shadow 0.2s;
        }

        .docente-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .docente-info h4 {
            margin: 0 0 0.3rem 0;
            font-size: 0.95rem;
            color: #333;
        }

        .docente-info span {
            font-size: 0.8rem;
            color: #888;
        }

        .docente-actions {
            display: flex;
            gap: 0.3rem;
        }

        /* ===== RESPONSIVE ===== */
        
        /* Tablets */
        @media (max-width: 900px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-container {
                padding: 1rem;
            }
            
            .tabs-admin {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        /* Móviles grandes */
        @media (max-width: 600px) {
            .tabs-admin {
                gap: 0.2rem;
                padding: 0.3rem;
            }

            .tab-admin {
                padding: 0.5rem 0.6rem;
                font-size: 0.7rem;
                white-space: nowrap;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.6rem;
            }

            .campos-row {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .admin-card {
                padding: 1rem;
            }
            
            .admin-card h3 {
                font-size: 0.95rem;
            }
            
            .tabla-wrap {
                font-size: 0.8rem;
            }
            
            .tabla-wrap th,
            .tabla-wrap td {
                padding: 0.5rem;
            }
            
            .docente-actions {
                flex-direction: column;
                gap: 0.3rem;
            }
            
            .btn-sm {
                font-size: 0.7rem;
                padding: 0.3rem 0.5rem;
            }
            
            .badge-estado {
                font-size: 0.65rem;
            }
        }

        /* Móviles pequeños */
        @media (max-width: 400px) {
            .tab-admin {
                padding: 0.4rem 0.5rem;
                font-size: 0.65rem;
            }
            
            .stats-grid {
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 0.8rem;
            }
            
            .stat-card .valor {
                font-size: 1.2rem;
            }
            
            .tabla-wrap {
                font-size: 0.75rem;
            }
            
            .admin-card {
                padding: 0.8rem;
                border-radius: 10px;
            }
            
            .barra-herramientas {
                flex-direction: column;
            }

            .buscador-input,
            .filtro-select {
                width: 100%;
                min-width: unset;
            }
            
            .card {
                padding: 1rem;
            }

            .acciones-cell {
                flex-direction: column;
            }

            .modal-content {
                padding: 1.2rem;
            }

            .barra-progreso {
                display: none;
            }

            .docente-grid {
                grid-template-columns: 1fr;
            }

            .uso-stats-row {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .uso-bottom {
                grid-template-columns: 1fr !important;
            }
        }

        /* ===== MÓDULO USO DE PLATAFORMA (rediseñado) ===== */
        .uso-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 8px 30px rgba(5,150,105,0.10), 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid rgba(16,185,129,0.12);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .uso-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.4rem 1.6rem 1.2rem;
            background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(59,130,246,0.05));
            border-bottom: 1px solid rgba(16,185,129,0.10);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .uso-header h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: #022C22;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            padding: 0;
        }
        .uso-subtitle {
            margin: 4px 0 0;
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 500;
        }
        .uso-online-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 0.5rem 0.9rem;
            border-radius: 99px;
            font-size: 0.82rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(16,185,129,0.35);
        }
        .uso-online-badge .dot-pulse {
            width: 8px; height: 8px;
            background: #fff;
            border-radius: 50%;
            animation: pulso-uso 1.5s infinite;
        }
        .uso-online-badge .ts {
            font-size: 0.7rem;
            opacity: 0.85;
            font-weight: 500;
            margin-left: 4px;
        }
        @keyframes pulso-uso {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.6); }
            50% { box-shadow: 0 0 0 6px rgba(255,255,255,0); }
        }

        .uso-stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0;
            padding: 0;
            background: rgba(248,250,252,0.4);
            border-bottom: 1px solid rgba(16,185,129,0.08);
        }
        .uso-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1.1rem 1rem;
            border-right: 1px solid rgba(16,185,129,0.08);
            transition: background 0.2s;
        }
        .uso-stat:last-child { border-right: none; }
        .uso-stat:hover { background: rgba(255,255,255,0.7); }
        .uso-stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .uso-stat-num {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
            color: #0f172a;
        }
        .uso-stat-lbl {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 4px;
            font-weight: 600;
        }
        .uso-stat.hoy .uso-stat-icon { background: rgba(16,185,129,0.12); color: #059669; }
        .uso-stat.hoy .uso-stat-num { color: #059669; }
        .uso-stat.semana .uso-stat-icon { background: rgba(59,130,246,0.12); color: #3B82F6; }
        .uso-stat.semana .uso-stat-num { color: #3B82F6; }
        .uso-stat.mes .uso-stat-icon { background: rgba(245,158,11,0.12); color: #D97706; }
        .uso-stat.mes .uso-stat-num { color: #D97706; }
        .uso-stat.ingr .uso-stat-icon { background: rgba(100,116,139,0.12); color: #475569; }
        .uso-stat.ingr .uso-stat-num { color: #334155; font-size: 1.3rem; }
        .uso-stat.nunca .uso-stat-icon { background: rgba(230,57,70,0.12); color: #e63946; }
        .uso-stat.nunca .uso-stat-num { color: #e63946; }

        .uso-bottom {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 0;
        }
        .uso-col {
            padding: 1.3rem 1.5rem;
        }
        .uso-col + .uso-col {
            border-left: 1px solid rgba(16,185,129,0.08);
        }
        .uso-col h4 {
            margin: 0 0 0.9rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .online-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0.8rem;
            border-radius: 10px;
            margin-bottom: 6px;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 1px solid #bbf7d0;
        }
        .online-row .left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .online-row .dot {
            width: 8px; height: 8px;
            background: #10B981;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.18);
        }
        .online-row .nombre { font-weight: 700; font-size: 0.88rem; color: #022C22; }
        .online-row .user { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
        .online-row .hace { font-size: 0.78rem; color: #059669; font-weight: 700; white-space: nowrap; }
        .online-empty {
            text-align: center;
            color: #94a3b8;
            font-size: 0.82rem;
            padding: 1.2rem;
            font-style: italic;
        }

        .reciente-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.55rem 0;
            border-bottom: 1px dashed rgba(16,185,129,0.12);
            gap: 8px;
        }
        .reciente-row:last-child { border-bottom: none; }
        .reciente-row .nombre {
            font-weight: 600;
            font-size: 0.85rem;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .reciente-row .user {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-left: 4px;
        }
        .reciente-row .hace {
            font-size: 0.76rem;
            font-weight: 700;
            color: #059669;
            white-space: nowrap;
        }
        .reciente-row .hace.viejo { color: #94a3b8; }
        .uso-empty {
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
            padding: 1.5rem;
            font-style: italic;
        }

        /* ===== SERVIDOR (módulo independiente) ===== */
        .srv-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 8px 30px rgba(15,23,42,0.08), 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid rgba(15,23,42,0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .srv-card .srv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.4rem 1.6rem 1.2rem;
            background: linear-gradient(135deg, rgba(15,23,42,0.04), rgba(59,130,246,0.05));
            border-bottom: 1px solid rgba(15,23,42,0.06);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .srv-card .srv-header h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .srv-subtitle {
            margin: 4px 0 0;
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .srv-subtitle #srv-host {
            font-family: 'SF Mono', Menlo, Consolas, monospace;
            background: rgba(15,23,42,0.06);
            padding: 2px 10px;
            border-radius: 99px;
            color: #334155;
            font-weight: 600;
        }
        .srv-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #d1fae5;
            padding: 0.5rem 0.9rem;
            border-radius: 99px;
            font-size: 0.78rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(15,23,42,0.25);
        }
        .srv-pulse {
            width: 8px; height: 8px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16,185,129,0.7);
            animation: srv-pulse-anim 1.6s infinite;
        }
        @keyframes srv-pulse-anim {
            0%   { box-shadow: 0 0 0 0 rgba(16,185,129,0.7); }
            70%  { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
        .srv-dot {
            width: 4px; height: 4px;
            background: #cbd5e1;
            border-radius: 50%;
            display: inline-block;
        }
        .srv-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.9rem;
            padding: 1.3rem 1.5rem 1.5rem;
        }
        .srv-metric {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(15,23,42,0.06);
            border-radius: 12px;
            padding: 0.85rem 1rem;
            transition: all 0.25s;
        }
        .srv-metric:hover {
            background: rgba(255,255,255,0.95);
            box-shadow: 0 4px 14px rgba(15,23,42,0.06);
            transform: translateY(-1px);
        }
        .srv-metric-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px;
        }
        .srv-metric-head span {
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748b;
        }
        .srv-metric-head strong {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }
        .srv-bar {
            width: 100%;
            height: 6px;
            background: rgba(15,23,42,0.06);
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .srv-bar-fill {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg,#10b981,#059669);
            border-radius: 99px;
            transition: width 0.6s ease, background 0.4s;
        }
        .srv-metric-foot {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 500;
            font-variant-numeric: tabular-nums;
        }
        .srv-info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.78rem;
            padding: 3px 0;
            color: #475569;
        }
        .srv-info-row span:last-child {
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }
        .srv-metric.srv-ok .srv-metric-head strong { color: #10b981; }
        .srv-metric.srv-fail .srv-metric-head strong { color: #ef4444; }

        @media (max-width: 768px) {
            .srv-grid { grid-template-columns: repeat(2, 1fr); padding: 1rem; }
            .srv-card .srv-header { padding: 1.1rem 1rem; }
        }
        @media (max-width: 480px) {
            .srv-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">👥 Creación de Estudiantes</span>
    <div style="display:flex; gap:0.5rem; align-items:center;">
        <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
    </div>
</div>

<div class="dashboard-container">

    <a href="../dashboard.php" class="btn-volver">← Volver al inicio</a>

    <!-- ALERTA -->
    <?php if ($msg_parts): ?>
        <div class="alerta alerta-<?php echo $msg_parts[0]; ?>" id="alerta-msg">
            <span><?php echo htmlspecialchars($msg_parts[1]); ?></span>
            <button class="alerta-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="stats-grid">
        <div class="stat-card verde">
            <span class="stat-numero"><?php echo count($estudiantes_activos); ?></span>
            <span class="stat-label">Activos</span>
        </div>
        <div class="stat-card rojo">
            <span class="stat-numero"><?php echo count($estudiantes_inactivos); ?></span>
            <span class="stat-label">Inactivos</span>
        </div>
        <div class="stat-card azul">
            <span class="stat-numero"><?php echo count($docentes); ?></span>
            <span class="stat-label">Docentes</span>
        </div>
        <div class="stat-card amarillo">
            <span class="stat-numero"><?php echo count($programas); ?></span>
            <span class="stat-label">Programas</span>
        </div>
    </div>


    <!-- TABS -->
    <div class="tabs-admin">
        <button class="tab-admin activo" onclick="mostrarPanel('crear', this)">➕ Crear Estudiante</button>
        <button class="tab-admin" onclick="mostrarPanel('activos', this)">
            ✅ Activos <span class="contador"><?php echo count($estudiantes_activos); ?></span>
        </button>
        <button class="tab-admin" onclick="mostrarPanel('inactivos', this)">
            🗃️ Historial <span class="contador-rojo"><?php echo count($estudiantes_inactivos); ?></span>
        </button>
        <button class="tab-admin" onclick="mostrarPanel('docentes', this)">
            👨‍🏫 Docentes <span class="contador"><?php echo count($docentes); ?></span>
        </button>
    </div>

    <!-- ============================================ -->
    <!-- PANEL: CREAR ESTUDIANTE -->
    <!-- ============================================ -->
    <div class="panel-admin activo" id="panel-crear">
        <div class="admin-grid">
            <div class="card">
                <h3>➕ Crear Nuevo Estudiante</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="crear_estudiante">
                    <div class="campo-admin">
                        <label>Nombre completo</label>
                        <input type="text" name="nombre" placeholder="Ej: Carlos Andrés Rodríguez" required>
                    </div>
                    <div class="campos-row">
                        <div class="campo-admin">
                            <label>Número de documento</label>
                            <input type="text" name="documento" placeholder="Cédula o T.I." required>
                        </div>
                        <div class="campo-admin">
                            <label>Correo electrónico</label>
                            <input type="email" name="email" placeholder="correo@email.com">
                        </div>
                    </div>
                    <div class="campo-admin">
                        <label>Programa</label>
                        <select name="programa_id" required>
                            <option value="">Selecciona un programa</option>
                            <?php foreach ($programas as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campos-row">
                        <div class="campo-admin">
                            <label>Bimestre de ingreso</label>
                            <select name="fecha_ingreso" required>
                                <option value="">Selecciona bimestre</option>
                                <?php foreach ($bimestres as $i => $b): ?>
                                    <option value="<?php echo $b['fecha_inicio']; ?>" <?php echo $i === 0 ? 'selected' : ''; ?>>
                                        Bimestre <?php echo $b['numero']; ?> — <?php echo date('d/m/Y', strtotime($b['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($b['fecha_fin'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo-admin">
                            <label>Contraseña inicial</label>
                            <input type="password" name="password" placeholder="Contraseña temporal" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-crear">✅ Crear Estudiante</button>
                </form>
            </div>
            <div class="card">
                <h3>📋 Últimos Estudiantes Creados</h3>
                <?php 
                $ultimos = array_slice($estudiantes_activos, 0, 8);
                if (empty($ultimos)): ?>
                    <p style="color:#aaa; text-align:center; padding:2rem 0;">No hay estudiantes registrados aún.</p>
                <?php else: ?>
                    <?php foreach ($ultimos as $u): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; border-bottom:1px solid #f0f0f0;">
                        <div>
                            <span class="nombre-estudiante" style="font-size:0.88rem;"><?php echo htmlspecialchars($u['nombre']); ?></span>
                            <br><span class="doc-estudiante"><?php echo $u['documento']; ?></span>
                        </div>
                        <span class="programa-tag"><?php echo htmlspecialchars($u['programa']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- PANEL: ESTUDIANTES ACTIVOS -->
    <!-- ============================================ -->
    <div class="panel-admin" id="panel-activos">
        <div class="card">
            <h3>✅ Estudiantes Activos (<?php echo count($estudiantes_activos); ?>)</h3>

            <!-- Barra de búsqueda y filtros -->
            <div class="barra-herramientas">
                <input type="text" class="buscador-input" id="buscar-activos" 
                       placeholder="Buscar por nombre o documento..." 
                       onkeyup="filtrarTabla('activos')">
                <select class="filtro-select" id="filtro-programa-activos" onchange="filtrarTabla('activos')">
                    <option value="">Todos los programas</option>
                    <?php foreach ($programas as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['nombre']); ?>">
                            <?php echo htmlspecialchars($p['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (empty($estudiantes_activos)): ?>
                <p style="color:#aaa; text-align:center; padding:2rem;">No hay estudiantes activos.</p>
            <?php else: ?>
            <div class="tabla-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Programa</th>
                            <th>Ingreso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-activos">
                        <?php foreach ($estudiantes_activos as $est): ?>
                        <tr data-nombre="<?php echo strtolower($est['nombre']); ?>" 
                            data-doc="<?php echo $est['documento']; ?>"
                            data-programa="<?php echo htmlspecialchars($est['programa']); ?>">
                            <td>
                                <span class="nombre-estudiante"><?php echo htmlspecialchars($est['nombre']); ?></span>
                                <br><span class="doc-estudiante"><?php echo $est['documento']; ?></span>
                                <?php if ($est['email']): ?>
                                    <br><span style="font-size:0.75rem;color:#aaa;"><?php echo htmlspecialchars($est['email']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="programa-tag"><?php echo htmlspecialchars($est['programa']); ?></span></td>
                            <td style="white-space:nowrap;"><?php echo $est['fecha_ingreso']; ?></td>
                            <td><span class="badge-activo">Activo</span></td>
                            <td>
                                <div class="acciones-cell">
                                    <button class="btn-accion btn-editar" onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($est)); ?>)">✏️ Editar</button>
                                    <button class="btn-accion btn-key" onclick="abrirModalPassword(<?php echo $est['id']; ?>, '<?php echo htmlspecialchars($est['nombre']); ?>')">🔑</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desactivar a <?php echo htmlspecialchars(addslashes($est['nombre'])); ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="desactivar">
                                        <input type="hidden" name="estudiante_id" value="<?php echo $est['id']; ?>">
                                        <button type="submit" class="btn-accion btn-desactivar">🚫</button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ¿ELIMINAR permanentemente a <?php echo htmlspecialchars(addslashes($est['nombre'])); ?>? Se borrarán todas sus notas, asistencias y datos. Esta acción NO se puede deshacer.')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="eliminar_estudiante">
                                        <input type="hidden" name="estudiante_id" value="<?php echo $est['id']; ?>">
                                        <button type="submit" class="btn-accion" style="background:#fee2e2;color:#dc2626;" title="Eliminar permanentemente">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="sin-resultados" id="sin-resultados-activos">🔍 No se encontraron estudiantes con ese filtro.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- PANEL: HISTORIAL / INACTIVOS -->
    <!-- ============================================ -->
    <div class="panel-admin" id="panel-inactivos">
        <div class="card">
            <h3>🗃️ Historial — Cuentas Inactivas (<?php echo count($estudiantes_inactivos); ?>)</h3>

            <div class="barra-herramientas">
                <input type="text" class="buscador-input" id="buscar-inactivos" 
                       placeholder="Buscar por nombre o documento..." 
                       onkeyup="filtrarTabla('inactivos')">
            </div>

            <?php if (empty($estudiantes_inactivos)): ?>
                <p style="color:#aaa; text-align:center; padding:2rem;">No hay cuentas inactivas. ¡Bien!</p>
            <?php else: ?>
            <div class="tabla-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Programa</th>
                            <th>Ingreso</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-inactivos">
                        <?php foreach ($estudiantes_inactivos as $est): ?>
                        <tr data-nombre="<?php echo strtolower($est['nombre']); ?>" 
                            data-doc="<?php echo $est['documento']; ?>"
                            data-programa="<?php echo htmlspecialchars($est['programa']); ?>">
                            <td>
                                <span class="nombre-estudiante"><?php echo htmlspecialchars($est['nombre']); ?></span>
                                <br><span class="doc-estudiante"><?php echo $est['documento']; ?></span>
                            </td>
                            <td><span class="programa-tag"><?php echo htmlspecialchars($est['programa']); ?></span></td>
                            <td><?php echo $est['fecha_ingreso']; ?></td>
                            <td><span class="badge-inactivo">Inactivo</span></td>
                            <td>
                                <div class="acciones-cell">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Reactivar a <?php echo htmlspecialchars(addslashes($est['nombre'])); ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="activar">
                                        <input type="hidden" name="estudiante_id" value="<?php echo $est['id']; ?>">
                                        <button type="submit" class="btn-accion btn-activar">✅ Reactivar</button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ¿ELIMINAR permanentemente a <?php echo htmlspecialchars(addslashes($est['nombre'])); ?>? Se borrarán todas sus notas, asistencias y datos. Esta acción NO se puede deshacer.')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="eliminar_estudiante">
                                        <input type="hidden" name="estudiante_id" value="<?php echo $est['id']; ?>">
                                        <button type="submit" class="btn-accion" style="background:#fee2e2;color:#dc2626;" title="Eliminar permanentemente">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="sin-resultados" id="sin-resultados-inactivos">🔍 No se encontraron resultados.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- PANEL: DOCENTES -->
    <!-- ============================================ -->
    <div class="panel-admin" id="panel-docentes">
        <div class="admin-grid">
            <div class="card">
                <h3>👨‍🏫 Crear Nuevo Docente</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="crear_docente">
                    <div class="campo-admin">
                        <label>Nombre del docente</label>
                        <input type="text" name="nombre_docente" placeholder="Ej: Prof. María López" required>
                    </div>
                    <div class="campo-admin">
                        <label>Nombre de usuario</label>
                        <input type="text" name="username_docente" placeholder="Ej: docente2" required>
                    </div>
                    <div class="campo-admin">
                        <label>Contraseña</label>
                        <input type="password" name="password_docente" placeholder="Contraseña del docente" required>
                    </div>
                    <div class="campo-admin">
                        <label>Programa asignado</label>
                        <select name="programa_id_docente" required>
                            <option value="">Selecciona un programa</option>
                            <?php foreach ($programas as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-crear">👨‍🏫 Crear Docente</button>
                </form>
            </div>
            <div class="card">
                <h3>📋 Docentes Registrados (<?php echo count($docentes); ?>)</h3>
                <?php if (empty($docentes)): ?>
                    <p style="color:#aaa; text-align:center; padding:2rem;">No hay docentes registrados.</p>
                <?php else: ?>
                    <div class="docente-grid" style="grid-template-columns:1fr;">
                        <?php foreach ($docentes as $doc): ?>
                        <div class="docente-card">
                            <div class="docente-info">
                                <h4>👨‍🏫 <?php echo htmlspecialchars($doc['username']); ?></h4>
                                <span>
                                    <?php if ($doc['estado'] === 'activo'): ?>
                                        <span class="badge-activo">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-inactivo">Inactivo</span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($doc['programa_nombre'])): ?>
                                    <small style="display:block;color:#666;margin-top:0.2rem;font-size:0.78rem;">📚 <?php echo htmlspecialchars($doc['programa_nombre']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="docente-actions">
                                <button class="btn-accion btn-key" onclick="abrirModalPasswordDocente(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['username']); ?>')" title="Resetear contraseña">🔑</button>
                                <?php if ($doc['estado'] === 'activo'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desactivar docente?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="desactivar_docente">
                                        <input type="hidden" name="docente_id" value="<?php echo $doc['id']; ?>">
                                        <button type="submit" class="btn-accion btn-desactivar">🚫</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="accion" value="activar_docente">
                                        <input type="hidden" name="docente_id" value="<?php echo $doc['id']; ?>">
                                        <button type="submit" class="btn-accion btn-activar">✅</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ¿ELIMINAR permanentemente a <?php echo htmlspecialchars($doc['username']); ?>? Esta acción NO se puede deshacer.')">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="accion" value="eliminar_docente">
                                    <input type="hidden" name="docente_id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" class="btn-accion" style="background:#fee2e2;color:#dc2626;" title="Eliminar permanentemente">🗑</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


</div>

<!-- ============================================ -->
<!-- MODAL: EDITAR ESTUDIANTE -->
<!-- ============================================ -->
<div class="modal-overlay" id="modal-editar">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✏️ Editar Estudiante</h3>
            <button class="modal-close" onclick="cerrarModal('modal-editar')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="accion" value="editar_estudiante">
            <input type="hidden" name="estudiante_id" id="edit-id">
            <div class="campo-admin">
                <label>Nombre completo</label>
                <input type="text" name="nombre" id="edit-nombre" required>
            </div>
            <div class="campos-row">
                <div class="campo-admin">
                    <label>Documento</label>
                    <input type="text" name="documento" id="edit-documento" required>
                </div>
                <div class="campo-admin">
                    <label>Email</label>
                    <input type="email" name="email" id="edit-email">
                </div>
            </div>
            <div class="campo-admin">
                <label>Programa</label>
                <select name="programa_id" id="edit-programa" required>
                    <?php foreach ($programas as $p): ?>
                        <option value="<?php echo $p['id']; ?>">
                            <?php echo htmlspecialchars($p['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-crear">💾 Guardar Cambios</button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: RESETEAR CONTRASEÑA ESTUDIANTE -->
<!-- ============================================ -->
<div class="modal-overlay" id="modal-password">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔑 Resetear Contraseña</h3>
            <button class="modal-close" onclick="cerrarModal('modal-password')">&times;</button>
        </div>
        <p style="color:#666; font-size:0.9rem; margin-bottom:1rem;">
            Estudiante: <strong id="pw-nombre"></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="accion" value="reset_password">
            <input type="hidden" name="estudiante_id" id="pw-id">
            <div class="campo-admin">
                <label>Nueva contraseña</label>
                <input type="password" name="nueva_password" placeholder="Nueva contraseña" required minlength="8">
            </div>
            <button type="submit" class="btn-crear">🔑 Cambiar Contraseña</button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: RESETEAR CONTRASEÑA DOCENTE -->
<!-- ============================================ -->
<div class="modal-overlay" id="modal-password-docente">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔑 Resetear Contraseña Docente</h3>
            <button class="modal-close" onclick="cerrarModal('modal-password-docente')">&times;</button>
        </div>
        <p style="color:#666; font-size:0.9rem; margin-bottom:1rem;">
            Docente: <strong id="pw-doc-nombre"></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="accion" value="reset_password_docente">
            <input type="hidden" name="docente_id" id="pw-doc-id">
            <div class="campo-admin">
                <label>Nueva contraseña</label>
                <input type="password" name="nueva_password" placeholder="Nueva contraseña" required minlength="8">
            </div>
            <button type="submit" class="btn-crear">🔑 Cambiar Contraseña</button>
        </form>
    </div>
</div>

<script>
// ===== TABS =====
function mostrarPanel(panel, btn) {
    document.querySelectorAll('.panel-admin').forEach(p => p.classList.remove('activo'));
    document.querySelectorAll('.tab-admin').forEach(b => b.classList.remove('activo'));
    document.getElementById('panel-' + panel).classList.add('activo');
    btn.classList.add('activo');
}

// ===== BÚSQUEDA Y FILTROS =====
function filtrarTabla(tipo) {
    const buscar = document.getElementById('buscar-' + tipo).value.toLowerCase();
    const filtroPrograma = document.getElementById('filtro-programa-' + tipo);
    const programa = filtroPrograma ? filtroPrograma.value : '';
    const tbody = document.getElementById('tbody-' + tipo);
    const filas = tbody.querySelectorAll('tr');
    const sinResultados = document.getElementById('sin-resultados-' + tipo);
    let visibles = 0;

    filas.forEach(fila => {
        const nombre = fila.dataset.nombre || '';
        const doc = fila.dataset.doc || '';
        const prog = fila.dataset.programa || '';

        const coincideBusqueda = nombre.includes(buscar) || doc.includes(buscar);
        const coincidePrograma = !programa || prog === programa;

        if (coincideBusqueda && coincidePrograma) {
            fila.classList.remove('fila-oculta');
            visibles++;
        } else {
            fila.classList.add('fila-oculta');
        }
    });

    if (sinResultados) {
        sinResultados.style.display = visibles === 0 ? 'block' : 'none';
    }
}

// ===== MODALES =====
function abrirModalEditar(est) {
    document.getElementById('edit-id').value = est.id;
    document.getElementById('edit-nombre').value = est.nombre;
    document.getElementById('edit-documento').value = est.documento;
    document.getElementById('edit-email').value = est.email || '';
    document.getElementById('edit-programa').value = est.programa_id;
    document.getElementById('modal-editar').classList.add('activo');
}

function abrirModalPassword(id, nombre) {
    document.getElementById('pw-id').value = id;
    document.getElementById('pw-nombre').textContent = nombre;
    document.getElementById('modal-password').classList.add('activo');
}

function abrirModalPasswordDocente(id, nombre) {
    document.getElementById('pw-doc-id').value = id;
    document.getElementById('pw-doc-nombre').textContent = nombre;
    document.getElementById('modal-password-docente').classList.add('activo');
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('activo');
}

// Cerrar modal al hacer clic fuera
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('activo');
        }
    });
});

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.activo').forEach(m => m.classList.remove('activo'));
    }
});

// Auto-ocultar alerta después de 5 segundos
const alerta = document.getElementById('alerta-msg');
if (alerta) {
    setTimeout(() => {
        alerta.style.opacity = '0';
        alerta.style.transition = 'opacity 0.5s';
        setTimeout(() => alerta.style.display = 'none', 500);
    }, 5000);
}
</script>

<script src="/intep/sesion.js"></script>
<?php include __DIR__ . '/../partials/asistente_admin.php'; ?>
</body>
</html>