<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: dashboard.php');
    exit;
}

$estudiante_id = $_SESSION['estudiante_id'];
$nombre = $_SESSION['usuario_nombre'];
$mensaje = '';
$tipo_msg = '';

// Obtener datos completos del estudiante
$q_est = "SELECT e.nombre, e.documento, e.email, p.nombre as programa
          FROM estudiantes e
          JOIN programas p ON e.programa_id = p.id
          WHERE e.id = ?";
$stmt_est = mysqli_prepare($conexion, $q_est);
mysqli_stmt_bind_param($stmt_est, 'i', $estudiante_id);
mysqli_stmt_execute($stmt_est);
$info_est = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_est));

// Crear tabla si no existe
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    detalle TEXT,
    estado ENUM('pendiente','en_proceso','completada','rechazada') DEFAULT 'pendiente',
    respuesta TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
        $tipo_msg = 'error';
    } else {
        $tipo_solicitud = trim($_POST['tipo_solicitud'] ?? '');
        $detalle = trim($_POST['detalle'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');

        if (empty($tipo_solicitud)) {
            $mensaje = 'Selecciona un tipo de solicitud.';
            $tipo_msg = 'error';
        } else {
            // Armar detalle completo
            $detalle_completo = '';
            if (!empty($ubicacion)) {
                $detalle_completo .= "Ubicación: $ubicacion\n";
            }
            if (!empty($detalle)) {
                $detalle_completo .= $detalle;
            }

            // Guardar en BD
            $sql = "INSERT INTO solicitudes (estudiante_id, tipo, detalle, estado, created_at) VALUES (?, ?, ?, 'pendiente', NOW())";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iss', $estudiante_id, $tipo_solicitud, $detalle_completo);
                if (mysqli_stmt_execute($stmt)) {

                    // Enviar correo
                    $fecha_hora = date('d/m/Y H:i');
                    $asunto = "=?UTF-8?B?" . base64_encode("[INTEP] Nueva solicitud: $tipo_solicitud") . "?=";

                    $cuerpo = "Nueva solicitud recibida desde el Portal Estudiantil INTEP\n";
                    $cuerpo .= "============================================================\n\n";
                    $cuerpo .= "DATOS DEL ESTUDIANTE\n";
                    $cuerpo .= "Nombre:    " . ($info_est['nombre'] ?? $nombre) . "\n";
                    $cuerpo .= "Documento: " . ($info_est['documento'] ?? 'N/A') . "\n";
                    $cuerpo .= "Programa:  " . ($info_est['programa'] ?? 'N/A') . "\n";
                    $cuerpo .= "Email:     " . ($info_est['email'] ?? 'N/A') . "\n\n";
                    $cuerpo .= "SOLICITUD\n";
                    $cuerpo .= "Tipo:      $tipo_solicitud\n";
                    if (!empty($ubicacion)) {
                        $cuerpo .= "Ubicacion: $ubicacion\n";
                    }
                    if (!empty($detalle)) {
                        $cuerpo .= "Detalle:   $detalle\n";
                    }
                    $cuerpo .= "\nFecha:     $fecha_hora\n";
                    $cuerpo .= "============================================================\n";
                    $cuerpo .= "Este correo fue enviado automaticamente desde el Portal INTEP.\n";

                    // Enviar con PHPMailer si está disponible, si no con mail()
                    $vendorPath = __DIR__ . '/vendor/autoload.php';
                    if (file_exists($vendorPath)) {
                        require_once $vendorPath;
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = Config::get('MAIL_HOST', 'smtp.gmail.com');
                            $mail->SMTPAuth   = true;
                            $mail->Username   = Config::get('MAIL_USER', 'institutointepmadrid@gmail.com');
                            $mail->Password   = Config::get('MAIL_PASS', '');
                            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = (int) Config::get('MAIL_PORT', 587);
                            $mail->CharSet    = 'UTF-8';

                            $mail->setFrom(Config::get('MAIL_USER', 'institutointepmadrid@gmail.com'), 'Portal INTEP');
                            $mail->addAddress('institutointepmadrid@gmail.com', 'Secretaría INTEP');
                            if (!empty($info_est['email'])) {
                                $mail->addReplyTo($info_est['email'], $info_est['nombre'] ?? '');
                            }
                            $mail->Subject = "[INTEP] Nueva solicitud: $tipo_solicitud";
                            $mail->Body    = $cuerpo;
                            $mail->send();
                        } catch (Exception $e) {
                            error_log("Error enviando correo INTEP: " . $mail->ErrorInfo);
                        }
                    } else {
                        $headers = "From: portal@intep.edu.co\r\n";
                        $headers .= "Reply-To: " . ($info_est['email'] ?? 'portal@intep.edu.co') . "\r\n";
                        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                        @mail('institutointepmadrid@gmail.com', "[INTEP] Nueva solicitud: $tipo_solicitud", $cuerpo, $headers);
                    }

                    $mensaje = 'Solicitud enviada correctamente. Recibirás respuesta en los próximos días hábiles.';
                    $tipo_msg = 'exito';
                } else {
                    $mensaje = 'Error al enviar la solicitud. Intenta de nuevo.';
                    $tipo_msg = 'error';
                }
            }
        }
    }
}

