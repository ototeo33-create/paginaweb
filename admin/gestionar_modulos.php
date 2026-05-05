<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) {
    header('Location: ../login.php');
    exit;
}

$mensaje    = '';
$usuario_id = (int)$_SESSION['usuario_id'];
$rol        = $_SESSION['usuario_rol'];

// ============================================================
// ACCIONES POST (solo admin)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rol === 'admin') {
    $accion = $_POST['accion'] ?? '';

    // --- CREAR EN CATALOGO ---
    if ($accion === 'crear_catalogo') {
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        if ($nombre) {
            $stmt = mysqli_prepare($conexion,
                "INSERT IGNORE INTO modulos_formacion (nombre, codigo) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $nombre, $codigo);
            mysqli_stmt_execute($stmt);
            $mensaje = 'success|Modulo "' . $nombre . '" agregado al catalogo.';
        } else {
            $mensaje = 'error|El nombre del modulo es obligatorio.';
        }

    // --- ASIGNAR MODULO ---
    } elseif ($accion === 'asignar') {
        $modulo_formacion_id = (int)($_POST['modulo_formacion_id'] ?? 0);
        $bimestre   = !empty($_POST['bimestre']) ? (int)$_POST['bimestre'] : null;
        $orden      = (int)($_POST['orden'] ?? 1);
        $tipo       = $_POST['tipo'] ?? 'especifico';
        $docente_id = !empty($_POST['docente_id']) ? (int)$_POST['docente_id'] : null;

        // Reglas: transversal -> programa_id NULL; los demas -> requiere programa_id
        if ($tipo === 'transversal') {
            $programa_id_t = null;
        } else {
            $programa_id_t = !empty($_POST['programa_id_target']) ? (int)$_POST['programa_id_target'] : null;
        }

        if (!$modulo_formacion_id) {
            $mensaje = 'error|Selecciona un modulo del catalogo.';
        } elseif ($tipo !== 'transversal' && !$programa_id_t) {
            $mensaje = 'error|Selecciona el tecnico al que pertenece este modulo.';
        } else {
            // Verificar duplicado
            if ($programa_id_t === null) {
                $check = mysqli_prepare($conexion,
                    "SELECT id FROM programa_modulo
                     WHERE programa_id IS NULL AND modulo_formacion_id = ?
                       AND (bimestre <=> ?)");
                mysqli_stmt_bind_param($check, 'ii', $modulo_formacion_id, $bimestre);
            } else {
                $check = mysqli_prepare($conexion,
                    "SELECT id FROM programa_modulo
                     WHERE programa_id = ? AND modulo_formacion_id = ?
                       AND (bimestre <=> ?)");
                mysqli_stmt_bind_param($check, 'iii', $programa_id_t, $modulo_formacion_id, $bimestre);
            }
            mysqli_stmt_execute($check);
            $check_res = mysqli_stmt_get_result($check);

            if (mysqli_num_rows($check_res) > 0) {
                $mensaje = 'error|Ese modulo ya esta asignado en el mismo bimestre.';
            } else {
                $stmt = mysqli_prepare($conexion,
                    "INSERT INTO programa_modulo (programa_id, modulo_formacion_id, bimestre, orden, tipo, docente_id)
                     VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iiissi',
                    $programa_id_t, $modulo_formacion_id, $bimestre, $orden, $tipo, $docente_id);
                mysqli_stmt_execute($stmt);
                $mensaje = 'success|Modulo asignado correctamente.';
            }
        }

    // --- ASIGNAR/CAMBIAR DOCENTE (rapido) ---
    } elseif ($accion === 'set_docente') {
        $pm_id      = (int)($_POST['pm_id'] ?? 0);
        $docente_id = !empty($_POST['docente_id']) ? (int)$_POST['docente_id'] : null;
        if ($pm_id) {
            $stmt = mysqli_prepare($conexion,
                "UPDATE programa_modulo SET docente_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $docente_id, $pm_id);
            mysqli_stmt_execute($stmt);
            $mensaje = 'success|Docente actualizado.';
        }

    // --- ELIMINAR ASIGNACION ---
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['programa_modulo_id'] ?? 0);
        if ($id) {
            // Cascada manual: notas, asistencia, observaciones, estudiante_modulo
            foreach (['notas', 'asistencia', 'observaciones', 'estudiante_modulo'] as $tbl) {
                $stmt = mysqli_prepare($conexion, "DELETE FROM $tbl WHERE programa_modulo_id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    mysqli_stmt_execute($stmt);
                }
            }
            $stmt = mysqli_prepare($conexion, "DELETE FROM programa_modulo WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $mensaje = 'success|Modulo desasignado.';
        }
    }

    $bim_redir = (int)($_POST['bimestre_view'] ?? 0);
    header("Location: gestionar_modulos.php?bimestre=" . $bim_redir . "&msg=" . urlencode($mensaje));
    exit;
}

if (isset($_GET['msg'])) $mensaje = $_GET['msg'];
$msg_parts = $mensaje ? explode('|', $mensaje, 2) : null;

// ============================================================
// DATOS
// ============================================================
$bim_sel = isset($_GET['bimestre']) ? (int)$_GET['bimestre'] : 1;
if ($bim_sel < 1 || $bim_sel > 5) $bim_sel = 1;

// Programas
$programas = [];
$res_p = mysqli_query($conexion, "SELECT id, nombre FROM programas ORDER BY nombre ASC");
while ($r = mysqli_fetch_assoc($res_p)) $programas[] = $r;

// Catalogo de modulos
$catalogo = [];
$res_c = mysqli_query($conexion, "SELECT id, nombre, codigo FROM modulos_formacion ORDER BY nombre ASC");
while ($r = mysqli_fetch_assoc($res_c)) $catalogo[] = $r;

// Docentes
$docentes = [];
$res_d = mysqli_query($conexion,
    "SELECT id, username FROM usuarios
     WHERE rol IN ('admin','docente') AND estado = 'activo'
     ORDER BY username ASC");
while ($r = mysqli_fetch_assoc($res_d)) $docentes[] = $r;

// Modulos del bimestre seleccionado
// Para docente: solo los suyos
// Para admin: todos
$modulos = [];
$sql = "SELECT pm.*, mf.nombre AS modulo_nombre, mf.codigo,
               u.username AS docente_username,
               p.nombre AS programa_nombre
        FROM programa_modulo pm
        JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
        LEFT JOIN usuarios u ON pm.docente_id = u.id
        LEFT JOIN programas p ON pm.programa_id = p.id
        WHERE pm.bimestre = ?";
if ($rol === 'docente') {
    $sql .= " AND pm.docente_id = ?";
}
$sql .= " ORDER BY pm.programa_id IS NULL DESC, p.nombre ASC, pm.orden ASC, mf.nombre ASC";

$stmt = mysqli_prepare($conexion, $sql);
if ($rol === 'docente') {
    mysqli_stmt_bind_param($stmt, 'ii', $bim_sel, $usuario_id);
} else {
    mysqli_stmt_bind_param($stmt, 'i', $bim_sel);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $modulos[] = $r;

// Agrupar: transversales (programa_id NULL) y por programa
$transversales    = [];
$mods_por_programa = [];
foreach ($modulos as $m) {
    if ($m['programa_id'] === null) {
        $transversales[] = $m;
    } else {
        $mods_por_programa[(int)$m['programa_id']][] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Modulos – INTEP</title>
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

        .page-title {
            font-size: 1.3rem; font-weight: 800; color: #022C22;
            margin-bottom: 1.5rem; padding-bottom: 0.8rem;
            border-bottom: 2px solid #ECFDF5;
        }

        .alerta { padding: 0.8rem 1rem; border-radius: 10px; margin-bottom: 1.2rem; font-size: 0.88rem; font-weight: 500; }
        .alerta-success { background: rgba(16,185,129,0.1); color: #065F46; border-left: 4px solid #10B981; }
        .alerta-error   { background: rgba(239,68,68,0.1);  color: #991B1B; border-left: 4px solid #EF4444; }

        /* Selector bimestre */
        .bim-selector {
            background: rgba(255,255,255,0.85); backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.4rem 1.8rem; margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            border: 1px solid rgba(16,185,129,0.12);
        }
        .bim-selector label {
            font-size: 0.75rem; font-weight: 800; color: #374151;
            text-transform: uppercase; letter-spacing: 0.5px;
            display: block; margin-bottom: 0.7rem;
        }
        .bim-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .bim-tab {
            padding: 0.65rem 1.4rem; border-radius: 10px; cursor: pointer;
            border: 2px solid rgba(16,185,129,0.25); background: white;
            font-weight: 700; font-size: 0.9rem; color: #374151;
            text-decoration: none; transition: all 0.2s;
        }
        .bim-tab:hover { border-color: #10B981; color: #059669; }
        .bim-tab.active {
            background: linear-gradient(135deg, #059669, #10B981);
            border-color: transparent; color: white;
            box-shadow: 0 4px 10px rgba(5,150,105,0.25);
        }

        /* Card */
        .card {
            background: white; border-radius: 16px;
            border: 1px solid rgba(16,185,129,0.12);
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            margin-bottom: 1.4rem; overflow: hidden;
        }
        .card-header {
            padding: 1rem 1.4rem; display: flex; align-items: center;
            justify-content: space-between; gap: 1rem; flex-wrap: wrap;
        }
        .card-header h3 { font-size: 1rem; margin: 0; color: white; font-weight: 700; }
        .card-header .meta { font-size: 0.78rem; color: rgba(255,255,255,0.85); }
        .ch-tecnico   { background: linear-gradient(135deg, #022C22, #064E3B); }
        .ch-transversal { background: linear-gradient(135deg, #92400E, #D97706); }
        .ch-add { background: linear-gradient(135deg, #1E40AF, #3B82F6); }

        .card-body { padding: 1.2rem 1.4rem; }

        /* Form de agregar */
        .form-agregar {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr 1fr;
            gap: 0.8rem; align-items: end;
        }
        .campo label {
            display: block; font-size: 0.72rem; font-weight: 700; color: #6B7280;
            text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.3rem;
        }
        .campo select, .campo input {
            width: 100%; padding: 0.6rem 0.8rem;
            border: 2px solid rgba(16,185,129,0.2); border-radius: 8px;
            font-size: 0.88rem; outline: none; box-sizing: border-box;
            background: white; transition: border-color 0.2s;
        }
        .campo select:focus, .campo input:focus { border-color: #10B981; }

        .btn-asignar {
            padding: 0.65rem 1rem; border: none; border-radius: 8px;
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; font-weight: 800; font-size: 0.88rem;
            cursor: pointer; transition: all 0.2s; white-space: nowrap;
        }
        .btn-asignar:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,0.35); }

        .form-toggle-row {
            margin-top: 0.8rem; padding-top: 0.8rem;
            border-top: 1px dashed #E5E7EB;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.82rem; color: #6B7280;
        }
        .link-catalogo {
            color: #059669; cursor: pointer; font-weight: 700; text-decoration: underline;
            background: none; border: none; font-size: 0.85rem;
        }

        /* Modal catalogo */
        .modal-bg {
            display: none; position: fixed; inset: 0; z-index: 1000;
            background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
        }
        .modal-bg.show { display: flex; }
        .modal {
            background: white; border-radius: 16px; padding: 1.8rem;
            max-width: 460px; width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal h3 { font-size: 1.1rem; color: #022C22; margin-bottom: 1.2rem; }
        .modal-cierre {
            float: right; cursor: pointer; background: none; border: none;
            font-size: 1.2rem; color: #6B7280;
        }

        /* Lista de modulos */
        .mod-lista {
            display: grid; gap: 0.5rem;
        }
        .mod-item {
            display: grid; grid-template-columns: 1fr auto auto;
            gap: 0.8rem; align-items: center;
            padding: 0.7rem 0.9rem; border-radius: 10px;
            background: #F9FAFB; border: 1px solid #E5E7EB;
            transition: all 0.15s;
        }
        .mod-item:hover { background: #F0FDF4; border-color: #A7F3D0; }
        .mod-info .nombre { font-weight: 700; color: #1F2937; font-size: 0.92rem; }
        .mod-info .meta { font-size: 0.74rem; color: #6B7280; margin-top: 2px; }
        .tipo-tag {
            display: inline-block; padding: 0.1rem 0.5rem; border-radius: 4px;
            font-size: 0.66rem; font-weight: 700; text-transform: uppercase; margin-right: 0.3rem;
        }
        .tipo-especifico  { background: #FEE2E2; color: #991B1B; }
        .tipo-transversal { background: #FEF3C7; color: #92400E; }
        .tipo-basico      { background: #DCFCE7; color: #166534; }
        .orden-tag {
            background: #022C22; color: #F59E0B;
            padding: 0.1rem 0.45rem; border-radius: 4px;
            font-size: 0.7rem; font-weight: 800;
        }

        .docente-select {
            min-width: 160px; padding: 0.4rem 0.6rem;
            border: 1.5px solid #E5E7EB; border-radius: 7px;
            font-size: 0.82rem; background: white; cursor: pointer;
            transition: border-color 0.2s;
        }
        .docente-select:hover { border-color: #10B981; }
        .docente-select.sin { background: #FEF2F2; border-color: #FCA5A5; color: #991B1B; }

        .btn-eliminar {
            background: transparent; border: 1.5px solid #EF4444; color: #EF4444;
            padding: 0.3rem 0.55rem; border-radius: 6px; cursor: pointer;
            font-size: 0.75rem; font-weight: 700; transition: all 0.15s;
        }
        .btn-eliminar:hover { background: #EF4444; color: white; }

        .vacio-msg {
            text-align: center; padding: 2rem 1rem; color: #9CA3AF;
            background: #F9FAFB; border-radius: 8px; font-size: 0.88rem;
        }

        /* Aviso docente */
        .aviso-docente {
            background: rgba(245,158,11,0.1); color: #92400E;
            padding: 0.8rem 1rem; border-radius: 10px;
            margin-bottom: 1.2rem; border-left: 4px solid #F59E0B;
            font-size: 0.88rem;
        }

        /* Toast */
        .toast {
            position: fixed; bottom: 2rem; right: 2rem; z-index: 1100;
            padding: 0.9rem 1.5rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transform: translateY(100px); opacity: 0; transition: all 0.3s;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.exito { background: #059669; color: white; }
        .toast.error { background: #EF4444; color: white; }

        /* Atajos */
        .atajos {
            display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;
        }
        .atajo {
            padding: 0.45rem 0.95rem; border-radius: 8px;
            background: white; border: 2px solid rgba(16,185,129,0.2);
            color: #059669; text-decoration: none;
            font-size: 0.82rem; font-weight: 700;
            transition: all 0.2s;
        }
        .atajo:hover { background: #ECFDF5; border-color: #10B981; }

        @media (max-width: 800px) {
            .form-agregar { grid-template-columns: 1fr; }
            .mod-item { grid-template-columns: 1fr; }
            .docente-select { width: 100%; }
        }
    </style>
</head>
<body data-rol="<?php echo $rol; ?>">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">Gestionar Modulos</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesion</a>
</div>

<div class="dashboard-container">
    <a href="../dashboard.php" class="btn-volver">&larr; Volver al inicio</a>
    <div class="page-title">Modulos por Bimestre</div>

    <?php if ($msg_parts): ?>
        <div class="alerta alerta-<?php echo $msg_parts[0]; ?>">
            <?php echo htmlspecialchars($msg_parts[1]); ?>
        </div>
    <?php endif; ?>

    <?php if ($rol === 'docente'): ?>
        <div class="aviso-docente">
            Estas viendo unicamente los modulos que te han sido asignados en este bimestre.
        </div>
    <?php endif; ?>

    <!-- Selector de bimestre -->
    <div class="bim-selector">
        <label>Bimestre</label>
        <div class="bim-tabs">
            <?php for ($b = 1; $b <= 5; $b++): ?>
                <a class="bim-tab <?php echo $b == $bim_sel ? 'active' : ''; ?>"
                   href="?bimestre=<?php echo $b; ?>">Bimestre <?php echo $b; ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <?php if ($rol === 'admin'): ?>
    <div class="atajos">
        <a class="atajo" href="modulos_estudiantes.php?bimestre=<?php echo $bim_sel; ?>">&rarr; Asignar modulos a estudiantes</a>
        <a class="atajo" href="ingresar_notas.php">&rarr; Ingresar notas</a>
    </div>
    <?php endif; ?>

    <?php if ($rol === 'admin'): ?>
    <!-- Card: Agregar nuevo módulo al bimestre -->
    <div class="card">
        <div class="card-header ch-add">
            <h3>&#10133; Agregar Modulo al Bimestre <?php echo $bim_sel; ?></h3>
            <span class="meta">Eligelo del catalogo o crea uno nuevo</span>
        </div>
        <div class="card-body">
            <form method="POST" id="form-asignar">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="accion" value="asignar">
                <input type="hidden" name="bimestre" value="<?php echo $bim_sel; ?>">
                <input type="hidden" name="bimestre_view" value="<?php echo $bim_sel; ?>">

                <div class="form-agregar">
                    <div class="campo">
                        <label>Modulo del catalogo</label>
                        <select name="modulo_formacion_id" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($catalogo as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['nombre']); ?>
                                    <?php if ($c['codigo']) echo ' (' . htmlspecialchars($c['codigo']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label>Tipo</label>
                        <select name="tipo" id="select-tipo" onchange="cambiarTipo()" required>
                            <option value="especifico">Especifico</option>
                            <option value="transversal">Transversal (todos)</option>
                            <option value="basico">Basico</option>
                        </select>
                    </div>

                    <div class="campo" id="campo-orden">
                        <label>Orden</label>
                        <select name="orden">
                            <option value="1">Modulo 1</option>
                            <option value="2">Modulo 2</option>
                        </select>
                    </div>

                    <div class="campo" id="campo-tecnico">
                        <label>Tecnico (si es especifico)</label>
                        <select name="programa_id_target">
                            <option value="">-- Selecciona tecnico --</option>
                            <?php foreach ($programas as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo" style="display:flex;flex-direction:column;">
                        <label>Docente (opcional)</label>
                        <div style="display:flex;gap:0.4rem;align-items:end;">
                            <select name="docente_id" style="flex:1;">
                                <option value="">Sin asignar</option>
                                <?php foreach ($docentes as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-asignar">Asignar</button>
                        </div>
                    </div>
                </div>

                <div class="form-toggle-row">
                    <span>El modulo no esta en el catalogo?
                        <button type="button" class="link-catalogo" onclick="abrirModal()">Crear nuevo modulo</button>
                    </span>
                    <span style="font-size:0.78rem;color:#9CA3AF;">
                        Transversal = visible para estudiantes de todos los tecnicos.
                    </span>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card: Transversales del bimestre -->
    <div class="card">
        <div class="card-header ch-transversal">
            <h3>&#127759; Modulos Transversales (todos los estudiantes)</h3>
            <span class="meta"><?php echo count($transversales); ?> en bimestre <?php echo $bim_sel; ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($transversales)): ?>
                <div class="vacio-msg">
                    No hay modulos transversales en este bimestre.
                    <?php if ($rol === 'admin'): ?>
                        Para crear uno, usa el formulario de arriba con tipo "Transversal".
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mod-lista">
                    <?php foreach ($transversales as $m): ?>
                    <div class="mod-item">
                        <div class="mod-info">
                            <div>
                                <span class="orden-tag">M<?php echo $m['orden']; ?></span>
                                <span class="tipo-tag tipo-transversal">Transversal</span>
                                <span class="nombre"><?php echo htmlspecialchars($m['modulo_nombre']); ?></span>
                            </div>
                            <?php if ($m['codigo']): ?>
                                <div class="meta">Codigo: <?php echo htmlspecialchars($m['codigo']); ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($rol === 'admin'): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="accion" value="set_docente">
                            <input type="hidden" name="pm_id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="bimestre_view" value="<?php echo $bim_sel; ?>">
                            <select name="docente_id" class="docente-select <?php echo $m['docente_id'] ? '' : 'sin'; ?>"
                                    onchange="this.form.submit()">
                                <option value="">Sin docente</option>
                                <?php foreach ($docentes as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $d['id'] == $m['docente_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <form method="POST" style="margin:0;"
                              onsubmit="return confirm('Eliminar este modulo transversal? Tambien se borraran sus notas, asistencia y asignaciones a estudiantes.');">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="programa_modulo_id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="bimestre_view" value="<?php echo $bim_sel; ?>">
                            <button type="submit" class="btn-eliminar">&#10005;</button>
                        </form>
                        <?php else: ?>
                        <div style="font-size:0.82rem;color:#374151;font-weight:600;">
                            <?php echo htmlspecialchars($m['docente_username'] ?? '—'); ?>
                        </div>
                        <a href="ingresar_notas.php?modulo_id=<?php echo $m['id']; ?>"
                           class="atajo" style="padding:0.3rem 0.7rem;font-size:0.76rem;">Notas</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cards: Por técnico -->
    <?php
    // Determinar qué técnicos mostrar
    $tecnicos_a_mostrar = [];
    if ($rol === 'admin') {
        // Mostrar todos los técnicos (incluso vacíos)
        foreach ($programas as $p) $tecnicos_a_mostrar[] = $p;
    } else {
        // Docente: solo técnicos donde tiene módulos
        foreach ($programas as $p) {
            if (!empty($mods_por_programa[(int)$p['id']])) $tecnicos_a_mostrar[] = $p;
        }
    }
    ?>

    <?php foreach ($tecnicos_a_mostrar as $tec):
        $tec_id = (int)$tec['id'];
        $mods   = $mods_por_programa[$tec_id] ?? [];
        if ($rol === 'admin' || !empty($mods)):
    ?>
    <div class="card">
        <div class="card-header ch-tecnico">
            <h3>&#127891; <?php echo htmlspecialchars($tec['nombre']); ?></h3>
            <span class="meta"><?php echo count($mods); ?> modulos en bimestre <?php echo $bim_sel; ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($mods)): ?>
                <div class="vacio-msg">Sin modulos especificos en este bimestre para este tecnico.</div>
            <?php else: ?>
                <div class="mod-lista">
                    <?php foreach ($mods as $m): ?>
                    <div class="mod-item">
                        <div class="mod-info">
                            <div>
                                <span class="orden-tag">M<?php echo $m['orden']; ?></span>
                                <span class="tipo-tag tipo-<?php echo $m['tipo']; ?>"><?php echo ucfirst($m['tipo']); ?></span>
                                <span class="nombre"><?php echo htmlspecialchars($m['modulo_nombre']); ?></span>
                            </div>
                            <?php if ($m['codigo']): ?>
                                <div class="meta">Codigo: <?php echo htmlspecialchars($m['codigo']); ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($rol === 'admin'): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="accion" value="set_docente">
                            <input type="hidden" name="pm_id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="bimestre_view" value="<?php echo $bim_sel; ?>">
                            <select name="docente_id" class="docente-select <?php echo $m['docente_id'] ? '' : 'sin'; ?>"
                                    onchange="this.form.submit()">
                                <option value="">Sin docente</option>
                                <?php foreach ($docentes as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $d['id'] == $m['docente_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <form method="POST" style="margin:0;"
                              onsubmit="return confirm('Eliminar este modulo del tecnico? Tambien se borraran sus notas, asistencia y asignaciones a estudiantes.');">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="programa_modulo_id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="bimestre_view" value="<?php echo $bim_sel; ?>">
                            <button type="submit" class="btn-eliminar">&#10005;</button>
                        </form>
                        <?php else: ?>
                        <div style="font-size:0.82rem;color:#374151;font-weight:600;">
                            <?php echo htmlspecialchars($m['docente_username'] ?? '—'); ?>
                        </div>
                        <a href="ingresar_notas.php?modulo_id=<?php echo $m['id']; ?>"
                           class="atajo" style="padding:0.3rem 0.7rem;font-size:0.76rem;">Notas</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
        endif;
    endforeach; ?>

    <?php if ($rol === 'admin'): ?>
    <!-- Modal: crear modulo en catalogo -->
    <div class="modal-bg" id="modal-catalogo">
        <div class="modal">
            <button class="modal-cierre" onclick="cerrarModal()">&#10005;</button>
            <h3>&#10133; Crear Modulo en Catalogo</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="accion" value="crear_catalogo">
                <input type="hidden" name="bimestre_view" value="<?php echo $bim_sel; ?>">
                <div class="campo" style="margin-bottom:1rem;">
                    <label>Nombre del modulo</label>
                    <input type="text" name="nombre" placeholder="Ej: Etica Laboral" required>
                </div>
                <div class="campo" style="margin-bottom:1.2rem;">
                    <label>Codigo (opcional)</label>
                    <input type="text" name="codigo" placeholder="Ej: EL-01" maxlength="20">
                </div>
                <button type="submit" class="btn-asignar" style="width:100%;">Crear en catalogo</button>
                <p style="font-size:0.75rem;color:#9CA3AF;margin-top:0.7rem;text-align:center;">
                    Despues podras asignarlo a un bimestre desde el formulario.
                </p>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<script>
function cambiarTipo() {
    var t  = document.getElementById('select-tipo').value;
    var ct = document.getElementById('campo-tecnico');
    if (!ct) return;
    if (t === 'transversal') {
        ct.style.opacity = '0.4';
        ct.querySelector('select').disabled = true;
        ct.querySelector('select').required = false;
        ct.querySelector('label').textContent = 'Tecnico (no aplica)';
    } else {
        ct.style.opacity = '1';
        ct.querySelector('select').disabled = false;
        ct.querySelector('select').required = true;
        ct.querySelector('label').textContent = 'Tecnico (requerido)';
    }
}

function abrirModal()  { document.getElementById('modal-catalogo').classList.add('show'); }
function cerrarModal() { document.getElementById('modal-catalogo').classList.remove('show'); }
document.addEventListener('click', function(e) {
    var bg = document.getElementById('modal-catalogo');
    if (bg && e.target === bg) cerrarModal();
});

if (document.getElementById('select-tipo')) cambiarTipo();

<?php if ($msg_parts): ?>
(function() {
    var t = document.getElementById('toast');
    t.textContent = <?php echo json_encode($msg_parts[1]); ?>;
    t.className   = 'toast <?php echo $msg_parts[0] === 'success' ? 'exito' : 'error'; ?> show';
    setTimeout(function() { t.classList.remove('show'); }, 3500);
})();
<?php endif; ?>
</script>
<script src="/intep/sesion.js"></script>
</body>
</html>
