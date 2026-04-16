// ==================== VARIABLES GLOBALES ====================
let scene, camera, renderer;
let playerGroup;
let pallets = [];
let racks = [];
let zones = [];
let warehouseObjects = [];
let currentTask = 0;
let score = 0;
let timeLeft = 300;
let timerInterval;
let hasLoad = false;
let currentLoad = null;
let collisions = 0;
let collisionsHit = false;
let gameStarted = false;
let gameEnded = false;
let playerRotation = 0;
let loadsCount = 0;
let fuel = 100;
let achievements = [];

// ==================== SISTEMA DE AUDIO ====================
let bgMusic;
let soundPickup, soundDrop, soundCollision, soundComplete;
let musicEnabled = false;
let musicMuted = true;

// Audio Context para generar sonidos procedurales
let audioContext;

// Generar sonido procedural
function generateTone(frequency, duration, type = 'sine', volume = 0.3) {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = frequency;
    oscillator.type = type;
    
    gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + duration);
}

// Generar ruido
function generateNoise(duration, volume = 0.1) {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    
    const bufferSize = audioContext.sampleRate * duration;
    const buffer = audioContext.createBuffer(1, bufferSize, audioContext.sampleRate);
    const output = buffer.getChannelData(0);
    
    for (let i = 0; i < bufferSize; i++) {
        output[i] = Math.random() * 2 - 1;
    }
    
    const noise = audioContext.createBufferSource();
    noise.buffer = buffer;
    
    const gainNode = audioContext.createGain();
    gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
    
    noise.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    noise.start();
}

// Sonidos del juego
function playSoundPickup() {
    if (musicMuted) return;
    generateTone(523.25, 0.1, 'sine', 0.2); // C5
    setTimeout(() => generateTone(659.25, 0.15, 'sine', 0.2), 50); // E5
}

function playSoundDrop() {
    if (musicMuted) return;
    generateTone(392, 0.1, 'sine', 0.2); // G4
    setTimeout(() => generateTone(329.63, 0.15, 'sine', 0.2), 50); // E4
}

function playSoundCollision() {
    if (musicMuted) return;
    generateNoise(0.15, 0.3);
    generateTone(150, 0.2, 'sawtooth', 0.2);
}

function playSoundComplete() {
    if (musicMuted) return;
    // Melodía de victoria
    const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
    notes.forEach((freq, i) => {
        setTimeout(() => generateTone(freq, 0.3, 'sine', 0.15), i * 100);
    });
}

function playSoundStart() {
    if (musicMuted) return;
    const notes = [261.63, 329.63, 392, 523.25]; // C4, E4, G4, C5
    notes.forEach((freq, i) => {
        setTimeout(() => generateTone(freq, 0.2, 'sine', 0.15), i * 80);
    });
}

// Música de fondo procedural (melodía suave que se repite)
let musicOscillators = [];
let musicGain;

function startBackgroundMusic() {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    
    if (musicGain) return;
    
    musicGain = audioContext.createGain();
    musicGain.gain.value = 0.08;
    musicGain.connect(audioContext.destination);
    
    // Melodía suave en loop
    const playMelody = () => {
        if (musicMuted || !musicGain) return;
        
        // Notas musicales suaves (Escala pentatónica)
        const notes = [261.63, 293.66, 329.63, 392, 440, 523.25, 587.33, 659.25];
        const melody = [
            { freq: 329.63, dur: 1.5 },
            { freq: 392, dur: 1.5 },
            { freq: 440, dur: 1 },
            { freq: 392, dur: 0.5 },
            { freq: 329.63, dur: 1.5 },
            { freq: 293.66, dur: 1 },
            { freq: 261.63, dur: 2 },
        ];
        
        let time = 0;
        melody.forEach(note => {
            setTimeout(() => {
                if (musicMuted || !musicGain) return;
                
                const osc = audioContext.createOscillator();
                const noteGain = audioContext.createGain();
                
                osc.type = 'sine';
                osc.frequency.value = note.freq;
                
                noteGain.gain.setValueAtTime(0, audioContext.currentTime);
                noteGain.gain.linearRampToValueAtTime(0.5, audioContext.currentTime + 0.1);
                noteGain.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + note.dur * 0.5);
                noteGain.gain.linearRampToValueAtTime(0, audioContext.currentTime + note.dur);
                
                osc.connect(noteGain);
                noteGain.connect(musicGain);
                
                osc.start();
                osc.stop(audioContext.currentTime + note.dur);
            }, time * 1000);
            
            time += note.dur;
        });
        
        // Programar siguiente iteración
        setTimeout(playMelody, time * 1000);
    };
    
    playMelody();
}

function stopBackgroundMusic() {
    if (musicGain) {
        musicGain.disconnect();
        musicGain = null;
    }
}

function toggleMusic() {
    musicMuted = !musicMuted;
    const icon = document.getElementById('musicIcon');
    const btn = document.getElementById('musicToggle');
    
    if (musicMuted) {
        icon.textContent = '🔇';
        btn.classList.remove('playing');
        stopBackgroundMusic();
    } else {
        icon.textContent = '🎵';
        btn.classList.add('playing');
        startBackgroundMusic();
        // Crear AudioContext si no existe
        if (!audioContext) {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
    }
    
    localStorage.setItem('warehouseMusicMuted', musicMuted);
}

// Inicializar música desde localStorage
function initMusic() {
    const saved = localStorage.getItem('warehouseMusicMuted');
    if (saved !== null) {
        musicMuted = saved === 'true';
    }
    
    const icon = document.getElementById('musicIcon');
    const btn = document.getElementById('musicToggle');
    
    if (musicMuted) {
        icon.textContent = '🔇';
        btn.classList.remove('playing');
    } else {
        icon.textContent = '🎵';
        btn.classList.add('playing');
    }
}

// ==================== CONCEPTOS EDUCATIVOS ====================
const EDUCATIONAL_TIPS = {
    ABC: [
        "💡 Clasificación ABC: Los productos Zona A (20%) generan el 80% del valor. ¡Ubícalos cerca!",
        "💡 Los productos de alta rotación van en Zona A para acceso rápido.",
        "💡 La clasificación ABC optimiza el espacio y reduce tiempos de búsqueda."
    ],
    FIFO: [
        "💡 FIFO (Primero Entrado, Primero Salido): Ideal para productos perecederos.",
        "💡 FIFO previene obsolescencia y pérdidas por vencimiento.",
        "💡 Aplica FIFO en la zona de recepción: primero en entrar, primero en salir."
    ],
    FEFO: [
        "💡 FEFO (Primero que Expira, Primero Out): Prioriza la fecha de vencimiento.",
        "💡 FEFO es esencial en la zona de refrigeración.",
        "💡 Verifica fechas de vencimiento antes de almacenar en frío."
    ],
    RECEPCION: [
        "💡 Recepción: Verifica cantidad, estado y documentación.",
        "💡 El 70% de errores en almacén se originan en la recepción.",
        "💡 Compara remisión, factura y orden de compra."
    ],
    SEGURIDAD: [
        "💡 Velocidad máxima en zonas peatonales: 10 km/h.",
        "💡 Siempre mirar en la dirección de movimiento del montacargas.",
        "💡 Usar EPP es obligatorio: casco, chaleco, calzado de seguridad."
    ],
    KPIs: [
        "💡 KPI: Rotación de Inventario = CMV / Inventario Promedio",
        "💡 Cobertura = Inventario / Demanda Diaria (días)",
        "💡 Rotura de Stock = Pedidos no atendidos / Total pedidos × 100"
    ]
};

const TIP_INTERVAL = 45000;
let lastTipTime = 0;
let currentTipCategory = null;

// ==================== LOGROS DISPONIBLES ====================
const ACHIEVEMENTS = {
    speedster: { name: "⚡ Velocista", desc: "Completar en menos de 3 minutos", icon: "⚡" },
    perfect: { name: "💎 Perfecto", desc: "0 colisiones", icon: "💎" },
    efficient: { name: "📊 Eficiente", desc: "100% de eficiencia", icon: "📊" },
    firstBlood: { name: "🎯 Primera Carga", desc: "Completa tu primera tarea", icon: "🎯" },
    master: { name: "🏆 Maestro", desc: "Obtén calificación A+", icon: "🏆" },
    explorer: { name: "🗺️ Explorador", desc: "Visita todas las zonas", icon: "🗺️" },
    coldZone: { name: "❄️ Zona Fría", desc: "Almacena en zona refrigerada", icon: "❄️" },
    returns: { name: "📥 Devoluciones", desc: "Procesa una devolución", icon: "📥" },
    abcMaster: { name: "📊 Clasificador ABC", desc: "Almacena correctamente por clasificación", icon: "📊" },
    fifoExpert: { name: "🔄 Experto FIFO", desc: "Aplica correctamente el método FIFO", icon: "🔄" },
    safetyFirst: { name: "🦺 Safety First", desc: "0 colisiones en toda la partida", icon: "🦺" }
};

// ==================== TAREAS CON CONCEPTOS EDUCATIVOS ====================
const TASKS = [
    { text: "Carga el PALLET AZUL del área de recepción", color: 0x3498db, zone: "RECEP", zoneTarget: "ALMACEN", tip: "RECEPCION", concept: "Verifica la mercancía antes de cargar" },
    { text: "Almacena en ZONA A (azul) - productos alta rotación", color: 0x3498db, zoneTarget: "ALMACEN", tip: "ABC", concept: "Clasificación ABC: Zona A = 20% items, 80% valor" },
    { text: "Carga el PALLET NARANJA del área de carga/descarga", color: 0xe67e22, zone: "CARGA", zoneTarget: "PICKING", tip: "RECEPCION", concept: "Documenta cualquier discrepancia" },
    { text: "Prepara pedido siguiendo método FIFO", color: 0xe67e22, zoneTarget: "PICKING", tip: "FIFO", concept: "Primero Entrado, Primero Salido" },
    { text: "Transporta a DESPACHO - verifica fechas", color: 0x2ecc71, zoneTarget: "DESPACHO", tip: "FEFO", concept: "FEFO: Primero en vencer, primero en salir" },
    { text: "Entrega en ANDÉN - DOCUMENTA salida", color: 0x9b59b6, zoneTarget: "ANDEN", tip: "RECEPCION", concept: "Guía de despacho y manifiesto de carga" }
];

// ==================== INVENTARIO ====================
let inventory = { ALMACEN: 0, PICKING: 0, DESPACHO: 0, ANDEN: 0, FRIO: 0, DEV: 0 };

// ==================== CONTROL DE CÁMARA ====================
let cameraMode = 'follow';
let cameraModes = ['follow', 'free', 'first'];
let cameraTheta = Math.PI / 4;
let cameraPhi = Math.PI / 4;
let cameraDistance = 20;
let cameraDistanceMin = 3;
let cameraDistanceMax = 60;
let cameraTarget = new THREE.Vector3(0, 0, 0);
let isDragging = false;
let previousMousePosition = { x: 0, y: 0 };
const speed = 0.15;
const rotateSpeed = 0.05;

// ==================== ELEMENTOS DOM ====================
const startScreen = document.getElementById('startScreen');
const startBtn = document.getElementById('startBtn');
const viewRankingBtn = document.getElementById('viewRankingBtn');
const hud = document.getElementById('hud');
const progressBar = document.getElementById('progressBar');
const progressFill = document.getElementById('progressFill');
const minimap = document.getElementById('minimap');
const resultsScreen = document.getElementById('resultsScreen');
const restartBtn = document.getElementById('restartBtn');
const menuBtn = document.getElementById('menuBtn');
const rankingScreen = document.getElementById('rankingScreen');
const rankingList = document.getElementById('rankingList');
const backToMenuBtn = document.getElementById('backToMenuBtn');
const notification = document.getElementById('notification');
const gameContainer = document.getElementById('gameContainer');
const cameraModeIndicator = document.getElementById('cameraMode');
const cameraPanel = document.getElementById('cameraPanel');
const inventoryPanel = document.getElementById('inventoryPanel');
const statsPanel = document.getElementById('statsPanel');
const zoomIndicator = document.getElementById('zoomIndicator');
const crosshair = document.getElementById('crosshair');
const achievementPopup = document.getElementById('achievementPopup');
const tutorialOverlay = document.getElementById('tutorialOverlay') || { style: {} };

// ==================== TECLAS ====================
const keys = {};

// ==================== RANKING ====================
function getRanking() {
    const data = localStorage.getItem('warehouseRanking');
    return data ? JSON.parse(data) : [];
}

function saveScore(score, time, collisions) {
    const ranking = getRanking();
    const entry = { score, time: 300 - time, collisions, date: new Date().toLocaleDateString() };
    ranking.push(entry);
    ranking.sort((a, b) => b.score - a.score);
    ranking.splice(10);
    localStorage.setItem('warehouseRanking', JSON.stringify(ranking));
    return ranking;
}

function displayRanking() {
    const ranking = getRanking();
    rankingList.innerHTML = '';
    if (ranking.length === 0) {
        rankingList.innerHTML = '<p style="text-align:center;color:#888;padding:20px;">No hay puntuaciones aún. ¡Sé el primero!</p>';
        return;
    }
    ranking.forEach((entry, i) => {
        const row = document.createElement('div');
        row.className = `ranking-row ${i < 3 ? 'top-' + (i+1) : ''}`;
        row.innerHTML = `<span>${i + 1}</span><span>${entry.score} pts</span><span>${formatTime(entry.time)}</span><span>${entry.date}</span>`;
        rankingList.appendChild(row);
    });
}

// ==================== INICIALIZACIÓN ====================
function init() {
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1a1a2e);
    scene.fog = new THREE.Fog(0x1a1a2e, 30, 100);
    
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    updateCameraPosition();
    
    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    gameContainer.appendChild(renderer.domElement);
    
    setupLights();
    createEnvironment();
    createZones();
    createRacks();
    createWarehouseObjects();
    createReceptionArea();
    createPallets();
    createPlayer();
    
    setupControls();
    setupCameraControls();
    displayRanking();
    
    animate();
}

