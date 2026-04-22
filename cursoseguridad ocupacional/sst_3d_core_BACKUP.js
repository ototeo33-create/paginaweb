// ==================== VARIABLES GLOBALES ====================
let scene, camera, renderer;
let worker;
let dangers = [];
let currentZone = 'almacen';
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

// Cámara
let cameraMode = 'follow';
let cameraModes = ['follow', 'free', 'first'];
let cameraTheta = Math.PI / 4;
let cameraPhi = Math.PI / 4;
let cameraDistance = 25;
let cameraTarget = new THREE.Vector3(0, 0, 0);
let isDragging = false;
let previousMouse = { x: 0, y: 0 };

// Teclas
const keys = {};

// Raycaster para interacción
const raycaster = new THREE.Raycaster();
const mouse = new THREE.Vector2();

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

// Música de fondo suave
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

// ==================== INICIALIZACIÓN ====================
function init() {
    // Crear escena
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1a1a2e);
    scene.fog = new THREE.Fog(0x1a1a2e, 50, 150);
    
    // Crear cámara
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
    
    // Iniciar animación
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
        { pos: [-25, 8, -25], color: 0xff6b6b, intensity: 0.4 }, // Almacén
        { pos: [25, 8, -25], color: 0x4ecdc4, intensity: 0.4 }, // Máquinas
        { pos: [0, 12, 0], color: 0xf7d794, intensity: 0.5 }, // Centro
        { pos: [25, 8, 25], color: 0xa29bfe, intensity: 0.4 }, // Eléctrico
        { pos: [-25, 8, 25], color: 0x55efc4, intensity: 0.4 }, // Químico
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
    
    // Cuadrícula
    const grid = new THREE.GridHelper(150, 150, 0x2c3e50, 0x2c3e50);
    grid.position.y = 0.02;
    scene.add(grid);
    
    // Paredes perimetrales
    createWalls();
    
    // Columnas
    createColumns();
    
    // Techo
    createCeiling();
    
    // Señalización de seguridad
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

