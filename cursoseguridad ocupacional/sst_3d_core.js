// ==================== VARIABLES GLOBALES ====================
let scene, camera, renderer;
let worker;
let dangers = [];
let currentZone = 'recepcion';
let score = 0;
let dangersIdentified = 0;
let totalDangers = 0;
let currentDanger = null;
let selectedProbability = 0;
let selectedSeverity = 0;
let achievements = [];
let gameStarted = false;
let gamePaused = false;
let gameEnded = false;
let inEPPStage = false;

// Sistema de vestimenta EPP
let eppEquipped = {
    casco: false,
    gafas: false,
    tapabocas: false,
    chaleco: false,
    guantes: false,
    botas: false
};
let allEPPEquipped = false;

// Oficina/Escena del vestidor
let officeScene, officeCamera, officeRenderer;
let officeWorker;
let eppItems = [];

// CÃ¡mara
let cameraMode = 'follow';
let cameraModes = ['follow', 'free', 'first'];
let cameraTheta = Math.PI / 4;
let cameraPhi = Math.PI / 4;
let cameraDistance = 22;
let cameraTarget = new THREE.Vector3(0, 0, 0);
let isDragging = false;
let previousMouse = { x: 0, y: 0 };
let clock;

const worldBounds = {
    minX: -68,
    maxX: 68,
    minZ: -68,
    maxZ: 68
};
const worldObstacles = [];

const workerMotion = {
    velocity: new THREE.Vector3(),
    desiredVelocity: new THREE.Vector3(),
    normalizedSpeed: 0,
    stridePhase: 0,
    desiredYaw: 0
};

// Teclas
const keys = {};

// Raycaster para interacciÃ³n
const raycaster = new THREE.Raycaster();
const mouse = new THREE.Vector2();
let exactEvaluations = 0;
let onboardingDismissed = false;

const achievementCatalog = {
    explorer: { name: 'Explorador' },
    scorer: { name: 'Analista destacado' }
};

const zoneTraining = {
    recepcion: {
        mission: 'Verifica condiciones ergonómicas, orden y seguridad eléctrica.',
        briefing: 'Prioriza riesgos ergonómicos, cableado expuesto y obstrucción de salidas.',
        onboarding: 'Empieza por la recepción: identifica cables en el piso, sillas defectuosas y archivadores inestables.',
        prevention: 'Organiza cableado, repara mobiliario y asegura salidas de emergencia.'
    },
    vestuario: {
        mission: 'Evalúa almacenamiento de EPP, condiciones de higiene y accesibilidad.',
        briefing: 'Prioriza EPP vencido o dañado, almacenamiento incorrecto y bloqueo de duchas de emergencia.',
        onboarding: 'En vestuario, verifica estado de EPP, orden y accesibilidad a estaciones de lavado.',
        prevention: 'Reemplaza EPP vencido, organiza almacenamiento y despeja accesos de emergencia.'
    },
    almacen: {
        mission: 'Verifica apilado, pisos y rutas seguras.',
        briefing: 'Prioriza orden, estabilidad de la carga y señalización de superficies peligrosas.',
        onboarding: 'Empieza por almacenamiento: identifica cargas inestables, derrames y bloqueos de circulación.',
        prevention: 'Asegura el apilado, limpia derrames y mantén despejados los pasillos.'
    },
    maquinas: {
        mission: 'Detecta atrapamientos, calor y proyección de partículas.',
        briefing: 'Busca guardas ausentes, puntos de contacto y exposiciones del operador.',
        onboarding: 'En máquinas, observa resguardos, distancias de seguridad y protección ocular.',
        prevention: 'Instala guardas, delimita zonas de operación y aplica bloqueo antes de intervenir.'
    },
    alturas: {
        mission: 'Confirma protección contra caídas y acceso seguro.',
        briefing: 'La prioridad es evitar caídas mediante anclaje, inspección y acompañamiento seguro.',
        onboarding: 'En alturas, revisa arnés, línea de vida, anclajes y estado de la escalera.',
        prevention: 'Exige arnés, línea de vida certificada y aseguramiento completo del acceso.'
    },
    confinados: {
        mission: 'Valida permiso, atmósfera segura y control LOTO.',
        briefing: 'Un espacio confinado requiere medición, vigía y rescate planificado antes de entrar.',
        onboarding: 'Busca ausencia de monitoreo atmosférico, permiso de trabajo y aislamiento de energías.',
        prevention: 'Mide gases, ventila, asigna vigía y bloquea todas las energías peligrosas.'
    },
    electrico: {
        mission: 'Evalúa aislamiento, bloqueo y distancias de seguridad.',
        briefing: 'Observa tableros, cables, energización expuesta y barreras de protección.',
        onboarding: 'En riesgo eléctrico, revisa energización expuesta, bloqueo y protección dieléctrica.',
        prevention: 'Desenergiza, bloquea, señaliza y usa herramientas y EPP dieléctricos.'
    },
    quimico: {
        mission: 'Controla compatibilidad, contención y EPP específico.',
        briefing: 'La prioridad es evitar contacto, mezclas incompatibles y derrames sin contención.',
        onboarding: 'En químicos, verifica contención secundaria, segregación y EPP compatible.',
        prevention: 'Separa incompatibles, usa bandejas de contención y protege piel, ojos y vías respiratorias.'
    },
    capacitacion: {
        mission: 'Verifica seguridad en sala de capacitación, salidas de emergencia y equipos.',
        briefing: 'Prioriza cableado en pasillos, extintores sin mantenimiento y salidas bloqueadas.',
        onboarding: 'En capacitación, revisa orden, señalización y condiciones de seguridad del auditorio.',
        prevention: 'Organiza cableado, mantiene extintores y despeja salidas de emergencia.'
    },
    investigacion: {
        mission: 'Evalúa procedimientos de investigación y preservación de evidencia.',
        briefing: 'Prioriza delimitación del área, registros completos y manejo adecuado de evidencia.',
        onboarding: 'En investigación de accidentes, verifica acordonamiento, documentación y protocolos.',
        prevention: 'Acordona el área, documenta exhaustivamente y preserva la evidencia sin contaminación.'
    }
};

const zoneLessonMap = {
    recepcion: 'leccion1-3',
    vestuario: 'leccion11',
    almacen: 'leccion4-5',
    maquinas: 'leccion6-7',
    alturas: 'leccion8',
    quimico: 'leccion9-10',
    confinados: 'leccion12',
    electrico: 'leccion12',
    capacitacion: 'leccion15-16',
    investigacion: 'leccion13-14'
};

function openLesson() {
    if (!currentZone) return;
    const lesson = zoneLessonMap[currentZone];
    if (lesson) {
        window.open('CURSO_SST_INTERACTIVO.html#' + lesson, '_blank');
    } else {
        window.open('CURSO_SST_INTERACTIVO.html', '_blank');
    }
}

const probabilityGuide = {
    1: 'Rara vez ocurre y existen controles confiables.',
    2: 'Puede ocurrir de forma esporadica bajo desvio puntual.',
    3: 'La exposicion es frecuente y el evento es posible.',
    4: 'La falla es muy probable porque faltan barreras importantes.',
    5: 'La materializacion es inminente o casi segura.'
};

const severityGuide = {
    1: 'Danio leve con recuperacion rapida.',
    2: 'Lesion temporal con incapacidad o tratamiento.',
    3: 'Secuela permanente o afectacion grave.',
    4: 'Consecuencia fatal o multiple.'
};

const cp1252ReverseMap = {
    '\u20AC': 0x80,
    '\u201A': 0x82,
    '\u0192': 0x83,
    '\u201E': 0x84,
    '\u2026': 0x85,
    '\u2020': 0x86,
    '\u2021': 0x87,
    '\u02C6': 0x88,
    '\u2030': 0x89,
    '\u0160': 0x8A,
    '\u2039': 0x8B,
    '\u0152': 0x8C,
    '\u017D': 0x8E,
    '\u2018': 0x91,
    '\u2019': 0x92,
    '\u201C': 0x93,
    '\u201D': 0x94,
    '\u2022': 0x95,
    '\u2013': 0x96,
    '\u2014': 0x97,
    '\u02DC': 0x98,
    '\u2122': 0x99,
    '\u0161': 0x9A,
    '\u203A': 0x9B,
    '\u0153': 0x9C,
    '\u017E': 0x9E,
    '\u0178': 0x9F
};

const mojibakePattern = /[\u00C2\u00C3\u00E2\u00F0\u0178\u017D\u017E\u0152\u0153\u20AC\u201A\u0192\u201E\u2026\u2020\u2021\u02C6\u2030\u0160\u2039\u2018\u2019\u201C\u201D\u2022\u2013\u2014\u02DC\u2122\u0161\u203A]/;
const utf8Decoder = new TextDecoder('utf-8', { fatal: true });

function encodeAsLegacyBytes(text) {
    const bytes = [];
    
    for (const char of text) {
        const code = char.codePointAt(0);
        if (code <= 0xFF) {
            bytes.push(code);
            continue;
        }
        
        const mapped = cp1252ReverseMap[char];
        if (mapped === undefined) return null;
        bytes.push(mapped);
    }
    
    return Uint8Array.from(bytes);
}

function decodeMojibake(text) {
    const bytes = encodeAsLegacyBytes(text);
    if (!bytes) return null;
    
    try {
        return utf8Decoder.decode(bytes);
    } catch (error) {
        return null;
    }
}

function repairBrokenText(text) {
    if (typeof text !== 'string' || text.length === 0) return text;
    
    let fixed = text;
    
    // Some strings are double-encoded; decode in short passes until stable.
    for (let i = 0; i < 3; i++) {
        if (!mojibakePattern.test(fixed)) break;
        const decoded = decodeMojibake(fixed);
        if (!decoded || decoded === fixed) break;
        fixed = decoded;
    }
    
    return fixed;
}