// ==================== LUCES ====================
function setupLights() {
    const ambient = new THREE.AmbientLight(0x404060, 0.5);
    scene.add(ambient);
    
    const sunLight = new THREE.DirectionalLight(0xffffff, 0.6);
    sunLight.position.set(30, 40, 20);
    sunLight.castShadow = true;
    sunLight.shadow.mapSize.width = 2048;
    sunLight.shadow.mapSize.height = 2048;
    sunLight.shadow.camera.near = 0.5;
    sunLight.shadow.camera.far = 150;
    sunLight.shadow.camera.left = -80;
    sunLight.shadow.camera.right = 80;
    sunLight.shadow.camera.top = 80;
    sunLight.shadow.camera.bottom = -80;
    scene.add(sunLight);
    
    // Luces interiores del almacén
    const warehouseLights = [
        { pos: [0, 10, 0], color: 0xffffcc, intensity: 0.5 },
        { pos: [-30, 10, -20], color: 0xffffcc, intensity: 0.4 },
        { pos: [30, 10, -20], color: 0xffffcc, intensity: 0.4 },
        { pos: [0, 10, -40], color: 0xffffcc, intensity: 0.4 },
        { pos: [-30, 10, 20], color: 0xffffcc, intensity: 0.4 },
        { pos: [30, 10, 20], color: 0xffffcc, intensity: 0.4 }
    ];
    
    warehouseLights.forEach(light => {
        const l = new THREE.PointLight(light.color, light.intensity, 40);
        l.position.set(...light.pos);
        scene.add(l);
    });
    
    // Luz fría para zona de refrigeración
    const coldLight = new THREE.PointLight(0x87ceeb, 0.6, 25);
    coldLight.position.set(40, 6, -30);
    scene.add(coldLight);
    
    // Luz de acento
    const blueLight = new THREE.PointLight(0x00ffcc, 0.3, 30);
    blueLight.position.set(-40, 5, -20);
    scene.add(blueLight);
}

