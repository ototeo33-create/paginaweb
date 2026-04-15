(function() {
    // Inject UFO Styles
    const style = document.createElement('style');
    style.innerHTML = `
        #interactive-ufo {
            position: fixed;
            z-index: 9999;
            cursor: pointer;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
            pointer-events: auto;
        }
        #interactive-ufo:hover {
            transform: scale(1.15);
            filter: drop-shadow(0 0 20px rgba(16, 185, 129, 0.8));
        }
        .ufo-tooltip {
            position: absolute;
            background: rgba(15, 23, 42, 0.95);
            border: 2px solid #10b981;
            color: white;
            padding: 15px 20px;
            border-radius: 15px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            width: max-content;
            max-width: 300px;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.8);
            text-align: center;
        }
        /* Continuous spin for tracking lights */
        @keyframes ufoBlinkRed { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
        @keyframes ufoBlinkYellow { 0%, 100% { opacity: 0.5; } 50% { opacity: 1; } }
        @keyframes ufoBlinkBlue { 0%, 100% { opacity: 0; } 50% { opacity: 1; } }
    `;
    document.head.appendChild(style);

    // Inject UFO HTML
    const ufoContainer = document.createElement('div');
    ufoContainer.id = 'interactive-ufo';
    ufoContainer.innerHTML = `
        <div class="ufo-tooltip" id="ufo-tooltip"></div>
        <svg width="100" height="70" viewBox="-40 -20 80 80" overflow="visible">
            <g>
                <!-- Beam -->
                <polygon points="-10,5 10,5 40,60 -40,60" fill="rgba(99, 102, 241, 0.25)" />
                <polygon points="-5,5 5,5 20,60 -20,60" fill="rgba(255, 255, 255, 0.2)" />
                
                <!-- Glass Dome -->
                <path d="M -15 0 Q 0 -30 15 0" fill="#cbd5e1" opacity="0.9" />
                
                <!-- Alien head -->
                <ellipse cx="0" cy="-6" rx="6" ry="8" fill="#10b981" />
                <!-- Alien eyes -->
                <ellipse cx="-2" cy="-7" rx="1.5" ry="2" fill="black" transform="rotate(-20 -2 -7)"/>
                <ellipse cx="2" cy="-7" rx="1.5" ry="2" fill="black" transform="rotate(20 2 -7)"/>
                
                <!-- Ship body -->
                <ellipse cx="0" cy="0" rx="35" ry="12" fill="#334155" />
                <ellipse cx="0" cy="0" rx="33" ry="5" fill="#475569" />
                <ellipse cx="0" cy="3" rx="20" ry="4" fill="#1e293b" />
                
                <!-- Blinking tracking lights -->
                <circle cx="-25" cy="1" r="2.5" fill="#ef4444" style="animation: ufoBlinkRed 0.8s infinite" />
                <circle cx="0" cy="4" r="2.5" fill="#facc15" style="animation: ufoBlinkYellow 1.5s infinite" />
                <circle cx="25" cy="1" r="2.5" fill="#06b6d4" style="animation: ufoBlinkBlue 0.8s infinite" />
            </g>
        </svg>
    `;
    document.body.appendChild(ufoContainer);

    // Movement Logic
    let posX = -150;
    let posY = Math.random() * window.innerHeight;
    let targetX = window.innerWidth + 150;
    let targetY = Math.random() * window.innerHeight;
    let speed = 0.8;
    let flying = true;
    
    // Initialize starting position
    ufoContainer.style.left = posX + 'px';
    ufoContainer.style.top = posY + 'px';

    function moveUFO() {
        if (!flying) {
            requestAnimationFrame(moveUFO);
            return;
        }

        let dx = targetX - posX;
        let dy = targetY - posY;
        let dist = Math.sqrt(dx*dx + dy*dy);
        
        if (dist < 5) {
            // Pick a new random destination across the screen
            // 70% chance to go inside the screen, 30% chance to go outside
            if (Math.random() < 0.7) {
                targetX = 50 + Math.random() * (window.innerWidth - 100);
                targetY = 50 + Math.random() * (window.innerHeight - 100);
                speed = 0.5 + Math.random() * 1.5;
            } else {
                targetX = Math.random() < 0.5 ? -150 : window.innerWidth + 150;
                targetY = -50 + Math.random() * (window.innerHeight + 100);
                speed = 1.5 + Math.random() * 2;
            }
        }

        posX += (dx / dist) * speed;
        posY += (dy / dist) * speed;

        // Sine wave for floating effect
        let wave = Math.sin(Date.now() / 800) * 15;

        ufoContainer.style.left = posX + 'px';
        ufoContainer.style.top = (posY + wave) + 'px';

        requestAnimationFrame(moveUFO);
    }
    
    // Start animation loop
    requestAnimationFrame(moveUFO);

    // Interaction Database (Educational Easter Eggs)
    const spaceVocab = [
        "👽 Alien: Extraterrestre",
        "🚀 Space Shuttle: Transbordador Espacial",
        "🌕 Moon: Luna",
        "🌌 Galaxy: Galaxia",
        "☄️ Comet: Cometa",
        "🔭 Telescope: Telescopio",
        "🌍 Earth: Planeta Tierra",
        "☀️ Sun: Sol",
        "👩‍🚀 Astronaut: Astronauta"
    ];

    const tips = [
        "⭐ Tip: 'Aeronautics' se pronuncia [er-uh-naw-tiks]",
        "⭐ Tip: 15 minutos de inglés diarios valen más que 3 horas en un domingo.",
        "⭐ Tip: Usa 'In' para meses (In May) y 'On' para días (On Monday).",
        "⭐ Tip: Escuchar música en inglés ayuda a acostumbrar el oído.",
        "⭐ Secreto: Repetir las misiones te da fluidez."
    ];

    // Click Event
    ufoContainer.addEventListener('click', (e) => {
        e.stopPropagation();
        
        if (!flying) return; // Prevent spam clicking
        
        flying = false; // Pause while showing message
        
        const tooltip = document.getElementById('ufo-tooltip');
        
        // Randomly pick between vocabulary or tip
        const isVocab = Math.random() > 0.5;
        if (isVocab) {
            tooltip.innerHTML = `<strong>Vocabulario Espacial:</strong><br>${spaceVocab[Math.floor(Math.random() * spaceVocab.length)]}`;
        } else {
            tooltip.innerHTML = tips[Math.floor(Math.random() * tips.length)];
        }
        
        tooltip.style.opacity = '1';
        
        // Add a slight pop effect
        ufoContainer.style.transform = 'scale(1.3) rotate(5deg)';
        
        setTimeout(() => {
            tooltip.style.opacity = '0';
            ufoContainer.style.transform = 'scale(1) rotate(0deg)';
            flying = true;
            
            // Hyperspace jump escape!
            targetX = Math.random() * window.innerWidth;
            targetY = -200; // Fly to the top offscreen
            speed = 8; // Very fast
        }, 4000);
    });

})();
