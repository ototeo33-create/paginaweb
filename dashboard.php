<?php
require_once 'config.php';

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

    $q = "SELECT e.nombre, e.documento, p.nombre as programa 
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
}

if ($rol === 'docente') {
    $doc_id = $_SESSION['usuario_id'];

    $q = "SELECT COUNT(*) as modulos FROM modulos WHERE docente_id = ?";
    $stmt = mysqli_prepare($conexion, $q);
    mysqli_stmt_bind_param($stmt, 'i', $doc_id);
    mysqli_stmt_execute($stmt);
    $stats_doc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $q2 = "SELECT COUNT(DISTINCT n.estudiante_id) as estudiantes 
           FROM notas n 
           JOIN modulos m ON n.modulo_id = m.id 
           WHERE m.docente_id = ?";
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

    $r = mysqli_query($conexion, "SELECT COUNT(*) as total FROM modulos");
    $stats_admin['modulos'] = mysqli_fetch_assoc($r)['total'];
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
           MENU CARDS — blancas sobre fondo claro
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

        /* Línea de acento superior al hacer hover */
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

        .menu-card-v2 .card-icon.verde    { background: #ECFDF5; }
        .menu-card-v2 .card-icon.azul     { background: #EFF6FF; }
        .menu-card-v2 .card-icon.amarillo { background: #FFFBEB; }
        .menu-card-v2 .card-icon.rojo     { background: #FDF2F8; }
        .menu-card-v2 .card-icon.morado   { background: #ECFDF5; }
        .menu-card-v2 .card-icon.naranja  { background: #FFF7ED; }
        .menu-card-v2 .card-icon.gris     { background: #F1F5F9; }

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

        .menu-card-v2 .card-arrow {
            position: absolute;
            top: 1.8rem;
            right: 1.5rem;
            font-size: 1.1rem;
            color: #D1D5DB;
            transition: all 0.3s;
        }

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
                grid-template-columns: 1fr; 
                gap: 1rem;
            }
            
            .menu-card-v2 {
                padding: 1.4rem;
                border-radius: 14px;
            }
            .menu-card-v2 .card-icon {
                width: 44px;
                height: 44px;
                font-size: 1.2rem;
            }
            .menu-card-v2 h3 {
                font-size: 0.95rem;
            }
            .menu-card-v2 p {
                font-size: 0.8rem;
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
                gap: 0.8rem;
            }
            
            .menu-card-v2 {
                padding: 1.2rem;
                border-radius: 12px;
            }
            .menu-card-v2 .card-icon {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
                margin-bottom: 0.8rem;
            }
            .menu-card-v2 h3 {
                font-size: 0.9rem;
            }
            .menu-card-v2 p {
                font-size: 0.75rem;
            }
            .menu-card-v2 .card-arrow {
                top: 1.2rem;
                right: 1rem;
                font-size: 0.9rem;
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
                padding: 1rem;
            }
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
            <div class="menu-grid">
                <a href="mi_foto.php" class="menu-card-v2">
                    <div class="card-icon morado">📸</div>
                    <h3>Mi Foto</h3>
                    <p>Tomate una selfie para tu perfil institucional</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="notas.php" class="menu-card-v2">
                    <div class="card-icon verde">📊</div>
                    <h3>Mis Notas</h3>
                    <p>Consulta tus calificaciones por bimestre y módulo con detalle de los 3 cortes</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="horarios.php" class="menu-card-v2">
                    <div class="card-icon azul">📅</div>
                    <h3>Mi Horario</h3>
                    <p>Consulta tu horario semanal y mensual de clases</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="mi_cartera.php" class="menu-card-v2">
                    <div class="card-icon naranja">💳</div>
                    <h3>Mi Cartera</h3>
                    <p>Consulta tus pagos, cuotas pendientes y estado de cuenta</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="solicitudes.php" class="menu-card-v2">
                    <div class="card-icon morado">📋</div>
                    <h3>Solicitudes</h3>
                    <p>Certificados, paz y salvo, reparación de equipos y más trámites</p>
                    <span class="card-arrow">→</span>
                </a>
                <a href="#" class="menu-card-v2 btn-instalar-app" onclick="instalarApp(event)" style="display:none;">
                    <div class="card-icon verde">📲</div>
                    <h3>Descargar App</h3>
                    <p>Instala la aplicación en tu celular para acceso rápido</p>
                    <span class="card-arrow">→</span>
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
                <a href="admin/cartera.php" class="menu-card-v2">
                    <div class="card-icon naranja">💳</div>
                    <h3>Cartera y Pagos</h3>
                    <p>Gestiona los pagos, cuotas y estado de cuenta de los estudiantes</p>
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

<script src="/intep/sesion.js"></script>

<!-- PWA Install -->
<script>
let deferredPrompt = null;

// Si ya está instalada (modo standalone), no mostrar botón
if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
    // App ya instalada, no hacer nada
} else {
    // Capturar evento de instalación PWA
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        // Mostrar todas las tarjetas de instalar
        document.querySelectorAll('.btn-instalar-app').forEach(function(btn) {
            btn.style.display = '';
        });
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
</body>
</html>