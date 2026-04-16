<?php
require_once '../config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Activar modo preview para toda la sesión admin
$_SESSION['admin_preview'] = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos Admin | INTEP</title>
    <link rel="icon" href="/intep/favicon/favicon.svg" type="image/svg+xml">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f4f6fa;
            min-height: 100vh;
            color: #1a1a2e;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .topbar a.btn-back {
            text-decoration: none;
            color: #6b7280;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .topbar a.btn-back:hover { background: #f3f4f6; }
        .topbar h1 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #111827;
            flex: 1;
        }
        .badge-preview {
            background: #fef3c7;
            color: #92400e;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            border: 1px solid #fcd34d;
        }

        .container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .page-header {
            margin-bottom: 2rem;
        }
        .page-header h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 0.4rem;
        }
        .page-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin: 2rem 0 1rem;
        }

        .cursos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.2rem;
        }

        .curso-card {
            background: #fff;
            border-radius: 16px;
            border: 1.5px solid #e5e7eb;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            position: relative;
            overflow: hidden;
        }
        .curso-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            border-color: #c7d2fe;
        }
        .curso-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
        }
        .curso-card.ingles::before    { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
        .curso-card.kids::before      { background: linear-gradient(90deg, #f59e0b, #ef4444); }
        .curso-card.almacen::before   { background: linear-gradient(90deg, #10b981, #059669); }

        .curso-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .ingles .curso-icon    { background: #eef2ff; }
        .kids .curso-icon      { background: #fff7ed; }
        .almacen .curso-icon   { background: #ecfdf5; }

        .curso-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
        }
        .curso-card p {
            font-size: 0.85rem;
            color: #6b7280;
            line-height: 1.5;
            flex: 1;
        }
        .curso-nivel {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            display: inline-block;
            width: fit-content;
        }
        .nivel-a1 { background: #dbeafe; color: #1d4ed8; }
        .nivel-a2 { background: #ede9fe; color: #7c3aed; }
        .nivel-b1 { background: #fae8ff; color: #9333ea; }
        .nivel-kids { background: #fff7ed; color: #c2410c; }
        .nivel-tec { background: #dcfce7; color: #15803d; }

        .btn-ver {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #4f46e5;
        }
        .kids .btn-ver   { color: #d97706; }
        .almacen .btn-ver { color: #059669; }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            font-size: 0.87rem;
            color: #1e40af;
            display: flex;
            gap: 0.6rem;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        .info-box span { line-height: 1.5; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="../dashboard.php" class="btn-back">← Volver</a>
    <h1>🎓 Cursos Admin</h1>
    <span class="badge-preview">Modo Preview</span>
</div>

<div class="container">
    <div class="page-header">
        <h2>Vista previa de cursos</h2>
        <p>Accede a cualquier curso de la plataforma para verificar su funcionamiento sin necesidad de crear un estudiante.</p>
    </div>

    <div class="info-box">
        <span>ℹ️</span>
        <span>Estás en <strong>modo administrador</strong>. Los cursos se abrirán sin datos de estudiante — verás el contenido, módulos y navegación tal como los ve un alumno, pero sin progreso guardado.</span>
    </div>

    <!-- INGLÉS -->
    <div class="section-title">Curso de Inglés</div>
    <div class="cursos-grid">
        <a href="../cursoingles/dashboard.php" class="curso-card ingles">
            <div class="curso-icon">🇬🇧</div>
            <div>
                <span class="curso-nivel nivel-a1">Nivel A1 — Principiante</span>
            </div>
            <h3>Inglés A1 Beginner</h3>
            <p>Módulos de introducción al inglés: saludos, vocabulario básico, pronunciación y estructuras iniciales.</p>
            <div class="btn-ver">Ver curso <span>→</span></div>
        </a>

        <a href="../cursoingles/dashboard_a2.php" class="curso-card ingles">
            <div class="curso-icon">📖</div>
            <div>
                <span class="curso-nivel nivel-a2">Nivel A2 — Elemental</span>
            </div>
            <h3>Inglés A2 Elemental</h3>
            <p>Gramática elemental, conversaciones cotidianas, tiempos verbales básicos y comprensión lectora.</p>
            <div class="btn-ver">Ver curso <span>→</span></div>
        </a>

        <a href="../cursoingles/dashboard_b1.php" class="curso-card ingles">
            <div class="curso-icon">🎯</div>
            <div>
                <span class="curso-nivel nivel-b1">Nivel B1 — Intermedio</span>
            </div>
            <h3>Inglés B1 Intermediate</h3>
            <p>Inglés intermedio: expresión oral, escritura, comprensión auditiva y vocabulario ampliado.</p>
            <div class="btn-ver">Ver curso <span>→</span></div>
        </a>
    </div>

    <!-- PRIMERA INFANCIA -->
    <div class="section-title">INTEP Kids — Primera Infancia</div>
    <div class="cursos-grid">
        <a href="../cursoingles/cursoinglespreescolar/dashboard.php" class="curso-card kids">
            <div class="curso-icon">🧸</div>
            <div>
                <span class="curso-nivel nivel-kids">Primera Infancia / Preescolar</span>
            </div>
            <h3>INTEP Kids</h3>
            <p>Plataforma interactiva de inglés para niños. Incluye mapa de aventuras, módulos gamificados y actividades lúdicas.</p>
            <div class="btn-ver">Ver curso <span>→</span></div>
        </a>
    </div>

    <!-- ALMACENAMIENTO -->
    <div class="section-title">Técnico Laboral — Almacenamiento</div>
    <div class="cursos-grid">
        <a href="../cursodealmacenamiento/curso.html" class="curso-card almacen">
            <div class="curso-icon">📦</div>
            <div>
                <span class="curso-nivel nivel-tec">Técnico Laboral</span>
            </div>
            <h3>Almacenamiento y Aprovisionamiento</h3>
            <p>6 módulos técnicos sobre gestión de bodegas, recibo, despacho, inventarios y normativas de almacenamiento.</p>
            <div class="btn-ver">Ver curso <span>→</span></div>
        </a>
    </div>

</div>

</body>
</html>
