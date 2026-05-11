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
    <nav class="navbar">
        <a href="#home" class="brand">
            <img src="https://institutointep.edu.co/logointep.png" alt="INTEP">
            <span class="brand-text">
                <span class="brand-name">INTEP English</span>
                <span class="brand-subtitle">Aprender con calma, claridad y constancia</span>
            </span>
        </a>

        <ul class="nav-links">
            <li><a href="#home">Inicio</a></li>
            <li><a href="#features">Metodología</a></li>
            <li><a href="#levels">Niveles</a></li>
        </ul>

        <div class="nav-actions">
            <a href="#levels" class="btn-secondary">Explorar</a>
            <a href="/intep/login.php" class="btn-primary">Iniciar sesión</a>
        </div>
    </nav>

    <main>
        <section class="section-shell hero" id="home">
            <div class="hero-content">
                <div class="badge">Nuevo ciclo 2026 · Curso de inglés INTEP</div>
                <h1>Un curso de inglés más <span class="text-gradient">claro, humano y constante</span>.</h1>
                <p>
                    Avanza por módulos breves, ejercicios guiados, juegos suaves y evaluaciones que te muestran exactamente cómo vas.
                    Menos ruido visual, más enfoque en aprender de verdad.
                </p>

                <div class="hero-buttons">
                    <a href="/intep/login.php" class="btn-primary">Entrar al portal</a>
                    <a href="#levels" class="btn-secondary">Ver niveles</a>
                </div>

                <div class="hero-meta">
                    <span class="meta-pill">24 módulos</span>
                    <span class="meta-pill">3 niveles</span>
                    <span class="meta-pill">Progreso guardado</span>
                    <span class="meta-pill">Práctica diaria de 15 min</span>
                </div>
            </div>

            <div class="hero-panel">
                <div class="preview-window">
                    <div class="preview-top">
                        <span class="preview-badge">Ruta del estudiante</span>
                        <span class="preview-status">Interfaz calmada</span>
                    </div>

                    <h2>Tu avance se siente ligero y entendible.</h2>
                    <p>Todo está organizado para que sepas qué estudiar, qué ya completaste y qué sigue después.</p>

                    <div class="preview-progress">
                        <div class="preview-progress-bar"></div>
                    </div>

                    <div class="preview-steps">
                        <div class="preview-step done">
                            <span>1</span>
                            <div>
                                <strong>Repasa vocabulario</strong>
                                <small>Tarjetas visuales y ejemplos claros</small>
                            </div>
                        </div>
                        <div class="preview-step current">
                            <span>2</span>
                            <div>
                                <strong>Practica una estructura</strong>
                                <small>Ejercicios simples con retroalimentación inmediata</small>
                            </div>
                        </div>
                        <div class="preview-step">
                            <span>3</span>
                            <div>
                                <strong>Haz el quiz del módulo</strong>
                                <small>Comprueba tu progreso antes de avanzar</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-shell features" id="features">
            <div class="section-heading">
                <span class="section-kicker">Metodología</span>
                <h2>Una mezcla armónica entre <span class="text-gradient">didáctica, orden y suavidad visual</span>.</h2>
                <p>
                    Conserva el lado amigable del aprendizaje gamificado, pero con una presentación más limpia,
                    más tranquila y más enfocada en el contenido.
                </p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon">🌿</div>
                    <h3>Ritmo sereno</h3>
                    <p>Colores suaves, espacios amplios y menos distracciones para que estudiar no canse la vista.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">🧩</div>
                    <h3>Aprendizaje guiado</h3>
                    <p>Vocabulario, gramática, práctica y quiz en un flujo fácil de seguir.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Progreso visible</h3>
                    <p>Módulos, avance y exámenes organizados de forma clara para que siempre sepas dónde estás.</p>
                </article>
            </div>
        </section>

        <section class="section-shell levels" id="levels">
            <div class="section-heading">
                <span class="section-kicker">Niveles</span>
                <h2>Tres rutas con la misma lógica visual, cada una con <span class="text-gradient">su tono suave</span>.</h2>
                <p>
                    Cada nivel tiene una identidad sutil para orientarte sin cambiar por completo de estilo cada vez.
                </p>
            </div>

            <div class="level-grid">
                <article class="level-card level-a1">
                    <span class="level-pill">A1 Beginner</span>
                    <h3>Fundamentos para empezar sin miedo</h3>
                    <p>Saludos, rutinas, familia, ciudad, compras y primeras conversaciones reales.</p>
                    <ul class="level-list">
                        <li><span class="level-dot"></span> 8 módulos guiados</li>
                        <li><span class="level-dot"></span> Vocabulario y estructuras base</li>
                        <li><span class="level-dot"></span> Quiz por módulo y prueba final</li>
                    </ul>
                </article>

                <article class="level-card level-a2">
                    <span class="level-pill">A2 Elementary</span>
                    <h3>Más soltura para hablar del pasado y del futuro</h3>
                    <p>Comparaciones, planes, historias cortas, indicaciones y contexto laboral básico.</p>
                    <ul class="level-list">
                        <li><span class="level-dot"></span> 8 módulos intermedios</li>
                        <li><span class="level-dot"></span> Más contexto práctico</li>
                        <li><span class="level-dot"></span> Flujo de aprendizaje más sólido</li>
                    </ul>
                </article>

                <article class="level-card level-b1">
                    <span class="level-pill">B1 Intermediate</span>
                    <h3>Comunicación más madura y segura</h3>
                    <p>Writing, ideas complejas, reported speech, passive voice y consolidación de tiempos.</p>
                    <ul class="level-list">
                        <li><span class="level-dot"></span> 8 módulos avanzados</li>
                        <li><span class="level-dot"></span> Inglés académico y funcional</li>
                        <li><span class="level-dot"></span> Evaluación final por nivel</li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="section-shell cta" id="courses">
            <div class="cta-card">
                <div>
                    <h2>Todo el curso con una experiencia visual más amable.</h2>
                    <p>
                        Entra con tu cuenta INTEP y continúa desde tu nivel actual. El curso guarda tu avance y te lleva paso a paso.
                    </p>
                </div>

                <div class="cta-actions">
                    <a href="/intep/login.php" class="btn-primary">Acceder con mi cuenta INTEP</a>
                    <a href="#features" class="btn-secondary">Ver cómo funciona</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="section-shell footer">
        <a href="#home" class="brand">
            <img src="https://institutointep.edu.co/logointep.png" alt="INTEP">
            <span class="brand-text">
                <span class="brand-name">INTEP English</span>
                <span class="brand-subtitle">Curso institucional de inglés</span>
            </span>
        </a>

        <p>© 2026 INTEP. Plataforma de aprendizaje de inglés.</p>
    </footer>
</body>
</html>