// ==================== AMBIENTE ====================
function createEnvironment() {
    // Piso principal
    const floorGeo = new THREE.PlaneGeometry(100, 100);
    const floorMat = new THREE.MeshStandardMaterial({ color: 0x3d5a6c, roughness: 0.7, metalness: 0.3 });
    const floor = new THREE.Mesh(floorGeo, floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.receiveShadow = true;
    scene.add(floor);
    
    // Cuadrícula
    const gridHelper = new THREE.GridHelper(100, 100, 0x2c3e50, 0x2c3e50);
    gridHelper.position.y = 0.02;
    scene.add(gridHelper);
    
    createTransitLines();
    createWalls();
    createColumns();
    createCeiling();
}

function createTransitLines() {
    const lineMat = new THREE.MeshBasicMaterial({ color: 0xf1c40f });
    
    // Líneas de tránsito principales
    const hLine1 = new THREE.Mesh(new THREE.PlaneGeometry(0.3, 80), lineMat);
    hLine1.rotation.x = -Math.PI / 2;
    hLine1.position.set(-25, 0.03, 0);
    scene.add(hLine1);
    
    const hLine2 = new THREE.Mesh(new THREE.PlaneGeometry(0.3, 80), lineMat);
    hLine2.rotation.x = -Math.PI / 2;
    hLine2.position.set(25, 0.03, 0);
    scene.add(hLine2);
    
    const vLine = new THREE.Mesh(new THREE.PlaneGeometry(0.3, 80), lineMat);
    vLine.rotation.x = -Math.PI / 2;
    vLine.position.set(0, 0.03, -20);
    scene.add(vLine);
    
    // Flechas direccionales
    const arrowGeo = new THREE.ConeGeometry(0.8, 1.5, 3);
    for (let x = -40; x <= 40; x += 15) {
        const arrow = new THREE.Mesh(arrowGeo, lineMat);
        arrow.rotation.x = -Math.PI / 2;
        arrow.position.set(x, 0.04, 0);
        scene.add(arrow);
    }
}

function createWalls() {
    const wallMat = new THREE.MeshStandardMaterial({ color: 0x34495e, transparent: true, opacity: 0.4 });
    
    // Paredes
    const walls = [
        { size: [100, 15], pos: [0, 7.5, -50], rot: [0, 0, 0] },
        { size: [100, 15], pos: [-50, 7.5, 0], rot: [0, Math.PI/2, 0] },
        { size: [100, 15], pos: [50, 7.5, 0], rot: [0, Math.PI/2, 0] }
    ];
    
    walls.forEach(w => {
        const wall = new THREE.Mesh(new THREE.PlaneGeometry(...w.size), wallMat);
        wall.position.set(...w.pos);
        wall.rotation.set(...w.rot);
        scene.add(wall);
    });
}

function createColumns() {
    const columnMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const columnGeo = new THREE.BoxGeometry(1, 12, 1);
    
    for (let x = -45; x <= 45; x += 15) {
        for (let z = -45; z <= 35; z += 15) {
            if (Math.abs(x) > 10 || z < 0) {
                const column = new THREE.Mesh(columnGeo, columnMat);
                column.position.set(x, 6, z);
                column.castShadow = true;
                scene.add(column);
            }
        }
    }
}

function createCeiling() {
    const beamMat = new THREE.MeshStandardMaterial({ color: 0x5d6d7e });
    const beamGeo = new THREE.BoxGeometry(100, 0.5, 0.5);
    
    for (let z = -45; z <= 35; z += 10) {
        const beam = new THREE.Mesh(beamGeo, beamMat);
        beam.position.set(0, 12, z);
        scene.add(beam);
    }
}

// ==================== TODAS LAS ZONAS ====================
function createZones() {
    zones = [];
    
    // 1. ÁREA DE RECEPCIÓN (Amarillo) - Entrada principal
    createZone(0, 0, 30, 0xf1c40f, "RECEP", "RECEPCIÓN");
    
    // 2. ÁREA DE CARGA/DESCARGA (Naranja) - Dock de trucks
    createZone(-40, 0, 25, 0xe67e22, "CARGA", "CARGA/DESCARGA");
    createDock(45, 0, 25);
    
    // 3. ÁREA DE ALMACENAMIENTO (Azul) - Estanterías principales
    createZone(-35, 0, -15, 0x3498db, "ALMACEN", "ALMACENAMIENTO");
    createZone(-20, 0, -15, 0x2980b9, "ALMACEN2", "ALMACENAMIENTO");
    
    // 4. Zona de PICKING (Morado)
    createZone(20, 0, -15, 0x9b59b6, "PICKING", "PICKING");
    createWorkStation(20, 0, -15);
    
    // 5. Zona de DESPACHO (Verde)
    createZone(0, 0, -35, 0x2ecc71, "DESPACHO", "DESPACHO");
    
    // 6. Zona de DEVOLUCIONES (Rosa)
    createZone(40, 0, -15, 0xe91e63, "DEV", "DEVOLUCIONES");
    
    // 7. Zona FRÍA/REFRIGERACIÓN (Celeste)
    createZone(40, 0, -40, 0x87ceeb, "FRIO", "REFRIGERACIÓN");
    createColdRoom(40, 0, -40);
    
    // 8. Zona de SEGURIDAD (Rojo)
    createZone(-40, 0, -40, 0xe74c3c, "SEGUR", "SEGURIDAD");
    
    // 9. Oficina de CONTROL (Gris)
    createZone(-40, 0, 10, 0x95a5a6, "OFICINA", "CONTROL");
    createOffice(40, 0, 10);
    
    // 10. ANDÉN de embarque
    createZone(40, 0, -5, 0x9b59b6, "ANDEN", "ANDÉN EMBARQUE");
    createLoadingDock(40, 0, -5);
}

function createZone(x, y, z, color, code, label) {
    // Base de la zona
    const zoneGeo = new THREE.PlaneGeometry(12, 12);
    const zoneMat = new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.2, side: THREE.DoubleSide });
    const zone = new THREE.Mesh(zoneGeo, zoneMat);
    zone.rotation.x = -Math.PI / 2;
    zone.position.set(x, y + 0.02, z);
    scene.add(zone);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(12, 0.1, 12));
    const borderMat = new THREE.LineBasicMaterial({ color, linewidth: 2 });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(x, y + 0.05, z);
    scene.add(border);
    
    // Cartel
    const signGroup = new THREE.Group();
    const signGeo = new THREE.BoxGeometry(5, 1.8, 0.1);
    const signMat = new THREE.MeshStandardMaterial({ color });
    const sign = new THREE.Mesh(signGeo, signMat);
    signGroup.add(sign);
    
    const poleGeo = new THREE.CylinderGeometry(0.1, 0.1, 4, 8);
    const poleMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const pole = new THREE.Mesh(poleGeo, poleMat);
    pole.position.y = -2.5;
    signGroup.add(pole);
    
    signGroup.position.set(x, 5, z);
    scene.add(signGroup);
    
    // Etiqueta HTML
    const labelDiv = document.createElement('div');
    labelDiv.className = 'zone-label';
    labelDiv.innerHTML = `<strong>${code}</strong><br><small>${label}</small>`;
    labelDiv.style.cssText = `
        position: absolute;
        background: rgba(0,0,0,0.85);
        color: white;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        text-align: center;
        border: 2px solid ${'#' + color.toString(16).padStart(6, '0')};
        pointer-events: none;
        text-shadow: 0 1px 2px black;
    `;
    document.body.appendChild(labelDiv);
    
    zones.push({ mesh: zone, x, z, label: code, color, labelDiv });
}

// ==================== OBJETOS POR ZONA ====================
function createDock(x, y, z) {
    // Plataforma de carga
    const platformGeo = new THREE.BoxGeometry(8, 1, 10);
    const platformMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.position.set(x + 4, 0.5, z);
    platform.castShadow = true;
    platform.receiveShadow = true;
    scene.add(platform);
    
    // Rampa
    const rampGeo = new THREE.BoxGeometry(4, 0.3, 6);
    const ramp = new THREE.Mesh(rampGeo, platformMat);
    ramp.position.set(x + 6, 0.15, z);
    ramp.rotation.z = 0.2;
    scene.add(ramp);
    
    // Barandal
    const railMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const railGeo = new THREE.BoxGeometry(0.1, 1, 8);
    [-3, 3].forEach(offset => {
        const rail = new THREE.Mesh(railGeo, railMat);
        rail.position.set(x + 2, 1.5, z + offset);
        scene.add(rail);
    });
}

function createWorkStation(x, y, z) {
    const group = new THREE.Group();
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d, metalness: 0.8 });
    
    // Mesa de picking
    const tableGeo = new THREE.BoxGeometry(3, 0.1, 2);
    const table = new THREE.Mesh(tableGeo, metalMat);
    table.position.y = 1;
    table.castShadow = true;
    group.add(table);
    
    // Piernas
    const legGeo = new THREE.BoxGeometry(0.1, 1, 0.1);
    [[-1.3, -0.8], [1.3, -0.8], [-1.3, 0.8], [1.3, 0.8]].forEach(pos => {
        const leg = new THREE.Mesh(legGeo, metalMat);
        leg.position.set(pos[0], 0.5, pos[1]);
        group.add(leg);
    });
    
    // Carro de picking
    const cartGeo = new THREE.BoxGeometry(1.5, 0.8, 1);
    const cartMat = new THREE.MeshStandardMaterial({ color: 0xe67e22 });
    const cart = new THREE.Mesh(cartGeo, cartMat);
    cart.position.set(0, 0.4, -2);
    group.add(cart);
    
    // Cajas en la mesa
    const boxColors = [0xe74c3c, 0x3498db, 0x2ecc71];
    boxColors.forEach((c, i) => {
        const boxGeo = new THREE.BoxGeometry(0.5, 0.4, 0.5);
        const boxMat = new THREE.MeshStandardMaterial({ color: c });
        const box = new THREE.Mesh(boxGeo, boxMat);
        box.position.set(-0.8 + i * 0.7, 1.25, 0);
        group.add(box);
    });
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createColdRoom(x, y, z) {
    // Estructura de la cámara fría
    const frameMat = new THREE.MeshStandardMaterial({ color: 0xecf0f1, metalness: 0.5 });
    
    // Paredes
    const wallGeo = new THREE.BoxGeometry(0.1, 4, 12);
    [-6, 6].forEach(offset => {
        const wall = new THREE.Mesh(wallGeo, frameMat);
        wall.position.set(x + offset, 2, z);
        scene.add(wall);
    });
    
    const backWall = new THREE.Mesh(new THREE.BoxGeometry(12, 4, 0.1), frameMat);
    backWall.position.set(x, 2, z - 6);
    scene.add(backWall);
    
    // Techo
    const ceiling = new THREE.Mesh(new THREE.BoxGeometry(12, 0.1, 12), frameMat);
    ceiling.position.set(x, 4.05, z);
    scene.add(ceiling);
    
    // Puerta
    const doorMat = new THREE.MeshStandardMaterial({ color: 0xbdc3c7, metalness: 0.3 });
    const door = new THREE.Mesh(new THREE.BoxGeometry(2, 3.5, 0.1), doorMat);
    door.position.set(x, 1.75, z + 5.9);
    scene.add(door);
    
    // Ventana en la puerta
    const windowGeo = new THREE.BoxGeometry(0.8, 0.8, 0.05);
    const windowMat = new THREE.MeshStandardMaterial({ color: 0x87ceeb, transparent: true, opacity: 0.5 });
    const window = new THREE.Mesh(windowGeo, windowMat);
    window.position.set(x, 2.5, z + 5.95);
    scene.add(window);
    
    // Máquina de frío
    const unitGeo = new THREE.BoxGeometry(2, 2, 1);
    const unitMat = new THREE.MeshStandardMaterial({ color: 0xecf0f1 });
    const unit = new THREE.Mesh(unitGeo, unitMat);
    unit.position.set(x + 5, 1, z - 5);
    scene.add(unit);
    
    // Rejillas
    const ventGeo = new THREE.BoxGeometry(1.5, 0.1, 0.5);
    const ventMat = new THREE.MeshStandardMaterial({ color: 0x3498db });
    for (let i = 0; i < 3; i++) {
        const vent = new THREE.Mesh(ventGeo, ventMat);
        vent.position.set(x + i * 2, 3.9, z);
        scene.add(vent);
    }
}

