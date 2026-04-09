<?php
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol        = $_SESSION['usuario_rol'];
$usuario_id = (int)$_SESSION['usuario_id'];

// Docentes y admins van a su propio panel
if (in_array($rol, ['docente', 'admin'])) {
    header('Location: /intep/admin/materiales.php');
    exit;
}

// Solo estudiantes continúan
$est_id = $_SESSION['estudiante_id'] ?? null;
if (!$est_id) {
    header('Location: dashboard.php');
    exit;
}

// Verificar que la tabla existe (auto-migración defensiva)
$tabla_existe = mysqli_query($conexion, "SHOW TABLES LIKE 'material_clase'");
if (mysqli_num_rows($tabla_existe) === 0) {
    $materiales_por_modulo = [];
    $tiene_materiales = false;
} else {

// ============================================================
// OBTENER MATERIALES DEL PROGRAMA DEL ESTUDIANTE
// Solo módulos de su programa, bimestre activo primero
// ============================================================
$sql = "
    SELECT mc.id, mc.titulo, mc.descripcion, mc.categoria,
           mc.archivo_nombre, mc.archivo_tipo, mc.archivo_tamano,
           mc.fecha_subida,
           mf.nombre AS modulo,
           pm.bimestre,
           p.nombre  AS programa_nombre,
           u.username AS docente_nombre
    FROM material_clase mc
    JOIN programa_modulo pm ON mc.programa_modulo_id = pm.id
    JOIN modulos_formacion mf ON pm.modulo_formacion_id = mf.id
    JOIN programas p ON pm.programa_id = p.id
    LEFT JOIN usuarios u ON mc.docente_id = u.id
    JOIN estudiantes e ON e.programa_id = pm.programa_id
    WHERE e.id = ? AND mc.activo = 1
    ORDER BY pm.bimestre DESC, mf.nombre ASC, mc.fecha_subida DESC
";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, 'i', $est_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$materiales_por_modulo = [];
while ($row = mysqli_fetch_assoc($res)) {
    $key = 'Bim. ' . $row['bimestre'] . ' › ' . $row['modulo'];
    $materiales_por_modulo[$key][] = $row;
}
$tiene_materiales = !empty($materiales_por_modulo);

} // fin else tabla existe

