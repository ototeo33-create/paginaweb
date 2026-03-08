<?php
require_once 'config.php';

// Verificar que esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Solo estudiantes pueden ver esta página
if ($_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: dashboard.php');
    exit;
}

$estudiante_id = $_SESSION['estudiante_id'];

// Obtener nombre del estudiante y programa
$query_est = "SELECT e.nombre as estudiante_nombre, p.nombre as programa_nombre 
              FROM estudiantes e 
              JOIN programas p ON e.programa_id = p.id 
              WHERE e.id = ?";
$stmt_est = mysqli_prepare($conexion, $query_est);
if ($stmt_est) {
    mysqli_stmt_bind_param($stmt_est, 'i', $estudiante_id);
    mysqli_stmt_execute($stmt_est);
    $res_est = mysqli_stmt_get_result($stmt_est);
    $info_estudiante = mysqli_fetch_assoc($res_est);
}

// Obtener notas del estudiante con módulos y materias
$query = "SELECT n.*, 
                 mod_t.nombre AS modulo_nombre, 
                 mod_t.bimestre,
                 mod_t.orden,
                 mat.nombre AS materia_nombre
          FROM notas n
          JOIN modulos mod_t ON n.modulo_id = mod_t.id
          JOIN materias mat ON mod_t.materia_id = mat.id
          WHERE n.estudiante_id = ?
          ORDER BY mod_t.bimestre ASC, mod_t.orden ASC";

$stmt = mysqli_prepare($conexion, $query);
$notas_por_bimestre = [];

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $estudiante_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $bimestre = $fila['bimestre'];
        if (!isset($notas_por_bimestre[$bimestre])) {
            $notas_por_bimestre[$bimestre] = [];
        }
        $notas_por_bimestre[$bimestre][] = $fila;
    }
} else {
    $error_db = mysqli_error($conexion);
}

// Función para clase CSS según nota
function claseNota($nota) {
    if ($nota === null || $nota == 0) return '';
    if ($nota >= 3.5) return 'nota-alta';
    if ($nota >= 3.0) return 'nota-media';
    return 'nota-baja';
}

// Función para mostrar nota o pendiente
function mostrarNota($nota) {
    if ($nota === null || $nota == 0) return '<span class="nota-pendiente">—</span>';
    return number_format($nota, 1);
}

// Función para badge de estado
function badgeEstado($aprobado, $nota_final) {
    if ($nota_final === null || $nota_final == 0) {
        return '<span class="badge badge-pendiente">⏳ Pendiente</span>';
    }
    if ($aprobado) {
        return '<span class="badge badge-aprobado">✅ Aprobado</span>';
    }
    return '<span class="badge badge-reprobado">❌ Reprobado</span>';
}

// Calcular promedio general
$total_notas = 0;
$count_notas = 0;
foreach ($notas_por_bimestre as $modulos) {
    foreach ($modulos as $nota) {
        if ($nota['nota_final'] !== null && $nota['nota_final'] > 0) {
            $total_notas += $nota['nota_final'];
            $count_notas++;
        }
    }
}
$promedio_general = $count_notas > 0 ? $total_notas / $count_notas : 0;

