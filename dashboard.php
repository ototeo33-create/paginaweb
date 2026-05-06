<?php
require_once 'config.php';
require_once __DIR__ . '/partials/icons.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$nombre = $_SESSION['usuario_nombre'];
$rol    = $_SESSION['usuario_rol'];

// ============================================
// DATOS SEGÚN ROL
// ============================================

if ($rol === 'estudiante') {
    $est_id = $_SESSION['estudiante_id'];

    // Auto-crear columna foto si no existe
    $col_check = mysqli_query($conexion, "SHOW COLUMNS FROM estudiantes LIKE 'foto'");
    if (mysqli_num_rows($col_check) === 0) {
        mysqli_query($conexion, "ALTER TABLE estudiantes ADD COLUMN foto VARCHAR(255) DEFAULT NULL AFTER email");
    }

    $q = "SELECT e.nombre, e.documento, e.foto, p.nombre as programa
          FROM estudiantes e
          JOIN programas p ON e.programa_id = p.id
          WHERE e.id = ?";
    $stmt = mysqli_prepare($conexion, $q);
    mysqli_stmt_bind_param($stmt, 'i', $est_id);
    mysqli_stmt_execute($stmt);
    $info_est = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $q2 = "SELECT COUNT(*) as total, 
                  SUM(CASE WHEN n.aprobado = 1 THEN 1 ELSE 0 END) as aprobados,
                  ROUND(AVG(CASE WHEN n.nota_final > 0 THEN n.nota_final END), 1) as promedio
           FROM notas n WHERE n.estudiante_id = ?";
    $stmt2 = mysqli_prepare($conexion, $q2);
    mysqli_stmt_bind_param($stmt2, 'i', $est_id);
    mysqli_stmt_execute($stmt2);
    $stats_est = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));

    $q3 = "SELECT COUNT(*) as clases FROM horarios WHERE estudiante_id = ?";
    $stmt3 = mysqli_prepare($conexion, $q3);
    mysqli_stmt_bind_param($stmt3, 'i', $est_id);
    mysqli_stmt_execute($stmt3);
    $horarios_est = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3));

    // Detectar estudiante de Primera Infancia para mostrar INTEP Kids
    $programa_nombre_est  = $info_est['programa'] ?? '';
    $es_primera_infancia  = stripos($programa_nombre_est, 'primera infancia') !== false
                         || stripos($programa_nombre_est, 'preescolar') !== false;

    // Detectar si es estudiante de un programa de inglés (por nombre del programa)
    $tiene_ingles = stripos($programa_nombre_est, 'inglés') !== false
                 || stripos($programa_nombre_est, 'ingles') !== false;

    // Detectar si es estudiante de Almacenamiento
    $tiene_almacenamiento = stripos($programa_nombre_est, 'almacen') !== false
                         || stripos($programa_nombre_est, 'recibo') !== false
                         || stripos($programa_nombre_est, 'despacho') !== false
                         || stripos($programa_nombre_est, 'bodega') !== false;
}

if ($rol === 'docente') {
    $doc_id = $_SESSION['usuario_id'];

    $q = "SELECT COUNT(*) as modulos FROM programa_modulo WHERE docente_id = ?";
    $stmt = mysqli_prepare($conexion, $q);
    mysqli_stmt_bind_param($stmt, 'i', $doc_id);
    mysqli_stmt_execute($stmt);
    $stats_doc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $q2 = "SELECT COUNT(DISTINCT n.estudiante_id) as estudiantes
           FROM notas n
           JOIN programa_modulo pm ON n.programa_modulo_id = pm.id
           WHERE pm.docente_id = ?";
    $stmt2 = mysqli_prepare($conexion, $q2);
    mysqli_stmt_bind_param($stmt2, 'i', $doc_id);
    mysqli_stmt_execute($stmt2);
    $est_doc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
}

if ($rol === 'admin') {
    $stats_admin = [];

    $r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM estudiantes WHERE estado = 'activo'");
    $stats_admin['estudiantes'] = mysqli_fetch_assoc($r)['total'];

    $r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'docente' AND estado = 'activo'");
    $stats_admin['docentes'] = mysqli_fetch_assoc($r)['total'];

    $r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM programas");
    $stats_admin['programas'] = mysqli_fetch_assoc($r)['total'];

    $r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM programa_modulo");
    $stats_admin['modulos'] = mysqli_fetch_assoc($r)['total'];
}

// Estado de evaluacion docente
$res_eval = mysqli_query($conexion, "SELECT periodo, activa FROM eval_control ORDER BY id DESC LIMIT 1");
$eval_ctrl = $res_eval ? mysqli_fetch_assoc($res_eval) : null;
$eval_activa = ($eval_ctrl && $eval_ctrl['activa'] == 1);
$eval_periodo = $eval_ctrl['periodo'] ?? '';