function normalizeSearchText(text) {
    return repairBrokenText(text || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function repairInterfaceText(root = document.body) {
    if (!root) return;
    
    document.title = repairBrokenText(document.title);
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    
    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }
    
    nodes.forEach((node) => {
        node.textContent = repairBrokenText(node.textContent);
    });
    
    root.querySelectorAll('[title]').forEach((element) => {
        element.title = repairBrokenText(element.title);
    });
}

function getRiskAssessment(probability, severity) {
    const value = probability * severity;
    
    if (value <= 4) return { value, label: 'ACEPTABLE', className: 'acceptable' };
    if (value <= 8) return { value, label: 'BAJO', className: 'low' };
    if (value <= 12) return { value, label: 'MEDIO', className: 'medium' };
    if (value <= 16) return { value, label: 'ALTO', className: 'high' };
    return { value, label: 'CRITICO', className: 'critical' };
}

function getProbabilityHint(value = 0) {
    return value ? probabilityGuide[value] : 'Selecciona que tan probable es que el evento ocurra en estas condiciones.';
}

function getSeverityHint(value = 0) {
    return value ? severityGuide[value] : 'Selecciona la consecuencia mas seria razonable si el peligro se materializa.';
}

function getZoneDangerCount(zone) {
    return dangers.filter((danger) => danger.zone === zone).length;
}

function getZoneRemainingCount(zone) {
    return dangers.filter((danger) => danger.zone === zone && !danger.identified).length;
}

function getZoneButtonData(zone) {
    const button = document.querySelector(`.zone-btn[data-zone="${zone}"]`);
    const labels = button ? button.querySelectorAll('span') : [];
    
    return {
        name: repairBrokenText(labels[1]?.textContent?.trim() || zone),
        icon: repairBrokenText(button?.querySelector('.zone-btn-icon')?.textContent || '')
    };
}

function getDangerGuidance(danger) {
    const zoneCopy = zoneTraining[danger.zone] || zoneTraining.almacen;
    const keywords = normalizeSearchText(`${danger.type} ${danger.description}`);
    let focus = repairBrokenText(danger.description);
    let control = zoneCopy.prevention;
    
    if (keywords.includes('caida')) {
        control = 'Controla la energia potencial, instala barreras fisicas y usa proteccion anticaidas o sujecion de carga.';
        focus = 'La altura o el apilado deficiente vuelven critica cualquier perdida de control.';
    } else if (keywords.includes('resbal')) {
        control = 'Aisla el area, elimina el derrame y senaliza antes de reabrir el paso.';
        focus = 'El piso comprometido aumenta la frecuencia de exposicion y genera caidas secundarias.';
    } else if (keywords.includes('obstru')) {
        control = 'Despeja la ruta, define orden y asegura el flujo peatonal y de evacuacion.';
        focus = 'Una ruta bloqueada convierte tareas rutinarias en eventos con tropiezos o choques.';
    } else if (keywords.includes('pinz') || keywords.includes('atrap')) {
        control = 'Restituye guardas, controla acceso y bloquea la energia antes de intervenir la maquina.';
        focus = 'El contacto con partes en movimiento puede provocar amputaciones o lesiones incapacitantes.';
    } else if (keywords.includes('particul')) {
        control = 'Instala barreras de proyeccion y exige proteccion visual certificada.';
        focus = 'La ausencia de resguardo y de gafas deja expuestos ojos y rostro durante toda la tarea.';
    } else if (keywords.includes('caliente')) {
        control = 'Aisla la superficie, protege el contacto y senaliza la temperatura peligrosa.';
        focus = 'El trabajador puede tocar o aproximarse a una fuente termica sin advertencia efectiva.';
    } else if (keywords.includes('atm') || keywords.includes('gas') || keywords.includes('oxigen')) {
        control = 'Mide la atmosfera antes y durante el trabajo, ventila y prepara rescate.';
        focus = 'En espacios confinados la exposicion puede incapacitar antes de que el trabajador reaccione.';
    } else if (keywords.includes('energia') || keywords.includes('loto') || keywords.includes('electr')) {
        control = 'Desenergiza, bloquea, verifica ausencia de tension y controla la reconexion.';
        focus = 'Sin aislamiento ni bloqueo, la fuente energetica puede activarse mientras existe exposicion directa.';
    } else if (keywords.includes('quimic') || keywords.includes('acido') || keywords.includes('incompat')) {
        control = 'Separa sustancias incompatibles, usa contencion secundaria y protege piel, ojos y vias respiratorias.';
        focus = 'La mezcla o el contacto sin barreras adecuadas puede escalar rapido a lesion grave o emergencia.';
    } else if (keywords.includes('epp') || keywords.includes('arnes') || keywords.includes('gafas')) {
        control = 'Corrige de inmediato el EPP requerido y verifica ajuste, compatibilidad y entrenamiento.';
        focus = 'La tarea sigue expuesta porque la defensa personal requerida no esta presente o no es suficiente.';
    }
    
    return {
        focus,
        control,
        expected: getRiskAssessment(danger.probability, danger.severity)
    };
}

function isEvaluationOpen() {
    return document.getElementById('evaluationPanel')?.classList.contains('visible');
}

function isWorldInputBlocked() {
    return !gameStarted || gamePaused || gameEnded || isEvaluationOpen();
}

function dismissOnboarding() {
    onboardingDismissed = true;
    document.getElementById('onboardingCard')?.classList.add('hidden');
}

function showOnboarding() {
    onboardingDismissed = false;
    document.getElementById('onboardingCard')?.classList.remove('hidden');
    updateZoneBriefing();
}

function updateZoneBriefing() {
    const zoneCopy = zoneTraining[currentZone] || zoneTraining.almacen;
    const zoneData = getZoneButtonData(currentZone);
    const pending = getZoneRemainingCount(currentZone);
    const total = getZoneDangerCount(currentZone);
    
    document.getElementById('currentZoneName').textContent = zoneData.name;
    document.getElementById('currentZoneIcon').textContent = zoneData.icon;
    document.getElementById('missionText').textContent = zoneCopy.mission;
    document.getElementById('zonePrompt').textContent = `${zoneData.name}: evalua el contexto antes de asignar la clase de riesgo.`;
    document.getElementById('briefingTitle').textContent = zoneData.name;
    document.getElementById('briefingDescription').textContent = zoneCopy.briefing;
    document.getElementById('briefingRemaining').textContent = `Peligros pendientes: ${pending}/${total}`;
    document.getElementById('onboardingTitle').textContent = zoneData.name;
    document.getElementById('onboardingText').textContent = zoneCopy.onboarding;
    
    if (onboardingDismissed) {
        document.getElementById('onboardingCard')?.classList.add('hidden');
    }
}

function updateAnalysisPanel(danger = null, summary = {}) {
    const title = document.getElementById('analysisTitle');
    const description = document.getElementById('analysisDescription');
    const risk = document.getElementById('analysisRisk');
    const result = document.getElementById('analysisResult');
    const action = document.getElementById('analysisAction');
    
    if (!danger) {
        title.textContent = 'Sin evaluacion todavia';
        description.textContent = 'Selecciona un marcador para recibir una explicacion clara del peligro y de los controles recomendados.';
        risk.textContent = '-';
        result.textContent = '-';
        action.textContent = 'Aqui veras la accion preventiva sugerida.';
        return;
    }
    
    const guidance = getDangerGuidance(danger);
    const expectedText = `P${danger.probability} / S${danger.severity} / ${guidance.expected.label}`;
    title.textContent = repairBrokenText(danger.type);
    description.textContent = summary.message || guidance.focus;
    risk.textContent = expectedText;
    result.textContent = summary.userText || 'Pendiente';
    action.textContent = `Control sugerido: ${guidance.control}`;
}

function toggleInfoPanel() {
    document.getElementById('infoPanel').classList.toggle('visible');
}

function wrapAngle(angle) {
    while (angle > Math.PI) angle -= Math.PI * 2;
    while (angle < -Math.PI) angle += Math.PI * 2;
    return angle;
}

function lerpAngle(current, target, factor) {
    return current + wrapAngle(target - current) * factor;
}

function registerObstacle(x, z, width, depth, padding = 0.35) {
    worldObstacles.push({
        x,
        z,
        halfWidth: width / 2 + padding,
        halfDepth: depth / 2 + padding
    });
}

// ==================== SISTEMA DE AUDIO ====================
let audioContext;
let musicMuted = false;
let soundMuted = false;

// Generador de tonos procedurales
function playTone(frequency, duration, type = 'sine', volume = 0.3) {
    if (soundMuted || !audioContext) return;
    
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    
    const osc = audioContext.createOscillator();
    const gain = audioContext.createGain();
    
    osc.connect(gain);
    gain.connect(audioContext.destination);
    
    osc.frequency.value = frequency;
    osc.type = type;
    
    gain.gain.setValueAtTime(volume, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
    
    osc.start();
    osc.stop(audioContext.currentTime + duration);
}

// Generador de ruido
function playNoise(duration, volume = 0.1) {
    if (soundMuted || !audioContext) return;
    
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    
    const bufferSize = audioContext.sampleRate * duration;
    const buffer = audioContext.createBuffer(1, bufferSize, audioContext.sampleRate);
    const data = buffer.getChannelData(0);
    
    for (let i = 0; i < bufferSize; i++) {
        data[i] = Math.random() * 2 - 1;
    }
    
    const noise = audioContext.createBufferSource();
    noise.buffer = buffer;
    
    const gain = audioContext.createGain();
    gain.gain.setValueAtTime(volume, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
    
    noise.connect(gain);
    gain.connect(audioContext.destination);
    noise.start();
}

// Sonidos del juego
function playSoundClick() {
    playTone(800, 0.1, 'sine', 0.2);
}

function playSoundSelect() {
    playTone(523.25, 0.1, 'sine', 0.2);
    setTimeout(() => playTone(659.25, 0.15, 'sine', 0.2), 50);
}

function playSoundSuccess() {
    const notes = [523.25, 659.25, 783.99, 1046.50];
    notes.forEach((freq, i) => {
        setTimeout(() => playTone(freq, 0.3, 'sine', 0.15), i * 100);
    });
}

function playSoundError() {
    playNoise(0.15, 0.3);
    playTone(150, 0.2, 'sawtooth', 0.2);
}

function playSoundWarning() {
    playTone(440, 0.1, 'square', 0.1);
    setTimeout(() => playTone(440, 0.1, 'square', 0.1), 150);
    setTimeout(() => playTone(440, 0.1, 'square', 0.1), 300);
}

function playSoundAchievement() {
    const notes = [261.63, 329.63, 392, 523.25, 659.25, 783.99];
    notes.forEach((freq, i) => {
        setTimeout(() => playTone(freq, 0.2, 'sine', 0.1), i * 80);
    });
}

function playSoundAmbient() {
    if (musicMuted || !audioContext) return;
    playTone(110, 2, 'sine', 0.03);
    playTone(165, 2, 'triangle', 0.02);
}

// MÃºsica de fondo suave
let ambientInterval;
function startAmbientMusic() {
    if (musicMuted || ambientInterval) return;
    ambientInterval = setInterval(playSoundAmbient, 2000);
}

function stopAmbientMusic() {
    if (ambientInterval) {
        clearInterval(ambientInterval);
        ambientInterval = null;
    }
}

function toggleMusic() {
    musicMuted = !musicMuted;
    if (musicMuted) {
        stopAmbientMusic();
    } else {
        startAmbientMusic();
    }
}

// Inicializar audio al primer click
function initAudio() {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
}

// ==================== INICIALIZACIÃ“N ====================
function init() {
    clock = new THREE.Clock();
    worldObstacles.length = 0;
    
    // Crear escena
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1a1a2e);
    scene.fog = new THREE.Fog(0x1a1a2e, 50, 150);
    
    // Crear cÃ¡mara
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(30, 20, 30);
    camera.lookAt(0, 0, 0);
    
    // Crear renderer
    renderer = new THREE.WebGLRenderer({ 
        canvas: document.getElementById('canvas3D'),
        antialias: true 
    });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    
    // Configurar luces
    setupLights();
    
    // Crear entorno
    createEnvironment();
    
    // Crear trabajador con EPP
    createWorker();
    
    // Crear zonas con peligros
    createAllZones();
    
    // Configurar controles
    setupControls();
    
    // Event listeners
    window.addEventListener('resize', onWindowResize);
    
    // Iniciar animaciÃ³n
    animate();
}

// ==================== LUCES ====================
function setupLights() {
    // Luz ambiental
    const ambient = new THREE.AmbientLight(0x404050, 0.4);
    scene.add(ambient);
    
    // Luz direccional (sol)
    const sunLight = new THREE.DirectionalLight(0xffffff, 0.8);
    sunLight.position.set(50, 80, 30);
    sunLight.castShadow = true;
    sunLight.shadow.mapSize.width = 2048;
    sunLight.shadow.mapSize.height = 2048;
    sunLight.shadow.camera.near = 0.5;
    sunLight.shadow.camera.far = 200;
    sunLight.shadow.camera.left = -100;
    sunLight.shadow.camera.right = 100;
    sunLight.shadow.camera.top = 100;
    sunLight.shadow.camera.bottom = -100;
    scene.add(sunLight);
    
    // Luz de relleno
    const fillLight = new THREE.DirectionalLight(0x8888ff, 0.3);
    fillLight.position.set(-30, 20, -30);
    scene.add(fillLight);
    
    // Luces de ambiente por zonas
    const zoneLights = [
        { pos: [-25, 8, -25], color: 0xff6b6b, intensity: 0.4 }, // AlmacÃ©n
        { pos: [25, 8, -25], color: 0x4ecdc4, intensity: 0.4 }, // MÃ¡quinas
        { pos: [0, 12, 0], color: 0xf7d794, intensity: 0.5 }, // Centro
        { pos: [25, 8, 25], color: 0xa29bfe, intensity: 0.4 }, // ElÃ©ctrico
        { pos: [-25, 8, 25], color: 0x55efc4, intensity: 0.4 }, // QuÃ­mico
        { pos: [0, 15, -40], color: 0xfdcb6e, intensity: 0.4 }, // Alturas
        { pos: [-40, 5, 0], color: 0x74b9ff, intensity: 0.4 }, // Confinados
    ];
    
    zoneLights.forEach(light => {
        const pointLight = new THREE.PointLight(light.color, light.intensity, 40);
        pointLight.position.set(...light.pos);
        scene.add(pointLight);
    });
}

// ==================== ENTORNO ====================
function createEnvironment() {
    // Piso principal
    const floorGeo = new THREE.PlaneGeometry(150, 150);
    const floorMat = new THREE.MeshStandardMaterial({ 
        color: 0x3d5a6c, 
        roughness: 0.8, 
        metalness: 0.2 
    });
    const floor = new THREE.Mesh(floorGeo, floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.receiveShadow = true;
    scene.add(floor);
    
    // CuadrÃ­cula
    const grid = new THREE.GridHelper(150, 150, 0x2c3e50, 0x2c3e50);
    grid.position.y = 0.02;
    scene.add(grid);
    
    // Paredes perimetrales
    createWalls();
    
    // Columnas
    createColumns();
    
    // Techo
    createCeiling();
    
    // SeÃ±alizaciÃ³n de seguridad
    createSafetySigns();
    
    // Extintores
    createFireExtinguishers();
    
    // Botiquines
    createFirstAidKits();
}

// ==================== PAREDES ====================
function createWalls() {
    const wallMat = new THREE.MeshStandardMaterial({ 
        color: 0x34495e, 
        transparent: true, 
        opacity: 0.3 
    });
    
    const wallPositions = [
        { size: [150, 15], pos: [0, 7.5, -75], rot: [0, 0, 0] },
        { size: [150, 15], pos: [-75, 7.5, 0], rot: [0, Math.PI/2, 0] },
        { size: [150, 15], pos: [75, 7.5, 0], rot: [0, Math.PI/2, 0] },
        { size: [150, 15], pos: [0, 7.5, 75], rot: [0, 0, 0] },
    ];
    
    wallPositions.forEach(w => {
        const wall = new THREE.Mesh(new THREE.PlaneGeometry(...w.size), wallMat);
        wall.position.set(...w.pos);
        wall.rotation.set(...w.rot);
        scene.add(wall);
    });
}

// ==================== COLUMNAS ====================
function createColumns() {
    const columnMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const columnGeo = new THREE.BoxGeometry(1, 12, 1);
    
    for (let x = -70; x <= 70; x += 20) {
        for (let z = -70; z <= 70; z += 20) {
            const column = new THREE.Mesh(columnGeo, columnMat);
            column.position.set(x, 6, z);
            column.castShadow = true;
            scene.add(column);
        }
    }
}

// ==================== TECHO ====================
function createCeiling() {
    const beamMat = new THREE.MeshStandardMaterial({ color: 0x5d6d7e });
    
    // Vigas principales
    for (let x = -70; x <= 70; x += 10) {
        const beam = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.5, 150), beamMat);
        beam.position.set(x, 12, 0);
        scene.add(beam);
    }
    
    for (let z = -70; z <= 70; z += 10) {
        const beam = new THREE.Mesh(new THREE.BoxGeometry(150, 0.5, 0.5), beamMat);
        beam.position.set(0, 12, z);
        scene.add(beam);
    }
    
    // Paneles de techo
    const panelMat = new THREE.MeshStandardMaterial({ color: 0xecf0f1, transparent: true, opacity: 0.3 });
    for (let x = -60; x <= 60; x += 15) {
        for (let z = -60; z <= 60; z += 15) {
            const panel = new THREE.Mesh(new THREE.PlaneGeometry(14, 14), panelMat);
            panel.rotation.x = Math.PI / 2;
            panel.position.set(x, 12, z);
            scene.add(panel);
        }
    }
}

// ==================== SEÃ‘ALES DE SEGURIDAD ====================
function createSafetySigns() {
    const signs = [
        { pos: [-70, 3, -70], text: 'ðŸš¨ ALARMA', color: 0xe74c3c },
        { pos: [70, 3, -70], text: 'âš ï¸ RIESGO', color: 0xf39c12 },
        { pos: [-70, 3, 70], text: 'ðŸš« PROHIBIDO', color: 0xe74c3c },
        { pos: [70, 3, 70], text: 'ðŸ¦º EPP', color: 0x3498db },
        { pos: [0, 3, -70], text: 'ðŸšª SALIDA', color: 0x27ae60 },
        { pos: [0, 3, 70], text: 'ðŸšª SALIDA', color: 0x27ae60 },
    ];
    
    signs.forEach(sign => {
        const group = new THREE.Group();
        
        // Poste
        const poleGeo = new THREE.CylinderGeometry(0.08, 0.08, 3, 8);
        const poleMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
        const pole = new THREE.Mesh(poleGeo, poleMat);
        pole.position.y = 1.5;
        group.add(pole);
        
        // Cartel
        const signGeo = new THREE.BoxGeometry(2, 1.2, 0.1);
        const signMat = new THREE.MeshStandardMaterial({ color: sign.color });
        const signMesh = new THREE.Mesh(signGeo, signMat);
        signMesh.position.y = 3.3;
        group.add(signMesh);
        
        group.position.set(...sign.pos);
        scene.add(group);
    });
}

// ==================== EXTINTORES ====================
function createFireExtinguishers() {
    const positions = [
        [-45, 45], [-45, -45], [45, 45], [45, -45],
        [0, -60], [0, 60], [-60, 0], [60, 0]
    ];
    
    positions.forEach(pos => {
        createFireExtinguisher(pos[0], pos[1]);
    });
}

function createFireExtinguisher(x, z) {
    const group = new THREE.Group();
    
    const bodyGeo = new THREE.CylinderGeometry(0.15, 0.18, 0.8, 16);
    const bodyMat = new THREE.MeshStandardMaterial({ color: 0xe74c3c });
    const body = new THREE.Mesh(bodyGeo, bodyMat);
    body.position.y = 0.6;
    group.add(body);
    
    const topGeo = new THREE.ConeGeometry(0.12, 0.25, 16);
    const top = new THREE.Mesh(topGeo, bodyMat);
    top.position.y = 1.1;
    group.add(top);
    
    group.position.set(x, 0, z);
    scene.add(group);
}

// ==================== BOTIQUINES ====================
function createFirstAidKits() {
    const positions = [
        [-44, 10], [44, 10], [-44, -10], [44, -10], [0, 44]
    ];
    
    positions.forEach(pos => {
        createFirstAidKit(pos[0], pos[1]);
    });
}

function createFirstAidKit(x, z) {
    const group = new THREE.Group();
    
    const boxGeo = new THREE.BoxGeometry(0.6, 0.4, 0.25);
    const boxMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
    const box = new THREE.Mesh(boxGeo, boxMat);
    box.position.y = 1.3;
    group.add(box);
    
    // Cruz roja
    const crossMat = new THREE.MeshBasicMaterial({ color: 0xe74c3c });
    const crossH = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.06, 0.02), crossMat);
    crossH.position.set(0, 1.3, 0.13);
    group.add(crossH);
    
    const crossV = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.25, 0.02), crossMat);
    crossV.position.set(0, 1.3, 0.13);
    group.add(crossV);
    
    group.position.set(x, 0, z);
    scene.add(group);
}

// ==================== TRABAJADOR CON EPP ====================
let walkCycle = 0;
let workerParts = {};

// Crear textura con texto INTEP
function createIntepTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');
    
    // Fondo verde INTEP
    ctx.fillStyle = '#059669';
    ctx.fillRect(0, 0, 256, 256);
    
    // Borde blanco
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 8;
    ctx.strokeRect(10, 10, 236, 236);
    
    // Texto INTEP
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 80px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('INTEP', 128, 128);
    
    // LÃ­nea decorativa
    ctx.fillStyle = '#10B981';
    ctx.fillRect(48, 160, 160, 8);
    
    const texture = new THREE.CanvasTexture(canvas);
    texture.needsUpdate = true;
    return texture;
}

function createWorker() {
    // 1. Inicializar el grupo principal
    worker = new THREE.Group();
    worker.position.set(0, 0, 0); // Suelo en Y=0

    // --- MATERIALES ---
    const greenDark = 0x059669; // Verde INTEP
    const greenLight = 0x10B981;
    
    const vestMat = new THREE.MeshStandardMaterial({ color: greenDark });
    const pantsMat = new THREE.MeshStandardMaterial({ color: 0x1a1a1a });
    const bootMat = new THREE.MeshStandardMaterial({ color: 0x1a1a1a });
    const skinMat = new THREE.MeshStandardMaterial({ color: 0xf5cba7 });
    const helmetMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const gloveMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const goggleMat = new THREE.MeshStandardMaterial({ color: 0x3498db, transparent: true, opacity: 0.6 });
    
    const stripeMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
    const beltMat = new THREE.MeshStandardMaterial({ color: 0x222222 });
    const faceDetailMat = new THREE.MeshStandardMaterial({ color: 0x000000 });

    // ==========================================
    // 1. PIERNAS (Izquierda y Derecha)
    // ==========================================
    
    // --- PIERNA IZQUIERDA ---
    workerParts.leftThigh = new THREE.Group();
    workerParts.leftThigh.position.set(-0.08, 0.6, 0); // Regla: Y=0.6
    
    const leftThighMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.07, 0.35, 12), pantsMat);
    leftThighMesh.position.y = -0.175; // Regla: Centro de la malla desplazado
    workerParts.leftThigh.add(leftThighMesh);

    workerParts.leftShin = new THREE.Group();
    workerParts.leftShin.position.y = -0.35; // Regla: Y=-0.35 relativo al muslo
    
    const leftShinMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.05, 0.35, 12), pantsMat);
    leftShinMesh.position.y = -0.175;
    workerParts.leftShin.add(leftShinMesh);

    workerParts.leftFoot = new THREE.Group();
    workerParts.leftFoot.position.y = -0.35; // Regla: Y=-0.35 relativo a la espinilla
    
    const leftFootMesh = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.06, 0.2), bootMat);
    leftFootMesh.position.set(0, -0.03, 0.03); // Desplazado ligeramente hacia el frente
    workerParts.leftFoot.add(leftFootMesh);

    workerParts.leftShin.add(workerParts.leftFoot);
    workerParts.leftThigh.add(workerParts.leftShin);
    worker.add(workerParts.leftThigh);

    // --- PIERNA DERECHA ---
    workerParts.rightThigh = new THREE.Group();
    workerParts.rightThigh.position.set(0.08, 0.6, 0);
    
    const rightThighMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.07, 0.35, 12), pantsMat);
    rightThighMesh.position.y = -0.175;
    workerParts.rightThigh.add(rightThighMesh);

    workerParts.rightShin = new THREE.Group();
    workerParts.rightShin.position.y = -0.35;
    
    const rightShinMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.05, 0.35, 12), pantsMat);
    rightShinMesh.position.y = -0.175;
    workerParts.rightShin.add(rightShinMesh);

    workerParts.rightFoot = new THREE.Group();
    workerParts.rightFoot.position.y = -0.35;
    
    const rightFootMesh = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.06, 0.2), bootMat);
    rightFootMesh.position.set(0, -0.03, 0.03);
    workerParts.rightFoot.add(rightFootMesh);

    workerParts.rightShin.add(workerParts.rightFoot);
    workerParts.rightThigh.add(workerParts.rightShin);
    worker.add(workerParts.rightThigh);


    // ==========================================
    // 2. TORSO
    // ==========================================
    workerParts.torso = new THREE.Group();
    workerParts.torso.position.y = 1.0; // Regla: Y=1.0

    const torsoMesh = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.5, 0.25), vestMat);
    workerParts.torso.add(torsoMesh);

    // Espalda Logo INTEP
    const intepTexture = createIntepTexture();
    const backMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.35, 0.4, 0.05),
        new THREE.MeshStandardMaterial({ map: intepTexture })
    );
    backMesh.position.set(0, 0, -0.13); // Regla: Y=0, z=-0.13
    workerParts.torso.add(backMesh);

    // Franjas Reflectivas y CinturÃ³n
    const stripeTop = new THREE.Mesh(new THREE.BoxGeometry(0.41, 0.03, 0.26), stripeMat);
    stripeTop.position.y = 0.1;
    workerParts.torso.add(stripeTop);

    const stripeBottom = new THREE.Mesh(new THREE.BoxGeometry(0.41, 0.03, 0.26), stripeMat);
    stripeBottom.position.y = -0.1;
    workerParts.torso.add(stripeBottom);

    const belt = new THREE.Mesh(new THREE.BoxGeometry(0.42, 0.05, 0.27), beltMat);
    belt.position.y = -0.23;
    workerParts.torso.add(belt);


    // ==========================================
    // 3. BRAZOS (Con Codos y Guantes)
    // ==========================================
    
    // --- BRAZO IZQUIERDO ---
    workerParts.leftArm = new THREE.Group();
    workerParts.leftArm.position.set(-0.25, 0.2, 0); // Pegado al torso (arriba)
    
    const leftUpperArm = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.05, 0.25, 12), vestMat);
    leftUpperArm.position.y = -0.125;
    workerParts.leftArm.add(leftUpperArm);

    workerParts.leftForearm = new THREE.Group();
    workerParts.leftForearm.position.y = -0.25; // Pivote del codo
    
    const leftLowerArm = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.04, 0.25, 12), vestMat); // Manga larga protectora
    leftLowerArm.position.y = -0.125;
    workerParts.leftForearm.add(leftLowerArm);

    workerParts.leftHand = new THREE.Group();
    workerParts.leftHand.position.y = -0.25; // Pivote de la muÃ±eca
    
    const leftGlove = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.1, 0.08), gloveMat);
    leftGlove.position.y = -0.05;
    workerParts.leftHand.add(leftGlove);

    workerParts.leftForearm.add(workerParts.leftHand);
    workerParts.leftArm.add(workerParts.leftForearm);
    workerParts.torso.add(workerParts.leftArm);

    // --- BRAZO DERECHO ---
    workerParts.rightArm = new THREE.Group();
    workerParts.rightArm.position.set(0.25, 0.2, 0);
    
    const rightUpperArm = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.05, 0.25, 12), vestMat);
    rightUpperArm.position.y = -0.125;
    workerParts.rightArm.add(rightUpperArm);

    workerParts.rightForearm = new THREE.Group();
    workerParts.rightForearm.position.y = -0.25;
    
    const rightLowerArm = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.04, 0.25, 12), vestMat);
    rightLowerArm.position.y = -0.125;
    workerParts.rightForearm.add(rightLowerArm);

    workerParts.rightHand = new THREE.Group();
    workerParts.rightHand.position.y = -0.25;
    
    const rightGlove = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.1, 0.08), gloveMat);
    rightGlove.position.y = -0.05;
    workerParts.rightHand.add(rightGlove);

    workerParts.rightForearm.add(workerParts.rightHand);
    workerParts.rightArm.add(workerParts.rightForearm);
    workerParts.torso.add(workerParts.rightArm);


    // ==========================================
    // 4. CUELLO
    // ==========================================
    const neckMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.06, 0.08, 12), skinMat);
    neckMesh.position.y = 0.29; // Asomando por encima del torso
    workerParts.torso.add(neckMesh);


    // ==========================================
    // 5. CABEZA Y CARA
    // ==========================================
    workerParts.head = new THREE.Group();
    workerParts.head.position.y = 0.48; // Regla: Y=0.48 pegado al torso

    const headMesh = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.25, 0.2), skinMat);
    workerParts.head.add(headMesh);

    // Detalles faciales
    const leftEye = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.03, 0.01), faceDetailMat);
    leftEye.position.set(-0.04, 0.02, 0.105);
    workerParts.head.add(leftEye);

    const rightEye = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.03, 0.01), faceDetailMat);
    rightEye.position.set(0.04, 0.02, 0.105);
    workerParts.head.add(rightEye);

    const nose = new THREE.Mesh(new THREE.BoxGeometry(0.02, 0.04, 0.03), skinMat);
    nose.position.set(0, -0.02, 0.11);
    workerParts.head.add(nose);

    const mouth = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.01, 0.01), faceDetailMat);
    mouth.position.set(0, -0.07, 0.105);
    workerParts.head.add(mouth);

    const leftEar = new THREE.Mesh(new THREE.BoxGeometry(0.02, 0.05, 0.04), skinMat);
    leftEar.position.set(-0.11, 0, 0);
    workerParts.head.add(leftEar);

    const rightEar = new THREE.Mesh(new THREE.BoxGeometry(0.02, 0.05, 0.04), skinMat);
    rightEar.position.set(0.11, 0, 0);
    workerParts.head.add(rightEar);


    // ==========================================
    // 6. CASCO DE SEGURIDAD
    // ==========================================
    workerParts.helmet = new THREE.Group();
    // La regla dice Y=0.58 pegado a la cabeza. 
    // Como la cabeza estÃ¡ en 0.48, el offset local debe ser 0.10 (0.48 + 0.10 = 0.58 absoluto sobre el torso)
    workerParts.helmet.position.y = 0.10; 

    // Media esfera (CrÃ¡neo del casco)
    const helmetDome = new THREE.Mesh(
        new THREE.SphereGeometry(0.11, 16, 16, 0, Math.PI * 2, 0, Math.PI / 2),
        helmetMat
    );
    workerParts.helmet.add(helmetDome);

    // Ala del casco (Borde inferior)
    const helmetBrim = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.13, 0.02, 16), helmetMat);
    helmetBrim.position.set(0, -0.01, 0.02); // Tirado un poco hacia el frente
    workerParts.helmet.add(helmetBrim);

    workerParts.head.add(workerParts.helmet);


    // ==========================================
    // 7. GAFAS DE SEGURIDAD
    // ==========================================
    workerParts.glasses = new THREE.Group();
    workerParts.glasses.position.set(0, 0.02, 0.105); // Altura de los ojos

    // Montura negra
    const frame = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.08, 0.01), beltMat);
    workerParts.glasses.add(frame);

    // Lentes azules transparentes
    const lenses = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.06, 0.02), goggleMat);
    lenses.position.z = 0.005; // Sobresaliendo un poco de la montura
    workerParts.glasses.add(lenses);

    // Correa elÃ¡stica trasera
    const strap = new THREE.Mesh(new THREE.BoxGeometry(0.19, 0.02, 0.22), beltMat);
    strap.position.z = -0.10;
    workerParts.glasses.add(strap);

    workerParts.head.add(workerParts.glasses);

    // Ensamblaje final
    workerParts.torso.add(workerParts.head);
    worker.add(workerParts.torso);

    // PosiciÃ³n inicial
    worker.position.set(40, 0, 40);
    workerMotion.desiredYaw = worker.rotation.y;
    scene.add(worker);
}

