<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$estudiante_id = $_SESSION['estudiante_id'] ?? null;
$mensaje = '';
$tipo_msg = '';

// Procesar foto enviada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_foto'])) {
    if (isset($_POST['imagen_data']) && !empty($_POST['imagen_data'])) {
        $imagen_data = $_POST['imagen_data'];
        
        // Convertir base64 a imagen
        if (preg_match('/^data:image\/(\w+);base64,/', $imagen_data, $tipo)) {
            $tipo_img = $tipo[1]; // jpeg, png, etc.
            $imagen_base64 = substr($imagen_data, strpos($imagen_data, ',') + 1);
            $imagen_bin = base64_decode($imagen_base64);
            
            // Validar tamaño máximo (2MB)
            if (strlen($imagen_bin) > 2 * 1024 * 1024) {
                $mensaje = 'La imagen es muy grande. Máximo 2MB.';
                $tipo_msg = 'error';
            } else {
                // Crear nombre de archivo único
                $nombre_archivo = 'estudiante_' . $estudiante_id . '_' . time() . '.jpg';
                $ruta_carpeta = __DIR__ . '/uploads/fotos/';
                
                // Crear carpeta si no existe
                if (!is_dir($ruta_carpeta)) {
                    mkdir($ruta_carpeta, 0755, true);
                }
                
                $ruta_completa = $ruta_carpeta . $nombre_archivo;
                
                // Guardar imagen
                if (file_put_contents($ruta_completa, $imagen_bin)) {
                    // Actualizar en base de datos
                    $ruta_db = '/intep/uploads/fotos/' . $nombre_archivo;
                    
                    // Verificar si ya tiene foto
                    $check = mysqli_prepare($conexion, "SELECT id FROM estudiantes WHERE id = ?");
                    mysqli_stmt_bind_param($check, 'i', $estudiante_id);
                    mysqli_stmt_execute($check);
                    
                    $upd = mysqli_prepare($conexion, "UPDATE estudiantes SET foto = ? WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'si', $ruta_db, $estudiante_id);
                    
                    if (mysqli_stmt_execute($upd)) {
                        $mensaje = '✅ Foto guardada correctamente.';
                        $tipo_msg = 'exito';
                    } else {
                        $mensaje = 'Error al guardar en la base de datos.';
                        $tipo_msg = 'error';
                    }
                } else {
                    $mensaje = 'Error al guardar la imagen.';
                    $tipo_msg = 'error';
                }
            }
        } else {
            $mensaje = 'Formato de imagen inválido.';
            $tipo_msg = 'error';
        }
    } else {
        $mensaje = 'No se recibió ninguna imagen.';
        $tipo_msg = 'error';
    }
}