// Contar aprobados y reprobados
$total_aprobados = 0;
$total_reprobados = 0;
$total_pendientes = 0;
foreach ($notas_por_bimestre as $modulos) {
    foreach ($modulos as $nota) {
        if ($nota['nota_final'] === null || $nota['nota_final'] == 0) {
            $total_pendientes++;
        } elseif ($nota['aprobado']) {
            $total_aprobados++;
        } else {
            $total_reprobados++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Notas – INTEP</title>
    <link rel="stylesheet" href="/intep/css/estilos.css">
    <style>
        /* ===== RESUMEN DE NOTAS ===== */
        .resumen-notas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .resumen-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.08);
            border-left: 4px solid rgba(16, 185, 129, 0.3);
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
        }

        .resumen-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.12);
        }

        .resumen-card.promedio { border-left-color: #059669; }
        .resumen-card.aprobados { border-left-color: #10B981; }
        .resumen-card.reprobados { border-left-color: #EF4444; }
        .resumen-card.pendientes { border-left-color: #F59E0B; }

        .resumen-card .numero {
            font-size: 2rem;
            font-weight: 700;
            display: block;
            margin-bottom: 0.3rem;
        }

        .resumen-card.promedio .numero { color: #059669; }
        .resumen-card.aprobados .numero { color: #10B981; }
        .resumen-card.reprobados .numero { color: #EF4444; }
        .resumen-card.pendientes .numero { color: #F59E0B; }

        .resumen-card .etiqueta {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== INFO ESTUDIANTE ===== */
        .info-estudiante {
            background: linear-gradient(135deg, #064E3B, #059669);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .info-estudiante h2 {
            margin: 0;
            font-size: 1.3rem;
        }

        .info-estudiante .programa-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        /* ===== BIMESTRE ===== */
        .bimestre-seccion {
            margin-bottom: 2rem;
        }

        .bimestre-header {
            background: rgba(209, 250, 229, 0.8);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(16, 185, 129, 0.15);
            border-radius: 10px 10px 0 0;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .bimestre-header:hover {
            background: rgba(209, 250, 229, 0.9);
        }

        .bimestre-header h3 {
            margin: 0;
            color: #059669;
            font-size: 1.1rem;
        }

        .bimestre-header .toggle-icon {
            font-size: 1.2rem;
            transition: transform 0.3s;
            color: #059669;
        }

        .bimestre-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .bimestre-contenido {
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }

        .bimestre-contenido.oculto {
            display: none;
        }

        /* ===== MÓDULO CARD ===== */
        .modulo-card {
            background: #fff;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .modulo-card:last-child {
            border-bottom: none;
        }

        .modulo-titulo {
            font-weight: 600;
            font-size: 1rem;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .modulo-materia {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 1rem;
        }

        /* ===== GRID DE EVIDENCIAS ===== */
        .evidencias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .evidencia-bloque {
            background: #F0FDF4;
            border-radius: 8px;
            padding: 1rem;
        }

        .evidencia-bloque h4 {
            margin: 0 0 0.6rem 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .evidencia-bloque.conocimiento h4 { color: #3B82F6; }
        .evidencia-bloque.producto h4 { color: #D946A8; }
        .evidencia-bloque.desempeno h4 { color: #10B981; }

        .evidencia-detalle {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            font-size: 0.9rem;
            color: #555;
            border-bottom: 1px solid #eee;
        }

        .evidencia-detalle:last-child {
            border-bottom: none;
        }

        .evidencia-promedio {
            display: flex;
            justify-content: space-between;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
            border-top: 2px solid #ddd;
            font-weight: 700;
            font-size: 0.95rem;
        }

        /* ===== RESULTADO FINAL ===== */
        .resultado-final {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ECFDF5;
            border-radius: 8px;
            padding: 0.8rem 1.2rem;
            margin-top: 0.5rem;
        }

        .resultado-final .nota-valor {
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* ===== BADGES Y COLORES ===== */
        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-aprobado { background: #ECFDF5; color: #065F46; }
        .badge-reprobado { background: #FEF2F2; color: #991B1B; }
        .badge-pendiente { background: #FFFBEB; color: #92400E; }

        .nota-alta { color: #10B981; }
        .nota-media { color: #F59E0B; }
        .nota-baja { color: #EF4444; }
        .nota-pendiente { color: #aaa; }

        /* ===== FÓRMULA INFO ===== */
        .formula-info {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: #059669;
        }

        .formula-info strong {
            display: block;
            margin-bottom: 0.3rem;
        }

        /* ===== SIN NOTAS ===== */
        .sin-notas {
            text-align: center;
            padding: 3rem 1rem;
            color: #888;
        }

        .sin-notas .icono {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .info-estudiante {
                flex-direction: column;
                text-align: center;
            }

            .resumen-notas {
                grid-template-columns: repeat(2, 1fr);
            }

            .evidencias-grid {
                grid-template-columns: 1fr;
            }

            .resultado-final {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-header">
        <h1>INTEP</h1>
        <span class="usuario-info">📊 Mis Notas</span>
        <a href="logout.php" class="btn-salir">Cerrar sesión</a>
    </div>

    <div class="dashboard-container">

        <a href="dashboard.php" class="btn-volver">← Volver al inicio</a>

        <!-- Info del estudiante -->
        <?php if (isset($info_estudiante)): ?>
        <div class="info-estudiante">
            <h2>👤 <?php echo htmlspecialchars($info_estudiante['estudiante_nombre']); ?></h2>
            <span class="programa-badge">🎓 <?php echo htmlspecialchars($info_estudiante['programa_nombre']); ?></span>
        </div>
        <?php endif; ?>

        <!-- Resumen general -->
        <?php if (!empty($notas_por_bimestre)): ?>
        <div class="resumen-notas">
            <div class="resumen-card promedio">
                <span class="numero"><?php echo $promedio_general > 0 ? number_format($promedio_general, 1) : '—'; ?></span>
                <span class="etiqueta">Promedio General</span>
            </div>
            <div class="resumen-card aprobados">
                <span class="numero"><?php echo $total_aprobados; ?></span>
                <span class="etiqueta">Aprobados</span>
            </div>
            <div class="resumen-card reprobados">
                <span class="numero"><?php echo $total_reprobados; ?></span>
                <span class="etiqueta">Reprobados</span>
            </div>
            <div class="resumen-card pendientes">
                <span class="numero"><?php echo $total_pendientes; ?></span>
                <span class="etiqueta">Pendientes</span>
            </div>
        </div>

        <!-- Fórmula informativa -->
        <div class="formula-info">
            <strong>📐 Fórmula de evaluación por módulo:</strong>
            Conocimiento (30%) + Producto (30%) + Desempeño (40%) = Nota Final | Mínimo aprobatorio: <strong style="display:inline;">3.5</strong>
        </div>
        <?php endif; ?>

        <?php if (isset($error_db)): ?>
            <div class="sin-notas">
                <div class="icono">⚠️</div>
                <p>Error al consultar las notas. Contacta al administrador.</p>
                <!-- <?php echo htmlspecialchars($error_db); ?> -->
            </div>

        <?php elseif (empty($notas_por_bimestre)): ?>
            <div class="sin-notas">
                <div class="icono">📋</div>
                <p>Aún no tienes notas registradas.</p>
                <p style="font-size:0.85rem;">Cuando tus docentes suban calificaciones, aparecerán aquí organizadas por bimestre.</p>
            </div>

        <?php else: ?>

            <!-- Notas organizadas por bimestre -->
            <?php for ($bim = 1; $bim <= 5; $bim++): ?>
                <?php if (isset($notas_por_bimestre[$bim])): ?>
                <div class="bimestre-seccion">
                    <div class="bimestre-header" onclick="toggleBimestre(<?php echo $bim; ?>)" id="header-bim-<?php echo $bim; ?>">
                        <h3>📚 Bimestre <?php echo $bim; ?> <small style="color:#888; font-weight:normal;">(Meses <?php echo ($bim*2-1) . '-' . ($bim*2); ?>)</small></h3>
                        <span class="toggle-icon" id="icon-bim-<?php echo $bim; ?>">▼</span>
                    </div>
                    <div class="bimestre-contenido" id="contenido-bim-<?php echo $bim; ?>">
                        <?php foreach ($notas_por_bimestre[$bim] as $nota): ?>
                        <div class="modulo-card">
                            <div class="modulo-titulo">
                                📘 <?php echo htmlspecialchars($nota['modulo_nombre']); ?>
                            </div>
                            <div class="modulo-materia">
                                Materia: <?php echo htmlspecialchars($nota['materia_nombre']); ?> · Módulo <?php echo $nota['orden']; ?>
                            </div>

                            <!-- Grid de 3 evidencias -->
                            <div class="evidencias-grid">
                                <!-- Conocimiento 30% -->
                                <div class="evidencia-bloque conocimiento">
                                    <h4>📝 Conocimiento (30%)</h4>
                                    <div class="evidencia-detalle">
                                        <span>Parcial 1 (sem.4)</span>
                                        <span class="<?php echo claseNota($nota['parcial1']); ?>">
                                            <?php echo mostrarNota($nota['parcial1']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-detalle">
                                        <span>Parcial 2 (sem.8)</span>
                                        <span class="<?php echo claseNota($nota['parcial2']); ?>">
                                            <?php echo mostrarNota($nota['parcial2']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-promedio">
                                        <span>Promedio</span>
                                        <span class="<?php echo claseNota($nota['nota_conocimiento']); ?>">
                                            <?php echo mostrarNota($nota['nota_conocimiento']); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Producto 30% -->
                                <div class="evidencia-bloque producto">
                                    <h4>📂 Producto (30%)</h4>
                                    <div class="evidencia-detalle">
                                        <span>Trabajo 1</span>
                                        <span class="<?php echo claseNota($nota['producto1']); ?>">
                                            <?php echo mostrarNota($nota['producto1']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-detalle">
                                        <span>Trabajo 2</span>
                                        <span class="<?php echo claseNota($nota['producto2']); ?>">
                                            <?php echo mostrarNota($nota['producto2']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-detalle">
                                        <span>Trabajo 3</span>
                                        <span class="<?php echo claseNota($nota['producto3']); ?>">
                                            <?php echo mostrarNota($nota['producto3']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-promedio">
                                        <span>Promedio</span>
                                        <span class="<?php echo claseNota($nota['nota_producto']); ?>">
                                            <?php echo mostrarNota($nota['nota_producto']); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Desempeño 40% -->
                                <div class="evidencia-bloque desempeno">
                                    <h4>🔧 Desempeño (40%)</h4>
                                    <div class="evidencia-detalle">
                                        <span>Taller 1</span>
                                        <span class="<?php echo claseNota($nota['desempeno1']); ?>">
                                            <?php echo mostrarNota($nota['desempeno1']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-detalle">
                                        <span>Taller 2</span>
                                        <span class="<?php echo claseNota($nota['desempeno2']); ?>">
                                            <?php echo mostrarNota($nota['desempeno2']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-detalle">
                                        <span>Taller 3</span>
                                        <span class="<?php echo claseNota($nota['desempeno3']); ?>">
                                            <?php echo mostrarNota($nota['desempeno3']); ?>
                                        </span>
                                    </div>
                                    <div class="evidencia-promedio">
                                        <span>Promedio</span>
                                        <span class="<?php echo claseNota($nota['nota_desempeno']); ?>">
                                            <?php echo mostrarNota($nota['nota_desempeno']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Nota final y estado -->
                            <div class="resultado-final">
                                <div>
                                    <span style="color:#666; font-size:0.9rem;">Nota Final:</span>
                                    <span class="nota-valor <?php echo claseNota($nota['nota_final']); ?>">
                                        <?php echo mostrarNota($nota['nota_final']); ?>
                                    </span>
                                </div>
                                <div>
                                    <?php echo badgeEstado($nota['aprobado'], $nota['nota_final']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endfor; ?>

        <?php endif; ?>

    </div>

    <script>
        // Toggle de bimestres (expandir/colapsar)
        function toggleBimestre(bim) {
            const contenido = document.getElementById('contenido-bim-' + bim);
            const header = document.getElementById('header-bim-' + bim);
            const icon = document.getElementById('icon-bim-' + bim);

            contenido.classList.toggle('oculto');

            if (contenido.classList.contains('oculto')) {
                header.classList.add('collapsed');
                icon.textContent = '▶';
            } else {
                header.classList.remove('collapsed');
                icon.textContent = '▼';
            }
        }
    </script>
    <script src="/intep/sesion.js"></script>
</body>
</html>