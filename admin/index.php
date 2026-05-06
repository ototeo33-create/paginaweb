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

    // --- CAMBIAR CONTRASEÑA ADMIN ---
    if ($accion === 'cambiar_password_admin') {
        $password_actual = trim($_POST['password_actual'] ?? '');
        $nueva_password = trim($_POST['nueva_password'] ?? '');
        $confirmar_password = trim($_POST['confirmar_password'] ?? '');

        // Verificar contraseña actual
        $admin_id = $_SESSION['usuario_id'];
        $q_admin = mysqli_prepare($conexion, "SELECT password_hash FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($q_admin, 'i', $admin_id);
        mysqli_stmt_execute($q_admin);
        $admin_data = mysqli_fetch_assoc(mysqli_stmt_get_result($q_admin));

        if (!$admin_data || !password_verify($password_actual, $admin_data['password_hash'])) {
            $mensaje = 'error|La contraseña actual es incorrecta.';
        } elseif ($nueva_password !== $confirmar_password) {
            $mensaje = 'error|Las contraseñas nuevas no coinciden.';
        } else {
            $validacion = validarPassword($nueva_password);
            if ($validacion !== true) {
                $mensaje = 'error|' . $validacion;
            } else {
                $nuevo_hash = hashPassword($nueva_password);
                $upd = mysqli_prepare($conexion, "UPDATE usuarios SET password_hash = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'si', $nuevo_hash, $admin_id);
                if (mysqli_stmt_execute($upd)) {
                    $mensaje = 'success|🔑 Tu contraseña de administrador ha sido actualizada.';
                } else {
                    $mensaje = 'error|Error al cambiar la contraseña.';
                }
            }
        }
    }
    } // fin verificación CSRF
}

// ============================================
// OBTENER DATOS DE MONITOREO
// ============================================