function createOffice(x, y, z) {
    const group = new THREE.Group();
    
    // Edificio de oficina
    const buildingGeo = new THREE.BoxGeometry(8, 5, 6);
    const buildingMat = new THREE.MeshStandardMaterial({ color: 0x34495e });
    const building = new THREE.Mesh(buildingGeo, buildingMat);
    building.position.y = 2.5;
    group.add(building);
    
    // Ventanas
    const windowMat = new THREE.MeshStandardMaterial({ color: 0x85c1e9, transparent: true, opacity: 0.7 });
    for (let wx = -3; wx <= 3; wx += 2) {
        for (let wy = 1; wy <= 3; wy += 1.5) {
            const wGeo = new THREE.BoxGeometry(1.2, 0.8, 0.05);
            const w = new THREE.Mesh(wGeo, windowMat);
            w.position.set(wx, wy, 3.05);
            group.add(w);
        }
    }
    
    // Puerta
    const doorGeo = new THREE.BoxGeometry(1.5, 2.5, 0.1);
    const doorMat = new THREE.MeshStandardMaterial({ color: 0x5d6d7e });
    const door = new THREE.Mesh(doorGeo, doorMat);
    door.position.set(0, 1.25, 3.05);
    group.add(door);
    
    // Letrero
    const signGeo = new THREE.BoxGeometry(4, 1, 0.2);
    const signMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    const sign = new THREE.Mesh(signGeo, signMat);
    sign.position.set(0, 4.5, 3);
    group.add(sign);
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createLoadingDock(x, y, z) {
    // Plataforma del andén
    const platformGeo = new THREE.BoxGeometry(10, 1.5, 12);
    const platformMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.position.set(x, 0.75, z);
    platform.castShadow = true;
    scene.add(platform);
    
    // Niveladores
    const levelerGeo = new THREE.BoxGeometry(2, 0.3, 1);
    const levelerMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    for (let i = -3; i <= 3; i += 2) {
        const leveler = new THREE.Mesh(levelerGeo, levelerMat);
        leveler.position.set(x + i, 1.55, z + 5);
        scene.add(leveler);
    }
    
    // Tolda/Techo
    const canopyGeo = new THREE.BoxGeometry(12, 0.2, 14);
    const canopyMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    const canopy = new THREE.Mesh(canopyGeo, canopyMat);
    canopy.position.set(x, 6, z);
    scene.add(canopy);
    
    // Pilares
    const pillarGeo = new THREE.CylinderGeometry(0.2, 0.2, 5, 8);
    const pillarMat = new THREE.MeshStandardMaterial({ color: 0xf39c12 });
    [[-5, 6], [5, 6], [-5, -6], [5, -6]].forEach(pos => {
        const pillar = new THREE.Mesh(pillarGeo, pillarMat);
        pillar.position.set(x + pos[0], 3.5, z + pos[1]);
        scene.add(pillar);
    });
}

// ==================== ESTANTERÍAS ====================
function createRacks() {
    racks = [];
    
    // Rack selectivo - Zona Almacenamiento izquierda
    for (let i = 0; i < 4; i++) {
        createRack(-35, -5 - i * 8, 0x3498db, 4, 5);
    }
    
    // Rack selectivo - Zona Almacenamiento derecha
    for (let i = 0; i < 4; i++) {
        createRack(-20, -5 - i * 8, 0x2980b9, 4, 5);
    }
    
    // Rack para zona de picking
    createRack(20, -8, 0x9b59b6, 5, 4);
    createRack(20, -20, 0x9b59b6, 5, 4);
    
    // Estanterías cantilever para productos largos
    createCantileverRack(0, -40, 0x7f8c8d);
}

function createRack(x, z, color, width = 4, height = 4) {
    const rackGroup = new THREE.Group();
    const postGeo = new THREE.BoxGeometry(0.15, height, 0.15);
    const postMat = new THREE.MeshStandardMaterial({ color: 0x95a5a6, metalness: 0.8 });
    
    [[-width/2 + 0.1, -0.5], [width/2 - 0.1, -0.5], [-width/2 + 0.1, 0.5], [width/2 - 0.1, 0.5]].forEach(pos => {
        const post = new THREE.Mesh(postGeo, postMat);
        post.position.set(pos[0], height/2, pos[1]);
        post.castShadow = true;
        rackGroup.add(post);
    });
    
    const shelfGeo = new THREE.BoxGeometry(width, 0.08, 1.2);
    const shelfMat = new THREE.MeshStandardMaterial({ color });
    for (let y = 0.8; y < height; y += 1) {
        const shelf = new THREE.Mesh(shelfGeo, shelfMat);
        shelf.position.set(0, y, 0);
        shelf.castShadow = true;
        shelf.receiveShadow = true;
        rackGroup.add(shelf);
    }
    
    rackGroup.position.set(x, 0, z);
    scene.add(rackGroup);
    racks.push({ mesh: rackGroup, x, z, width, depth: 1.2 });
}

function createCantileverRack(x, z, color) {
    const rackGroup = new THREE.Group();
    const postMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d, metalness: 0.8 });
    const postGeo = new THREE.BoxGeometry(0.3, 6, 0.3);
    
    [-2, 2].forEach(offset => {
        const post = new THREE.Mesh(postGeo, postMat);
        post.position.set(offset, 3, 0);
        post.castShadow = true;
        rackGroup.add(post);
    });
    
    const armGeo = new THREE.BoxGeometry(0.1, 0.1, 3);
    const armMat = new THREE.MeshStandardMaterial({ color });
    [1, 2.5, 4].forEach(y => {
        [-0.5, 0.5].forEach(side => {
            const arm = new THREE.Mesh(armGeo, armMat);
            arm.position.set(side * 1.25, y, 0);
            rackGroup.add(arm);
        });
    });
    
    rackGroup.position.set(x, 0, z);
    scene.add(rackGroup);
    racks.push({ mesh: rackGroup, x, z, width: 4, depth: 3 });
}

// ==================== OBJETOS DEL ALMACÉN ====================
function createWarehouseObjects() {
    warehouseObjects = [];
    
    // Extintores en todas las zonas
    createFireExtinguisher(-45, 30);
    createFireExtinguisher(45, 30);
    createFireExtinguisher(-45, -45);
    createFireExtinguisher(45, -45);
    createFireExtinguisher(0, -45);
    
    // Botiquines
    createFirstAidKit(-43, 10);
    createFirstAidKit(43, -10);
    createFirstAidKit(0, 30);
    
    // Señales de seguridad
    createSafetySign(0, -48, "⚠️ PELIGRO");
    createSafetySign(-48, 0, "🦺 USO OBLIGATORIO");
    createSafetySign(48, 0, "🚫 VELOCIDAD MÁX 10 KM/H");
    createSafetySign(-45, -35, "🚨 ALARMA");
    
    // Pallets vacíos dispersos
    createEmptyPallet(-5, 20);
    createEmptyPallet(5, 20);
    createEmptyPallet(-35, 20);
    createEmptyPallet(35, 20);
    createEmptyPallet(-25, 0);
    createEmptyPallet(25, 0);
    
    // Contenedores
    createContainer(-42, 20, 0xe74c3c);
    createContainer(-45, 25, 0x3498db);
    createContainer(42, 30, 0x2ecc71);
    
    // Mesas de trabajo
    createWorkTable(-35, 0);
    createWorkTable(20, 5);
    createWorkTable(0, -30);
    
    // Carros elevadores pequeños
    createSmallCart(-10, 25);
    createSmallCart(30, 25);
    
    // Básculas
    createScale(-3, 30);
    
    // Torres de iluminación
    createLightTower(-40, -20);
    createLightTower(40, -20);
}

function createFireExtinguisher(x, z) {
    const group = new THREE.Group();
    const bodyGeo = new THREE.CylinderGeometry(0.12, 0.15, 0.7, 16);
    const bodyMat = new THREE.MeshStandardMaterial({ color: 0xe74c3c });
    const body = new THREE.Mesh(bodyGeo, bodyMat);
    body.position.y = 0.55;
    group.add(body);
    
    const topGeo = new THREE.ConeGeometry(0.1, 0.2, 16);
    const top = new THREE.Mesh(topGeo, bodyMat);
    top.position.y = 1;
    group.add(top);
    
    const hoseGeo = new THREE.TorusGeometry(0.15, 0.02, 8, 16, Math.PI);
    const hoseMat = new THREE.MeshStandardMaterial({ color: 0x333333 });
    const hose = new THREE.Mesh(hoseGeo, hoseMat);
    hose.position.y = 0.7;
    group.add(hose);
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createFirstAidKit(x, z) {
    const boxGeo = new THREE.BoxGeometry(0.5, 0.35, 0.25);
    const boxMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
    const box = new THREE.Mesh(boxGeo, boxMat);
    box.position.set(x, 1.2, z);
    box.castShadow = true;
    scene.add(box);
    
    const crossMat = new THREE.MeshBasicMaterial({ color: 0xe74c3c });
    const crossH = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.05, 0.01), crossMat);
    crossH.position.set(x, 1.2, z + 0.13);
    scene.add(crossH);
    const crossV = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.2, 0.01), crossMat);
    crossV.position.set(x, 1.2, z + 0.13);
    scene.add(crossV);
}