function updateWorkerMovement(delta) {
    if (!worker) return;
    
    const inputX = (keys['d'] ? 1 : 0) - (keys['a'] ? 1 : 0);
    const inputZ = (keys['w'] ? 1 : 0) - (keys['s'] ? 1 : 0);
    const hasInput = inputX !== 0 || inputZ !== 0;
    const canMove = !isWorldInputBlocked();
    const maxSpeed = 5.2;
    const blend = Math.min(1, delta * (hasInput && canMove ? 7.5 : 5.5));
    
    if (hasInput && canMove) {
        workerMotion.desiredVelocity.set(inputX, 0, inputZ).normalize().multiplyScalar(maxSpeed);
    } else {
        workerMotion.desiredVelocity.set(0, 0, 0);
    }
    
    workerMotion.velocity.lerp(workerMotion.desiredVelocity, blend);
    
    if (!hasInput && workerMotion.velocity.lengthSq() < 0.0005) {
        workerMotion.velocity.set(0, 0, 0);
    }
    
    const radius = 0.3;
    const previousX = worker.position.x;
    const previousZ = worker.position.z;
    let nextX = THREE.MathUtils.clamp(previousX + workerMotion.velocity.x * delta, worldBounds.minX, worldBounds.maxX);
    let nextZ = THREE.MathUtils.clamp(previousZ + workerMotion.velocity.z * delta, worldBounds.minZ, worldBounds.maxZ);
    
    worldObstacles.forEach((obstacle) => {
        const collideX = Math.abs(nextX - obstacle.x) < obstacle.halfWidth + radius
            && Math.abs(previousZ - obstacle.z) < obstacle.halfDepth + radius;
        const collideZ = Math.abs(previousX - obstacle.x) < obstacle.halfWidth + radius
            && Math.abs(nextZ - obstacle.z) < obstacle.halfDepth + radius;
        
        if (collideX) {
            nextX = previousX;
            workerMotion.velocity.x = 0;
        }
        if (collideZ) {
            nextZ = previousZ;
            workerMotion.velocity.z = 0;
        }
    });
    
    worker.position.x = nextX;
    worker.position.z = nextZ;
    
    const speed = workerMotion.velocity.length();
    workerMotion.normalizedSpeed = THREE.MathUtils.clamp(speed / maxSpeed, 0, 1);
    
    if (speed > 0.05) {
        workerMotion.desiredYaw = Math.atan2(workerMotion.velocity.x, workerMotion.velocity.z);
        worker.rotation.y = lerpAngle(worker.rotation.y, workerMotion.desiredYaw, Math.min(1, delta * 9));
    }
}

// AnimaciÃ³n de caminata
function animateWorker(delta) {
    if (!worker || !gameStarted || gamePaused) return;
    
    const torsoBaseY = 1.0;
    const headBaseY = 0.48;
    const speed = workerMotion.normalizedSpeed;
    const moving = speed > 0.03;
    
    worker.position.y = 0;
    workerParts.torso.scale.y = 1;
    
    if (moving) {
        workerMotion.stridePhase += delta * (4 + speed * 10);
        const phase = workerMotion.stridePhase;
        const legSwing = Math.sin(phase) * (0.18 + speed * 0.42);
        const leftKnee = Math.max(0, Math.sin(phase)) * (0.08 + speed * 0.45);
        const rightKnee = Math.max(0, Math.sin(phase + Math.PI)) * (0.08 + speed * 0.45);
        const bounce = Math.abs(Math.sin(phase * 2)) * 0.03 * speed;
        
        workerParts.leftThigh.rotation.x = legSwing;
        workerParts.leftShin.rotation.x = leftKnee;
        workerParts.leftFoot.rotation.x = -leftKnee * 0.35;
        
        workerParts.rightThigh.rotation.x = -legSwing;
        workerParts.rightShin.rotation.x = rightKnee;
        workerParts.rightFoot.rotation.x = -rightKnee * 0.35;
        
        workerParts.leftArm.rotation.x = -legSwing * 0.68 - speed * 0.08;
        workerParts.leftArm.rotation.z = 0.05;
        workerParts.rightArm.rotation.x = legSwing * 0.68 - speed * 0.08;
        workerParts.rightArm.rotation.z = -0.05;
        
        workerParts.torso.position.y = torsoBaseY + bounce;
        workerParts.torso.rotation.x = 0.03 + speed * 0.06;
        workerParts.torso.rotation.z = Math.sin(phase) * 0.035 * speed;
        workerParts.head.position.y = headBaseY + Math.sin(phase * 2) * 0.008 * speed;
        workerParts.head.rotation.x = -0.02 + bounce * 0.5;
        workerParts.head.rotation.y = Math.sin(phase) * 0.04 * speed;
    } else {
        const breathe = Math.sin(Date.now() * 0.002) * 0.006;
        const resetBlend = Math.min(1, delta * 8);
        
        workerParts.torso.scale.y = 1 + breathe;
        workerParts.torso.position.y = THREE.MathUtils.lerp(workerParts.torso.position.y, torsoBaseY + breathe * 0.6, resetBlend);
        workerParts.torso.rotation.x = THREE.MathUtils.lerp(workerParts.torso.rotation.x, 0, resetBlend);
        workerParts.torso.rotation.z = THREE.MathUtils.lerp(workerParts.torso.rotation.z, 0, resetBlend);
        workerParts.head.position.y = THREE.MathUtils.lerp(workerParts.head.position.y, headBaseY + breathe * 0.4, resetBlend);
        workerParts.head.rotation.x = THREE.MathUtils.lerp(workerParts.head.rotation.x, 0, resetBlend);
        workerParts.head.rotation.y = Math.sin(Date.now() * 0.0015) * 0.03;
        
        workerParts.leftArm.rotation.x = THREE.MathUtils.lerp(workerParts.leftArm.rotation.x, -0.08, resetBlend);
        workerParts.leftArm.rotation.z = THREE.MathUtils.lerp(workerParts.leftArm.rotation.z, 0.05, resetBlend);
        workerParts.rightArm.rotation.x = THREE.MathUtils.lerp(workerParts.rightArm.rotation.x, -0.08, resetBlend);
        workerParts.rightArm.rotation.z = THREE.MathUtils.lerp(workerParts.rightArm.rotation.z, -0.05, resetBlend);
        
        workerParts.leftThigh.rotation.x = THREE.MathUtils.lerp(workerParts.leftThigh.rotation.x, 0, resetBlend);
        workerParts.leftShin.rotation.x = THREE.MathUtils.lerp(workerParts.leftShin.rotation.x, 0, resetBlend);
        workerParts.leftFoot.rotation.x = THREE.MathUtils.lerp(workerParts.leftFoot.rotation.x, 0, resetBlend);
        workerParts.rightThigh.rotation.x = THREE.MathUtils.lerp(workerParts.rightThigh.rotation.x, 0, resetBlend);
        workerParts.rightShin.rotation.x = THREE.MathUtils.lerp(workerParts.rightShin.rotation.x, 0, resetBlend);
        workerParts.rightFoot.rotation.x = THREE.MathUtils.lerp(workerParts.rightFoot.rotation.x, 0, resetBlend);
    }
}

// ==================== ZONAS ====================
function createAllZones() {
    console.log('Creando zonas...');
    createZoneRecepcion();
    console.log('Zona Recepción creada');
    createZoneVestuario();
    console.log('Zona Vestuario creada');
    createZoneAlmacen();
    createZoneMaquinas();
    createZoneAlturas();
    createZoneConfinados();
    createZoneElectrico();
    createZoneQuimico();
    createZoneCapacitacion();
    console.log('Zona Capacitación creada');
    createZoneInvestigacion();
    console.log('Zona Investigación creada');
    console.log('Todas las zonas creadas');
}

// ==================== ZONA ALMACÃ‰N ====================
function createZoneAlmacen() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'almacen', name: 'Almacén' };
    const zoneWidth = 38;
    const zoneDepth = 34;
    const centerX = -28;
    const centerZ = -28;
    
    const slab = new THREE.Mesh(
        new THREE.BoxGeometry(zoneWidth, 0.18, zoneDepth),
        new THREE.MeshStandardMaterial({ color: 0x455a64, roughness: 0.92, metalness: 0.08 })
    );
    slab.position.set(centerX, 0.09, centerZ);
    zoneGroup.add(slab);
    
    // Plataforma de zona
    const platformGeo = new THREE.PlaneGeometry(zoneWidth, zoneDepth);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0xf39c12, transparent: true, opacity: 0.08 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(centerX, 0.19, centerZ);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(zoneWidth, 0.1, zoneDepth));
    const borderMat = new THREE.LineBasicMaterial({ color: 0xf39c12 });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(centerX, 0.22, centerZ);
    zoneGroup.add(border);
    
    createWarehouseFloorMarkings(centerX, centerZ, zoneWidth, zoneDepth, zoneGroup);
    
    // Hileras de estanterias y areas operativas
    createWarehouseRackRow(-39, -28, zoneGroup, { segments: 5, spacing: 6.1, loaded: true, unsafeTopLoad: true });
    createWarehouseRackRow(-28, -28, zoneGroup, { segments: 5, spacing: 6.1, loaded: true });
    createWarehouseRackRow(-17, -28, zoneGroup, { segments: 5, spacing: 6.1, loaded: true });
    
    // Pallets en el piso
    const palletConfigs = [
        { pos: [-43.5, -18], color: 0xe67e22, stackCount: 2 },
        { pos: [-43.5, -37.5], color: 0x3498db, stackCount: 1 },
        { pos: [-23.5, -17.8], color: 0x2ecc71, stackCount: 1, scattered: true },
        { pos: [-14.8, -39], color: 0x9b59b6, stackCount: 2, blocking: true }
    ];
    
    palletConfigs.forEach((item) => {
        createPallet(item.pos[0], item.pos[1], item.color, zoneGroup, item);
    });
    
    createForklift(-44, -31.5, zoneGroup);
    
    // CARGAS/PELIGROS IDENTIFICABLES
    addDanger({
        position: new THREE.Vector3(-39, 4.6, -28),
        type: 'Caída de objetos',
        description: 'Cajas apiladas sin seguridad en estantería alta',
        probability: 4,
        severity: 3,
        zone: 'almacen',
        mesh: createDangerHighlight(-39, 4.6, -28, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-23.5, 0.12, -17.8),
        type: 'Piso resbaloso',
        description: 'Piso mojado sin señalización',
        probability: 3,
        severity: 2,
        zone: 'almacen',
        mesh: createDangerHighlight(-23.5, 0.12, -17.8, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-14.8, 0.12, -39),
        type: 'ObstrucciÃ³n',
        description: 'Materiales bloqueando el pasillo',
        probability: 4,
        severity: 2,
        zone: 'almacen',
        mesh: createDangerHighlight(-14.8, 0.12, -39, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createWarehouseFloorMarkings(centerX, centerZ, width, depth, parent) {
    const markingMat = new THREE.MeshBasicMaterial({ color: 0xf8e16c, transparent: true, opacity: 0.28 });
    const walkwayMat = new THREE.MeshBasicMaterial({ color: 0x10b981, transparent: true, opacity: 0.12 });
    
    const leftWalkway = new THREE.Mesh(new THREE.PlaneGeometry(2.8, depth - 4), walkwayMat);
    leftWalkway.rotation.x = -Math.PI / 2;
    leftWalkway.position.set(centerX - width / 2 + 3.4, 0.2, centerZ);
    parent.add(leftWalkway);
    
    const forkliftLane = new THREE.Mesh(new THREE.PlaneGeometry(3.2, depth - 3), markingMat);
    forkliftLane.rotation.x = -Math.PI / 2;
    forkliftLane.position.set(centerX - width / 2 + 7.2, 0.2, centerZ);
    parent.add(forkliftLane);
    
    [-14.3, 14.3].forEach((offsetZ) => {
        const crossAisle = new THREE.Mesh(new THREE.PlaneGeometry(width - 5, 1.8), markingMat);
        crossAisle.rotation.x = -Math.PI / 2;
        crossAisle.position.set(centerX, 0.2, centerZ + offsetZ);
        parent.add(crossAisle);
    });
}

function createWarehouseRackRow(x, z, parent, options = {}) {
    const rowGroup = new THREE.Group();
    const segments = options.segments ?? 4;
    const spacing = options.spacing ?? 5.8;
    const totalLength = (segments - 1) * spacing + 4.8;
    
    for (let i = 0; i < segments; i++) {
        const offset = (i - (segments - 1) / 2) * spacing;
        createRack(0, offset, rowGroup, {
            width: 4.8,
            depth: 1.6,
            height: 5.8,
            levels: 4,
            loaded: options.loaded,
            unsafeTopLoad: options.unsafeTopLoad && i === Math.floor(segments / 2)
        });
    }
    
    rowGroup.position.set(x, 0, z);
    rowGroup.rotation.y = options.rotationY || 0;
    parent.add(rowGroup);
    registerObstacle(x, z, 2.1, totalLength, 0.25);
}

function createRack(x, z, parent, options = {}) {
    const rackGroup = new THREE.Group();
    const postMat = new THREE.MeshStandardMaterial({ color: 0x95a5a6, metalness: 0.8 });
    const shelfMat = new THREE.MeshStandardMaterial({ color: 0x3498db });
    const width = options.width ?? 4;
    const depth = options.depth ?? 1.2;
    const height = options.height ?? 5;
    const levels = options.levels ?? 4;
    const loaded = options.loaded ?? false;
    const unsafeTopLoad = options.unsafeTopLoad ?? false;
    const halfWidth = width / 2;
    const halfDepth = depth / 2;
    const levelHeights = Array.from({ length: levels }, (_, index) => 0.8 + index * ((height - 1.2) / Math.max(1, levels - 1)));
    
    // Postes
    const postGeo = new THREE.BoxGeometry(0.16, height, 0.16);
    [[-halfWidth, -halfDepth], [halfWidth, -halfDepth], [-halfWidth, halfDepth], [halfWidth, halfDepth]].forEach(pos => {
        const post = new THREE.Mesh(postGeo, postMat);
        post.position.set(pos[0], height / 2, pos[1]);
        post.castShadow = true;
        rackGroup.add(post);
    });
    
    // Estantes
    const shelfGeo = new THREE.BoxGeometry(width, 0.1, depth);
    levelHeights.forEach((y, index) => {
        const shelf = new THREE.Mesh(shelfGeo, shelfMat);
        shelf.position.y = y;
        rackGroup.add(shelf);
        
        if (loaded) {
            const boxCount = unsafeTopLoad && index === levelHeights.length - 1 ? 3 : 2;
            for (let boxIndex = 0; boxIndex < boxCount; boxIndex++) {
                const load = new THREE.Mesh(
                    new THREE.BoxGeometry(1.05, 0.7, depth - 0.2),
                    new THREE.MeshStandardMaterial({ color: boxIndex % 2 === 0 ? 0xc97a32 : 0x8d5524 })
                );
                const offsetX = boxCount === 1 ? 0 : -0.75 + boxIndex * 0.75;
                load.position.set(offsetX, y + 0.4, 0);
                if (unsafeTopLoad && index === levelHeights.length - 1 && boxIndex === 2) {
                    load.position.x += 0.45;
                    load.rotation.z = 0.15;
                }
                load.castShadow = true;
                rackGroup.add(load);
            }
        }
    });
    
    const beamGeo = new THREE.BoxGeometry(width + 0.2, 0.08, 0.08);
    [1.6, 3.1, 4.6].forEach((y) => {
        [-halfDepth, halfDepth].forEach((beamZ) => {
            const beam = new THREE.Mesh(beamGeo, postMat);
            beam.position.set(0, y, beamZ);
            rackGroup.add(beam);
        });
    });
    
    rackGroup.position.set(x, 0, z);
    parent.add(rackGroup);
}

function createPallet(x, z, color, parent, options = {}) {
    const palletGroup = new THREE.Group();
    const stackCount = options.stackCount ?? 1;
    
    // Base
    const baseGeo = new THREE.BoxGeometry(1.4, 0.12, 1.4);
    const baseMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.06;
    palletGroup.add(base);
    
    for (let level = 0; level < stackCount; level++) {
        const boxGeo = new THREE.BoxGeometry(1.1, 0.78, 1.1);
        const boxMat = new THREE.MeshStandardMaterial({ color, metalness: 0.1 });
        const box = new THREE.Mesh(boxGeo, boxMat);
        box.position.y = 0.52 + level * 0.8;
        box.castShadow = true;
        if (options.scattered && level === stackCount - 1) {
            box.position.x -= 0.22;
            box.rotation.y = 0.22;
        }
        if (options.blocking && level === 0) {
            box.scale.set(1.18, 1, 1.1);
            box.rotation.y = 0.12;
        }
        palletGroup.add(box);
    }
    
    palletGroup.position.set(x, 0, z);
    parent.add(palletGroup);
    
    if (options.blocking) {
        registerObstacle(x, z, 1.7, 1.7, 0.2);
    }
}

function createForklift(x, z, parent) {
    const forklift = new THREE.Group();
    const bodyMat = new THREE.MeshStandardMaterial({ color: 0xf39c12, metalness: 0.2, roughness: 0.6 });
    const darkMat = new THREE.MeshStandardMaterial({ color: 0x2d3436 });
    
    const body = new THREE.Mesh(new THREE.BoxGeometry(2.2, 1.2, 1.4), bodyMat);
    body.position.y = 0.9;
    forklift.add(body);
    
    const mast = new THREE.Mesh(new THREE.BoxGeometry(0.2, 3.2, 1.1), darkMat);
    mast.position.set(1, 1.9, 0);
    forklift.add(mast);
    
    const overheadGuard = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.12, 1.2), darkMat);
    overheadGuard.position.set(-0.15, 1.95, 0);
    forklift.add(overheadGuard);
    
    [-0.7, 0.7].forEach((wheelZ) => {
        [-0.55, 0.65].forEach((wheelX) => {
            const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.28, 0.2, 16), darkMat);
            wheel.rotation.z = Math.PI / 2;
            wheel.position.set(wheelX, 0.28, wheelZ);
            forklift.add(wheel);
        });
    });
    
    [-0.25, 0.25].forEach((forkZ) => {
        const fork = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.08, 0.12), darkMat);
        fork.position.set(1.45, 0.25, forkZ);
        forklift.add(fork);
    });
    
    forklift.position.set(x, 0, z);
    forklift.rotation.y = Math.PI / 2;
    parent.add(forklift);
    registerObstacle(x, z, 2.6, 2.1, 0.25);
}