// Estadísticas de uso de plataforma
$uso = [];
$r = mysqli_query($conexion, "SELECT COUNT(*) as n FROM usuarios WHERE rol='estudiante' AND ultimo_login IS NOT NULL AND DATE(ultimo_login) = CURDATE()");
$uso['hoy'] = mysqli_fetch_assoc($r)['n'] ?? 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) as n FROM usuarios WHERE rol='estudiante' AND ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$uso['semana'] = mysqli_fetch_assoc($r)['n'] ?? 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) as n FROM usuarios WHERE rol='estudiante' AND ultimo_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$uso['mes'] = mysqli_fetch_assoc($r)['n'] ?? 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) as n FROM usuarios WHERE rol='estudiante' AND ultimo_login IS NOT NULL");
$uso['alguna_vez'] = mysqli_fetch_assoc($r)['n'] ?? 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) as n FROM usuarios WHERE rol='estudiante' AND ultimo_login IS NULL AND estado='activo'");
$uso['nunca'] = mysqli_fetch_assoc($r)['n'] ?? 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) as n FROM estudiantes");
$uso['total_estudiantes'] = mysqli_fetch_assoc($r)['n'] ?? 0;
$r = mysqli_query($conexion, "SELECT u.username, COALESCE(e.nombre, u.username) as nombre, u.ultimo_login
                               FROM usuarios u LEFT JOIN estudiantes e ON u.estudiante_id = e.id
                               WHERE u.rol='estudiante' AND u.ultimo_login IS NOT NULL
                               ORDER BY u.ultimo_login DESC LIMIT 10");
$uso['recientes'] = [];
while ($row = mysqli_fetch_assoc($r)) $uso['recientes'][] = $row;

$msg_parts = $mensaje ? explode('|', $mensaje) : null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin – INTEP</title>
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
    <span class="usuario-info">⚙️ Panel Administrador</span>
    <div style="display:flex; gap:0.5rem; align-items:center;">
        <button onclick="document.getElementById('modal-password-admin').classList.add('activo')" class="btn-salir" style="cursor:pointer; background:rgba(16,185,129,0.1); border-color:#10B981; color:#10B981;">🔐 Mi Contraseña</button>
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

    <!-- ============================================ -->
    <!-- USO DE PLATAFORMA (MÓDULO DEL DASHBOARD) -->
    <!-- ============================================ -->
    <div class="uso-card">
        <div class="uso-header">
            <div>
                <h3>📊 Uso de la plataforma</h3>
                <p class="uso-subtitle">Actividad de estudiantes en tiempo real</p>
            </div>
            <div class="uso-online-badge" title="Estudiantes activos en este momento">
                <span class="dot-pulse"></span>
                <span><strong id="online-count">0</strong> en línea</span>
                <span class="ts" id="online-ts"></span>
            </div>
        </div>

        <div class="uso-stats-row">
            <div class="uso-stat hoy">
                <div class="uso-stat-icon">📅</div>
                <div>
                    <div class="uso-stat-num"><?php echo $uso['hoy']; ?></div>
                    <div class="uso-stat-lbl">Hoy</div>
                </div>
            </div>
            <div class="uso-stat semana">
                <div class="uso-stat-icon">📈</div>
                <div>
                    <div class="uso-stat-num"><?php echo $uso['semana']; ?></div>
                    <div class="uso-stat-lbl">Últimos 7 días</div>
                </div>
            </div>
            <div class="uso-stat mes">
                <div class="uso-stat-icon">🗓️</div>
                <div>
                    <div class="uso-stat-num"><?php echo $uso['mes']; ?></div>
                    <div class="uso-stat-lbl">Últimos 30 días</div>
                </div>
            </div>
            <div class="uso-stat ingr">
                <div class="uso-stat-icon">✅</div>
                <div>
                    <div class="uso-stat-num"><?php echo $uso['alguna_vez']; ?>/<?php echo $uso['total_estudiantes']; ?></div>
                    <div class="uso-stat-lbl">Han ingresado</div>
                </div>
            </div>
            <div class="uso-stat nunca">
                <div class="uso-stat-icon">⛔</div>
                <div>
                    <div class="uso-stat-num"><?php echo $uso['nunca']; ?></div>
                    <div class="uso-stat-lbl">Nunca han entrado</div>
                </div>
            </div>
        </div>

        <div class="uso-bottom">
            <div class="uso-col">
                <h4>🟢 En línea ahora</h4>
                <div id="online-lista">
                    <div class="online-empty">Cargando...</div>
                </div>
            </div>
            <div class="uso-col">
                <h4>🕐 Últimos accesos</h4>
                <?php if (!empty($uso['recientes'])): ?>
                    <?php foreach ($uso['recientes'] as $ur): ?>
                        <?php
                        $diff = time() - strtotime($ur['ultimo_login']);
                        if ($diff < 3600) { $hace = round($diff/60) . ' min'; $clase = ''; }
                        elseif ($diff < 86400) { $hace = round($diff/3600) . ' h'; $clase = ''; }
                        elseif ($diff < 86400*7) { $hace = round($diff/86400) . ' d'; $clase = ''; }
                        else { $hace = date('d/m/Y', strtotime($ur['ultimo_login'])); $clase = 'viejo'; }
                        ?>
                        <div class="reciente-row">
                            <div style="min-width:0;flex:1;">
                                <span class="nombre"><?php echo htmlspecialchars($ur['nombre']); ?></span>
                                <span class="user"><?php echo htmlspecialchars($ur['username']); ?></span>
                            </div>
                            <span class="hace <?php echo $clase; ?>"><?php echo $hace; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="uso-empty">Aún no hay registros de acceso.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ESTADÍSTICAS DEL SERVIDOR (módulo aparte) -->
    <!-- ============================================ -->
    <div class="srv-card">
        <div class="srv-header">
            <div>
                <h3>📊 Estadísticas del servidor</h3>
                <p class="srv-subtitle">
                    <span id="srv-host">—</span>
                    <span class="srv-dot"></span>
                    <span id="srv-uptime">uptime —</span>
                    <span class="srv-dot"></span>
                    <span id="srv-ts">—</span>
                </p>
            </div>
            <div class="srv-status-badge" id="srv-status-badge">
                <span class="srv-pulse"></span>
                <span>Monitoreo activo</span>
            </div>
        </div>

        <div class="srv-grid">
            <div class="srv-metric" id="srv-cpu-card">
                <div class="srv-metric-head">
                    <span>⚙️ CPU</span>
                    <strong id="srv-cpu-val">—</strong>
                </div>
                <div class="srv-bar"><div class="srv-bar-fill" id="srv-cpu-bar"></div></div>
                <div class="srv-metric-foot" id="srv-cpu-foot">— cores</div>
            </div>

            <div class="srv-metric" id="srv-ram-card">
                <div class="srv-metric-head">
                    <span>🧠 RAM</span>
                    <strong id="srv-ram-val">—</strong>
                </div>
                <div class="srv-bar"><div class="srv-bar-fill" id="srv-ram-bar"></div></div>
                <div class="srv-metric-foot" id="srv-ram-foot">— / —</div>
            </div>

            <div class="srv-metric" id="srv-disk-card">
                <div class="srv-metric-head">
                    <span>💾 Disco</span>
                    <strong id="srv-disk-val">—</strong>
                </div>
                <div class="srv-bar"><div class="srv-bar-fill" id="srv-disk-bar"></div></div>
                <div class="srv-metric-foot" id="srv-disk-foot">— / —</div>
            </div>

            <div class="srv-metric" id="srv-temp-card">
                <div class="srv-metric-head">
                    <span>🌡️ Temperatura</span>
                    <strong id="srv-temp-val">—</strong>
                </div>
                <div class="srv-bar"><div class="srv-bar-fill" id="srv-temp-bar"></div></div>
                <div class="srv-metric-foot" id="srv-temp-foot">—</div>
            </div>

            <div class="srv-metric" id="srv-load-card">
                <div class="srv-metric-head">
                    <span>📈 Carga</span>
                    <strong id="srv-load-val">—</strong>
                </div>
                <div class="srv-bar"><div class="srv-bar-fill" id="srv-load-bar"></div></div>
                <div class="srv-metric-foot" id="srv-load-foot">1m · 5m · 15m</div>
            </div>

            <div class="srv-metric" id="srv-db-card">
                <div class="srv-metric-head">
                    <span>🗄️ MariaDB</span>
                    <strong id="srv-db-val">—</strong>
                </div>
                <div class="srv-info-row"><span>Conexiones</span><span id="srv-db-conn">—</span></div>
                <div class="srv-info-row"><span>Queries</span><span id="srv-db-q">—</span></div>
            </div>
        </div>
    </div>

    <script>
    function srvBarColor(p){
        if (p >= 85) return 'linear-gradient(90deg,#ef4444,#dc2626)';
        if (p >= 65) return 'linear-gradient(90deg,#f59e0b,#d97706)';
        return 'linear-gradient(90deg,#10b981,#059669)';
    }
    function srvTempColor(t){
        if (t >= 75) return 'linear-gradient(90deg,#ef4444,#dc2626)';
        if (t >= 60) return 'linear-gradient(90deg,#f59e0b,#d97706)';
        if (t >= 45) return 'linear-gradient(90deg,#3b82f6,#2563eb)';
        return 'linear-gradient(90deg,#10b981,#059669)';
    }
    function setBar(id, percent, color){
        const b = document.getElementById(id);
        if (!b) return;
        b.style.width = Math.min(100, Math.max(0, percent)) + '%';
        if (color) b.style.background = color;
    }
    function cargarServidor(){
        fetch('api_server_status.php').then(r=>r.json()).then(d=>{
            document.getElementById('srv-host').textContent = d.host || '';
            document.getElementById('srv-ts').textContent = 'Act. ' + d.ts;
            document.getElementById('srv-uptime').textContent = d.uptime ? ('uptime ' + d.uptime.human) : 'uptime —';

            // CPU
            if (d.cpu !== null && d.cpu !== undefined) {
                document.getElementById('srv-cpu-val').textContent = d.cpu + '%';
                setBar('srv-cpu-bar', d.cpu, srvBarColor(d.cpu));
                document.getElementById('srv-cpu-foot').textContent = (d.cores || '?') + ' núcleos';
            } else {
                document.getElementById('srv-cpu-val').textContent = 'N/D';
            }

            // RAM
            if (d.mem) {
                document.getElementById('srv-ram-val').textContent = d.mem.percent + '%';
                setBar('srv-ram-bar', d.mem.percent, srvBarColor(d.mem.percent));
                document.getElementById('srv-ram-foot').textContent = d.mem.used_h + ' / ' + d.mem.total_h;
            } else {
                document.getElementById('srv-ram-val').textContent = 'N/D';
            }

            // Disco
            if (d.disk) {
                document.getElementById('srv-disk-val').textContent = d.disk.percent + '%';
                setBar('srv-disk-bar', d.disk.percent, srvBarColor(d.disk.percent));
                document.getElementById('srv-disk-foot').textContent = d.disk.used_h + ' / ' + d.disk.total_h;
            } else {
                document.getElementById('srv-disk-val').textContent = 'N/D';
            }

            // Temp
            if (d.temp && d.temp.main) {
                const t = d.temp.main;
                document.getElementById('srv-temp-val').textContent = t.toFixed(1) + '°C';
                setBar('srv-temp-bar', Math.min(100, t / 90 * 100), srvTempColor(t));
                const zonas = (d.temp.zones || []).slice(0,3).map(z => `${z.type}: ${z.value}°`).join(' · ');
                document.getElementById('srv-temp-foot').textContent = zonas || 'sensores —';
            } else {
                document.getElementById('srv-temp-val').textContent = 'N/D';
                document.getElementById('srv-temp-foot').textContent = 'Sin sensor disponible';
            }

            // Carga
            if (d.load) {
                document.getElementById('srv-load-val').textContent = d.load['1m'].toFixed(2);
                const cores = d.cores || 1;
                const pct = Math.min(100, d.load['1m'] / cores * 100);
                setBar('srv-load-bar', pct, srvBarColor(pct));
                document.getElementById('srv-load-foot').textContent =
                    `${d.load['1m'].toFixed(2)} · ${d.load['5m'].toFixed(2)} · ${d.load['15m'].toFixed(2)}`;
            } else {
                document.getElementById('srv-load-val').textContent = 'N/D';
            }

            // DB
            if (d.db) {
                document.getElementById('srv-db-val').textContent = '✓';
                document.getElementById('srv-db-card').classList.add('srv-ok');
                document.getElementById('srv-db-conn').textContent = d.db.Threads_connected || '—';
                document.getElementById('srv-db-q').textContent = d.db.Queries
                    ? Number(d.db.Queries).toLocaleString() : '—';
            } else {
                document.getElementById('srv-db-val').textContent = '✗';
                document.getElementById('srv-db-card').classList.add('srv-fail');
            }
        }).catch(()=>{
            document.getElementById('srv-ts').textContent = 'error';
        });
    }
    cargarServidor();
    setInterval(cargarServidor, 15000);

    function cargarOnlineDash(){
        fetch('api_online.php').then(r=>r.json()).then(d=>{
            document.getElementById('online-count').textContent = d.total;
            document.getElementById('online-ts').textContent = '· ' + d.ts;
            const lista = document.getElementById('online-lista');
            if (!d.usuarios || d.usuarios.length === 0) {
                lista.innerHTML = '<p class="online-empty">Ningún estudiante conectado en este momento.</p>';
            } else {
                lista.innerHTML = d.usuarios.map(u => `
                    <div class="online-row">
                        <div class="left">
                            <span class="dot"></span>
                            <div style="min-width:0;">
                                <div class="nombre">${u.nombre}</div>
                                <div class="user">${u.username}</div>
                            </div>
                        </div>
                        <span class="hace">${u.hace}</span>
                    </div>`).join('');
            }
        }).catch(()=>{
            document.getElementById('online-lista').innerHTML = '<p class="online-empty">No se pudo cargar.</p>';
        });
    }
    cargarOnlineDash();
    setInterval(cargarOnlineDash, 30000);
    </script>

</div>

<!-- ============================================ -->
<!-- MODAL: CAMBIAR CONTRASEÑA ADMIN -->
<!-- ============================================ -->
<div class="modal-overlay" id="modal-password-admin">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔐 Cambiar Mi Contraseña</h3>
            <button class="modal-close" onclick="cerrarModal('modal-password-admin')">&times;</button>
        </div>
        <p style="color:#666; font-size:0.9rem; margin-bottom:1rem;">
            Cambia tu contraseña de administrador. Necesitas ingresar tu contraseña actual.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="accion" value="cambiar_password_admin">
            <div class="campo-admin">
                <label>Contraseña actual</label>
                <input type="password" name="password_actual" placeholder="Tu contraseña actual" required>
            </div>
            <div class="campo-admin">
                <label>Nueva contraseña</label>
                <input type="password" name="nueva_password" placeholder="Nueva contraseña" required minlength="8">
            </div>
            <div class="campo-admin">
                <label>Confirmar nueva contraseña</label>
                <input type="password" name="confirmar_password" placeholder="Repite la nueva contraseña" required minlength="8">
            </div>
            <button type="submit" class="btn-crear">🔐 Cambiar Contraseña</button>
        </form>
    </div>
</div>

<script>
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
</body>
</html>