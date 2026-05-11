<?php

function intepGetPracticeSidebarItems(string $nivel): array
{
    $nivel = strtoupper($nivel);

    $catalog = [
        'A1' => [
            [
                'href' => '/intep/cursoingles/minijuego.html',
                'icon' => '🧱',
                'title' => 'Constructor',
                'description' => 'Ordena frases cortas de presentacion, rutinas y vida diaria.',
                'note' => 'Recomendado',
                'pills' => ['To be + have', 'Base oral'],
                'featured' => true,
            ],
            [
                'href' => '/intep/cursoingles/match.html',
                'icon' => '🧩',
                'title' => 'Parejas',
                'description' => 'Une vocabulario cotidiano con significado rapido y sin saturacion.',
                'note' => 'Memoria',
                'pills' => ['Vocabulario A1', 'Rapidez'],
            ],
            [
                'href' => '/intep/cursoingles/visual.html',
                'icon' => '👀',
                'title' => 'Visual',
                'description' => 'Reconoce objetos, lugares y acciones basicas con apoyo visual.',
                'note' => 'Nuevo',
                'pills' => ['Imagen + palabra', 'Refuerzo'],
            ],
            [
                'href' => '/intep/cursoingles/musica.html',
                'icon' => '🎧',
                'title' => 'Escucha y aprende',
                'description' => 'Practica saludos, clase y rutinas con audios cortos propios.',
                'note' => 'Escucha',
                'pills' => ['Dialogos base', 'Comprension'],
            ],
            [
                'href' => '/intep/cursoingles/logros.html',
                'icon' => '🏆',
                'title' => 'Logros',
                'description' => 'Sigue tus medallas, rachas y dominio de practica A1.',
                'note' => 'Ruta A1',
                'pills' => ['Metas', 'Progreso'],
            ],
        ],
        'A2' => [
            [
                'href' => '/intep/cursoingles/minijuego.html',
                'icon' => '🧱',
                'title' => 'Constructor',
                'description' => 'Arma pasado, futuro, preguntas y situaciones reales del nivel A2.',
                'note' => 'Situaciones',
                'pills' => ['Past + future', 'Contexto'],
                'featured' => true,
            ],
            [
                'href' => '/intep/cursoingles/match.html',
                'icon' => '🧩',
                'title' => 'Parejas',
                'description' => 'Relaciona vocabulario de trabajo, ciudad, salud y viajes.',
                'note' => 'Contexto',
                'pills' => ['A2 real', 'Conexiones'],
            ],
            [
                'href' => '/intep/cursoingles/visual.html',
                'icon' => '👀',
                'title' => 'Visual',
                'description' => 'Interpreta escenas urbanas y vocabulario funcional con mas amplitud.',
                'note' => 'Lectura rapida',
                'pills' => ['Escenas A2', 'Agilidad'],
            ],
            [
                'href' => '/intep/cursoingles/musica.html',
                'icon' => '🎧',
                'title' => 'Escucha y aprende',
                'description' => 'Dialogos cortos de direcciones, planes, oficina y salud.',
                'note' => 'Listening',
                'pills' => ['Dialogos A2', 'Comprension'],
            ],
            [
                'href' => '/intep/cursoingles/logros.html',
                'icon' => '🏆',
                'title' => 'Logros',
                'description' => 'Mide cuanto estas consolidando tu practica intermedia.',
                'note' => 'Ruta A2',
                'pills' => ['Metas', 'Constancia'],
            ],
        ],
        'B1' => [
            [
                'href' => '/intep/cursoingles/minijuego.html',
                'icon' => '🧱',
                'title' => 'Constructor',
                'description' => 'Construye ideas con conectores, precision y estructuras mas largas.',
                'note' => 'Precision',
                'pills' => ['B1 activo', 'Sintaxis'],
                'featured' => true,
            ],
            [
                'href' => '/intep/cursoingles/match.html',
                'icon' => '🧩',
                'title' => 'Parejas',
                'description' => 'Relaciona ideas, conceptos y vocabulario mas abstracto.',
                'note' => 'Analitico',
                'pills' => ['Ideas B1', 'Retencion'],
            ],
            [
                'href' => '/intep/cursoingles/visual.html',
                'icon' => '👀',
                'title' => 'Visual',
                'description' => 'Reconoce significado rapido con vocabulario de mayor precision.',
                'note' => 'Agilidad',
                'pills' => ['Conceptos B1', 'Interpretacion'],
            ],
            [
                'href' => '/intep/cursoingles/musica.html',
                'icon' => '🎧',
                'title' => 'Escucha y aprende',
                'description' => 'Audios breves de opinion, trabajo y comprension inferencial.',
                'note' => 'Listening',
                'pills' => ['Inferencia', 'B1 real'],
            ],
            [
                'href' => '/intep/cursoingles/logros.html',
                'icon' => '🏆',
                'title' => 'Logros',
                'description' => 'Monitorea dominio, consistencia y metas de practica avanzada.',
                'note' => 'Ruta B1',
                'pills' => ['Metas', 'Dominio'],
            ],
        ],
    ];

    return $catalog[$nivel] ?? $catalog['A1'];
}

function intepGetPracticeSidebarIntro(string $nivel): string
{
    return match (strtoupper($nivel)) {
        'A2' => 'Practica situacional para pasado, futuro, contexto urbano y escucha funcional.',
        'B1' => 'Practica de precision para listening, estructuras largas y vocabulario mas fino.',
        default => 'Practica guiada para fijar bases, vocabulario esencial y seguridad al hablar.',
    };
}

function intepRenderPracticeSidebar(string $nivel): string
{
    $items = intepGetPracticeSidebarItems($nivel);
    $html = '<nav class="dashboard-nav practice-nav">';

    foreach ($items as $item) {
        $classes = 'dashboard-nav-link practice-link';
        if (!empty($item['featured'])) {
            $classes .= ' is-featured';
        }

        $html .= '<a href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '" class="' . $classes . '">';
        $html .= '<span class="practice-icon">' . $item['icon'] . '</span>';
        $html .= '<span class="practice-copy">';
        $html .= '<span class="practice-top">';
        $html .= '<span class="practice-title">' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '<span class="practice-note">' . htmlspecialchars($item['note'], ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</span>';
        $html .= '<span class="practice-description">' . htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') . '</span>';

        if (!empty($item['pills']) && is_array($item['pills'])) {
            $html .= '<span class="practice-pills">';
            foreach ($item['pills'] as $pill) {
                $html .= '<span class="practice-pill">' . htmlspecialchars($pill, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $html .= '</span>';
        }

        $html .= '</span>';
        $html .= '</a>';
    }

    $html .= '</nav>';

    return $html;
}