// ==================== ZONA MÃQUINAS ====================
function createZoneMaquinas() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'maquinas', name: 'Máquinas' };
    
    // Plataforma
    const platformGeo = new THREE.PlaneGeometry(25, 25);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0x4ecdc4, transparent: true, opacity: 0.15 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(25, 0.03, -25);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(25, 0.1, 25));
    const borderMat = new THREE.LineBasicMaterial({ color: 0x4ecdc4 });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(25, 0.05, -25);
    zoneGroup.add(border);
    
    // MÃ¡quinas
    createMachine(15, -15, zoneGroup);
    createMachine(25, -25, zoneGroup);
    createMachine(35, -15, zoneGroup);
    
    // Transportador
    createConveyor(20, -35, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(15, 1, -15),
        type: 'Punto de pinzamiento',
        description: 'Guarda de protección removida en máquina fresadora',
        probability: 5,
        severity: 4,
        zone: 'maquinas',
        mesh: createDangerHighlight(15, 1, -15, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(25, 0.8, -25),
        type: 'Superficie caliente',
        description: 'Máquina sin guarda cerca de superficie caliente',
        probability: 3,
        severity: 3,
        zone: 'maquinas',
        mesh: createDangerHighlight(25, 0.8, -25, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(35, 1.5, -15),
        type: 'ProyecciÃ³n de partÃ­culas',
        description: 'Operario sin gafas protectoras cerca de esmeril',
        probability: 4,
        severity: 3,
        zone: 'maquinas',
        mesh: createDangerHighlight(35, 1.5, -15, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createMachine(x, z, parent) {
    const machineGroup = new THREE.Group();
    const bodyMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d, metalness: 0.8 });
    const accentMat = new THREE.MeshStandardMaterial({ color: 0xe74c3c });
    
    // Cuerpo principal
    const bodyGeo = new THREE.BoxGeometry(4, 2, 3);
    const body = new THREE.Mesh(bodyGeo, bodyMat);
    body.position.y = 1;
    body.castShadow = true;
    machineGroup.add(body);
    
    // Panel de control
    const panelGeo = new THREE.BoxGeometry(0.5, 1, 2);
    const panel = new THREE.Mesh(panelGeo, accentMat);
    panel.position.set(1.5, 1.5, 0);
    machineGroup.add(panel);
    
    // BotÃ³n de emergencia
    const btnGeo = new THREE.CylinderGeometry(0.15, 0.15, 0.1, 16);
    const btnMat = new THREE.MeshStandardMaterial({ color: 0xe74c3c });
    const btn = new THREE.Mesh(btnGeo, btnMat);
    btn.position.set(1.5, 2.1, 0.5);
    machineGroup.add(btn);
    
    machineGroup.position.set(x, 0, z);
    parent.add(machineGroup);
}

function createConveyor(x, z, parent) {
    const conveyorGroup = new THREE.Group();
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x34495e });
    
    // Estructura
    const frameGeo = new THREE.BoxGeometry(15, 0.3, 1);
    const frame = new THREE.Mesh(frameGeo, frameMat);
    frame.position.y = 0.8;
    conveyorGroup.add(frame);
    
    // Rodillos
    const rollerMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    for (let i = -7; i <= 7; i += 1) {
        const rollerGeo = new THREE.CylinderGeometry(0.15, 0.15, 0.8, 8);
        const roller = new THREE.Mesh(rollerGeo, rollerMat);
        roller.rotation.x = Math.PI / 2;
        roller.position.set(i, 0.8, 0);
        conveyorGroup.add(roller);
    }
    
    conveyorGroup.position.set(x, 0, z);
    parent.add(conveyorGroup);
}

// ==================== ZONA TRABAJO EN ALTURAS ====================
function createZoneAlturas() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'alturas', name: 'Trabajo en Alturas' };
    
    // Plataforma
    const platformGeo = new THREE.PlaneGeometry(30, 20);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0xf7d794, transparent: true, opacity: 0.15 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(0, 0.03, -50);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(30, 0.1, 20));
    const borderMat = new THREE.LineBasicMaterial({ color: 0xf7d794 });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(0, 0.05, -50);
    zoneGroup.add(border);
    
    // Andamio
    createScaffolding(0, -50, zoneGroup);
    
    // Escalera de acceso
    createLadder(-8, -42, zoneGroup);
    
    // LÃ­nea de vida
    createFallProtection(0, -50, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(0, 6, -50),
        type: 'CaÃ­da en alturas',
        description: 'Trabajador sin arnÃ©s en plataforma a 6m de altura',
        probability: 5,
        severity: 4,
        zone: 'alturas',
        mesh: createDangerHighlight(0, 6, -50, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-8, 3, -42),
        type: 'Escalera insegura',
        description: 'Escalera sin aseguramiento ni compaÃ±ero de sujeciÃ³n',
        probability: 4,
        severity: 3,
        zone: 'alturas',
        mesh: createDangerHighlight(-8, 3, -42, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(5, 4, -50),
        type: 'Sin lÃ­nea de vida',
        description: 'Ausencia de punto de anclaje para sistema anticaÃ­das',
        probability: 5,
        severity: 4,
        zone: 'alturas',
        mesh: createDangerHighlight(5, 4, -50, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createScaffolding(x, z, parent) {
    const scaffoldGroup = new THREE.Group();
    const frameMat = new THREE.MeshStandardMaterial({ color: 0xe74c3c, metalness: 0.6 });
    
    // Montantes
    const postGeo = new THREE.BoxGeometry(0.1, 8, 0.1);
    [[-3, -3], [-3, 3], [3, -3], [3, 3]].forEach(pos => {
        const post = new THREE.Mesh(postGeo, frameMat);
        post.position.set(pos[0], 4, pos[1]);
        scaffoldGroup.add(post);
    });
    
    // Plataformas
    const platGeo = new THREE.BoxGeometry(6, 0.1, 6);
    [1, 4, 7].forEach(y => {
        const plat = new THREE.Mesh(platGeo, frameMat);
        plat.position.y = y;
        scaffoldGroup.add(plat);
    });
    
    // Barandales
    const railGeo = new THREE.BoxGeometry(6, 0.05, 0.05);
    [1.5, 5].forEach(y => {
        [-3, 3].forEach(z => {
            const rail = new THREE.Mesh(railGeo, frameMat);
            rail.position.set(0, y, z);
            scaffoldGroup.add(rail);
        });
    });
    
    scaffoldGroup.position.set(x, 0, z);
    parent.add(scaffoldGroup);
}

function createLadder(x, z, parent) {
    const ladderGroup = new THREE.Group();
    const railMat = new THREE.MeshStandardMaterial({ color: 0xf39c12 });
    
    // Largueros
    const railGeo = new THREE.BoxGeometry(0.1, 5, 0.1);
    [-0.4, 0.4].forEach(offset => {
        const rail = new THREE.Mesh(railGeo, railMat);
        rail.position.set(offset, 2.5, 0);
        ladderGroup.add(rail);
    });
    
    // PeldaÃ±os
    const stepGeo = new THREE.BoxGeometry(0.8, 0.05, 0.05);
    for (let i = 0; i < 10; i++) {
        const step = new THREE.Mesh(stepGeo, railMat);
        step.position.y = 0.5 + i * 0.5;
        ladderGroup.add(step);
    }
    
    ladderGroup.position.set(x, 0, z);
    parent.add(ladderGroup);
}

function createFallProtection(x, z, parent) {
    const protectionGroup = new THREE.Group();
    const cableMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    
    // Anclaje superior
    const anchorGeo = new THREE.BoxGeometry(0.5, 0.5, 0.5);
    const anchorMat = new THREE.MeshStandardMaterial({ color: 0x27ae60 });
    const anchor = new THREE.Mesh(anchorGeo, anchorMat);
    anchor.position.set(0, 8, 0);
    protectionGroup.add(anchor);
    
    // Cable horizontal
    const cableGeo = new THREE.CylinderGeometry(0.02, 0.02, 8, 8);
    const cable = new THREE.Mesh(cableGeo, cableMat);
    cable.rotation.z = Math.PI / 2;
    cable.position.set(0, 7, 0);
    protectionGroup.add(cable);
    
    protectionGroup.position.set(x, 0, z);
    parent.add(protectionGroup);
}

// ==================== ZONA ESPACIOS CONFINADOS ====================
function createZoneConfinados() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'confinados', name: 'Espacios Confinados' };
    
    // Plataforma
    const platformGeo = new THREE.PlaneGeometry(20, 20);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0x74b9ff, transparent: true, opacity: 0.15 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(-50, 0.03, 0);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(20, 0.1, 20));
    const borderMat = new THREE.LineBasicMaterial({ color: 0x74b9ff });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(-50, 0.05, 0);
    zoneGroup.add(border);
    
    // Tanque/Silo
    createTank(-50, 0, zoneGroup);
    
    // Pozo de inspección
    createManhole(-42, 0, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(-50, 1.5, 0),
        type: 'Atmósfera peligrosa',
        description: 'Tanque sin medición de oxígeno ni gases tóxicos',
        probability: 4,
        severity: 4,
        zone: 'confinados',
        mesh: createDangerHighlight(-50, 1.5, 0, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-42, 0.5, 0),
        type: 'Espacio confinado',
        description: 'Pozo abierto sin permiso de trabajo ni vigilante',
        probability: 4,
        severity: 4,
        zone: 'confinados',
        mesh: createDangerHighlight(-42, 0.5, 0, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-55, 0.5, 0),
        type: 'Energías peligrosas',
        description: 'Tubería sin bloqueo ni etiquetado (LOTO)',
        probability: 3,
        severity: 4,
        zone: 'confinados',
        mesh: createDangerHighlight(-55, 0.5, 0, zoneGroup)
    });

    addDanger({
        position: new THREE.Vector3(-48, 1.5, 5),
        type: 'Falta de señalización',
        description: 'Espacio confinado sin señalización de advertencia ni procedimientos',
        probability: 3,
        severity: 3,
        zone: 'confinados',
        mesh: createDangerHighlight(-48, 1.5, 5, zoneGroup)
    });

    scene.add(zoneGroup);
}

function createTank(x, z, parent) {
    const tankGroup = new THREE.Group();
    const tankMat = new THREE.MeshStandardMaterial({ color: 0x636e72 });
    
    // Cuerpo del tanque
    const bodyGeo = new THREE.CylinderGeometry(3, 3, 5, 16);
    const body = new THREE.Mesh(bodyGeo, tankMat);
    body.position.y = 2.5;
    body.castShadow = true;
    tankGroup.add(body);
    
    // Tapa
    const topGeo = new THREE.CylinderGeometry(3.2, 3, 0.3, 16);
    const top = new THREE.Mesh(topGeo, tankMat);
    top.position.y = 5.15;
    tankGroup.add(top);
    
    // Entrada
    const entryGeo = new THREE.CylinderGeometry(0.8, 0.8, 0.5, 16);
    const entry = new THREE.Mesh(entryGeo, tankMat);
    entry.position.y = 5.5;
    tankGroup.add(entry);
    
    // Escalera
    const ladderGeo = new THREE.BoxGeometry(0.1, 3, 0.1);
    [-1, 1].forEach(offset => {
        const rail = new THREE.Mesh(ladderGeo, new THREE.MeshStandardMaterial({ color: 0xf39c12 }));
        rail.position.set(offset, 1.5, 3.2);
        tankGroup.add(rail);
    });
    
    tankGroup.position.set(x, 0, z);
    parent.add(tankGroup);
}

function createManhole(x, z, parent) {
    const holeGroup = new THREE.Group();
    
    // Marco
    const frameGeo = new THREE.TorusGeometry(1.2, 0.2, 8, 16);
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x636e72 });
    const frame = new THREE.Mesh(frameGeo, frameMat);
    frame.rotation.x = Math.PI / 2;
    frame.position.y = 0.2;
    holeGroup.add(frame);
    
    // Profundidad (visual)
    const depthGeo = new THREE.CylinderGeometry(1, 1.5, 2, 16);
    const depthMat = new THREE.MeshBasicMaterial({ color: 0x1a1a1a });
    const depth = new THREE.Mesh(depthGeo, depthMat);
    depth.position.y = -0.9;
    holeGroup.add(depth);
    
    holeGroup.position.set(x, 0, z);
    parent.add(holeGroup);
}

// ==================== ZONA ELÃ‰CTRICA ====================
function createZoneElectrico() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'electrico', name: 'Zona Eléctrica' };
    
    // Plataforma
    const platformGeo = new THREE.PlaneGeometry(20, 20);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0xa29bfe, transparent: true, opacity: 0.15 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(50, 0.03, 25);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(20, 0.1, 20));
    const borderMat = new THREE.LineBasicMaterial({ color: 0xa29bfe });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(50, 0.05, 25);
    zoneGroup.add(border);
    
    // Tablero eléctrico
    createElectricPanel(45, 20, zoneGroup);
    createElectricPanel(55, 20, zoneGroup);
    
    // Cableado
    createCableTray(50, 25, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(45, 1.5, 20),
        type: 'Contacto eléctrico',
        description: 'Tablero abierto con cables expuestos energizados',
        probability: 4,
        severity: 4,
        zone: 'electrico',
        mesh: createDangerHighlight(45, 1.5, 20, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(50, 0.5, 30),
        type: 'Cable dañado',
        description: 'Cable de alimentación con aislamiento deteriorado',
        probability: 3,
        severity: 4,
        zone: 'electrico',
        mesh: createDangerHighlight(50, 0.5, 30, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(55, 1.5, 20),
        type: 'Falta de puesta a tierra',
        description: 'Interruptor diferencial sin conexión a tierra',
        probability: 3,
        severity: 4,
        zone: 'electrico',
        mesh: createDangerHighlight(55, 1.5, 20, zoneGroup)
    });

    addDanger({
        position: new THREE.Vector3(50, 1.5, 30),
        type: 'Falta de señalización',
        description: 'Área de alto voltaje sin señalización de advertencia',
        probability: 3,
        severity: 3,
        zone: 'electrico',
        mesh: createDangerHighlight(50, 1.5, 30, zoneGroup)
    });

    scene.add(zoneGroup);
}