// ==================== SEÑALES DE SEGURIDAD ====================
function createSafetySigns() {
    const signs = [
        { pos: [-70, 3, -70], text: '🚨 ALARMA', color: 0xe74c3c },
        { pos: [70, 3, -70], text: '⚠️ RIESGO', color: 0xf39c12 },
        { pos: [-70, 3, 70], text: '🚫 PROHIBIDO', color: 0xe74c3c },
        { pos: [70, 3, 70], text: '🦺 EPP', color: 0x3498db },
        { pos: [0, 3, -70], text: '🚪 SALIDA', color: 0x27ae60 },
        { pos: [0, 3, 70], text: '🚪 SALIDA', color: 0x27ae60 },
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
    
    // Línea decorativa
    ctx.fillStyle = '#10B981';
    ctx.fillRect(48, 160, 160, 8);
    
    const texture = new THREE.CanvasTexture(canvas);
    texture.needsUpdate = true;
    return texture;
}

function createWorker() {
    worker = new THREE.Group();
    
    // Colores INTEP
    const greenDark = 0x059669;
    const greenLight = 0x10B981;
    
    const helmetMat = new THREE.MeshStandardMaterial({ color: 0xf1c40f });
    const vestMat = new THREE.MeshStandardMaterial({ color: greenDark });
    const vestAccentMat = new THREE.MeshStandardMaterial({ color: greenLight });
    const pantsMat = new THREE.MeshStandardMaterial({ color: 0x1a1a1a }); // Pantalón negro
    const bootMat = new THREE.MeshStandardMaterial({ color: 0x1a1a1a });
    const skinMat = new THREE.MeshStandardMaterial({ color: 0xf5cba7 });
    const gloveMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const stripeMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
    const goggleMat = new THREE.MeshStandardMaterial({ color: 0x3498db, transparent: true, opacity: 0.6 });
    
    // ===== PIERNAS =====
    // Pierna izquierda
    workerParts.leftThigh = new THREE.Group();
    
    const leftThighMesh = new THREE.Mesh(
        new THREE.CylinderGeometry(0.08, 0.07, 0.35, 12),
        pantsMat
    );
    leftThighMesh.position.y = -0.175;
    workerParts.leftThigh.add(leftThighMesh);
    
    workerParts.leftShin = new THREE.Group();
    const leftShinMesh = new THREE.Mesh(
        new THREE.CylinderGeometry(0.06, 0.05, 0.35, 12),
        pantsMat
    );
    leftShinMesh.position.y = -0.175;
    workerParts.leftShin.add(leftShinMesh);
    
    // Pie/bota
    workerParts.leftFoot = new THREE.Group();
    const leftFootMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.11, 0.06, 0.2),
        bootMat
    );
    leftFootMesh.position.set(0, -0.03, 0.03);
    workerParts.leftFoot.add(leftFootMesh);
    workerParts.leftFoot.position.y = -0.35;
    workerParts.leftShin.add(workerParts.leftFoot);
    
    workerParts.leftShin.position.y = -0.35;
    workerParts.leftThigh.add(workerParts.leftShin);
    
    workerParts.leftThigh.position.set(-0.08, 0.6, 0);
    worker.add(workerParts.leftThigh);
    
    // Pierna derecha
    workerParts.rightThigh = new THREE.Group();
    
    const rightThighMesh = new THREE.Mesh(
        new THREE.CylinderGeometry(0.08, 0.07, 0.35, 12),
        pantsMat
    );
    rightThighMesh.position.y = -0.175;
    workerParts.rightThigh.add(rightThighMesh);
    
    workerParts.rightShin = new THREE.Group();
    const rightShinMesh = new THREE.Mesh(
        new THREE.CylinderGeometry(0.06, 0.05, 0.35, 12),
        pantsMat
    );
    rightShinMesh.position.y = -0.175;
    workerParts.rightShin.add(rightShinMesh);
    
    workerParts.rightFoot = new THREE.Group();
    const rightFootMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.11, 0.06, 0.2),
        bootMat
    );
    rightFootMesh.position.set(0, -0.03, 0.03);
    workerParts.rightFoot.add(rightFootMesh);
    workerParts.rightFoot.position.y = -0.35;
    workerParts.rightShin.add(workerParts.rightFoot);
    
    workerParts.rightShin.position.y = -0.35;
    workerParts.rightThigh.add(workerParts.rightShin);
    
    workerParts.rightThigh.position.set(0.08, 0.6, 0);
    worker.add(workerParts.rightThigh);
    
    // ===== TORSO =====
    workerParts.torso = new THREE.Group();
    
    // Pecho (parte frontal verde)
    const torsoMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.4, 0.5, 0.25),
        vestMat
    );
    workerParts.torso.add(torsoMesh);
    
    // Espalda con texto INTEP
    const intepTexture = createIntepTexture();
    const backMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.35, 0.4, 0.05),
        new THREE.MeshStandardMaterial({ 
            map: intepTexture,
            side: THREE.DoubleSide
        })
    );
    backMesh.position.z = -0.13;
    workerParts.torso.add(backMesh);
    
    // Franjas decorativas verdes claras
    const stripeGeo = new THREE.BoxGeometry(0.41, 0.03, 0.26);
    [-0.12, 0.12].forEach(y => {
        const stripe = new THREE.Mesh(stripeGeo, vestAccentMat);
        stripe.position.y = y;
        workerParts.torso.add(stripe);
    });
    
    // Cinturón negro
    const beltMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.41, 0.04, 0.26),
        new THREE.MeshStandardMaterial({ color: 0x1a1a1a })
    );
    beltMesh.position.y = -0.22;
    workerParts.torso.add(beltMesh);
    
    // Hebilla del cinturón
    const buckleMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.06, 0.05, 0.04),
        new THREE.MeshStandardMaterial({ color: 0xc0c0c0, metalness: 0.8 })
    );
    buckleMesh.position.set(0, -0.22, 0.14);
    workerParts.torso.add(buckleMesh);
    
    workerParts.torso.position.y = 1.0;
    worker.add(workerParts.torso);
    
    // ===== BRAZOS =====
    // Brazo izquierdo
    workerParts.leftArm = new THREE.Group();
    
    const leftUpperArm = new THREE.Mesh(
        new THREE.CylinderGeometry(0.05, 0.06, 0.25, 12),
        skinMat
    );
    leftUpperArm.position.y = -0.125;
    workerParts.leftArm.add(leftUpperArm);
    
    workerParts.leftForearm = new THREE.Group();
    const leftForearmMesh = new THREE.Mesh(
        new THREE.CylinderGeometry(0.04, 0.05, 0.22, 12),
        skinMat
    );
    leftForearmMesh.position.y = -0.11;
    workerParts.leftForearm.add(leftForearmMesh);
    
    // Mano/guante
    const leftHandMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.06, 0.08, 0.04),
        gloveMat
    );
    leftHandMesh.position.y = -0.18;
    workerParts.leftForearm.add(leftHandMesh);
    
    workerParts.leftForearm.position.y = -0.25;
    workerParts.leftArm.add(workerParts.leftForearm);
    
    workerParts.leftArm.position.set(-0.22, 0.12, 0);
    workerParts.torso.add(workerParts.leftArm);
    
    // Brazo derecho
    workerParts.rightArm = new THREE.Group();
    
    const rightUpperArm = new THREE.Mesh(
        new THREE.CylinderGeometry(0.05, 0.06, 0.25, 12),
        skinMat
    );
    rightUpperArm.position.y = -0.125;
    workerParts.rightArm.add(rightUpperArm);
    
    workerParts.rightForearm = new THREE.Group();
    const rightForearmMesh = new THREE.Mesh(
        new THREE.CylinderGeometry(0.04, 0.05, 0.22, 12),
        skinMat
    );
    rightForearmMesh.position.y = -0.11;
    workerParts.rightForearm.add(rightForearmMesh);
    
    const rightHandMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.06, 0.08, 0.04),
        gloveMat
    );
    rightHandMesh.position.y = -0.18;
    workerParts.rightForearm.add(rightHandMesh);
    
    workerParts.rightForearm.position.y = -0.25;
    workerParts.rightArm.add(workerParts.rightForearm);
    
    workerParts.rightArm.position.set(0.22, 0.12, 0);
    workerParts.torso.add(workerParts.rightArm);
    
    // ===== CUELLO =====
    const neckMesh = new THREE.Mesh(
        new THREE.CylinderGeometry(0.05, 0.06, 0.08, 12),
        skinMat
    );
    neckMesh.position.y = 0.32;
    workerParts.torso.add(neckMesh);
    
    // ===== CABEZA =====
    workerParts.head = new THREE.Group();
    
    const headMesh = new THREE.Mesh(
        new THREE.SphereGeometry(0.12, 16, 16),
        skinMat
    );
    headMesh.scale.set(1, 1.1, 0.95);
    workerParts.head.add(headMesh);
    
    // Cejas
    [-0.035, 0.035].forEach(x => {
        const browMesh = new THREE.Mesh(
            new THREE.BoxGeometry(0.06, 0.012, 0.015),
            new THREE.MeshStandardMaterial({ color: 0x5d4e37 })
        );
        browMesh.position.set(x, 0.03, 0.1);
        workerParts.head.add(browMesh);
    });
    
    // Ojos
    [-0.035, 0.035].forEach(x => {
        const eyeMesh = new THREE.Mesh(
            new THREE.SphereGeometry(0.015, 8, 8),
            new THREE.MeshStandardMaterial({ color: 0xffffff })
        );
        eyeMesh.position.set(x, 0.015, 0.1);
        workerParts.head.add(eyeMesh);
        
        const pupilMesh = new THREE.Mesh(
            new THREE.SphereGeometry(0.007, 6, 6),
            new THREE.MeshStandardMaterial({ color: 0x1a1a1a })
        );
        pupilMesh.position.set(x, 0.015, 0.115);
        workerParts.head.add(pupilMesh);
    });
    
    // Nariz
    const noseMesh = new THREE.Mesh(
        new THREE.ConeGeometry(0.01, 0.02, 6),
        skinMat
    );
    noseMesh.position.set(0, -0.015, 0.11);
    noseMesh.rotation.x = Math.PI / 2;
    workerParts.head.add(noseMesh);
    
    // Boca
    const mouthMesh = new THREE.Mesh(
        new THREE.BoxGeometry(0.03, 0.008, 0.012),
        new THREE.MeshStandardMaterial({ color: 0x8b4513 })
    );
    mouthMesh.position.set(0, -0.05, 0.1);
    workerParts.head.add(mouthMesh);
    
    // Orejas
    [-0.12, 0.12].forEach(x => {
        const earMesh = new THREE.Mesh(
            new THREE.SphereGeometry(0.02, 6, 6),
            skinMat
        );
        earMesh.scale.set(0.5, 1, 0.7);
        earMesh.position.set(x, 0, 0);
        workerParts.head.add(earMesh);
    });
    
    workerParts.head.position.y = 0.48;
    workerParts.torso.add(workerParts.head);
    
    // ===== CASCO =====
    workerParts.helmet = new THREE.Group();
    
    const helmetBody = new THREE.Mesh(
        new THREE.SphereGeometry(0.135, 12, 12, 0, Math.PI * 2, 0, Math.PI * 0.5),
        helmetMat
    );
    helmetBody.position.y = 0.01;
    workerParts.helmet.add(helmetBody);
    
    const helmetBrim = new THREE.Mesh(
        new THREE.TorusGeometry(0.13, 0.012, 6, 16, Math.PI),
        helmetMat
    );
    helmetBrim.position.y = -0.01;
    helmetBrim.rotation.x = Math.PI / 2;
    workerParts.helmet.add(helmetBrim);
    
    workerParts.helmet.position.y = 0.58;
    workerParts.head.add(workerParts.helmet);
    
    // ===== GAFAS =====
    const gogglesGroup = new THREE.Group();
    
    const gogFrame = new THREE.Mesh(
        new THREE.BoxGeometry(0.2, 0.05, 0.025),
        new THREE.MeshStandardMaterial({ color: 0x333333 })
    );
    gogglesGroup.add(gogFrame);
    
    const lensGeo = new THREE.BoxGeometry(0.07, 0.04, 0.012);
    [-0.05, 0.05].forEach(x => {
        const lens = new THREE.Mesh(lensGeo, goggleMat);
        lens.position.set(x, 0, 0.015);
        gogglesGroup.add(lens);
    });
    
    gogglesGroup.position.set(0, 0.025, 0.1);
    workerParts.head.add(gogglesGroup);
    
    // Posición inicial
    worker.position.set(0, 0, 15);
    scene.add(worker);
}

