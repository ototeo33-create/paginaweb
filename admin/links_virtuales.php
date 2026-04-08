<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }
if (!in_array($_SESSION['usuario_rol'], ['admin', 'docente'])) { header('Location: ../dashboard.php'); exit; }

$mensaje = '';

// Procesar guardado de link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'error|Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';
        $materia_id = (int)($_POST['materia_id'] ?? 0);
        $programa_id = (int)($_POST['programa_id'] ?? 0);
        $dia = $_POST['dia'] ?? '';
        $link = trim($_POST['link_virtual'] ?? '');

        if ($accion === 'guardar_link' && $materia_id && $programa_id && $dia) {
            $q = "UPDATE horarios SET link_virtual = ? WHERE programa_modulo_id = ? AND programa_id = ? AND dia = ?";
            $stmt = mysqli_prepare($conexion, $q);
            mysqli_stmt_bind_param($stmt, 'siis', $link, $materia_id, $programa_id, $dia);
            mysqli_stmt_execute($stmt);
            $afectados = mysqli_affected_rows($conexion);
            if ($link) {
                $mensaje = "success|✅ Link guardado para $afectados estudiante(s).";
            } else {
                $mensaje = "success|🗑️ Link eliminado correctamente.";
            }
        }
    }
}

if (isset($_GET['msg'])) $mensaje = $_GET['msg'];