// Si es estudiante: verificar cuántos docentes le faltan por evaluar
$eval_pendientes = 0;
if ($rol === 'estudiante' && $eval_activa) {
    $prog_id = null;
    $rp = mysqli_prepare($conexion, "SELECT COALESCE(u.programa_id, e.programa_id) AS programa_id FROM usuarios u LEFT JOIN estudiantes e ON u.estudiante_id = e.id WHERE u.id = ?");
    mysqli_stmt_bind_param($rp, 'i', $usuario_id);
    mysqli_stmt_execute($rp);
    $prog_id = mysqli_stmt_get_result($rp)->fetch_assoc()['programa_id'] ?? null;
    if ($prog_id) {
        $stmt_pend = mysqli_prepare($conexion,
            "SELECT COUNT(*) as total FROM programa_modulo pm
             JOIN usuarios u ON pm.docente_id = u.id
             WHERE pm.programa_id = ? AND pm.estado = 'activo' AND u.rol = 'docente'
               AND pm.docente_id NOT IN (
                   SELECT docente_id FROM eval_docente WHERE estudiante_id = ? AND periodo = ?
               )");
        mysqli_stmt_bind_param($stmt_pend, 'iis', $prog_id, $usuario_id, $eval_periodo);
        mysqli_stmt_execute($stmt_pend);
        $eval_pendientes = (int)mysqli_stmt_get_result($stmt_pend)->fetch_assoc()['total'];
    }
}

// Zona horaria Colombia
date_default_timezone_set('America/Bogota');

$hora = (int)date('H');
if ($hora < 12)     { $saludo = 'Buenos días';   $saludo_icon = '☀️'; }
elseif ($hora < 18) { $saludo = 'Buenas tardes'; $saludo_icon = '🌤️'; }
else                { $saludo = 'Buenas noches'; $saludo_icon = '🌙'; }

$dias    = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses   = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fecha_hoy = $dias[(int)date('w')] . ', ' . date('j') . ' de ' . $meses[(int)date('n')] . ' de ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>

        /* ═══════════════════════════════════════════
           FONDO GLOBAL — blanco limpio con animaciones
        ═══════════════════════════════════════════ */
        html, body {
            min-height: 100%;
            background: #f8f9fc;
            background-attachment: fixed;
        }

        /* Formas decorativas animadas */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 600px 400px at 5% 10%,  rgba(16,185,129,0.06) 0%, transparent 50%),
                radial-gradient(ellipse 400px 300px at 95% 20%, rgba(217,70,168,0.04) 0%, transparent 50%),
                radial-gradient(ellipse 300px 250px at 85% 85%, rgba(59,130,246,0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
            animation: fondo-mover 20s ease-in-out infinite;
        }
        @keyframes fondo-mover {
            0%, 100% { transform:translate(0,0); }
            50% { transform:translate(-10px, 5px); }
        }

        /* Círculos flotantes */
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
            0%, 100% { transform:translateY(0) rotate(0deg); }
            50% { transform:translateY(-20px) rotate(10deg); }
        }

        /* ═══════════════════════════════════════════
           CONTENEDOR
        ═══════════════════════════════════════════ */
        .dashboard-container {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem 3rem;
        }

        /* ═══════════════════════════════════════════
           HERO BIENVENIDA — gradiente suave
        ═══════════════════════════════════════════ */
        .bienvenida-hero {
            background: linear-gradient(135deg,
                #059669 0%,
                #10B981 50%,
                #34D399 100%);
            border-radius: 20px;
            padding: 2.2rem 2.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(5,150,105,0.25);
        }

        .bienvenida-hero::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -5%;
            width: 250px;
            height: 250px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            animation: hero-orb 8s ease-in-out infinite;
        }
        @keyframes hero-orb {
            0%, 100% { transform:scale(1); opacity:0.5; }
            50% { transform:scale(1.2); opacity:0.8; }
        }

        .bienvenida-hero::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: 5%;
            width: 150px;
            height: 150px;
            background: rgba(217,70,168,0.2);
            border-radius: 50%;
            animation: hero-orb 10s ease-in-out infinite reverse;
        }

        .bienvenida-hero .saludo {
            font-size: 0.87rem;
            opacity: 0.85;
            margin-bottom: 0.3rem;
            letter-spacing: 0.3px;
        }

        .bienvenida-hero h2 {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
        }

        .bienvenida-hero .fecha {
            font-size: 0.82rem;
            opacity: 0.75;
        }

        .bienvenida-hero .rol-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.3rem 1rem;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.8rem;
        }

        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            flex: 1;
            min-width: 0;
        }

        .hero-foto {
            flex-shrink: 0;
            margin-left: 2rem;
            margin-right: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }

        .hero-foto:hover {
            transform: scale(1.05);
        }

        .hero-foto img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.5);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            display: block;
        }

        .hero-foto-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            border: 3px dashed rgba(255,255,255,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        /* ═══════════════════════════════════════════
           QUICK STATS — cards con hover animacion
        ═══════════════════════════════════════════ */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-stat {
            background: white;
            border-radius: 16px;
            padding: 1.4rem 1rem;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            border: 1px solid rgba(16,185,129,0.08);
        }

        .quick-stat:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(5,150,105,0.12);
        }

        .quick-stat.verde    { border-bottom-color: #10B981; }
        .quick-stat.azul     { border-bottom-color: #3B82F6; }
        .quick-stat.amarillo { border-bottom-color: #F59E0B; }
        .quick-stat.morado   { border-bottom-color: #D946A8; }

        .quick-stat .stat-icon {
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .quick-stat .stat-num {
            font-size: 2rem;
            font-weight: 800;
            display: block;
            color: #022C22;
        }

        .quick-stat .stat-label {
            font-size: 0.70rem;
            color: #A0A0B0;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            margin-top: 0.3rem;
            display: block;
        }

        /* ═══════════════════════════════════════════
           SECCIÓN LABEL
        ═══════════════════════════════════════════ */
        .seccion-label {
            font-size: 0.73rem;
            text-transform: uppercase;
            letter-spacing: 1.8px;
            color: #6B7280;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-left: 0.2rem;
        }

        /* ═══════════════════════════════════════════
           MENU CARDS — desktop: cards anchas con descripción
        ═══════════════════════════════════════════ */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }

        .menu-card-v2 {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(16,185,129,0.1);
        }

        .menu-card-v2::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #059669, #10B981, #3B82F6);
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 16px 16px 0 0;
        }

        .menu-card-v2:hover::after { opacity: 1; }

        .menu-card-v2:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 32px rgba(5,150,105,0.15);
            border-color: rgba(16,185,129,0.3);
        }

        .menu-card-v2 .card-icon {
            width: 52px;
            height: 52px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .menu-card-v2 .card-icon svg { width: 26px; height: 26px; display: block; }

        .menu-card-v2 .card-icon.verde    { background: #ECFDF5; color: #059669; }
        .menu-card-v2 .card-icon.azul     { background: #EFF6FF; color: #2563EB; }
        .menu-card-v2 .card-icon.amarillo { background: #FFFBEB; color: #D97706; }
        .menu-card-v2 .card-icon.rojo     { background: #FDF2F8; color: #DB2777; }
        .menu-card-v2 .card-icon.morado   { background: #F5F3FF; color: #7C3AED; }
        .menu-card-v2 .card-icon.naranja  { background: #FFF7ED; color: #EA580C; }
        .menu-card-v2 .card-icon.gris     { background: #F1F5F9; color: #64748B; }

        .menu-card-v2 h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #022C22;
            margin: 0 0 0.4rem 0;
        }

        .menu-card-v2 p {
            font-size: 0.83rem;
            color: #9CA3AF;
            margin: 0;
            line-height: 1.5;
        }

        /* Tarjeta evaluacion activa */
        .menu-card-v2.eval-activa {
            border: 2px solid #059669;
            background: linear-gradient(135deg, #fff 60%, #ECFDF5 100%);
            box-shadow: 0 4px 20px rgba(5,150,105,0.18);
        }
        .menu-card-v2.eval-activa::after { opacity: 1; }
        .menu-card-v2.eval-deshabilitada {
            opacity: 0.45;
            pointer-events: none;
            filter: grayscale(0.4);
        }
        .eval-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: #059669; color: white;
            font-size: 0.72rem; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
            margin-bottom: 8px; width: fit-content;
        }
        .eval-badge .dot {
            width: 7px; height: 7px; background: white;
            border-radius: 50%; animation: blink 1.2s infinite;
        }
        .eval-badge.pendiente { background: #f59e0b; }
        .eval-badge.completa  { background: #059669; }
        .eval-badge.inactiva  { background: #9CA3AF; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }

        .menu-card-v2 .card-arrow {
            position: absolute;
            top: 1.8rem;
            right: 1.5rem;
            font-size: 1.1rem;
            color: #D1D5DB;
            transition: all 0.3s;
        }
        .menu-card-v2 .card-arrow svg { width: 18px; height: 18px; }

        .menu-card-v2:hover .card-arrow {
            color: #10B981;
            transform: translateX(5px);
        }

        /* Variante peligro */
        .menu-card-v2.danger::after {
            background: linear-gradient(90deg, #EF4444, #F87171);
        }
        .menu-card-v2.danger:hover { border-color: rgba(239,68,68,0.35); }
        .menu-card-v2.danger:hover .card-arrow { color: #EF4444; }

        /* ═══════════════════════════════════════════
           INTEP INGLÉS — card especial
        ═══════════════════════════════════════════ */
        .ingles-card {
            display: block;
            text-decoration: none;
            background: linear-gradient(135deg, #0A1628 0%, #0F2040 50%, #162850 100%);
            border-radius: 20px;
            padding: 1.6rem 1.8rem;
            border: 1px solid rgba(59,130,246,0.35);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 1.2rem;
            box-shadow: 0 4px 24px rgba(59,130,246,0.15);
        }

        /* Brillo animado tipo shimmer */
        .ingles-card::before {
            content: '';
            position: absolute;
            top: -50%; left: -60%;
            width: 40%; height: 200%;
            background: linear-gradient(105deg, transparent, rgba(255,255,255,0.07), transparent);
            animation: ingles-shimmer 3s infinite;
        }
        @keyframes ingles-shimmer {
            0%   { left: -60%; }
            100% { left: 160%; }
        }

        /* Glow orb fondo */
        .ingles-card::after {
            content: '';
            position: absolute;
            top: -30px; right: -30px;
            width: 140px; height: 140px;
            background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, transparent 70%);
            border-radius: 50%;
        }

        .ingles-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(59,130,246,0.3);
            border-color: rgba(59,130,246,0.6);
        }

        .ingles-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .ingles-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(59,130,246,0.18);
            border: 1px solid rgba(59,130,246,0.35);
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 800;
            color: #93C5FD;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ingles-streak {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(245,200,66,0.12);
            border: 1px solid rgba(245,200,66,0.3);
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 800;
            color: #F5C842;
        }

        .ingles-card-body {
            position: relative;
            z-index: 1;
        }

        .ingles-title {
            font-size: 1.35rem;
            font-weight: 900;
            color: #F1F5F9;
            margin-bottom: 0.3rem;
            letter-spacing: -0.3px;
        }

        .ingles-title span {
            background: linear-gradient(90deg, #60A5FA, #93C5FD);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .ingles-sub {
            font-size: 0.82rem;
            color: #64748B;
            margin-bottom: 1.1rem;
        }

        .ingles-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .ingles-xp-bar-wrap {
            flex: 1;
            margin-right: 1rem;
        }

        .ingles-xp-label {
            font-size: 0.70rem;
            color: #475569;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        .ingles-xp-label span { color: #F5C842; }

        .ingles-xp-bg {
            background: rgba(255,255,255,0.08);
            border-radius: 50px;
            height: 7px;
            overflow: hidden;
        }

        .ingles-xp-fill {
            height: 100%;
            background: linear-gradient(90deg, #3B82F6, #60A5FA);
            border-radius: 50px;
            transition: width 1s ease;
        }

        .ingles-cta {
            background: linear-gradient(135deg, #3B82F6, #1D4ED8);
            color: white;
            font-size: 0.82rem;
            font-weight: 800;
            padding: 9px 18px;
            border-radius: 50px;
            white-space: nowrap;
            box-shadow: 0 4px 14px rgba(59,130,246,0.4);
            transition: all 0.2s;
        }

        .ingles-card:hover .ingles-cta {
            box-shadow: 0 6px 20px rgba(59,130,246,0.55);
            transform: scale(1.04);
        }

        /* ═══════════════════════════════════════════
           INFO PROGRAMA (ESTUDIANTE)
        ═══════════════════════════════════════════ */
        .info-programa {
            background: white;
            border: 1px solid rgba(16,185,129,0.15);
            border-left: 4px solid #10B981;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .info-programa .prog-icon { font-size: 1.5rem; }

        .info-programa .prog-text {
            font-size: 0.9rem;
            color: #6B7280;
        }

        .info-programa .prog-text strong {
            color: #022C22;
            display: block;
            font-weight: 700;
        }

        /* ═══════════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════════ */
        
        /* Tablets */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1.5rem 1rem 2rem;
            }
            
            .bienvenida-hero { 
                padding: 1.5rem; 
                border-radius: 16px;
            }
            .bienvenida-hero h2 { 
                font-size: 1.3rem; 
            }
            .bienvenida-hero .saludo {
                font-size: 0.8rem;
            }
            .bienvenida-hero .rol-badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.8rem;
            }
            .hero-foto img,
            .hero-foto-placeholder {
                width: 80px;
                height: 80px;
            }
            .hero-foto {
                margin-left: 1rem;
            }
            
            .quick-stats { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 0.8rem;
            }
            .quick-stat {
                padding: 1rem 0.8rem;
                border-radius: 12px;
            }
            .quick-stat .stat-num {
                font-size: 1.5rem;
            }
            .quick-stat .stat-label {
                font-size: 0.7rem;
            }
            
            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.85rem;
            }
            .menu-card-v2 {
                padding: 1rem;
                border-radius: 16px;
            }
            .menu-card-v2 .card-icon {
                width: 42px;
                height: 42px;
                font-size: 1.2rem;
            }
            .menu-card-v2 h3 {
                font-size: 0.88rem;
            }
            
            .info-programa {
                padding: 0.8rem 1rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .dashboard-header h1 img {
                height: 30px;
            }
            .dashboard-header .usuario-info {
                font-size: 0.75rem;
                max-width: 120px;
            }
        }

        /* Móviles grandes */
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 1rem 0.8rem 1.5rem;
            }
            
            .bienvenida-hero { 
                padding: 1.2rem; 
                border-radius: 14px;
                margin-bottom: 1.5rem;
            }
            .bienvenida-hero h2 { 
                font-size: 1.1rem; 
            }
            .bienvenida-hero .saludo {
                font-size: 0.75rem;
            }
            .bienvenida-hero .fecha {
                font-size: 0.7rem;
            }
            .hero-foto img,
            .hero-foto-placeholder {
                width: 65px;
                height: 65px;
                font-size: 1.5rem;
            }
            .hero-foto {
                margin-left: 0.8rem;
            }
            .hero-foto-placeholder {
                border-width: 2px;
            }

            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.6rem;
            }
            .quick-stat {
                padding: 0.8rem 0.5rem;
                border-radius: 10px;
            }
            .quick-stat .stat-icon {
                font-size: 1.2rem;
            }
            .quick-stat .stat-num {
                font-size: 1.3rem;
            }
            .quick-stat .stat-label {
                font-size: 0.65rem;
            }
            
            .seccion-label {
                font-size: 0.65rem;
                letter-spacing: 1.2px;
                margin-bottom: 0.8rem;
            }
            
            .menu-grid {
                gap: 0.75rem;
            }
            .menu-card-v2 {
                padding: 0.85rem;
                border-radius: 14px;
            }
            .menu-card-v2 .card-icon {
                width: 38px;
                height: 38px;
                font-size: 1.05rem;
            }
            .menu-card-v2 h3 {
                font-size: 0.82rem;
            }
            
            .info-programa {
                font-size: 0.85rem;
            }
            
            .dashboard-header {
                padding: 0.5rem 0.8rem;
            }
            .dashboard-header h1 img {
                height: 26px;
            }
            .dashboard-header .usuario-info {
                font-size: 0.68rem;
                max-width: 100px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .dashboard-header .btn-salir {
                padding: 0.3rem 0.7rem;
                font-size: 0.68rem;
            }
        }

        /* Móviles pequeños */
        @media (max-width: 360px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .bienvenida-hero h2 {
                font-size: 1rem;
            }
            
            .menu-card-v2 {
                padding: 0.75rem;
            }
            .menu-card-v2 h3 {
                font-size: 0.78rem;
            }
        }

        /* ═══════════════════════════════════════════
           MÓVIL ESTUDIANTE: grid cuadrado + bottom-nav
           Solo aplica con max-width:768px y rol estudiante
        ═══════════════════════════════════════════ */
        @media (max-width: 768px) {
            body[data-rol="estudiante"] .menu-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.85rem;
            }
            body[data-rol="estudiante"] .menu-card-v2 {
                aspect-ratio: 1 / 1;
                padding: 1rem;
                border-radius: 18px;
                justify-content: space-between;
                opacity: 0;
                animation: card-pop .5s cubic-bezier(.34,1.56,.64,1) forwards;
                will-change: transform, opacity;
            }
            body[data-rol="estudiante"] .menu-card-v2 p { display: none; }
            body[data-rol="estudiante"] .menu-card-v2 .card-arrow { display: none; }
            body[data-rol="estudiante"] .menu-card-v2 .card-icon {
                width: 44px; height: 44px; margin-bottom: 0;
            }
            body[data-rol="estudiante"] .menu-card-v2 .card-icon svg {
                width: 22px; height: 22px;
            }
            body[data-rol="estudiante"] .menu-card-v2 h3 {
                font-size: 0.88rem;
                margin: 0;
                line-height: 1.25;
            }

            body[data-rol="estudiante"] .menu-card-v2:nth-child(1)  { animation-delay: .04s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(2)  { animation-delay: .08s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(3)  { animation-delay: .12s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(4)  { animation-delay: .16s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(5)  { animation-delay: .20s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(6)  { animation-delay: .24s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(7)  { animation-delay: .28s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(8)  { animation-delay: .32s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(9)  { animation-delay: .36s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(10) { animation-delay: .40s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(11) { animation-delay: .44s; }
            body[data-rol="estudiante"] .menu-card-v2:nth-child(12) { animation-delay: .48s; }

            body[data-rol="estudiante"] .menu-card-v2:active {
                transform: scale(.94);
                box-shadow: 0 1px 4px rgba(0,0,0,0.08);
                transition: transform .08s ease;
            }

            body[data-rol="estudiante"] .menu-card-v2:hover {
                transform: none;
                box-shadow: 0 2px 12px rgba(0,0,0,0.05);
                border-color: rgba(16,185,129,0.1);
            }

            /* Bottom-nav solo en móvil */
            body[data-rol="estudiante"] .bottom-nav { display: flex; }
            body[data-rol="estudiante"] .dashboard-container { padding-bottom: 100px; }
        }

        @keyframes card-pop {
            0%   { opacity: 0; transform: translateY(14px) scale(.92); }
            70%  { opacity: 1; transform: translateY(-2px) scale(1.02); }
            100% { opacity: 1; transform: translateY(0)    scale(1);   }
        }

        /* ═══════════════════════════════════════════
           BOTTOM NAV — oculto por defecto, visible solo en móvil
        ═══════════════════════════════════════════ */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            left: 12px; right: 12px;
            height: 68px;
            background: rgba(255,255,255,0.98);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            justify-content: space-around;
            align-items: center;
            padding: 0 6px;
            border-radius: 999px;
            box-shadow: 0 10px 32px rgba(0,0,0,0.14), 0 2px 8px rgba(0,0,0,0.06);
            z-index: 100;
        }
        .bn-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            text-decoration: none;
            color: #374151;
            font-size: 0.72rem;
            font-weight: 600;
            transition: color .2s ease, transform .15s ease;
            padding: 6px 4px;
            border-radius: 18px;
        }
        .bn-item .bn-icon {
            width: 26px; height: 26px;
            display: inline-flex;
            align-items: center; justify-content: center;
            transition: transform .2s ease;
        }
        .bn-item .bn-icon svg { width: 24px; height: 24px; display: block; stroke-width: 2; }
        .bn-item.active {
            color: #059669;
            font-weight: 700;
        }
        .bn-item.active .bn-icon { transform: scale(1.1); }
        .bn-item:active { transform: scale(.92); }

        @media (prefers-reduced-motion: reduce) {
            .menu-card-v2 { animation: none !important; opacity: 1 !important; }
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

    <div class="dashboard-header">
        <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
        <span class="usuario-info"><?php echo htmlspecialchars($nombre); ?> · <?php echo ucfirst($rol); ?></span>
        <a href="logout.php" class="btn-salir">Cerrar sesión</a>
    </div>

    <div class="dashboard-container">

        <!-- BIENVENIDA -->
        <div class="bienvenida-hero">
            <div class="hero-content">
                <div class="hero-text">
                    <div class="saludo"><?php echo $saludo_icon . ' ' . $saludo; ?></div>
                    <h2><?php echo htmlspecialchars($nombre); ?></h2>
                    <div class="fecha">📆 <?php echo $fecha_hoy; ?></div>
                    <span class="rol-badge">
                        <?php
                        switch ($rol) {
                            case 'admin':      echo '⚙️ Administrador'; break;
                            case 'docente':    echo '👨‍🏫 Docente';       break;
                            case 'estudiante': echo '🎓 Estudiante';     break;
                        }
                        ?>
                    </span>
                </div>
                <?php if ($rol === 'estudiante'): ?>
                <a href="mi_foto.php" class="hero-foto" title="Cambiar foto de perfil">
                    <?php if (!empty($info_est['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($info_est['foto']); ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <div class="hero-foto-placeholder">
                            <span>📷</span>
                        </div>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== ESTUDIANTE ===== -->
        <?php if ($rol === 'estudiante'): ?>

            <?php if (isset($info_est)): ?>
            <div class="info-programa">
                <span class="prog-icon">🎓</span>
                <div class="prog-text">
                    <strong><?php echo htmlspecialchars($info_est['programa']); ?></strong>
                    Documento: <?php echo htmlspecialchars($info_est['documento']); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="quick-stats">
                <div class="quick-stat verde">
                    <span class="stat-icon">📊</span>
                    <span class="stat-num"><?php echo $stats_est['promedio'] ?? '—'; ?></span>
                    <span class="stat-label">Promedio</span>
                </div>
                <div class="quick-stat azul">
                    <span class="stat-icon">✅</span>
                    <span class="stat-num"><?php echo (int)($stats_est['aprobados'] ?? 0); ?></span>
                    <span class="stat-label">Aprobados</span>
                </div>
                <div class="quick-stat amarillo">
                    <span class="stat-icon">📚</span>
                    <span class="stat-num"><?php echo (int)($stats_est['total'] ?? 0); ?></span>
                    <span class="stat-label">Módulos</span>
                </div>
                <div class="quick-stat morado">
                    <span class="stat-icon">📅</span>
                    <span class="stat-num"><?php echo (int)($horarios_est['clases'] ?? 0); ?></span>
                    <span class="stat-label">Clases</span>
                </div>
            </div>

            <div class="seccion-label">Acceso rápido</div>

            <!-- ── INTEP INGLÉS ── -->
            <a href="idiomas.php" class="ingles-card">
                <div class="ingles-card-top">
                    <div class="ingles-badge">🌐 IA · Nuevo</div>
                    <div class="ingles-streak">🔥 <?php
                        // Racha del estudiante (0 si no ha empezado)
                        $q_streak = "SELECT racha_actual FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1";
                        $st = mysqli_prepare($conexion, $q_streak);
                        if ($st) {
                            mysqli_stmt_bind_param($st, 'i', $est_id);
                            mysqli_stmt_execute($st);
                            $r_streak = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
                            echo ($r_streak['racha_actual'] ?? 0) . ' días';
                        } else { echo '0 días'; }
                    ?></div>
                </div>
                <div class="ingles-card-body">
                    <div class="ingles-title">INTEP <span>Inglés</span></div>
                    <div class="ingles-sub">Ejercicios generados por IA · Ranking · Progreso por niveles</div>
                    <div class="ingles-card-footer">
                        <div class="ingles-xp-bar-wrap"><?php
                            $q_xp = "SELECT xp_total, nivel_actual FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1";
                            $st2 = mysqli_prepare($conexion, $q_xp);
                            $xp = 0; $nivel = 'A1'; $xp_pct = 0;
                            if ($st2) {
                                mysqli_stmt_bind_param($st2, 'i', $est_id);
                                mysqli_stmt_execute($st2);
                                $r_xp = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));
                                if ($r_xp) { $xp = $r_xp['xp_total']; $nivel = $r_xp['nivel_actual']; }
                            }
                            $xp_map = ['A1'=>[0,300],'A2'=>[300,700],'B1'=>[700,1200],'B2'=>[1200,2000]];
                            $range = $xp_map[$nivel] ?? [0,300];
                            $xp_pct = min(100, round(($xp - $range[0]) / ($range[1] - $range[0]) * 100));
                        ?>
                            <div class="ingles-xp-label">
                                <span>Nivel <?php echo $nivel; ?></span>
                                <span><?php echo $xp; ?> XP</span>
                            </div>
                            <div class="ingles-xp-bg">
                                <div class="ingles-xp-fill" style="width:<?php echo $xp_pct; ?>%"></div>
                            </div>
                        </div>
                        <div class="ingles-cta">Practicar →</div>
                    </div>
                </div>
            </a>

            <div class="menu-grid">
                <a href="notas.php" class="menu-card-v2">
                    <div class="card-icon verde"><?= icon('notas') ?></div>
                    <h3>Mis Notas</h3>
                    <p>Consulta tus calificaciones por bimestre y módulo con detalle de los 3 cortes</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <a href="horarios.php" class="menu-card-v2">
                    <div class="card-icon azul"><?= icon('horario') ?></div>
                    <h3>Mi Horario</h3>
                    <p>Consulta tu horario semanal y mensual de clases</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <a href="mi_cartera.php" class="menu-card-v2">
                    <div class="card-icon naranja"><?= icon('cartera') ?></div>
                    <h3>Mi Cartera</h3>
                    <p>Consulta tus pagos, cuotas pendientes y estado de cuenta</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <a href="asistencia.php" class="menu-card-v2">
                    <div class="card-icon verde"><?= icon('asistencia') ?></div>
                    <h3>Mi Asistencia</h3>
                    <p>Consulta tu registro de asistencia por módulo y bimestre</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <a href="solicitudes.php" class="menu-card-v2">
                    <div class="card-icon morado"><?= icon('solicitudes') ?></div>
                    <h3>Solicitudes</h3>
                    <p>Certificados, paz y salvo, reparación de equipos y más trámites</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <a href="materiales.php" class="menu-card-v2">
                    <div class="card-icon naranja"><?= icon('materiales') ?></div>
                    <h3>Material de Clase</h3>
                    <p>Descarga guías, talleres y recursos compartidos por tus profesores</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <a href="evaluar_docente.php" class="menu-card-v2 <?php echo $eval_activa ? 'eval-activa' : 'eval-deshabilitada'; ?>">
                    <div class="card-icon morado"><?= icon('evaluar') ?></div>
                    <?php if ($eval_activa): ?>
                        <?php if ($eval_pendientes > 0): ?>
                            <span class="eval-badge pendiente"><span class="dot"></span> <?php echo $eval_pendientes; ?> pendiente<?php echo $eval_pendientes > 1 ? 's' : ''; ?></span>
                        <?php else: ?>
                            <span class="eval-badge completa"><span class="dot"></span> Completada</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="eval-badge inactiva">No disponible</span>
                    <?php endif; ?>
                    <h3>Evaluar Docentes</h3>
                    <p><?php echo $eval_activa
                        ? ($eval_pendientes > 0 ? 'La evaluación está abierta · Período ' . htmlspecialchars($eval_periodo) : '¡Ya evaluaste a todos tus docentes este período!')
                        : 'La evaluación no está habilitada por el momento'; ?>
                    </p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <?php if (!empty($tiene_ingles)): ?>
                <a href="/intep/cursoingles/index.php" class="menu-card-v2">
                    <div class="card-icon morado"><?= icon('ingles') ?></div>
                    <h3>Módulos de Inglés</h3>
                    <p>Aprende con flashcards, juegos y misiones de rol interactivas</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($es_primera_infancia)): ?>
                <a href="/intep/cursoingles/cursoinglespreescolar/index.php" class="menu-card-v2">
                    <div class="card-icon amarillo"><?= icon('kids') ?></div>
                    <h3>INTEP Kids</h3>
                    <p>Practica inglés con juegos, canciones y actividades para tu seminario</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($tiene_almacenamiento)): ?>
                <a href="/intep/cursodealmacenamiento/entrada_curso.php" class="menu-card-v2">
                    <div class="card-icon naranja"><?= icon('almacen') ?></div>
                    <h3>Curso de Almacenamiento</h3>
                    <p>Técnicas de almacenamiento, recibo y despacho de mercancías · 6 módulos</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
                <?php endif; ?>
                <a href="#" class="menu-card-v2 btn-instalar-app" onclick="instalarApp(event)">
                    <div class="card-icon verde"><?= icon('app') ?></div>
                    <h3>Descargar App</h3>
                    <p>Instala la aplicación en tu celular para acceso rápido</p>
                    <span class="card-arrow"><?= icon('arrow', ['size' => 18]) ?></span>
                </a>
            </div>

        <!-- ===== DOCENTE ===== -->
        <?php elseif ($rol === 'docente'): ?>

            <div class="quick-stats">
                <div class="quick-stat verde">
                    <span class="stat-icon">📦</span>
                    <span class="stat-num"><?php echo (int)($stats_doc['modulos'] ?? 0); ?></span>
                    <span class="stat-label">Mis Módulos</span>
                </div>
                <div class="quick-stat azul">
                    <span class="stat-icon">👥</span>
                    <span class="stat-num"><?php echo (int)($est_doc['estudiantes'] ?? 0); ?></span>
                    <span class="stat-label">Estudiantes</span>
                </div>
            </div>

            <div class="seccion-label">Herramientas</div>
            <div class="menu-grid">
                <a href="admin/gestionar_modulos.php" class="menu-card-v2">
                    <div class="card-icon verde">📋</div>
                    <h3>Gestionar Módulos</h3>
                    <p>Consulta los módulos que tienes asignados por programa</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/ingresar_notas.php" class="menu-card-v2">
                    <div class="card-icon amarillo">✏️</div>
                    <h3>Ingresar Notas</h3>
                    <p>Registra y edita las calificaciones de tus estudiantes</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="horarios.php" class="menu-card-v2">
                    <div class="card-icon azul">📅</div>
                    <h3>Horarios</h3>
                    <p>Consulta los horarios de los estudiantes del programa</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/links_virtuales.php" class="menu-card-v2">
                    <div class="card-icon azul">🔗</div>
                    <h3>Links de Clases Virtuales</h3>
                    <p>Agrega o edita los links de Meet, Zoom o Teams para cada clase</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/materiales.php" class="menu-card-v2">
                    <div class="card-icon naranja">📚</div>
                    <h3>Material de Clase</h3>
                    <p>Sube guías, talleres, evaluaciones y recursos para tus estudiantes</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/eval_resultados.php" class="menu-card-v2 <?php echo $eval_activa ? 'eval-activa' : ''; ?>">
                    <div class="card-icon morado">⭐</div>
                    <?php if ($eval_activa): ?>
                        <span class="eval-badge pendiente"><span class="dot"></span> En curso · <?php echo htmlspecialchars($eval_periodo); ?></span>
                    <?php endif; ?>
                    <h3>Mi Evaluación de Desempeño</h3>
                    <p><?php echo $eval_activa
                        ? 'Tus estudiantes te están evaluando ahora · Ve tus resultados'
                        : 'Consulta los resultados de evaluaciones anteriores'; ?>
                    </p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="#" class="menu-card-v2 btn-instalar-app" onclick="instalarApp(event)" style="display:none;">
                    <div class="card-icon verde">📲</div>
                    <h3>Descargar App</h3>
                    <p>Instala la aplicación en tu celular para acceso rápido</p>
                    <span class="card-arrow">→</span>
                </a>
            </div>

        <!-- ===== ADMIN ===== -->
        <?php elseif ($rol === 'admin'): ?>

            <div class="quick-stats">
                <div class="quick-stat verde">
                    <span class="stat-icon">👥</span>
                    <span class="stat-num"><?php echo $stats_admin['estudiantes']; ?></span>
                    <span class="stat-label">Estudiantes</span>
                </div>
                <div class="quick-stat azul">
                    <span class="stat-icon">👨‍🏫</span>
                    <span class="stat-num"><?php echo $stats_admin['docentes']; ?></span>
                    <span class="stat-label">Docentes</span>
                </div>
                <div class="quick-stat amarillo">
                    <span class="stat-icon">🎓</span>
                    <span class="stat-num"><?php echo $stats_admin['programas']; ?></span>
                    <span class="stat-label">Programas</span>
                </div>
                <div class="quick-stat morado">
                    <span class="stat-icon">📦</span>
                    <span class="stat-num"><?php echo $stats_admin['modulos']; ?></span>
                    <span class="stat-label">Módulos</span>
                </div>
            </div>

            <div class="seccion-label">Gestión del sistema</div>
            <div class="menu-grid">
                <a href="admin/index.php" class="menu-card-v2">
                    <div class="card-icon rojo">⚙️</div>
                    <h3>Panel Admin</h3>
                    <p>Gestiona estudiantes, docentes y cuentas del sistema</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/gestionar_modulos.php" class="menu-card-v2">
                    <div class="card-icon verde">📋</div>
                    <h3>Gestionar Módulos</h3>
                    <p>Crea y asigna módulos por bimestre y programa</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/modulos_estudiantes.php" class="menu-card-v2">
                    <div class="card-icon amarillo">📚</div>
                    <h3>Módulos Estudiantes</h3>
                    <p>Asigna módulos del bimestre a cada estudiante individualmente</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/ingresar_notas.php" class="menu-card-v2">
                    <div class="card-icon amarillo">✏️</div>
                    <h3>Ingresar Notas</h3>
                    <p>Registra y edita calificaciones de los estudiantes</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="horarios.php" class="menu-card-v2">
                    <div class="card-icon azul">📅</div>
                    <h3>Horarios</h3>
                    <p>Gestiona los horarios de clases del instituto</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/links_virtuales.php" class="menu-card-v2">
                    <div class="card-icon azul">🔗</div>
                    <h3>Links de Clases Virtuales</h3>
                    <p>Agrega o edita los links de Meet, Zoom o Teams para cada clase</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/eval_admin.php" class="menu-card-v2">
                    <div class="card-icon morado">⭐</div>
                    <h3>Evaluación Docente</h3>
                    <p>Activa evaluaciones, consulta resultados y estadísticas por docente</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/cartera.php" class="menu-card-v2">
                    <div class="card-icon naranja">💳</div>
                    <h3>Cartera y Pagos</h3>
                    <p>Gestiona los pagos, cuotas y estado de cuenta de los estudiantes</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/cursos_admin.php" class="menu-card-v2">
                    <div class="card-icon azul">🎓</div>
                    <h3>Cursos Admin</h3>
                    <p>Accede a todos los cursos de la plataforma para verificar su funcionamiento</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="admin/limpiar_datos.php" class="menu-card-v2 danger">
                    <div class="card-icon gris">🗑️</div>
                    <h3>Limpiar Datos de Prueba</h3>
                    <p>Elimina todos los usuarios y registros de prueba para dejar el sistema en limpio</p>
                    <span class="card-arrow">→</span>
                </a>
            </div>

        <?php endif; ?>

    </div>

    <?php if ($rol === 'estudiante'):
        $bn_page = basename($_SERVER['PHP_SELF']);
        $bn = function($file) use ($bn_page) { return $bn_page === $file ? ' active' : ''; };
    ?>
    <nav class="bottom-nav" aria-label="Navegación principal">
        <a href="notas.php" class="bn-item<?php echo $bn('notas.php'); ?>">
            <span class="bn-icon"><?= icon('notas', ['size' => 24]) ?></span>
            <span>Notas</span>
        </a>
        <a href="horarios.php" class="bn-item<?php echo $bn('horarios.php'); ?>">
            <span class="bn-icon"><?= icon('horario', ['size' => 24]) ?></span>
            <span>Horario</span>
        </a>
        <a href="dashboard.php" class="bn-item<?php echo $bn('dashboard.php'); ?>">
            <span class="bn-icon"><?= icon('home', ['size' => 24]) ?></span>
            <span>Home</span>
        </a>
        <a href="mi_cartera.php" class="bn-item<?php echo $bn('mi_cartera.php'); ?>">
            <span class="bn-icon"><?= icon('cartera', ['size' => 24]) ?></span>
            <span>Cartera</span>
        </a>
        <a href="perfil.php" class="bn-item<?php echo $bn('perfil.php'); ?>">
            <span class="bn-icon"><?= icon('perfil', ['size' => 24]) ?></span>
            <span>Yo</span>
        </a>
    </nav>
    <?php endif; ?>

<script src="/intep/sesion.js"></script>

<!-- PWA Install -->
<script>
let deferredPrompt = null;

// Si ya está instalada (modo standalone), no mostrar botón
if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
    // App ya instalada → ocultar botón
    document.querySelectorAll('.btn-instalar-app').forEach(function(btn) {
        btn.style.display = 'none';
    });
} else {
    // Capturar evento de instalación PWA (Android Chrome)
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
    });
}

function instalarApp(e) {
    e.preventDefault();
    if (!deferredPrompt) {
        // Fallback: instrucciones manuales
        alert('Para instalar la app:\n\n📱 Android (Chrome): Toca el menú ⋮ → "Añadir a pantalla de inicio"\n\n🍎 iPhone (Safari): Toca el botón compartir ↑ → "Añadir a pantalla de inicio"');
        return;
    }
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function(choiceResult) {
        if (choiceResult.outcome === 'accepted') {
            // Ocultar botones después de instalar
            document.querySelectorAll('.btn-instalar-app').forEach(function(btn) {
                btn.style.display = 'none';
            });
        }
        deferredPrompt = null;
    });
}

// Ocultar si se instala mientras está en la página
window.addEventListener('appinstalled', function() {
    document.querySelectorAll('.btn-instalar-app').forEach(function(btn) {
        btn.style.display = 'none';
    });
    deferredPrompt = null;
});
</script>
<!-- ── GATITO NEGRO — permanente, cambia de posición en la card ── -->
<div id="gato-wrap" style="position:absolute;overflow:hidden;pointer-events:none;border-radius:20px;z-index:10;">
    <div id="gato-container" style="position:absolute;">
        <div id="gato-inner">
            <svg width="30" height="30" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="50" cy="72" rx="22" ry="16" fill="#111"/>
                <circle cx="50" cy="48" r="18" fill="#111"/>
                <polygon points="35,36 30,18 44,30" fill="#111"/>
                <polygon points="65,36 70,18 56,30" fill="#111"/>
                <polygon points="36,34 32,22 43,31" fill="#1a1a2e"/>
                <polygon points="64,34 68,22 57,31" fill="#1a1a2e"/>
                <ellipse cx="43" cy="47" rx="4" ry="5" fill="#f0e130"/>
                <ellipse cx="57" cy="47" rx="4" ry="5" fill="#f0e130"/>
                <ellipse cx="43" cy="47" rx="2" ry="4" fill="#111"/>
                <ellipse cx="57" cy="47" rx="2" ry="4" fill="#111"/>
                <polygon points="50,53 48,56 52,56" fill="#ff8fa3"/>
                <line x1="30" y1="54" x2="46" y2="55" stroke="#555" stroke-width="1.2"/>
                <line x1="30" y1="57" x2="46" y2="57" stroke="#555" stroke-width="1.2"/>
                <path d="M72,72 Q95,60 90,82 Q80,90 72,82" fill="#111"/>
                <ellipse cx="36" cy="86" rx="7" ry="5" fill="#111"/>
                <ellipse cx="64" cy="86" rx="7" ry="5" fill="#111"/>
            </svg>
        </div>
    </div>
</div>

<style>
@keyframes gato-bob {
    0%,100% { transform: translateY(0) rotate(0deg); }
    30%      { transform: translateY(-3px) rotate(-3deg); }
    70%      { transform: translateY(-1px) rotate(2deg); }
}
@keyframes gato-fade-in  { from { opacity:0; transform: scale(.6); } to { opacity:1; transform: scale(1); } }
@keyframes gato-fade-out { from { opacity:1; transform: scale(1); } to { opacity:0; transform: scale(.6); } }
#gato-container { transition: top .8s cubic-bezier(.34,1.56,.64,1), left .8s cubic-bezier(.34,1.56,.64,1); }
#gato-inner { animation: gato-bob .9s ease-in-out infinite; }
</style>

<script>
(function() {
    var wrap = document.getElementById('gato-wrap');
    var cont = document.getElementById('gato-container');
    var inner = document.getElementById('gato-inner');
    var sz = 30; // tamaño del gato px
    var margin = 8;

    // 4 posiciones: esquinas de la card
    var posiciones = [
        { bottom: margin, right: margin,  top: 'auto', left: 'auto'  },  // inf-der
        { bottom: margin, left:  margin,  top: 'auto', right: 'auto' },  // inf-izq
        { top:    margin, right: margin,  bottom: 'auto', left: 'auto' },// sup-der
        { top:    margin, left:  margin,  bottom: 'auto', right: 'auto'},// sup-izq
    ];
    var posActual = 0;

    function posicionarSobreCard() {
        var card = document.querySelector('.ingles-card');
        if (!card) return false;
        var rect = card.getBoundingClientRect();
        var scrollY = window.scrollY || document.documentElement.scrollTop;
        wrap.style.top    = (rect.top + scrollY) + 'px';
        wrap.style.left   = rect.left + 'px';
        wrap.style.width  = rect.width + 'px';
        wrap.style.height = rect.height + 'px';
        return true;
    }

    function aplicarPosicion(idx) {
        var p = posiciones[idx];
        cont.style.top    = p.top    !== undefined ? (p.top    === 'auto' ? '' : p.top    + 'px') : '';
        cont.style.bottom = p.bottom !== undefined ? (p.bottom === 'auto' ? '' : p.bottom + 'px') : '';
        cont.style.left   = p.left   !== undefined ? (p.left   === 'auto' ? '' : p.left   + 'px') : '';
        cont.style.right  = p.right  !== undefined ? (p.right  === 'auto' ? '' : p.right  + 'px') : '';
        // limpiar el opuesto
        if (p.top    === 'auto') cont.style.top    = '';
        if (p.bottom === 'auto') cont.style.bottom = '';
        if (p.left   === 'auto') cont.style.left   = '';
        if (p.right  === 'auto') cont.style.right  = '';
    }

    function moverGato() {
        // Fade out
        inner.style.animation = 'none';
        inner.style.opacity = '0';
        inner.style.transform = 'scale(.6)';
        inner.style.transition = 'opacity .35s ease, transform .35s ease';

        setTimeout(function() {
            // Cambiar a posición aleatoria diferente
            var opciones = [0,1,2,3].filter(function(i){ return i !== posActual; });
            posActual = opciones[Math.floor(Math.random() * opciones.length)];
            posicionarSobreCard();
            aplicarPosicion(posActual);

            // Fade in
            setTimeout(function() {
                inner.style.opacity = '1';
                inner.style.transform = 'scale(1)';
                setTimeout(function() {
                    inner.style.transition = '';
                    inner.style.animation = 'gato-bob .9s ease-in-out infinite';
                }, 400);
            }, 100);
        }, 380);
    }

    // Mostrar inmediatamente en posición inicial
    function init() {
        if (!posicionarSobreCard()) { setTimeout(init, 300); return; }
        aplicarPosicion(posActual);
        inner.style.opacity = '0';
        inner.style.transform = 'scale(.6)';
        inner.style.transition = 'opacity .5s ease, transform .5s ease';
        setTimeout(function() {
            inner.style.opacity = '1';
            inner.style.transform = 'scale(1)';
            setTimeout(function() {
                inner.style.transition = '';
                inner.style.animation = 'gato-bob .9s ease-in-out infinite';
            }, 500);
        }, 800);
        // Cambiar posición cada 35-55 seg
        setInterval(moverGato, Math.random() * 20000 + 35000);
    }

    window.addEventListener('resize', posicionarSobreCard);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }
})();
</script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/intep/sw.js');
}
</script>
</body>
</html>