function createHand(gloveMat) {
    const hand = new THREE.Group();
    
    const palm = new THREE.Mesh(new THREE.BoxGeometry(0.07, 0.05, 0.035), gloveMat);
    hand.add(palm);
    
    // Dedos
    const fingerGeo = new THREE.CylinderGeometry(0.008, 0.01, 0.045, 8);
    [[-0.028, -0.05], [-0.01, -0.055], [0.01, -0.055], [0.028, -0.05]].forEach(pos => {
        const finger = new THREE.Mesh(fingerGeo, gloveMat);
        finger.position.set(pos[0], pos[1], 0);
        hand.add(finger);
    });
    
    // Pulgar
    const thumb = new THREE.Mesh(new THREE.CylinderGeometry(0.01, 0.012, 0.03, 8), gloveMat);
    thumb.position.set(-0.04, -0.015, 0.015);
    thumb.rotation.z = Math.PI / 4;
    hand.add(thumb);
    
    return hand;
}

// Animación de caminata
function animateWorker() {
    if (!worker || !gameStarted || gamePaused) return;
    
    const isMoving = keys['w'] || keys['a'] || keys['s'] || keys['d'];
    
    if (isMoving) {
        walkCycle += 0.15;
        const phase = walkCycle;
        
        // Piernas - movimiento pendular
        const legSwing = Math.sin(phase) * 0.35;
        const kneeBend = Math.max(0, Math.sin(phase)) * 0.4;
        
        workerParts.leftThigh.rotation.x = legSwing;
        workerParts.leftShin.rotation.x = kneeBend;
        
        workerParts.rightThigh.rotation.x = -legSwing;
        workerParts.rightShin.rotation.x = Math.max(0, Math.sin(phase + Math.PI)) * 0.4;
        
        // Brazos - contramovimiento
        workerParts.leftArm.rotation.x = -legSwing * 0.6;
        workerParts.rightArm.rotation.x = legSwing * 0.6;
        
        // Rebote al caminar
        worker.position.y = Math.abs(Math.sin(phase * 2)) * 0.015;
        
        // Torso balanceo sutil
        workerParts.torso.rotation.z = Math.sin(phase) * 0.015;
        
    } else {
        // Idle - respiración sutil
        const breathe = Math.sin(Date.now() * 0.002) * 0.003;
        workerParts.torso.scale.y = 1 + breathe;
        
        // Brazos relajados a los lados
        workerParts.leftArm.rotation.x = -0.05;
        workerParts.leftArm.rotation.z = 0.03;
        workerParts.rightArm.rotation.x = -0.05;
        workerParts.rightArm.rotation.z = -0.03;
        
        // Reset piernas
        workerParts.leftThigh.rotation.x = 0;
        workerParts.leftShin.rotation.x = 0;
        workerParts.rightThigh.rotation.x = 0;
        workerParts.rightShin.rotation.x = 0;
        
        worker.position.y = 0;
    }
}

