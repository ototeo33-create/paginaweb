<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../dashboard.php');
    exit;
}

$es_admin   = $_SESSION['usuario_rol'] === 'admin';
$usuario_id = (int)$_SESSION['usuario_id'];
$mensaje    = '';

// ============================================================
// AUTO-MIGRACIÓN: crear tabla material_clase si no existe
// ============================================================
mysqli_query($conexion, "
    CREATE TABLE IF NOT EXISTS material_clase (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        programa_modulo_id INT NOT NULL,
        docente_id      INT NOT NULL,
        titulo          VARCHAR(255) NOT NULL,
        descripcion     TEXT DEFAULT NULL,
        categoria       VARCHAR(50) DEFAULT 'general',
        archivo_nombre  VARCHAR(255) NOT NULL,
        archivo_path    VARCHAR(500) NOT NULL,
        archivo_tipo    VARCHAR(100) DEFAULT NULL,
        archivo_tamano  INT DEFAULT NULL,
        fecha_subida    DATETIME DEFAULT CURRENT_TIMESTAMP,
        activo          TINYINT(1) DEFAULT 1,
        INDEX idx_pm   (programa_modulo_id),
        INDEX idx_doc  (docente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ============================================================
// OBTENER MÓDULOS DEL DOCENTE (o todos si es admin)
// ============================================================
$modulos = [];
if ($es_admin) {
    $sql_mod = "SELECT pm.id, mf.nombre AS modulo, pm.bimestre,
                       p.nombre AS programa_nombre
                FROM programa_modulo pm
                JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                JOIN programas p ON pm.programa_id = p.id
                ORDER BY p.nombre, pm.bimestre, pm.orden";
    $res_mod = mysqli_query($conexion, $sql_mod);
} else {
    $sql_mod = "SELECT pm.id, mf.nombre AS modulo, pm.bimestre,
                       p.nombre AS programa_nombre
                FROM programa_modulo pm
                JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                JOIN programas p ON pm.programa_id = p.id
                WHERE pm.docente_id = ?
                ORDER BY p.nombre, pm.bimestre, pm.orden";
    $stmt_mod = mysqli_prepare($conexion, $sql_mod);
    mysqli_stmt_bind_param($stmt_mod, 'i', $usuario_id);
    mysqli_stmt_execute($stmt_mod);
    $res_mod = mysqli_stmt_get_result($stmt_mod);
}
while ($row = mysqli_fetch_assoc($res_mod)) {
    $modulos[] = $row;
}

// ============================================================
// PROCESAR ACCIONES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'error|Token de seguridad inválido. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';

        // --- SUBIR MATERIAL ---
        if ($accion === 'subir_material') {
            $pm_id      = (int)($_POST['programa_modulo_id'] ?? 0);
            $titulo     = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $categoria  = trim($_POST['categoria'] ?? 'general');

            $categorias_validas = ['general', 'guia', 'taller', 'evaluacion', 'recurso'];
            if (!in_array($categoria, $categorias_validas)) $categoria = 'general';

            if ($pm_id <= 0 || $titulo === '') {
                $mensaje = 'error|Completa todos los campos obligatorios.';
            } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                $err = $_FILES['archivo']['error'] ?? -1;
                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                    $mensaje = 'error|El archivo es muy grande. Máximo 50 MB.';
                } else {
                    $mensaje = 'error|No se recibió ningún archivo o hubo un error al subir.';
                }
            } else {
                // Verificar que el módulo pertenece al docente (si no es admin)
                $modulo_ok = false;
                foreach ($modulos as $m) {
                    if ($m['id'] === $pm_id) { $modulo_ok = true; break; }
                }
                if (!$modulo_ok) {
                    $mensaje = 'error|No tienes permiso sobre ese módulo.';
                } else {
                    $archivo        = $_FILES['archivo'];
                    $nombre_original = basename($archivo['name']);
                    $tamano         = $archivo['size'];
                    $tmp            = $archivo['tmp_name'];

                    // Validar tamaño (50 MB)
                    if ($tamano > 50 * 1024 * 1024) {
                        $mensaje = 'error|El archivo supera el límite de 50 MB.';
                    } else {
                        // Detectar MIME real
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime  = $finfo->file($tmp);

                        $mimes_permitidos = [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'application/zip',
                            'application/x-zip-compressed',
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'video/mp4',
                            'video/x-msvideo',
                            'text/plain',
                        ];

                        if (!in_array($mime, $mimes_permitidos)) {
                            $mensaje = 'error|Tipo de archivo no permitido. Sube PDF, Word, Excel, PowerPoint, ZIP, imagen o MP4.';
                        } else {
                            // Extensión segura desde el nombre original
                            $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                            $ext = preg_replace('/[^a-z0-9]/', '', $ext);
                            if (strlen($ext) > 5) $ext = substr($ext, 0, 5);

                            // Nombre de archivo seguro (nunca el original)
                            $nombre_seguro = 'mat_' . $usuario_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $carpeta       = __DIR__ . '/../uploads/materiales/';

                            if (!is_dir($carpeta)) {
                                mkdir($carpeta, 0755, true);
                            }

                            $ruta_completa = $carpeta . $nombre_seguro;

                            if (!move_uploaded_file($tmp, $ruta_completa)) {
                                $mensaje = 'error|Error al guardar el archivo en el servidor.';
                            } else {
                                $ruta_db = '/intep/uploads/materiales/' . $nombre_seguro;

                                $stmt = mysqli_prepare($conexion,
                                    "INSERT INTO material_clase
                                     (programa_modulo_id, docente_id, titulo, descripcion, categoria,
                                      archivo_nombre, archivo_path, archivo_tipo, archivo_tamano)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                                );
                                mysqli_stmt_bind_param($stmt, 'iissssssi',
                                    $pm_id, $usuario_id, $titulo, $descripcion, $categoria,
                                    $nombre_original, $ruta_db, $mime, $tamano
                                );
                                if (mysqli_stmt_execute($stmt)) {
                                    $mensaje = 'success|✅ Material "' . htmlspecialchars($titulo) . '" subido correctamente.';
                                } else {
                                    // Borrar archivo si falló BD
                                    @unlink($ruta_completa);
                                    $mensaje = 'error|Error al guardar en la base de datos.';
                                }
                            }
                        }
                    }
                }
            }
        }

        // --- ELIMINAR MATERIAL ---
        elseif ($accion === 'eliminar_material') {
            $mat_id = (int)($_POST['material_id'] ?? 0);
            if ($mat_id > 0) {
                // Solo puede eliminar el propio docente o el admin
                if ($es_admin) {
                    $stmt = mysqli_prepare($conexion, "SELECT archivo_path FROM material_clase WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'i', $mat_id);
                } else {
                    $stmt = mysqli_prepare($conexion, "SELECT archivo_path FROM material_clase WHERE id = ? AND docente_id = ?");
                    mysqli_stmt_bind_param($stmt, 'ii', $mat_id, $usuario_id);
                }
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $mat = mysqli_fetch_assoc($res);

                if ($mat) {
                    // Marcar como inactivo (soft delete)
                    $upd = mysqli_prepare($conexion, "UPDATE material_clase SET activo = 0 WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'i', $mat_id);
                    mysqli_stmt_execute($upd);
                    $mensaje = 'success|Material eliminado correctamente.';
                } else {
                    $mensaje = 'error|Material no encontrado o sin permiso.';
                }
            }
        }
    }
}

// ============================================================
// CARGAR MATERIALES DEL DOCENTE
// ============================================================
$materiales = [];
if ($es_admin) {
    $sql_mat = "SELECT mc.*, mf.nombre AS modulo, p.nombre AS programa_nombre,
                       u.username AS docente_nombre
                FROM material_clase mc
                JOIN programa_modulo pm ON mc.programa_modulo_id = pm.id
                JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                JOIN programas p ON pm.programa_id = p.id
                LEFT JOIN usuarios u ON mc.docente_id = u.id
                WHERE mc.activo = 1
                ORDER BY mc.fecha_subida DESC";
    $res_mat = mysqli_query($conexion, $sql_mat);
} else {
    $sql_mat = "SELECT mc.*, mf.nombre AS modulo, p.nombre AS programa_nombre
                FROM material_clase mc
                JOIN programa_modulo pm ON mc.programa_modulo_id = pm.id
                JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
                JOIN programas p ON pm.programa_id = p.id
                WHERE mc.docente_id = ? AND mc.activo = 1
                ORDER BY mc.fecha_subida DESC";
    $stmt_mat = mysqli_prepare($conexion, $sql_mat);
    mysqli_stmt_bind_param($stmt_mat, 'i', $usuario_id);
    mysqli_stmt_execute($stmt_mat);
    $res_mat = mysqli_stmt_get_result($stmt_mat);
}
while ($row = mysqli_fetch_assoc($res_mat)) {
    $materiales[] = $row;
}

// ============================================================
// HELPERS
// ============================================================
function formatBytes($bytes) {
    if ($bytes < 1024)        return $bytes . ' B';
    if ($bytes < 1048576)     return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function iconoCategoria($cat) {
    $icons = [
        'general'    => '📄',
        'guia'       => '📖',
        'taller'     => '🔧',
        'evaluacion' => '📝',
        'recurso'    => '🎯',
    ];
    return $icons[$cat] ?? '📄';
}

function labelCategoria($cat) {
    $labels = [
        'general'    => 'General',
        'guia'       => 'Guía de Clase',
        'taller'     => 'Taller',
        'evaluacion' => 'Evaluación',
        'recurso'    => 'Recurso Extra',
    ];
    return $labels[$cat] ?? 'General';
}

[$tipo_msg, $texto_msg] = $mensaje ? explode('|', $mensaje, 2) : ['', ''];
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material de Clase – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        body { background: #f8f9fc; font-family: 'Segoe UI', system-ui, sans-serif; }

        .page-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        .page-header {
            background: linear-gradient(135deg, #059669 0%, #10B981 60%, #34D399 100%);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(5,150,105,0.25);
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -40%; right: -5%;
            width: 220px; height: 220px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .page-header h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 0.3rem; position: relative; z-index: 1; }
        .page-header p  { font-size: 0.87rem; opacity: 0.85; margin: 0; position: relative; z-index: 1; }

        .btn-back {
            display: inline-flex; align-items: center; gap: 0.5rem;
            color: white; text-decoration: none; font-size: 0.85rem;
            font-weight: 600;
            background: linear-gradient(135deg, #059669, #10B981);
            padding: 0.55rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            transition: all 0.25s;
            box-shadow: 0 2px 8px rgba(5,150,105,0.2);
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(5,150,105,0.35);
        }

        /* Alerta */
        .alerta {
            padding: 1rem 1.2rem; border-radius: 12px;
            margin-bottom: 1.5rem; font-size: 0.9rem;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .alerta.success { background: #ECFDF5; color: #065f46; border: 1px solid #A7F3D0; }
        .alerta.error   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        /* Card formulario */
        .card {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(16,185,129,0.1);
            margin-bottom: 2rem;
        }
        .card h2 {
            font-size: 1.05rem; font-weight: 700; color: #022C22;
            margin: 0 0 1.2rem; display: flex; align-items: center; gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: 0.82rem; font-weight: 600; color: #374151; }
        .form-group select,
        .form-group input[type="text"],
        .form-group textarea {
            padding: 0.7rem 0.9rem;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #111827;
            background: #FAFAFA;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        .form-group select:focus,
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #10B981;
            background: white;
        }
        .form-group textarea { resize: vertical; min-height: 70px; }

        /* Zona de subida */
        .upload-zone {
            border: 2px dashed #D1FAE5;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            background: #F0FDF4;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: #10B981;
            background: #ECFDF5;
        }
        .upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .upload-zone .uz-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .upload-zone .uz-text { font-size: 0.88rem; color: #059669; font-weight: 600; }
        .upload-zone .uz-sub  { font-size: 0.75rem; color: #6B7280; margin-top: 0.3rem; }
        .upload-zone .uz-filename { font-size: 0.85rem; color: #059669; margin-top: 0.5rem; font-weight: 600; }

        .btn-submit {
            padding: 0.85rem 2rem;
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; border: none; border-radius: 12px;
            font-size: 0.95rem; font-weight: 700; cursor: pointer;
            transition: all 0.3s; margin-top: 1.2rem;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5,150,105,0.35);
        }
        .btn-submit:disabled {
            opacity: 0.6; cursor: not-allowed; transform: none;
        }

        /* Barra de progreso */
        .progress-bar-wrap {
            width: 100%; height: 6px; background: #E5E7EB;
            border-radius: 99px; overflow: hidden; margin-top: 0.5rem; display: none;
        }
        .progress-bar {
            height: 100%; background: linear-gradient(90deg, #059669, #10B981);
            border-radius: 99px; width: 0%; transition: width 0.3s;
        }

        /* Sección label */
        .seccion-label {
            font-size: 0.73rem; text-transform: uppercase;
            letter-spacing: 1.8px; color: #6B7280;
            font-weight: 700; margin-bottom: 1rem;
        }

        /* Lista de materiales */
        .materiales-lista { display: flex; flex-direction: column; gap: 0.8rem; }

        .material-item {
            background: white;
            border-radius: 14px;
            padding: 1.1rem 1.3rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(16,185,129,0.1);
            display: flex; align-items: center; gap: 1rem;
            transition: box-shadow 0.2s;
        }
        .material-item:hover { box-shadow: 0 4px 16px rgba(5,150,105,0.12); }

        .material-icon {
            width: 44px; height: 44px;
            border-radius: 11px; background: #ECFDF5;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }

        .material-info { flex: 1; min-width: 0; }
        .material-info .titulo {
            font-weight: 700; color: #022C22; font-size: 0.95rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .material-info .meta {
            font-size: 0.78rem; color: #9CA3AF; margin-top: 0.2rem;
        }
        .material-info .meta span { margin-right: 0.8rem; }

        .badge-cat {
            display: inline-block; padding: 0.2rem 0.6rem;
            border-radius: 99px; font-size: 0.72rem; font-weight: 600;
            background: #D1FAE5; color: #065F46;
        }

        .material-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }

        .btn-descargar {
            padding: 0.45rem 1rem;
            background: #059669; color: white;
            border-radius: 8px; font-size: 0.8rem; font-weight: 600;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-descargar:hover { background: #047857; }

        .btn-eliminar {
            padding: 0.45rem 0.8rem;
            background: #FEF2F2; color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 8px; font-size: 0.8rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-eliminar:hover { background: #FEE2E2; }

        .empty-state {
            text-align: center; padding: 3rem 1rem;
            color: #9CA3AF; font-size: 0.9rem;
        }
        .empty-state .es-icon { font-size: 2.5rem; margin-bottom: 0.8rem; }

        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .page-header { padding: 1.5rem; }
            .page-header h1 { font-size: 1.3rem; }
            .material-item { flex-direction: column; align-items: flex-start; }
            .material-actions { width: 100%; }
            .btn-descargar, .btn-eliminar { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?> · <?php echo ucfirst($_SESSION['usuario_rol']); ?></span>
    <a href="/intep/logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="page-container">
    <a href="/intep/dashboard.php" class="btn-back">← Volver al inicio</a>

    <div class="page-header">
        <h1>📚 Material de Clase</h1>
        <p>Sube guías, talleres, evaluaciones y recursos para tus estudiantes</p>
    </div>

    <?php if ($texto_msg): ?>
    <div class="alerta <?php echo htmlspecialchars($tipo_msg); ?>">
        <?php echo $tipo_msg === 'success' ? '✅' : '❌'; ?>
        <?php echo htmlspecialchars($texto_msg); ?>
    </div>
    <?php endif; ?>

    <!-- FORMULARIO DE SUBIDA -->
    <?php if (!empty($modulos)): ?>
    <div class="card">
        <h2>📤 Subir nuevo material</h2>
        <form method="POST" enctype="multipart/form-data" id="form-subir">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="accion" value="subir_material">

            <div class="form-grid">
                <div class="form-group full">
                    <label for="programa_modulo_id">Módulo *</label>
                    <select name="programa_modulo_id" id="programa_modulo_id" required>
                        <option value="">— Selecciona un módulo —</option>
                        <?php foreach ($modulos as $m): ?>
                        <option value="<?php echo $m['id']; ?>">
                            <?php echo htmlspecialchars($m['programa_nombre'] . ' › ' . $m['modulo'] . ' (Bim. ' . $m['bimestre'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="titulo">Título del material *</label>
                    <input type="text" name="titulo" id="titulo" placeholder="Ej: Guía de Excel Básico" maxlength="255" required>
                </div>

                <div class="form-group">
                    <label for="categoria">Categoría</label>
                    <select name="categoria" id="categoria">
                        <option value="general">📄 General</option>
                        <option value="guia">📖 Guía de Clase</option>
                        <option value="taller">🔧 Taller</option>
                        <option value="evaluacion">📝 Evaluación</option>
                        <option value="recurso">🎯 Recurso Extra</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="descripcion">Descripción (opcional)</label>
                    <textarea name="descripcion" id="descripcion" placeholder="Breve descripción del contenido..." maxlength="500"></textarea>
                </div>

                <div class="form-group full">
                    <label>Archivo *</label>
                    <div class="upload-zone" id="upload-zone">
                        <input type="file" name="archivo" id="archivo" required
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png,.gif,.mp4,.avi,.txt">
                        <div class="uz-icon">📁</div>
                        <div class="uz-text">Haz clic o arrastra tu archivo aquí</div>
                        <div class="uz-sub">PDF, Word, Excel, PowerPoint, ZIP, imagen o MP4 · Máx. 50 MB</div>
                        <div class="uz-filename" id="nombre-archivo" style="display:none;"></div>
                    </div>
                    <div class="progress-bar-wrap" id="progress-wrap">
                        <div class="progress-bar" id="progress-bar"></div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="btn-subir">📤 Subir Material</button>
        </form>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="es-icon">📦</div>
            <p>No tienes módulos asignados aún.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- LISTA DE MATERIALES -->
    <div class="seccion-label">Materiales subidos</div>

    <?php if (empty($materiales)): ?>
    <div class="empty-state">
        <div class="es-icon">🗂️</div>
        <p>Aún no has subido ningún material. ¡Empieza subiendo el primero!</p>
    </div>
    <?php else: ?>
    <div class="materiales-lista">
        <?php foreach ($materiales as $mat): ?>
        <div class="material-item">
            <div class="material-icon"><?php echo iconoCategoria($mat['categoria']); ?></div>
            <div class="material-info">
                <div class="titulo"><?php echo htmlspecialchars($mat['titulo']); ?></div>
                <div class="meta">
                    <span>📦 <?php echo htmlspecialchars($mat['programa_nombre']); ?> › <?php echo htmlspecialchars($mat['modulo']); ?></span>
                    <span class="badge-cat"><?php echo labelCategoria($mat['categoria']); ?></span>
                    <span>📎 <?php echo htmlspecialchars($mat['archivo_nombre']); ?></span>
                    <span>⚖️ <?php echo formatBytes($mat['archivo_tamano']); ?></span>
                    <span>📅 <?php echo date('d/m/Y H:i', strtotime($mat['fecha_subida'])); ?></span>
                    <?php if ($es_admin && !empty($mat['docente_nombre'])): ?>
                    <span>👨‍🏫 <?php echo htmlspecialchars($mat['docente_nombre']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($mat['descripcion'])): ?>
                <div class="meta" style="margin-top:0.3rem; color:#6B7280;">
                    <?php echo htmlspecialchars($mat['descripcion']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="material-actions">
                <a href="/intep/descargar_material.php?id=<?php echo $mat['id']; ?>"
                   class="btn-descargar" target="_blank">⬇️ Ver</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este material?');">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="eliminar_material">
                    <input type="hidden" name="material_id" value="<?php echo $mat['id']; ?>">
                    <button type="submit" class="btn-eliminar">🗑️</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Mostrar nombre del archivo seleccionado
document.getElementById('archivo').addEventListener('change', function() {
    const nom = document.getElementById('nombre-archivo');
    if (this.files.length > 0) {
        const f = this.files[0];
        const mb = (f.size / 1048576).toFixed(1);
        nom.textContent = '✅ ' + f.name + ' (' + mb + ' MB)';
        nom.style.display = 'block';
    } else {
        nom.style.display = 'none';
    }
});

// Drag & drop visual
const zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const input = document.getElementById('archivo');
    if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
    }
});

// Simular progreso al enviar (UX)
document.getElementById('form-subir').addEventListener('submit', function() {
    const wrap = document.getElementById('progress-wrap');
    const bar  = document.getElementById('progress-bar');
    const btn  = document.getElementById('btn-subir');
    wrap.style.display = 'block';
    btn.disabled = true;
    btn.textContent = '⏳ Subiendo...';
    let w = 0;
    const iv = setInterval(() => {
        w = Math.min(w + Math.random() * 15, 85);
        bar.style.width = w + '%';
        if (w >= 85) clearInterval(iv);
    }, 300);
});
</script>

<?php include __DIR__ . '/../partials/asistente_admin.php'; ?>
</body>
</html>
