<?php
// ============================================================
// INTEP Inglés — Certificado de Módulo Completado
// ============================================================
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['usuario_id']) || empty($_SESSION['estudiante_id'])) {
    header('Location: /intep/login.php'); exit;
}
$est_id = (int)$_SESSION['estudiante_id'];

// Parámetros: nivel y módulo
$nivel     = in_array($_GET['nivel'] ?? '', ['A1','A2','B1','kids']) ? $_GET['nivel'] : 'A1';
$modulo    = (int)($_GET['modulo'] ?? 1);

// Verificar que realmente completó el módulo
$st = mysqli_prepare($conexion,
    "SELECT completado, fecha_completado FROM ingles_cursos_progreso
     WHERE estudiante_id=? AND nivel=? AND modulo_num=? AND completado=1 LIMIT 1");
mysqli_stmt_bind_param($st, 'isi', $est_id, $nivel, $modulo);
mysqli_stmt_execute($st);
$prog = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
if (!$prog) {
    header('Location: /intep/cursoingles/dashboard.php'); exit;
}
$fecha_completado = $prog['fecha_completado'] ? date('d/m/Y', strtotime($prog['fecha_completado'])) : date('d/m/Y');

// Datos del estudiante
$st2 = mysqli_prepare($conexion,
    "SELECT e.nombre, e.documento, e.foto, p.nombre AS programa
     FROM estudiantes e LEFT JOIN programas p ON p.id = e.programa_id
     WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($st2, 'i', $est_id);
mysqli_stmt_execute($st2);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st2)) ?? [];

$nombre_est = $est['nombre'] ?? 'Estudiante';
$programa   = $est['programa'] ?? 'Inglés';
$foto       = $est['foto'] ?? '';