// Obtener historial de solicitudes
$solicitudes = [];
$q = "SELECT * FROM solicitudes WHERE estudiante_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conexion, $q);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $estudiante_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $solicitudes[] = $row;
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
    <title>Solicitudes – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        body { background: #F0FDF4; }

        .page-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .page-title { font-size: 1.4rem; font-weight: 800; color: #022C22; margin: 0 0 0.3rem; }
        .page-subtitle { font-size: 0.85rem; color: #9CA3AF; margin-bottom: 1.5rem; }

        /* Alertas */
        .alerta { padding: 0.9rem 1.2rem; border-radius: 10px; margin-bottom: 1.2rem; font-size: 0.88rem; font-weight: 500; }
        .alerta.exito { background: #ECFDF5; color: #065F46; border-left: 4px solid #10B981; }
        .alerta.error { background: #FEF2F2; color: #991B1B; border-left: 4px solid #EF4444; }

        /* Categorías */
        .categoria { margin-bottom: 1.5rem; }
        .categoria-titulo {
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px;
            color: #6B7280; font-weight: 700; margin-bottom: 0.8rem; padding-left: 0.2rem;
        }

        .opciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.8rem;
        }

        .opcion-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 2px solid rgba(16,185,129,0.1);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .opcion-card:hover {
            border-color: #10B981;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5,150,105,0.15);
        }

        .opcion-card.seleccionada {
            border-color: #059669;
            background: rgba(209, 250, 229, 0.9);
            box-shadow: 0 6px 20px rgba(5,150,105,0.2);
        }

        .opcion-icon { font-size: 1.3rem; margin-bottom: 0.4rem; display: block; }
        .opcion-nombre { font-size: 0.88rem; font-weight: 700; color: #022C22; }
        .opcion-desc { font-size: 0.75rem; color: #9CA3AF; margin-top: 0.2rem; }

        /* Formulario */
        .form-solicitud {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(16,185,129,0.1);
            margin-bottom: 2rem;
            display: none;
        }

        .form-solicitud.visible { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

        .form-solicitud h3 { font-size: 1rem; font-weight: 700; color: #022C22; margin: 0 0 0.3rem; }
        .form-tipo-seleccionado { font-size: 0.82rem; color: #10B981; font-weight: 600; margin-bottom: 1rem; }

        .campo { margin-bottom: 1rem; }
        .campo label { display: block; font-size: 0.82rem; font-weight: 600; color: #444; margin-bottom: 0.4rem; }
        .campo input, .campo textarea {
            width: 100%; padding: 0.7rem 1rem; border: 2px solid #E5E7EB;
            border-radius: 10px; font-size: 0.9rem; outline: none;
            transition: border-color 0.2s; box-sizing: border-box; font-family: inherit;
        }
        .campo input:focus, .campo textarea:focus { border-color: #10B981; }
        .campo textarea { min-height: 90px; resize: vertical; }
        .campo-ubicacion { display: none; }
        .campo-ubicacion.visible { display: block; }

        .btn-enviar {
            background: linear-gradient(135deg, #059669, #10B981); color: white; border: none;
            padding: 0.75rem 2rem; border-radius: 10px; font-size: 0.95rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s; width: 100%;
        }
        .btn-enviar:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(5,150,105,0.3); }
        .btn-enviar:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Historial */
        .historial-titulo {
            font-size: 0.73rem; text-transform: uppercase; letter-spacing: 1.8px;
            color: #6B7280; font-weight: 700; margin-bottom: 1rem;
        }

        .solicitud-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px; padding: 1.2rem 1.5rem; margin-bottom: 0.8rem;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.06);
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
        }

        .solicitud-card:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.1);
        }

        .solicitud-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem; }
        .solicitud-tipo { font-size: 0.9rem; font-weight: 700; color: #022C22; }

        .badge-estado {
            display: inline-block; padding: 0.2rem 0.7rem; border-radius: 20px;
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .badge-pendiente { background: rgba(255, 251, 235, 0.8); color: #92400E; }
        .badge-en_proceso { background: rgba(219, 234, 254, 0.8); color: #1D4ED8; }
        .badge-completada { background: rgba(236, 253, 245, 0.8); color: #065F46; }
        .badge-rechazada { background: rgba(254, 242, 242, 0.8); color: #991B1B; }

        .solicitud-detalle { font-size: 0.83rem; color: #6B7280; line-height: 1.5; white-space: pre-line; }
        .solicitud-fecha { font-size: 0.75rem; color: #9CA3AF; margin-top: 0.5rem; }

        .solicitud-respuesta {
            margin-top: 0.6rem; padding: 0.7rem 1rem; background: rgba(209, 250, 229, 0.8);
            border-radius: 8px; font-size: 0.82rem; color: #047857;
        }

        .sin-solicitudes { text-align: center; padding: 2rem; color: #9CA3AF; font-size: 0.9rem; }

        /* Info banner */
        .info-estudiante {
            background: white; border-left: 4px solid #10B981; border-radius: 12px;
            padding: 0.8rem 1.2rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #6B7280;
            display: flex; align-items: center; gap: 0.8rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .info-estudiante strong { color: #022C22; display: block; }

        /* Responsive */
        @media (max-width: 600px) {
            .page-wrap { padding: 1rem 0.8rem; }
            .opciones-grid { grid-template-columns: 1fr 1fr; gap: 0.6rem; }
            .opcion-card { padding: 0.8rem; }
            .opcion-nombre { font-size: 0.8rem; }
            .opcion-desc { font-size: 0.7rem; }
            .form-solicitud { padding: 1.2rem; }
            .page-title { font-size: 1.15rem; }
        }

        @media (max-width: 400px) {
            .opciones-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="dashboard-header">
        <h1>INTEP</h1>
        <span class="usuario-info">📋 Solicitudes</span>
        <a href="logout.php" class="btn-salir">Cerrar sesión</a>
    </div>

    <div class="page-wrap">

        <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

        <h1 class="page-title">📋 Solicitudes</h1>
        <p class="page-subtitle">Selecciona el tipo de solicitud que necesitas. Será enviada a secretaría.</p>

        <?php if ($info_est): ?>
        <div class="info-estudiante">
            <span>🎓</span>
            <div>
                <strong><?php echo htmlspecialchars($info_est['nombre']); ?></strong>
                <?php echo htmlspecialchars($info_est['documento']); ?> · <?php echo htmlspecialchars($info_est['programa']); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($mensaje): ?>
            <div class="alerta <?php echo $tipo_msg; ?>">
                <?php echo $tipo_msg === 'exito' ? '✅' : '⚠️'; ?> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- ===== CATEGORÍA 1: TRÁMITES ACADÉMICOS ===== -->
        <div class="categoria">
            <div class="categoria-titulo">🎓 Trámites Académicos</div>
            <div class="opciones-grid">
                <div class="opcion-card" onclick="seleccionar(this, 'Certificado de notas')" data-cat="academico">
                    <span class="opcion-icon">📊</span>
                    <div class="opcion-nombre">Certificado de notas</div>
                    <div class="opcion-desc">Certificado oficial con tus calificaciones</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Certificado de estudio')" data-cat="academico">
                    <span class="opcion-icon">📄</span>
                    <div class="opcion-nombre">Certificado de estudio</div>
                    <div class="opcion-desc">Constancia de que estás matriculado</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Constancia de matrícula')" data-cat="academico">
                    <span class="opcion-icon">📝</span>
                    <div class="opcion-nombre">Constancia de matrícula</div>
                    <div class="opcion-desc">Documento que certifica tu matrícula vigente</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Carta de recomendación académica')" data-cat="academico">
                    <span class="opcion-icon">✉️</span>
                    <div class="opcion-nombre">Carta de recomendación</div>
                    <div class="opcion-desc">Recomendación académica institucional</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Certificado de asistencia')" data-cat="academico">
                    <span class="opcion-icon">📅</span>
                    <div class="opcion-nombre">Certificado de asistencia</div>
                    <div class="opcion-desc">Constancia de asistencia a clases</div>
                </div>
            </div>
        </div>

        <!-- ===== CATEGORÍA 2: TRÁMITES ADMINISTRATIVOS ===== -->
        <div class="categoria">
            <div class="categoria-titulo">🏛️ Trámites Administrativos</div>
            <div class="opciones-grid">
                <div class="opcion-card" onclick="seleccionar(this, 'Constancia de paz y salvo')" data-cat="admin">
                    <span class="opcion-icon">✅</span>
                    <div class="opcion-nombre">Paz y salvo</div>
                    <div class="opcion-desc">Constancia de estar al día financieramente</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Duplicado de carnet estudiantil')" data-cat="admin">
                    <span class="opcion-icon">🪪</span>
                    <div class="opcion-nombre">Duplicado de carnet</div>
                    <div class="opcion-desc">Reposición de tu carnet estudiantil</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Retiro de documentos')" data-cat="admin">
                    <span class="opcion-icon">📁</span>
                    <div class="opcion-nombre">Retiro de documentos</div>
                    <div class="opcion-desc">Solicita la devolución de tus documentos</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Acuerdo de pago')" data-cat="admin">
                    <span class="opcion-icon">🤝</span>
                    <div class="opcion-nombre">Acuerdo de pago</div>
                    <div class="opcion-desc">Solicita un plan de pagos para tus cuotas</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Carta laboral de práctica')" data-cat="admin">
                    <span class="opcion-icon">💼</span>
                    <div class="opcion-nombre">Carta laboral de práctica</div>
                    <div class="opcion-desc">Carta para presentar en tu lugar de práctica</div>
                </div>
            </div>
        </div>

        <!-- ===== CATEGORÍA 3: REPARACIÓN Y MANTENIMIENTO ===== -->
        <div class="categoria">
            <div class="categoria-titulo">🔧 Reparación y Mantenimiento de Equipos</div>
            <div class="opciones-grid">
                <div class="opcion-card" onclick="seleccionar(this, 'Reparación - Computador', true)" data-cat="reparacion">
                    <span class="opcion-icon">💻</span>
                    <div class="opcion-nombre">Computador</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Reparación - Impresora', true)" data-cat="reparacion">
                    <span class="opcion-icon">🖨️</span>
                    <div class="opcion-nombre">Impresora</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Reparación - Proyector', true)" data-cat="reparacion">
                    <span class="opcion-icon">📽️</span>
                    <div class="opcion-nombre">Proyector</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Reparación - Teclado o Mouse', true)" data-cat="reparacion">
                    <span class="opcion-icon">⌨️</span>
                    <div class="opcion-nombre">Teclado o Mouse</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Reparación - Red o Internet', true)" data-cat="reparacion">
                    <span class="opcion-icon">🌐</span>
                    <div class="opcion-nombre">Red o Internet</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Reparación - Mobiliario', true)" data-cat="reparacion">
                    <span class="opcion-icon">🪑</span>
                    <div class="opcion-nombre">Mobiliario</div>
                </div>
                <div class="opcion-card" onclick="seleccionar(this, 'Reparación - Otro equipo', true)" data-cat="reparacion">
                    <span class="opcion-icon">🔧</span>
                    <div class="opcion-nombre">Otro equipo</div>
                </div>
            </div>
        </div>

        <!-- ===== FORMULARIO ===== -->
        <div class="form-solicitud" id="form-panel">
            <h3>📨 Enviar Solicitud</h3>
            <div class="form-tipo-seleccionado" id="tipo-display"></div>
            <form method="POST" id="form-enviar">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="enviar_solicitud" value="1">
                <input type="hidden" name="tipo_solicitud" id="tipo_solicitud" value="">

                <div class="campo campo-ubicacion" id="campo-ubicacion">
                    <label>Ubicación / Aula</label>
                    <input type="text" name="ubicacion" placeholder="Ej: Sala de cómputo 2, Aula 301...">
                </div>

                <div class="campo">
                    <label>Observaciones o detalles (opcional)</label>
                    <textarea name="detalle" placeholder="Especifica si necesitas algo particular, cantidad de copias, dirigido a quién, etc."></textarea>
                </div>

                <button type="submit" class="btn-enviar">📨 Enviar Solicitud</button>
            </form>
        </div>

        <!-- ===== HISTORIAL ===== -->
        <?php if (!empty($solicitudes)): ?>
            <div class="historial-titulo">📋 Mis solicitudes anteriores</div>
            <?php foreach ($solicitudes as $sol): ?>
                <div class="solicitud-card">
                    <div class="solicitud-header">
                        <span class="solicitud-tipo"><?php echo htmlspecialchars($sol['tipo']); ?></span>
                        <span class="badge-estado badge-<?php echo $sol['estado']; ?>">
                            <?php
                            switch ($sol['estado']) {
                                case 'pendiente':   echo 'Pendiente'; break;
                                case 'en_proceso':  echo 'En proceso'; break;
                                case 'completada':  echo 'Completada'; break;
                                case 'rechazada':   echo 'Rechazada'; break;
                            }
                            ?>
                        </span>
                    </div>
                    <?php if (!empty($sol['detalle'])): ?>
                        <div class="solicitud-detalle"><?php echo htmlspecialchars($sol['detalle']); ?></div>
                    <?php endif; ?>
                    <div class="solicitud-fecha">📅 <?php echo date('d/m/Y H:i', strtotime($sol['created_at'])); ?></div>
                    <?php if (!empty($sol['respuesta'])): ?>
                        <div class="solicitud-respuesta">
                            💬 <strong>Respuesta:</strong> <?php echo htmlspecialchars($sol['respuesta']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sin-solicitudes">📋 Aún no has enviado solicitudes.</div>
        <?php endif; ?>

    </div>

<script>
function seleccionar(el, tipo, esReparacion) {
    // Quitar selección anterior
    document.querySelectorAll('.opcion-card').forEach(c => c.classList.remove('seleccionada'));
    // Marcar seleccionada
    el.classList.add('seleccionada');
    // Mostrar formulario
    document.getElementById('form-panel').classList.add('visible');
    document.getElementById('tipo_solicitud').value = tipo;
    document.getElementById('tipo-display').textContent = tipo;
    // Mostrar/ocultar campo ubicación
    var campoUbi = document.getElementById('campo-ubicacion');
    if (esReparacion) {
        campoUbi.classList.add('visible');
    } else {
        campoUbi.classList.remove('visible');
    }
    // Scroll al formulario
    document.getElementById('form-panel').scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<script src="/intep/sesion.js"></script>
</body>
</html>