function createElectricPanel(x, z, parent) {
    const panelGroup = new THREE.Group();
    const panelMat = new THREE.MeshStandardMaterial({ color: 0x636e72, metalness: 0.5 });
    
    // Gabinente
    const cabinetGeo = new THREE.BoxGeometry(1.5, 2, 0.3);
    const cabinet = new THREE.Mesh(cabinetGeo, panelMat);
    cabinet.position.y = 1.5;
    panelGroup.add(cabinet);
    
    // Puerta (abierta)
    const doorGeo = new THREE.BoxGeometry(1.4, 1.8, 0.05);
    const doorMat = new THREE.MeshStandardMaterial({ color: 0x95a5a6 });
    const door = new THREE.Mesh(doorGeo, doorMat);
    door.position.set(0.7, 1.5, 0.2);
    door.rotation.y = -Math.PI / 4;
    panelGroup.add(door);
    
    // Breakers
    const breakerGeo = new THREE.BoxGeometry(0.15, 0.3, 0.1);
    const breakerMat = new THREE.MeshStandardMaterial({ color: 0xe74c3c });
    for (let row = 0; row < 4; row++) {
        for (let col = 0; col < 3; col++) {
            const breaker = new THREE.Mesh(breakerGeo, breakerMat);
            breaker.position.set(-0.4 + col * 0.4, 0.8 + row * 0.4, 0.1);
            panelGroup.add(breaker);
        }
    }
    
    panelGroup.position.set(x, 0, z);
    parent.add(panelGroup);
}

function createCableTray(x, z, parent) {
    const trayGroup = new THREE.Group();
    const trayMat = new THREE.MeshStandardMaterial({ color: 0x636e72 });
    
    // Bandeja
    const trayGeo = new THREE.BoxGeometry(8, 0.1, 0.5);
    const tray = new THREE.Mesh(trayGeo, trayMat);
    tray.position.y = 3;
    trayGroup.add(tray);
    
    // Cables
    const cableColors = [0x1a1a1a, 0xe74c3c, 0x3498db, 0xf1c40f];
    for (let i = 0; i < 4; i++) {
        const cableGeo = new THREE.CylinderGeometry(0.05, 0.05, 8, 8);
        const cableMat = new THREE.MeshStandardMaterial({ color: cableColors[i] });
        const cable = new THREE.Mesh(cableGeo, cableMat);
        cable.rotation.z = Math.PI / 2;
        cable.position.set(0, 3.08, -0.15 + i * 0.12);
        trayGroup.add(cable);
    }
    
    trayGroup.position.set(x, 0, z);
    parent.add(trayGroup);
}

// ==================== ZONA QUÃMICA ====================
function createZoneQuimico() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'quimico', name: 'Zona Química' };
    
    // Plataforma
    const platformGeo = new THREE.PlaneGeometry(20, 20);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0x55efc4, transparent: true, opacity: 0.15 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(-50, 0.03, 25);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(20, 0.1, 20));
    const borderMat = new THREE.LineBasicMaterial({ color: 0x55efc4 });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(-50, 0.05, 25);
    zoneGroup.add(border);
    
    // EstantesåŒ–å­¦å“
    createChemicalShelf(-45, 20, zoneGroup);
    createChemicalShelf(-55, 20, zoneGroup);
    
    // Contenedores
    createChemicalContainer(-50, 30, 0xe74c3c, 'Inflamable', zoneGroup);
    createChemicalContainer(-47, 30, 0x3498db, 'Tóxico', zoneGroup);
    createChemicalContainer(-53, 30, 0xf1c40f, 'Corrosivo', zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(-45, 1.5, 20),
        type: 'Almacenamiento incorrecto',
        description: 'Sustancias incompatibles almacenadas juntas',
        probability: 4,
        severity: 4,
        zone: 'quimico',
        mesh: createDangerHighlight(-45, 1.5, 20, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-50, 0.5, 30),
        type: 'Sin contenedor secundario',
        description: 'Tambor de quÃ­mico sin bandeja de contenciÃ³n',
        probability: 3,
        severity: 3,
        zone: 'quimico',
        mesh: createDangerHighlight(-50, 0.5, 30, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-55, 0.5, 30),
        type: 'Sin EPP adecuado',
        description: 'Trabajador manipulando Ã¡cidos sin guantes de neopreno',
        probability: 4,
        severity: 3,
        zone: 'quimico',
        mesh: createDangerHighlight(-55, 0.5, 30, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createChemicalShelf(x, z, parent) {
    const shelfGroup = new THREE.Group();
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x636e72, metalness: 0.5 });
    
    // Marco
    const postGeo = new THREE.BoxGeometry(0.1, 3, 0.1);
    [[-1.5, -0.5], [-1.5, 0.5], [1.5, -0.5], [1.5, 0.5]].forEach(pos => {
        const post = new THREE.Mesh(postGeo, frameMat);
        post.position.set(pos[0], 1.5, pos[1]);
        shelfGroup.add(post);
    });
    
    // Estantes
    const shelfGeo = new THREE.BoxGeometry(3, 0.05, 1);
    [0.5, 1.5, 2.5].forEach(y => {
        const shelf = new THREE.Mesh(shelfGeo, frameMat);
        shelf.position.y = y;
        shelfGroup.add(shelf);
    });
    
    shelfGroup.position.set(x, 0, z);
    parent.add(shelfGroup);
}

function createChemicalContainer(x, z, color, label, parent) {
    const containerGroup = new THREE.Group();
    
    // Tambor
    const drumGeo = new THREE.CylinderGeometry(0.4, 0.4, 1, 16);
    const drumMat = new THREE.MeshStandardMaterial({ color });
    const drum = new THREE.Mesh(drumGeo, drumMat);
    drum.position.y = 0.5;
    containerGroup.add(drum);
    
    // Etiqueta de peligro
    const tagGeo = new THREE.BoxGeometry(0.3, 0.2, 0.01);
    const tagMat = new THREE.MeshBasicMaterial({ color: 0xf1c40f });
    const tag = new THREE.Mesh(tagGeo, tagMat);
    tag.position.set(0, 0.7, 0.41);
    containerGroup.add(tag);
    
    containerGroup.position.set(x, 0, z);
    parent.add(containerGroup);
}

// ==================== FUNCIONES AUXILIARES PARA OFICINA ====================
function createDesk(x, z, parent) {
    const deskGroup = new THREE.Group();
    const deskMat = new THREE.MeshStandardMaterial({ color: 0x8b4513, roughness: 0.9 });
    const legMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50, metalness: 0.7 });
    
    // Tablero
    const topGeo = new THREE.BoxGeometry(1.6, 0.05, 0.8);
    const top = new THREE.Mesh(topGeo, deskMat);
    top.position.y = 0.7;
    deskGroup.add(top);
    
    // Patas
    const legGeo = new THREE.BoxGeometry(0.05, 0.7, 0.05);
    [[-0.75, -0.35], [0.75, -0.35], [-0.75, 0.35], [0.75, 0.35]].forEach(pos => {
        const leg = new THREE.Mesh(legGeo, legMat);
        leg.position.set(pos[0], 0.35, pos[1]);
        deskGroup.add(leg);
    });
    
    // Cajonera
    const drawerGeo = new THREE.BoxGeometry(0.5, 0.4, 0.4);
    const drawer = new THREE.Mesh(drawerGeo, deskMat);
    drawer.position.set(-0.4, 0.2, -0.2);
    deskGroup.add(drawer);
    
    deskGroup.position.set(x, 0, z);
    parent.add(deskGroup);
}

function createChair(x, z, parent) {
    const chairGroup = new THREE.Group();
    const seatMat = new THREE.MeshStandardMaterial({ color: 0x34495e });
    const legMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    
    // Asiento
    const seatGeo = new THREE.BoxGeometry(0.5, 0.05, 0.5);
    const seat = new THREE.Mesh(seatGeo, seatMat);
    seat.position.y = 0.4;
    chairGroup.add(seat);
    
    // Respaldo
    const backGeo = new THREE.BoxGeometry(0.5, 0.4, 0.05);
    const back = new THREE.Mesh(backGeo, seatMat);
    back.position.set(0, 0.6, -0.2);
    chairGroup.add(back);
    
    // Patas
    const legGeo = new THREE.CylinderGeometry(0.02, 0.02, 0.4, 8);
    [[-0.2, -0.2], [0.2, -0.2], [-0.2, 0.2], [0.2, 0.2]].forEach(pos => {
        const leg = new THREE.Mesh(legGeo, legMat);
        leg.position.set(pos[0], 0.2, pos[1]);
        chairGroup.add(leg);
    });
    
    // Ruedas (sillas de oficina)
    const wheelGeo = new THREE.SphereGeometry(0.03, 8, 8);
    const wheelMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    [[-0.2, -0.2], [0.2, -0.2], [-0.2, 0.2], [0.2, 0.2], [0, 0]].forEach(pos => {
        const wheel = new THREE.Mesh(wheelGeo, wheelMat);
        wheel.position.set(pos[0], 0.02, pos[1]);
        chairGroup.add(wheel);
    });
    
    chairGroup.position.set(x, 0, z);
    parent.add(chairGroup);
}

function createFileCabinet(x, z, parent) {
    const cabinetGroup = new THREE.Group();
    const cabinetMat = new THREE.MeshStandardMaterial({ color: 0x95a5a6, metalness: 0.5 });
    
    // Cuerpo
    const bodyGeo = new THREE.BoxGeometry(0.5, 1.2, 0.4);
    const body = new THREE.Mesh(bodyGeo, cabinetMat);
    body.position.y = 0.6;
    cabinetGroup.add(body);
    
    // Cajones
    const drawerGeo = new THREE.BoxGeometry(0.45, 0.25, 0.35);
    const drawerMat = new THREE.MeshStandardMaterial({ color: 0x34495e });
    [0.3, 0.6, 0.9].forEach(y => {
        const drawer = new THREE.Mesh(drawerGeo, drawerMat);
        drawer.position.set(0, y, 0.02);
        cabinetGroup.add(drawer);
    });
    
    // Manijas
    const handleGeo = new THREE.CylinderGeometry(0.02, 0.02, 0.1, 8);
    const handleMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    [0.3, 0.6, 0.9].forEach(y => {
        const handle = new THREE.Mesh(handleGeo, handleMat);
        handle.rotation.z = Math.PI / 2;
        handle.position.set(0.22, y, 0);
        cabinetGroup.add(handle);
    });
    
    cabinetGroup.position.set(x, 0, z);
    parent.add(cabinetGroup);
}

function createWhiteboard(x, z, parent) {
    const boardGroup = new THREE.Group();
    
    // Pizarra
    const boardGeo = new THREE.BoxGeometry(1.5, 1.0, 0.05);
    const boardMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
    const board = new THREE.Mesh(boardGeo, boardMat);
    board.position.y = 1.0;
    boardGroup.add(board);
    
    // Marco
    const frameGeo = new THREE.BoxGeometry(1.55, 1.05, 0.08);
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    const frame = new THREE.Mesh(frameGeo, frameMat);
    frame.position.y = 1.0;
    boardGroup.add(frame);
    
    // Contenido (diagrama SG-SST)
    const textGeo = new THREE.PlaneGeometry(1.4, 0.8);
    const textMat = new THREE.MeshBasicMaterial({ 
        color: 0x000000,
        transparent: true,
        opacity: 0.7
    });
    const text = new THREE.Mesh(textGeo, textMat);
    text.position.set(0, 1.0, 0.03);
    text.rotation.y = Math.PI; // Para que mire hacia afuera
    boardGroup.add(text);
    
    boardGroup.position.set(x, 0, z);
    parent.add(boardGroup);
}

function createReceptionCounter(x, z, parent) {
    const counterGroup = new THREE.Group();
    const counterMat = new THREE.MeshStandardMaterial({ color: 0x7d3c98 });
    
    // Mostrador
    const topGeo = new THREE.BoxGeometry(3.0, 0.1, 1.2);
    const top = new THREE.Mesh(topGeo, counterMat);
    top.position.y = 1.0;
    counterGroup.add(top);
    
    // Base
    const baseGeo = new THREE.BoxGeometry(2.8, 1.0, 1.0);
    const base = new THREE.Mesh(baseGeo, counterMat);
    base.position.y = 0.5;
    counterGroup.add(base);
    
    // Computadora
    const compGeo = new THREE.BoxGeometry(0.4, 0.3, 0.3);
    const compMat = new THREE.MeshStandardMaterial({ color: 0x1c2833 });
    const computer = new THREE.Mesh(compGeo, compMat);
    computer.position.set(-0.5, 1.15, 0.1);
    counterGroup.add(computer);
    
    counterGroup.position.set(x, 0, z);
    parent.add(counterGroup);
}

function createLockerRow(x, z, parent, count) {
    const rowGroup = new THREE.Group();
    const lockerMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d, metalness: 0.5 });
    
    for (let i = 0; i < count; i++) {
        const lockerGroup = new THREE.Group();
        const lockerGeo = new THREE.BoxGeometry(0.8, 1.8, 0.6);
        const locker = new THREE.Mesh(lockerGeo, lockerMat);
        locker.position.y = 0.9;
        lockerGroup.add(locker);
        
        // Manija
        const handleGeo = new THREE.CylinderGeometry(0.02, 0.02, 0.1, 8);
        const handleMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
        const handle = new THREE.Mesh(handleGeo, handleMat);
        handle.rotation.z = Math.PI / 2;
        handle.position.set(0.35, 0.9, 0.31);
        lockerGroup.add(handle);
        
        lockerGroup.position.set(i * 1.0 - (count - 1) * 0.5, 0, 0);
        rowGroup.add(lockerGroup);
    }
    
    rowGroup.position.set(x, 0, z);
    parent.add(rowGroup);
}

function createEPPTable(x, z, parent) {
    const tableGroup = new THREE.Group();
    const tableMat = new THREE.MeshStandardMaterial({ color: 0x95a5a6 });
    
    // Tablero
    const topGeo = new THREE.BoxGeometry(2.0, 0.05, 1.0);
    const top = new THREE.Mesh(topGeo, tableMat);
    top.position.y = 0.8;
    tableGroup.add(top);
    
    // Patas
    const legGeo = new THREE.BoxGeometry(0.05, 0.8, 0.05);
    [[-0.9, -0.45], [0.9, -0.45], [-0.9, 0.45], [0.9, 0.45]].forEach(pos => {
        const leg = new THREE.Mesh(legGeo, tableMat);
        leg.position.set(pos[0], 0.4, pos[1]);
        tableGroup.add(leg);
    });
    
    // EPP sobre la mesa (casco, guantes, gafas)
    const helmetGeo = new THREE.SphereGeometry(0.15, 16, 16, 0, Math.PI * 2, 0, Math.PI / 2);
    const helmetMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const helmet = new THREE.Mesh(helmetGeo, helmetMat);
    helmet.position.set(-0.5, 0.85, 0);
    tableGroup.add(helmet);
    
    const gloveGeo = new THREE.BoxGeometry(0.15, 0.05, 0.2);
    const gloveMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const glove = new THREE.Mesh(gloveGeo, gloveMat);
    glove.position.set(0.5, 0.85, -0.2);
    tableGroup.add(glove);
    
    tableGroup.position.set(x, 0, z);
    parent.add(tableGroup);
}

function createEyeWashStation(x, z, parent) {
    const stationGroup = new THREE.Group();
    const stationMat = new THREE.MeshStandardMaterial({ color: 0x3498db });
    
    // Base
    const baseGeo = new THREE.BoxGeometry(0.8, 0.1, 0.8);
    const base = new THREE.Mesh(baseGeo, stationMat);
    base.position.y = 0.05;
    stationGroup.add(base);
    
    // Columna
    const columnGeo = new THREE.CylinderGeometry(0.1, 0.1, 1.2, 12);
    const column = new THREE.Mesh(columnGeo, stationMat);
    column.position.y = 0.7;
    stationGroup.add(column);
    
    // Brazos de ducha
    const armGeo = new THREE.CylinderGeometry(0.03, 0.03, 0.4, 8);
    const arm = new THREE.Mesh(armGeo, stationMat);
    arm.rotation.z = Math.PI / 2;
    arm.position.set(0.2, 1.3, 0);
    stationGroup.add(arm);
    
    // Cartel de seguridad
    const signGeo = new THREE.BoxGeometry(0.3, 0.2, 0.01);
    const signMat = new THREE.MeshBasicMaterial({ color: 0xff0000 });
    const sign = new THREE.Mesh(signGeo, signMat);
    sign.position.set(0, 1.6, 0.11);
    stationGroup.add(sign);
    
    stationGroup.position.set(x, 0, z);
    parent.add(stationGroup);
}

function createAuditoriumSeating(x, z, parent) {
    const seatingGroup = new THREE.Group();
    const chairMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    
    // Filas de sillas
    for (let row = 0; row < 4; row++) {
        for (let col = 0; col < 6; col++) {
            const chairGroup = new THREE.Group();
            const seatGeo = new THREE.BoxGeometry(0.5, 0.05, 0.5);
            const seat = new THREE.Mesh(seatGeo, chairMat);
            seat.position.y = 0.25;
            chairGroup.add(seat);
            
            const backGeo = new THREE.BoxGeometry(0.5, 0.4, 0.05);
            const back = new THREE.Mesh(backGeo, chairMat);
            back.position.set(0, 0.45, -0.2);
            chairGroup.add(back);
            
            chairGroup.position.set(
                (col - 2.5) * 1.2,
                0,
                (row - 1.5) * 1.5
            );
            seatingGroup.add(chairGroup);
        }
    }
    
    seatingGroup.position.set(x, 0, z);
    parent.add(seatingGroup);
}

function createPodium(x, z, parent) {
    const podiumGroup = new THREE.Group();
    const podiumMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    
    // Base
    const baseGeo = new THREE.BoxGeometry(1.0, 0.1, 1.0);
    const base = new THREE.Mesh(baseGeo, podiumMat);
    base.position.y = 0.05;
    podiumGroup.add(base);
    
    // Cuerpo
    const bodyGeo = new THREE.BoxGeometry(0.8, 1.0, 0.8);
    const body = new THREE.Mesh(bodyGeo, podiumMat);
    body.position.y = 0.6;
    podiumGroup.add(body);
    
    // Atril
    const standGeo = new THREE.BoxGeometry(0.6, 0.4, 0.05);
    const standMat = new THREE.MeshStandardMaterial({ color: 0x34495e });
    const stand = new THREE.Mesh(standGeo, standMat);
    stand.position.set(0, 1.2, 0.4);
    stand.rotation.x = -0.3;
    podiumGroup.add(stand);
    
    podiumGroup.position.set(x, 0, z);
    parent.add(podiumGroup);
}