// ==================== ZONAS ====================
function createAllZones() {
    createZoneAlmacen();
    createZoneMaquinas();
    createZoneAlturas();
    createZoneConfinados();
    createZoneElectrico();
    createZoneQuimico();
}

// ==================== ZONA ALMACÉN ====================
function createZoneAlmacen() {
    const zoneGroup = new THREE.Group();
    zoneGroup.userData = { zone: 'almacen', name: 'Almacén' };
    
    // Plataforma de zona
    const platformGeo = new THREE.PlaneGeometry(25, 25);
    const platformMat = new THREE.MeshBasicMaterial({ color: 0xf39c12, transparent: true, opacity: 0.15 });
    const platform = new THREE.Mesh(platformGeo, platformMat);
    platform.rotation.x = -Math.PI / 2;
    platform.position.set(-25, 0.03, -25);
    zoneGroup.add(platform);
    
    // Borde
    const borderGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(25, 0.1, 25));
    const borderMat = new THREE.LineBasicMaterial({ color: 0xf39c12 });
    const border = new THREE.LineSegments(borderGeo, borderMat);
    border.position.set(-25, 0.05, -25);
    zoneGroup.add(border);
    
    // Estanterías
    for (let i = 0; i < 3; i++) {
        createRack(-35, -20 - i * 12, zoneGroup);
        createRack(-20, -20 - i * 12, zoneGroup);
    }
    
    // Pallets en el piso
    const palletColors = [0xe74c3c, 0x3498db, 0x2ecc71, 0x9b59b6];
    const palletPositions = [
        [-30, -15], [-25, -15], [-20, -15],
        [-30, -30], [-25, -30], [-20, -30]
    ];
    
    palletPositions.forEach((pos, i) => {
        createPallet(pos[0], pos[1], palletColors[i % palletColors.length], zoneGroup);
    });
    
    // CARGAS/PELIGROS IDENTIFICABLES
    addDanger({
        position: new THREE.Vector3(-35, 1.5, -25),
        type: 'Caída de objetos',
        description: 'Cajas apiladas sin seguridad en estantería alta',
        probability: 4,
        severity: 3,
        zone: 'almacen',
        mesh: createDangerHighlight(-35, 1.5, -25, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-20, 0.1, -30),
        type: 'Piso resbaloso',
        description: 'Piso mojado sin señalización',
        probability: 3,
        severity: 2,
        zone: 'almacen',
        mesh: createDangerHighlight(-20, 0.1, -30, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-30, 0.1, -35),
        type: 'Obstrucción',
        description: 'Materiales bloqueando el pasillo',
        probability: 4,
        severity: 2,
        zone: 'almacen',
        mesh: createDangerHighlight(-30, 0.1, -35, zoneGroup)
    });
    
    scene.add(zoneGroup);
}