// Obtener clases únicas agrupadas (sin repetir por estudiante)
$clases = [];
$q = "SELECT h.programa_modulo_id as materia_id, h.programa_id, h.dia, h.hora_inicio, h.hora_fin, h.salon, h.bimestre_id,
             mf.nombre as materia, p.nombre as programa,
             b.numero as bimestre_num, b.anio as bimestre_anio,
             h.link_virtual,
             COUNT(DISTINCT h.estudiante_id) as total_estudiantes
      FROM horarios h
      JOIN programa_modulo pm ON h.programa_modulo_id = pm.id
      JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
      JOIN programas p ON h.programa_id = p.id
      LEFT JOIN bimestres b ON h.bimestre_id = b.id
      GROUP BY h.programa_modulo_id, h.programa_id, h.dia, h.hora_inicio, h.hora_fin
      ORDER BY p.nombre, mf.nombre, FIELD(h.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado')";
$res = mysqli_query($conexion, $q);
while ($c = mysqli_fetch_assoc($res)) $clases[] = $c;

// Agrupar por programa > materia
$agrupado = [];
foreach ($clases as $c) {
    $key = $c['programa'] . '||' . $c['materia'];
    if (!isset($agrupado[$key])) {
        $agrupado[$key] = [
            'programa' => $c['programa'],
            'materia' => $c['materia'],
            'clases' => []
        ];
    }
    $agrupado[$key]['clases'][] = $c;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="/intep/favicon/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Links de Clases Virtuales – INTEP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/intep/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/intep/favicon/favicon-32x32.png">
    <link rel="manifest" href="/intep/favicon/site.webmanifest">
    <meta name="theme-color" content="#009B48">
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        .page-container { max-width: 1100px; margin: 1rem auto; padding: 0 1rem; }

        .grupo-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16,185,129,0.1);
        }
        .grupo-header {
            background: linear-gradient(135deg, #072918, #0d5a2a);
            color: white;
            padding: 1rem 1.5rem;
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .grupo-header .programa-tag {
            background: rgba(255,255,255,0.12);
            padding: 0.25rem 0.8rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
        }
        .grupo-header .materia-name {
            font-size: 1.05rem;
            font-weight: 700;
        }

        .clase-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            flex-wrap: wrap;
            transition: background 0.15s;
        }
        .clase-row:last-child { border-bottom: none; }
        .clase-row:hover { background: #f0fdf4; }

        .clase-dia {
            background: #d1fae5;
            color: #065f46;
            font-weight: 800;
            font-size: 0.82rem;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            min-width: 50px;
            text-align: center;
            flex-shrink: 0;
        }
        .clase-info {
            font-size: 0.85rem;
            color: #555;
            min-width: 140px;
            flex-shrink: 0;
        }
        .clase-info strong { color: #1a1a1a; }
        .clase-estudiantes {
            background: #f3f4f6;
            color: #6b7280;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .link-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            min-width: 250px;
        }
        .link-input {
            flex: 1;
            padding: 0.5rem 0.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .link-input:focus { border-color: #10B981; }
        .link-input.tiene-link { border-color: #10B981; background: #f0fdf4; }

        .btn-guardar-link {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-guardar-link:hover { transform: translateY(-1px); box-shadow: 0 3px 12px rgba(5,150,105,0.3); }

        .btn-borrar-link {
            background: #fee2e2;
            color: #dc2626;
            border: 2px solid #fecaca;
            padding: 0.45rem 0.7rem;
            border-radius: 8px;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .btn-borrar-link:hover { background: #fecaca; border-color: #ef4444; }

        .estado-link {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        .estado-activo { color: #059669; }
        .estado-vacio { color: #9ca3af; }

        .alerta-success { background: rgba(16,185,129,0.1); color: #065f46; padding: 0.8rem 1rem; border-radius: 10px; margin-bottom: 1rem; border-left: 4px solid #10b981; font-size: 0.88rem; }
        .alerta-error { background: rgba(239,68,68,0.1); color: #991b1b; padding: 0.8rem 1rem; border-radius: 10px; margin-bottom: 1rem; border-left: 4px solid #ef4444; font-size: 0.88rem; }

        .empty-state { text-align: center; padding: 4rem 1rem; color: #aaa; }
        .empty-state p { font-size: 0.95rem; margin-top: 0.5rem; }

        .resumen-bar {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            margin-bottom: 1.2rem;
            display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;
            border: 1px solid rgba(16,185,129,0.1);
            font-size: 0.85rem; color: #555;
        }
        .resumen-bar .stat { font-weight: 700; color: #059669; }

        @media(max-width:768px) {
            .clase-row { flex-direction: column; align-items: stretch; gap: 0.6rem; }
            .link-form { min-width: 100%; }
            .clase-info { min-width: auto; }
        }
    </style>
</head>
<body data-rol="<?php echo $_SESSION['usuario_rol'] ?? ''; ?>">

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info">🔗 Links de Clases Virtuales</span>
    <a href="../logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="page-container">

    <a href="../dashboard.php" class="btn-volver" style="display:inline-block;margin-bottom:1rem;font-size:0.82rem;color:#059669;text-decoration:none;font-weight:600;">← Volver al inicio</a>

    <?php
    if ($mensaje) {
        $parts = explode('|', $mensaje, 2);
        $tipo = $parts[0]; $texto = $parts[1] ?? $mensaje;
        echo '<div class="alerta-' . ($tipo === 'success' ? 'success' : 'error') . '">' . htmlspecialchars($texto) . '</div>';
    }
    ?>

    <?php
    $total_clases = count($clases);
    $con_link = count(array_filter($clases, fn($c) => !empty($c['link_virtual'])));
    ?>
    <div class="resumen-bar">
        <span>📊 Total de clases: <span class="stat"><?php echo $total_clases; ?></span></span>
        <span>🔗 Con link activo: <span class="stat"><?php echo $con_link; ?></span></span>
        <span>⏳ Sin link: <span class="stat" style="color:#9ca3af;"><?php echo $total_clases - $con_link; ?></span></span>
    </div>

    <?php if (empty($agrupado)): ?>
        <div class="empty-state">
            <div style="font-size:3rem;">🔗</div>
            <p>No hay clases con horarios asignados aún.</p>
        </div>
    <?php else: ?>

        <?php foreach ($agrupado as $grupo): ?>
        <div class="grupo-card">
            <div class="grupo-header">
                <span class="programa-tag"><?php echo htmlspecialchars($grupo['programa']); ?></span>
                <span class="materia-name"><?php echo htmlspecialchars($grupo['materia']); ?></span>
            </div>

            <?php foreach ($grupo['clases'] as $c):
                $tiene_link = !empty($c['link_virtual']);
                $dias_cortos = ['Lunes'=>'LUN','Martes'=>'MAR','Miércoles'=>'MIÉ','Jueves'=>'JUE','Viernes'=>'VIE','Sábado'=>'SÁB'];
                $dia_corto = $dias_cortos[$c['dia']] ?? $c['dia'];
            ?>
            <div class="clase-row">
                <span class="clase-dia"><?php echo $dia_corto; ?></span>
                <span class="clase-info">
                    <strong><?php echo htmlspecialchars($c['dia']); ?></strong>
                    · <?php echo substr($c['hora_inicio'], 0, 5); ?> – <?php echo substr($c['hora_fin'], 0, 5); ?>
                    <?php if ($c['salon']): ?> · Salón <?php echo htmlspecialchars($c['salon']); ?><?php endif; ?>
                </span>
                <span class="clase-estudiantes">👥 <?php echo $c['total_estudiantes']; ?> estudiante<?php echo $c['total_estudiantes'] != 1 ? 's' : ''; ?></span>

                <form method="POST" class="link-form" onsubmit="return true;">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="accion" value="guardar_link">
                    <input type="hidden" name="materia_id" value="<?php echo $c['materia_id']; ?>">
                    <input type="hidden" name="programa_id" value="<?php echo $c['programa_id']; ?>">
                    <input type="hidden" name="dia" value="<?php echo htmlspecialchars($c['dia']); ?>">
                    <input type="text" name="link_virtual"
                           class="link-input <?php echo $tiene_link ? 'tiene-link' : ''; ?>"
                           value="<?php echo htmlspecialchars($c['link_virtual'] ?? ''); ?>"
                           placeholder="https://meet.google.com/xxx-xxxx-xxx">
                    <button type="submit" class="btn-guardar-link">💾 Guardar</button>
                    <?php if ($tiene_link): ?>
                        <button type="submit" class="btn-borrar-link" onclick="this.form.querySelector('[name=link_virtual]').value='';">🗑️</button>
                    <?php endif; ?>
                </form>

                <span class="estado-link <?php echo $tiene_link ? 'estado-activo' : 'estado-vacio'; ?>">
                    <?php echo $tiene_link ? '● Activo' : '○ Sin link'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<script src="/intep/sesion.js"></script>
</body>
</html>