function createSafetySign(x, z, text) {
    const group = new THREE.Group();
    const poleGeo = new THREE.CylinderGeometry(0.05, 0.05, 2.5, 8);
    const poleMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const pole = new THREE.Mesh(poleGeo, poleMat);
    pole.position.y = 1.25;
    group.add(pole);
    
    const signGeo = new THREE.BoxGeometry(2, 1.2, 0.05);
    const signMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const sign = new THREE.Mesh(signGeo, signMat);
    sign.position.y = 2.8;
    group.add(sign);
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createEmptyPallet(x, z) {
    const group = new THREE.Group();
    const baseGeo = new THREE.BoxGeometry(1.2, 0.12, 1.2);
    const baseMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.06;
    group.add(base);
    
    const plankGeo = new THREE.BoxGeometry(1.2, 0.04, 0.08);
    const plankMat = new THREE.MeshStandardMaterial({ color: 0xa0522d });
    [-0.4, 0, 0.4].forEach(offset => {
        const plank = new THREE.Mesh(plankGeo, plankMat);
        plank.position.set(0, 0.14, offset);
        group.add(plank);
    });
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createContainer(x, z, color) {
    const geo = new THREE.BoxGeometry(1.8, 1.2, 1.2);
    const mat = new THREE.MeshStandardMaterial({ color, metalness: 0.3 });
    const container = new THREE.Mesh(geo, mat);
    container.position.set(x, 0.6, z);
    container.castShadow = true;
    scene.add(container);
}

function createWorkTable(x, z) {
    const group = new THREE.Group();
    const topGeo = new THREE.BoxGeometry(2.5, 0.1, 1.5);
    const topMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const top = new THREE.Mesh(topGeo, topMat);
    top.position.y = 0.95;
    top.castShadow = true;
    top.receiveShadow = true;
    group.add(top);
    
    const legGeo = new THREE.BoxGeometry(0.08, 0.95, 0.08);
    [[-1.1, -0.6], [1.1, -0.6], [-1.1, 0.6], [1.1, 0.6]].forEach(pos => {
        const leg = new THREE.Mesh(legGeo, topMat);
        leg.position.set(pos[0], 0.475, pos[1]);
        group.add(leg);
    });
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createSmallCart(x, z) {
    const group = new THREE.Group();
    const bodyGeo = new THREE.BoxGeometry(1, 0.5, 1.5);
    const bodyMat = new THREE.MeshStandardMaterial({ color: 0xf39c12 });
    const body = new THREE.Mesh(bodyGeo, bodyMat);
    body.position.y = 0.5;
    group.add(body);
    
    const wheelGeo = new THREE.CylinderGeometry(0.15, 0.15, 0.1, 16);
    const wheelMat = new THREE.MeshStandardMaterial({ color: 0x333333 });
    [[-0.4, 0.15, -0.6], [0.4, 0.15, -0.6], [-0.4, 0.15, 0.6], [0.4, 0.15, 0.6]].forEach(pos => {
        const wheel = new THREE.Mesh(wheelGeo, wheelMat);
        wheel.rotation.z = Math.PI / 2;
        wheel.position.set(...pos);
        group.add(wheel);
    });
    
    const handleGeo = new THREE.BoxGeometry(0.05, 0.8, 0.05);
    const handleMat = new THREE.MeshStandardMaterial({ color: 0x333333 });
    [-0.4, 0.4].forEach(offset => {
        const handle = new THREE.Mesh(handleGeo, handleMat);
        handle.position.set(offset, 1.15, -0.8);
        group.add(handle);
    });
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createScale(x, z) {
    const group = new THREE.Group();
    const baseGeo = new THREE.BoxGeometry(1.5, 0.15, 1.5);
    const baseMat = new THREE.MeshStandardMaterial({ color: 0x333333 });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.075;
    group.add(base);
    
    const platformGeo = new THREE.BoxGeometry(1.2, 0.05, 1.2);
    const platformMat = new THREE.MeshStandardMaterial({ color: 0x666666 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.position.y = 0.2;
    group.add(platform);
    
    const displayGeo = new THREE.BoxGeometry(0.4, 0.3, 0.1);
    const displayMat = new THREE.MeshStandardMaterial({ color: 0x00ff00 });
    const display = new THREE.Mesh(displayGeo, displayMat);
    display.position.set(0, 0.5, -0.65);
    group.add(display);
    
    group.position.set(x, 0, z);
    scene.add(group);
}

function createLightTower(x, z) {
    const group = new THREE.Group();
    const poleGeo = new THREE.CylinderGeometry(0.1, 0.15, 6, 8);
    const poleMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const pole = new THREE.Mesh(poleGeo, poleMat);
    pole.position.y = 3;
    group.add(pole);
    
    const lightGeo = new THREE.BoxGeometry(0.8, 0.4, 0.4);
    const lightMat = new THREE.MeshStandardMaterial({ color: 0xffffcc });
    const light = new THREE.Mesh(lightGeo, lightMat);
    light.position.y = 6.2;
    group.add(light);
    
    group.position.set(x, 0, z);
    scene.add(group);
}

// ==================== ÁREA DE RECEPCIÓN ====================
function createReceptionArea() {
    // Plataforma
    const platformGeo = new THREE.BoxGeometry(12, 0.25, 12);
    const platformMat = new THREE.MeshStandardMaterial({ color: 0xf39c12 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.position.set(0, 0.125, 30);
    platform.receiveShadow = true;
    scene.add(platform);
    
    // Toldo
    const canopyGeo = new THREE.BoxGeometry(14, 0.2, 14);
    const canopyMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    const canopy = new THREE.Mesh(canopyGeo, canopyMat);
    canopy.position.set(0, 7, 30);
    scene.add(canopy);
    
    // Pilares del toldo
    const pillarGeo = new THREE.CylinderGeometry(0.2, 0.2, 7, 8);
    const pillarMat = new THREE.MeshStandardMaterial({ color: 0xf39c12 });
    [[-6, -6], [6, -6], [-6, 6], [6, 6]].forEach(pos => {
        const pillar = new THREE.Mesh(pillarGeo, pillarMat);
        pillar.position.set(pos[0], 3.5, 30 + pos[1]);
        scene.add(pillar);
    });
    
    // Rampa de acceso
    const rampGeo = new THREE.BoxGeometry(6, 0.15, 4);
    const ramp = new THREE.Mesh(rampGeo, platformMat);
    ramp.position.set(0, 0.075, 38);
    ramp.rotation.x = -0.15;
    scene.add(ramp);
}

// ==================== PALLETS/CARGAS ====================
function createPallets() {
    pallets = [];
    
    // Pallets en zona de recepción
    const palletColors = [0x3498db, 0xe67e22, 0x9b59b6, 0xf1c40f, 0x2ecc71];
    const palletPositions = [
        [-3, 28], [0, 28], [3, 28],
        [-2, 31], [2, 31]
    ];
    
    palletPositions.forEach((pos, i) => {
        if (i < palletColors.length) {
            createPallet(pos[0], pos[1], palletColors[i], i);
        }
    });
}

function createPallet(x, z, color, taskIndex) {
    const palletGroup = new THREE.Group();
    
    // Base de madera
    const baseGeo = new THREE.BoxGeometry(1.4, 0.12, 1.4);
    const baseMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.06;
    base.castShadow = true;
    palletGroup.add(base);
    
    // Tablones
    const plankGeo = new THREE.BoxGeometry(1.4, 0.04, 0.08);
    const plankMat = new THREE.MeshStandardMaterial({ color: 0xa0522d });
    [-0.5, 0, 0.5].forEach(offset => {
        const plank = new THREE.Mesh(plankGeo, plankMat);
        plank.position.set(0, 0.14, offset);
        palletGroup.add(plank);
    });
    
    // Cajas
    const boxGeo = new THREE.BoxGeometry(1.1, 0.7, 1.1);
    const boxMat = new THREE.MeshStandardMaterial({ color, metalness: 0.1 });
    const box = new THREE.Mesh(boxGeo, boxMat);
    box.position.y = 0.55;
    box.castShadow = true;
    palletGroup.add(box);
    
    // Etiqueta
    const labelGeo = new THREE.PlaneGeometry(0.4, 0.3);
    const labelMat = new THREE.MeshBasicMaterial({ color: 0xffffff, side: THREE.DoubleSide });
    const label = new THREE.Mesh(labelGeo, labelMat);
    label.position.set(0, 0.65, 0.56);
    palletGroup.add(label);
    
    palletGroup.position.set(x, 0, z);
    palletGroup.userData = { taskIndex, color, originalPos: { x, z }, pickedUp: false, delivered: false };
    
    scene.add(palletGroup);
    pallets.push(palletGroup);
}

// ==================== MONTACARGAS ====================
function createPlayer() {
    playerGroup = new THREE.Group();
    
    // Chasis
    const chassisGeo = new THREE.BoxGeometry(1.4, 0.4, 2.4);
    const chassisMat = new THREE.MeshStandardMaterial({ color: 0xe94560, metalness: 0.3 });
    const chassis = new THREE.Mesh(chassisGeo, chassisMat);
    chassis.position.y = 0.5;
    chassis.castShadow = true;
    playerGroup.add(chassis);
    
    // Cuerpo
    const bodyGeo = new THREE.BoxGeometry(1.2, 1.2, 1.6);
    const body = new THREE.Mesh(bodyGeo, chassisMat);
    body.position.y = 1.25;
    body.castShadow = true;
    playerGroup.add(body);
    
    // Cabina
    const cabinGeo = new THREE.BoxGeometry(1.1, 1, 0.9);
    const cabinMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50, metalness: 0.5 });
    const cabin = new THREE.Mesh(cabinGeo, cabinMat);
    cabin.position.set(0, 2.1, -0.2);
    playerGroup.add(cabin);
    
    // Ventanas
    const windowMat = new THREE.MeshStandardMaterial({ color: 0x87ceeb, transparent: true, opacity: 0.6, metalness: 0.9 });
    const windowFront = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.5, 0.05), windowMat);
    windowFront.position.set(0, 2.15, 0.26);
    playerGroup.add(windowFront);
    
    [-0.5, 0.5].forEach(x => {
        const w = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.4, 0.5), windowMat);
        w.position.set(x, 2.15, -0.2);
        playerGroup.add(w);
    });
    
    // Techo
    const roofGeo = new THREE.BoxGeometry(1.3, 0.1, 1.4);
    const roofMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const roof = new THREE.Mesh(roofGeo, roofMat);
    roof.position.y = 2.65;
    roof.castShadow = true;
    playerGroup.add(roof);
    
    // Mástil
    const mastGeo = new THREE.BoxGeometry(0.15, 2.2, 0.15);
    const mastMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d, metalness: 0.8 });
    [-0.4, 0.4].forEach(x => {
        const mast = new THREE.Mesh(mastGeo, mastMat);
        mast.position.set(x, 1.4, 1);
        playerGroup.add(mast);
    });
    
    // Horquillas
    const forkGeo = new THREE.BoxGeometry(0.1, 0.05, 2);
    const forkMat = new THREE.MeshStandardMaterial({ color: 0x5d6d7e, metalness: 0.9 });
    [-0.35, 0.35].forEach(x => {
        const fork = new THREE.Mesh(forkGeo, forkMat);
        fork.position.set(x, 0.28, 1.8);
        playerGroup.add(fork);
    });
    
    // Contrapeso
    const counterGeo = new THREE.BoxGeometry(1.1, 0.9, 0.7);
    const counterMat = new THREE.MeshStandardMaterial({ color: 0x34495e });
    const counter = new THREE.Mesh(counterGeo, counterMat);
    counter.position.set(0, 0.75, -1);
    playerGroup.add(counter);
    
    // Ruedas
    const wheelGeo = new THREE.CylinderGeometry(0.35, 0.35, 0.25, 16);
    const wheelMat = new THREE.MeshStandardMaterial({ color: 0x2c3e50 });
    [-0.7, 0.7].forEach(x => {
        const wheel = new THREE.Mesh(wheelGeo, wheelMat);
        wheel.rotation.z = Math.PI / 2;
        wheel.position.set(x, 0.35, -0.8);
        playerGroup.add(wheel);
    });
    
    const frontWheelGeo = new THREE.CylinderGeometry(0.25, 0.25, 0.2, 16);
    [-0.55, 0.55].forEach(x => {
        const wheel = new THREE.Mesh(frontWheelGeo, wheelMat);
        wheel.rotation.z = Math.PI / 2;
        wheel.position.set(x, 0.25, 0.9);
        playerGroup.add(wheel);
    });
    
    // Luces
    const lightGeo = new THREE.SphereGeometry(0.08, 16, 16);
    const lightMat = new THREE.MeshBasicMaterial({ color: 0xffff00 });
    [-0.35, 0.35].forEach(x => {
        const light = new THREE.Mesh(lightGeo, lightMat);
        light.position.set(x, 0.95, 1.2);
        playerGroup.add(light);
    });
    
    // Luz de advertencia
    const warnLight = new THREE.Mesh(new THREE.SphereGeometry(0.1, 16, 16), new THREE.MeshBasicMaterial({ color: 0x00ffcc }));
    warnLight.position.set(0, 2.8, 0);
    playerGroup.add(warnLight);
    
    // Indicador de batería
    const batteryLight = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.12, 0.05), new THREE.MeshBasicMaterial({ color: 0x00ff00 }));
    batteryLight.position.set(0, 1.95, 0.46);
    playerGroup.add(batteryLight);
    playerGroup.userData.batteryLight = batteryLight;
    
    playerGroup.position.set(0, 0, 15);
    scene.add(playerGroup);
}