function createRack(x, z, parent) {
    const rackGroup = new THREE.Group();
    const postMat = new THREE.MeshStandardMaterial({ color: 0x95a5a6, metalness: 0.8 });
    const shelfMat = new THREE.MeshStandardMaterial({ color: 0x3498db });
    
    // Postes
    const postGeo = new THREE.BoxGeometry(0.15, 5, 0.15);
    [[-2, -0.5], [2, -0.5], [-2, 0.5], [2, 0.5]].forEach(pos => {
        const post = new THREE.Mesh(postGeo, postMat);
        post.position.set(pos[0], 2.5, pos[1]);
        post.castShadow = true;
        rackGroup.add(post);
    });
    
    // Estantes
    const shelfGeo = new THREE.BoxGeometry(4, 0.08, 1.2);
    [0.8, 1.8, 2.8, 3.8].forEach(y => {
        const shelf = new THREE.Mesh(shelfGeo, shelfMat);
        shelf.position.y = y;
        rackGroup.add(shelf);
    });
    
    rackGroup.position.set(x, 0, z);
    parent.add(rackGroup);
}

function createPallet(x, z, color, parent) {
    const palletGroup = new THREE.Group();
    
    // Base
    const baseGeo = new THREE.BoxGeometry(1.4, 0.12, 1.4);
    const baseMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.06;
    palletGroup.add(base);
    
    // Cajas
    const boxGeo = new THREE.BoxGeometry(1.1, 0.8, 1.1);
    const boxMat = new THREE.MeshStandardMaterial({ color, metalness: 0.1 });
    const box = new THREE.Mesh(boxGeo, boxMat);
    box.position.y = 0.52;
    box.castShadow = true;
    palletGroup.add(box);
    
    palletGroup.position.set(x, 0, z);
    parent.add(palletGroup);
}

