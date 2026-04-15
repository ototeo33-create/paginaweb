<?php
date_default_timezone_set('America/Bogota');
require_once 'config.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$loginExitoso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $query = "SELECT u.*, e.nombre as nombre_estudiante 
                  FROM usuarios u 
                  LEFT JOIN estudiantes e ON u.estudiante_id = e.id 
                  WHERE u.username = ?";
        
        $stmt = mysqli_prepare($conexion, $query);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        $usuario = mysqli_fetch_assoc($resultado);

        // Verificar intentos fallidos
        if (verificarIntentosFallidos($username, $conexion)) {
            $error = 'Demasiados intentos. Intenta en 15 minutos.';
        } elseif ($usuario && verificarPassword($password, $usuario['password_hash'], $conexion)) {
            if ($usuario['estado'] === 'activo') {
                // Rehash si es necesario
                if (needsRehash($usuario['password_hash'])) {
                    $nuevoHash = hashPassword($password);
                    $upd = mysqli_prepare($conexion, "UPDATE usuarios SET password_hash = ? WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'si', $nuevoHash, $usuario['id']);
                    mysqli_stmt_execute($upd);
                }
                
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_rol'] = $usuario['rol'];
                $_SESSION['usuario_nombre'] = $usuario['nombre_estudiante'] ?? $username;
                $_SESSION['estudiante_id'] = $usuario['estudiante_id'];
                $_SESSION['ultimo_acceso'] = time();
                $_SESSION['login_time'] = time();
                $loginExitoso = true;
            } elseif ($usuario['estado'] === 'inactivo') {
                $error = 'Tu cuenta ha sido desactivada. Contacta a secretaría.';
            }
        } else {
            // Registrar intento fallido siempre (previene enumeración de usuarios)
            registrarIntentoFallido($username, $conexion);
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

$imagenes = [
    'img/foto1.jpg',
    'img/foto2.jpg',
    'img/foto3.jpg',
    'img/foto4.jpg',
    'img/foto5.jpg',
];

$dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fecha_hoy = $dias[(int)date('w')] . ', ' . date('j') . ' de ' . $meses[(int)date('n')] . ' de ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Estudiantil – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* ═══════════════════════════════════════════════════
           LOGIN PREMIUM — INTEP VERDE INSTITUCIONAL
        ═══════════════════════════════════════════════════ */
        .login-body { margin:0; padding:0; min-height:100vh; overflow:hidden; }
        .login-wrapper { display:flex; min-height:100vh; }

        /* ========== FONDO VERDE INSTITUCIONAL ========== */
        .espacio-fondo {
            position:fixed; top:0; left:0; width:100%; height:100%;
            background: linear-gradient(135deg, #022C22 0%, #064E3B 40%, #047857 70%, #022C22 100%);
            z-index:0;
            overflow:hidden;
        }

        .espacio-fondo::before {
            content:'';
            position:absolute;
            top:-30%; left:-20%; width:140%; height:140%;
            background:
                radial-gradient(ellipse 50% 40% at 20% 20%, rgba(16,185,129,0.2) 0%, transparent 50%),
                radial-gradient(ellipse 40% 30% at 80% 30%, rgba(5,150,105,0.15) 0%, transparent 50%),
                radial-gradient(ellipse 45% 35% at 60% 80%, rgba(110,231,183,0.08) 0%, transparent 50%),
                radial-gradient(ellipse 35% 25% at 10% 70%, rgba(16,185,129,0.12) 0%, transparent 50%);
            animation: gradiente-mover 15s ease-in-out infinite;
        }
        @keyframes gradiente-mover {
            0%, 100% { transform:translate(0,0) scale(1); }
            50% { transform:translate(5%, 3%) scale(1.1); }
        }

        .espacio-fondo::after {
            content:'';
            position:absolute;
            bottom:-20%; right:-10%; width:100%; height:100%;
            background:
                radial-gradient(ellipse 60% 50% at 90% 90%, rgba(5,150,105,0.15) 0%, transparent 50%);
            animation: gradiente-mover 18s ease-in-out infinite reverse;
        }

        /* Constelaciones */
        .constelacion {
            position:absolute;
            opacity:0.25;
            animation: constelacion-float 15s ease-in-out infinite;
        }
        @keyframes constelacion-float {
            0%, 100% { transform:translateY(0) rotate(0deg); }
            50% { transform:translateY(-20px) rotate(2deg); }
        }
        .constelacion line {
            stroke:rgba(16,185,129,0.35);
            stroke-width:1;
            animation: pulse-line 3s ease-in-out infinite;
        }
        @keyframes pulse-line {
            0%, 100% { stroke-opacity:0.3; }
            50% { stroke-opacity:0.6; }
        }
        .constelacion circle {
            fill:rgba(167,243,208,0.5);
            animation: pulse-star 2s ease-in-out infinite;
        }
        @keyframes pulse-star {
            0%, 100% { r:2; fill-opacity:0.5; }
            50% { r:3; fill-opacity:0.8; }
        }

        /* Planetas verdes */
        .planeta {
            position:absolute;
            border-radius:50%;
            animation: planeta-orbit var(--duracion) linear infinite;
        }
        @keyframes planeta-orbit {
            0% { transform:rotate(0deg); }
            100% { transform:rotate(360deg); }
        }

        /* Estrellas fugaces */
        .estrella-fugaz {
            position:absolute;
            width:100px; height:2px;
            background: linear-gradient(90deg, rgba(167,243,208,0.7), transparent);
            opacity:0;
            animation: shooting-star 4s ease-in-out infinite;
            animation-delay: var(--delay);
        }
        @keyframes shooting-star {
            0% { opacity:0; transform:translateX(0) translateY(0); }
            5% { opacity:1; }
            15% { opacity:0; transform:translateX(300px) translateY(150px); }
            100% { opacity:0; }
        }

        /* ========== PANTALLA DE CARGA ========== */
        .loader-screen {
            position:fixed; top:0; left:0; width:100%; height:100%;
            background: linear-gradient(135deg, #022C22 0%, #064E3B 50%, #047857 100%);
            z-index:9999;
            display:none;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:1.8rem;
        }
        .loader-screen.activo {
            display:flex;
            animation: loaderIn 0.5s ease;
        }
        @keyframes loaderIn {
            from { opacity:0; } to { opacity:1; }
        }

        .loader-logo-container {
            position:relative;
            width:140px; height:140px;
            display:flex; align-items:center; justify-content:center;
        }
        .loader-logo-container img {
            width:100px;
            filter: brightness(0) invert(1) drop-shadow(0 0 25px rgba(16,185,129,0.5));
            animation: loaderPulse 2s ease-in-out infinite;
        }
        .loader-ring-outer {
            position:absolute; top:0; left:0; width:100%; height:100%;
            border: 3px solid transparent;
            border-top-color: #10B981;
            border-radius:50%;
            animation: spinCW 1.2s linear infinite;
        }
        .loader-ring-inner {
            position:absolute; top:15px; left:15px; right:15px; bottom:15px;
            border: 2px solid transparent;
            border-bottom-color: #6EE7B7;
            border-radius:50%;
            animation: spinCCW 0.9s linear infinite;
        }
        @keyframes spinCW { to { transform:rotate(360deg); } }
        @keyframes spinCCW { to { transform:rotate(-360deg); } }
        @keyframes loaderPulse {
            0%,100% { transform:scale(1); opacity:1; }
            50% { transform:scale(1.06); opacity:0.8; }
        }

        .loader-progress {
            width:220px; height:4px;
            background:rgba(16,185,129,0.15);
            border-radius:4px; overflow:hidden;
        }
        .loader-progress-bar {
            height:100%; width:0%;
            background: linear-gradient(90deg, #059669, #6EE7B7);
            border-radius:4px;
            animation: loaderBar 2s ease forwards;
        }
        @keyframes loaderBar {
            0% { width:0%; }
            30% { width:45%; }
            60% { width:70%; }
            90% { width:90%; }
            100% { width:100%; }
        }

        .loader-text {
            color:#6EE7B7; font-size:0.85rem;
            letter-spacing:3px; text-transform:uppercase;
            animation: loaderPulse 2s ease-in-out infinite;
        }
        .loader-welcome {
            color:white; font-size:1.1rem; font-weight:700;
            margin-top:-0.5rem;
            opacity:0;
            animation: fadeUp 0.5s ease 1s forwards;
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to { opacity:1; transform:translateY(0); }
        }

        /* ========== GALERÍA (izquierda) — BLANCA CON MOTIVOS EDUCATIVOS ========== */
        .login-galeria {
            flex:1.2; position:relative; overflow:hidden;
            background:#ffffff;
        }
        .galeria-img { display:none; }

        /* Contenedor del fondo educativo dinámico */
        .edu-bg-pattern {
            position:absolute; top:0; left:0; width:100%; height:100%;
            z-index:1; overflow:hidden; pointer-events:none;
        }

        /* --- 1. Círculos con emojis (10 elementos) --- */
        .edu-circle {
            position:absolute;
            width:65px; height:65px;
            border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:1.6rem;
            pointer-events:none;
            animation: circle-float var(--dur, 14s) ease-in-out infinite;
            animation-delay: var(--delay, 0s);
        }
        @keyframes circle-float {
            0%, 100% { transform:translateY(0) rotate(0deg) scale(1); }
            25%  { transform:translateY(-18px) rotate(4deg) scale(1.03); }
            50%  { transform:translateY(-28px) rotate(-2deg) scale(1.06); }
            75%  { transform:translateY(-12px) rotate(3deg) scale(1.02); }
        }

        /* Variantes de color para círculos */
        .edu-c-green  { background:rgba(16,185,129,0.08); border:1.5px solid rgba(16,185,129,0.12); }
        .edu-c-teal   { background:rgba(20,184,166,0.07); border:1.5px solid rgba(20,184,166,0.10); }
        .edu-c-blue   { background:rgba(59,130,246,0.06); border:1.5px solid rgba(59,130,246,0.09); }
        .edu-c-amber  { background:rgba(245,158,11,0.06); border:1.5px solid rgba(245,158,11,0.09); }
        .edu-c-rose   { background:rgba(244,63,94,0.05); border:1.5px solid rgba(244,63,94,0.08); }

        /* --- 2. Emojis grandes flotantes translúcidos (7) --- */
        .edu-big-float {
            position:absolute;
            font-size:3.5rem;
            opacity:0.10;
            filter:blur(1.5px);
            pointer-events:none;
            animation: big-float var(--dur, 20s) ease-in-out infinite;
            animation-delay: var(--delay, 0s);
        }
        @keyframes big-float {
            0%, 100% {
                transform:translateY(0) rotate(var(--rot, 0deg)) scale(1);
                opacity:0.08;
            }
            50% {
                transform:translateY(-35px) rotate(calc(var(--rot, 0deg) + 8deg)) scale(1.08);
                opacity:0.16;
            }
        }

        /* --- 3. Círculos decorativos animados (3) --- */
        .edu-deco-circle {
            position:absolute;
            border-radius:50%;
            pointer-events:none;
            animation: deco-drift var(--dur, 22s) ease-in-out infinite;
            animation-delay: var(--delay, 0s);
        }
        @keyframes deco-drift {
            0%, 100% { transform:translate(0, 0) scale(1); opacity:0.6; }
            33%  { transform:translate(15px, -20px) scale(1.05); opacity:0.9; }
            66%  { transform:translate(-10px, -35px) scale(1.1); opacity:0.7; }
        }

        /* Overlay semi-transparente para que logo y frases se lean bien */
        .galeria-overlay {
            position:absolute; top:0; left:0; width:100%; height:100%;
            background: linear-gradient(180deg,
                rgba(255,255,255,0.88) 0%,
                rgba(255,255,255,0.72) 40%,
                rgba(255,255,255,0.80) 70%,
                rgba(255,255,255,0.90) 100%
            );
            display:flex; flex-direction:column;
            justify-content:space-between;
            padding:2.5rem; z-index:2;
        }

        /* Logo galería — ahora sobre fondo blanco */
        .galeria-logo {
            display:flex; justify-content:center; align-items:center; z-index:4;
            animation: entradaArriba 0.8s ease forwards;
        }
        .logo-galeria {
            width:260px; max-width:70%;
            filter: drop-shadow(0 4px 15px rgba(5,150,105,0.15));
            transition: transform 0.4s ease;
        }
        .logo-galeria:hover { transform:scale(1.04); }

        /* ========== FRASES MOTIVACIONALES — para fondo blanco ========== */
        .galeria-frases {
            z-index:4; min-height:130px; position:relative;
        }
        .frase-item {
            position:absolute; bottom:0; left:0; width:100%;
            opacity:0; transform:translateY(15px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }
        .frase-item.activa {
            opacity:1; transform:translateY(0);
        }
        .frase-item h2 {
            font-size:1.85rem; font-weight:800; color:#022C22; line-height:1.3;
            margin-bottom:0.5rem;
        }
        .frase-item p {
            color:#6B7280; font-size:0.85rem; font-style:italic;
        }
        .frase-barra {
            width:50px; height:3px;
            background:rgba(5,150,105,0.15);
            border-radius:3px; margin-top:0.8rem;
            overflow:hidden;
        }
        .frase-barra-fill {
            height:100%; width:0%;
            background: linear-gradient(90deg, #059669, #10B981);
            border-radius:3px;
        }

        /* Galería dots */
        .galeria-dots {
            display:flex; gap:0.5rem; z-index:4;
            animation: entradaAbajo 0.8s ease 0.4s forwards; opacity:0;
        }
        .dot {
            width:8px; height:8px; border-radius:50%;
            background:rgba(5,150,105,0.2); cursor:pointer;
            transition:all 0.4s ease;
        }
        .dot-activo {
            background:#059669; width:28px; border-radius:4px;
            box-shadow:0 0 8px rgba(5,150,105,0.3);
        }

        /* ========== FORMULARIO (derecha) ========== */
        .login-form-side {
            flex:0.85; display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            background: linear-gradient(180deg, #022C22 0%, #064E3B 50%, #022C22 100%);
            padding:2rem; position:relative; overflow:hidden;
        }
        .login-form-side::before {
            content:''; position:absolute; top:-100px; right:-100px;
            width:300px; height:300px;
            background:radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 70%);
            border-radius:50%; pointer-events:none;
            animation: pulse-slow 6s ease-in-out infinite;
        }
        .login-form-side::after {
            content:''; position:absolute; bottom:-80px; left:-80px;
            width:250px; height:250px;
            background:radial-gradient(circle, rgba(110,231,183,0.08) 0%, transparent 70%);
            border-radius:50%; pointer-events:none;
            animation: pulse-slow 8s ease-in-out infinite reverse;
        }
        @keyframes pulse-slow {
            0%, 100% { transform:scale(1); opacity:0.8; }
            50% { transform:scale(1.1); opacity:1; }
        }

        .login-container {
            width:100%; max-width:400px; position:relative; z-index:1;
            animation: entradaDerecha 0.6s ease forwards;
        }

        /* Glass card — verde oscuro */
        .glass-card {
            background:rgba(2,44,34,0.85);
            backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
            border:1px solid rgba(16,185,129,0.2);
            border-radius:24px; padding:2.5rem 2rem;
            box-shadow: 0 8px 40px rgba(0,0,0,0.3), 0 2px 12px rgba(0,0,0,0.2);
            animation: aparecer 0.6s ease 0.15s forwards; opacity:0;
        }

        /* Logo centrado */
        .login-logo { text-align:center; margin-bottom:1.2rem; }

        .logo-container {
            display:inline-flex;
            align-items:center; justify-content:center;
            width:100px; height:100px;
            border-radius:24px;
            background: linear-gradient(145deg, rgba(16,185,129,0.25), rgba(5,150,105,0.15));
            box-shadow:
                0 4px 20px rgba(16,185,129,0.25),
                0 1px 3px rgba(0,0,0,0.2),
                inset 0 1px 0 rgba(255,255,255,0.08);
            margin-bottom:1rem;
            position:relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .logo-container:hover {
            transform:translateY(-3px);
            box-shadow:
                0 8px 30px rgba(16,185,129,0.4),
                0 2px 6px rgba(0,0,0,0.3),
                inset 0 1px 0 rgba(255,255,255,0.12);
        }
        .logo-container::before {
            content:'';
            position:absolute; inset:-3px;
            border-radius:26px;
            background: linear-gradient(135deg, #10B981, #047857, #10B981);
            z-index:-1;
            opacity:0.25;
            transition: opacity 0.3s;
        }
        .logo-container:hover::before { opacity:0.4; }

        .logo-container img {
            width:65px;
            filter: drop-shadow(0 2px 6px rgba(5,150,105,0.2));
        }

        .login-titulo {
            font-size:1.4rem; font-weight:800; color:#ffffff;
            margin-bottom:0.2rem;
        }
        .login-subtitulo {
            color:rgba(255,255,255,0.55); font-size:0.88rem;
        }

        /* Fecha/hora */
        .login-datetime {
            display:flex; align-items:center; justify-content:center;
            gap:0.5rem;
            background:rgba(16,185,129,0.12);
            padding:0.5rem 1rem; border-radius:10px;
            margin: 1.2rem 0 1.5rem;
            font-size:0.8rem; color:rgba(255,255,255,0.65); font-weight:500;
        }
        .login-datetime .reloj {
            font-weight:700; font-variant-numeric:tabular-nums;
        }

        /* Campos */
        .campo-login { margin-bottom:1.2rem; }
        .campo-login label {
            display:block; font-size:0.75rem; font-weight:700;
            color:rgba(255,255,255,0.55); margin-bottom:0.4rem;
            text-transform:uppercase; letter-spacing:0.8px;
        }
        .input-wrapper { position:relative; }
        .input-wrapper input {
            width:100%; padding:0.85rem 1rem 0.85rem 2.8rem;
            border:2px solid rgba(16,185,129,0.25); border-radius:12px;
            font-size:0.95rem; transition:all 0.3s ease;
            outline:none; background:rgba(0,0,0,0.25);
            color:#ffffff; box-sizing:border-box;
        }
        .input-wrapper input:focus {
            border-color:#10B981;
            box-shadow:0 0 0 4px rgba(16,185,129,0.15);
            background:rgba(0,0,0,0.35);
        }
        .input-wrapper input::placeholder { color:rgba(255,255,255,0.35); }
        .input-wrapper .input-icon {
            position:absolute; left:0.9rem; top:50%; transform:translateY(-50%);
            font-size:1rem; opacity:0.5; pointer-events:none; transition:opacity 0.3s; color:rgba(255,255,255,0.5);
        }
        .input-wrapper input:focus ~ .input-icon { opacity:0.8; }
        .toggle-pass {
            position:absolute; right:1rem; top:50%; transform:translateY(-50%);
            cursor:pointer; font-size:1rem; opacity:0.5;
            transition:opacity 0.2s; z-index:2; color:rgba(255,255,255,0.5);
        }
        .toggle-pass:hover { opacity:0.8; }

        /* Botón */
        .btn-login-premium {
            width:100%; padding:1rem;
            background: linear-gradient(135deg, #059669, #10B981);
            color:white; border:none; border-radius:12px;
            font-size:1rem; font-weight:700; cursor:pointer;
            transition:all 0.3s; margin-top:0.5rem;
            letter-spacing:0.5px; position:relative; overflow:hidden;
        }
        .btn-login-premium:hover {
            background: linear-gradient(135deg, #047857, #059669);
            transform:translateY(-2px);
            box-shadow:0 8px 25px rgba(5,150,105,0.45);
        }
        .btn-login-premium:active { transform:translateY(0); }
        .btn-login-premium::after {
            content:''; position:absolute; top:-50%; left:-100%;
            width:60%; height:200%;
            background:linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transform:skewX(-20deg); transition:left 0.5s;
        }
        .btn-login-premium:hover::after { left:120%; }

        /* Alertas */
        .alerta-login {
            padding:0.8rem 1rem; border-radius:10px; margin-bottom:1.2rem;
            font-size:0.85rem; display:flex; align-items:center;
            gap:0.5rem; animation:shakeAlert 0.5s ease;
        }
        .alerta-login.error {
            background:#FEF2F2; color:#DC2626;
            border:1px solid #FECACA;
        }
        .alerta-login.sesion {
            background:#FFFBEB; color:#B45309;
            border:1px solid #FDE68A;
        }
        @keyframes shakeAlert {
            0%,100% { transform:translateX(0); }
            20% { transform:translateX(-6px); }
            40% { transform:translateX(6px); }
            60% { transform:translateX(-3px); }
            80% { transform:translateX(3px); }
        }

        /* Divider */
        .login-divider {
            display:flex; align-items:center; gap:1rem;
            margin:1.5rem 0; color:rgba(255,255,255,0.25);
            font-size:0.72rem; text-transform:uppercase; letter-spacing:1.5px;
        }
        .login-divider::before, .login-divider::after {
            content:''; flex:1; height:1px;
            background:linear-gradient(90deg, transparent, rgba(16,185,129,0.25), transparent);
        }

        /* Footer */
        .login-footer-info {
            text-align:center; font-size:0.78rem; color:rgba(255,255,255,0.45); line-height:1.7;
        }
        .footer-contacto {
            margin-top:0.8rem; padding-top:0.8rem;
            border-top:1px solid rgba(16,185,129,0.15);
            display:flex; justify-content:center; gap:1.5rem; flex-wrap:wrap;
        }
        .footer-contacto span {
            display:flex; align-items:center; gap:0.3rem;
            font-size:0.75rem; color:rgba(255,255,255,0.35);
        }
        .footer-contacto a span {
            transition: color 0.2s ease;
        }
        .footer-contacto a:hover span {
            color:#10B981;
        }
        .login-copyright {
            text-align:center; padding:1rem 0; font-size:0.7rem;
            color:rgba(255,255,255,0.2); margin-top:1.5rem; position:relative; z-index:1;
        }

        /* ========== ANIMACIONES ========== */
        @keyframes entradaArriba {
            from { opacity:0; transform:translateY(-20px); }
            to { opacity:1; transform:translateY(0); }
        }
        @keyframes entradaAbajo {
            from { opacity:0; transform:translateY(20px); }
            to { opacity:1; transform:translateY(0); }
        }
        @keyframes entradaDerecha {
            from { opacity:0; transform:translateX(30px); }
            to { opacity:1; transform:translateX(0); }
        }
        @keyframes aparecer {
            from { opacity:0; transform:scale(0.95); }
            to { opacity:1; transform:scale(1); }
        }

        /* Onda separadora entre galería y formulario */
        .onda-svg { display:none; }

        /* ========== RESPONSIVE ========== */
        @media (max-width:768px) {
            .login-wrapper { flex-direction: column; }
            .login-galeria {
                display:none;
            }
            .login-form-side {
                flex:1;
                padding: 1.5rem;
                justify-content: center;
                min-height: 100vh;
            }
            .glass-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
            .login-titulo { font-size: 1.3rem; }
            .logo-container {
                width: 75px;
                height: 75px;
                border-radius: 18px;
            }
            .logo-container img { width: 45px; }
            .footer-contacto {
                flex-direction: column;
                gap: 0.5rem;
                align-items: center;
            }
            .login-datetime {
                flex-wrap: wrap;
                justify-content: center;
                text-align: center;
            }
            .campo-login { margin-bottom: 1rem; }
            .input-wrapper input {
                padding: 0.9rem 1rem 0.9rem 2.5rem;
                font-size: 1rem;
            }
            .btn-login-premium {
                padding: 0.9rem 1.5rem;
                font-size: 1rem;
            }
        }

        @media (max-width:480px) {
            .login-form-side {
                padding: 1rem;
                padding-top: 1.5rem;
            }
            .glass-card {
                padding: 1.5rem 1.2rem;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(5,150,105,0.1);
            }
            .login-datetime {
                font-size: 0.7rem;
                padding: 0.4rem 0.8rem;
            }
            .login-titulo { font-size: 1.15rem; }
            .login-subtitulo { font-size: 0.82rem; }
            .logo-container {
                width: 65px;
                height: 65px;
                border-radius: 16px;
            }
            .logo-container img { width: 40px; }
            .input-wrapper input {
                padding: 0.8rem 1rem 0.8rem 2.5rem;
                font-size: 0.95rem;
                border-radius: 10px;
            }
            .campo-login label {
                font-size: 0.7rem;
            }
            .btn-login-premium {
                padding: 0.85rem 1rem;
                border-radius: 10px;
            }
            .login-divider {
                font-size: 0.65rem;
                margin: 1.2rem 0;
            }
            .login-footer-info {
                font-size: 0.72rem;
            }
            .footer-contacto span {
                font-size: 0.7rem;
            }
            .login-copyright {
                font-size: 0.6rem;
                margin-top: 1rem;
            }
        }

        /* Pantallas muy pequeñas */
        @media (max-width:360px) {
            .glass-card {
                padding: 1.2rem 1rem;
            }
            .login-titulo {
                font-size: 1.05rem;
            }
            .input-wrapper input {
                padding: 0.75rem 0.75rem 0.75rem 2.2rem;
            }
        }
    </style>
</head>
<body class="login-body">

<!-- ===== FONDO VERDE INSTITUCIONAL ===== -->
<div class="espacio-fondo" id="espacio-fondo">
    <div class="estrellas-container" id="estrellas"></div>
    <svg class="constelacion" style="top:5%; left:10%; width:200px; height:150px;" viewBox="0 0 200 150">
        <line x1="30" y1="40" x2="70" y2="20"/>
        <line x1="70" y1="20" x2="100" y2="50"/>
        <line x1="100" y1="50" x2="140" y2="30"/>
        <line x1="140" y1="30" x2="170" y2="60"/>
        <circle cx="30" cy="40" r="2.5"/>
        <circle cx="70" cy="20" r="2"/>
        <circle cx="100" cy="50" r="3"/>
        <circle cx="140" cy="30" r="2"/>
        <circle cx="170" cy="60" r="2.5"/>
    </svg>
    <svg class="constelacion" style="top:15%; right:15%; width:180px; height:120px; animation-delay:-5s;" viewBox="0 0 180 120">
        <line x1="20" y1="60" x2="60" y2="30"/>
        <line x1="60" y1="30" x2="90" y2="50"/>
        <line x1="90" y1="50" x2="130" y2="20"/>
        <circle cx="20" cy="60" r="2"/>
        <circle cx="60" cy="30" r="3"/>
        <circle cx="90" cy="50" r="2.5"/>
        <circle cx="130" cy="20" r="2"/>
    </svg>
    <div class="planeta" style="top:10%; right:25%; width:25px; height:25px; background:linear-gradient(135deg, #10B981, #6EE7B7); --duracion:25s;"></div>
    <div class="planeta" style="bottom:15%; right:10%; width:18px; height:18px; background:linear-gradient(135deg, #059669, #A7F3D0); --duracion:20s; animation-delay:-8s;"></div>
</div>

<!-- ===== PANTALLA DE CARGA ===== -->
<div class="loader-screen" id="loader-screen">
    <div class="loader-logo-container">
        <img src="img/Logo.png" alt="INTEP">
        <div class="loader-ring-outer"></div>
        <div class="loader-ring-inner"></div>
    </div>
    <div class="loader-progress">
        <div class="loader-progress-bar"></div>
    </div>
    <span class="loader-text">Ingresando al portal</span>
    <span class="loader-welcome" id="loader-welcome">Bienvenido(a) 👋</span>
</div>

<div class="login-wrapper">

    <!-- ===== GALERÍA CON MOTIVOS EDUCATIVOS ===== -->
    <div class="login-galeria">
        <!-- Fondo dinámico educativo -->
        <div class="edu-bg-pattern" id="edu-bg-pattern">

            <!-- 3 Círculos decorativos animados -->
            <div class="edu-deco-circle" style="top:-40px; right:-60px; width:280px; height:280px; background:radial-gradient(circle, rgba(16,185,129,0.09) 0%, transparent 70%); --dur:20s; --delay:0s;"></div>
            <div class="edu-deco-circle" style="bottom:5%; left:-50px; width:220px; height:220px; background:radial-gradient(circle, rgba(5,150,105,0.07) 0%, transparent 70%); --dur:26s; --delay:-4s;"></div>
            <div class="edu-deco-circle" style="top:40%; right:15%; width:160px; height:160px; background:radial-gradient(circle, rgba(16,185,129,0.06) 0%, transparent 70%); --dur:22s; --delay:-8s;"></div>

            <!-- 10 Círculos con emojis educativos -->
            <div class="edu-circle edu-c-green" style="top:4%; left:10%; --dur:13s; --delay:0s;">📚</div>
            <div class="edu-circle edu-c-amber" style="top:14%; left:70%; --dur:16s; --delay:1.2s;">✏️</div>
            <div class="edu-circle edu-c-blue" style="top:28%; left:35%; --dur:14s; --delay:0.5s;">💻</div>
            <div class="edu-circle edu-c-teal" style="top:38%; left:78%; --dur:17s; --delay:2.5s;">🎓</div>
            <div class="edu-circle edu-c-rose" style="top:50%; left:12%; --dur:15s; --delay:1.8s;">🏆</div>
            <div class="edu-circle edu-c-green" style="top:58%; left:58%; --dur:13s; --delay:3.2s;">📖</div>
            <div class="edu-circle edu-c-amber" style="top:68%; left:30%; --dur:18s; --delay:0.8s;">📝</div>
            <div class="edu-circle edu-c-blue" style="top:76%; left:82%; --dur:14s; --delay:2s;">🔬</div>
            <div class="edu-circle edu-c-teal" style="top:85%; left:18%; --dur:16s; --delay:4s;">🎒</div>
            <div class="edu-circle edu-c-rose" style="top:92%; left:55%; --dur:15s; --delay:1s;">📐</div>

            <!-- 7 Emojis grandes flotantes translúcidos -->
            <span class="edu-big-float" style="top:3%; left:50%; --dur:22s; --delay:0s; --rot:5deg;">🎓</span>
            <span class="edu-big-float" style="top:20%; left:5%; --dur:19s; --delay:1.5s; --rot:-4deg;">💡</span>
            <span class="edu-big-float" style="top:35%; left:65%; --dur:24s; --delay:3s; --rot:7deg;">🌟</span>
            <span class="edu-big-float" style="top:48%; left:40%; --dur:20s; --delay:0.8s; --rot:-6deg;">✍️</span>
            <span class="edu-big-float" style="top:62%; left:75%; --dur:21s; --delay:2.2s; --rot:4deg;">🎯</span>
            <span class="edu-big-float" style="top:75%; left:8%; --dur:23s; --delay:4s; --rot:-3deg;">🏫</span>
            <span class="edu-big-float" style="top:88%; left:48%; --dur:18s; --delay:1s; --rot:6deg;">📚</span>
        </div>

        <?php foreach ($imagenes as $i => $img): ?>
            <img src="<?php echo $img; ?>" class="galeria-img <?php echo $i === 0 ? 'activa' : ''; ?>" alt="INTEP">
        <?php endforeach; ?>

        <div class="galeria-overlay">
            <div class="galeria-logo">
                <a href="https://institutointep.edu.co/" target="_blank" rel="noopener">
                    <img src="img/Logo.png" alt="Logo INTEP" class="logo-galeria">
                </a>
            </div>

            <!-- Frases motivacionales -->
            <div class="galeria-frases" id="galeria-frases">
                <div class="frase-item activa">
                    <h2>Formamos el talento<br>que construye el futuro</h2>
                    <p>— Misión INTEP</p>
                    <div class="frase-barra"><div class="frase-barra-fill"></div></div>
                </div>
                <div class="frase-item">
                    <h2>La educación es el arma<br>más poderosa del mundo</h2>
                    <p>— Nelson Mandela</p>
                    <div class="frase-barra"><div class="frase-barra-fill"></div></div>
                </div>
                <div class="frase-item">
                    <h2>El conocimiento es<br>la inversión que más rinde</h2>
                    <p>— Benjamin Franklin</p>
                    <div class="frase-barra"><div class="frase-barra-fill"></div></div>
                </div>
                <div class="frase-item">
                    <h2>Cada día es una<br>oportunidad para aprender</h2>
                    <p>— Tu futuro empieza hoy</p>
                    <div class="frase-barra"><div class="frase-barra-fill"></div></div>
                </div>
                <div class="frase-item">
                    <h2>Enseñar es dejar<br>una huella en la vida</h2>
                    <p>— Vocación pedagógica</p>
                    <div class="frase-barra"><div class="frase-barra-fill"></div></div>
                </div>
                <div class="frase-item">
                    <h2>Tu esfuerzo de hoy<br>será tu orgullo mañana</h2>
                    <p>— Sigue adelante</p>
                    <div class="frase-barra"><div class="frase-barra-fill"></div></div>
                </div>
            </div>

            <div class="galeria-dots" id="galeria-dots"></div>
        </div>

        <svg class="onda-svg" viewBox="0 0 1440 120" preserveAspectRatio="none" style="height:60px; position:absolute; bottom:0; left:0; width:100%; z-index:3;">
            <path d="M0,80 C360,120 720,40 1080,80 C1260,100 1380,60 1440,80 L1440,120 L0,120 Z" fill="rgba(2,44,34,0.4)"/>
            <path d="M0,90 C300,110 600,50 900,90 C1100,110 1300,70 1440,90 L1440,120 L0,120 Z" fill="rgba(2,44,34,0.6)"/>
        </svg>
    </div>

    <!-- ===== FORMULARIO ===== -->
    <div class="login-form-side">
        <div class="login-container">
            <div class="glass-card">

                <!-- Logo centrado con marco elegante -->
                <div class="login-logo">
                    <a href="https://institutointep.edu.co/" target="_blank" rel="noopener" class="logo-container">
                        <img src="img/Logo.png" alt="INTEP">
                    </a>
                    <h1 class="login-titulo">Portal Estudiantil</h1>
                    <p class="login-subtitulo">Ingresa con tus credenciales</p>
                </div>

                <!-- Fecha y hora -->
                <div class="login-datetime">
                    <span>📆 <?php echo $fecha_hoy; ?></span>
                    <span>·</span>
                    <span class="reloj" id="reloj-login">--:--</span>
                </div>

                <?php if (isset($_GET['sesion']) && $_GET['sesion'] === 'expirada'): ?>
                    <div class="alerta-login sesion">
                        <span>⏱</span> Tu sesión cerró por inactividad.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alerta-login error">
                        <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="off" id="form-login">
                    <div class="campo-login">
                        <label>Usuario</label>
                        <div class="input-wrapper">
                            <input type="text" name="username" placeholder="Número de documento" required autofocus
                                   value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                            <span class="input-icon">👤</span>
                        </div>
                    </div>

                    <div class="campo-login">
                        <label>Contraseña</label>
                        <div class="input-wrapper">
                            <input type="password" name="password" id="password" placeholder="Tu contraseña" required>
                            <span class="input-icon">🔒</span>
                            <span class="toggle-pass" onclick="togglePassword()">👁</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-login-premium">Ingresar →</button>
                </form>

                <div class="login-divider">acceso institucional</div>

                <div class="login-footer-info">
                    <span>¿Problemas para ingresar? Contacta a secretaría.</span>
                    <div class="footer-contacto">
                        <a href="https://maps.google.com/?q=Cl.+7+%23396,+Madrid,+Cundinamarca" target="_blank" rel="noopener" style="text-decoration:none;color:inherit;"><span>📍 Cl. 7 #396, Madrid, Cundinamarca</span></a>
                        <a href="https://wa.me/573222769962" target="_blank" rel="noopener" style="text-decoration:none;color:inherit;"><span>📞 322 276 9962</span></a>
                        <span>✉️ institutointepmadrid@gmail.com</span>
                    </div>
                </div>
            </div>

            <div class="login-copyright">
                © <?php echo date('Y'); ?> Instituto Técnico Pedagógico INTEP · Todos los derechos reservados
            </div>
        </div>
    </div>

</div>

<script>
    // ===== PARTICULAS VERDES SUTILES EN FONDO DERECHO =====
    (function() {
        var container = document.getElementById('estrellas');
        if (!container) return;
        var numParticulas = 40;

        for (var i = 0; i < numParticulas; i++) {
            var p = document.createElement('div');
            p.style.cssText = 'position:absolute;border-radius:50%;';
            p.style.left = Math.random() * 100 + '%';
            p.style.top = Math.random() * 100 + '%';
            var size = Math.random() * 2 + 0.5;
            p.style.width = size + 'px';
            p.style.height = size + 'px';
            p.style.background = 'rgba(167,243,208,' + (Math.random() * 0.3 + 0.1) + ')';
            p.style.animation = 'pulse-star ' + (Math.random() * 3 + 2) + 's ease-in-out infinite';
            p.style.animationDelay = (Math.random() * 5) + 's';
            container.appendChild(p);
        }
    })();

    // ===== ELEMENTOS EDUCATIVOS DINÁMICOS EN GALERÍA BLANCA =====
    (function() {
        var galeria = document.getElementById('edu-bg-pattern');
        if (!galeria) return;

        var iconosEdu = ['📚','✏️','💻','🎓','🏆','📖','📝','🔬','🎒','📐','💡','🌟','✍️','🎯','🏫','🧪','📕','🧮','🎨','🌍'];
        var colores = ['edu-c-green','edu-c-teal','edu-c-blue','edu-c-amber','edu-c-rose'];
        var maxElementos = 15;
        var elementosActivos = 0;

        function crearCirculoFlotante() {
            if (elementosActivos >= maxElementos) return;
            elementosActivos++;

            var div = document.createElement('div');
            div.className = 'edu-circle ' + colores[Math.floor(Math.random() * colores.length)];
            div.textContent = iconosEdu[Math.floor(Math.random() * iconosEdu.length)];
            div.style.top = (Math.random() * 85 + 3) + '%';
            div.style.left = (Math.random() * 80 + 5) + '%';
            div.style.setProperty('--dur', (Math.random() * 6 + 12) + 's');
            div.style.setProperty('--delay', '0s');
            div.style.opacity = '0';
            div.style.transition = 'opacity 1.5s ease';
            galeria.appendChild(div);

            // Fade in
            requestAnimationFrame(function() {
                div.style.opacity = '1';
            });

            // Remover después de un ciclo
            var vida = (Math.random() * 12000 + 16000);
            setTimeout(function() {
                div.style.opacity = '0';
                setTimeout(function() {
                    div.remove();
                    elementosActivos--;
                }, 1500);
            }, vida);
        }

        function crearEmojiGrande() {
            var span = document.createElement('span');
            span.className = 'edu-big-float';
            span.textContent = iconosEdu[Math.floor(Math.random() * iconosEdu.length)];
            span.style.top = (Math.random() * 80 + 5) + '%';
            span.style.left = (Math.random() * 75 + 5) + '%';
            span.style.setProperty('--dur', (Math.random() * 8 + 16) + 's');
            span.style.setProperty('--delay', '0s');
            span.style.setProperty('--rot', (Math.random() * 20 - 10) + 'deg');
            span.style.fontSize = (Math.random() * 1.5 + 2.8) + 'rem';
            span.style.opacity = '0';
            span.style.transition = 'opacity 2s ease';
            galeria.appendChild(span);

            requestAnimationFrame(function() {
                span.style.opacity = '1';
            });

            setTimeout(function() {
                span.style.opacity = '0';
                setTimeout(function() { span.remove(); }, 2000);
            }, Math.random() * 15000 + 20000);
        }

        // Crear elementos iniciales escalonados
        for (var i = 0; i < 4; i++) {
            setTimeout(crearCirculoFlotante, i * 600);
        }
        for (var j = 0; j < 2; j++) {
            setTimeout(crearEmojiGrande, j * 1200 + 300);
        }

        // Crear nuevos elementos cada 8 segundos
        setInterval(function() {
            crearCirculoFlotante();
            if (Math.random() > 0.5) crearEmojiGrande();
        }, 8000);
    })();

    // ===== PANTALLA DE CARGA AL LOGIN EXITOSO =====
    <?php if ($loginExitoso): ?>
    (function() {
        var loader = document.getElementById('loader-screen');
        var welcome = document.getElementById('loader-welcome');
        welcome.textContent = 'Bienvenido(a), <?php echo addslashes($_SESSION['usuario_nombre']); ?> 👋';
        loader.classList.add('activo');
        setTimeout(function() {
            window.location.href = 'dashboard.php';
        }, 2200);
    })();
    <?php endif; ?>

    // ===== GALERÍA =====
    var imgs = document.querySelectorAll('.galeria-img');
    var dotsC = document.getElementById('galeria-dots');
    var actualImg = 0, intGal;

    imgs.forEach(function(_, i) {
        var dot = document.createElement('span');
        dot.className = 'dot' + (i === 0 ? ' dot-activo' : '');
        dot.onclick = function() { cambiarImg(i); reiniciarGal(); };
        dotsC.appendChild(dot);
    });

    function cambiarImg(idx) {
        imgs.forEach(function(img) { img.classList.remove('activa'); });
        document.querySelectorAll('.dot').forEach(function(d) { d.classList.remove('dot-activo'); });
        if (imgs[idx]) {
            imgs[idx].style.transform = 'scale(1.08)';
            void imgs[idx].offsetWidth;
            imgs[idx].classList.add('activa');
        }
        var dots = document.querySelectorAll('.dot');
        if (dots[idx]) dots[idx].classList.add('dot-activo');
        actualImg = idx;
    }

    function reiniciarGal() {
        clearInterval(intGal);
        intGal = setInterval(function() {
            cambiarImg((actualImg + 1) % imgs.length);
        }, 5000);
    }

    cambiarImg(0);
    reiniciarGal();

    // ===== FRASES ROTATIVAS =====
    var frases = document.querySelectorAll('.frase-item');
    var actualFrase = 0;
    var DURACION = 6000;

    function iniciarBarra(idx) {
        var barra = frases[idx].querySelector('.frase-barra-fill');
        if (!barra) return;
        barra.style.transition = 'none';
        barra.style.width = '0%';
        void barra.offsetWidth;
        barra.style.transition = 'width ' + (DURACION / 1000) + 's linear';
        barra.style.width = '100%';
    }

    function cambiarFrase() {
        frases.forEach(function(f) { f.classList.remove('activa'); });
        actualFrase = (actualFrase + 1) % frases.length;
        frases[actualFrase].classList.add('activa');
        iniciarBarra(actualFrase);
    }

    iniciarBarra(0);
    setInterval(cambiarFrase, DURACION);

    // ===== RELOJ =====
    function actualizarReloj() {
        var ahora = new Date();
        var h = ahora.getHours();
        var m = String(ahora.getMinutes()).padStart(2, '0');
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        document.getElementById('reloj-login').textContent = h + ':' + m + ' ' + ampm;
    }
    actualizarReloj();
    setInterval(actualizarReloj, 30000);

    // ===== CONTRASEÑA =====
    function togglePassword() {
        var input = document.getElementById('password');
        var toggle = document.querySelector('.toggle-pass');
        if (input.type === 'password') {
            input.type = 'text'; toggle.textContent = '🙈';
        } else {
            input.type = 'password'; toggle.textContent = '👁';
        }
    }
</script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/intep/sw.js');
}
</script>
</body>
</html>