function createProjectorScreen(x, z, parent) {
    const screenGroup = new THREE.Group();
    
    // Pantalla
    const screenGeo = new THREE.BoxGeometry(3.0, 2.0, 0.05);
    const screenMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
    const screen = new THREE.Mesh(screenGeo, screenMat);
    screen.position.y = 1.0;
    screenGroup.add(screen);
    
    // Marco
    const frameGeo = new THREE.BoxGeometry(3.1, 2.1, 0.08);
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    const frame = new THREE.Mesh(frameGeo, frameMat);
    frame.position.y = 1.0;
    screenGroup.add(frame);
    
    // Soporte
    const standGeo = new THREE.BoxGeometry(0.1, 2.2, 0.1);
    const standMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    const stand = new THREE.Mesh(standGeo, standMat);
    stand.position.y = 1.1;
    screenGroup.add(stand);
    
    screenGroup.position.set(x, 0, z);
    parent.add(screenGroup);
}

function createNormativeLibrary(x, z, parent) {
    const libraryGroup = new THREE.Group();
    const shelfMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    
    // Estantería
    const shelfGeo = new THREE.BoxGeometry(2.0, 2.0, 0.5);
    const shelf = new THREE.Mesh(shelfGeo, shelfMat);
    shelf.position.y = 1.0;
    libraryGroup.add(shelf);
    
    // Libros
    const bookMat = new THREE.MeshStandardMaterial({ color: 0x3498db });
    for (let i = 0; i < 12; i++) {
        const bookGeo = new THREE.BoxGeometry(0.15, 0.2, 0.3);
        const book = new THREE.Mesh(bookGeo, bookMat);
        const row = Math.floor(i / 4);
        const col = i % 4;
        book.position.set(
            -0.7 + col * 0.4,
            0.3 + row * 0.5,
            0.1
        );
        libraryGroup.add(book);
    }
    
    // Extintor en la pared
    const extinguisherGeo = new THREE.CylinderGeometry(0.1, 0.1, 0.5, 12);
    const extinguisherMat = new THREE.MeshStandardMaterial({ color: 0xff0000 });
    const extinguisher = new THREE.Mesh(extinguisherGeo, extinguisherMat);
    extinguisher.position.set(0.8, 1.5, 0.26);
    libraryGroup.add(extinguisher);
    
    libraryGroup.position.set(x, 0, z);
    parent.add(libraryGroup);
}

// ==================== NUEVAS ZONAS ====================

function createZoneRecepcion() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'recepcion', name: 'Recepción y Oficina' };
    const zoneWidth = 20;
    const zoneDepth = 20;
    const centerX = 40;
    const centerZ = 40;
    
    // Plataforma
    const slab = new THREE.Mesh(
        new THREE.BoxGeometry(zoneWidth, 0.18, zoneDepth),
        new THREE.MeshStandardMaterial({ color: 0x455a64, roughness: 0.92, metalness: 0.08 })
    );
    slab.position.set(centerX, 0.09, centerZ);
    zoneGroup.add(slab);
    
    // Plataforma de zona (color distintivo)
    const platformGeo = new THREE.PlaneGeometry(zoneWidth, zoneDepth);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0x3498db, transparent: true, opacity: 0.08 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(centerX, 0.19, centerZ);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(zoneWidth, 0.1, zoneDepth));
    const borderMat = new THREE.LineBasicMaterial({ color: 0x3498db });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(centerX, 0.22, centerZ);
    zoneGroup.add(border);
    
    // Mobiliario de recepción
    createReceptionCounter(centerX - 2, centerZ - 3, zoneGroup);
    createChair(centerX - 3, centerZ - 3, zoneGroup);
    
    // Área de oficina
    createDesk(centerX + 5, centerZ + 2, zoneGroup);
    createChair(centerX + 5, centerZ + 4, zoneGroup);
    createFileCabinet(centerX + 7, centerZ + 2, zoneGroup);
    createWhiteboard(centerX + 5, centerZ - 5, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(centerX - 2, 1.2, centerZ - 3),
        type: 'Cableado expuesto',
        description: 'Cables de computadora en el piso sin protección',
        probability: 3,
        severity: 2,
        zone: 'recepcion',
        mesh: createDangerHighlight(centerX - 2, 1.2, centerZ - 3, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX + 5, 0.1, centerZ + 2),
        type: 'Silla ergonómica defectuosa',
        description: 'Silla de oficina con ruedas dañadas y respaldo inestable',
        probability: 4,
        severity: 2,
        zone: 'recepcion',
        mesh: createDangerHighlight(centerX + 5, 0.1, centerZ + 2, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX + 7, 1.5, centerZ + 2),
        type: 'Archivador inestable',
        description: 'Archivador sobrecargado y sin anclaje a la pared',
        probability: 2,
        severity: 3,
        zone: 'recepcion',
        mesh: createDangerHighlight(centerX + 7, 1.5, centerZ + 2, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createZoneVestuario() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'vestuario', name: 'Vestuario y EPP' };
    const zoneWidth = 15;
    const zoneDepth = 20;
    const centerX = 0;
    const centerZ = 40;
    
    // Plataforma
    const slab = new THREE.Mesh(
        new THREE.BoxGeometry(zoneWidth, 0.18, zoneDepth),
        new THREE.MeshStandardMaterial({ color: 0x455a64, roughness: 0.92, metalness: 0.08 })
    );
    slab.position.set(centerX, 0.09, centerZ);
    zoneGroup.add(slab);
    
    // Plataforma de zona
    const platformGeo = new THREE.PlaneGeometry(zoneWidth, zoneDepth);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0x9b59b6, transparent: true, opacity: 0.08 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(centerX, 0.19, centerZ);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(zoneWidth, 0.1, zoneDepth));
    const borderMat = new THREE.LineBasicMaterial({ color: 0x9b59b6 });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(centerX, 0.22, centerZ);
    zoneGroup.add(border);
    
    // Lockers
    createLockerRow(centerX - 4, centerZ - 5, zoneGroup, 3);
    createLockerRow(centerX + 4, centerZ - 5, zoneGroup, 3);
    
    // Mesa de EPP
    createEPPTable(centerX, centerZ + 3, zoneGroup);
    
    // Ducha lavaojos
    createEyeWashStation(centerX, centerZ - 8, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(centerX - 4, 1.0, centerZ - 5),
        type: 'EPP vencido o dañado',
        description: 'Casco de seguridad con grietas y fecha de vencimiento expirada',
        probability: 3,
        severity: 4,
        zone: 'vestuario',
        mesh: createDangerHighlight(centerX - 4, 1.0, centerZ - 5, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX, 0.1, centerZ + 3),
        type: 'Almacenamiento incorrecto de EPP',
        description: 'Guantes de nitrilo almacenados sobre sustancias químicas',
        probability: 2,
        severity: 3,
        zone: 'vestuario',
        mesh: createDangerHighlight(centerX, 0.1, centerZ + 3, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX, 0.1, centerZ - 8),
        type: 'Ducha de emergencia bloqueada',
        description: 'Acceso a ducha lavaojos obstruido por cajas',
        probability: 1,
        severity: 5,
        zone: 'vestuario',
        mesh: createDangerHighlight(centerX, 0.1, centerZ - 8, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createZoneCapacitacion() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'capacitacion', name: 'Sala de Capacitación' };
    const zoneWidth = 25;
    const zoneDepth = 25;
    const centerX = 40;
    const centerZ = -40;
    
    // Plataforma
    const slab = new THREE.Mesh(
        new THREE.BoxGeometry(zoneWidth, 0.18, zoneDepth),
        new THREE.MeshStandardMaterial({ color: 0x455a64, roughness: 0.92, metalness: 0.08 })
    );
    slab.position.set(centerX, 0.09, centerZ);
    zoneGroup.add(slab);
    
    // Plataforma de zona
    const platformGeo = new THREE.PlaneGeometry(zoneWidth, zoneDepth);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0x1abc9c, transparent: true, opacity: 0.08 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(centerX, 0.19, centerZ);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(zoneWidth, 0.1, zoneDepth));
    const borderMat = new THREE.LineBasicMaterial({ color: 0x1abc9c });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(centerX, 0.22, centerZ);
    zoneGroup.add(border);
    
    // Auditorio
    createAuditoriumSeating(centerX, centerZ, zoneGroup);
    
    // Podium y pantalla
    createPodium(centerX - 8, centerZ + 5, zoneGroup);
    createProjectorScreen(centerX - 8, centerZ + 8, zoneGroup);
    
    // Biblioteca de normativas
    createNormativeLibrary(centerX + 8, centerZ + 5, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(centerX - 8, 0.1, centerZ + 5),
        type: 'Cableado en pasillo',
        description: 'Cable de proyector atraviesa pasillo sin protección',
        probability: 4,
        severity: 2,
        zone: 'capacitacion',
        mesh: createDangerHighlight(centerX - 8, 0.1, centerZ + 5, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX + 8, 2.0, centerZ + 5),
        type: 'Extintor sin mantenimiento',
        description: 'Extintor sin etiqueta de revisión anual',
        probability: 1,
        severity: 5,
        zone: 'capacitacion',
        mesh: createDangerHighlight(centerX + 8, 2.0, centerZ + 5, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX, 0.1, centerZ - 8),
        type: 'Salida de emergencia bloqueada',
        description: 'Puerta de emergencia obstruida por sillas apiladas',
        probability: 2,
        severity: 5,
        zone: 'capacitacion',
        mesh: createDangerHighlight(centerX, 0.1, centerZ - 8, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createZoneInvestigacion() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'investigacion', name: 'Investigación de Accidentes' };
    const zoneWidth = 20;
    const zoneDepth = 20;
    const centerX = 0;
    const centerZ = -80;
    
    // Plataforma
    const slab = new THREE.Mesh(
        new THREE.BoxGeometry(zoneWidth, 0.18, zoneDepth),
        new THREE.MeshStandardMaterial({ color: 0x455a64, roughness: 0.92, metalness: 0.08 })
    );
    slab.position.set(centerX, 0.09, centerZ);
    zoneGroup.add(slab);
    
    // Plataforma de zona (color distintivo)
    const platformGeo = new THREE.PlaneGeometry(zoneWidth, zoneDepth);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0xe74c3c, transparent: true, opacity: 0.08 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(centerX, 0.19, centerZ);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(zoneWidth, 0.1, zoneDepth));
    const borderMat = new THREE.LineBasicMaterial({ color: 0xe74c3c });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(centerX, 0.22, centerZ);
    zoneGroup.add(border);
    
    // Elementos de investigación
    // Cinta de delimitación
    const tapeGeo = new THREE.BoxGeometry(12, 0.05, 0.2);
    const tapeMat = new THREE.MeshBasicMaterial({ color: 0xffdd59 });
    const tape = new THREE.Mesh(tapeGeo, tapeMat);
    tape.position.set(centerX, 0.25, centerZ - 5);
    zoneGroup.add(tape);
    
    // Conos de seguridad
    const coneGeo = new THREE.ConeGeometry(0.5, 1.2, 8);
    const coneMat = new THREE.MeshBasicMaterial({ color: 0xffdd59 });
    const cone1 = new THREE.Mesh(coneGeo, coneMat);
    cone1.position.set(centerX - 4, 0.6, centerZ - 5);
    zoneGroup.add(cone1);
    const cone2 = new THREE.Mesh(coneGeo, coneMat);
    cone2.position.set(centerX + 4, 0.6, centerZ - 5);
    zoneGroup.add(cone2);
    
    // Tablero de investigación (pizarra)
    const boardGeo = new THREE.BoxGeometry(3, 2, 0.1);
    const boardMat = new THREE.MeshBasicMaterial({ color: 0x2d3436 });
    const board = new THREE.Mesh(boardGeo, boardMat);
    board.position.set(centerX + 5, 1.5, centerZ + 5);
    zoneGroup.add(board);
    
    // Marcador de posición (silueta)
    const silhouetteGeo = new THREE.PlaneGeometry(1.5, 3);
    const silhouetteMat = new THREE.MeshBasicMaterial({ color: 0xffffff, side: THREE.DoubleSide });
    const silhouette = new THREE.Mesh(silhouetteGeo, silhouetteMat);
    silhouette.position.set(centerX - 5, 0.1, centerZ + 5);
    silhouette.rotation.x = -Math.PI / 2;
    zoneGroup.add(silhouette);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(centerX, 0.25, centerZ - 5),
        type: 'Área no acordonada',
        description: 'Escena del accidente sin delimitación adecuada',
        probability: 3,
        severity: 4,
        zone: 'investigacion',
        mesh: createDangerHighlight(centerX, 0.25, centerZ - 5, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX + 5, 1.5, centerZ + 5),
        type: 'Registros incompletos',
        description: 'Falta documentación de incidentes previos',
        probability: 4,
        severity: 3,
        zone: 'investigacion',
        mesh: createDangerHighlight(centerX + 5, 1.5, centerZ + 5, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(centerX - 5, 0.1, centerZ + 5),
        type: 'Evidencia contaminada',
        description: 'Manipulación de evidencia sin protocolos',
        probability: 2,
        severity: 4,
        zone: 'investigacion',
        mesh: createDangerHighlight(centerX - 5, 0.1, centerZ + 5, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

// ==================== SISTEMA DE PELIGROS ====================
function addDanger(dangerData) {
    dangerData.type = repairBrokenText(dangerData.type);
    dangerData.description = repairBrokenText(dangerData.description);
    dangerData.identified = false;
    
    if (dangerData.mesh) {
        dangerData.mesh.userData.isDanger = true;
        dangerData.mesh.userData.dangerData = dangerData;
    }
    
    dangers.push(dangerData);
    totalDangers = dangers.length;
}

function createDangerHighlight(x, y, z, parent) {
    const highlightGroup = new THREE.Group();
    
    // Esfera brillante
    const sphereGeo = new THREE.SphereGeometry(0.5, 16, 16);
    const sphereMat = new THREE.MeshBasicMaterial({ 
        color: 0xf39c12, 
        transparent: true, 
        opacity: 0.6 
    });
    const sphere = new THREE.Mesh(sphereGeo, sphereMat);
    highlightGroup.add(sphere);
    
    // Anillo pulsante
    const ringGeo = new THREE.RingGeometry(0.6, 0.8, 32);
    const ringMat = new THREE.MeshBasicMaterial({ 
        color: 0xf39c12, 
        transparent: true, 
        opacity: 0.4,
        side: THREE.DoubleSide
    });
    const ring = new THREE.Mesh(ringGeo, ringMat);
    ring.rotation.x = Math.PI / 2;
    highlightGroup.add(ring);
    
    highlightGroup.position.set(x, y, z);
    highlightGroup.userData.isDanger = true;
    parent.add(highlightGroup);
    
    return highlightGroup;
}

// ==================== CONTROLES ====================
function setupControls() {
    const canvas = renderer.domElement;
    
    // Teclado
    window.addEventListener('keydown', (e) => {
        const key = e.key.toLowerCase();
        keys[key] = true;
        
        if (key === 'v' && gameStarted && !gamePaused && !gameEnded && !isEvaluationOpen()) {
            nextCameraMode();
        }
        if (key === 'g' && gameStarted) toggleInfoPanel();
        if (key === '1' && gameStarted) setCameraMode('follow');
        if (key === '2' && gameStarted) setCameraMode('free');
        if (key === '3' && gameStarted) setCameraMode('first');
        if ((key === '+' || key === '=') && gameStarted && !isEvaluationOpen()) adjustZoom(-3);
        if ((key === '-' || key === '_') && gameStarted && !isEvaluationOpen()) adjustZoom(3);
        if (e.key === 'Escape' && gameStarted) {
            if (isEvaluationOpen()) cancelEvaluation();
            else togglePause();
        }
    });
    
    window.addEventListener('keyup', (e) => {
        keys[e.key.toLowerCase()] = false;
    });
    
    // Mouse
    canvas.addEventListener('mousedown', (e) => {
        if (e.button === 0 && gameStarted && !gamePaused && !gameEnded && !isEvaluationOpen()) {
            handleClick(e);
        }
        if (e.button === 0 && !isWorldInputBlocked()) {
            isDragging = true;
            previousMouse = { x: e.clientX, y: e.clientY };
        }
    });
    
    canvas.addEventListener('mousemove', (e) => {
        if (isDragging && cameraMode === 'free' && !isWorldInputBlocked()) {
            cameraTheta -= (e.clientX - previousMouse.x) * 0.005;
            cameraPhi += (e.clientY - previousMouse.y) * 0.005;
            cameraPhi = Math.max(0.1, Math.min(Math.PI / 2 - 0.1, cameraPhi));
            previousMouse = { x: e.clientX, y: e.clientY };
        }
    });
    
    window.addEventListener('mouseup', () => {
        isDragging = false;
    });
    
    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        if (gameStarted && !gamePaused && !gameEnded && !isEvaluationOpen()) {
            adjustZoom(e.deltaY * 0.02);
        }
    }, { passive: false });
    
    canvas.addEventListener('contextmenu', (e) => e.preventDefault());
}

function handleClick(event) {
    playSoundClick();
    mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
    mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
    
    raycaster.setFromCamera(mouse, camera);
    
    const interactableDangers = dangers
        .filter((danger) => danger.mesh && !danger.identified)
        .map((danger) => danger.mesh);
    const intersects = raycaster.intersectObjects(interactableDangers, true);
    
    if (!intersects.length) return;
    
    let object = intersects[0].object;
    while (object && !object.userData?.dangerData) {
        object = object.parent;
    }
    
    if (object?.userData?.dangerData) {
        dismissOnboarding();
        playSoundSelect();
        selectDanger(object.userData.dangerData);
    }
}

function selectDanger(danger) {
    if (!danger || danger.identified) return;
    
    currentDanger = danger;
    showEvaluationPanel(danger);
}

