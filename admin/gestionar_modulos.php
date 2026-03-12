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

// Obtener materias del programa
$materias = [];
$q_mat = "SELECT * FROM materias WHERE programa_id = ? ORDER BY nombre ASC";
$stmt_mat = mysqli_prepare($conexion, $q_mat);
mysqli_stmt_bind_param($stmt_mat, 'i', $programa_id);
mysqli_stmt_execute($stmt_mat);
$res_mat = mysqli_stmt_get_result($stmt_mat);
while ($m = mysqli_fetch_assoc($res_mat)) $materias[] = $m;

// Obtener docentes (solo admin)
$docentes = [];
if ($rol === 'admin') {
    $res_doc = mysqli_query($conexion, "SELECT id, username FROM usuarios 
                                        WHERE rol IN ('admin','docente') AND estado = 'activo' 
                                        ORDER BY username ASC");
    while ($d = mysqli_fetch_assoc($res_doc)) $docentes[] = $d;
}

// Obtener módulos según rol
if ($rol === 'docente') {
    $q_mod = "SELECT mo.*, m.nombre as materia, u.username as docente
              FROM modulos mo
              JOIN materias m ON mo.materia_id = m.id
              LEFT JOIN usuarios u ON mo.docente_id = u.id
              WHERE mo.programa_id = ? AND mo.docente_id = ?
              ORDER BY mo.bimestre, mo.orden";
    $stmt_mod = mysqli_prepare($conexion, $q_mod);
    mysqli_stmt_bind_param($stmt_mod, 'ii', $programa_id, $usuario_id);
} else {
    $q_mod = "SELECT mo.*, m.nombre as materia, u.username as docente
              FROM modulos mo
              JOIN materias m ON mo.materia_id = m.id
              LEFT JOIN usuarios u ON mo.docente_id = u.id
              WHERE mo.programa_id = ?
              ORDER BY mo.bimestre, mo.orden";
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

    if ($accion === 'crear' && $rol === 'admin') {
        $nombre     = trim($_POST['nombre']);
        $materia_id = (int)$_POST['materia_id'];
        $bimestre   = (int)$_POST['bimestre'];
        $orden      = (int)$_POST['orden'];
        $docente_id = !empty($_POST['docente_id']) ? (int)$_POST['docente_id'] : null;

        $q = "INSERT INTO modulos (programa_id, materia_id, nombre, bimestre, orden, docente_id)
              VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'iisiii', $programa_id, $materia_id, $nombre, $bimestre, $orden, $docente_id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Módulo creado correctamente.';

    } elseif ($accion === 'eliminar' && $rol === 'admin') {
        $id = (int)$_POST['modulo_id'];
        $q = "DELETE FROM modulos WHERE id = ?";
        $stmt = mysqli_prepare($conexion, $q);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $mensaje = 'success|Módulo eliminado.';
    }

    header("Location: gestionar_modulos.php?programa_id={$programa_id}&msg=ok");
    exit;
}

if (isset($_GET['msg'])) $mensaje = 'success|Cambios guardados correctamente.';
$msg_parts = $mensaje ? explode('|', $mensaje) : null;
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
        .btn-crear { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; padding: 0.8rem; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 0.95rem; margin-top: 0.5rem; transition: all 0.3s; }
        .btn-crear:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(5,150,105,0.3); }
        .btn-eliminar { background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 0.3rem 0.7rem; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 600; transition: all 0.2s; }
        .btn-eliminar:hover { background: #ef4444; color: white; }
        .alerta-success { background: rgba(16, 185, 129, 0.1); color: #065f46; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #10b981; font-size: 0.88rem; }
        .aviso-docente { background: rgba(245, 158, 11, 0.1); color: #92400e; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #f59e0b; font-size: 0.88rem; }
        .bimestre-tag { background: #022C22; color: #f59e0b; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
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
        @media(max-width:700px) {
            .grid-2 { grid-template-columns: 1fr; }
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

        <!-- Formulario crear módulo — solo admin -->
        <?php if ($rol === 'admin'): ?>
        <div class="card">
            <h3>➕ Crear Nuevo Módulo</h3>
            <form method="POST" action="gestionar_modulos.php?programa_id=<?php echo $programa_id; ?>">
                <input type="hidden" name="accion" value="crear">
                <div class="campo-admin">
                    <label>Nombre del Módulo</label>
                    <input type="text" name="nombre" placeholder="Ej: Contabilidad Básica I" required>
                </div>
                <div class="campo-admin">
                    <label>Materia asociada</label>
                    <select name="materia_id" required>
                        <option value="">Selecciona una materia</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2col">
                    <div class="campo-admin">
                        <label>Bimestre</label>
                        <select name="bimestre" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>">Bimestre <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="campo-admin">
                        <label>Orden en bimestre</label>
                        <select name="orden" required>
                            <option value="1">Módulo 1</option>
                            <option value="2">Módulo 2</option>
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
                <button type="submit" class="btn-crear">➕ Crear Módulo</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Lista de módulos -->
        <div class="card">
            <h3>
                📋 <?php echo $rol === 'docente' ? 'Mis Módulos' : 'Módulos del Programa'; ?>
                (<?php echo count($modulos); ?><?php echo $rol === 'admin' ? '/10' : ''; ?>)
            </h3>
            <?php if (empty($modulos)): ?>
                <div class="sin-modulos">
                    <p><?php echo $rol === 'docente' ? 'No tienes módulos asignados en este programa.' : 'No hay módulos creados aún. Usa el formulario para crear el primero.'; ?></p>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th style="background:var(--dark);color:white;padding:0.7rem;text-align:left;">Módulo</th>
                            <th style="background:var(--dark);color:white;padding:0.7rem;text-align:center;">Bimestre</th>
                            <?php if ($rol === 'admin'): ?>
                            <th style="background:var(--dark);color:white;padding:0.7rem;text-align:center;">Docente</th>
                            <th style="background:var(--dark);color:white;padding:0.7rem;text-align:center;">Eliminar</th>
                            <?php else: ?>
                            <th style="background:var(--dark);color:white;padding:0.7rem;text-align:center;">Notas</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulos as $mod): ?>
                        <tr style="border-bottom:1px solid #f0f4f1;">
                            <td style="padding:0.7rem;">
                                <strong><?php echo htmlspecialchars($mod['nombre']); ?></strong>
                                <small style="display:block;color:var(--gray);margin-top:0.2rem;"><?php echo htmlspecialchars($mod['materia']); ?></small>
                            </td>
                            <td style="padding:0.7rem;text-align:center;">
                                <span class="bimestre-tag">B<?php echo $mod['bimestre']; ?> · M<?php echo $mod['orden']; ?></span>
                            </td>
                            <?php if ($rol === 'admin'): ?>
                            <td style="padding:0.7rem;text-align:center;font-size:0.82rem;color:var(--gray);">
                                <?php echo htmlspecialchars($mod['docente'] ?? 'Sin asignar'); ?>
                            </td>
                            <td style="padding:0.7rem;text-align:center;">
                                <form method="POST" action="gestionar_modulos.php?programa_id=<?php echo $programa_id; ?>" 
                                      onsubmit="return confirm('¿Eliminar este módulo? También se borrarán sus notas.')">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="modulo_id" value="<?php echo $mod['id']; ?>">
                                    <button type="submit" class="btn-eliminar">🗑 Eliminar</button>
                                </form>
                            </td>
                            <?php else: ?>
                            <td style="padding:0.7rem;text-align:center;">
                                <a href="ingresar_notas.php?programa_id=<?php echo $programa_id; ?>&modulo_id=<?php echo $mod['id']; ?>"
                                   class="btn-subir-notas">✏️ Subir Notas</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="/intep/sesion.js"></script>
</body>
</html>