// ==================== CONTROLES ====================
function setupControls() {
    window.addEventListener('keydown', (e) => {
        const key = e.key.toLowerCase();
        keys[key] = true;
        if (key === 'v' && gameStarted && !gameEnded) nextCameraMode();
        if (key === '1' && gameStarted && !gameEnded) setCameraMode('follow');
        if (key === '2' && gameStarted && !gameEnded) setCameraMode('free');
        if (key === '3' && gameStarted && !gameEnded) setCameraMode('first');
        if ((key === '+' || key === '=') && gameStarted && !gameEnded) adjustZoom(-2);
        if ((key === '-' || key === '_') && gameStarted && !gameEnded) adjustZoom(2);
        if (e.key === ' ' && gameStarted && !gameEnded) { e.preventDefault(); handlePickup(); }
    });
    window.addEventListener('keyup', (e) => { keys[e.key.toLowerCase()] = false; });
    window.addEventListener('resize', onWindowResize);
}

function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
}

// ==================== CONTROL DE CÁMARA ====================
function setupCameraControls() {
    const canvas = renderer.domElement;
    canvas.addEventListener('mousedown', (e) => {
        if (e.button === 0) { isDragging = true; previousMousePosition = { x: e.clientX, y: e.clientY }; }
    });
    canvas.addEventListener('mousemove', (e) => {
        if (!isDragging || cameraMode !== 'free') return;
        cameraTheta -= (e.clientX - previousMousePosition.x) * 0.005;
        cameraPhi += (e.clientY - previousMousePosition.y) * 0.005;
        cameraPhi = Math.max(0.1, Math.min(Math.PI / 2 - 0.1, cameraPhi));
        previousMousePosition = { x: e.clientX, y: e.clientY };
    });
    window.addEventListener('mouseup', () => { isDragging = false; });
    canvas.addEventListener('mouseleave', () => { isDragging = false; });
    canvas.addEventListener('wheel', (e) => { e.preventDefault(); if (gameStarted && !gameEnded) adjustZoom(e.deltaY * 0.02); }, { passive: false });
    canvas.addEventListener('contextmenu', (e) => e.preventDefault());
}

function nextCameraMode() {
    const idx = cameraModes.indexOf(cameraMode);
    setCameraMode(cameraModes[(idx + 1) % cameraModes.length]);
}

function setCameraMode(mode) {
    cameraMode = mode;
    camera.fov = mode === 'first' ? 90 : 75;
    camera.updateProjectionMatrix();
    if (mode === 'free' && playerGroup) cameraTarget.set(playerGroup.position.x, 0, playerGroup.position.z);
    updateCameraIndicator();
    showNotification(getCameraModeText(), getCameraModeColor());
}

function getCameraModeText() {
    return { follow: '👁️ SEGUIR', free: '📷 LIBRE', first: '🎥 1RA PERSONA' }[cameraMode] + ' activado';
}

function getCameraModeColor() {
    return { follow: '#00ffcc', free: '#f1c40f', first: '#e94560' }[cameraMode];
}

function adjustZoom(delta) {
    cameraDistance += delta;
    cameraDistance = Math.max(cameraDistanceMin, Math.min(cameraDistanceMax, cameraDistance));
    document.getElementById('zoomText').textContent = Math.round((cameraDistanceMax / cameraDistance) * 30) + '%';
}

