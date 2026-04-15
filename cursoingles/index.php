<?php
// ============================================================
// INTEP Inglés — Entrada al curso interactivo
// Si hay sesión: redirect al dashboard del nivel del estudiante
// Si no: muestra la landing con botón de login
// ============================================================
require_once __DIR__ . '/../config.php';

if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['estudiante_id'])) {
    // Obtener nivel actual del estudiante
    $est_id = (int)$_SESSION['estudiante_id'];
    $nivel  = 'A1';
    $st = mysqli_prepare($conexion,
        "SELECT nivel_actual FROM idiomas_nivel WHERE estudiante_id = ? LIMIT 1");
    if ($st) {
        mysqli_stmt_bind_param($st, 'i', $est_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        if ($row) $nivel = $row['nivel_actual'];
    }
    // Redirigir al dashboard del nivel
    $dest = match($nivel) {
        'A2'    => 'dashboard_a2.php',
        'B1','B2' => 'dashboard_b1.php',
        default => 'dashboard.php',
    };
    header("Location: /intep/cursoingles/$dest");
    exit;
}
// Sin sesión: mostrar landing con login
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curso de Inglés Premium | INTEP</title>
    <meta name="description" content="Aprende inglés de forma rápida y efectiva con nuestro curso inmersivo. Niveles desde principiante hasta avanzado.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/intep/cursoingles/index.css">
    <link rel="icon" href="/intep/favicon/favicon.svg" type="image/svg+xml">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">
            <img src="https://institutointep.edu.co/logointep.png" alt="INTEP"
                 style="height:45px;vertical-align:middle;filter:drop-shadow(0 0 10px rgba(255,255,255,0.3));">
        </div>
        <ul class="nav-links">
            <li><a href="#home">Inicio</a></li>
            <li><a href="#features">Metodología</a></li>
            <li><a href="#courses">Cursos</a></li>
        </ul>
        <a href="/intep/login.php" class="btn-primary" style="text-decoration:none;">Iniciar Sesión</a>
    </nav>

    <!-- Hero -->
    <header id="home" class="hero">
        <div class="hero-content">
            <div class="badge">🚀 Nuevo Curso 2026</div>
            <h1>Domina el Inglés y <span class="text-gradient">Conquista el Mundo</span></h1>
            <p>Atraviesa la barrera del idioma con una metodología inmersiva, flashcards, juegos y misiones reales. Tu futuro bilingüe empieza hoy.</p>
            <div class="hero-buttons">
                <a href="/intep/login.php" class="btn-primary pulse-animation" style="text-decoration:none;">Ingresar al Portal</a>
                <a href="#courses" class="btn-secondary" style="text-decoration:none;">Ver Cursos</a>
            </div>
        </div>
        <div class="hero-graphic">
            <div class="glass-card floating-card-1">
                <div class="icon">🗣️</div>
                <div><h4>Conversación</h4><p>Práctica 100% real</p></div>
            </div>
            <div class="glass-card floating-card-2">
                <div class="icon">📈</div>
                <div><h4>Fluidez</h4><p>Sube de nivel rápido</p></div>
            </div>
            <div class="hero-circle"></div>
        </div>
    </header>

    <!-- Features -->
    <section id="features" class="features">
        <h2>¿Por qué elegir <span class="text-gradient">INTEP</span>?</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">🧠</div>
                <h3>Aprendizaje con IA</h3>
                <p>Ejercicios generados por inteligencia artificial adaptados a tu nivel y progreso.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎮</div>
                <h3>Juegos Interactivos</h3>
                <p>Constructor de oraciones, parejas, vocabulario visual y más. Aprender nunca fue tan divertido.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3>Seguimiento Real</h3>
                <p>Tu progreso queda guardado. Retoma donde lo dejaste desde cualquier dispositivo.</p>
            </div>
        </div>
    </section>

    <!-- Cursos -->
    <section id="courses" class="cta">
        <div class="cta-content">
            <h2>3 Niveles, 24 Módulos</h2>
            <p>Desde saludos básicos hasta conversaciones complejas. Cada módulo con flashcards, gramática y una misión de rol.</p>
            <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-bottom:2rem;">
                <span style="background:rgba(99,102,241,0.2);border:1px solid rgba(99,102,241,0.4);padding:8px 20px;border-radius:20px;font-weight:600;">A1 Beginner · 8 módulos</span>
                <span style="background:rgba(236,72,153,0.2);border:1px solid rgba(236,72,153,0.4);padding:8px 20px;border-radius:20px;font-weight:600;">A2 Elementary · 8 módulos</span>
                <span style="background:rgba(234,179,8,0.2);border:1px solid rgba(234,179,8,0.4);padding:8px 20px;border-radius:20px;font-weight:600;">B1 Intermediate · 8 módulos</span>
            </div>
            <a href="/intep/login.php" class="btn-primary large-btn" style="text-decoration:none;display:inline-block;">
                Acceder con mi cuenta INTEP
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="logo">
                <img src="https://institutointep.edu.co/logointep.png" alt="INTEP"
                     style="height:45px;vertical-align:middle;filter:drop-shadow(0 0 10px rgba(255,255,255,0.3));">
            </div>
            <p>© 2026 INTEP. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="/intep/cursoingles/ufo.js"></script>
    <script src="/intep/cursoingles/universe_bg.js"></script>
</body>
</html>