// Nombres de los módulos
$modulos_nombres = [
    'A1' => [
        1 => 'Nice to meet you!',     2 => 'My World',
        3 => 'Daily Routines',         4 => 'I can do that!',
        5 => 'City Life',              6 => 'Shopping & Food',
        7 => 'What are you doing?',    8 => 'The Past Weekend',
    ],
    'A2' => [
        1 => 'Past Adventures',        2 => 'Future Plans',
        3 => 'Comparing Things',       4 => 'Health & Body',
        5 => 'Travel & Directions',    6 => 'Work & Jobs',
        7 => 'Environment',            8 => 'Technology Today',
    ],
    'B1' => [
        1 => 'Current Affairs',        2 => 'Lifestyle Changes',
        3 => 'Problem Solving',        4 => 'Culture & Arts',
        5 => 'Science & Discovery',    6 => 'Media & News',
        7 => 'Global Issues',          8 => 'Career & Ambition',
    ],
    'kids' => [
        1 => 'Safari de Animales',     2 => 'Fiesta de Colores',
        3 => 'Números Mágicos',        4 => 'Comida Deliciosa',
    ],
];
$nombre_modulo = $modulos_nombres[$nivel][$modulo] ?? "Módulo $modulo";
$nivel_nombre  = ['A1'=>'A1 Beginner','A2'=>'A2 Elementary','B1'=>'B1 Intermediate','kids'=>'INTEP Kids'][$nivel] ?? $nivel;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado – <?= htmlspecialchars($nombre_est) ?> | INTEP</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Outfit:wght@400;600;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0f172a;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px 20px;font-family:'Inter',sans-serif;}

        /* Botones de acción (no se imprimen) */
        .action-bar{display:flex;gap:15px;margin-bottom:25px;flex-wrap:wrap;justify-content:center;}
        .btn-action{padding:12px 28px;border-radius:12px;font-family:'Outfit',sans-serif;font-weight:700;font-size:1rem;cursor:pointer;border:none;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
        .btn-print{background:#6366f1;color:white;box-shadow:0 4px 15px rgba(99,102,241,0.4);}
        .btn-print:hover{background:#4f46e5;transform:translateY(-2px);}
        .btn-back{background:rgba(255,255,255,0.1);color:#cbd5e1;border:1px solid rgba(255,255,255,0.2);}
        .btn-back:hover{background:rgba(255,255,255,0.15);color:white;}

        /* El certificado */
        .certificate{
            background:white;
            width:100%;max-width:800px;
            padding:60px 70px;
            border-radius:8px;
            position:relative;
            box-shadow:0 25px 60px rgba(0,0,0,0.5);
            text-align:center;
        }
        /* Borde decorativo */
        .certificate::before{
            content:'';position:absolute;inset:15px;
            border:3px solid #6366f1;border-radius:4px;pointer-events:none;
        }
        .certificate::after{
            content:'';position:absolute;inset:20px;
            border:1px solid rgba(99,102,241,0.3);border-radius:3px;pointer-events:none;
        }
        .cert-logo{height:55px;margin-bottom:15px;}
        .cert-subtitle{font-family:'Outfit',sans-serif;font-size:0.75rem;letter-spacing:4px;text-transform:uppercase;color:#94a3b8;margin-bottom:30px;}
        .cert-divider{width:80px;height:3px;background:linear-gradient(90deg,#6366f1,#ec4899);margin:0 auto 30px;border-radius:2px;}
        .cert-title{font-family:'Playfair Display',serif;font-size:2.2rem;color:#0f172a;margin-bottom:8px;line-height:1.2;}
        .cert-presents{font-size:0.95rem;color:#64748b;margin-bottom:20px;}
        .cert-name{font-family:'Playfair Display',serif;font-size:3rem;color:#6366f1;margin-bottom:6px;line-height:1.1;}
        .cert-program{font-size:0.9rem;color:#94a3b8;margin-bottom:30px;}
        .cert-body{font-size:1rem;color:#334155;line-height:1.7;max-width:520px;margin:0 auto 20px;}
        .cert-module{font-family:'Playfair Display',serif;font-size:1.6rem;color:#0f172a;font-style:italic;margin-bottom:5px;}
        .cert-level{display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;padding:6px 20px;border-radius:20px;font-family:'Outfit',sans-serif;font-weight:700;font-size:0.9rem;margin-bottom:35px;}
        .cert-xp{display:inline-block;background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#1a1a1a;padding:6px 20px;border-radius:20px;font-family:'Outfit',sans-serif;font-weight:700;font-size:0.9rem;margin-left:10px;margin-bottom:35px;}
        .cert-footer{display:flex;justify-content:space-between;align-items:flex-end;margin-top:40px;padding-top:30px;border-top:1px solid #e2e8f0;}
        .cert-sign{text-align:center;}
        .cert-sign-line{width:160px;height:1px;background:#0f172a;margin:0 auto 8px;}
        .cert-sign-name{font-family:'Playfair Display',serif;font-size:0.9rem;color:#0f172a;font-weight:700;}
        .cert-sign-role{font-size:0.75rem;color:#94a3b8;margin-top:3px;}
        .cert-date{text-align:center;}
        .cert-date-label{font-size:0.75rem;color:#94a3b8;letter-spacing:2px;text-transform:uppercase;}
        .cert-date-value{font-family:'Playfair Display',serif;font-size:1rem;color:#0f172a;margin-top:4px;}
        .cert-seal{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;font-size:0.55rem;font-weight:700;letter-spacing:1px;text-align:center;box-shadow:0 4px 15px rgba(99,102,241,0.4);}
        .cert-seal span{font-size:1.5rem;margin-bottom:2px;}
        .cert-foto{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e2e8f0;margin:0 auto 15px;display:block;}
        .cert-foto-placeholder{width:80px;height:80px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:bold;color:#94a3b8;margin:0 auto 15px;font-family:'Outfit',sans-serif;}
        .ornament{color:#6366f1;font-size:1.2rem;margin:0 10px;opacity:0.4;}

        @media print{
            body{background:white;padding:0;}
            .action-bar{display:none;}
            .certificate{box-shadow:none;border-radius:0;max-width:100%;}
            @page{size:A4 landscape;margin:10mm;}
        }
        @media(max-width:600px){
            .certificate{padding:40px 25px;}
            .cert-name{font-size:2rem;}
            .cert-footer{flex-direction:column;gap:20px;align-items:center;}
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="javascript:history.back()" class="btn-action btn-back">← Volver</a>
        <button onclick="window.print()" class="btn-action btn-print">🖨️ Imprimir / Guardar PDF</button>
        <a href="/intep/dashboard.php" class="btn-action btn-back">🏠 Portal INTEP</a>
    </div>

    <div class="certificate">
        <!-- Logo -->
        <img src="https://institutointep.edu.co/logointep.png" alt="INTEP" class="cert-logo">
        <p class="cert-subtitle">Instituto Técnico Pedagógico</p>
        <div class="cert-divider"></div>

        <!-- Foto del estudiante -->
        <?php if (!empty($foto)): ?>
            <img src="/intep/<?= htmlspecialchars($foto) ?>" alt="Foto" class="cert-foto">
        <?php else: ?>
            <div class="cert-foto-placeholder"><?= strtoupper(mb_substr($nombre_est,0,1)) ?></div>
        <?php endif; ?>

        <p class="cert-title">Certificado de Logro</p>
        <p class="cert-presents">Este certificado se otorga a</p>

        <h2 class="cert-name"><?= htmlspecialchars($nombre_est) ?></h2>
        <p class="cert-program"><?= htmlspecialchars($programa) ?></p>

        <p class="cert-body">
            por haber completado satisfactoriamente el módulo
        </p>

        <p class="cert-module">"<?= htmlspecialchars($nombre_modulo) ?>"</p>

        <span class="cert-level"><?= htmlspecialchars($nivel_nombre) ?></span>
        <span class="cert-xp">+50 XP ⭐</span>

        <p class="cert-body" style="margin-top:10px;font-size:0.9rem;color:#94a3b8;">
            demostrando dedicación y compromiso en el aprendizaje del idioma inglés.
        </p>

        <div class="cert-footer">
            <div class="cert-sign">
                <div class="cert-sign-line"></div>
                <p class="cert-sign-name">INTEP Inglés</p>
                <p class="cert-sign-role">Plataforma de Aprendizaje</p>
            </div>

            <div class="cert-seal">
                <span>🏆</span>
                <div style="line-height:1.3;">APROBADO<br>INTEP</div>
            </div>

            <div class="cert-date">
                <p class="cert-date-label">Fecha</p>
                <p class="cert-date-value"><?= $fecha_completado ?></p>
            </div>
        </div>
    </div>

</body>
</html>