function updateCameraIndicator() {
    const texts = { follow: '👁️ SEGUIR', free: '📷 LIBRE', first: '🎥 1RA PERSONA' };
    cameraModeIndicator.textContent = `${texts[cameraMode]} | V para cambiar`;
    document.querySelectorAll('.camera-mode-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('btn' + cameraMode.charAt(0).toUpperCase() + cameraMode.slice(1)).classList.add('active');
}

function updateCameraPosition() {
    if (!playerGroup) return;
    
    const cabinInterior = playerGroup.userData.cabinInterior;
    
    if (cameraMode === 'follow') {
        camera.position.set(playerGroup.position.x + Math.sin(playerRotation) * -10, 8, playerGroup.position.z + Math.cos(playerRotation) * -10);
        camera.lookAt(playerGroup.position.x, 1, playerGroup.position.z);
        crosshair.style.display = 'none';
        if (cabinInterior) cabinInterior.visible = false;
        const fc = document.getElementById('forkliftControls');
        if (fc) fc.style.display = 'none';
    } else if (cameraMode === 'free') {
        camera.position.set(cameraTarget.x + cameraDistance * Math.sin(cameraPhi) * Math.sin(cameraTheta), cameraTarget.y + cameraDistance * Math.cos(cameraPhi), cameraTarget.z + cameraDistance * Math.sin(cameraPhi) * Math.cos(cameraTheta));
        camera.lookAt(cameraTarget);
        crosshair.style.display = 'none';
        if (cabinInterior) cabinInterior.visible = false;
        const fc = document.getElementById('forkliftControls');
        if (fc) fc.style.display = 'none';
    } else if (cameraMode === 'first') {
        // Cámara dentro de la cabina
        const cabinOffsetZ = -0.35;
        camera.position.set(
            playerGroup.position.x + Math.sin(playerRotation) * cabinOffsetZ,
            1.75,
            playerGroup.position.z + Math.cos(playerRotation) * cabinOffsetZ
        );
        camera.lookAt(
            playerGroup.position.x + Math.sin(playerRotation) * 5,
            1.2,
            playerGroup.position.z + Math.cos(playerRotation) * 5
        );
        crosshair.style.display = 'block';
        if (cabinInterior) cabinInterior.visible = true;
        const fc2 = document.getElementById('forkliftControls');
        if (fc2) fc2.style.display = 'none';
        
        // Actualizar indicadores
        updateCabinIndicators();
    }
}

function updateCabinIndicators() {
    if (!playerGroup) return;
    
    const fuelIndicator = playerGroup.userData.fuelIndicator;
    const loadIndicator = playerGroup.userData.loadIndicator;
    
    if (fuelIndicator) {
        if (fuel > 50) {
            fuelIndicator.material.color.setHex(0x00ff00);
        } else if (fuel > 20) {
            fuelIndicator.material.color.setHex(0xffff00);
        } else {
            fuelIndicator.material.color.setHex(0xff0000);
        }
    }
    
    if (loadIndicator) {
        if (hasLoad) {
            loadIndicator.material.color.setHex(0xffd700);
        } else {
            loadIndicator.material.color.setHex(0x333333);
        }
    }
}

// ==================== MANEJO DE CARGA ====================
function handlePickup() {
    if (!hasLoad) {
        pallets.forEach(pallet => {
            if (pallet.userData.pickedUp || pallet.userData.delivered) return;
            if (pallet.userData.taskIndex !== currentTask) return;
            const dist = playerGroup.position.distanceTo(pallet.position);
            if (dist < 3.5) {
                hasLoad = true;
                currentLoad = pallet;
                pallet.userData.pickedUp = true;
                loadsCount++;
                unlockAchievement('firstBlood');
                playSoundPickup();
                const taskTip = TASKS[currentTask]?.concept || "Pallet cargado";
                showNotification(`✅ ${taskTip}`, "#00ffcc");
            }
        });
    } else {
        pallets.forEach(pallet => {
            if (!pallet.userData.pickedUp || pallet.userData.delivered) return;
            const targetZone = zones.find(z => z.label === TASKS[currentTask].zoneTarget);
            if (targetZone) {
                const distToZone = Math.sqrt(Math.pow(pallet.position.x - targetZone.x, 2) + Math.pow(pallet.position.z - targetZone.z, 2));
                if (distToZone < 7) {
                    hasLoad = false;
                    playSoundDrop();
                    pallet.userData.delivered = true;
                    pallet.visible = false;
                    currentLoad = null;
                    inventory[targetZone.label]++;
                    updateInventoryDisplay();
                    
                    const task = TASKS[currentTask];
                    unlockEducationalAchievement(task?.tip);
                    
                    const bonus = timeLeft > 180 ? 50 : 20;
                    score += 100 + bonus;
                    currentTask++;
                    playSoundComplete();
                    
                    if (currentTask >= TASKS.length) {
                        showNotification("🎉 ¡Simulación completada! Revisando concepto...", "#2ecc71");
                        setTimeout(endGame, 1000);
                    } else {
                        const nextTip = TASKS[currentTask]?.concept || "Siguiente tarea";
                        showNotification(`📚 ${nextTip}`, "#3498db");
                        updateMission();
                    }
                } else {
                    showNotification(`⚠️ Acércate a zona ${targetZone.label}`, "#f1c40f");
                }
            }
        });
    }
}

function updateMission() {
    document.getElementById('missionText').textContent = TASKS[currentTask].text;
    document.getElementById('tasks').textContent = currentTask;
    document.getElementById('totalTasks').textContent = TASKS.length;
    updateProgress();
}

function updateProgress() {
    const progress = (currentTask / TASKS.length) * 100;
    progressFill.style.width = progress + '%';
    document.getElementById('progressText').textContent = Math.round(progress) + '%';
}

function updateInventoryDisplay() {
    document.getElementById('invA').textContent = inventory.ALMACEN;
    document.getElementById('invB').textContent = inventory.PICKING;
    document.getElementById('invC').textContent = inventory.DESPACHO;
    document.getElementById('invTotal').textContent = inventory.ALMACEN + inventory.PICKING + inventory.DESPACHO;
    document.getElementById('invTotalMax').textContent = TASKS.length;
}

function updateFuelDisplay() {
    const fuelFill = document.getElementById('fuelFill');
    fuelFill.style.width = fuel + '%';
    document.getElementById('fuelPercent').textContent = Math.round(fuel) + '%';
    fuelFill.className = 'fuel-fill' + (fuel < 20 ? ' critical' : fuel < 40 ? ' low' : '');
    if (playerGroup?.userData.batteryLight) {
        playerGroup.userData.batteryLight.material.color.setHex(fuel > 50 ? 0x00ff00 : fuel > 20 ? 0xff6600 : 0xff0000);
    }
}

// ==================== LOGROS ====================
function unlockAchievement(key) {
    if (achievements.includes(key)) return;
    achievements.push(key);
    const ach = ACHIEVEMENTS[key];
    document.getElementById('achievementName').textContent = ach.name;
    document.querySelector('.achievement-icon').textContent = ach.icon;
    achievementPopup.classList.add('show');
    setTimeout(() => achievementPopup.classList.remove('show'), 3000);
}

function checkAchievements() {
    const timeUsed = 300 - timeLeft;
    if (timeUsed < 180) unlockAchievement('speedster');
    if (collisions === 0) unlockAchievement('perfect');
    const efficiency = Math.max(0, 100 - (collisions * 5));
    if (efficiency === 100) unlockAchievement('efficient');
    const finalScore = calculateFinalScore();
    if (finalScore >= 600) unlockAchievement('master');
}

// ==================== NOTIFICACIONES ====================
function showNotification(text, color, type = '') {
    notification.textContent = text;
    notification.style.background = color;
    notification.className = type;
    notification.style.display = 'block';
    setTimeout(() => { notification.style.display = 'none'; }, 2500);
}

// ==================== COLISIONES ====================
function checkCollision(newX, newZ) {
    for (let rack of racks) {
        const dx = Math.abs(newX - rack.x);
        const dz = Math.abs(newZ - rack.z);
        if (dx < (rack.width / 2 + 1) && dz < (rack.depth / 2 + 1)) return true;
    }
    if (Math.abs(newX) > 48 || Math.abs(newZ) > 48) return true;
    return false;
}

// ==================== MINIMAPA ====================
function updateMinimap() {
    const canvas = document.getElementById('minimapCanvas');
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, 150, 150);
    
    zones.forEach(zone => {
        const c = '#' + zone.color.toString(16).padStart(6, '0');
        ctx.fillStyle = c;
        ctx.globalAlpha = 0.4;
        ctx.fillRect((zone.x + 50) * 1.5, (zone.z + 50) * 1.5, 18, 18);
        ctx.globalAlpha = 1;
        ctx.strokeStyle = c;
        ctx.strokeRect((zone.x + 50) * 1.5, (zone.z + 50) * 1.5, 18, 18);
    });
    
    racks.forEach(rack => {
        ctx.fillStyle = '#555';
        ctx.fillRect((rack.x + 50) * 1.5 - 3, (rack.z + 50) * 1.5 - 2, 6, 4);
    });
    
    pallets.forEach(pallet => {
        if (!pallet.userData.delivered) {
            const isCurrent = pallet.userData.taskIndex === currentTask;
            ctx.fillStyle = isCurrent ? '#00ffcc' : '#' + pallet.userData.color.toString(16).padStart(6, '0');
            ctx.beginPath();
            ctx.arc((pallet.position.x + 50) * 1.5, (pallet.position.z + 50) * 1.5, isCurrent ? 5 : 3, 0, Math.PI * 2);
            ctx.fill();
        }
    });
    
    ctx.fillStyle = '#e94560';
    ctx.beginPath();
    ctx.arc((playerGroup.position.x + 50) * 1.5, (playerGroup.position.z + 50) * 1.5, 6, 0, Math.PI * 2);
    ctx.fill();
    
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo((playerGroup.position.x + 50) * 1.5, (playerGroup.position.z + 50) * 1.5);
    ctx.lineTo((playerGroup.position.x + 50) * 1.5 + Math.sin(playerRotation) * 10, (playerGroup.position.z + 50) * 1.5 - Math.cos(playerRotation) * 10);
    ctx.stroke();
}

// ==================== ETIQUETAS DE ZONAS ====================
function updateZoneLabels() {
    zones.forEach(zone => {
        const vector = new THREE.Vector3(zone.x, 4, zone.z);
        vector.project(camera);
        const x = (vector.x * 0.5 + 0.5) * window.innerWidth;
        const y = (-(vector.y * 0.5) + 0.5) * window.innerHeight;
        if (zone.labelDiv) {
            zone.labelDiv.style.left = x + 'px';
            zone.labelDiv.style.top = y + 'px';
            zone.labelDiv.style.transform = 'translate(-50%, -50%)';
            zone.labelDiv.style.display = vector.z > 1 ? 'none' : 'block';
        }
    });
}

// ==================== ESTADÍSTICAS ====================
function updateStats() {
    const timeUsed = 300 - timeLeft;
    document.getElementById('statTime').textContent = formatTime(timeUsed);
    document.getElementById('statLoads').textContent = loadsCount;
    document.getElementById('statCollisions').textContent = collisions;
    document.getElementById('statEfficiency').textContent = Math.max(0, 100 - (collisions * 5)) + '%';
}

// ==================== CONSEJOS EDUCATIVOS ====================
function showEducationalTip() {
    if (!gameStarted || gameEnded) return;
    
    const task = TASKS[currentTask];
    const tipCategory = task?.tip || 'ABC';
    const tips = EDUCATIONAL_TIPS[tipCategory] || EDUCATIONAL_TIPS.ABC;
    const randomTip = tips[Math.floor(Math.random() * tips.length)];
    
    showNotification(randomTip, "#3498db");
}

function startTipTimer() {
    setInterval(() => {
        if (gameStarted && !gameEnded) {
            showEducationalTip();
        }
    }, TIP_INTERVAL);
}

// ==================== INTEGRACIÓN CON CURSO ====================
function getCourseProgress() {
    try {
        const data = localStorage.getItem('intep_almacenamiento_progress');
        return data ? JSON.parse(data) : null;
    } catch (e) {
        return null;
    }
}

function syncWithCourse() {
    const courseProgress = getCourseProgress();
    if (courseProgress) {
        const completedModules = Object.values(courseProgress.moduleProgress)
            .filter(m => m.completed).length;
        console.log(`📚 Curso completado: ${completedModules}/7 módulos`);
        return completedModules;
    }
    return 0;
}

function unlockEducationalAchievement(category) {
    if (category === 'ABC') unlockAchievement('abcMaster');
    if (category === 'FIFO' || category === 'FEFO') unlockAchievement('fifoExpert');
    if (collisions === 0 && loadsCount >= 3) unlockAchievement('safetyFirst');
}