// Helpers
function formatBytes($b) {
    if ($b < 1024)     return $b . ' B';
    if ($b < 1048576)  return round($b / 1024, 1) . ' KB';
    return round($b / 1048576, 1) . ' MB';
}
function iconoCategoria($cat) {
    $i = ['general'=>'📄','guia'=>'📖','taller'=>'🔧','evaluacion'=>'📝','recurso'=>'🎯'];
    return $i[$cat] ?? '📄';
}
function labelCategoria($cat) {
    $l = ['general'=>'General','guia'=>'Guía de Clase','taller'=>'Taller','evaluacion'=>'Evaluación','recurso'=>'Recurso Extra'];
    return $l[$cat] ?? 'General';
}
function iconoTipo($mime) {
    if (str_contains($mime, 'pdf'))         return '🔴';
    if (str_contains($mime, 'word'))        return '🔵';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return '🟢';
    if (str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) return '🟠';
    if (str_contains($mime, 'zip'))         return '📦';
    if (str_contains($mime, 'image'))       return '🖼️';
    if (str_contains($mime, 'video'))       return '🎬';
    return '📎';
}
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
            max-width: 900px;
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

        /* Buscador */
        .buscador-wrap {
            background: white;
            border-radius: 14px;
            padding: 1rem 1.2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid rgba(16,185,129,0.1);
            margin-bottom: 1.5rem;
            display: flex; gap: 0.8rem; align-items: center;
        }
        .buscador-wrap input {
            flex: 1; border: none; outline: none;
            font-size: 0.9rem; color: #111827; background: transparent;
        }
        .buscador-wrap .search-icon { font-size: 1.1rem; color: #9CA3AF; }

        /* Grupo de módulo */
        .modulo-grupo { margin-bottom: 2rem; }

        .modulo-titulo {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.82rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.2px;
            color: #059669; margin-bottom: 0.8rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #D1FAE5;
        }

        /* Item material */
        .material-item {
            background: white;
            border-radius: 14px;
            padding: 1.1rem 1.3rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(16,185,129,0.08);
            display: flex; align-items: center; gap: 1rem;
            transition: all 0.25s;
            margin-bottom: 0.7rem;
        }
        .material-item:hover {
            box-shadow: 0 6px 20px rgba(5,150,105,0.12);
            transform: translateY(-2px);
            border-color: rgba(16,185,129,0.25);
        }

        .mat-type-icon {
            width: 46px; height: 46px;
            border-radius: 12px; background: #ECFDF5;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }

        .mat-info { flex: 1; min-width: 0; }
        .mat-info .titulo {
            font-weight: 700; color: #022C22; font-size: 0.95rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .mat-info .meta {
            display: flex; flex-wrap: wrap; gap: 0.6rem;
            font-size: 0.76rem; color: #9CA3AF; margin-top: 0.3rem;
        }
        .mat-info .descripcion {
            font-size: 0.82rem; color: #6B7280; margin-top: 0.3rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .badge-cat {
            display: inline-block; padding: 0.18rem 0.6rem;
            border-radius: 99px; font-size: 0.7rem; font-weight: 600;
            background: #D1FAE5; color: #065F46;
        }

        .btn-descargar {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.55rem 1.1rem;
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; border-radius: 10px;
            font-size: 0.82rem; font-weight: 700;
            text-decoration: none; flex-shrink: 0;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-descargar:hover {
            transform: scale(1.04);
            box-shadow: 0 4px 14px rgba(5,150,105,0.35);
        }

        .empty-state {
            text-align: center; padding: 4rem 1rem;
            color: #9CA3AF;
        }
        .empty-state .es-icon { font-size: 3rem; margin-bottom: 1rem; }
        .empty-state p { font-size: 0.9rem; }

        @media (max-width: 600px) {
            .page-header { padding: 1.5rem; }
            .page-header h1 { font-size: 1.3rem; }
            .material-item { flex-wrap: wrap; }
            .btn-descargar { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="dashboard-header">
    <h1><img src="/intep/img/Logo.png" alt="INTEP" height="36"></h1>
    <span class="usuario-info"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?> · Estudiante</span>
    <a href="/intep/logout.php" class="btn-salir">Cerrar sesión</a>
</div>

<div class="page-container">
    <a href="/intep/dashboard.php" class="btn-back">← Volver al inicio</a>

    <div class="page-header">
        <h1>📚 Material de Clase</h1>
        <p>Descarga las guías, talleres y recursos que tus profesores han compartido contigo</p>
    </div>

    <?php if ($tiene_materiales): ?>
    <!-- Buscador -->
    <div class="buscador-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="buscador" placeholder="Buscar por título, módulo o categoría..." autocomplete="off">
    </div>
    <?php endif; ?>

    <?php if (!$tiene_materiales): ?>
    <div class="empty-state">
        <div class="es-icon">🗂️</div>
        <p>Aún no hay materiales disponibles para tu programa.<br>Tu profesor los publicará pronto.</p>
    </div>
    <?php else: ?>

    <div id="lista-materiales">
    <?php foreach ($materiales_por_modulo as $grupo => $items): ?>
    <div class="modulo-grupo" data-grupo="<?php echo htmlspecialchars(strtolower($grupo)); ?>">
        <div class="modulo-titulo">
            📦 <?php echo htmlspecialchars($grupo); ?>
            <span style="font-weight:400; color:#9CA3AF;">(<?php echo count($items); ?> archivo<?php echo count($items) !== 1 ? 's' : ''; ?>)</span>
        </div>

        <?php foreach ($items as $mat): ?>
        <div class="material-item" data-buscar="<?php echo htmlspecialchars(strtolower($mat['titulo'] . ' ' . $mat['modulo'] . ' ' . $mat['categoria'] . ' ' . ($mat['descripcion'] ?? ''))); ?>">
            <div class="mat-type-icon"><?php echo iconoTipo($mat['archivo_tipo'] ?? ''); ?></div>
            <div class="mat-info">
                <div class="titulo"><?php echo htmlspecialchars($mat['titulo']); ?></div>
                <div class="meta">
                    <span class="badge-cat"><?php echo iconoCategoria($mat['categoria']) . ' ' . labelCategoria($mat['categoria']); ?></span>
                    <span>📎 <?php echo htmlspecialchars($mat['archivo_nombre']); ?></span>
                    <span>⚖️ <?php echo formatBytes($mat['archivo_tamano']); ?></span>
                    <span>📅 <?php echo date('d/m/Y', strtotime($mat['fecha_subida'])); ?></span>
                    <?php if (!empty($mat['docente_nombre'])): ?>
                    <span>👨‍🏫 <?php echo htmlspecialchars($mat['docente_nombre']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($mat['descripcion'])): ?>
                <div class="descripcion"><?php echo htmlspecialchars($mat['descripcion']); ?></div>
                <?php endif; ?>
            </div>
            <a href="/intep/descargar_material.php?id=<?php echo $mat['id']; ?>"
               class="btn-descargar" target="_blank">
                ⬇️ Descargar
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <div id="sin-resultados" style="display:none;" class="empty-state">
        <div class="es-icon">🔍</div>
        <p>No se encontraron materiales con ese término.</p>
    </div>

    <?php endif; ?>
</div>

<script>
const buscador = document.getElementById('buscador');
if (buscador) {
    buscador.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        let total = 0;

        document.querySelectorAll('.modulo-grupo').forEach(grupo => {
            const items = grupo.querySelectorAll('.material-item');
            let visibles = 0;
            items.forEach(item => {
                const texto = item.dataset.buscar || '';
                const grupo_texto = grupo.dataset.grupo || '';
                const match = texto.includes(term) || grupo_texto.includes(term);
                item.style.display = match ? '' : 'none';
                if (match) visibles++;
            });
            grupo.style.display = visibles > 0 ? '' : 'none';
            total += visibles;
        });

        const sinRes = document.getElementById('sin-resultados');
        if (sinRes) sinRes.style.display = total === 0 ? '' : 'none';
    });
}
</script>

</body>
</html>
