<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../login.php');
    exit;
}

$mensaje = '';
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['usuario_rol'];

// Obtener programas
$programas = [];
$res_prog = mysqli_query($conexion, "SELECT * FROM programas ORDER BY nombre ASC");
while ($p = mysqli_fetch_assoc($res_prog)) $programas[] = $p;

$programa_id = isset($_GET['programa_id']) ? (int)$_GET['programa_id'] :
               (!empty($programas) ? $programas[0]['id'] : 0);

// Obtener catálogo de módulos de formación (para el select de asignar)
$catalogo = [];
$res_cat = mysqli_query($conexion, "SELECT * FROM modulos_formacion ORDER BY nombre ASC");
while ($c = mysqli_fetch_assoc($res_cat)) $catalogo[] = $c;

// Obtener docentes (solo admin)
$docentes = [];
if ($rol === 'admin') {
    $res_doc = mysqli_query($conexion, "SELECT id, username FROM usuarios
                                        WHERE rol IN ('admin','docente') AND estado = 'activo'
                                        ORDER BY username ASC");
    while ($d = mysqli_fetch_assoc($res_doc)) $docentes[] = $d;
}

// Obtener módulos asignados al programa según rol
if ($rol === 'docente') {
    $q_mod = "SELECT pm.*, mf.nombre as modulo_nombre, mf.codigo, u.username as docente
              FROM programa_modulo pm
              JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
              LEFT JOIN usuarios u ON pm.docente_id = u.id
              WHERE pm.programa_id = ? AND pm.docente_id = ?
              ORDER BY pm.bimestre, pm.orden";
    $stmt_mod = mysqli_prepare($conexion, $q_mod);
    mysqli_stmt_bind_param($stmt_mod, 'ii', $programa_id, $usuario_id);
} else {
    $q_mod = "SELECT pm.*, mf.nombre as modulo_nombre, mf.codigo, u.username as docente
              FROM programa_modulo pm
              JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
              LEFT JOIN usuarios u ON pm.docente_id = u.id
              WHERE pm.programa_id = ?
              ORDER BY pm.bimestre, pm.orden";
    $stmt_mod = mysqli_prepare($conexion, $q_mod);
    mysqli_stmt_bind_param($stmt_mod, 'i', $programa_id);
}
mysqli_stmt_execute($stmt_mod);
$res_mod = mysqli_stmt_get_result($stmt_mod);
$modulos = [];
while ($m = mysqli_fetch_assoc($res_mod)) $modulos[] = $m;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];

    // --- CREAR MÓDULO NUEVO EN CATÁLOGO Y ASIGNAR ---
    if ($accion === 'crear_catalogo' && $rol === 'admin') {
        $nombre = trim($_POST['nombre']);
        $codigo = trim($_POST['codigo'] ?? '');
        if ($nombre) {
            $q = "INSERT IGNORE INTO modulos_formacion (nombre, codigo) VALUES (?, ?)";
            $stmt = mysqli_prepare($conexion, $q);
            mysqli_stmt_bind_param($stmt, 'ss', $nombre, $codigo);
            mysqli_stmt_execute($stmt);
            $mensaje = 'success|Módulo "' . $nombre . '" agregado al catálogo.';
        }

    // --- ASIGNAR MÓDULO EXISTENTE AL PROGRAMA ---
    } elseif ($accion === 'asignar' && $rol === 'admin') {
        $modulo_formacion_id = (int)$_POST['modulo_formacion_id'];
        $bimestre   = !empty($_POST['bimestre']) ? (int)$_POST['bimestre'] : null;
        $orden      = (int)$_POST['orden'];
        $tipo       = $_POST['tipo'] ?? 'especifico';
        $docente_id = !empty($_POST['docente_id']) ? (int)$_POST['docente_id'] : null;

        // Verificar que no esté ya asignado con el mismo bimestre
        $check = mysqli_prepare($conexion, "SELECT id FROM programa_modulo WHERE programa_id = ? AND modulo_formacion_id = ? AND (bimestre = ? OR (bimestre IS NULL AND ? IS NULL))");
        mysqli_stmt_bind_param($check, 'iiii', $programa_id, $modulo_formacion_id, $bimestre, $bimestre);
        mysqli_stmt_execute($check);
        $check_res = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_res) > 0) {
            $mensaje = 'error|Este módulo ya está asignado a este programa en el mismo bimestre.';
        } else {
            $q = "INSERT INTO programa_modulo (programa_id, modulo_formacion_id, bimestre, orden, tipo, docente_id)
                  VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexion, $q);
            mysqli_stmt_bind_param($stmt, 'iiissi', $programa_id, $modulo_formacion_id, $bimestre, $orden, $tipo, $docente_id);
            mysqli_stmt_execute($stmt);
            $mensaje = 'success|Módulo asignado al programa correctamente.';
        }

    // --- ELIMINAR ASIGNACIÓN ---
    } elseif ($accion === 'eliminar' && $rol === 'admin') {
        $id = (int)$_POST['programa_modulo_id'];

        // Eliminar registros dependientes
        $tablas_dependientes = ['notas', 'asistencia', 'observaciones'];
        foreach ($tablas_dependientes as $tabla) {
            $stmt_dep = mysqli_prepare($conexion, "DELETE FROM $tabla WHERE programa_modulo_id = ?");
            if ($stmt_dep) {
                mysqli_stmt_bind_param($stmt_dep, 'i', $id);
                mysqli_stmt_execute($stmt_dep);
            }
        }

        $q = "DELETE FROM programa_modulo WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Módulo desasignado del programa.';

    // --- ACTUALIZAR ASIGNACIÓN (bimestre, orden, tipo, docente) ---
    } elseif ($accion === 'actualizar' && $rol === 'admin') {
        $id = (int)$_POST['programa_modulo_id'];
        $bimestre   = !empty($_POST['bimestre']) ? (int)$_POST['bimestre'] : null;
        $orden      = (int)$_POST['orden'];
        $tipo       = $_POST['tipo'] ?? 'especifico';
        $docente_id = !empty($_POST['docente_id']) ? (int)$_POST['docente_id'] : null;

        $q = "UPDATE programa_modulo SET bimestre = ?, orden = ?, tipo = ?, docente_id = ? WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'iisii', $bimestre, $orden, $tipo, $docente_id, $id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Asignación actualizada.';
    }

    header("Location: gestionar_modulos.php?programa_id={$programa_id}&msg=" . urlencode($mensaje));
    exit;
}