function showEvaluationPanel(danger) {
    const panel = document.getElementById('evaluationPanel');
    const dangerName = document.getElementById('dangerName');
    const dangerBrief = document.getElementById('dangerBrief');
    const probabilityHint = document.getElementById('probabilityHint');
    const severityHint = document.getElementById('severityHint');
    const observation = document.getElementById('dangerObservation');
    const control = document.getElementById('dangerControl');
    const guidance = getDangerGuidance(danger);
    
    dangerName.textContent = repairBrokenText(danger.type);
    dangerBrief.textContent = guidance.focus;
    observation.textContent = repairBrokenText(danger.description);
    control.textContent = guidance.control;
    probabilityHint.textContent = getProbabilityHint();
    severityHint.textContent = getSeverityHint();
    panel.classList.add('visible');
    
    // Resetear selections
    selectedProbability = 0;
    selectedSeverity = 0;
    document.querySelectorAll('.prob-btn').forEach(btn => btn.classList.remove('selected'));
    document.querySelectorAll('.sev-btn').forEach(btn => btn.classList.remove('selected'));
    document.getElementById('riskLevel').textContent = '-';
    document.getElementById('riskLevel').className = 'result-value';
    updateAnalysisPanel(danger, { message: repairBrokenText(danger.description) });
}

function selectProbability(value) {
    selectedProbability = value;
    document.querySelectorAll('.prob-btn').forEach(btn => {
        btn.classList.toggle('selected', parseInt(btn.dataset.value) === value);
    });
    document.getElementById('probabilityHint').textContent = getProbabilityHint(value);
    updateRiskLevel();
}

function selectSeverity(value) {
    selectedSeverity = value;
    document.querySelectorAll('.sev-btn').forEach(btn => {
        btn.classList.toggle('selected', parseInt(btn.dataset.value) === value);
    });
    document.getElementById('severityHint').textContent = getSeverityHint(value);
    updateRiskLevel();
}

function updateRiskLevel() {
    if (selectedProbability === 0 || selectedSeverity === 0) return;
    
    const risk = getRiskAssessment(selectedProbability, selectedSeverity);
    const levelEl = document.getElementById('riskLevel');
    levelEl.textContent = risk.label;
    levelEl.className = 'result-value ' + risk.className;
}

function cancelEvaluation() {
    currentDanger = null;
    document.getElementById('evaluationPanel').classList.remove('visible');
}

function submitEvaluation() {
    if (!currentDanger || selectedProbability === 0 || selectedSeverity === 0) {
        showNotification('Completa todos los campos', 'warning');
        playSoundWarning();
        return;
    }
    
    const userRisk = getRiskAssessment(selectedProbability, selectedSeverity);
    const correctRisk = getRiskAssessment(currentDanger.probability, currentDanger.severity);
    const probabilityDiff = Math.abs(selectedProbability - currentDanger.probability);
    const severityDiff = Math.abs(selectedSeverity - currentDanger.severity);
    const exactMatch = probabilityDiff === 0 && severityDiff === 0;
    const sameBand = userRisk.label === correctRisk.label;
    const partiallyAligned = probabilityDiff <= 1 || severityDiff <= 1;
    const correctReference = `P${currentDanger.probability} / S${currentDanger.severity} / ${correctRisk.label}`;
    const userReference = `P${selectedProbability} / S${selectedSeverity} / ${userRisk.label}`;
    let points = 0;
    let feedbackMessage = '';
    let notificationType = 'info';
    let identifiedThisRound = false;
    
    if (exactMatch) {
        points = 100;
        exactEvaluations++;
        identifiedThisRound = true;
        feedbackMessage = 'Valoracion exacta. Identificaste correctamente probabilidad, severidad y nivel de riesgo.';
        notificationType = 'success';
        playSoundSuccess();
    } else if (sameBand && probabilityDiff <= 1 && severityDiff <= 1) {
        points = 70;
        identifiedThisRound = true;
        feedbackMessage = `Buen analisis. La banda de riesgo coincide, pero la referencia esperada era ${correctReference}.`;
        notificationType = 'success';
        playSoundSuccess();
    } else if (sameBand || partiallyAligned) {
        points = 40;
        identifiedThisRound = true;
        feedbackMessage = `Vas bien, pero ajusta la valoracion. La referencia correcta era ${correctReference}.`;
        notificationType = 'warning';
        playSoundWarning();
    } else {
        points = 0;
        feedbackMessage = `La evaluacion no coincide. Toma como referencia ${correctReference}.`;
        notificationType = 'error';
        playSoundError();
    }
    
    if (identifiedThisRound && !currentDanger.identified) {
        currentDanger.identified = true;
        dangersIdentified++;
        if (currentDanger.mesh) currentDanger.mesh.visible = false;
        updateProgress();
        checkAchievements();
    }
    
    score += points;
    document.getElementById('scoreValue').textContent = score;
    document.getElementById('infoPanel').classList.add('visible');
    showNotification(feedbackMessage, notificationType);
    updateAnalysisPanel(currentDanger, {
        message: feedbackMessage,
        userText: `${userReference} | ${points} pts`
    });
    
    document.getElementById('evaluationPanel').classList.remove('visible');
    currentDanger = null;
    
    // Verificar si completÃ³ todos los peligros
    if (dangersIdentified >= totalDangers) {
        endGame();
    }
}

function updateProgress() {
    const progress = (dangersIdentified / totalDangers) * 100;
    document.getElementById('dangerProgress').style.width = progress + '%';
    document.getElementById('dangerCount').textContent = dangersIdentified + '/' + totalDangers;
    updateZoneBriefing();
}

function checkAchievements() {
    // LÃ³gica de logros
    if (dangersIdentified >= 3 && !achievements.includes('explorer')) {
        unlockAchievement('explorer', 'Explorador', 'Identifica 3 peligros');
    }
    if (score >= 100 && !achievements.includes('scorer')) {
        unlockAchievement('scorer', 'Puntuador', 'Alcanza 100 puntos');
    }
}

function unlockAchievement(id, name, desc) {
    achievements.push(id);
    playSoundAchievement();
    const popup = document.getElementById('achievementPopup');
    document.getElementById('achievementName').textContent = `${name} - ${desc}`;
    popup.classList.add('visible');
    
    setTimeout(() => {
        popup.classList.remove('visible');
    }, 3000);
}

function showNotification(message, type = 'info') {
    const container = document.getElementById('notifications');
    const notification = document.createElement('div');
    notification.className = 'notification ' + type;
    notification.textContent = repairBrokenText(message);
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// ==================== CÃMARA ====================
function nextCameraMode() {
    const idx = cameraModes.indexOf(cameraMode);
    setCameraMode(cameraModes[(idx + 1) % cameraModes.length]);
}

function setCameraMode(mode) {
    cameraMode = mode;
    camera.fov = 75; // Mismo FOV para todos los modos
    camera.updateProjectionMatrix();
    
    const indicator = document.getElementById('cameraIndicator');
    const modeText = { follow: 'Seguidor', free: 'Libre', first: '1ra Persona' };
    indicator.querySelector('.camera-mode').textContent = 'Vista: ' + modeText[mode];
}

function adjustZoom(delta) {
    cameraDistance += delta;
    cameraDistance = Math.max(10, Math.min(80, cameraDistance));
}

// ==================== SISTEMA DE VESTIMENTA EPP ====================
function showEPPStage() {
    initAudio();
    playSoundSelect();
    document.getElementById('startScreen').classList.add('hidden');
    document.getElementById('eppScreen').classList.add('visible');
    inEPPStage = true;
    
    // Iniciar escena de la oficina
    initOfficeScene();
}

function initOfficeScene() {
    // Crear escena de la oficina
    officeScene = new THREE.Scene();
    officeScene.background = new THREE.Color(0x1f2937);
    
    // CÃ¡mara
    officeCamera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    officeCamera.position.set(0.6, 2.8, 8.6);
    officeCamera.lookAt(0.4, 1.5, -0.4);
    
    // Renderer
    officeRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    officeRenderer.setSize(window.innerWidth, window.innerHeight);
    officeRenderer.setClearColor(0x000000, 0);
    
    // Crear canvas overlay para la oficina
    const canvas = document.createElement('canvas');
    canvas.id = 'officeCanvas';
    canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:855;';
    document.body.appendChild(canvas);
    
    officeRenderer = new THREE.WebGLRenderer({ canvas, antialias: true });
    officeRenderer.setSize(window.innerWidth, window.innerHeight);
    officeRenderer.setClearColor(0x1f2937, 1);
    
    // Luces de oficina
    const ambient = new THREE.AmbientLight(0xffffff, 0.72);
    officeScene.add(ambient);
    
    const spotLight = new THREE.SpotLight(0xffffff, 1.05);
    spotLight.position.set(0, 6, 1.5);
    spotLight.castShadow = true;
    officeScene.add(spotLight);
    
    const fillLight = new THREE.PointLight(0x9ad1ff, 0.35, 18);
    fillLight.position.set(-3, 3, 3);
    officeScene.add(fillLight);
    
    // Piso de oficina
    const floorGeo = new THREE.PlaneGeometry(14, 10);
    const floorMat = new THREE.MeshStandardMaterial({ color: 0x52606d, roughness: 0.92 });
    const floor = new THREE.Mesh(floorGeo, floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = 0;
    officeScene.add(floor);
    
    // Paredes
    const wallMat = new THREE.MeshStandardMaterial({ color: 0xe5e7eb, side: THREE.DoubleSide });
    const stripeMat = new THREE.MeshStandardMaterial({ color: 0x059669 });
    
    // Pared atrÃ¡s
    const backWall = new THREE.Mesh(new THREE.PlaneGeometry(14, 4.5), wallMat);
    backWall.position.set(0, 2.25, -4.8);
    officeScene.add(backWall);
    
    // Paredes laterales
    const leftWall = new THREE.Mesh(new THREE.PlaneGeometry(10, 4.5), wallMat);
    leftWall.position.set(-7, 2.25, 0);
    leftWall.rotation.y = Math.PI / 2;
    officeScene.add(leftWall);
    
    const rightWall = new THREE.Mesh(new THREE.PlaneGeometry(10, 4.5), wallMat);
    rightWall.position.set(7, 2.25, 0);
    rightWall.rotation.y = -Math.PI / 2;
    officeScene.add(rightWall);
    
    const frontLintel = new THREE.Mesh(new THREE.BoxGeometry(14, 0.3, 0.2), stripeMat);
    frontLintel.position.set(0, 4.35, 4.9);
    officeScene.add(frontLintel);
    
    const ceiling = new THREE.Mesh(
        new THREE.PlaneGeometry(14, 10),
        new THREE.MeshStandardMaterial({ color: 0xd6dde3, side: THREE.DoubleSide })
    );
    ceiling.rotation.x = Math.PI / 2;
    ceiling.position.y = 4.45;
    officeScene.add(ceiling);
    
    const prepBand = new THREE.Mesh(new THREE.BoxGeometry(14, 0.25, 0.08), stripeMat);
    prepBand.position.set(0, 2.6, -4.7);
    officeScene.add(prepBand);
    
    // Crear trabajador (sin EPP)
    createOfficeWorker();
    
    // Crear locker con EPP
    createEPPStation();
    
    // Crear puerta
    createDoor();
    
    // Animar escena de oficina
    animateOfficeScene();
}

function createOfficeWorker() {
    officeWorker = new THREE.Group();
    
    // Colores INTEP
    const bodyMat = new THREE.MeshStandardMaterial({ color: 0x059669 }); // Verde INTEP
    const pantsMat = new THREE.MeshStandardMaterial({ color: 0x1a1a1a }); // Negro
    const skinMat = new THREE.MeshStandardMaterial({ color: 0xf5cba7 });
    
    // Piernas con articulaciones
    const thighGeo = new THREE.CylinderGeometry(0.1, 0.08, 0.4, 12);
    const shinGeo = new THREE.CylinderGeometry(0.07, 0.05, 0.4, 12);
    
    // Pierna izquierda
    officeWorker.leftThigh = new THREE.Group();
    const leftThigh = new THREE.Mesh(thighGeo, pantsMat);
    leftThigh.position.y = -0.2;
    officeWorker.leftThigh.add(leftThigh);
    
    officeWorker.leftShin = new THREE.Group();
    const leftShin = new THREE.Mesh(shinGeo, pantsMat);
    leftShin.position.y = -0.2;
    officeWorker.leftShin.add(leftShin);
    officeWorker.leftShin.position.y = -0.4;
    officeWorker.leftThigh.add(officeWorker.leftShin);
    
    const leftFoot = new THREE.Mesh(
        new THREE.BoxGeometry(0.12, 0.05, 0.22),
        skinMat
    );
    leftFoot.position.set(0, -0.45, 0.03);
    officeWorker.leftShin.add(leftFoot);
    
    officeWorker.leftThigh.position.set(-0.1, 0.9, 0);
    officeWorker.add(officeWorker.leftThigh);
    
    // Pierna derecha
    officeWorker.rightThigh = new THREE.Group();
    const rightThigh = new THREE.Mesh(thighGeo.clone(), pantsMat);
    rightThigh.position.y = -0.2;
    officeWorker.rightThigh.add(rightThigh);
    
    officeWorker.rightShin = new THREE.Group();
    const rightShin = new THREE.Mesh(shinGeo.clone(), pantsMat);
    rightShin.position.y = -0.2;
    officeWorker.rightShin.add(rightShin);
    officeWorker.rightShin.position.y = -0.4;
    officeWorker.rightThigh.add(officeWorker.rightShin);
    
    const rightFoot = new THREE.Mesh(
        new THREE.BoxGeometry(0.12, 0.05, 0.22),
        skinMat
    );
    rightFoot.position.set(0, -0.45, 0.03);
    officeWorker.rightShin.add(rightFoot);
    
    officeWorker.rightThigh.position.set(0.1, 0.9, 0);
    officeWorker.add(officeWorker.rightThigh);
    
    // Torso
    officeWorker.torso = new THREE.Group();
    const torso = new THREE.Mesh(
        new THREE.BoxGeometry(0.4, 0.55, 0.25),
        bodyMat
    );
    officeWorker.torso.add(torso);
    
    // Espalda
    const back = new THREE.Mesh(
        new THREE.BoxGeometry(0.35, 0.5, 0.1),
        new THREE.MeshStandardMaterial({ color: 0x1f4f7a })
    );
    back.position.z = -0.12;
    officeWorker.torso.add(back);
    
    officeWorker.torso.position.y = 1.25;
    officeWorker.add(officeWorker.torso);
    
    // Brazos con articulaciones
    const upperArmGeo = new THREE.CylinderGeometry(0.06, 0.07, 0.25, 12);
    const forearmGeo = new THREE.CylinderGeometry(0.04, 0.06, 0.22, 12);
    
    // Brazo izquierdo
    officeWorker.leftArm = new THREE.Group();
    const leftUpperArm = new THREE.Mesh(upperArmGeo, skinMat);
    leftUpperArm.position.y = -0.125;
    officeWorker.leftArm.add(leftUpperArm);
    
    officeWorker.leftForearm = new THREE.Group();
    const leftForearm = new THREE.Mesh(forearmGeo, skinMat);
    leftForearm.position.y = -0.11;
    officeWorker.leftForearm.add(leftForearm);
    officeWorker.leftForearm.position.y = -0.25;
    officeWorker.leftArm.add(officeWorker.leftForearm);
    
    const leftHand = new THREE.Mesh(
        new THREE.BoxGeometry(0.07, 0.08, 0.04),
        skinMat
    );
    leftHand.position.y = -0.15;
    officeWorker.leftForearm.add(leftHand);
    
    officeWorker.leftArm.position.set(-0.25, 0.18, 0);
    officeWorker.torso.add(officeWorker.leftArm);
    
    // Brazo derecho
    officeWorker.rightArm = new THREE.Group();
    const rightUpperArm = new THREE.Mesh(upperArmGeo.clone(), skinMat);
    rightUpperArm.position.y = -0.125;
    officeWorker.rightArm.add(rightUpperArm);
    
    officeWorker.rightForearm = new THREE.Group();
    const rightForearm = new THREE.Mesh(forearmGeo.clone(), skinMat);
    rightForearm.position.y = -0.11;
    officeWorker.rightForearm.add(rightForearm);
    officeWorker.rightForearm.position.y = -0.25;
    officeWorker.rightArm.add(officeWorker.rightForearm);
    
    const rightHand = new THREE.Mesh(
        new THREE.BoxGeometry(0.07, 0.08, 0.04),
        skinMat
    );
    rightHand.position.y = -0.15;
    officeWorker.rightForearm.add(rightHand);
    
    officeWorker.rightArm.position.set(0.25, 0.18, 0);
    officeWorker.torso.add(officeWorker.rightArm);
    
    // Cabeza
    officeWorker.head = new THREE.Group();
    const head = new THREE.Mesh(
        new THREE.SphereGeometry(0.14, 20, 20),
        skinMat
    );
    head.scale.set(1, 1.1, 0.95);
    officeWorker.head.add(head);
    
    // Cejas
    const browMat = new THREE.MeshStandardMaterial({ color: 0x5d4e37 });
    const browGeo = new THREE.BoxGeometry(0.1, 0.015, 0.02);
    [-0.04, 0.04].forEach(x => {
        const brow = new THREE.Mesh(browGeo, browMat);
        brow.position.set(x, 0.04, 0.12);
        officeWorker.head.add(brow);
    });
    
    // Ojos
    const eyeGeo = new THREE.SphereGeometry(0.02, 12, 12);
    const eyeMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
    const pupilGeo = new THREE.SphereGeometry(0.01, 8, 8);
    const pupilMat = new THREE.MeshStandardMaterial({ color: 0x1a1a1a });
    
    [-0.045, 0.045].forEach(x => {
        const eye = new THREE.Mesh(eyeGeo, eyeMat);
        eye.position.set(x, 0.015, 0.11);
        officeWorker.head.add(eye);
        
        const pupil = new THREE.Mesh(pupilGeo, pupilMat);
        pupil.position.set(x, 0.015, 0.13);
        officeWorker.head.add(pupil);
    });
    
    // Nariz
    const nose = new THREE.Mesh(
        new THREE.ConeGeometry(0.015, 0.03, 8),
        skinMat
    );
    nose.position.set(0, -0.02, 0.13);
    nose.rotation.x = Math.PI / 2;
    officeWorker.head.add(nose);
    
    // Boca
    const mouth = new THREE.Mesh(
        new THREE.BoxGeometry(0.05, 0.01, 0.015),
        new THREE.MeshStandardMaterial({ color: 0x8b4513 })
    );
    mouth.position.set(0, -0.06, 0.12);
    officeWorker.head.add(mouth);
    
    // Orejas
    const earGeo = new THREE.SphereGeometry(0.025, 8, 8);
    [-0.14, 0.14].forEach(x => {
        const ear = new THREE.Mesh(earGeo, skinMat);
        ear.scale.set(0.5, 1, 0.7);
        ear.position.set(x, 0, 0);
        officeWorker.head.add(ear);
    });
    
    officeWorker.head.position.y = 1.7;
    officeWorker.add(officeWorker.head);
    
    // AnimaciÃ³n idle
    animateOfficeWorker();
    
    officeWorker.position.set(-2.4, 0, 1.1);
    officeWorker.lookAt(1.8, 1.2, -0.2);
    officeScene.add(officeWorker);
}

function animateOfficeWorker() {
    if (!officeWorker) return;
    
    // RespiraciÃ³n
    const breathe = Math.sin(Date.now() * 0.002) * 0.005;
    if (officeWorker.torso) {
        officeWorker.torso.scale.y = 1 + breathe;
    }
    
    // Movimiento sutil de cabeza
    if (officeWorker.head) {
        officeWorker.head.rotation.y = Math.sin(Date.now() * 0.001) * 0.1;
        officeWorker.head.rotation.x = Math.sin(Date.now() * 0.0015) * 0.03;
    }
    
    // Brazos relajados
    if (officeWorker.leftArm) {
        officeWorker.leftArm.rotation.x = -0.1 + Math.sin(Date.now() * 0.002) * 0.02;
    }
    if (officeWorker.rightArm) {
        officeWorker.rightArm.rotation.x = -0.1 + Math.sin(Date.now() * 0.002 + 0.5) * 0.02;
    }
}

function createEPPStation() {
    eppItems = [];
    
    // Lockers
    const lockerGroup = new THREE.Group();
    const lockerMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    
    for (let i = 0; i < 6; i++) {
        const locker = new THREE.Mesh(
            new THREE.BoxGeometry(0.72, 1.85, 0.65),
            lockerMat
        );
        locker.position.set(-4.5 + i * 0.9, 0.92, -4.3);
        lockerGroup.add(locker);
    }
    officeScene.add(lockerGroup);
    
    // Mesa de preparacion
    const tableGroup = new THREE.Group();
    const tableMat = new THREE.MeshStandardMaterial({ color: 0x8b5a2b, roughness: 0.9 });
    const legMat = new THREE.MeshStandardMaterial({ color: 0x4b5563, metalness: 0.25 });
    
    const tableTop = new THREE.Mesh(
        new THREE.BoxGeometry(4.6, 0.16, 1.6),
        tableMat
    );
    tableTop.position.y = 0.98;
    tableGroup.add(tableTop);
    
    const tableLeg = new THREE.BoxGeometry(0.12, 0.95, 0.12);
    [[-2.05, -0.6], [2.05, -0.6], [-2.05, 0.6], [2.05, 0.6]].forEach(([x, z]) => {
        const leg = new THREE.Mesh(tableLeg, legMat);
        leg.position.set(x, 0.47, z);
        tableGroup.add(leg);
    });
    
    const lowerShelf = new THREE.Mesh(
        new THREE.BoxGeometry(4.1, 0.08, 1.1),
        new THREE.MeshStandardMaterial({ color: 0x6b7280, metalness: 0.18 })
    );
    lowerShelf.position.y = 0.42;
    tableGroup.add(lowerShelf);
    
    tableGroup.position.set(2.1, 0, -0.25);
    officeScene.add(tableGroup);
    
    // EPP sobre la mesa (items cliqueables)
    const eppPositions = [
        { type: 'casco', pos: [0.35, 1.16, -0.7], icon: 'â›‘ï¸', color: 0xf1c40f },
        { type: 'gafas', pos: [1.35, 1.16, -0.7], icon: 'ðŸ¥½', color: 0x3498db },
        { type: 'tapabocas', pos: [2.25, 1.16, -0.72], icon: 'ðŸ˜·', color: 0xffffff },
        { type: 'chaleco', pos: [3.45, 1.14, -0.66], icon: 'ðŸ¦º', color: 0xf39c12 },
        { type: 'guantes', pos: [1.15, 1.16, 0.18], icon: 'ðŸ§¤', color: 0x8b4513 },
        { type: 'botas', pos: [2.85, 1.16, 0.2], icon: 'ðŸ‘¢', color: 0x1a1a1a }
    ];
    
    eppPositions.forEach(item => {
        const eppMesh = createEPPMesh(item);
        eppMesh.userData.eppType = item.type;
        eppMesh.userData.isEPP = true;
        officeScene.add(eppMesh);
        eppItems.push(eppMesh);
    });
    
    // Cartel de instrucciones
    const signGroup = new THREE.Group();
    const signMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const sign = new THREE.Mesh(new THREE.BoxGeometry(2.8, 0.7, 0.05), signMat);
    sign.position.y = 3;
    signGroup.add(sign);
    
    const postMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const post = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 2, 8), postMat);
    post.position.y = 1.5;
    signGroup.add(post);
    
    signGroup.position.set(0, 0, -4.55);
    officeScene.add(signGroup);
    
    const cabinet = new THREE.Mesh(
        new THREE.BoxGeometry(1.8, 1.1, 0.55),
        new THREE.MeshStandardMaterial({ color: 0x94a3b8 })
    );
    cabinet.position.set(-5.5, 0.55, 2.8);
    officeScene.add(cabinet);
}

