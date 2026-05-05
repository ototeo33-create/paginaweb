<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

mysqli_query($conexion, "
    CREATE TABLE IF NOT EXISTS estudiante_modulo (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        estudiante_id      INT NOT NULL,
        programa_modulo_id INT NOT NULL,
        estado             VARCHAR(20) DEFAULT 'activo',
        created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (estudiante_id)      REFERENCES estudiantes(id) ON DELETE CASCADE,
        FOREIGN KEY (programa_modulo_id) REFERENCES programa_modulo(id) ON DELETE CASCADE,
        UNIQUE KEY uk_est_mod (estudiante_id, programa_modulo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$mensaje = '';

// ============================================================
// ACCIONES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'error|Token de seguridad invalido. Recarga la pagina.';
    } else {
        $accion       = $_POST['accion'] ?? '';
        $bim_post     = (int)($_POST['bimestre'] ?? 0);
        $prog_post    = (int)($_POST['programa_id'] ?? 0);

        // Sincronizar asignaciones del bimestre+técnico para los estudiantes enviados
        if ($accion === 'sincronizar') {
            $estudiante_ids = $_POST['estudiante_ids'] ?? [];
            $asign          = $_POST['asign'] ?? [];

            if (!$bim_post || !$prog_post || empty($estudiante_ids)) {
                $mensaje = 'error|Datos incompletos.';
            } else {
                // Universo de programa_modulo_ids disponibles en este bimestre:
                // - Específicos del técnico seleccionado
                // - Transversales globales (programa_id NULL)
                $stmt_uni = mysqli_prepare($conexion,
                    "SELECT id FROM programa_modulo
                     WHERE bimestre = ? AND estado = 'activo'
                       AND (programa_id = ? OR programa_id IS NULL)");
                mysqli_stmt_bind_param($stmt_uni, 'ii', $bim_post, $prog_post);
                mysqli_stmt_execute($stmt_uni);
                $res_uni = mysqli_stmt_get_result($stmt_uni);
                $pm_universo = [];
                while ($r = mysqli_fetch_assoc($res_uni)) $pm_universo[] = (int)$r['id'];

                if (empty($pm_universo)) {
                    $mensaje = 'error|No hay modulos disponibles en este bimestre.';
                } else {
                    $pm_universo_str = implode(',', $pm_universo);
                    $insertados = 0; $borrados = 0;

                    foreach ($estudiante_ids as $eid) {
                        $eid = (int)$eid;
                        if (!$eid) continue;

                        // Borrar todas las asignaciones de este estudiante en este universo
                        $stmt_del = mysqli_prepare($conexion,
                            "DELETE FROM estudiante_modulo
                             WHERE estudiante_id = ?
                               AND programa_modulo_id IN ($pm_universo_str)");
                        mysqli_stmt_bind_param($stmt_del, 'i', $eid);
                        mysqli_stmt_execute($stmt_del);
                        $borrados += mysqli_stmt_affected_rows($stmt_del);

                        // Insertar las marcadas
                        $marcados = $asign[$eid] ?? [];
                        foreach ($marcados as $pm_id) {
                            $pm_id = (int)$pm_id;
                            if (!in_array($pm_id, $pm_universo, true)) continue; // seguridad
                            $stmt_ins = mysqli_prepare($conexion,
                                "INSERT IGNORE INTO estudiante_modulo (estudiante_id, programa_modulo_id) VALUES (?, ?)");
                            mysqli_stmt_bind_param($stmt_ins, 'ii', $eid, $pm_id);
                            mysqli_stmt_execute($stmt_ins);
                            if (mysqli_stmt_affected_rows($stmt_ins) > 0) $insertados++;
                        }
                    }
                    $mensaje = 'success|Asignaciones guardadas (' . $insertados . ' nuevas, ' . $borrados . ' removidas).';
                }
            }
        }

        $qs = http_build_query(['programa_id' => $prog_post, 'bimestre' => $bim_post, 'msg' => $mensaje]);
        header("Location: modulos_estudiantes.php?$qs");
        exit;
    }
}

if (isset($_GET['msg'])) $mensaje = $_GET['msg'];
$msg_parts = $mensaje ? explode('|', $mensaje, 2) : null;

// ============================================================
// DATOS
// ============================================================
$programas = [];
$res_prog  = mysqli_query($conexion, "SELECT id, nombre FROM programas ORDER BY nombre ASC");
while ($r  = mysqli_fetch_assoc($res_prog)) $programas[] = $r;

$prog_sel = isset($_GET['programa_id']) ? (int)$_GET['programa_id'] : 0;
$bim_sel  = isset($_GET['bimestre'])    ? (int)$_GET['bimestre']    : 0;

$programa_info = null;
foreach ($programas as $p) {
    if ($p['id'] == $prog_sel) { $programa_info = $p; break; }
}

// Módulos disponibles en el bimestre+técnico (específicos del técnico + transversales globales)
$modulos_disp = [];
if ($prog_sel && $bim_sel) {
    $stmt_d = mysqli_prepare($conexion, "
        SELECT pm.id, pm.tipo, pm.programa_id, pm.orden,
               mf.nombre AS modulo_nombre,
               u.username AS docente_nombre
        FROM programa_modulo pm
        JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
        LEFT JOIN usuarios u ON pm.docente_id = u.id
        WHERE pm.bimestre = ? AND pm.estado = 'activo'
          AND (pm.programa_id = ? OR pm.programa_id IS NULL)
        ORDER BY pm.programa_id IS NULL, pm.orden ASC, mf.nombre ASC
    ");
    mysqli_stmt_bind_param($stmt_d, 'ii', $bim_sel, $prog_sel);
    mysqli_stmt_execute($stmt_d);
    $res_d = mysqli_stmt_get_result($stmt_d);
    while ($r = mysqli_fetch_assoc($res_d)) $modulos_disp[] = $r;
}

// Estudiantes del técnico
$estudiantes = [];
if ($prog_sel) {
    $stmt_e = mysqli_prepare($conexion,
        "SELECT id, nombre, documento FROM estudiantes
         WHERE programa_id = ? AND estado = 'activo'
         ORDER BY nombre ASC");
    mysqli_stmt_bind_param($stmt_e, 'i', $prog_sel);
    mysqli_stmt_execute($stmt_e);
    $res_e = mysqli_stmt_get_result($stmt_e);
    while ($r = mysqli_fetch_assoc($res_e)) $estudiantes[] = $r;
}

// Asignaciones actuales (estudiante_id => [pm_id, pm_id, ...])
$asignaciones = [];
if (!empty($estudiantes) && !empty($modulos_disp)) {
    $est_ids_str = implode(',', array_map('intval', array_column($estudiantes, 'id')));
    $pm_ids_str  = implode(',', array_map('intval', array_column($modulos_disp, 'id')));
    $res_a = mysqli_query($conexion,
        "SELECT estudiante_id, programa_modulo_id FROM estudiante_modulo
         WHERE estudiante_id IN ($est_ids_str)
           AND programa_modulo_id IN ($pm_ids_str)");
    while ($r = mysqli_fetch_assoc($res_a)) {
        $asignaciones[(int)$r['estudiante_id']][] = (int)$r['programa_modulo_id'];
    }
}

$bimestres = [1, 2, 3, 4, 5];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Modulos por Bimestre – INTEP</title>
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

        /* Filtros */
        .filtros {
            background: rgba(255,255,255,0.85); backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.4rem 1.8rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(5,150,105,0.08);
            border: 1px solid rgba(16,185,129,0.12);
            display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;
        }
        .filtros .campo label {
            display: block; font-size: 0.75rem; font-weight: 800; color: #374151;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem;
        }
        .filtros select {
            width: 100%; padding: 0.7rem 1rem;
            border: 2px solid rgba(16,185,129,0.25); border-radius: 10px;
            font-size: 0.92rem; font-weight: 600; background: white; outline: none;
            cursor: pointer; transition: border-color 0.2s;
        }
        .filtros select:focus { border-color: #10B981; }
        .info-pill {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; padding: 0.65rem 1.1rem;
            border-radius: 10px; font-size: 0.85rem; font-weight: 700;
            white-space: nowrap;
        }

        /* Bimestres rápidos (chips) */
        .bim-chips { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .bim-chip {
            padding: 0.55rem 1rem; border-radius: 10px; cursor: pointer;
            border: 2px solid rgba(16,185,129,0.25); background: white;
            font-weight: 700; font-size: 0.85rem; color: #374151;
            text-decoration: none; transition: all 0.2s;
        }
        .bim-chip:hover { border-color: #10B981; color: #059669; }
        .bim-chip.active {
            background: linear-gradient(135deg, #059669, #10B981);
            border-color: transparent; color: white;
            box-shadow: 0 4px 10px rgba(5,150,105,0.25);
        }

        /* Stats */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.9rem; margin-bottom: 1.5rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.78); backdrop-filter: blur(12px);
            border-radius: 12px; padding: 0.9rem;
            border: 1px solid rgba(16,185,129,0.1); text-align: center;
        }
        .stat-card .num { font-size: 1.7rem; font-weight: 800; line-height: 1; }
        .stat-card .lbl { font-size: 0.7rem; color: #6B7280; text-transform: uppercase;
                          font-weight: 600; margin-top: 0.3rem; }
        .num-azul { color: #3B82F6; } .num-morado { color: #7C3AED; }
        .num-rojo { color: #EF4444; } .num-naranja { color: #EA580C; }

        /* Panel matriz */
        .panel {
            background: white; border-radius: 16px;
            border: 1px solid rgba(16,185,129,0.1);
            box-shadow: 0 4px 20px rgba(5,150,105,0.08); overflow: hidden;
        }
        .panel-header {
            background: #022C22; color: white; padding: 1rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
        }
        .panel-header h3 { font-size: 1rem; margin: 0; }

        .save-bar {
            padding: 0.9rem 1.5rem;
            background: linear-gradient(135deg, #064E3B, #065f46);
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .save-bar .info { font-size: 0.85rem; color: rgba(255,255,255,0.78); flex: 1; }
        .save-bar .info strong { color: #6EE7B7; }
        .btn-save {
            padding: 0.6rem 1.6rem;
            background: linear-gradient(135deg, #10B981, #34D399);
            color: #022C22; border: none; border-radius: 10px;
            font-size: 0.9rem; font-weight: 800; cursor: pointer; transition: all 0.2s;
        }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(16,185,129,0.4); }

        /* Buscador */
        .buscador-wrap { padding: 0.7rem 1.2rem; border-bottom: 1px solid #F0FDF4; }
        .buscador {
            width: 100%; padding: 0.55rem 1rem;
            border: 2px solid rgba(16,185,129,0.2); border-radius: 8px;
            font-size: 0.88rem; outline: none; transition: border-color 0.2s;
        }
        .buscador:focus { border-color: #10B981; }

        /* Matriz */
        .matriz-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; max-height: 70vh; }
        .matriz { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.85rem; }
        .matriz thead th {
            position: sticky; top: 0; z-index: 3;
            background: #064E3B; color: white;
            padding: 0.7rem 0.5rem; font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.3px;
            text-align: center; vertical-align: bottom;
        }
        .matriz thead th.col-est {
            text-align: left; min-width: 220px;
            position: sticky; left: 0; top: 0; z-index: 4;
            background: #064E3B;
        }
        .matriz th.col-mod {
            min-width: 110px; max-width: 140px;
            white-space: normal; word-wrap: break-word;
        }
        .matriz th.col-mod .mod-nom { font-size: 0.78rem; line-height: 1.15; display: block; }
        .matriz th.col-mod .mod-tipo {
            display: inline-block; padding: 0.08rem 0.4rem; border-radius: 4px;
            font-size: 0.6rem; margin-top: 0.25rem;
        }
        .matriz th.col-mod .tip-especifico  { background: #FEE2E2; color: #991B1B; }
        .matriz th.col-mod .tip-transversal { background: #FEF3C7; color: #92400E; }
        .matriz th.col-mod .tip-basico      { background: #DCFCE7; color: #166534; }
        .matriz th.col-mod .mod-doc { display: block; font-size: 0.65rem; opacity: 0.8; margin-top: 0.2rem; font-weight: 500; }
        .matriz th.col-acc {
            min-width: 60px; padding: 0.3rem 0.25rem; font-size: 0.65rem;
            background: #022C22;
        }

        .matriz tbody td {
            padding: 0.5rem; border-bottom: 1px solid #F0FDF4; text-align: center;
        }
        .matriz tbody td.col-est {
            text-align: left; padding: 0.55rem 0.8rem;
            position: sticky; left: 0; z-index: 1;
            background: white; border-right: 1px solid #ECFDF5;
        }
        .matriz tbody tr:hover td { background: #F0FDF4; }
        .matriz tbody tr:hover td.col-est { background: #F0FDF4; }

        .est-nombre { font-weight: 600; color: #1F2937;
                      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .est-doc { font-size: 0.72rem; color: #9CA3AF; }

        .check-mod { width: 18px; height: 18px; accent-color: #059669; cursor: pointer; }

        .btn-col-toggle {
            background: rgba(255,255,255,0.18); color: white; border: 1px solid rgba(255,255,255,0.25);
            padding: 0.18rem 0.4rem; border-radius: 5px; font-size: 0.65rem; font-weight: 700;
            cursor: pointer; transition: all 0.15s; white-space: nowrap;
        }
        .btn-col-toggle:hover { background: rgba(255,255,255,0.3); }

        /* Toast */
        .toast {
            position: fixed; bottom: 2rem; right: 2rem; z-index: 1000;
            padding: 0.9rem 1.5rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transform: translateY(100px); opacity: 0; transition: all 0.3s;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.exito { background: #059669; color: white; }
        .toast.error { background: #EF4444; color: white; }

        .sin-datos { text-align: center; padding: 3rem; color: #9CA3AF; }
        .sin-datos .ico { font-size: 2.5rem; margin-bottom: 0.8rem; }

        .alerta { padding: 0.8rem 1rem; border-radius: 10px; margin-bottom: 1.2rem; font-size: 0.88rem; font-weight: 500; }
        .alerta-success { background: rgba(16,185,129,0.1); color: #065F46; border-left: 4px solid #10B981; }
        .alerta-error   { background: rgba(239,68,68,0.1);  color: #991B1B; border-left: 4px solid #EF4444; }

        .leyenda {
            display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
            font-size: 0.78rem; color: #6B7280; margin-bottom: 1rem; padding: 0.5rem 0;
        }
        .leyenda .item { display: inline-flex; align-items: center; gap: 0.35rem; }
        .leyenda .swatch { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }

        @media (max-width: 720px) {
            .filtros { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-rol="admin">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">Asignar Modulos por Bimestre</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesion</a>
</div>

<div class="dashboard-container">
    <a href="../dashboard.php" class="btn-volver">&larr; Volver al inicio</a>
    <div class="page-title">Asignar Modulos a Estudiantes (por Bimestre)</div>

    <?php if ($msg_parts): ?>
        <div class="alerta alerta-<?php echo $msg_parts[0]; ?>">
            <?php echo htmlspecialchars($msg_parts[1]); ?>
        </div>
    <?php endif; ?>

    <!-- Filtros: Técnico + Bimestre -->
    <div class="filtros">
        <div class="campo">
            <label>Tecnico / Programa</label>
            <select onchange="cambiarFiltros('programa_id', this.value)">
                <option value="">-- Selecciona un tecnico --</option>
                <?php foreach ($programas as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $prog_sel ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label>Bimestre</label>
            <div class="bim-chips">
                <?php foreach ($bimestres as $b): ?>
                    <a class="bim-chip <?php echo $b == $bim_sel ? 'active' : ''; ?>"
                       href="?<?php echo http_build_query(['programa_id' => $prog_sel, 'bimestre' => $b]); ?>">
                        Bim <?php echo $b; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($programa_info && $bim_sel): ?>
            <span class="info-pill">
                <?php echo count($estudiantes); ?> estudiantes &middot;
                <?php echo count($modulos_disp); ?> modulos
            </span>
        <?php endif; ?>
    </div>

    <?php if (!$prog_sel): ?>
        <div class="sin-datos">
            <div class="ico">&#128218;</div>
            <p>Selecciona un tecnico para empezar.</p>
        </div>
    <?php elseif (!$bim_sel): ?>
        <div class="sin-datos">
            <div class="ico">&#128197;</div>
            <p>Selecciona un bimestre para ver los modulos disponibles.</p>
        </div>
    <?php elseif (empty($modulos_disp)): ?>
        <div class="sin-datos">
            <div class="ico">&#128203;</div>
            <p>No hay modulos en este bimestre para el tecnico seleccionado.</p>
            <p style="font-size:0.85rem;">Ve a <a href="gestionar_modulos.php" style="color:#059669;font-weight:700;">Gestionar Modulos</a> para agregarlos.</p>
        </div>
    <?php elseif (empty($estudiantes)): ?>
        <div class="sin-datos">
            <div class="ico">&#128101;</div>
            <p>No hay estudiantes activos en este tecnico.</p>
        </div>
    <?php else:
        // Stats
        $cnt_especificos  = 0; $cnt_transversales = 0;
        foreach ($modulos_disp as $m) {
            if ($m['tipo'] === 'transversal' || $m['programa_id'] === null) $cnt_transversales++;
            else $cnt_especificos++;
        }
        $est_completos = 0; $est_sin = 0; $est_parciales = 0;
        foreach ($estudiantes as $e) {
            $cnt = isset($asignaciones[$e['id']]) ? count($asignaciones[$e['id']]) : 0;
            if ($cnt == 0) $est_sin++;
            elseif ($cnt == count($modulos_disp)) $est_completos++;
            else $est_parciales++;
        }
    ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="num num-azul"><?php echo count($estudiantes); ?></div>
            <div class="lbl">Estudiantes</div>
        </div>
        <div class="stat-card">
            <div class="num num-morado"><?php echo $cnt_especificos; ?></div>
            <div class="lbl">Espec del tecnico</div>
        </div>
        <div class="stat-card">
            <div class="num num-naranja"><?php echo $cnt_transversales; ?></div>
            <div class="lbl">Transversales</div>
        </div>
        <div class="stat-card">
            <div class="num num-rojo"><?php echo $est_sin; ?></div>
            <div class="lbl">Sin modulos</div>
        </div>
    </div>

    <div class="leyenda">
        <span class="item"><span class="swatch" style="background:#FEE2E2"></span>Especifico</span>
        <span class="item"><span class="swatch" style="background:#FEF3C7"></span>Transversal (global)</span>
        <span class="item"><span class="swatch" style="background:#DCFCE7"></span>Basico</span>
    </div>

    <!-- Panel matriz -->
    <div class="panel">
        <div class="panel-header">
            <h3>
                <?php echo htmlspecialchars($programa_info['nombre']); ?>
                &middot; Bimestre <?php echo $bim_sel; ?>
            </h3>
        </div>

        <form method="POST" id="form-sync">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="accion" value="sincronizar">
            <input type="hidden" name="programa_id" value="<?php echo $prog_sel; ?>">
            <input type="hidden" name="bimestre" value="<?php echo $bim_sel; ?>">

            <div class="save-bar">
                <div class="info">
                    Marca los modulos que cada estudiante tomara este bimestre.
                    Los <strong>transversales</strong> son los mismos para todos los tecnicos.
                </div>
                <button type="submit" class="btn-save"
                        onclick="return confirm('Guardar cambios de asignacion para este bimestre y tecnico?')">
                    &#128190; Guardar cambios
                </button>
            </div>

            <div class="buscador-wrap">
                <input type="text" class="buscador" id="buscador"
                       placeholder="Buscar estudiante..." oninput="filtrar()">
            </div>

            <div class="matriz-wrap">
                <table class="matriz">
                    <thead>
                        <tr>
                            <th class="col-est">Estudiante</th>
                            <?php foreach ($modulos_disp as $m):
                                $es_transv = ($m['programa_id'] === null) || ($m['tipo'] === 'transversal');
                                $tipo_cls  = $m['tipo'] ?: 'especifico';
                            ?>
                            <th class="col-mod">
                                <span class="mod-nom"><?php echo htmlspecialchars($m['modulo_nombre']); ?></span>
                                <span class="mod-tipo tip-<?php echo $tipo_cls; ?>">
                                    <?php echo $es_transv ? 'TRANSV' : strtoupper(substr($tipo_cls, 0, 5)); ?>
                                </span>
                                <?php if ($m['docente_nombre']): ?>
                                    <span class="mod-doc">&#128100; <?php echo htmlspecialchars($m['docente_nombre']); ?></span>
                                <?php else: ?>
                                    <span class="mod-doc" style="color:#FCA5A5;">Sin docente</span>
                                <?php endif; ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th class="col-est col-acc">Asignar a todos &darr;</th>
                            <?php foreach ($modulos_disp as $m): ?>
                            <th class="col-acc">
                                <button type="button" class="btn-col-toggle"
                                        onclick="toggleColumna(<?php echo $m['id']; ?>)">
                                    Todos
                                </button>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $e):
                            $eid = (int)$e['id'];
                            $mis_pm = $asignaciones[$eid] ?? [];
                        ?>
                        <tr class="fila-est"
                            data-busca="<?php echo strtolower(htmlspecialchars($e['nombre'] . ' ' . $e['documento'])); ?>">
                            <td class="col-est">
                                <input type="hidden" name="estudiante_ids[]" value="<?php echo $eid; ?>">
                                <div class="est-nombre" title="<?php echo htmlspecialchars($e['nombre']); ?>">
                                    <?php echo htmlspecialchars($e['nombre']); ?>
                                </div>
                                <div class="est-doc">CC <?php echo htmlspecialchars($e['documento']); ?></div>
                            </td>
                            <?php foreach ($modulos_disp as $m):
                                $marcado = in_array((int)$m['id'], $mis_pm, true);
                            ?>
                            <td>
                                <input type="checkbox"
                                       class="check-mod col-<?php echo $m['id']; ?>"
                                       name="asign[<?php echo $eid; ?>][]"
                                       value="<?php echo $m['id']; ?>"
                                       <?php echo $marcado ? 'checked' : ''; ?>>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<script>
function cambiarFiltros(key, val) {
    var url = new URL(window.location.href);
    if (val) url.searchParams.set(key, val); else url.searchParams.delete(key);
    url.searchParams.delete('msg');
    window.location.href = url.toString();
}

function toggleColumna(modId) {
    var checks = document.querySelectorAll('.col-' + modId);
    var algunoSinMarcar = false;
    checks.forEach(function(c) { if (!c.disabled && !c.checked && !c.closest('.fila-est').classList.contains('hidden')) algunoSinMarcar = true; });
    checks.forEach(function(c) {
        if (c.disabled) return;
        if (c.closest('.fila-est').classList.contains('hidden')) return;
        c.checked = algunoSinMarcar;
    });
}

function filtrar() {
    var q = document.getElementById('buscador').value.toLowerCase().trim();
    document.querySelectorAll('.fila-est').forEach(function(tr) {
        var s = tr.dataset.busca || '';
        var match = !q || s.includes(q);
        tr.classList.toggle('hidden', !match);
        tr.style.display = match ? '' : 'none';
    });
}

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