if (isset($_GET['msg']) && $_GET['msg']) {
    $mensaje = $_GET['msg'];
} elseif (isset($_GET['msg'])) {
    $mensaje = 'success|Cambios guardados correctamente.';
}
$msg_parts = $mensaje ? explode('|', $mensaje) : null;

// Agrupar módulos por bimestre para la vista
$modulos_por_bimestre = [];
$modulos_sin_bimestre = [];
foreach ($modulos as $mod) {
    if ($mod['bimestre']) {
        $modulos_por_bimestre[$mod['bimestre']][] = $mod;
    } else {
        $modulos_sin_bimestre[] = $mod;
    }
}
ksort($modulos_por_bimestre);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Módulos – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/intep/favicon/favicon-16x16.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <link rel="shortcut icon" href="/intep/favicon/favicon.ico">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1.3fr; gap: 1.5rem; }
        .card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08), 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.12), 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1.2rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(16, 185, 129, 0.15); color: #022C22; }
        .campo-admin { margin-bottom: 1rem; }
        .campo-admin label { display: block; font-size: 0.8rem; font-weight: 600; color: #666; margin-bottom: 0.3rem; text-transform: uppercase; }
        .campo-admin input, .campo-admin select { width: 100%; padding: 0.7rem 0.9rem; border: 2px solid rgba(16, 185, 129, 0.2); border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box; background: rgba(255,255,255,0.8); }
        .campo-admin input:focus, .campo-admin select:focus { border-color: #10B981; }
        .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .grid-3col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .btn-crear { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; padding: 0.8rem; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 0.95rem; margin-top: 0.5rem; transition: all 0.3s; }
        .btn-crear:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(5,150,105,0.3); }
        .btn-secundario { background: linear-gradient(135deg, #0369a1, #0ea5e9); color: white; border: none; padding: 0.8rem; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 0.95rem; margin-top: 0.5rem; transition: all 0.3s; }
        .btn-secundario:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(3,105,161,0.3); }
        .btn-eliminar { background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 0.3rem 0.7rem; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; transition: all 0.2s; }
        .btn-eliminar:hover { background: #ef4444; color: white; }
        .alerta-success { background: rgba(16, 185, 129, 0.1); color: #065f46; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #10b981; font-size: 0.88rem; }
        .alerta-error { background: rgba(239, 68, 68, 0.1); color: #991b1b; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #ef4444; font-size: 0.88rem; }
        .aviso-docente { background: rgba(245, 158, 11, 0.1); color: #92400e; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #f59e0b; font-size: 0.88rem; }
        .bimestre-tag { background: #022C22; color: #f59e0b; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .tipo-tag { padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .tipo-especifico { background: #fecaca; color: #991b1b; }
        .tipo-transversal { background: #fef08a; color: #854d0e; }
        .tipo-basico { background: #bbf7d0; color: #166534; }
        .selector-top {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        .selector-top label { font-weight: 700; font-size: 0.88rem; color: #666; }
        .selector-top select { padding: 0.6rem 1rem; border: 2px solid rgba(16, 185, 129, 0.2); border-radius: 10px; outline: none; font-size: 0.88rem; background: rgba(255,255,255,0.8); }
        .selector-top select:focus { border-color: var(--verde-claro); }
        .sin-modulos { text-align: center; padding: 2rem; color: var(--gray); }
        .btn-subir-notas { background: var(--verde); color: white; padding: 0.3rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: background 0.2s; }
        .btn-subir-notas:hover { background: var(--verde-claro); }
        .bimestre-section { margin-bottom: 1.5rem; }
        .bimestre-header { background: linear-gradient(135deg, #022C22, #064e3b); color: white; padding: 0.5rem 1rem; border-radius: 8px 8px 0 0; font-weight: 700; font-size: 0.85rem; }
        .tabs-form { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .tab-btn { padding: 0.5rem 1rem; border: 2px solid rgba(16, 185, 129, 0.2); border-radius: 8px; background: white; cursor: pointer; font-size: 0.82rem; font-weight: 600; transition: all 0.2s; }
        .tab-btn.active { background: #059669; color: white; border-color: #059669; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        @media(max-width:700px) {
            .grid-2 { grid-template-columns: 1fr; }
            .grid-3col { grid-template-columns: 1fr; }
            .card { padding: 0.8rem; }
            .selector-top { padding: 0.8rem; flex-wrap: wrap; gap: 0.5rem; }
            .dashboard-container { padding: 0.5rem; max-width: 100%; overflow-x: hidden; }
            table { font-size: 0.78rem; }
            table td, table th { padding: 0.5rem 0.4rem; }
            .btn-eliminar { padding: 0.2rem 0.5rem; font-size: 0.7rem; }
            .btn-subir-notas { padding: 0.2rem 0.5rem; font-size: 0.7rem; }
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">📋 Gestionar Módulos</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="dashboard-container">

    <a href="../dashboard.php" class="btn-volver">← Volver al inicio</a>

    <?php if ($msg_parts): ?>
        <div class="alerta-<?php echo $msg_parts[0]; ?>"><?php echo htmlspecialchars($msg_parts[1]); ?></div>
    <?php endif; ?>

    <?php if ($rol === 'docente'): ?>
        <div class="aviso-docente">
            📌 Estás viendo únicamente los módulos que te han sido asignados.
        </div>
    <?php endif; ?>

    <!-- Selector de programa -->
    <div class="selector-top">
        <label>Programa:</label>
        <select onchange="window.location.href='gestionar_modulos.php?programa_id='+this.value">
            <?php foreach ($programas as $p): ?>
                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $programa_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($rol === 'docente'): ?>
            <a href="ingresar_notas.php?programa_id=<?php echo $programa_id; ?>"
               style="background:var(--verde);color:white;padding:0.6rem 1.2rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.88rem;">
                ✏️ Ir a Ingresar Notas →
            </a>
        <?php endif; ?>
    </div>

    <div class="grid-2">

        <!-- Formularios — solo admin -->
        <?php if ($rol === 'admin'): ?>
        <div class="card">
            <div class="tabs-form">
                <button class="tab-btn active" onclick="switchTab('asignar')">📌 Asignar Módulo</button>
                <button class="tab-btn" onclick="switchTab('crear')">➕ Crear Nuevo</button>
            </div>

            <!-- Tab: Asignar módulo existente -->
            <div class="tab-content active" id="tab-asignar">
                <form method="POST" action="gestionar_modulos.php?programa_id=<?php echo $programa_id; ?>">
                    <input type="hidden" name="accion" value="asignar">
                    <div class="campo-admin">
                        <label>Módulo de formación</label>
                        <select name="modulo_formacion_id" required>
                            <option value="">Selecciona del catálogo</option>
                            <?php foreach ($catalogo as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['nombre']); ?>
                                    <?php echo $c['codigo'] ? ' (' . $c['codigo'] . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid-3col">
                        <div class="campo-admin">
                            <label>Bimestre</label>
                            <select name="bimestre">
                                <option value="">Sin asignar</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>">Bimestre <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo-admin">
                            <label>Orden</label>
                            <select name="orden" required>
                                <option value="1">Módulo 1</option>
                                <option value="2">Módulo 2</option>
                            </select>
                        </div>
                        <div class="campo-admin">
                            <label>Tipo</label>
                            <select name="tipo" required>
                                <option value="especifico">Específico</option>
                                <option value="transversal">Transversal</option>
                                <option value="basico">Básico</option>
                            </select>
                        </div>
                    </div>
                    <div class="campo-admin">
                        <label>Docente asignado</label>
                        <select name="docente_id">
                            <option value="">Sin asignar</option>
                            <?php foreach ($docentes as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-crear">📌 Asignar al Programa</button>
                </form>
            </div>

            <!-- Tab: Crear módulo nuevo en catálogo -->
            <div class="tab-content" id="tab-crear">
                <form method="POST" action="gestionar_modulos.php?programa_id=<?php echo $programa_id; ?>">
                    <input type="hidden" name="accion" value="crear_catalogo">
                    <div class="campo-admin">
                        <label>Nombre del módulo</label>
                        <input type="text" name="nombre" placeholder="Ej: Ética Laboral" required>
                    </div>
                    <div class="campo-admin">
                        <label>Código (opcional)</label>
                        <input type="text" name="codigo" placeholder="Ej: EL" maxlength="20">
                    </div>
                    <button type="submit" class="btn-secundario">➕ Crear en Catálogo</button>
                    <p style="font-size:0.75rem;color:#888;margin-top:0.5rem;">Esto solo crea el módulo en el catálogo. Luego debes asignarlo al programa.</p>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista de módulos del programa -->
        <div class="card">
            <h3>
                📋 <?php echo $rol === 'docente' ? 'Mis Módulos' : 'Módulos del Programa'; ?>
                (<?php echo count($modulos); ?>)
            </h3>
            <?php if (empty($modulos)): ?>
                <div class="sin-modulos">
                    <p><?php echo $rol === 'docente' ? 'No tienes módulos asignados en este programa.' : 'No hay módulos asignados aún. Usa el formulario para asignar el primero.'; ?></p>
                </div>
            <?php else: ?>

                <!-- Módulos con bimestre asignado -->
                <?php foreach ($modulos_por_bimestre as $bim => $mods): ?>
                <div class="bimestre-section">
                    <div class="bimestre-header">Bimestre <?php echo $bim; ?></div>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                            <tbody>
                                <?php foreach ($mods as $mod): ?>
                                <tr style="border-bottom:1px solid #f0f4f1;">
                                    <td style="padding:0.6rem;">
                                        <strong><?php echo htmlspecialchars($mod['modulo_nombre']); ?></strong>
                                        <?php if ($mod['codigo']): ?>
                                            <span style="color:#888;font-size:0.75rem;">(<?php echo htmlspecialchars($mod['codigo']); ?>)</span>
                                        <?php endif; ?>
                                        <br>
                                        <span class="tipo-tag tipo-<?php echo $mod['tipo']; ?>"><?php echo $mod['tipo']; ?></span>
                                    </td>
                                    <td style="padding:0.6rem;text-align:center;">
                                        <span class="bimestre-tag">B<?php echo $mod['bimestre']; ?> · M<?php echo $mod['orden']; ?></span>
                                    </td>
                                    <?php if ($rol === 'admin'): ?>
                                    <td style="padding:0.6rem;text-align:center;font-size:0.82rem;color:var(--gray);">
                                        <?php echo htmlspecialchars($mod['docente'] ?? 'Sin asignar'); ?>
                                    </td>
                                    <td style="padding:0.6rem;text-align:center;">
                                        <form method="POST" action="gestionar_modulos.php?programa_id=<?php echo $programa_id; ?>"
                                              onsubmit="return confirm('¿Desasignar este módulo del programa?')" style="display:inline;">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="programa_modulo_id" value="<?php echo $mod['id']; ?>">
                                            <button type="submit" class="btn-eliminar">🗑</button>
                                        </form>
                                    </td>
                                    <?php else: ?>
                                    <td style="padding:0.6rem;text-align:center;">
                                        <a href="ingresar_notas.php?programa_modulo_id=<?php echo $mod['id']; ?>"
                                           class="btn-subir-notas">✏️ Notas</a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Módulos sin bimestre asignado -->
                <?php if (!empty($modulos_sin_bimestre)): ?>
                <div class="bimestre-section">
                    <div class="bimestre-header" style="background:linear-gradient(135deg,#92400e,#d97706);">Sin bimestre asignado</div>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                            <tbody>
                                <?php foreach ($modulos_sin_bimestre as $mod): ?>
                                <tr style="border-bottom:1px solid #f0f4f1;">
                                    <td style="padding:0.6rem;">
                                        <strong><?php echo htmlspecialchars($mod['modulo_nombre']); ?></strong>
                                        <?php if ($mod['codigo']): ?>
                                            <span style="color:#888;font-size:0.75rem;">(<?php echo htmlspecialchars($mod['codigo']); ?>)</span>
                                        <?php endif; ?>
                                        <br>
                                        <span class="tipo-tag tipo-<?php echo $mod['tipo']; ?>"><?php echo $mod['tipo']; ?></span>
                                    </td>
                                    <td style="padding:0.6rem;text-align:center;">
                                        <span style="color:#d97706;font-size:0.78rem;">Pendiente</span>
                                    </td>
                                    <?php if ($rol === 'admin'): ?>
                                    <td style="padding:0.6rem;text-align:center;font-size:0.82rem;color:var(--gray);">
                                        <?php echo htmlspecialchars($mod['docente'] ?? 'Sin asignar'); ?>
                                    </td>
                                    <td style="padding:0.6rem;text-align:center;">
                                        <form method="POST" action="gestionar_modulos.php?programa_id=<?php echo $programa_id; ?>"
                                              onsubmit="return confirm('¿Desasignar este módulo del programa?')" style="display:inline;">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="programa_modulo_id" value="<?php echo $mod['id']; ?>">
                                            <button type="submit" class="btn-eliminar">🗑</button>
                                        </form>
                                    </td>
                                    <?php else: ?>
                                    <td style="padding:0.6rem;text-align:center;">
                                        <span style="color:#aaa;font-size:0.78rem;">—</span>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
}
</script>
<script src="/intep/sesion.js"></script>
</body>
</html>