function createEPPMesh(item) {
    const group = new THREE.Group();
    
    const mat = new THREE.MeshStandardMaterial({ color: item.color, metalness: 0.3 });
    
    switch(item.type) {
        case 'casco':
            const helmetGeo = new THREE.SphereGeometry(0.2, 16, 8, 0, Math.PI * 2, 0, Math.PI / 2);
            const helmet = new THREE.Mesh(helmetGeo, mat);
            helmet.position.y = 0.15;
            group.add(helmet);
            
            const brimGeo = new THREE.CylinderGeometry(0.25, 0.25, 0.03, 16);
            const brim = new THREE.Mesh(brimGeo, mat);
            group.add(brim);
            break;
            
        case 'gafas':
            const gogGeo = new THREE.BoxGeometry(0.25, 0.08, 0.08);
            const gog = new THREE.Mesh(gogGeo, mat);
            group.add(gog);
            break;
            
        case 'tapabocas':
            const maskGeo = new THREE.BoxGeometry(0.15, 0.08, 0.05);
            const mask = new THREE.Mesh(maskGeo, mat);
            group.add(mask);
            break;
            
        case 'chaleco':
            const vestGeo = new THREE.BoxGeometry(0.3, 0.35, 0.1);
            const vest = new THREE.Mesh(vestGeo, mat);
            group.add(vest);
            
            const stripeGeo = new THREE.BoxGeometry(0.31, 0.04, 0.11);
            const stripeMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
            const stripe = new THREE.Mesh(stripeGeo, stripeMat);
            stripe.position.y = 0.1;
            group.add(stripe);
            break;
            
        case 'guantes':
            const gloveGeo = new THREE.BoxGeometry(0.1, 0.15, 0.08);
            const glove = new THREE.Mesh(gloveGeo, mat);
            group.add(glove);
            break;
            
        case 'botas':
            const bootGeo = new THREE.BoxGeometry(0.12, 0.2, 0.25);
            const boot = new THREE.Mesh(bootGeo, mat);
            group.add(boot);
            break;
    }
    
    // Esfera de highlight
    const highlightGeo = new THREE.SphereGeometry(0.35, 16, 16);
    const highlightMat = new THREE.MeshBasicMaterial({ 
        color: 0xf1c40f, 
        transparent: true, 
        opacity: 0.2,
        wireframe: true
    });
    const highlight = new THREE.Mesh(highlightGeo, highlightMat);
    group.add(highlight);
    
    group.position.set(...item.pos);
    group.userData.eppType = item.type;
    group.userData.isEPP = true;
    group.userData.baseY = item.pos[1];
    
    return group;
}

function createDoor() {
    const doorGroup = new THREE.Group();
    
    // Marco
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const frameGeo = new THREE.BoxGeometry(1.2, 2.2, 0.1);
    const frame = new THREE.Mesh(frameGeo, frameMat);
    frame.position.y = 1.1;
    doorGroup.add(frame);
    
    // Puerta (cerrada al inicio)
    const doorMat = new THREE.MeshStandardMaterial({ color: 0x5d4e37 });
    const door = new THREE.Mesh(new THREE.BoxGeometry(1, 2, 0.05), doorMat);
    door.position.set(0.5, 1, 0.03);
    doorGroup.add(door);
    doorGroup.userData.door = door;
    
    // SeÃ±al de salida
    const signMat = new THREE.MeshBasicMaterial({ color: 0x27ae60 });
    const sign = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.4, 0.05), signMat);
    sign.position.set(-0.5, 1.8, 0.08);
    doorGroup.add(sign);
    
    doorGroup.position.set(0, 0, 2.5);
    doorGroup.userData.isDoor = true;
    officeScene.add(doorGroup);
    officeWorker.userData.door = doorGroup;
}

function animateOfficeScene() {
    if (!inEPPStage) return;
    
    requestAnimationFrame(animateOfficeScene);
    
    // Rotar items de EPP
    eppItems.forEach((item, i) => {
        if (!eppEquipped[item.userData.eppType]) {
            item.rotation.y += 0.02;
            item.position.y = item.userData.baseY + Math.sin(Date.now() * 0.003 + i) * 0.05;
        }
    });
    
    // Animar trabajador de oficina
    animateOfficeWorker();
    
    // Hacer que el trabajador mire hacia los EPP
    if (officeWorker && officeWorker.head) {
        officeWorker.head.rotation.y = Math.sin(Date.now() * 0.001) * 0.2;
    }
    
    // Renderizar escena de oficina
    if (officeRenderer) {
        officeRenderer.render(officeScene, officeCamera);
    }
}

function equipEPP(type) {
    if (eppEquipped[type]) return;
    
    playSoundSuccess();
    eppEquipped[type] = true;
    
    // Marcar en la lista
    const item = document.querySelector(`[data-epp="${type}"]`);
    item.classList.add('equipped');
    document.getElementById(`status-${type}`).textContent = 'âœ“';
    
    // Actualizar preview
    const preview = document.getElementById(`preview-${type}`);
    preview.classList.add('equipped');
    
    // Ocultar EPP en la escena 3D
    const eppMesh = eppItems.find(epp => epp.userData.eppType === type);
    if (eppMesh) {
        eppMesh.visible = false;
    }
    
    // Verificar si todos estÃ¡n equipados
    updateEPPProgress();
}

function updateEPPProgress() {
    const equippedCount = Object.values(eppEquipped).filter(v => v).length;
    const total = 6;
    const progress = (equippedCount / total) * 100;
    
    document.getElementById('eppProgressFill').style.width = progress + '%';
    document.getElementById('eppProgressText').textContent = `${equippedCount}/${total} elementos`;
    
    const enterBtn = document.getElementById('btnEnterArea');
    const exitBtn = document.querySelector('.btn-exit-office');
    
    if (equippedCount === total) {
        allEPPEquipped = true;
        enterBtn.disabled = false;
        enterBtn.textContent = 'âœ“ Entrar al Ãrea Industrial';
        
        // Actualizar preview del cuerpo
        document.querySelector('.preview-body').classList.add('ready');
        
        showNotification('Â¡EPP completo! Ya puedes entrar al Ã¡rea industrial', 'success');
    }
}

function enterIndustrialArea() {
    if (!allEPPEquipped) return;
    
    playSoundSelect();
    
    // Ocultar pantalla EPP
    document.getElementById('eppScreen').classList.remove('visible');
    
    // Ocultar canvas de oficina
    if (document.getElementById('officeCanvas')) {
        document.getElementById('officeCanvas').style.display = 'none';
    }
    
    // Mostrar simulador
    document.getElementById('simulatorContainer').classList.add('active');
    
    inEPPStage = false;
    gameStarted = true;
    
    // Actualizar EPP del trabajador principal
    updateWorkerEPP();
    
    startAmbientMusic();
    updateProgress();
    document.getElementById('infoPanel').classList.remove('visible');
    showOnboarding();
    updateAnalysisPanel();
    selectZone(currentZone);
    
    showNotification('Explora la zona y usa la guia cuando necesites justificar el riesgo.', 'info');
}

function updateWorkerEPP() {
    if (!worker) return;
    
    // El trabajador ya tiene EPP en el modelo, asÃ­ que solo ajustamos la visibilidad
    // segÃºn lo que se haya equipado
    // En este caso, como todos deben estar equipados para entrar, todos serÃ¡n visibles
}

// ==================== CONTROLES DE INTERFAZ ====================
function startSimulation() {
    showEPPStage();
}

function toggleMenu() {
    togglePause();
}

function togglePause() {
    playSoundClick();
    gamePaused = !gamePaused;
    if (gamePaused) stopAmbientMusic();
    else if (!musicMuted) startAmbientMusic();
    document.getElementById('pauseMenu').classList.toggle('visible', gamePaused);
}

function resumeSimulation() {
    gamePaused = false;
    document.getElementById('pauseMenu').classList.remove('visible');
    if (!musicMuted) startAmbientMusic();
}

function restartSimulation() {
    location.reload();
}

function quitSimulation() {
    location.reload();
}

function showTutorial() {
    showOnboarding();
    document.getElementById('infoPanel').classList.add('visible');
    showNotification('Recuerda: observa el contexto, luego valora probabilidad y severidad.', 'info');
}

function closeInfoPanel() {
    document.getElementById('infoPanel').classList.remove('visible');
}

function selectZone(zone) {
    playSoundClick();
    currentZone = zone;
    
    // Actualizar botÃ³n activo
    document.querySelectorAll('.zone-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.zone === zone);
    });
    
    // Mover cÃ¡mara a la zona
    const zonePositions = {
        recepcion: new THREE.Vector3(40, 15, 40),
        vestuario: new THREE.Vector3(0, 15, 40),
        almacen: new THREE.Vector3(-28, 15, -28),
        maquinas: new THREE.Vector3(25, 15, -25),
        alturas: new THREE.Vector3(0, 20, -50),
        confinados: new THREE.Vector3(-50, 10, 0),
        electrico: new THREE.Vector3(50, 15, 25),
        quimico: new THREE.Vector3(-50, 15, 25),
        capacitacion: new THREE.Vector3(40, 15, -40),
        investigacion: new THREE.Vector3(0, 15, -80)
    };
    
    cameraTarget.copy(zonePositions[zone]);
    updateZoneBriefing();
}

function endGame() {
    gameEnded = true;
    
    document.getElementById('finalScore').textContent = score;
    document.getElementById('statIdentified').textContent = dangersIdentified;
    document.getElementById('statCorrect').textContent = exactEvaluations;
    document.getElementById('statAccuracy').textContent = Math.round((dangersIdentified / totalDangers) * 100) + '%';
    
    // Mostrar logros
    const achievementsList = document.getElementById('achievementsList');
    achievementsList.innerHTML = '';
    achievements.forEach(id => {
        const badge = document.createElement('span');
        badge.className = 'achievement-badge earned';
        badge.textContent = achievementCatalog[id]?.name || id;
        achievementsList.appendChild(badge);
    });
    
    document.getElementById('resultsScreen').classList.add('visible');
}

function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
    
    if (officeCamera && officeRenderer) {
        officeCamera.aspect = window.innerWidth / window.innerHeight;
        officeCamera.updateProjectionMatrix();
        officeRenderer.setSize(window.innerWidth, window.innerHeight);
    }
}

// ==================== ANIMACIÃ“N ====================
function animate() {
    requestAnimationFrame(animate);
    const delta = Math.min(clock?.getDelta?.() || (1 / 60), 0.05);
    
    if (!gamePaused && gameStarted && !gameEnded) {
        updateWorkerMovement(delta);
        if (workerMotion.normalizedSpeed > 0.03) dismissOnboarding();
        
        // Animar trabajador
        animateWorker(delta);
        
        // Actualizar cÃ¡mara
        updateCamera(delta);
        
        // Animar peligros
        dangers.forEach((danger, i) => {
            if (danger.mesh && !danger.identified) {
                danger.mesh.position.y = danger.position.y + Math.sin(Date.now() * 0.003 + i) * 0.2;
                danger.mesh.rotation.y += 0.02;
            }
        });
    }
    
    renderer.render(scene, camera);
}

function updateCamera() {
    if (!worker) return;
    
    switch (cameraMode) {
        case 'follow':
            const followDir = workerMotion.velocity.lengthSq() > 0.01
                ? workerMotion.velocity.clone().normalize()
                : new THREE.Vector3(Math.sin(worker.rotation.y), 0, Math.cos(worker.rotation.y));
            const followDistance = cameraDistance * 0.78;
            const followHeight = cameraDistance * 0.42 + 2;
            const desiredFollowPos = worker.position.clone().add(new THREE.Vector3(
                -followDir.x * followDistance,
                followHeight,
                -followDir.z * followDistance
            ));
            const lookAhead = worker.position.clone().add(followDir.multiplyScalar(2.5 + workerMotion.normalizedSpeed * 4));
            camera.position.lerp(desiredFollowPos, 0.12);
            camera.lookAt(lookAhead.x, 1.45, lookAhead.z);
            break;
            
        case 'free':
            const x = cameraTarget.x + Math.sin(cameraTheta) * Math.cos(cameraPhi) * cameraDistance;
            const y = cameraTarget.y + Math.sin(cameraPhi) * cameraDistance;
            const z = cameraTarget.z + Math.cos(cameraTheta) * Math.cos(cameraPhi) * cameraDistance;
            camera.position.set(x, y, z);
            camera.lookAt(cameraTarget);
            break;
            
        case 'first':
            const headPosition = new THREE.Vector3(
                worker.position.x + Math.sin(worker.rotation.y) * 0.15, // Más adelante
                1.75, // Un poco más alto
                worker.position.z + Math.cos(worker.rotation.y) * 0.15
            );
            const lookDir = new THREE.Vector3(
                Math.sin(worker.rotation.y),
                -0.05, // Mirar ligeramente hacia abajo
                Math.cos(worker.rotation.y)
            );
            camera.position.lerp(headPosition, 0.5); // Interpolación más suave
            camera.lookAt(headPosition.clone().add(lookDir.multiplyScalar(10))); // Mirar más lejos
            break;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    repairInterfaceText();
});

// Iniciar cuando carga la pÃ¡gina
window.addEventListener('load', () => {
    repairInterfaceText();
    init();
    updateZoneBriefing();
    updateAnalysisPanel();
});

// Manejar clicks en la escena de oficina
window.addEventListener('click', function(e) {
    if (!inEPPStage || !officeScene || !officeCamera) return;
    
    const rect = officeRenderer.domElement.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    const y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
    
    const mouseOffice = new THREE.Vector2(x, y);
    raycaster.setFromCamera(mouseOffice, officeCamera);
    
    const intersects = raycaster.intersectObjects(eppItems, true);
    if (intersects.length > 0) {
        let obj = intersects[0].object;
        while (obj.parent && !obj.userData.eppType) {
            obj = obj.parent;
        }
        if (obj.userData.eppType) {
            equipEPP(obj.userData.eppType);
        }
    }
});