// ==================== ZONA MÁQUINAS ====================
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
    
    // Máquinas
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
        type: 'Proyección de partículas',
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
    
    // Botón de emergencia
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
    
    // Línea de vida
    createFallProtection(0, -50, zoneGroup);
    
    // PELIGROS
    addDanger({
        position: new THREE.Vector3(0, 6, -50),
        type: 'Caída en alturas',
        description: 'Trabajador sin arnés en plataforma a 6m de altura',
        probability: 5,
        severity: 4,
        zone: 'alturas',
        mesh: createDangerHighlight(0, 6, -50, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-8, 3, -42),
        type: 'Escalera insegura',
        description: 'Escalera sin aseguramiento ni compañero de sujeción',
        probability: 4,
        severity: 3,
        zone: 'alturas',
        mesh: createDangerHighlight(-8, 3, -42, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(5, 4, -50),
        type: 'Sin línea de vida',
        description: 'Ausencia de punto de anclaje para sistema anticaídas',
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
    
    // Peldaños
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

// ==================== ZONA ELÉCTRICA ====================
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

// ==================== ZONA QUÍMICA ====================
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
    
    // Estantes化学品
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
        description: 'Tambor de químico sin bandeja de contención',
        probability: 3,
        severity: 3,
        zone: 'quimico',
        mesh: createDangerHighlight(-50, 0.5, 30, zoneGroup)
    });
    
    addDanger({
        position: new THREE.Vector3(-55, 0.5, 30),
        type: 'Sin EPP adecuado',
        description: 'Trabajador manipulando ácidos sin guantes de neopreno',
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

// ==================== SISTEMA DE PELIGROS ====================
function addDanger(dangerData) {
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
        keys[e.key.toLowerCase()] = true;
        
        if (e.key === 'v' && gameStarted && !gamePaused && !gameEnded) {
            nextCameraMode();
        }
        if (e.key === '1' && gameStarted) setCameraMode('follow');
        if (e.key === '2' && gameStarted) setCameraMode('free');
        if (e.key === '3' && gameStarted) setCameraMode('first');
        if ((e.key === '+' || e.key === '=') && gameStarted) adjustZoom(-3);
        if ((e.key === '-' || e.key === '_') && gameStarted) adjustZoom(3);
        if (e.key === 'Escape' && gameStarted) togglePause();
    });
    
    window.addEventListener('keyup', (e) => {
        keys[e.key.toLowerCase()] = false;
    });
    
    // Mouse
    canvas.addEventListener('mousedown', (e) => {
        if (e.button === 0 && gameStarted && !gamePaused && !gameEnded) {
            handleClick(e);
        }
        if (e.button === 0) {
            isDragging = true;
            previousMouse = { x: e.clientX, y: e.clientY };
        }
    });
    
    canvas.addEventListener('mousemove', (e) => {
        if (isDragging && cameraMode === 'free') {
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
        if (gameStarted && !gamePaused && !gameEnded) {
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
    
    // Verificar si hizo clic en un peligro
    scene.traverse((object) => {
        if (object.userData && object.userData.isDanger) {
            const intersects = raycaster.intersectObject(object, true);
            if (intersects.length > 0) {
                playSoundSelect();
                selectDanger(object.parent.userData?.dangerData || object.parent.parent.userData?.dangerData);
            }
        }
    });
}

function selectDanger(danger) {
    if (!danger || danger.identified) return;
    
    currentDanger = danger;
    showEvaluationPanel(danger);
}

function showEvaluationPanel(danger) {
    const panel = document.getElementById('evaluationPanel');
    const dangerName = document.getElementById('dangerName');
    
    dangerName.textContent = danger.type;
    panel.classList.add('visible');
    
    // Resetear selections
    selectedProbability = 0;
    selectedSeverity = 0;
    document.querySelectorAll('.prob-btn').forEach(btn => btn.classList.remove('selected'));
    document.querySelectorAll('.sev-btn').forEach(btn => btn.classList.remove('selected'));
    document.getElementById('riskLevel').textContent = '-';
    document.getElementById('riskLevel').className = 'result-value';
}

function selectProbability(value) {
    selectedProbability = value;
    document.querySelectorAll('.prob-btn').forEach(btn => {
        btn.classList.toggle('selected', parseInt(btn.dataset.value) === value);
    });
    updateRiskLevel();
}

function selectSeverity(value) {
    selectedSeverity = value;
    document.querySelectorAll('.sev-btn').forEach(btn => {
        btn.classList.toggle('selected', parseInt(btn.dataset.value) === value);
    });
    updateRiskLevel();
}

function updateRiskLevel() {
    if (selectedProbability === 0 || selectedSeverity === 0) return;
    
    const risk = selectedProbability * selectedSeverity;
    const levelEl = document.getElementById('riskLevel');
    
    let level, className;
    if (risk <= 4) { level = 'ACEPTABLE'; className = 'acceptable'; }
    else if (risk <= 9) { level = 'BAJO'; className = 'low'; }
    else if (risk <= 16) { level = 'MEDIO'; className = 'medium'; }
    else if (risk <= 24) { level = 'ALTO'; className = 'high'; }
    else { level = 'CRÍTICO'; className = 'critical'; }
    
    levelEl.textContent = level;
    levelEl.className = 'result-value ' + className;
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
    
    const userRisk = selectedProbability * selectedSeverity;
    const correctRisk = currentDanger.probability * currentDanger.severity;
    
    let points = 0;
    let accuracy = Math.abs(userRisk - correctRisk);
    
    if (accuracy === 0) {
        points = 100;
        showNotification('¡Excelente! Evaluación correcta', 'success');
        playSoundSuccess();
        currentDanger.identified = true;
        dangersIdentified++;
        updateProgress();
        checkAchievements();
    } else if (accuracy <= 2) {
        points = 50;
        showNotification('Casi! Valoración cercana', 'warning');
        playSoundWarning();
        currentDanger.identified = true;
        dangersIdentified++;
        updateProgress();
    } else {
        points = 0;
        showNotification('Incorrecto. El valor correcto era: ' + correctRisk, 'error');
        playSoundError();
    }
    
    score += points;
    document.getElementById('scoreValue').textContent = score;
    
    document.getElementById('evaluationPanel').classList.remove('visible');
    currentDanger = null;
    
    // Verificar si completó todos los peligros
    if (dangersIdentified >= totalDangers) {
        endGame();
    }
}

function updateProgress() {
    const progress = (dangersIdentified / totalDangers) * 100;
    document.getElementById('dangerProgress').style.width = progress + '%';
    document.getElementById('dangerCount').textContent = dangersIdentified + '/' + totalDangers;
}

function checkAchievements() {
    // Lógica de logros
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
    document.getElementById('achievementName').textContent = name + ' - ' + desc;
    popup.classList.add('visible');
    
    setTimeout(() => {
        popup.classList.remove('visible');
    }, 3000);
}

function showNotification(message, type = 'info') {
    const container = document.getElementById('notifications');
    const notification = document.createElement('div');
    notification.className = 'notification ' + type;
    notification.textContent = message;
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// ==================== CÁMARA ====================
function nextCameraMode() {
    const idx = cameraModes.indexOf(cameraMode);
    setCameraMode(cameraModes[(idx + 1) % cameraModes.length]);
}

function setCameraMode(mode) {
    cameraMode = mode;
    camera.fov = mode === 'first' ? 90 : 75;
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
    officeScene.background = new THREE.Color(0x2c3e50);
    
    // Cámara
    officeCamera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    officeCamera.position.set(0, 2, 5);
    officeCamera.lookAt(0, 1, 0);
    
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
    officeRenderer.setClearColor(0x2c3e50, 1);
    
    // Luces de oficina
    const ambient = new THREE.AmbientLight(0xffffff, 0.6);
    officeScene.add(ambient);
    
    const spotLight = new THREE.SpotLight(0xffffff, 0.8);
    spotLight.position.set(0, 4, 2);
    spotLight.castShadow = true;
    officeScene.add(spotLight);
    
    // Piso de oficina
    const floorGeo = new THREE.PlaneGeometry(10, 10);
    const floorMat = new THREE.MeshStandardMaterial({ color: 0x34495e });
    const floor = new THREE.Mesh(floorGeo, floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = 0;
    officeScene.add(floor);
    
    // Paredes
    const wallMat = new THREE.MeshStandardMaterial({ color: 0xecf0f1, side: THREE.DoubleSide });
    
    // Pared atrás
    const backWall = new THREE.Mesh(new THREE.PlaneGeometry(10, 4), wallMat);
    backWall.position.set(0, 2, -3);
    officeScene.add(backWall);
    
    // Paredes laterales
    const leftWall = new THREE.Mesh(new THREE.PlaneGeometry(6, 4), wallMat);
    leftWall.position.set(-5, 2, 0);
    leftWall.rotation.y = Math.PI / 2;
    officeScene.add(leftWall);
    
    const rightWall = new THREE.Mesh(new THREE.PlaneGeometry(6, 4), wallMat);
    rightWall.position.set(5, 2, 0);
    rightWall.rotation.y = -Math.PI / 2;
    officeScene.add(rightWall);
    
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
    
    // Animación idle
    animateOfficeWorker();
    
    officeWorker.position.set(0, 0, 0);
    officeScene.add(officeWorker);
}

function animateOfficeWorker() {
    if (!officeWorker) return;
    
    // Respiración
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
            new THREE.BoxGeometry(0.6, 1.2, 0.5),
            lockerMat
        );
        locker.position.set(-2 + i * 0.7, 0.6, -2.5);
        lockerGroup.add(locker);
    }
    officeScene.add(lockerGroup);
    
    // Banco de equipamiento
    const benchGroup = new THREE.Group();
    const benchMat = new THREE.MeshStandardMaterial({ color: 0x8b4513 });
    
    const benchTop = new THREE.Mesh(
        new THREE.BoxGeometry(3, 0.1, 1),
        benchMat
    );
    benchTop.position.y = 0.9;
    benchGroup.add(benchTop);
    
    const benchLeg = new THREE.BoxGeometry(0.1, 0.9, 0.9);
    [-1.4, 1.4].forEach(x => {
        const leg = new THREE.Mesh(benchLeg, benchMat);
        leg.position.set(x, 0.45, 0);
        benchGroup.add(leg);
    });
    
    benchGroup.position.set(2, 0, -1);
    officeScene.add(benchGroup);
    
    // EPP en el banco (items cliqueables)
    const eppPositions = [
        { type: 'casco', pos: [1.5, 1.1, -1], icon: '⛑️', color: 0xf1c40f },
        { type: 'gafas', pos: [1.8, 1.1, -1], icon: '🥽', color: 0x3498db },
        { type: 'tapabocas', pos: [2.1, 1.1, -1], icon: '😷', color: 0xffffff },
        { type: 'chaleco', pos: [2.4, 1.1, -1], icon: '🦺', color: 0xf39c12 },
        { type: 'guantes', pos: [1.5, 1.1, -0.6], icon: '🧤', color: 0x8b4513 },
        { type: 'botas', pos: [1.8, 1.1, -0.6], icon: '👢', color: 0x1a1a1a }
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
    const sign = new THREE.Mesh(new THREE.BoxGeometry(2, 0.6, 0.05), signMat);
    sign.position.y = 3;
    signGroup.add(sign);
    
    const postMat = new THREE.MeshStandardMaterial({ color: 0x7f8c8d });
    const post = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 2, 8), postMat);
    post.position.y = 1.5;
    signGroup.add(post);
    
    signGroup.position.set(0, 0, -2.9);
    officeScene.add(signGroup);
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
    
    // Señal de salida
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
            item.position.y = 1.1 + Math.sin(Date.now() * 0.003 + i) * 0.05;
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
    document.getElementById(`status-${type}`).textContent = '✓';
    
    // Actualizar preview
    const preview = document.getElementById(`preview-${type}`);
    preview.classList.add('equipped');
    
    // Ocultar EPP en la escena 3D
    const eppMesh = eppItems.find(epp => epp.userData.eppType === type);
    if (eppMesh) {
        eppMesh.visible = false;
    }
    
    // Verificar si todos están equipados
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
        enterBtn.textContent = '✓ Entrar al Área Industrial';
        
        // Actualizar preview del cuerpo
        document.querySelector('.preview-body').classList.add('ready');
        
        showNotification('¡EPP completo! Ya puedes entrar al área industrial', 'success');
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
    document.getElementById('infoPanel').classList.add('visible');
    
    showNotification('Recuerda: Usa correctamente tu EPP en todo momento', 'info');
}

function updateWorkerEPP() {
    if (!worker) return;
    
    // El trabajador ya tiene EPP en el modelo, así que solo ajustamos la visibilidad
    // según lo que se haya equipado
    // En este caso, como todos deben estar equipados para entrar, todos serán visibles
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
}

function restartSimulation() {
    location.reload();
}

function quitSimulation() {
    location.reload();
}

function showTutorial() {
    showNotification('Tutorial: Haz clic en los puntos amarillos para identificar peligros', 'info');
}

function closeInfoPanel() {
    document.getElementById('infoPanel').classList.remove('visible');
}

function selectZone(zone) {
    playSoundClick();
    currentZone = zone;
    
    // Actualizar botón activo
    document.querySelectorAll('.zone-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.zone === zone);
    });
    
    // Actualizar indicador de zona
    const zoneNames = {
        almacen: { name: 'Almacén', icon: '📦' },
        maquinas: { name: 'Máquinas', icon: '⚙️' },
        alturas: { name: 'Trabajo en Alturas', icon: '🪜' },
        confinados: { name: 'Espacios Confinados', icon: '🕳️' },
        electrico: { name: 'Zona Eléctrica', icon: '⚡' },
        quimico: { name: 'Zona Química', icon: '☢️' }
    };
    
    document.getElementById('currentZoneName').textContent = zoneNames[zone].name;
    document.getElementById('currentZoneIcon').textContent = zoneNames[zone].icon;
    
    // Mover cámara a la zona
    const zonePositions = {
        almacen: new THREE.Vector3(-25, 15, -25),
        maquinas: new THREE.Vector3(25, 15, -25),
        alturas: new THREE.Vector3(0, 20, -50),
        confinados: new THREE.Vector3(-50, 10, 0),
        electrico: new THREE.Vector3(50, 15, 25),
        quimico: new THREE.Vector3(-50, 15, 25)
    };
    
    cameraTarget.copy(zonePositions[zone]);
}

function endGame() {
    gameEnded = true;
    
    document.getElementById('finalScore').textContent = score;
    document.getElementById('statIdentified').textContent = dangersIdentified;
    document.getElementById('statCorrect').textContent = Math.floor(score / 100);
    document.getElementById('statAccuracy').textContent = Math.round((dangersIdentified / totalDangers) * 100) + '%';
    
    // Mostrar logros
    const achievementsList = document.getElementById('achievementsList');
    achievementsList.innerHTML = '';
    achievements.forEach(id => {
        const badge = document.createElement('span');
        badge.className = 'achievement-badge earned';
        badge.textContent = id;
        achievementsList.appendChild(badge);
    });
    
    document.getElementById('resultsScreen').classList.add('visible');
}

function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
}

// ==================== ANIMACIÓN ====================
function animate() {
    requestAnimationFrame(animate);
    
    if (!gamePaused && gameStarted) {
        // Mover trabajador con teclas
        if (worker) {
            const moveSpeed = 0.25;
            let isMoving = false;
            
            if (keys['w']) { worker.position.z -= moveSpeed; worker.rotation.y = 0; isMoving = true; }
            if (keys['s']) { worker.position.z += moveSpeed; worker.rotation.y = Math.PI; isMoving = true; }
            if (keys['a']) { worker.position.x -= moveSpeed; worker.rotation.y = Math.PI / 2; isMoving = true; }
            if (keys['d']) { worker.position.x += moveSpeed; worker.rotation.y = -Math.PI / 2; isMoving = true; }
            
            // Limitar área
            worker.position.x = Math.max(-70, Math.min(70, worker.position.x));
            worker.position.z = Math.max(-70, Math.min(70, worker.position.z));
        }
        
        // Animar trabajador
        animateWorker();
        
        // Actualizar cámara
        updateCamera();
        
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
            const targetX = worker.position.x + Math.sin(cameraTheta) * cameraDistance;
            const targetZ = worker.position.z + Math.cos(cameraTheta) * cameraDistance;
            camera.position.x += (targetX - camera.position.x) * 0.05;
            camera.position.z += (targetZ - camera.position.z) * 0.05;
            camera.position.y = cameraDistance * 0.6;
            camera.lookAt(worker.position.x, 1, worker.position.z);
            break;
            
        case 'free':
            const x = cameraTarget.x + Math.sin(cameraTheta) * Math.cos(cameraPhi) * cameraDistance;
            const y = cameraTarget.y + Math.sin(cameraPhi) * cameraDistance;
            const z = cameraTarget.z + Math.cos(cameraTheta) * Math.cos(cameraPhi) * cameraDistance;
            camera.position.set(x, y, z);
            camera.lookAt(cameraTarget);
            break;
            
        case 'first':
            camera.position.set(
                worker.position.x,
                1.7,
                worker.position.z - 1
            );
            const lookDir = new THREE.Vector3(
                Math.sin(cameraTheta),
                0,
                Math.cos(cameraTheta)
            );
            camera.lookAt(worker.position.clone().add(lookDir));
            break;
    }
}

// Iniciar cuando carga la página
window.addEventListener('load', init);

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