// Obtener foto actual del estudiante
$foto_actual = '';
if ($estudiante_id) {
    $r = mysqli_prepare($conexion, "SELECT foto, nombre FROM estudiantes WHERE id = ?");
    mysqli_stmt_bind_param($r, 'i', $estudiante_id);
    mysqli_stmt_execute($r);
    $resultado = mysqli_stmt_get_result($r);
    $estudiante = mysqli_fetch_assoc($resultado);
    $foto_actual = $estudiante['foto'] ?? '';
    $nombre_estudiante = $estudiante['nombre'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Foto – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body {
            background: #f8f9fc;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .foto-container {
            max-width: 500px;
            margin: 2rem auto;
            padding: 1.5rem;
        }
        
        .card-foto {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .card-foto:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.12);
        }
        
        .card-foto h2 {
            color: #022C22;
            margin-bottom: 0.5rem;
        }
        
        .card-foto p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }
        
        /* Vista previa de cámara */
        .camara-wrapper {
            position: relative;
            width: 100%;
            max-width: 320px;
            margin: 0 auto 1.5rem;
            border-radius: 16px;
            overflow: hidden;
            background: #022C22;
        }
        
        #video {
            width: 100%;
            display: block;
        }
        
        #canvas {
            display: none;
        }
        
        /* Foto capturada */
        .foto-preview {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            overflow: hidden;
            border: 4px solid #10B981;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .foto-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .foto-preview .sin-foto {
            color: #9ca3af;
            font-size: 0.9rem;
        }
        
        /* Botones */
        .btn-camara {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0.3rem;
        }
        
        .btn-capturar {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white;
        }
        
        .btn-capturar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5,150,105,0.4);
        }
        
        .btn-recuperar {
            background: #f3f4f6;
            color: #4b5563;
        }
        
        .btn-guardar {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
            width: 100%;
            max-width: 300px;
        }
        
        .btn-guardar:hover {
            box-shadow: 0 6px 20px rgba(16,185,129,0.4);
        }
        
        .btn-volver {
            display: inline-block;
            margin-bottom: 1rem;
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-volver:hover {
            color: #059669;
        }
        
        /* Alerta */
        .alerta {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .alerta.exito {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alerta.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Instrucciones */
        .instrucciones {
            background: #ECFDF5;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .instrucciones h4 {
            color: #059669;
            margin: 0 0 0.5rem;
            font-size: 0.9rem;
        }
        
        .instrucciones ul {
            margin: 0;
            padding-left: 1.2rem;
            color: #4b5563;
            font-size: 0.85rem;
        }
        
        .instrucciones li {
            margin-bottom: 0.3rem;
        }
        
        @media (max-width: 480px) {
            .foto-container {
                padding: 1rem;
                margin: 1rem auto;
            }
            
            .card-foto {
                padding: 1.5rem 1rem;
            }
            
            .camara-wrapper {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="foto-container">
    <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>
    
    <div class="card-foto">
        <h2>📸 Mi Foto de Perfil</h2>
        <p>Tomate una selfie para identificarte en el sistema</p>
        
        <?php if ($mensaje): ?>
            <div class="alerta <?php echo $tipo_msg; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($foto_actual): ?>
            <div class="foto-preview">
                <img src="<?php echo htmlspecialchars($foto_actual); ?>" alt="Tu foto">
            </div>
        <?php else: ?>
            <div class="foto-preview">
                <span class="sin-foto">Sin foto</span>
            </div>
        <?php endif; ?>
        
        <div class="instrucciones">
            <h4>📋 Instrucciones:</h4>
            <ul>
                <li>Permite el acceso a la cámara cuando se te pida</li>
                <li>Asegúrate de tener buena iluminación</li>
                <li>Mira directamente a la cámara</li>
                <li>Presiona "Capturar" cuando estés listo</li>
            </ul>
        </div>
        
        <div class="camara-wrapper">
            <video id="video" autoplay playsinline></video>
            <canvas id="canvas"></canvas>
        </div>
        
        <div class="botones">
            <button type="button" class="btn-camara btn-capturar" id="btn-iniciar" onclick="iniciarCamara()">📷 Iniciar Cámara</button>
            <button type="button" class="btn-camara btn-capturar" id="btn-capturar" onclick="capturarFoto()" style="display:none;">🔴 Capturar</button>
            <button type="button" class="btn-camara btn-recuperar" id="btn-recuperar" onclick="recuperar()" style="display:none;">🔄 Repetir</button>
        </div>
        
        <form method="POST" id="form-foto" style="margin-top: 1.5rem;">
            <input type="hidden" name="guardar_foto" value="1">
            <input type="hidden" name="imagen_data" id="imagen_data">
            <button type="submit" class="btn-camara btn-guardar" id="btn-guardar" style="display:none;">💾 Guardar Foto</button>
        </form>
    </div>
</div>

<script>
let videoStream = null;

async function iniciarCamara() {
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 }
            } 
        });
        
        const video = document.getElementById('video');
        video.srcObject = videoStream;
        video.style.display = 'block';
        
        document.getElementById('btn-iniciar').style.display = 'none';
        document.getElementById('btn-capturar').style.display = 'inline-block';
        
    } catch (err) {
        alert('Error al acceder a la cámara: ' + err.message);
    }
}

function capturarFoto() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Voltear horizontalmente (espejo)
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0);
    
    // Obtener imagen como base64
    const imagenData = canvas.toDataURL('image/jpeg', 0.85);
    document.getElementById('imagen_data').value = imagenData;
    
    // Mostrar vista previa
    const previewImg = document.querySelector('.foto-preview img') || document.createElement('img');
    previewImg.src = imagenData;
    const preview = document.querySelector('.foto-preview');
    preview.innerHTML = '';
    preview.appendChild(previewImg);
    
    // Detener cámara
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
    video.style.display = 'none';
    
    // Mostrar botones
    document.getElementById('btn-capturar').style.display = 'none';
    document.getElementById('btn-recuperar').style.display = 'inline-block';
    document.getElementById('btn-guardar').style.display = 'inline-block';
}

function recuperar() {
    document.getElementById('imagen_data').value = '';
    document.getElementById('btn-recuperar').style.display = 'none';
    document.getElementById('btn-guardar').style.display = 'none';
    document.getElementById('btn-iniciar').style.display = 'inline-block';
    
    // Restaurar foto anterior si existe
    <?php if ($foto_actual): ?>
    const preview = document.querySelector('.foto-preview');
    preview.innerHTML = '<img src="<?php echo htmlspecialchars($foto_actual); ?>" alt="Tu foto">';
    <?php endif; ?>
}

// Cerrar cámara al salir de la página
window.addEventListener('beforeunload', function() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
});
</script>

</body>
</html>
