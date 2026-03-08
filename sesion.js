// ============================================
// CONTROL DE SESIÓN POR INACTIVIDAD — INTEP
// ============================================
// Configuración por rol (se lee del atributo data-rol del body)
// Si el rol es "admin", el timer no se activa.
// Para cambiar tiempos, solo modifica este objeto:

const SESION_CONFIG = {
    admin:       { timeout: 0, aviso: 0 },        // 0 = sin límite
    docente:     { timeout: 120, aviso: 30 },      // 2 min, aviso a los 1:30
    estudiante:  { timeout: 120, aviso: 30 },      // 2 min, aviso a los 1:30
};

// ============================================
// DETECTAR ROL DEL USUARIO
// ============================================
// Se lee desde: <body data-rol="admin"> (lo pone el PHP)
// Si no existe, asume "estudiante" por seguridad
const rolUsuario = document.body.dataset.rol || 'estudiante';
const config = SESION_CONFIG[rolUsuario] || SESION_CONFIG.estudiante;

// Si timeout es 0, no activar nada (admin)
if (config.timeout > 0) {
    inicializarControlSesion(config);
}

function inicializarControlSesion(config) {
    const TIEMPO_LIMITE = config.timeout * 1000;
    const TIEMPO_AVISO = config.aviso * 1000;

    let timerInactividad = null;
    let timerAviso = null;
    let intervaloContador = null;
    let avisoMostrado = false;

    // ============================================
    // CREAR ELEMENTOS DEL AVISO
    // ============================================
    const aviso = document.createElement('div');
    aviso.id = 'aviso-sesion';
    aviso.innerHTML = `
        <div id="aviso-sesion-box">
            <div id="aviso-sesion-icon">⏱</div>
            <div>
                <strong>Sesión por expirar</strong>
                <p>Tu sesión cerrará en <span id="contador">${config.aviso}</span> segundos por inactividad.</p>
            </div>
            <button id="btn-continuar">Continuar</button>
        </div>
    `;
    document.body.appendChild(aviso);

    // Botón continuar
    document.getElementById('btn-continuar').addEventListener('click', reiniciarTimer);

    // ============================================
    // ESTILOS
    // ============================================
    const estilos = document.createElement('style');
    estilos.textContent = `
        #aviso-sesion {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInSesion 0.3s ease;
        }
        #aviso-sesion-box {
            background: #0d1f17;
            color: white;
            padding: 1rem 1.2rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            border-left: 4px solid #f5c842;
            max-width: 340px;
        }
        #aviso-sesion-icon { font-size: 1.8rem; }
        #aviso-sesion-box strong { font-size: 0.95rem; display: block; }
        #aviso-sesion-box p { font-size: 0.82rem; color: #a0b8ab; margin-top: 0.2rem; }
        #btn-continuar {
            background: #25a865;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: background 0.2s;
        }
        #btn-continuar:hover { background: #1a7a4a; }
        @keyframes slideInSesion {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(estilos);

    // ============================================
    // FUNCIONES
    // ============================================
    function mostrarAviso() {
        aviso.style.display = 'block';
        avisoMostrado = true;
        let segundos = config.aviso;
        const contador = document.getElementById('contador');
        if (contador) contador.textContent = segundos;

        // Limpiar intervalo anterior si existe
        if (intervaloContador) clearInterval(intervaloContador);

        intervaloContador = setInterval(() => {
            segundos--;
            if (contador) contador.textContent = segundos;
            if (segundos <= 0) {
                clearInterval(intervaloContador);
                intervaloContador = null;
            }
        }, 1000);
    }

    function ocultarAviso() {
        aviso.style.display = 'none';
        avisoMostrado = false;
        if (intervaloContador) {
            clearInterval(intervaloContador);
            intervaloContador = null;
        }
    }

    function limpiarTimers() {
        if (timerInactividad) clearTimeout(timerInactividad);
        if (timerAviso) clearTimeout(timerAviso);
        timerInactividad = null;
        timerAviso = null;
    }

    function reiniciarTimer() {
        ocultarAviso();
        limpiarTimers();
        iniciarTimer();
        // Ping al servidor para renovar sesión PHP
        fetch(window.location.href, { method: 'HEAD' }).catch(() => {});
    }

    function iniciarTimer() {
        timerAviso = setTimeout(mostrarAviso, TIEMPO_LIMITE - TIEMPO_AVISO);
        timerInactividad = setTimeout(() => {
            window.location.href = '/intep/login.php?sesion=expirada';
        }, TIEMPO_LIMITE);
    }

    // ============================================
    // DETECTAR ACTIVIDAD DEL USUARIO
    // ============================================
    const eventos = ['mousemove', 'keypress', 'click', 'scroll', 'touchstart'];

    eventos.forEach(evento => {
        document.addEventListener(evento, () => {
            if (avisoMostrado) return;
            limpiarTimers();
            iniciarTimer();
        });
    });

    // ============================================
    // ARRANCAR
    // ============================================
    iniciarTimer();
}