function formatTime(seconds) {
    return `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
}

// ==================== JUEGO ====================
function startGame() {
    startScreen.style.display = 'none';
    rankingScreen.style.display = 'none';
    hud.style.display = 'flex';
    progressBar.style.display = 'block';
    minimap.style.display = 'block';
    cameraModeIndicator.style.display = 'block';
    cameraPanel.style.display = 'block';
    inventoryPanel.style.display = 'block';
    statsPanel.style.display = 'block';
    zoomIndicator.style.display = 'block';
    
    gameStarted = true;
    achievements = [];
    currentTask = 0;
    score = 0;
    timeLeft = 300;
    hasLoad = false;
    currentLoad = null;
    collisions = 0;
    loadsCount = 0;
    fuel = 100;
    inventory = { ALMACEN: 0, PICKING: 0, DESPACHO: 0, ANDEN: 0, FRIO: 0, DEV: 0 };
    cameraMode = 'follow';
    cameraDistance = 20;
    
    pallets.forEach(p => {
        p.userData.pickedUp = false;
        p.userData.delivered = false;
        p.visible = true;
        p.position.set(p.userData.originalPos.x, 0, p.userData.originalPos.z);
    });
    
    playerGroup.position.set(0, 0, 20);
    playerGroup.rotation.y = 0;
    playerRotation = 0;
    
    syncWithCourse();
    updateMission();
    updateInventoryDisplay();
    updateFuelDisplay();
    updateCameraIndicator();
    
    setTimeout(() => {
        showNotification("💡 " + (TASKS[0]?.concept || "Bienvenido al simulador"), "#3498db");
    }, 1000);
    
    timerInterval = setInterval(() => {
        timeLeft--;
        fuel = Math.max(0, fuel - 0.05);
        updateFuelDisplay();
        document.getElementById('timer').textContent = formatTime(timeLeft);
        document.getElementById('score').textContent = score;
        updateStats();
        if (timeLeft <= 0) endGame();
    }, 1000);
}

function calculateFinalScore() {
    const collisionPenalty = collisions * 25;
    const fuelPenalty = fuel < 20 ? 50 : 0;
    return Math.max(0, score - collisionPenalty - fuelPenalty);
}

function endGame() {
    gameEnded = true;
    clearInterval(timerInterval);
    
    hud.style.display = 'none';
    progressBar.style.display = 'none';
    minimap.style.display = 'none';
    cameraModeIndicator.style.display = 'none';
    cameraPanel.style.display = 'none';
    inventoryPanel.style.display = 'none';
    statsPanel.style.display = 'none';
    zoomIndicator.style.display = 'none';
    crosshair.style.display = 'none';
    
    checkAchievements();
    
    const finalScore = calculateFinalScore();
    const timeUsed = 300 - timeLeft;
    const efficiency = Math.max(0, 100 - (collisions * 5));
    const courseProgress = syncWithCourse();
    
    let grade, gradeClass, feedback;
    if (finalScore >= 600) { grade = 'A+'; gradeClass = 'grade-a-plus'; feedback = '¡EXCELENTE! Eres un experto en logística.'; }
    else if (finalScore >= 500) { grade = 'A'; gradeClass = 'grade-a'; feedback = '¡Muy bien! Tienes un excelente desempeño.'; }
    else if (finalScore >= 400) { grade = 'B'; gradeClass = 'grade-b'; feedback = 'Buen trabajo. Sigue practicando.'; }
    else if (finalScore >= 300) { grade = 'C'; gradeClass = 'grade-c'; feedback = 'Aprobado. Necesitas mejorar.'; }
    else if (finalScore >= 200) { grade = 'D'; gradeClass = 'grade-d'; feedback = 'Repite la práctica.'; }
    else { grade = 'F'; gradeClass = 'grade-f'; feedback = 'Necesitas repasar el curso teórico.'; }
    
    if (finalScore >= 600) unlockAchievement('master');
    
    document.getElementById('grade').textContent = grade;
    document.getElementById('grade').className = gradeClass;
    document.getElementById('finalTime').textContent = formatTime(timeUsed);
    document.getElementById('finalScore').textContent = finalScore;
    document.getElementById('finalCollisions').textContent = collisions;
    document.getElementById('finalEfficiency').textContent = efficiency + '%';
    document.getElementById('feedback').textContent = feedback;
    
    if (courseProgress > 0) {
        document.getElementById('feedback').textContent += ` (Curso: ${courseProgress}/7 módulos)`;
    }
    
    const achievementsGrid = document.getElementById('achievementsList');
    achievementsGrid.innerHTML = '';
    Object.keys(ACHIEVEMENTS).forEach(key => {
        const ach = ACHIEVEMENTS[key];
        achievementsGrid.innerHTML += `<div class="achievement-badge ${achievements.includes(key) ? '' : 'locked'}"><span class="badge-icon">${ach.icon}</span><span class="badge-name">${achievements.includes(key) ? ach.name : '???'}</span></div>`;
    });
    
    document.getElementById('inventorySummary').innerHTML = `
        <div class="inv-summary-item"><span class="inv-summary-color" style="background:#3498db"></span>Almacén: ${inventory.ALMACEN}</div>
        <div class="inv-summary-item"><span class="inv-summary-color" style="background:#9b59b6"></span>Picking: ${inventory.PICKING}</div>
        <div class="inv-summary-item"><span class="inv-summary-color" style="background:#2ecc71"></span>Despacho: ${inventory.DESPACHO}</div>
    `;
    
    saveScore(finalScore, timeLeft, collisions);
    resultsScreen.style.display = 'flex';
}

function resetGame() { resultsScreen.style.display = 'none'; startGame(); }
function showRanking() { startScreen.style.display = 'none'; rankingScreen.style.display = 'flex'; displayRanking(); }
function backToMenu() { rankingScreen.style.display = 'none'; resultsScreen.style.display = 'none'; startScreen.style.display = 'flex'; }
function goToCourse() { window.open('curso.html', '_self'); }

// ==================== EVENT LISTENERS ====================
startBtn.addEventListener('click', () => {
    playSoundStart();
    startGame();
});
viewRankingBtn.addEventListener('click', showRanking);
restartBtn.addEventListener('click', resetGame);
menuBtn.addEventListener('click', backToMenu);
backToMenuBtn.addEventListener('click', backToMenu);

// Botón de música
const musicToggleBtn = document.getElementById('musicToggle');
if (musicToggleBtn) {
    musicToggleBtn.addEventListener('click', toggleMusic);
}

// Inicializar música
initMusic();

// ==================== BUCLE DE ANIMACIÓN ====================
let beaconPulse = 0;

function animate() {
    requestAnimationFrame(animate);
    
    if (gameStarted && !gameEnded) {
        if (keys['w'] || keys['arrowup']) {
            const moveX = Math.sin(playerRotation) * speed;
            const moveZ = Math.cos(playerRotation) * speed;
            const newX = playerGroup.position.x + moveX;
            const newZ = playerGroup.position.z + moveZ;
            if (!checkCollision(newX, newZ)) { playerGroup.position.x = newX; playerGroup.position.z = newZ; }
            else if (!collisionsHit) { collisions++; collisionsHit = true; playSoundCollision(); setTimeout(() => collisionsHit = false, 500); showNotification("⚠️ ¡Colisión!", "#e94560", "error"); }
        }
        if (keys['s'] || keys['arrowdown']) {
            const moveX = -Math.sin(playerRotation) * speed;
            const moveZ = -Math.cos(playerRotation) * speed;
            const newX = playerGroup.position.x + moveX;
            const newZ = playerGroup.position.z + moveZ;
            if (!checkCollision(newX, newZ)) { playerGroup.position.x = newX; playerGroup.position.z = newZ; }
            else if (!collisionsHit) { collisions++; collisionsHit = true; playSoundCollision(); setTimeout(() => collisionsHit = false, 500); showNotification("⚠️ ¡Colisión!", "#e94560", "error"); }
        }
        if (keys['a'] || keys['arrowleft']) { playerRotation += rotateSpeed; playerGroup.rotation.y = playerRotation; }
        if (keys['d'] || keys['arrowright']) { playerRotation -= rotateSpeed; playerGroup.rotation.y = playerRotation; }
        if (hasLoad && currentLoad) { currentLoad.position.x = playerGroup.position.x + Math.sin(playerRotation) * 2.5; currentLoad.position.z = playerGroup.position.z + Math.cos(playerRotation) * 2.5; currentLoad.rotation.y = playerRotation; }
        updateCameraPosition();
        updateMinimap();
        updateZoneLabels();
    }
    
    // Animar ruedas traseras (motrices) - girar sobre su propio eje
    if (playerGroup && playerGroup.userData.backWheels) {
        if (keys['w'] || keys['arrowup']) {
            playerGroup.userData.backWheels.forEach(function(wheel) {
                wheel.rotation.x -= 0.2;
            });
            playerGroup.userData.frontWheels.forEach(function(wheel) {
                wheel.rotation.x -= 0.2;
            });
        }
        if (keys['s'] || keys['arrowdown']) {
            playerGroup.userData.backWheels.forEach(function(wheel) {
                wheel.rotation.x += 0.2;
            });
            playerGroup.userData.frontWheels.forEach(function(wheel) {
                wheel.rotation.x += 0.2;
            });
        }
    }
    
    // Animar baliza (parpadeo)
    if (playerGroup && playerGroup.userData.beaconLight) {
        beaconPulse += 0.1;
        const beacon = playerGroup.userData.beaconLight;
        const intensity = (Math.sin(beaconPulse) + 1) / 2;
        const baseColor = new THREE.Color(0xff8800);
        const brightColor = new THREE.Color(0xffdd00);
        beacon.material.color.lerpColors(baseColor, brightColor, intensity);
    }
    
    renderer.render(scene, camera);
}

try {
    init();
} catch(e) {
    console.error("Error inicializando:", e);
    alert("Error: " + e.message);
}
