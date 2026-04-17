// ==================== VARIABLES GLOBALES ====================
let scene, camera, renderer;
let playerGroup;
let pallets = [];
let racks = [];
let zones = [];
let warehouseObjects = [];
let warehouseLightRefs = [];
let coldZoneParticles = null;
let collisionObjects = [];
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
let currentMission = null;       // number: which mission is active
let missionTasks = [];           // array: tasks for the current mission
let gamePaused = false;          // true when quiz overlay is showing
let visitTaskTriggered = false;  // guard: evita que una tarea visit se dispare múltiples frames
let quizCallback = null;         // function to call after quiz answered correctly
let quizzesCorrect = 0;         // count of quizzes answered correctly
let quizzesTotal = 0;           // count of total quizzes shown

// ==================== SISTEMA DE INVENTARIO REAL ====================
let inventory = [];             // Array de productos con ubicaciones
let stockLocations = {};        // Mapa de ubicaciones -> producto
let pickingQueue = [];          // Cola de picking pendiente
let inventoryLog = [];          // Log de movimientos para trazabilidad

// ==================== SISTEMA DE TURNOS Y PRIORIDADES ====================
let shiftStartTime = null;      // Hora de inicio del turno
let shiftTimer = null;          // Timer del turno
let shiftDuration = 480;        // Duración del turno en segundos (8 horas)
let pendingOrders = [];         // Órdenes pendientes
let completedOrders = [];       // Órdenes completadas
let efficiencyMetrics = {       // Métricas de eficiencia
    itemsPicked: 0,
    itemsCorrect: 0,
    timeSpent: 0,
    errors: 0,
    pickingRate: 0
};
let currentPriority = 'normal'; // Prioridad actual (alta, normal, baja)
let timePenalty = 0;            // Penalización por tiempo excedido

// ==================== FUNCIONES DE INVENTARIO Y TURNOS ====================
function initInventorySystem() {
    // Inicializar ubicaciones de inventario basadas en racks existentes
    inventory = [];
    stockLocations = {};
    pickingQueue = [];
    inventoryLog = [];
    
    // Crear ubicaciones para cada rack
    racks.forEach((rack, rackIndex) => {
        if (!rack.location) return;
        
        const rackCode = rack.location; // Ej: A-01
        const width = rack.width || 4;
        const height = rack.height || 4;
        const depth = rack.depth || 1.2;
        
        // Crear ubicaciones por nivel (cada 1m de altura)
        for (let level = 1; level <= height; level++) {
            // Dividir ancho en posiciones (cada 1m aprox)
            for (let position = 1; position <= width; position++) {
                const locationCode = `${rackCode}-${level.toString().padStart(2, '0')}-${position.toString().padStart(2, '0')}`;
                const location = {
                    code: locationCode,
                    rackIndex: rackIndex,
                    x: rack.x + (position - width/2 - 0.5),
                    y: level, // altura en metros
                    z: rack.z,
                    occupied: false,
                    productId: null,
                    palletRef: null,
                    lastUpdated: new Date()
                };
                stockLocations[locationCode] = location;
                
                // Agregar marcador visual (opcional)
                addLocationMarker(location);
            }
        }
    });
    
    console.log(`Sistema de inventario inicializado: ${Object.keys(stockLocations).length} ubicaciones creadas`);
}

function addLocationMarker(location) {
    // Crear un marcador visual pequeño para debug
    const markerGeo = new THREE.BoxGeometry(0.05, 0.05, 0.05);
    const markerMat = new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.3 });
    const marker = new THREE.Mesh(markerGeo, markerMat);
    marker.position.set(location.x, location.y, location.z);
    marker.userData = { locationCode: location.code };
    scene.add(marker);
    return marker;
}

function registerProduct(productData) {
    const product = {
        id: `PROD-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
        sku: productData.sku || `SKU-${Math.floor(Math.random() * 10000)}`,
        name: productData.name,
        category: productData.category || 'general',
        quantity: productData.quantity || 1,
        unit: productData.unit || 'units',
        location: productData.location || null,
        priority: productData.priority || 'normal',
        dateReceived: productData.dateReceived || new Date(),
        expiryDate: productData.expiryDate || null,
        status: 'in_stock'
    };
    
    inventory.push(product);
    
    // Si tiene ubicación asignada, ocuparla
    if (product.location && stockLocations[product.location]) {
        stockLocations[product.location].occupied = true;
        stockLocations[product.location].productId = product.id;
        stockLocations[product.location].palletRef = productData.palletRef || null;
    }
    
    // Log
    logInventoryAction('REGISTER', product.id, product.location, `Producto registrado: ${product.name}`);
    
    return product;
}

function findAvailableLocation(category = 'general') {
    // Buscar ubicación disponible (simple: primera disponible)
    for (const code in stockLocations) {
        if (!stockLocations[code].occupied) {
            return code;
        }
    }
    return null;
}

function moveProduct(productId, newLocation) {
    const product = inventory.find(p => p.id === productId);
    if (!product) return false;
    
    const oldLocation = product.location;
    
    // Liberar ubicación anterior
    if (oldLocation && stockLocations[oldLocation]) {
        stockLocations[oldLocation].occupied = false;
        stockLocations[oldLocation].productId = null;
        stockLocations[oldLocation].palletRef = null;
    }
    
    // Ocupar nueva ubicación
    if (stockLocations[newLocation]) {
        stockLocations[newLocation].occupied = true;
        stockLocations[newLocation].productId = productId;
        product.location = newLocation;
        product.status = 'in_stock';
        
        logInventoryAction('MOVE', productId, `${oldLocation} -> ${newLocation}`, `Producto movido`);
        return true;
    }
    
    return false;
}

function createPickingOrder(productId, quantity = 1, priority = 'normal') {
    const product = inventory.find(p => p.id === productId);
    if (!product) return null;
    
    const order = {
        id: `ORDER-${Date.now()}`,
        productId: productId,
        productName: product.name,
        sku: product.sku,
        quantity: quantity,
        location: product.location,
        priority: priority,
        status: 'pending',
        createdAt: new Date(),
        assignedTo: null,
        completedAt: null
    };
    
    pickingQueue.push(order);
    
    // Ordenar por prioridad (alta -> normal -> baja)
    pickingQueue.sort((a, b) => {
        const priorityOrder = { 'alta': 0, 'normal': 1, 'baja': 2 };
        return priorityOrder[a.priority] - priorityOrder[b.priority];
    });
    
    logInventoryAction('ORDER_CREATE', productId, order.location, `Orden de picking creada: ${quantity} ${product.name}`);
    
    return order;
}

function completePickingOrder(orderId) {
    const orderIndex = pickingQueue.findIndex(o => o.id === orderId);
    if (orderIndex === -1) return false;
    
    const order = pickingQueue[orderIndex];
    order.status = 'completed';
    order.completedAt = new Date();
    
    // Mover a completadas
    completedOrders.push(order);
    pickingQueue.splice(orderIndex, 1);
    
    // Actualizar inventario
    const product = inventory.find(p => p.id === order.productId);
    if (product) {
        product.quantity -= order.quantity;
        if (product.quantity <= 0) {
            product.status = 'out_of_stock';
            // Liberar ubicación
            if (product.location && stockLocations[product.location]) {
                stockLocations[product.location].occupied = false;
                stockLocations[product.location].productId = null;
            }
        }
    }
    
    // Actualizar métricas
    efficiencyMetrics.itemsPicked += order.quantity;
    efficiencyMetrics.itemsCorrect += order.quantity;
    efficiencyMetrics.timeSpent += 1; // minutos estimados
    
    logInventoryAction('ORDER_COMPLETE', order.productId, order.location, `Orden completada: ${order.quantity} ${order.productName}`);
    
    return true;
}

function logInventoryAction(action, productId, location, description) {
    const logEntry = {
        timestamp: new Date(),
        action: action,
        productId: productId,
        location: location,
        description: description,
        user: 'operator'
    };
    
    inventoryLog.push(logEntry);
    
    // Mantener log limitado
    if (inventoryLog.length > 1000) {
        inventoryLog = inventoryLog.slice(-500);
    }
    
    // Debug en consola
    console.log(`[INVENTARIO] ${new Date().toLocaleTimeString()} - ${action}: ${description}`);
}

function startShift() {
    shiftStartTime = new Date();
    efficiencyMetrics = {
        itemsPicked: 0,
        itemsCorrect: 0,
        timeSpent: 0,
        errors: 0,
        pickingRate: 0
    };
    
    // Crear órdenes iniciales para el turno
    generateInitialOrders();
    updateInventoryMetrics();
    
    // Iniciar timer del turno
    if (shiftTimer) clearInterval(shiftTimer);
    shiftTimer = setInterval(updateShift, 1000); // Actualizar cada segundo
    
    console.log(`Turno iniciado: ${shiftStartTime.toLocaleTimeString()}`);
    showPriorityNotification('Turno iniciado - ¡Buen trabajo!', 'normal');
    
    // Mostrar panel de turno
    updateShiftPanel();
}

function updateShift() {
    if (!shiftStartTime) return;
    
    const now = new Date();
    const elapsedSeconds = Math.floor((now - shiftStartTime) / 1000);
    const remainingSeconds = Math.max(0, shiftDuration - elapsedSeconds);
    
    // Actualizar tiempo restante en HUD
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    
    // Actualizar métricas de eficiencia
    if (efficiencyMetrics.timeSpent > 0) {
        efficiencyMetrics.pickingRate = efficiencyMetrics.itemsPicked / (efficiencyMetrics.timeSpent / 60); // items por hora
    }
    
    // Notificar cuando quede poco tiempo
    if (remainingSeconds === 300) { // 5 minutos
        showPriorityNotification('5 minutos restantes en el turno', 'high');
    }
    if (remainingSeconds === 60) { // 1 minuto
        showPriorityNotification('1 minuto restante - Termina las órdenes urgentes', 'high');
    }
    if (remainingSeconds === 0) {
        endShift();
    }
    
    // Actualizar panel de turno en tiempo real
    updateShiftPanel();
}

function generateInitialOrders() {
    // Generar órdenes de picking iniciales basadas en inventario
    const sampleProducts = [
        { name: 'Cajas de Cartón 30x30', sku: 'CB-3030', category: 'empaque', quantity: 50 },
        { name: 'Baterías AA', sku: 'BAT-AA', category: 'electronica', quantity: 100 },
        { name: 'Agua Mineral 500ml', sku: 'H2O-500', category: 'bebidas', quantity: 200 },
        { name: 'Arroz 1kg', sku: 'FOOD-RICE', category: 'alimentos', quantity: 80 },
        { name: 'Detergente Líquido', sku: 'CLEAN-DET', category: 'limpieza', quantity: 60 }
    ];
    
    // Registrar productos en inventario
    sampleProducts.forEach((prod, index) => {
        const location = findAvailableLocation(prod.category);
        if (location) {
            const product = registerProduct({
                ...prod,
                location: location,
                priority: index < 2 ? 'alta' : 'normal'
            });
            
            // Crear órdenes de picking para algunos productos
            if (index < 3) {
                createPickingOrder(product.id, Math.min(10, prod.quantity), product.priority);
            }
        }
    });
    
    console.log(`${pickingQueue.length} órdenes de picking generadas para el turno`);
}

function endShift() {
    if (shiftTimer) {
        clearInterval(shiftTimer);
        shiftTimer = null;
    }
    stopInventoryMetrics();
    
    // Calcular eficiencia final
    const accuracy = efficiencyMetrics.itemsCorrect / Math.max(1, efficiencyMetrics.itemsPicked) * 100;
    const efficiencyScore = Math.min(100, accuracy - (efficiencyMetrics.errors * 5));
    
    // Mostrar reporte
    showPriorityNotification(`Turno finalizado - Eficiencia: ${efficiencyScore.toFixed(1)}%`, 'low');
    
    console.log('=== REPORTE DE TURNO ===');
    console.log(`Items recogidos: ${efficiencyMetrics.itemsPicked}`);
    console.log(`Precisión: ${accuracy.toFixed(1)}%`);
    console.log(`Errores: ${efficiencyMetrics.errors}`);
    console.log(`Tasa de picking: ${efficiencyMetrics.pickingRate.toFixed(1)} items/hora`);
    console.log(`Puntuación de eficiencia: ${efficiencyScore.toFixed(1)}%`);
    
    // Guardar en localStorage para histórico
    saveShiftReport(efficiencyScore);
}

function saveShiftReport(score) {
    const reports = JSON.parse(localStorage.getItem('shiftReports') || '[]');
    reports.push({
        date: new Date().toISOString(),
        score: score,
        metrics: { ...efficiencyMetrics }
    });
    
    // Mantener solo últimos 50 reportes
    if (reports.length > 50) {
        reports.splice(0, reports.length - 50);
    }
    
    localStorage.setItem('shiftReports', JSON.stringify(reports));
}

function updateShiftPanel() {
    const panel = document.getElementById('shiftPanel');
    if (!panel) return;
    
    // Calcular tiempo restante
    if (!shiftStartTime) {
        panel.style.display = 'none';
        return;
    }
    
    const now = new Date();
    const elapsedSeconds = Math.floor((now - shiftStartTime) / 1000);
    const remainingSeconds = Math.max(0, shiftDuration - elapsedSeconds);
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    
    // Actualizar elementos DOM
    document.getElementById('shiftTime').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    document.getElementById('shiftItemsPicked').textContent = efficiencyMetrics.itemsPicked;
    document.getElementById('shiftPickingRate').textContent = 
        `${efficiencyMetrics.pickingRate.toFixed(1)}/h`;
    document.getElementById('shiftErrors').textContent = efficiencyMetrics.errors;
    
    const priorityElement = document.getElementById('shiftPriority');
    priorityElement.textContent = currentPriority.toUpperCase();
    priorityElement.className = `priority-${currentPriority}`;
    
    document.getElementById('shiftPendingOrders').textContent = pickingQueue.length;
    
    // Mostrar panel si está oculto
    if (panel.style.display !== 'block') {
        panel.style.display = 'block';
    }
}

function getInventorySummary() {
    const totalProducts = inventory.length;
    const inStock = inventory.filter(p => p.status === 'in_stock').length;
    const outOfStock = inventory.filter(p => p.status === 'out_of_stock').length;
    const totalLocations = Object.keys(stockLocations).length;
    const occupiedLocations = Object.values(stockLocations).filter(l => l.occupied).length;
    
    return {
        totalProducts,
        inStock,
        outOfStock,
        totalLocations,
        occupiedLocations,
        occupancyRate: (occupiedLocations / totalLocations * 100).toFixed(1)
    };
}

function updateInventoryHUD() {
    // Actualizar HUD con información de inventario
    const summary = getInventorySummary();
    const hudElement = document.getElementById('inventoryDetail');
    if (!hudElement) return;
    
    hudElement.innerHTML = `
        <h4>📦 INVENTARIO</h4>
        <div class="stat-row"><span>Productos:</span><span>${summary.inStock}/${summary.totalProducts}</span></div>
        <div class="stat-row"><span>Ubicaciones:</span><span>${summary.occupiedLocations}/${summary.totalLocations}</span></div>
        <div class="stat-row"><span>Ocupación:</span><span>${summary.occupancyRate}%</span></div>
        <div class="stat-row"><span>Órdenes pendientes:</span><span>${pickingQueue.length}</span></div>
        <div class="stat-row"><span>Prioridad actual:</span><span>${currentPriority.toUpperCase()}</span></div>
    `;
}

function registerPalletAsProduct(pallet, missionData) {
    // Registrar un pallet como producto en el inventario
    const location = findAvailableLocation();
    if (!location) {
        console.warn('No hay ubicaciones disponibles para el pallet');
        return null;
    }
    
    const productName = missionData?.label || `Pallet ${pallet.userData.taskIndex + 1}`;
    const category = determineCategoryFromMission(currentMission);
    
    const product = registerProduct({
        name: productName,
        sku: `PALLET-${Date.now()}-${pallet.userData.taskIndex}`,
        category: category,
        quantity: 8, // 8 cajas por pallet
        unit: 'cajas',
        location: location,
        priority: missionData?.priority || 'normal',
        palletRef: pallet.uuid
    });
    
    // Asociar el pallet con el producto
    pallet.userData.productId = product.id;
    pallet.userData.inventoryLocation = location;
    
    return product;
}

function determineCategoryFromMission(missionNum) {
    const missionCategories = {
        1: 'general',
        2: 'recepcion',
        3: 'cubicaje',
        4: 'contenedores',
        5: 'almacenamiento',
        6: 'clasificacion'
    };
    return missionCategories[missionNum] || 'general';
}

function registerMissionPallets(missionNum) {
    const mission = MISSIONS[missionNum];
    if (!mission || !mission.pallets) return;
    
    mission.pallets.forEach((pData, i) => {
        // Encontrar el pallet correspondiente (ya creado)
        const pallet = pallets.find(p => p.userData.taskIndex === i);
        if (pallet) {
            registerPalletAsProduct(pallet, pData);
        }
    });
    
    console.log(`${mission.pallets.length} pallets registrados en inventario para misión ${missionNum}`);
}

function startMissionWithShift(missionNum) {
    // Iniciar turno para la misión
    startShift();
    
    // Registrar pallets de la misión en inventario
    registerMissionPallets(missionNum);
    
    // Crear órdenes de picking basadas en la misión
    createMissionOrders(missionNum);
    
    // Actualizar HUD inicial
    updateInventoryHUD();
}

function createMissionOrders(missionNum) {
    const mission = MISSIONS[missionNum];
    if (!mission) return;
    
    // Crear órdenes basadas en tareas de la misión
    mission.tasks.forEach((task, index) => {
        if (task.type === 'pickup') {
            // Encontrar el producto correspondiente al pallet
            const pallet = pallets.find(p => p.userData.taskIndex === task.palletIndex);
            if (pallet && pallet.userData.productId) {
                const priority = index === 0 ? 'alta' : 'normal'; // Primera tarea alta prioridad
                createPickingOrder(pallet.userData.productId, 8, priority);
            }
        }
    });
    
    console.log(`Órdenes de picking creadas para misión ${missionNum}`);
}

let inventoryMetricsTimer = null;

function updateInventoryMetrics() {
    // Actualizar métricas de inventario en HUD
    updateInventoryHUD();

    // Sólo reprogramar si el juego está activo
    if (gameStarted && !gameEnded) {
        inventoryMetricsTimer = setTimeout(updateInventoryMetrics, 5000);
    }
}

function stopInventoryMetrics() {
    if (inventoryMetricsTimer) {
        clearTimeout(inventoryMetricsTimer);
        inventoryMetricsTimer = null;
    }
}

// ==================== SISTEMA DE AUDIO ====================
let bgMusic;
let soundPickup, soundDrop, soundCollision, soundComplete;
let musicEnabled = false;
let musicMuted = true;

// Audio Context para generar sonidos procedurales
let audioContext;

// ==================== SISTEMA DE TEXTURAS ====================
let forkHeight = 0.28; // altura actual de las horquillas (0 = piso, 2 = alto)
const FORK_MIN = 0.28;
const FORK_MAX = 3.0;
let forkMoving = false; // si las horquillas están en movimiento

// ==================== FÍSICAS E INERCIA ====================
// playerSpeed: velocidad escalar en el eje local (adelante/atrás). Sin deslizamiento lateral.
let playerSpeed = 0;
let playerVelocityX = 0;  // derivada de playerSpeed, mantenida para animación de ruedas
let playerVelocityZ = 0;
const BASE_SPEED   = 0.18;  // velocidad de crucero
const MAX_SPEED    = 0.28;  // tope absoluto
const REVERSE_RATIO = 0.55; // reversa es 55% de la velocidad de frente
const ACCELERATION = 0.09;  // qué tan rápido alcanza la velocidad objetivo (antes 0.03)
const DECELERATION = 0.14;  // fricción al soltar W/S (antes 0.05)
const BRAKE_FORCE  = 0.25;  // freno al presionar dirección opuesta al movimiento
const TURN_SPEED = 0.05;
let flickerTime = 0;

// Generar texturas procedurales con Canvas
function createConcreteTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const ctx = canvas.getContext('2d');
    
    // Fondo de concreto
    ctx.fillStyle = '#3d5a6c';
    ctx.fillRect(0, 0, 512, 512);
    
    // Patrón de concreto con ruido
    ctx.globalAlpha = 0.05;
    for (let i = 0; i < 20000; i++) {
        ctx.fillStyle = `hsl(${Math.random() * 360}, 20%, ${40 + Math.random() * 30}%)`;
        ctx.fillRect(Math.random() * 512, Math.random() * 512, 2, 2);
    }
    ctx.globalAlpha = 1.0;
    
    // Líneas de tránsito (amarillas)
    ctx.strokeStyle = '#f1c40f';
    ctx.lineWidth = 8;
    ctx.setLineDash([20, 10]);
    
    // Línea central
    ctx.beginPath();
    ctx.moveTo(256, 0);
    ctx.lineTo(256, 512);
    ctx.stroke();
    
    // Líneas horizontales
    ctx.beginPath();
    ctx.moveTo(0, 128);
    ctx.lineTo(512, 128);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(0, 384);
    ctx.lineTo(512, 384);
    ctx.stroke();
    
    ctx.setLineDash([]);
    
    // Flechas direccionales
    ctx.fillStyle = '#f1c40f';
    for (let x = 64; x < 512; x += 128) {
        drawArrow(ctx, x, 256, Math.PI / 2, 20);
    }
    
    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(20, 20); // repetir para cubrir 100x100
    return texture;
}

function drawArrow(ctx, x, y, angle, size) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.beginPath();
    ctx.moveTo(0, -size);
    ctx.lineTo(size, size);
    ctx.lineTo(-size, size);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
}

function createMetalTexture(color = '#34495e') {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');
    
    // Base color
    ctx.fillStyle = color;
    ctx.fillRect(0, 0, 256, 256);
    
    // Brushed metal effect
    ctx.strokeStyle = `rgba(255, 255, 255, 0.1)`;
    ctx.lineWidth = 1;
    for (let i = 0; i < 256; i += 4) {
        ctx.beginPath();
        ctx.moveTo(0, i);
        ctx.lineTo(256, i + Math.sin(i * 0.1) * 3);
        ctx.stroke();
    }
    
    // Screw holes
    ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
    for (let x = 32; x < 256; x += 64) {
        for (let y = 32; y < 256; y += 64) {
            ctx.beginPath();
            ctx.arc(x, y, 3, 0, Math.PI * 2);
            ctx.fill();
        }
    }
    
    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(4, 4);
    return texture;
}

function createWoodTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');
    
    // Wood grain base
    ctx.fillStyle = '#8b4513';
    ctx.fillRect(0, 0, 256, 256);
    
    // Wood grain lines
    ctx.strokeStyle = '#a0522d';
    ctx.lineWidth = 2;
    for (let i = 0; i < 256; i += 8) {
        ctx.beginPath();
        ctx.moveTo(0, i);
        ctx.lineTo(256, i + Math.sin(i * 0.05) * 10);
        ctx.stroke();
    }
    
    // Knots
    ctx.fillStyle = '#5d4037';
    for (let i = 0; i < 8; i++) {
        const x = 32 + Math.random() * 192;
        const y = 32 + Math.random() * 192;
        const r = 4 + Math.random() * 8;
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fill();
    }
    
    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    return texture;
}

function createCardboardTexture(color = '#8b4513') {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');
    
    // Base color
    ctx.fillStyle = color;
    ctx.fillRect(0, 0, 256, 256);
    
    // Corrugated pattern
    ctx.strokeStyle = 'rgba(0, 0, 0, 0.2)';
    ctx.lineWidth = 2;
    for (let i = 0; i < 256; i += 16) {
        ctx.beginPath();
        ctx.moveTo(0, i);
        ctx.lineTo(256, i);
        ctx.stroke();
    }
    
    // Brand/logo placeholder
    ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
    ctx.font = 'bold 24px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('INTEP', 128, 128);
    
    // Barcode simulation
    ctx.fillStyle = '#000';
    for (let i = 0; i < 20; i++) {
        const w = 2 + Math.random() * 4;
        const h = 30 + Math.random() * 40;
        const x = 20 + i * 10;
        ctx.fillRect(x, 200, w, h);
    }
    
    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    return texture;
}

// ==================== ETIQUETAS DE UBICACIÓN ====================
function createLocationLabel(text, backgroundColor = '#2c3e50', textColor = '#ffffff') {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');
    
    // Fondo
    ctx.fillStyle = backgroundColor;
    ctx.fillRect(0, 0, 256, 128);
    
    // Borde
    ctx.strokeStyle = '#f1c40f';
    ctx.lineWidth = 4;
    ctx.strokeRect(2, 2, 252, 124);
    
    // Texto principal
    ctx.fillStyle = textColor;
    ctx.font = 'bold 48px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 128, 64);
    
    // Texto secundario
    ctx.font = '18px Arial';
    ctx.fillStyle = '#bdc3c7';
    ctx.fillText('UBICACIÓN', 128, 100);
    
    const texture = new THREE.CanvasTexture(canvas);
    const material = new THREE.SpriteMaterial({ map: texture });
    const sprite = new THREE.Sprite(material);
    sprite.scale.set(2, 1, 1);
    return sprite;
}

// ==================== PARTÍCULAS ZONA FRÍA ====================
function createColdZoneParticles() {
    const particleCount = 200;
    const particles = new THREE.BufferGeometry();
    const positions = new Float32Array(particleCount * 3);
    const colors = new Float32Array(particleCount * 3);
    
    // Zona fría alrededor de (40, -30)
    const zoneX = 40;
    const zoneZ = -30;
    const zoneRadius = 15;
    
    const color = new THREE.Color(0x87ceeb);
    
    for (let i = 0; i < particleCount; i++) {
        const i3 = i * 3;
        // Posiciones aleatorias dentro de un cilindro (altura 0-10)
        const angle = Math.random() * Math.PI * 2;
        const radius = Math.random() * zoneRadius;
        positions[i3] = zoneX + Math.cos(angle) * radius;
        positions[i3 + 1] = Math.random() * 10; // altura
        positions[i3 + 2] = zoneZ + Math.sin(angle) * radius;
        
        // Colores azulados con variación
        colors[i3] = color.r + (Math.random() * 0.3 - 0.15);
        colors[i3 + 1] = color.g + (Math.random() * 0.3 - 0.15);
        colors[i3 + 2] = color.b + (Math.random() * 0.3 - 0.15);
    }
    
    particles.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    particles.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    
    const material = new THREE.PointsMaterial({
        size: 0.2,
        vertexColors: true,
        transparent: true,
        opacity: 0.6,
        blending: THREE.AdditiveBlending
    });
    
    const particleSystem = new THREE.Points(particles, material);
    scene.add(particleSystem);
    return particleSystem;
}

// ==================== GENERACIÓN DE SONIDOS ====================
// Generar sonido procedural
function generateTone(frequency, duration, type, volume) {
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
    // Melodia de victoria
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

// Musica de fondo procedural (melodia suave que se repite)
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

    // Melodia suave en loop
    const playMelody = () => {
        if (musicMuted || !musicGain) return;

        // Notas musicales suaves (Escala pentatonica)
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

        // Programar siguiente iteracion
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
        icon.textContent = '\uD83D\uDD07';
        btn.classList.remove('playing');
        stopBackgroundMusic();
    } else {
        icon.textContent = '\uD83C\uDFB5';
        btn.classList.add('playing');
        startBackgroundMusic();
        // Crear AudioContext si no existe
        if (!audioContext) {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
    }

    localStorage.setItem('warehouseMusicMuted', musicMuted);
}

// Inicializar musica desde localStorage
function initMusic() {
    const saved = localStorage.getItem('warehouseMusicMuted');
    if (saved !== null) {
        musicMuted = saved === 'true';
    }

    const icon = document.getElementById('musicIcon');
    const btn = document.getElementById('musicToggle');

    if (musicMuted) {
        icon.textContent = '\uD83D\uDD07';
        btn.classList.remove('playing');
    } else {
        icon.textContent = '\uD83C\uDFB5';
        btn.classList.add('playing');
    }
}

// ==================== CONCEPTOS EDUCATIVOS ====================
const EDUCATIONAL_TIPS = {
    ABC: [
        "\uD83D\uDCA1 Clasificacion ABC: Los productos Zona A (20%) generan el 80% del valor. \u00A1Ubicalos cerca!",
        "\uD83D\uDCA1 Los productos de alta rotacion van en Zona A para acceso rapido.",
        "\uD83D\uDCA1 La clasificacion ABC optimiza el espacio y reduce tiempos de busqueda."
    ],
    FIFO: [
        "\uD83D\uDCA1 FIFO (Primero Entrado, Primero Salido): Ideal para productos perecederos.",
        "\uD83D\uDCA1 FIFO previene obsolescencia y perdidas por vencimiento.",
        "\uD83D\uDCA1 Aplica FIFO en la zona de recepcion: primero en entrar, primero en salir."
    ],
    FEFO: [
        "\uD83D\uDCA1 FEFO (Primero que Expira, Primero Out): Prioriza la fecha de vencimiento.",
        "\uD83D\uDCA1 FEFO es esencial en la zona de refrigeracion.",
        "\uD83D\uDCA1 Verifica fechas de vencimiento antes de almacenar en frio."
    ],
    RECEPCION: [
        "\uD83D\uDCA1 Recepcion: Verifica cantidad, estado y documentacion.",
        "\uD83D\uDCA1 El 70% de errores en almacen se originan en la recepcion.",
        "\uD83D\uDCA1 Compara remision, factura y orden de compra."
    ],
    SEGURIDAD: [
        "\uD83D\uDCA1 Velocidad maxima en zonas peatonales: 10 km/h.",
        "\uD83D\uDCA1 Siempre mirar en la direccion de movimiento del montacargas.",
        "\uD83D\uDCA1 Usar EPP es obligatorio: casco, chaleco, calzado de seguridad."
    ],
    KPIs: [
        "\uD83D\uDCA1 KPI: Rotacion de Inventario = CMV / Inventario Promedio",
        "\uD83D\uDCA1 Cobertura = Inventario / Demanda Diaria (dias)",
        "\uD83D\uDCA1 Rotura de Stock = Pedidos no atendidos / Total pedidos x 100"
    ],
    CONTENEDORES: [
        "\uD83D\uDCA1 Contenedor 20ft: capacidad aprox. 33 m\u00B3, ideal para carga general.",
        "\uD83D\uDCA1 Contenedor Reefer: mantiene temperatura de -30\u00B0C a +30\u00B0C.",
        "\uD83D\uDCA1 Siempre verifica los precintos de seguridad antes de abrir un contenedor."
    ],
    CUBICAJE: [
        "\uD83D\uDCA1 Cubicaje: Volumen = Largo x Ancho x Alto. Optimiza el espacio disponible.",
        "\uD83D\uDCA1 Un buen cubicaje puede aumentar la capacidad del almacen hasta un 30%.",
        "\uD83D\uDCA1 Contenedor 40ft = 67 m\u00B3 de capacidad volumetrica."
    ],
    UBICACION: [
        "\uD83D\uDCA1 Formato de ubicacion: PASILLO-RACK-NIVEL-POSICION (ej: A-01-02-03).",
        "\uD83D\uDCA1 La gestion de ubicacion reduce tiempos de busqueda un 40%.",
        "\uD83D\uDCA1 Tipos de ubicacion: Fija, Aleatoria o Mixta."
    ]
};

const TIP_INTERVAL = 45000;
let lastTipTime = 0;
let currentTipCategory = null;

// ==================== LOGROS DISPONIBLES ====================
const ACHIEVEMENTS = {
    speedster: { name: "\u26A1 Velocista", desc: "Completar en menos de 3 minutos", icon: "\u26A1" },
    perfect: { name: "\uD83D\uDC8E Perfecto", desc: "0 colisiones", icon: "\uD83D\uDC8E" },
    efficient: { name: "\uD83D\uDCCA Eficiente", desc: "100% de eficiencia", icon: "\uD83D\uDCCA" },
    firstBlood: { name: "\uD83C\uDFAF Primera Carga", desc: "Completa tu primera tarea", icon: "\uD83C\uDFAF" },
    master: { name: "\uD83C\uDFC6 Maestro", desc: "Obtiene calificacion A+", icon: "\uD83C\uDFC6" },
    explorer: { name: "\uD83D\uDDFA\uFE0F Explorador", desc: "Visita todas las zonas", icon: "\uD83D\uDDFA\uFE0F" },
    coldZone: { name: "\u2744\uFE0F Zona Fria", desc: "Almacena en zona refrigerada", icon: "\u2744\uFE0F" },
    returns: { name: "\uD83D\uDCE5 Devoluciones", desc: "Procesa una devolucion", icon: "\uD83D\uDCE5" },
    abcMaster: { name: "\uD83D\uDCCA Clasificador ABC", desc: "Almacena correctamente por clasificacion", icon: "\uD83D\uDCCA" },
    fifoExpert: { name: "\uD83D\uDD04 Experto FIFO", desc: "Aplica correctamente el metodo FIFO", icon: "\uD83D\uDD04" },
    safetyFirst: { name: "\uD83E\uDDBA Safety First", desc: "0 colisiones en toda la partida", icon: "\uD83E\uDDBA" }
};

// ==================== MISIONES EDUCATIVAS ====================
const MISSIONS = {
    1: {
        title: "Recorrido por el Centro de Distribucion",
        module: "M1 - Procesos Operativos en CD",
        icon: "\uD83C\uDFE2",
        description: "Conoce las zonas del almacen y sus funciones principales",
        timeLimit: 0,
        pallets: [],
        tasks: [
            { type: "visit", zone: "RECEP", text: "Dirigete a la zona de RECEPCION", concept: "Aqui se reciben todas las mercancias que ingresan al CD", quiz: { q: "\u00BFCual es la funcion principal de la zona de Recepcion?", options: ["Recibir y verificar mercancias entrantes", "Vender productos al publico", "Almacenar productos permanentemente"], correct: 0, tip: "La recepcion es el primer punto de contacto con la mercancia" } },
            { type: "visit", zone: "CARGA", text: "Visita la zona de CARGA/DESCARGA", concept: "Los muelles permiten cargar y descargar camiones", quiz: { q: "\u00BFQue operacion se realiza en los muelles?", options: ["Carga y descarga de vehiculos", "Produccion de bienes", "Contabilidad"], correct: 0, tip: "Los muelles son la interfaz entre el transporte y el almacen" } },
            { type: "visit", zone: "ALMACEN", text: "Visita la zona de ALMACENAMIENTO", concept: "El almacenamiento conserva la mercancia de forma organizada", quiz: { q: "\u00BFCual es el objetivo del almacenamiento?", options: ["Conservar mercancia con orden y disponibilidad", "Desechar productos viejos", "Solo guardar sin control"], correct: 0, tip: "Un almacen organizado reduce tiempos de busqueda en un 40%" } },
            { type: "visit", zone: "PICKING", text: "Visita la zona de PICKING", concept: "En picking se preparan los pedidos seleccionando productos", quiz: { q: "\u00BFQue se hace en la zona de Picking?", options: ["Preparar pedidos seleccionando productos", "Recibir camiones", "Limpiar el almacen"], correct: 0, tip: "Picking representa el 55% del costo operativo de un almacen" } },
            { type: "visit", zone: "DESPACHO", text: "Visita la zona de DESPACHO", concept: "El despacho coordina la salida de mercancia", quiz: { q: "\u00BFQue funcion cumple el Despacho?", options: ["Coordinar la salida de mercancia", "Recibir devoluciones", "Almacenar en frio"], correct: 0, tip: "Un despacho eficiente reduce los tiempos de entrega al cliente" } },
            { type: "visit", zone: "FRIO", text: "Visita la zona de REFRIGERACION", concept: "La zona fria mantiene la cadena de frio para perecederos", quiz: { q: "\u00BFPara que sirve la zona de Refrigeracion?", options: ["Mantener cadena de frio para perecederos", "Enfriar al personal", "Almacenar maquinaria"], correct: 0, tip: "Romper la cadena de frio puede causar perdidas del 100% del lote" } },
            { type: "visit", zone: "ANDEN", text: "Finaliza en el ANDEN de embarque", concept: "El anden es donde se cargan los vehiculos para distribucion", quiz: { q: "Las 4 funciones principales de un CD son:", options: ["Recibir, Almacenar, Preparar y Despachar", "Comprar, Vender, Cobrar y Pagar", "Producir, Empacar, Etiquetar y Enviar"], correct: 0, tip: "Recibir -> Almacenar -> Preparar -> Despachar: el flujo logistico completo" } }
        ],
        completionCard: { title: "Procesos de un Centro de Distribucion", concept: "Un Centro de Distribucion ejecuta 4 funciones clave:\n\n1. RECIBIR \u2014 Verificar mercancia entrante\n2. ALMACENAR \u2014 Conservar con orden y disponibilidad\n3. PREPARAR \u2014 Picking y preparacion de pedidos\n4. DESPACHAR \u2014 Coordinar salida y distribucion\n\nCada zona del almacen cumple un rol especifico en este flujo.", moduleRef: 1 }
    },
    2: {
        title: "Recepcion de Mercancias",
        module: "M2 - Sistemas de Recepcion",
        icon: "\uD83D\uDCE5",
        description: "Recibe un cargamento, verifica la mercancia y registrala",
        timeLimit: 300,
        pallets: [
            { x: -40, z: 25, color: 0xe67e22, label: "PALLET-001\nPaletizado\nRemision: R-2024" },
            { x: -37, z: 25, color: 0xf39c12, label: "PALLET-002\nVerificado\nEDI: OK" }
        ],
        tasks: [
            { type: "visit", zone: "CARGA", text: "Ve al muelle de CARGA/DESCARGA para recibir el camion", concept: "El recibo comienza en el muelle de descarga", quiz: { q: "\u00BFQue metodo de recibo organiza la mercancia en pallets?", options: ["Paletizado", "A granel", "En arrume negro"], correct: 0, tip: "El paletizado facilita el manejo con montacargas y reduce danos" } },
            { type: "pickup", palletIndex: 0, text: "Carga el PALLET NARANJA del muelle", concept: "Verifica cantidad, estado y documentacion antes de cargar", quiz: { q: "\u00BFQue debes verificar al recibir mercancia?", options: ["Cantidad, estado y documentacion", "Solo el color de la caja", "Nada, se carga directo"], correct: 0, tip: "Compara: remision, factura y orden de compra" } },
            { type: "deliver", zone: "RECEP", text: "Lleva a RECEPCION para inspeccion y registro", concept: "En recepcion se inspecciona y codifica la mercancia", quiz: null },
            { type: "pickup", palletIndex: 1, text: "Carga el pallet ya verificado y codificado", concept: "La codificacion permite trazabilidad del producto", quiz: { q: "\u00BFQue sistema electronico documenta el recibo de mercancias?", options: ["EDI (Intercambio Electronico de Datos)", "Correo electronico", "Mensaje de texto"], correct: 0, tip: "EDI elimina papeles y reduce errores de digitacion un 80%" } },
            { type: "deliver", zone: "ALMACEN", text: "Almacena la mercancia recibida en la estanteria", concept: "Mercancia verificada pasa a almacenamiento", quiz: null }
        ],
        completionCard: { title: "Principios de Recepcion de Mercancias", concept: "El proceso de recepcion tiene 4 etapas:\n\n1. DESCARGA \u2014 En muelle, verificar vehiculo y remision\n2. INSPECCION \u2014 Cantidad, estado, fechas\n3. REGISTRO \u2014 Codificacion y documentos (EDI)\n4. UBICACION \u2014 Trasladar a zona de almacenamiento\n\nEl 70% de errores en un almacen se originan en la recepcion.", moduleRef: 2 }
    },
    3: {
        title: "Cubicaje y Codificacion",
        module: "M3 - Cubicaje y Codigo de Barras",
        icon: "\uD83D\uDCD0",
        description: "Calcula volumenes, escanea codigos y optimiza espacio",
        timeLimit: 300,
        pallets: [
            { x: 0, z: 28, color: 0x3498db, label: "CAJA-A\n2.0x1.0x1.5m\nVol: ?" },
            { x: 3, z: 28, color: 0x2980b9, label: "CAJA-B\nCod: EAN-13\nRFID: Activo" }
        ],
        tasks: [
            { type: "visit", zone: "RECEP", text: "Ve a RECEPCION donde llego la mercancia", concept: "Antes de almacenar hay que calcular el espacio necesario", quiz: { q: "\u00BFQue es el cubicaje?", options: ["Calcular el volumen para optimizar espacio", "Pintar las cajas de colores", "Pesar los productos"], correct: 0, tip: "Cubicaje = Largo x Ancho x Alto" } },
            { type: "pickup", palletIndex: 0, text: "Carga la CAJA A (azul) para medicion", concept: "Esta caja mide 2.0 x 1.0 x 1.5 metros", quiz: { q: "Una caja mide 2.0 x 1.0 x 1.5 metros. \u00BFCual es su volumen?", options: ["3.0 m\u00B3", "4.5 m\u00B3", "2.5 m\u00B3"], correct: 0, tip: "Volumen = 2.0 x 1.0 x 1.5 = 3.0 m\u00B3" } },
            { type: "deliver", zone: "ALMACEN", text: "Almacena la CAJA A en la estanteria", concept: "Cada estanteria tiene capacidad cubica limitada", quiz: null },
            { type: "pickup", palletIndex: 1, text: "Carga la CAJA B para escaneo de codigo", concept: "Los productos se identifican con codigo de barras o RFID", quiz: { q: "\u00BFQue tecnologia de identificacion usa radiofrecuencia?", options: ["RFID", "Codigo de barras EAN-13", "Codigo QR"], correct: 0, tip: "RFID no necesita linea de vision directa, a diferencia del codigo de barras" } },
            { type: "deliver", zone: "ALMACEN2", text: "Ubica la CAJA B en almacenamiento secundario", concept: "La codificacion permite localizar cualquier producto al instante", quiz: { q: "\u00BFCual es la principal ventaja del codigo de barras EAN-13?", options: ["Lectura rapida con escaner optico", "Es mas bonito", "No sirve para nada"], correct: 0, tip: "EAN-13 es el estandar mundial para identificacion de productos en retail" } }
        ],
        completionCard: { title: "Cubicaje y Codificacion de Inventarios", concept: "Conceptos clave:\n\n\u2022 CUBICAJE: Volumen = L x A x A. Optimiza el uso del espacio.\n\u2022 CODIGO DE BARRAS (EAN-13): Lectura optica rapida, estandar mundial.\n\u2022 RFID: Identificacion por radiofrecuencia, no necesita linea de vision.\n\u2022 Contenedor 20ft = 33 m\u00B3 | Contenedor 40ft = 67 m\u00B3\n\nUn buen cubicaje puede aumentar la capacidad de almacen hasta un 30%.", moduleRef: 3 }
    },
    4: {
        title: "Inspeccion de Contenedores",
        module: "M4 - Contenedores y Seguridad",
        icon: "\uD83D\uDE9B",
        description: "Inspecciona contenedores, verifica precintos y simbolos de seguridad",
        timeLimit: 300,
        pallets: [
            { x: 40, z: -5, color: 0x9b59b6, label: "CONT-20FT\nPrecinto: OK\n\u26A0 Fragil" },
            { x: 43, z: -5, color: 0xe91e63, label: "CONT-40FT\nReeferr\n\u2744 -18\u00B0C" }
        ],
        tasks: [
            { type: "visit", zone: "ANDEN", text: "Ve al ANDEN donde llego el contenedor", concept: "Los contenedores deben inspeccionarse antes de abrir", quiz: { q: "\u00BFCuales son las dimensiones estandar de contenedores?", options: ["20 pies y 40 pies", "10 pies y 50 pies", "100 pies"], correct: 0, tip: "Contenedor 20ft \u2248 33m\u00B3 de capacidad, 40ft \u2248 67m\u00B3" } },
            { type: "pickup", palletIndex: 0, text: "Retira la carga del CONTENEDOR 20FT", concept: "Verifica el precinto de seguridad antes de abrir", quiz: { q: "\u00BFQue es un precinto de seguridad en un contenedor?", options: ["Un sello oficial que garantiza que no fue abierto", "Una cinta decorativa", "Un candado comun"], correct: 0, tip: "Los precintos pueden ser de plastico, acero o electronicos" } },
            { type: "deliver", zone: "RECEP", text: "Lleva la carga a RECEPCION para verificacion", concept: "Toda carga de contenedor pasa por recepcion", quiz: { q: "El simbolo \u26A0 con una llama indica:", options: ["Material inflamable", "Material fragil", "Material reciclable"], correct: 0, tip: "Los simbolos de seguridad ISO indican: toxico, inflamable, radiactivo, electrico, pesado" } },
            { type: "pickup", palletIndex: 1, text: "Retira la carga del CONTENEDOR REFRIGERADO", concept: "Un contenedor Reefer mantiene temperatura controlada", quiz: { q: "\u00BFQue tipo de contenedor mantiene temperatura controlada?", options: ["Contenedor Refrigerado (Reefer)", "Contenedor High Cube", "Contenedor Open Top"], correct: 0, tip: "Los Reefer mantienen desde -30\u00B0C hasta +30\u00B0C para perecederos" } },
            { type: "deliver", zone: "FRIO", text: "Lleva la carga refrigerada a ZONA FRIA", concept: "Los perecederos deben ir directo a la camara fria", quiz: null }
        ],
        completionCard: { title: "Contenedores y Medidas de Seguridad", concept: "Tipos de contenedores:\n\n\u2022 20 pies (33 m\u00B3) \u2014 Carga general\n\u2022 40 pies (67 m\u00B3) \u2014 Mayor capacidad\n\u2022 High Cube \u2014 Mas alto (+30 cm)\n\u2022 Reefer \u2014 Refrigerado (-30\u00B0C a +30\u00B0C)\n\nInspeccion obligatoria:\n1. Verificar precintos (plastico/acero/electronico)\n2. Identificar simbolos de seguridad\n3. Control previo antes de abrir\n4. Documentar estado del contenedor", moduleRef: 4 }
    },
    5: {
        title: "Almacenamiento y FIFO",
        module: "M5 - Sistemas de Almacenamiento",
        icon: "\uD83D\uDCE6",
        description: "Almacena siguiendo FIFO, ubica correctamente y sigue protocolos de seguridad",
        timeLimit: 360,
        pallets: [
            { x: -3, z: 28, color: 0x2ecc71, label: "LOTE-A\nFecha: 01/Mar\nUbic: A-01-02", fifoDate: 1 },
            { x: 0, z: 28, color: 0xf1c40f, label: "LOTE-B\nFecha: 15/Feb\nUbic: A-01-01", fifoDate: 0 },
            { x: 3, z: 28, color: 0xe74c3c, label: "LOTE-C\nFecha: 20/Mar\nUbic: A-02-01", fifoDate: 2 }
        ],
        tasks: [
            { type: "visit", zone: "RECEP", text: "Ve a RECEPCION \u2014 hay 3 lotes por almacenar", concept: "FIFO: Primero que Entra, Primero que Sale", quiz: { q: "\u00BFQue significa FIFO?", options: ["First In, First Out (Primero que entra, primero que sale)", "Fast In, Fast Out", "First Inspection, Final Output"], correct: 0, tip: "FIFO previene obsolescencia y es obligatorio para perecederos" } },
            { type: "pickup", palletIndex: 1, text: "Carga PRIMERO el LOTE B (15/Feb) \u2014 es el mas antiguo", concept: "En FIFO, el lote con fecha mas antigua se almacena primero", quiz: { q: "Hay 3 lotes: 01/Mar, 15/Feb, 20/Mar. \u00BFCual se almacena PRIMERO segun FIFO?", options: ["15/Feb \u2014 el mas antiguo", "20/Mar \u2014 el mas reciente", "01/Mar \u2014 el del medio"], correct: 0, tip: "FIFO: el primero que entro (fecha mas antigua) debe salir primero" } },
            { type: "deliver", zone: "ALMACEN", text: "Almacena el LOTE B en ubicacion A-01-01", concept: "Ubicacion A-01-01 = Pasillo A, Rack 01, Nivel 01", quiz: { q: "\u00BFQue significa la ubicacion A-01-02-03?", options: ["Pasillo A, Rack 01, Nivel 02, Posicion 03", "Almacen A, Zona 1, Caja 2, Piso 3", "Area A, Modulo 01, Seccion 02, Item 03"], correct: 0, tip: "El estandar es: PASILLO-RACK-NIVEL-POSICION" } },
            { type: "pickup", palletIndex: 0, text: "Ahora carga el LOTE A (01/Mar)", concept: "Segundo lote mas antiguo", quiz: null },
            { type: "deliver", zone: "ALMACEN", text: "Almacena el LOTE A en ubicacion A-01-02", concept: "Almacenamiento convencional usa racks selectivos", quiz: { q: "\u00BFCual metodo de almacenamiento permite acceso directo a cada pallet?", options: ["Convencional (rack selectivo)", "Drive-In", "Push-Back"], correct: 0, tip: "Rack selectivo: acceso directo, pero usa mas espacio en pasillos" } },
            { type: "pickup", palletIndex: 2, text: "Finalmente, carga el LOTE C (20/Mar) \u2014 el mas reciente", concept: "El mas reciente se almacena al final", quiz: null },
            { type: "deliver", zone: "ALMACEN2", text: "Almacena el LOTE C en ubicacion A-02-01", concept: "Seguridad: siempre usa EPP y respeta limites de carga", quiz: { q: "\u00BFCual es una norma de seguridad obligatoria en almacenamiento?", options: ["Usar EPP (casco, chaleco, calzado de seguridad)", "Correr en los pasillos", "Apilar sin limite de altura"], correct: 0, tip: "EPP obligatorio: casco, chaleco reflectivo, calzado de seguridad, guantes" } }
        ],
        completionCard: { title: "Sistemas de Almacenamiento", concept: "Principios clave:\n\n\u2022 FIFO: Primero que entra, primero que sale. Obligatorio para perecederos.\n\u2022 UBICACION: Formato PASILLO-RACK-NIVEL-POSICION (ej: A-01-02-03)\n\u2022 METODOS: Convencional, Drive-In, Push-Back, Cantilever\n\u2022 SEGURIDAD: EPP obligatorio, limites de carga, pasillos libres\n\nTipos de ubicacion: Fija, Aleatoria o Mixta.\nLa gestion de ubicacion reduce tiempos de busqueda un 40%.", moduleRef: 5 }
    },
    6: {
        title: "Clasificacion ABC y Reabastecimiento",
        module: "M6 - Reabastecimiento e Inventarios",
        icon: "\uD83D\uDCCA",
        description: "Clasifica productos por metodo ABC, calcula punto de reorden",
        timeLimit: 360,
        pallets: [
            { x: -3, z: 28, color: 0xe74c3c, label: "ZONA A\nAlta rotacion\n80% del valor", abcZone: "A" },
            { x: 0, z: 28, color: 0xf39c12, label: "ZONA B\nMedia rotacion\n15% del valor", abcZone: "B" },
            { x: 3, z: 28, color: 0x3498db, label: "ZONA C\nBaja rotacion\n5% del valor", abcZone: "C" }
        ],
        tasks: [
            { type: "visit", zone: "RECEP", text: "Ve a RECEPCION \u2014 clasifica 3 pallets por metodo ABC", concept: "El metodo ABC clasifica productos por su valor e importancia", quiz: { q: "En el metodo ABC, la Zona A representa:", options: ["20% de los items pero 80% del valor", "50% de los items y 50% del valor", "80% de los items pero 20% del valor"], correct: 0, tip: "ABC se basa en el Principio de Pareto (80/20)" } },
            { type: "pickup", palletIndex: 0, text: "Carga el pallet ZONA A (rojo) \u2014 alta rotacion", concept: "Zona A = productos de mayor valor, cerca de despacho", quiz: { q: "\u00BFDonde deben ubicarse los productos Zona A?", options: ["Cerca de despacho, acceso rapido", "En el fondo del almacen", "En la zona fria"], correct: 0, tip: "Zona A: acceso inmediato, zonas bajas, cerca de salida" } },
            { type: "deliver", zone: "DESPACHO", text: "Ubica Zona A CERCA de DESPACHO (acceso rapido)", concept: "Alta rotacion = cerca de la salida para eficiencia", quiz: null },
            { type: "pickup", palletIndex: 1, text: "Carga el pallet ZONA B (naranja) \u2014 media rotacion", concept: "Zona B = rotacion media, ubicacion intermedia", quiz: { q: "Demanda diaria = 100 uds, Lead time = 5 dias. \u00BFPunto de reorden?", options: ["500 unidades", "100 unidades", "50 unidades"], correct: 0, tip: "Punto de Reorden = Demanda x Lead Time = 100 x 5 = 500" } },
            { type: "deliver", zone: "PICKING", text: "Ubica Zona B en area de PICKING (zona intermedia)", concept: "Zona B tiene acceso moderado", quiz: null },
            { type: "pickup", palletIndex: 2, text: "Carga el pallet ZONA C (azul) \u2014 baja rotacion", concept: "Zona C = baja rotacion, ubicacion lejana", quiz: { q: "\u00BFQue es el stock de seguridad?", options: ["Reserva extra para cubrir variaciones de demanda", "El inventario total del almacen", "Productos vencidos"], correct: 0, tip: "Stock Seguridad = Demanda x Lead Time x Factor de servicio" } },
            { type: "deliver", zone: "ALMACEN2", text: "Ubica Zona C en ALMACENAMIENTO profundo", concept: "Baja rotacion = almacenamiento profundo, menos accesible", quiz: null }
        ],
        completionCard: { title: "Clasificacion ABC y Control de Inventarios", concept: "Metodo ABC (Pareto 80/20):\n\n\u2022 ZONA A: 20% items, 80% valor \u2192 Cerca de despacho\n\u2022 ZONA B: 30% items, 15% valor \u2192 Zona intermedia\n\u2022 ZONA C: 50% items, 5% valor \u2192 Almacenamiento profundo\n\nFormulas clave:\n\u2022 Punto de Reorden = Demanda x Lead Time + Stock Seguridad\n\u2022 Stock Seguridad = Demanda x Lead Time x Factor servicio\n\u2022 Rotacion = Costo Mercancia Vendida / Inventario Promedio", moduleRef: 6 }
    },
    0: {
        title: "Modo Libre",
        module: "Exploracion",
        icon: "\uD83D\uDD13",
        description: "Explora el almacen sin objetivos. Tips educativos aleatorios",
        timeLimit: 0,
        pallets: [
            { x: -3, z: 28, color: 0x3498db, label: "LIBRE" },
            { x: 0, z: 28, color: 0xe67e22, label: "LIBRE" },
            { x: 3, z: 28, color: 0x2ecc71, label: "LIBRE" }
        ],
        tasks: [],
        completionCard: null
    }
};

// ==================== INVENTARIO ====================
let zoneCounts = { ALMACEN: 0, PICKING: 0, DESPACHO: 0, ANDEN: 0, FRIO: 0, DEV: 0 };

// ==================== CONTROL DE CAMARA ====================
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
const shiftPanel = document.getElementById('shiftPanel');
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
        rankingList.innerHTML = '<p style="text-align:center;color:#888;padding:20px;">No hay puntuaciones aun. \u00A1Se el primero!</p>';
        return;
    }
    ranking.forEach((entry, i) => {
        const row = document.createElement('div');
        row.className = `ranking-row ${i < 3 ? 'top-' + (i+1) : ''}`;
        row.innerHTML = `<span>${i + 1}</span><span>${entry.score} pts</span><span>${formatTime(entry.time)}</span><span>${entry.date}</span>`;
        rankingList.appendChild(row);
    });
}

// ==================== INICIALIZACION ====================
function init() {
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1a1a2e);
    scene.fog = new THREE.Fog(0x1a1a2e, 20, 80);

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
    createPlayer();
    
    // Partículas para zona fría
    coldZoneParticles = createColdZoneParticles();

    // Inicializar sistema de inventario (necesita racks creados)
    initInventorySystem();

    setupControls();
    setupTerminal();
    setupCameraControls();
    displayRanking();

    animate();

    renderMissionSelect();
    initMusic();
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

    // Luces interiores del almacen
    const warehouseLights = [
        { pos: [0, 10, 0], color: 0xffffcc, intensity: 0.5 },
        { pos: [-30, 10, -20], color: 0xffffcc, intensity: 0.4 },
        { pos: [30, 10, -20], color: 0xffffcc, intensity: 0.4 },
        { pos: [0, 10, -40], color: 0xffffcc, intensity: 0.4 },
        { pos: [-30, 10, 20], color: 0xffffcc, intensity: 0.4 },
        { pos: [30, 10, 20], color: 0xffffcc, intensity: 0.4 }
    ];

    warehouseLightRefs = [];
    warehouseLights.forEach(light => {
        const l = new THREE.PointLight(light.color, light.intensity, 40);
        l.position.set(...light.pos);
        scene.add(l);
        warehouseLightRefs.push(l);
    });

    // Luz fria para zona de refrigeracion
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
    // Piso principal con textura de concreto
    const floorGeo = new THREE.PlaneGeometry(100, 100);
    const floorMat = new THREE.MeshStandardMaterial({ 
        map: createConcreteTexture(),
        roughness: 0.8,
        metalness: 0.1,
        side: THREE.DoubleSide
    });
    const floor = new THREE.Mesh(floorGeo, floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.receiveShadow = true;
    scene.add(floor);

    // Cuadricula (opcional, se puede comentar si la textura ya tiene líneas)
    // const gridHelper = new THREE.GridHelper(100, 100, 0x2c3e50, 0x2c3e50);
    // gridHelper.position.y = 0.02;
    // scene.add(gridHelper);

    createTransitLines();
    createWalls();
    createColumns();
    createCeiling();
}

function createTransitLines() {
    const lineMat = new THREE.MeshBasicMaterial({ color: 0xf1c40f });

    // Lineas de transito principales
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
    const wallMat = new THREE.MeshStandardMaterial({ 
        map: createMetalTexture('#34495e'),
        transparent: true,
        opacity: 0.6,
        roughness: 0.7,
        metalness: 0.3,
        side: THREE.DoubleSide
    });

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
    const columnMat = new THREE.MeshStandardMaterial({ 
        map: createMetalTexture('#7f8c8d'),
        roughness: 0.6,
        metalness: 0.4
    });
    const columnGeo = new THREE.BoxGeometry(1, 12, 1);

    for (let x = -45; x <= 45; x += 15) {
        for (let z = -45; z <= 35; z += 15) {
            if (Math.abs(x) > 10 || z < 0) {
                const column = new THREE.Mesh(columnGeo, columnMat);
                column.position.set(x, 6, z);
                column.castShadow = true;
                scene.add(column);
                collisionObjects.push({ x, z, width: 1, depth: 1 });
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

    // 1. AREA DE RECEPCION (Amarillo) - Entrada principal
    createZone(0, 0, 30, 0xf1c40f, "RECEP", "RECEPCION");

    // 2. AREA DE CARGA/DESCARGA (Naranja) - Dock de trucks
    createZone(-40, 0, 25, 0xe67e22, "CARGA", "CARGA/DESCARGA");
    createDock(45, 0, 25);

    // 3. AREA DE ALMACENAMIENTO (Azul) - Estanterias principales
    createZone(-35, 0, -15, 0x3498db, "ALMACEN", "ALMACENAMIENTO");
    createZone(-20, 0, -15, 0x2980b9, "ALMACEN2", "ALMACENAMIENTO");

    // 4. Zona de PICKING (Morado)
    createZone(20, 0, -15, 0x9b59b6, "PICKING", "PICKING");
    createWorkStation(20, 0, -15);

    // 5. Zona de DESPACHO (Verde)
    createZone(0, 0, -35, 0x2ecc71, "DESPACHO", "DESPACHO");

    // 6. Zona de DEVOLUCIONES (Rosa)
    createZone(40, 0, -15, 0xe91e63, "DEV", "DEVOLUCIONES");

    // 7. Zona FRIA/REFRIGERACION (Celeste)
    createZone(40, 0, -40, 0x87ceeb, "FRIO", "REFRIGERACION");
    createColdRoom(40, 0, -40);

    // 8. Zona de SEGURIDAD (Rojo)
    createZone(-40, 0, -40, 0xe74c3c, "SEGUR", "SEGURIDAD");

    // 9. Oficina de CONTROL (Gris)
    createZone(-40, 0, 10, 0x95a5a6, "OFICINA", "CONTROL");
    createOffice(40, 0, 10);

    // 10. ANDEN de embarque
    createZone(40, 0, -5, 0x9b59b6, "ANDEN", "ANDEN EMBARQUE");
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
    // Estructura de la camara fria
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
    const windowMesh = new THREE.Mesh(windowGeo, windowMat);
    windowMesh.position.set(x, 2.5, z + 5.95);
    scene.add(windowMesh);

    // Maquina de frio
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
    // Plataforma del anden
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

// ==================== ESTANTERIAS ====================
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

    // Estanterias cantilever para productos largos
    createCantileverRack(0, -40, 0x7f8c8d);
}

function createRack(x, z, color, width, height) {
    width = width || 4;
    height = height || 4;
    const rackGroup = new THREE.Group();
    
    // Texturas procedurales
    const postTexture = createMetalTexture('#95a5a6');
    const shelfTexture = createMetalTexture('#' + color.toString(16).padStart(6, '0'));
    
    // Postes con textura de metal
    const postGeo = new THREE.BoxGeometry(0.15, height, 0.15);
    const postMat = new THREE.MeshStandardMaterial({ 
        map: postTexture,
        metalness: 0.9,
        roughness: 0.5
    });

    const postPositions = [
        [-width/2 + 0.1, -0.5],  // frente izquierdo
        [width/2 - 0.1, -0.5],   // frente derecho
        [-width/2 + 0.1, 0.5],   // trasero izquierdo
        [width/2 - 0.1, 0.5]     // trasero derecho
    ];
    
    postPositions.forEach(pos => {
        const post = new THREE.Mesh(postGeo, postMat);
        post.position.set(pos[0], height/2, pos[1]);
        post.castShadow = true;
        rackGroup.add(post);
    });

    // Bastidores en X (refuerzos diagonales) entre postes frontales y traseros
    const crossGeo = new THREE.CylinderGeometry(0.03, 0.03, Math.sqrt(width * width + 1), 8);
    const crossMat = new THREE.MeshStandardMaterial({ 
        map: postTexture,
        metalness: 0.8,
        roughness: 0.6
    });
    
    // Diagonal frontal izquierdo a trasero derecho
    const cross1 = new THREE.Mesh(crossGeo, crossMat);
    cross1.position.set(0, height/2, 0);
    cross1.rotation.z = Math.atan2(1, width - 0.2);
    rackGroup.add(cross1);
    
    // Diagonal frontal derecho a trasero izquierdo
    const cross2 = new THREE.Mesh(crossGeo, crossMat);
    cross2.position.set(0, height/2, 0);
    cross2.rotation.z = -Math.atan2(1, width - 0.2);
    rackGroup.add(cross2);

    // Estantes con textura de metal
    const shelfGeo = new THREE.BoxGeometry(width, 0.08, 1.2);
    const shelfMat = new THREE.MeshStandardMaterial({ 
        map: shelfTexture,
        metalness: 0.7,
        roughness: 0.4
    });
    for (let y = 0.8; y < height; y += 1) {
        const shelf = new THREE.Mesh(shelfGeo, shelfMat);
        shelf.position.set(0, y, 0);
        shelf.castShadow = true;
        shelf.receiveShadow = true;
        rackGroup.add(shelf);
    }

    // Etiqueta de ubicación (generar código basado en coordenadas)
    const rackLetter = String.fromCharCode(65 + Math.floor((x + 50) / 10)); // A, B, C...
    const rackNumber = Math.floor((z + 50) / 10).toString().padStart(2, '0');
    const locationCode = `${rackLetter}-${rackNumber}`;
    const label = createLocationLabel(locationCode);
    label.position.set(0, height + 0.5, -0.7); // frente del rack
    rackGroup.add(label);

    rackGroup.position.set(x, 0, z);
    scene.add(rackGroup);
    racks.push({ mesh: rackGroup, x, z, width, depth: 1.2, location: locationCode });
}

function createCantileverRack(x, z, color) {
    const rackGroup = new THREE.Group();
    
    // Texturas procedurales
    const postTexture = createMetalTexture('#7f8c8d');
    const armTexture = createMetalTexture('#' + color.toString(16).padStart(6, '0'));
    
    // Postes con textura de metal
    const postGeo = new THREE.BoxGeometry(0.3, 6, 0.3);
    const postMat = new THREE.MeshStandardMaterial({ 
        map: postTexture,
        metalness: 0.9,
        roughness: 0.5
    });

    [-2, 2].forEach(offset => {
        const post = new THREE.Mesh(postGeo, postMat);
        post.position.set(offset, 3, 0);
        post.castShadow = true;
        rackGroup.add(post);
    });

    // Brazos cantilever con textura
    const armGeo = new THREE.BoxGeometry(0.1, 0.1, 3);
    const armMat = new THREE.MeshStandardMaterial({ 
        map: armTexture,
        metalness: 0.8,
        roughness: 0.4
    });
    [1, 2.5, 4].forEach(y => {
        [-0.5, 0.5].forEach(side => {
            const arm = new THREE.Mesh(armGeo, armMat);
            arm.position.set(side * 1.25, y, 0);
            arm.castShadow = true;
            rackGroup.add(arm);
        });
    });

    // Etiqueta de ubicación
    const rackLetter = String.fromCharCode(65 + Math.floor((x + 50) / 10));
    const rackNumber = Math.floor((z + 50) / 10).toString().padStart(2, '0');
    const locationCode = `${rackLetter}-${rackNumber}`;
    const label = createLocationLabel(locationCode, '#34495e', '#ecf0f1');
    label.position.set(0, 7, 0);
    rackGroup.add(label);

    rackGroup.position.set(x, 0, z);
    scene.add(rackGroup);
    racks.push({ mesh: rackGroup, x, z, width: 4, depth: 3, location: locationCode });
}

// ==================== OBJETOS DEL ALMACEN ====================
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

    // Senales de seguridad
    createSafetySign(0, -48, "\u26A0\uFE0F PELIGRO");
    createSafetySign(-48, 0, "\uD83E\uDDBA USO OBLIGATORIO");
    createSafetySign(48, 0, "\uD83D\uDEAB VELOCIDAD MAX 10 KM/H");
    createSafetySign(-45, -35, "\uD83D\uDEA8 ALARMA");

    // Pallets vacios dispersos
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

    // Carros elevadores pequenos
    createSmallCart(-10, 25);
    createSmallCart(30, 25);

    // Basculas
    createScale(-3, 30);

    // Torres de iluminacion
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
    collisionObjects.push({ x, z, width: 1.8, depth: 1.2 });
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
    collisionObjects.push({ x, z, width: 2.5, depth: 1.5 });
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
    collisionObjects.push({ x, z, width: 1, depth: 1.5 });
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
    collisionObjects.push({ x, z, width: 0.3, depth: 0.3 });
}

// ==================== AREA DE RECEPCION ====================
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
function createMissionPallets(missionNum) {
    // Remove existing pallets from scene
    pallets.forEach(p => { scene.remove(p); });
    pallets = [];

    const mission = MISSIONS[missionNum];
    if (!mission || !mission.pallets) return;

    mission.pallets.forEach((pData, i) => {
        const pallet = createPallet(pData.x, pData.z, pData.color, i);
        // Add extra mission data to pallet
        pallet.userData.missionLabel = pData.label || '';
        pallet.userData.fifoDate = pData.fifoDate !== undefined ? pData.fifoDate : -1;
        pallet.userData.abcZone = pData.abcZone || '';
    });
}

function createPallet(x, z, color, taskIndex) {
    const palletGroup = new THREE.Group();

    // Base de madera con textura
    const baseGeo = new THREE.BoxGeometry(1.4, 0.12, 1.4);
    const baseMat = new THREE.MeshStandardMaterial({ 
        map: createWoodTexture(),
        roughness: 0.8,
        metalness: 0.0
    });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.06;
    base.castShadow = true;
    palletGroup.add(base);

    // Tablones (más realistas, 5 tablones)
    const plankGeo = new THREE.BoxGeometry(1.4, 0.04, 0.08);
    const plankMat = new THREE.MeshStandardMaterial({ 
        map: createWoodTexture(),
        roughness: 0.7,
        metalness: 0.0
    });
    [-0.6, -0.3, 0, 0.3, 0.6].forEach(offset => {
        const plank = new THREE.Mesh(plankGeo, plankMat);
        plank.position.set(0, 0.14, offset);
        palletGroup.add(plank);
    });

    // Pila de cajas (2x2x2 = 8 cajas)
    const boxSize = 0.5;
    const boxGeo = new THREE.BoxGeometry(boxSize, boxSize, boxSize);
    const boxMat = new THREE.MeshStandardMaterial({ 
        map: createCardboardTexture('#' + color.toString(16).padStart(6, '0')),
        roughness: 0.9,
        metalness: 0.0
    });
    
    // Apilar cajas
    for (let row = 0; row < 2; row++) {
        for (let col = 0; col < 2; col++) {
            for (let layer = 0; layer < 2; layer++) {
                const box = new THREE.Mesh(boxGeo, boxMat);
                const x = (col - 0.5) * boxSize * 1.1;
                const z = (row - 0.5) * boxSize * 1.1;
                const y = 0.3 + layer * boxSize;
                box.position.set(x, y, z);
                box.castShadow = true;
                palletGroup.add(box);
            }
        }
    }

    // Etiqueta frontal con información de la misión
    const labelGeo = new THREE.PlaneGeometry(0.4, 0.3);
    const labelCanvas = document.createElement('canvas');
    labelCanvas.width = 256;
    labelCanvas.height = 192;
    const ctx = labelCanvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 256, 192);
    ctx.fillStyle = '#000000';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('INTEP', 128, 30);
    ctx.font = '14px Arial';
    ctx.fillText('Almacén CD', 128, 60);
    ctx.fillStyle = '#' + color.toString(16).padStart(6, '0');
    ctx.fillRect(80, 80, 96, 40);
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 16px Arial';
    ctx.fillText('PALLET', 128, 105);
    ctx.fillStyle = '#000000';
    ctx.font = '12px Arial';
    ctx.fillText(`Task ${taskIndex + 1}`, 128, 140);
    
    const labelTexture = new THREE.CanvasTexture(labelCanvas);
    const labelMat = new THREE.MeshBasicMaterial({ map: labelTexture, side: THREE.DoubleSide });
    const label = new THREE.Mesh(labelGeo, labelMat);
    label.position.set(0, 1.0, 0.71);
    label.rotation.y = Math.PI; // Rotar para que mire hacia afuera
    palletGroup.add(label);

    palletGroup.position.set(x, 0, z);
    palletGroup.userData = { taskIndex, color, originalPos: { x, z }, pickedUp: false, delivered: false };

    scene.add(palletGroup);
    pallets.push(palletGroup);
    return palletGroup;
}

// ==================== MONTACARGAS ====================
function createPlayer() {
    playerGroup = new THREE.Group();

    // ===== Textura de placa INTEP =====
    function createTextTexture(text, textColor = '#FFFFFF', bgColor = '#1f7a1f') {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = 512; canvas.height = 256;
        ctx.fillStyle = bgColor;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 8;
        ctx.strokeRect(12, 12, canvas.width - 24, canvas.height - 24);
        ctx.font = 'bold 120px Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        ctx.fillText(text, canvas.width / 2 + 4, canvas.height / 2 - 18);
        ctx.fillStyle = textColor;
        ctx.fillText(text, canvas.width / 2, canvas.height / 2 - 22);
        ctx.font = 'bold 26px Arial';
        ctx.fillStyle = '#FFFFFF';
        ctx.fillText('INSTITUTO TECNICO PEDAGOGICO', canvas.width / 2, canvas.height - 34);
        const texture = new THREE.CanvasTexture(canvas);
        texture.needsUpdate = true;
        return texture;
    }

    // ===== Materiales PBR =====
    const matBody       = new THREE.MeshStandardMaterial({ color: 0x1f7a1f, metalness: 0.55, roughness: 0.35 });
    const matBodyDark   = new THREE.MeshStandardMaterial({ color: 0x155215, metalness: 0.55, roughness: 0.40 });
    const matChassis    = new THREE.MeshStandardMaterial({ color: 0x222831, metalness: 0.70, roughness: 0.50 });
    const matSteel      = new THREE.MeshStandardMaterial({ color: 0x6a7176, metalness: 0.90, roughness: 0.30 });
    const matChrome     = new THREE.MeshStandardMaterial({ color: 0xc8c8c8, metalness: 0.95, roughness: 0.15 });
    const matRubber     = new THREE.MeshStandardMaterial({ color: 0x141414, metalness: 0.10, roughness: 0.95 });
    const matRim        = new THREE.MeshStandardMaterial({ color: 0xb5b5b5, metalness: 0.85, roughness: 0.25 });
    const matBlack      = new THREE.MeshStandardMaterial({ color: 0x141414, metalness: 0.50, roughness: 0.50 });
    const matSeat       = new THREE.MeshStandardMaterial({ color: 0x1a1a1a, metalness: 0.10, roughness: 0.90 });
    const matYellow     = new THREE.MeshStandardMaterial({ color: 0xf4c20d, metalness: 0.40, roughness: 0.50, emissive: 0x8a6a00, emissiveIntensity: 0.4 });
    const matFork       = new THREE.MeshStandardMaterial({ color: 0x3d4348, metalness: 0.85, roughness: 0.35 });
    const matHydraulic  = new THREE.MeshStandardMaterial({ color: 0xe0e0e0, metalness: 0.95, roughness: 0.10 });
    const matHeadlight  = new THREE.MeshBasicMaterial({ color: 0xfffbe6 });
    const matTaillight  = new THREE.MeshBasicMaterial({ color: 0xff2020 });

    // ============================================================
    // 1. CHASIS INFERIOR (placa biselada con esquinas redondeadas)
    // ============================================================
    const chassisShape = new THREE.Shape();
    (function () {
        const w = 1.45, l = 2.55, r = 0.22;
        chassisShape.moveTo(-w/2 + r, -l/2);
        chassisShape.lineTo(w/2 - r, -l/2);
        chassisShape.quadraticCurveTo(w/2, -l/2, w/2, -l/2 + r);
        chassisShape.lineTo(w/2, l/2 - r);
        chassisShape.quadraticCurveTo(w/2, l/2, w/2 - r, l/2);
        chassisShape.lineTo(-w/2 + r, l/2);
        chassisShape.quadraticCurveTo(-w/2, l/2, -w/2, l/2 - r);
        chassisShape.lineTo(-w/2, -l/2 + r);
        chassisShape.quadraticCurveTo(-w/2, -l/2, -w/2 + r, -l/2);
    })();
    const chassisGeo = new THREE.ExtrudeGeometry(chassisShape, {
        depth: 0.42, bevelEnabled: true, bevelThickness: 0.06, bevelSize: 0.05, bevelSegments: 2, curveSegments: 6
    });
    chassisGeo.rotateX(-Math.PI / 2);
    chassisGeo.translate(0, 0.48, 0);
    const chassis = new THREE.Mesh(chassisGeo, matChassis);
    chassis.castShadow = true; chassis.receiveShadow = true;
    playerGroup.add(chassis);

    // Estribos laterales (negros, antideslizantes)
    [-0.72, 0.72].forEach(x => {
        const step = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.04, 0.55), matBlack);
        step.position.set(x, 0.55, 0.1);
        playerGroup.add(step);
    });

    // ============================================================
    // 2. CONTRAPESO TRASERO (masa redondeada que incluye motor)
    // ============================================================
    const cwShape = new THREE.Shape();
    (function () {
        const w = 1.5, h = 1.2, r = 0.32;
        cwShape.moveTo(-w/2, 0);
        cwShape.lineTo(w/2, 0);
        cwShape.lineTo(w/2, h - r);
        cwShape.quadraticCurveTo(w/2, h, w/2 - r, h);
        cwShape.lineTo(-w/2 + r, h);
        cwShape.quadraticCurveTo(-w/2, h, -w/2, h - r);
        cwShape.closePath();
    })();
    const cwGeo = new THREE.ExtrudeGeometry(cwShape, {
        depth: 1.1, bevelEnabled: true, bevelThickness: 0.1, bevelSize: 0.1, bevelSegments: 4, curveSegments: 8
    });
    cwGeo.translate(0, 0, -1.1); // extrusión hacia -Z (atrás)
    const counterweight = new THREE.Mesh(cwGeo, matBodyDark);
    counterweight.position.set(0, 0.5, -0.35);
    counterweight.castShadow = true;
    playerGroup.add(counterweight);

    // Rejillas de ventilación en los laterales del contrapeso
    [-0.76, 0.76].forEach(x => {
        for (let i = 0; i < 4; i++) {
            const louver = new THREE.Mesh(new THREE.BoxGeometry(0.02, 0.04, 0.5), matBlack);
            louver.position.set(x, 0.95 + i * 0.08, -0.85);
            playerGroup.add(louver);
        }
    });

    // Placa INTEP en la parte trasera del contrapeso
    const textTexture = createTextTexture('INTEP');
    const plaqueMat = new THREE.MeshBasicMaterial({ map: textTexture, side: THREE.DoubleSide });
    const plaque = new THREE.Mesh(new THREE.PlaneGeometry(1.0, 0.5), plaqueMat);
    plaque.position.set(0, 1.0, -1.47);
    plaque.rotation.y = Math.PI;
    playerGroup.add(plaque);
    playerGroup.userData.intepText = plaque;

    // Tapa superior del motor (pequeño panel sobre el contrapeso)
    const engineTop = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.06, 0.7), matBodyDark);
    engineTop.position.set(0, 1.73, -0.85);
    playerGroup.add(engineTop);
    for (let i = 0; i < 5; i++) {
        const vent = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.02, 0.04), matBlack);
        vent.position.set(0, 1.77, -1.05 + i * 0.1);
        playerGroup.add(vent);
    }

    // ============================================================
    // 3. ZONA DEL OPERADOR (piso, asiento, dashboard)
    // ============================================================
    // Piso antideslizante
    const floor = new THREE.Mesh(new THREE.BoxGeometry(1.15, 0.04, 0.8), matBlack);
    floor.position.set(0, 0.93, 0.35);
    playerGroup.add(floor);
    // Franjas amarillas del piso
    for (let i = -1; i <= 1; i += 2) {
        const stripe = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.045, 0.06), matYellow);
        stripe.position.set(0, 0.933, 0.35 + i * 0.3);
        playerGroup.add(stripe);
    }

    // Base del asiento
    const seatBase = new THREE.Mesh(new THREE.BoxGeometry(0.52, 0.12, 0.48), matSeat);
    seatBase.position.set(0, 1.2, 0.1);
    playerGroup.add(seatBase);
    // Cojín redondeado
    const seatCushion = new THREE.Mesh(
        new THREE.CylinderGeometry(0.26, 0.26, 0.48, 16, 1, false, 0, Math.PI),
        matSeat
    );
    seatCushion.rotation.x = -Math.PI / 2;
    seatCushion.rotation.z = Math.PI;
    seatCushion.position.set(0, 1.3, 0.1);
    playerGroup.add(seatCushion);

    // Respaldo inclinado
    const backrest = new THREE.Mesh(new THREE.BoxGeometry(0.52, 0.7, 0.1), matSeat);
    backrest.position.set(0, 1.62, -0.15);
    backrest.rotation.x = -0.12;
    playerGroup.add(backrest);
    // Borde superior del respaldo
    const backTop = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 0.52, 12), matSeat);
    backTop.rotation.z = Math.PI / 2;
    backTop.position.set(0, 1.96, -0.23);
    playerGroup.add(backTop);

    // Columna de dirección (angulada)
    const column = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.07, 0.85, 12), matChrome);
    column.position.set(0, 1.32, 0.58);
    column.rotation.x = -0.32;
    playerGroup.add(column);

    // Volante (corona + 3 radios + cubo)
    const swGroup = new THREE.Group();
    const swRing = new THREE.Mesh(new THREE.TorusGeometry(0.18, 0.022, 8, 24), matBlack);
    swGroup.add(swRing);
    for (let i = 0; i < 3; i++) {
        const spoke = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, 0.33, 6), matBlack);
        spoke.rotation.z = (i * Math.PI) / 3;
        swGroup.add(spoke);
    }
    const swHub = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 0.04, 12), matSteel);
    swHub.rotation.x = Math.PI / 2;
    swGroup.add(swHub);
    swGroup.position.set(0, 1.65, 0.72);
    swGroup.rotation.x = (Math.PI / 2) - 0.32;
    playerGroup.add(swGroup);

    // Tablero con 2 relojes (fuel + carga)
    const dash = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.22, 0.12), matBlack);
    dash.position.set(0, 1.4, 0.85);
    dash.rotation.x = 0.35;
    playerGroup.add(dash);
    for (let side = -1; side <= 1; side += 2) {
        const gRing = new THREE.Mesh(new THREE.TorusGeometry(0.065, 0.01, 8, 16), matChrome);
        gRing.position.set(side * 0.18, 1.44, 0.92);
        gRing.rotation.x = 0.35;
        playerGroup.add(gRing);
        const gFace = new THREE.Mesh(
            new THREE.CircleGeometry(0.06, 16),
            new THREE.MeshBasicMaterial({ color: side < 0 ? 0x00ff55 : 0x333333 })
        );
        gFace.position.set(side * 0.18, 1.44, 0.928);
        gFace.rotation.x = 0.35;
        playerGroup.add(gFace);
        if (side < 0) playerGroup.userData.fuelIndicator = gFace;
        else playerGroup.userData.loadIndicator = gFace;
    }

    // Palancas hidráulicas al lado derecho del asiento
    for (let i = 0; i < 2; i++) {
        const leverBar = new THREE.Mesh(new THREE.CylinderGeometry(0.022, 0.022, 0.42, 8), matChrome);
        leverBar.position.set(0.38, 1.52, 0.1 + i * 0.12);
        leverBar.rotation.x = 0.18;
        playerGroup.add(leverBar);
        const knob = new THREE.Mesh(
            new THREE.SphereGeometry(0.045, 12, 12),
            new THREE.MeshStandardMaterial({ color: i === 0 ? 0xcc0000 : 0x0055cc })
        );
        knob.position.set(0.38, 1.72, 0.13 + i * 0.12);
        playerGroup.add(knob);
    }

    // ============================================================
    // 4. JAULA DE SEGURIDAD ROPS (overhead guard, abierta)
    // ============================================================
    const postGeo = new THREE.CylinderGeometry(0.05, 0.05, 1.55, 10);
    const postPositions = [[-0.6, 0.82], [0.6, 0.82], [-0.6, -0.45], [0.6, -0.45]];
    postPositions.forEach(([x, z]) => {
        const post = new THREE.Mesh(postGeo, matBodyDark);
        post.position.set(x, 1.78, z);
        playerGroup.add(post);
    });
    // Marco superior
    [-0.45, 0.82].forEach(z => {
        const bar = new THREE.Mesh(new THREE.BoxGeometry(1.28, 0.08, 0.08), matBodyDark);
        bar.position.set(0, 2.53, z);
        playerGroup.add(bar);
    });
    [-0.6, 0.6].forEach(x => {
        const bar = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.08, 1.32), matBodyDark);
        bar.position.set(x, 2.53, 0.185);
        playerGroup.add(bar);
    });
    // Rejilla del techo (barrotes transversales + longitudinales)
    for (let i = 1; i < 6; i++) {
        const g = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.025, 0.03), matBlack);
        g.position.set(0, 2.53, -0.45 + i * 0.22);
        playerGroup.add(g);
    }
    for (let i = 1; i < 4; i++) {
        const g = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.025, 1.24), matBlack);
        g.position.set(-0.6 + i * 0.3, 2.53, 0.185);
        playerGroup.add(g);
    }

    // ============================================================
    // 5. MASTIL (columnas de viga I + cilindro hidráulico + cadenas)
    // ============================================================
    const mastGroup = new THREE.Group();
    mastGroup.name = 'mast';

    // Perfil viga I
    const ibeam = new THREE.Shape();
    (function () {
        const bw = 0.14, bh = 0.16, tw = 0.035;
        ibeam.moveTo(-bw/2, -bh/2);
        ibeam.lineTo(bw/2, -bh/2);
        ibeam.lineTo(bw/2, -bh/2 + tw);
        ibeam.lineTo(tw/2, -bh/2 + tw);
        ibeam.lineTo(tw/2, bh/2 - tw);
        ibeam.lineTo(bw/2, bh/2 - tw);
        ibeam.lineTo(bw/2, bh/2);
        ibeam.lineTo(-bw/2, bh/2);
        ibeam.lineTo(-bw/2, bh/2 - tw);
        ibeam.lineTo(-tw/2, bh/2 - tw);
        ibeam.lineTo(-tw/2, -bh/2 + tw);
        ibeam.lineTo(-bw/2, -bh/2 + tw);
        ibeam.closePath();
    })();
    const ibeamGeo = new THREE.ExtrudeGeometry(ibeam, { depth: 2.8, bevelEnabled: false });
    ibeamGeo.rotateX(-Math.PI / 2);
    [-0.42, 0.42].forEach(x => {
        const col = new THREE.Mesh(ibeamGeo, matSteel);
        col.position.set(x, 0.3, 1.15);
        mastGroup.add(col);
    });
    // Cross-bar superior del mástil
    const mastTop = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.1, 0.14), matSteel);
    mastTop.position.set(0, 3.05, 1.15);
    mastGroup.add(mastTop);

    // Cilindro hidráulico principal (detrás del mástil, centrado)
    const hydraCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 2.0, 14), matHydraulic);
    hydraCyl.position.set(0, 1.4, 1.02);
    mastGroup.add(hydraCyl);
    const hydraPiston = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 1.4, 12), matChrome);
    hydraPiston.position.set(0, 2.6, 1.02);
    mastGroup.add(hydraPiston);
    // Cadenas de elevación
    [-0.1, 0.1].forEach(x => {
        const chain = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 2.4, 6), matBlack);
        chain.position.set(x, 1.7, 1.02);
        mastGroup.add(chain);
    });

    // ============================================================
    // 6. CARRO + HORQUILLAS EN L (grupo móvil)
    // ============================================================
    const forkGroup = new THREE.Group();
    forkGroup.name = 'forks';

    // Plancha del carro
    const carriage = new THREE.Mesh(new THREE.BoxGeometry(1.05, 0.6, 0.07), matSteel);
    carriage.position.set(0, 0.3, 0);
    forkGroup.add(carriage);
    // Refuerzos del carro
    [0.2, -0.2].forEach(y => {
        const bar = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.04, 0.09), matSteel);
        bar.position.set(0, 0.3 + y, -0.03);
        forkGroup.add(bar);
    });
    // Horquillas en L (parte vertical + uña horizontal + punta biselada)
    [-0.33, 0.33].forEach(x => {
        const vert = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.6, 0.06), matFork);
        vert.position.set(x, 0.3, 0.07);
        forkGroup.add(vert);
        const tine = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.06, 1.3), matFork);
        tine.position.set(x, 0.0, 0.75);
        forkGroup.add(tine);
        const tip = new THREE.Mesh(new THREE.ConeGeometry(0.09, 0.26, 4), matFork);
        tip.rotation.x = Math.PI / 2;
        tip.rotation.z = Math.PI / 4;
        tip.scale.set(1, 1, 0.55);
        tip.position.set(x, -0.005, 1.5);
        forkGroup.add(tip);
    });
    forkGroup.position.set(0, forkHeight, 1.22);
    mastGroup.add(forkGroup);

    playerGroup.add(mastGroup);
    playerGroup.userData.forkGroup = forkGroup;
    playerGroup.userData.mastGroup = mastGroup;

    // Cilindros de inclinación (chasis ↔ base del mástil)
    [-0.4, 0.4].forEach(x => {
        const tc = new THREE.Mesh(new THREE.CylinderGeometry(0.055, 0.055, 0.55, 10), matHydraulic);
        tc.position.set(x, 0.95, 0.82);
        tc.rotation.x = -0.7;
        playerGroup.add(tc);
        const tr = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.35, 8), matChrome);
        tr.position.set(x, 1.12, 0.98);
        tr.rotation.x = -0.7;
        playerGroup.add(tr);
    });

    // ============================================================
    // 7. RUEDAS DETALLADAS (neumático + llanta + cubo + pernos)
    // ============================================================
    function createWheel(radius, width) {
        const g = new THREE.Group();
        // Neumático
        const tire = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius, width, 24), matRubber);
        g.add(tire);
        // Labrado (taquitos alrededor)
        for (let i = 0; i < 14; i++) {
            const tread = new THREE.Mesh(new THREE.BoxGeometry(width * 0.85, 0.025, 0.06), matRubber);
            const ang = (i / 14) * Math.PI * 2;
            tread.position.set(0, Math.sin(ang) * (radius - 0.01), Math.cos(ang) * (radius - 0.01));
            tread.rotation.x = ang;
            g.add(tread);
        }
        // Rin
        const rim = new THREE.Mesh(new THREE.CylinderGeometry(radius * 0.58, radius * 0.58, width + 0.012, 16), matRim);
        g.add(rim);
        // Cubo central
        const cap = new THREE.Mesh(new THREE.CylinderGeometry(radius * 0.18, radius * 0.18, width + 0.04, 12), matChrome);
        g.add(cap);
        // Pernos
        for (let i = 0; i < 5; i++) {
            const bolt = new THREE.Mesh(new THREE.CylinderGeometry(0.022, 0.022, width + 0.02, 6), matChrome);
            const ang = (i / 5) * Math.PI * 2;
            bolt.position.set(0, Math.sin(ang) * radius * 0.38, Math.cos(ang) * radius * 0.38);
            g.add(bolt);
        }
        g.rotation.z = Math.PI / 2;
        return g;
    }

    // Ruedas DELANTERAS (grandes, junto al mástil)
    const frontWheelsGroup = new THREE.Group();
    frontWheelsGroup.name = 'frontWheels';
    [-0.78, 0.78].forEach(x => {
        const wg = new THREE.Group();
        const wheel = createWheel(0.4, 0.32);
        wg.add(wheel);
        wg.position.set(x, 0.4, 1.0);
        wg.userData.wheel = wheel;
        wg.userData.steeringAngle = 0;
        frontWheelsGroup.add(wg);
    });
    playerGroup.add(frontWheelsGroup);
    playerGroup.userData.frontWheels = frontWheelsGroup.children;
    playerGroup.userData.frontWheelsGroup = frontWheelsGroup;

    // Ruedas TRASERAS (pequeñas)
    const rearWheelsGroup = new THREE.Group();
    rearWheelsGroup.name = 'rearWheels';
    [-0.6, 0.6].forEach(x => {
        const wg = new THREE.Group();
        const wheel = createWheel(0.3, 0.25);
        wg.add(wheel);
        wg.position.set(x, 0.3, -0.9);
        wg.userData.wheel = wheel;
        rearWheelsGroup.add(wg);
    });
    playerGroup.add(rearWheelsGroup);
    playerGroup.userData.rearWheels = rearWheelsGroup.children;

    // ============================================================
    // 8. LUCES
    // ============================================================
    // Faros delanteros (carcasa cromada + lente)
    [-0.48, 0.48].forEach(x => {
        const housing = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 0.12, 12), matChrome);
        housing.rotation.x = Math.PI / 2;
        housing.position.set(x, 0.85, 1.3);
        playerGroup.add(housing);
        const lens = new THREE.Mesh(new THREE.CircleGeometry(0.09, 16), matHeadlight);
        lens.position.set(x, 0.85, 1.37);
        playerGroup.add(lens);
    });
    // Luces traseras
    [-0.55, 0.55].forEach(x => {
        const tl = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.1, 0.03), matTaillight);
        tl.position.set(x, 0.75, -1.465);
        playerGroup.add(tl);
    });
    // Baliza giratoria amarilla (sobre el techo de la jaula)
    const beaconBase = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.09, 0.05, 12), matBlack);
    beaconBase.position.set(0, 2.58, 0.18);
    playerGroup.add(beaconBase);
    const beacon = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.14, 12), matYellow);
    beacon.position.set(0, 2.67, 0.18);
    playerGroup.add(beacon);
    playerGroup.userData.warningLight = beacon;

    // ============================================================
    // 9. DETALLES: espejos, agarraderas, escape
    // ============================================================
    // Espejos con brazo
    [-1, 1].forEach(sign => {
        const arm = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 0.24, 6), matBlack);
        arm.position.set(sign * 0.64, 2.3, 0.75);
        arm.rotation.z = sign * -0.55;
        playerGroup.add(arm);
        const mbody = new THREE.Mesh(new THREE.BoxGeometry(0.17, 0.12, 0.03), matBlack);
        mbody.position.set(sign * 0.78, 2.33, 0.75);
        playerGroup.add(mbody);
        const mface = new THREE.Mesh(
            new THREE.PlaneGeometry(0.14, 0.1),
            new THREE.MeshStandardMaterial({ color: 0xccddee, metalness: 1, roughness: 0.1 })
        );
        mface.position.set(sign * 0.78, 2.33, 0.766);
        playerGroup.add(mface);
    });

    // Agarradera amarilla de seguridad
    const grab = new THREE.Mesh(new THREE.CylinderGeometry(0.022, 0.022, 0.42, 8), matYellow);
    grab.position.set(-0.52, 2.0, -0.25);
    grab.rotation.z = Math.PI / 2;
    playerGroup.add(grab);

    // Tubo de escape vertical
    const stack = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 0.55, 10), matBlack);
    stack.position.set(-0.55, 1.95, -0.4);
    playerGroup.add(stack);
    const stackCap = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.05, 10), matChrome);
    stackCap.position.set(-0.55, 2.25, -0.4);
    playerGroup.add(stackCap);

    // Interior de cabina (solo visible en primera persona)
    const cabinInterior = new THREE.Group();
    cabinInterior.visible = false;
    const hud = new THREE.Mesh(
        new THREE.PlaneGeometry(0.6, 0.12),
        new THREE.MeshBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.55 })
    );
    hud.position.set(0, 2.15, 0.72);
    cabinInterior.add(hud);
    playerGroup.add(cabinInterior);
    playerGroup.userData.cabinInterior = cabinInterior;

    // Indicador de batería (compatibilidad con código existente)
    const batteryLight = new THREE.Mesh(
        new THREE.BoxGeometry(0.25, 0.05, 0.03),
        new THREE.MeshBasicMaterial({ color: 0x00ff00 })
    );
    batteryLight.position.set(0, 1.36, 0.94);
    batteryLight.rotation.x = 0.35;
    playerGroup.add(batteryLight);
    playerGroup.userData.batteryLight = batteryLight;

    // ===== Posición inicial =====
    playerGroup.position.set(0, 0, 15);
    scene.add(playerGroup);

    console.log('Montacargas INTEP rediseñado — modelo industrial con vigas I, contrapeso curvo y jaula ROPS');
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
        if ((key === 'q' || key === 'e') && gameStarted && !gameEnded) { e.preventDefault(); moveForks(key === 'q' ? -1 : 1); }
        if (key === 't' && gameStarted && !gameEnded) { e.preventDefault(); showTerminal(); }
    });
    window.addEventListener('keyup', (e) => { keys[e.key.toLowerCase()] = false; });
    window.addEventListener('resize', onWindowResize);
}

function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
}

// ==================== CONTROL DE HORQUILLAS ====================
function moveForks(direction) {
    if (forkMoving || !playerGroup.userData.forkGroup) return;
    
    const step = 0.05;
    const newHeight = forkHeight + (direction * step);
    
    // Limitar altura
    if (newHeight < FORK_MIN || newHeight > FORK_MAX) return;
    
    forkMoving = true;
    forkHeight = newHeight;
    
    // Actualizar posición del grupo de horquillas
    playerGroup.userData.forkGroup.position.y = forkHeight;
    
    // Actualizar HUD
    updateForkHeightDisplay();
    
    // Sonido de movimiento de horquillas
    generateTone(300 + forkHeight * 50, 0.05, 'sawtooth', 0.1);
    
    // Permitir movimiento nuevamente después de un breve delay
    setTimeout(() => { forkMoving = false; }, 50);
}

function updateForkHeightDisplay() {
    const display = document.getElementById('forkHeight');
    if (display) {
        const percent = Math.round(((forkHeight - FORK_MIN) / (FORK_MAX - FORK_MIN)) * 100);
        display.textContent = `Horquillas: ${percent}%`;
        display.style.color = percent > 80 ? '#e74c3c' : percent > 50 ? '#f1c40f' : '#2ecc71';
    }
}

// ==================== CONTROL DE CAMARA ====================
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
    return { follow: '\uD83D\uDC41\uFE0F SEGUIR', free: '\uD83D\uDCF7 LIBRE', first: '\uD83C\uDFA5 1RA PERSONA' }[cameraMode] + ' activado';
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
    const texts = { follow: '\uD83D\uDC41\uFE0F SEGUIR', free: '\uD83D\uDCF7 LIBRE', first: '\uD83C\uDFA5 1RA PERSONA' };
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
        // Camara en la cabeza del operador (nueva cabina ROPS)
        const cabinOffsetZ = 0.05;  // justo delante del respaldo
        const headHeight = 1.95;    // altura de la vista del operador
        camera.position.set(
            playerGroup.position.x + Math.sin(playerRotation) * cabinOffsetZ,
            headHeight,
            playerGroup.position.z + Math.cos(playerRotation) * cabinOffsetZ
        );
        camera.lookAt(
            playerGroup.position.x + Math.sin(playerRotation) * 6,
            headHeight - 0.35,  // mirada ligeramente hacia las horquillas
            playerGroup.position.z + Math.cos(playerRotation) * 6
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
    if (gamePaused) return;
    if (currentMission === 0) { handleFreePickup(); return; }

    const task = missionTasks[currentTask];
    if (!task) return;

    if (task.type === 'pickup' && !hasLoad) {
        // Check distance to the specific pallet
        const pallet = pallets[task.palletIndex];
        if (!pallet || pallet.userData.pickedUp || pallet.userData.delivered) return;
        const dist = playerGroup.position.distanceTo(pallet.position);
        if (dist < 3.5) {
            if (task.quiz) {
                showQuiz(task.quiz, function() { doPickup(pallet, task); });
            } else {
                doPickup(pallet, task);
            }
        }
    } else if (task.type === 'deliver' && hasLoad) {
        const targetZone = zones.find(z => z.label === task.zone);
        if (targetZone) {
            const distToZone = Math.sqrt(
                Math.pow(playerGroup.position.x - targetZone.x, 2) +
                Math.pow(playerGroup.position.z - targetZone.z, 2)
            );
            if (distToZone < 7) {
                if (task.quiz) {
                    showQuiz(task.quiz, function() { doDeliver(targetZone, task); });
                } else {
                    doDeliver(targetZone, task);
                }
            } else {
                showNotification("\u26A0\uFE0F Acercate mas a la zona " + task.zone, "#f1c40f");
            }
        }
    }
}

function doPickup(pallet, task) {
    hasLoad = true;
    currentLoad = pallet;
    updateLoadIndicator();
    showVisualFeedback('pickup');
    pallet.userData.pickedUp = true;
    loadsCount++;
    if (loadsCount === 1) unlockAchievement('firstBlood');
    playSoundPickup();
    showNotification("\u2705 " + (task.concept || 'Pallet cargado'), "#00ffcc");
    advanceTask();
}

function doDeliver(targetZone, task) {
    hasLoad = false;
    updateLoadIndicator();
    showVisualFeedback('deliver');
    playSoundDrop();
    currentLoad.userData.delivered = true;
    currentLoad.visible = false;
    currentLoad = null;

    if (zoneCounts[targetZone.label] !== undefined) {
        zoneCounts[targetZone.label]++;
    }
    updateInventoryDisplay();

    const bonus = timeLeft > 180 ? 50 : 20;
    score += 100 + bonus;
    playSoundComplete();
    showNotification("\uD83D\uDCDA " + (task.concept || 'Entrega completada'), "#3498db");
    advanceTask();
}

function advanceTask() {
    currentTask++;
    if (currentTask >= missionTasks.length) {
        showNotification("\uD83C\uDF89 \u00A1Mision completada!", "#2ecc71");
        setTimeout(function() {
            const mission = MISSIONS[currentMission];
            if (mission && mission.completionCard) {
                showConceptCard(mission.completionCard);
            } else {
                endGame();
            }
        }, 1500);
    } else {
        updateMission();
    }
}

function handleFreePickup() {
    // Simplified pickup for free mode - pick up any pallet within range
    if (!hasLoad) {
        for (let i = 0; i < pallets.length; i++) {
            var p = pallets[i];
            if (p.userData.pickedUp || p.userData.delivered) continue;
            var dist = playerGroup.position.distanceTo(p.position);
            if (dist < 3.5) {
                hasLoad = true;
                currentLoad = p;
                p.userData.pickedUp = true;
                playSoundPickup();
                showNotification("\u2705 Pallet cargado (modo libre)", "#00ffcc");
                return;
            }
        }
    } else {
        // Drop anywhere in free mode
        hasLoad = false;
        playSoundDrop();
        currentLoad.userData.pickedUp = false;
        currentLoad = null;
        showNotification("\uD83D\uDCE6 Pallet descargado", "#3498db");
    }
}

// ==================== QUIZ OVERLAY ====================
function showQuiz(quiz, onComplete) {
    gamePaused = true;
    quizCallback = onComplete;
    quizzesTotal++;

    const overlay = document.getElementById('quizOverlay');
    document.getElementById('quizQuestion').textContent = quiz.q;
    document.getElementById('quizFeedback').textContent = '';
    document.getElementById('quizFeedback').className = '';

    const optionsDiv = document.getElementById('quizOptions');
    optionsDiv.innerHTML = '';

    quiz.options.forEach(function(opt, i) {
        const btn = document.createElement('button');
        btn.className = 'quiz-option-btn';
        btn.textContent = opt;
        btn.onclick = function() {
            if (i === quiz.correct) {
                // Correct!
                quizzesCorrect++;
                btn.classList.add('correct');
                document.getElementById('quizFeedback').textContent = '\u2705 \u00A1Correcto! ' + (quiz.tip || '');
                document.getElementById('quizFeedback').className = 'quiz-feedback-correct';
                score += 25; // bonus for correct answer
                playSoundPickup();
                setTimeout(function() {
                    closeQuiz();
                    if (quizCallback) quizCallback();
                }, 1500);
            } else {
                // Wrong
                btn.classList.add('wrong');
                document.getElementById('quizFeedback').textContent = '\u274C Incorrecto. ' + (quiz.tip || 'Intenta de nuevo.');
                document.getElementById('quizFeedback').className = 'quiz-feedback-wrong';
                playSoundCollision();
                // Don't close - let them try again, but disable this option
                btn.disabled = true;
                score -= 10;
            }
        };
        optionsDiv.appendChild(btn);
    });

    overlay.style.display = 'flex';
}

function closeQuiz() {
    document.getElementById('quizOverlay').style.display = 'none';
    gamePaused = false;
    quizCallback = null;
}

// ==================== CONCEPT CARD ====================
function showConceptCard(card) {
    gamePaused = true;

    document.getElementById('conceptTitle').textContent = card.title;
    document.getElementById('conceptText').textContent = card.concept;
    document.getElementById('conceptModuleRef').textContent = 'Modulo ' + card.moduleRef + ' del curso teorico';
    document.getElementById('conceptCard').style.display = 'flex';

    // Set up the "go to course" button
    const courseBtn = document.getElementById('conceptCourseBtn');
    if (courseBtn) {
        courseBtn.onclick = function() {
            window.open('curso.html', '_self');
        };
    }
}

function closeConceptCard() {
    document.getElementById('conceptCard').style.display = 'none';
    gamePaused = false;
    endGame();
}

// ==================== TERMINAL PORTÁTIL ====================
function showTerminal() {
    gamePaused = true;
    document.getElementById('terminalOverlay').style.display = 'flex';
    document.getElementById('terminalInput').focus();
}

function closeTerminal() {
    document.getElementById('terminalOverlay').style.display = 'none';
    gamePaused = false;
}

function setupTerminal() {
    const overlay = document.getElementById('terminalOverlay');
    if (!overlay) return;
    
    document.getElementById('closeTerminal').addEventListener('click', closeTerminal);
    document.getElementById('terminalSend').addEventListener('click', handleTerminalCommand);
    document.getElementById('terminalClear').addEventListener('click', () => {
        document.getElementById('terminalOutput').textContent = '';
    });
    document.getElementById('terminalHelp').addEventListener('click', () => {
        appendTerminalOutput('Comandos disponibles: help, status, mission, inventory, edi, scan');
    });
    
    const input = document.getElementById('terminalInput');
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleTerminalCommand();
    });
    
    // Cerrar con Escape
    overlay.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeTerminal();
    });
}

function handleTerminalCommand() {
    const input = document.getElementById('terminalInput');
    const command = input.value.trim().toLowerCase();
    if (!command) return;
    
    appendTerminalOutput(`> ${command}`);
    
    switch(command) {
        case 'help':
            appendTerminalOutput('Comandos: help, status, mission, inventory, edi, scan');
            break;
        case 'status':
            appendTerminalOutput(`Misión: ${currentMission !== null ? 'Activa' : 'No activa'}`);
            appendTerminalOutput(`Tarea: ${currentTask + 1}/${missionTasks.length}`);
            appendTerminalOutput(`Score: ${score}`);
            break;
        case 'mission':
            if (currentMission !== null && missionTasks[currentTask]) {
                const task = missionTasks[currentTask];
                appendTerminalOutput(`Tarea actual: ${task.text}`);
                if (task.zone) appendTerminalOutput(`Zona destino: ${task.zone}`);
            } else {
                appendTerminalOutput('No hay misión activa.');
            }
            break;
        case 'inventory':
            appendTerminalOutput(`Carga: ${hasLoad ? 'Sí' : 'No'}`);
            appendTerminalOutput(`Pallets entregados: ${loadsCount}`);
            const summary = getInventorySummary();
            appendTerminalOutput(`Productos: ${summary.inStock}/${summary.totalProducts}`);
            appendTerminalOutput(`Ubicaciones: ${summary.occupiedLocations}/${summary.totalLocations}`);
            appendTerminalOutput(`Órdenes pendientes: ${pickingQueue.length}`);
            break;
        case 'edi':
            appendTerminalOutput('Sistema EDI (Intercambio Electrónico de Datos)');
            appendTerminalOutput('Conectado a servidor central. Estado: OK');
            appendTerminalOutput('Última sincronización: ' + new Date().toLocaleTimeString());
            break;
        case 'scan':
            appendTerminalOutput('Escaneando entorno...');
            // Simular lectura de códigos de barras
            setTimeout(() => appendTerminalOutput('Código: INTEP-ALM-2025'), 300);
            break;
        default:
            appendTerminalOutput(`Comando no reconocido: "${command}". Escribe "help" para ayuda.`);
    }
    
    input.value = '';
    input.focus();
}

function appendTerminalOutput(text) {
    const output = document.getElementById('terminalOutput');
    output.textContent += '\n' + text;
    output.scrollTop = output.scrollHeight;
}

// ==================== MISSION UPDATE ====================
function updateMission() {
    if (!missionTasks || currentTask >= missionTasks.length) return;
    const task = missionTasks[currentTask];
    document.getElementById('missionText').textContent = task.text;
    document.getElementById('tasks').textContent = currentTask;
    document.getElementById('totalTasks').textContent = missionTasks.length;
    updateProgress();
}

function updateProgress() {
    const total = missionTasks.length || 1;
    const progress = (currentTask / total) * 100;
    progressFill.style.width = progress + '%';
    document.getElementById('progressText').textContent = Math.round(progress) + '%';
}

function updateInventoryDisplay() {
    document.getElementById('invA').textContent = zoneCounts.ALMACEN;
    document.getElementById('invB').textContent = zoneCounts.PICKING;
    document.getElementById('invC').textContent = zoneCounts.DESPACHO;
    document.getElementById('invTotal').textContent = zoneCounts.ALMACEN + zoneCounts.PICKING + zoneCounts.DESPACHO;
    document.getElementById('invTotalMax').textContent = missionTasks ? missionTasks.length : 0;
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

function updateLoadIndicator() {
    const indicator = document.getElementById('loadIndicator');
    const status = document.getElementById('loadStatus');
    if (!indicator || !status) return;
    
    if (hasLoad) {
        indicator.style.display = 'flex';
        status.textContent = 'SI';
        indicator.classList.add('active');
    } else {
        indicator.style.display = 'none';
        status.textContent = 'NO';
        indicator.classList.remove('active');
    }
}

function showVisualFeedback(type) {
    let element;
    switch(type) {
        case 'pickup':
            element = document.getElementById('loadIndicator');
            break;
        case 'deliver':
            element = document.getElementById('loadIndicator');
            break;
        case 'inventory':
            element = document.getElementById('inventoryPanel');
            break;
        case 'shift':
            element = document.getElementById('shiftPanel');
            break;
        default:
            return;
    }
    
    if (element) {
        element.classList.add('pulse');
        setTimeout(() => element.classList.remove('pulse'), 1000);
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
    const mission = MISSIONS[currentMission];
    const baseTime = (mission && mission.timeLimit > 0) ? mission.timeLimit : 300;
    const timeUsed = baseTime - timeLeft;
    if (timeUsed < 180) unlockAchievement('speedster');
    if (collisions === 0) unlockAchievement('perfect');
    const efficiency = Math.max(0, 100 - (collisions * 5));
    if (efficiency === 100) unlockAchievement('efficient');
    const finalScore = calculateFinalScore();
    if (finalScore >= 600) unlockAchievement('master');
}

// ==================== NOTIFICACIONES ====================
function showNotification(text, color, type) {
    type = type || '';
    notification.textContent = text;
    notification.style.background = color;
    notification.className = type;
    notification.style.display = 'block';
    setTimeout(() => { notification.style.display = 'none'; }, 2500);
}

function showPriorityNotification(text, priority = 'normal') {
    const priorityColors = {
        high: '#e74c3c',   // Rojo
        normal: '#3498db', // Azul
        low: '#2ecc71'     // Verde
    };
    const icon = {
        high: '🚨',
        normal: 'ℹ️',
        low: '📄'
    }[priority];
    
    showNotification(`${icon} ${text}`, priorityColors[priority], priority);
}

// ==================== COLISIONES ====================
function checkCollision(newX, newZ) {
    // Colisiones con estanterías
    for (let rack of racks) {
        const dx = Math.abs(newX - rack.x);
        const dz = Math.abs(newZ - rack.z);
        if (dx < (rack.width / 2 + 1) && dz < (rack.depth / 2 + 1)) return true;
    }
    
    // Colisiones con objetos (columnas, contenedores, etc.)
    for (let obj of collisionObjects) {
        const dx = Math.abs(newX - obj.x);
        const dz = Math.abs(newZ - obj.z);
        if (dx < (obj.width / 2 + 0.5) && dz < (obj.depth / 2 + 0.5)) return true;
    }
    
    // Límites del almacén
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
            const palletColor = pallet.userData.color || 0xffffff;
            const isCurrent = pallet.userData.taskIndex === currentTask;
            ctx.fillStyle = isCurrent
                ? '#00ffcc'
                : '#' + palletColor.toString(16).padStart(6, '0');
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

// ==================== ESTADISTICAS ====================
function updateStats() {
    const mission = MISSIONS[currentMission];
    var timeUsed;
    if (mission && mission.timeLimit > 0) {
        timeUsed = mission.timeLimit - timeLeft;
    } else {
        timeUsed = timeLeft; // counting up
    }
    document.getElementById('statTime').textContent = formatTime(Math.abs(timeUsed));
    document.getElementById('statLoads').textContent = loadsCount;
    document.getElementById('statCollisions').textContent = collisions;
    document.getElementById('statEfficiency').textContent = Math.max(0, 100 - (collisions * 5)) + '%';
}

// ==================== CONSEJOS EDUCATIVOS ====================
function showEducationalTip() {
    if (!gameStarted || gameEnded) return;

    // Pick a random category from all available tips
    const categories = Object.keys(EDUCATIONAL_TIPS);
    const tipCategory = categories[Math.floor(Math.random() * categories.length)];
    const tips = EDUCATIONAL_TIPS[tipCategory];
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

// ==================== INTEGRACION CON CURSO ====================
function getCourseProgress() {
    try {
        const data = localStorage.getItem('intep_almacenamiento_progress');
        return data ? JSON.parse(data) : null;
    } catch (e) {
        return null;
    }
}

function syncWithCourse() {
    try {
        const data = localStorage.getItem('intep_almacenamiento_progress');
        if (!data) return { unlockedMissions: [1, 0] };

        const progress = JSON.parse(data);
        const unlocked = [1, 0]; // M1 tutorial + free always available

        for (let i = 1; i <= 6; i++) {
            if (progress.moduleProgress && progress.moduleProgress[i] && progress.moduleProgress[i].completed) {
                if (!unlocked.includes(i)) unlocked.push(i);
            }
        }
        return { unlockedMissions: unlocked, progress };
    } catch(e) {
        return { unlockedMissions: [1, 0] };
    }
}

function getSimulatorProgress() {
    try {
        const data = localStorage.getItem('intep_simulador_progress');
        return data ? JSON.parse(data) : { missions: {} };
    } catch(e) {
        return { missions: {} };
    }
}

function saveSimulatorProgress(missionNum, scoreVal, time) {
    const key = 'intep_simulador_progress';
    var data;
    try {
        data = JSON.parse(localStorage.getItem(key) || '{"missions":{}}');
    } catch(e) {
        data = { missions: {} };
    }

    const existing = data.missions[missionNum];
    data.missions[missionNum] = {
        completed: true,
        score: Math.max(scoreVal, existing?.score || 0),
        bestTime: existing?.bestTime ? Math.min(time, existing.bestTime) : time,
        date: new Date().toLocaleDateString(),
        quizAccuracy: quizzesTotal > 0 ? Math.round((quizzesCorrect / quizzesTotal) * 100) : 0
    };
    localStorage.setItem(key, JSON.stringify(data));
}

function unlockEducationalAchievement(category) {
    if (category === 'ABC') unlockAchievement('abcMaster');
    if (category === 'FIFO' || category === 'FEFO') unlockAchievement('fifoExpert');
    if (collisions === 0 && loadsCount >= 3) unlockAchievement('safetyFirst');
}

function formatTime(seconds) {
    return `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
}

// ==================== MISSION SELECT ====================
function renderMissionSelect() {
    const courseSync = syncWithCourse();
    const simProgress = getSimulatorProgress();
    const container = document.getElementById('missionGrid');
    if (!container) return;

    container.innerHTML = '';

    // Order: 1,2,3,4,5,6,0
    const order = [1, 2, 3, 4, 5, 6, 0];

    order.forEach(function(num) {
        const mission = MISSIONS[num];
        if (!mission) return;

        const unlocked = courseSync.unlockedMissions.includes(num);
        const completed = simProgress.missions[num]?.completed || false;
        const bestScore = simProgress.missions[num]?.score || 0;

        const card = document.createElement('div');
        card.className = 'mission-card' + (unlocked ? '' : ' locked') + (completed ? ' completed' : '');
        card.dataset.mission = num;

        card.innerHTML = `
            <div class="mission-card-header">
                <span class="mission-icon">${mission.icon}</span>
                <span class="mission-num">${num > 0 ? 'M' + num : ''}</span>
                ${completed ? '<span class="mission-check">\u2705</span>' : ''}
            </div>
            <h3 class="mission-card-title">${mission.title}</h3>
            <p class="mission-card-module">${mission.module}</p>
            <p class="mission-card-desc">${mission.description}</p>
            ${completed ? '<p class="mission-best-score">Mejor: ' + bestScore + ' pts</p>' : ''}
            <div class="mission-card-footer">
                ${unlocked
                    ? '<button class="mission-start-btn" onclick="selectMission(' + num + ')">' + (completed ? '\uD83D\uDD04 REPETIR' : '\u25B6 INICIAR') + '</button>'
                    : '<span class="mission-locked-text">\uD83D\uDD12 Completa el Modulo ' + num + ' en el curso teorico</span>'
                }
            </div>
            ${mission.timeLimit > 0 ? '<span class="mission-time">\u23F1 ' + formatTime(mission.timeLimit) + '</span>' : '<span class="mission-time">\u221E Sin limite</span>'}
        `;

        container.appendChild(card);
    });
}

function selectMission(num) {
    playSoundStart();
    startGame(num);
}

// ==================== JUEGO ====================
function startGame(missionNum) {
    currentMission = missionNum;
    const mission = MISSIONS[missionNum];
    if (!mission) return;

    missionTasks = mission.tasks;

    startScreen.style.display = 'none';
    rankingScreen.style.display = 'none';
    hud.style.display = 'flex';
    progressBar.style.display = 'block';
    minimap.style.display = 'block';
    cameraModeIndicator.style.display = 'block';
    cameraPanel.style.display = 'block';
    inventoryPanel.style.display = 'block';
    statsPanel.style.display = 'block';
    shiftPanel.style.display = 'block';
    zoomIndicator.style.display = 'block';

    gameStarted = true;
    gameEnded = false;
    gamePaused = false;
    achievements = [];
    currentTask = 0;
    score = 0;
    hasLoad = false;
    currentLoad = null;
    updateLoadIndicator();
    collisions = 0;
    loadsCount = 0;
    fuel = 100;
    zoneCounts = { ALMACEN: 0, PICKING: 0, DESPACHO: 0, ANDEN: 0, FRIO: 0, DEV: 0 };
    forkHeight = 0.28;
    forkMoving = false;
    quizzesCorrect = 0;
    quizzesTotal = 0;
    visitTaskTriggered = false;
    // NOTA: 'inventory' es ahora un array de productos, no este objeto simple
    // Se mantiene el array existente del sistema de inventario
    cameraMode = 'follow';
    cameraDistance = 20;

    // Create pallets for this mission
    createMissionPallets(missionNum);
    startMissionWithShift(missionNum);
    playerGroup.position.set(0, 0, 20);
    playerGroup.rotation.y = 0;
    playerRotation = 0;
    playerSpeed = 0;
    playerVelocityX = 0;
    playerVelocityZ = 0;
    if (playerGroup.userData.forkGroup) {
        playerGroup.userData.forkGroup.position.y = forkHeight;
    }

    // Timer setup
    if (mission.timeLimit > 0) {
        timeLeft = mission.timeLimit;
        document.getElementById('timer').textContent = formatTime(timeLeft);
        timerInterval = setInterval(function() {
            timeLeft--;
            fuel = Math.max(0, fuel - 0.05);
            updateFuelDisplay();
            document.getElementById('timer').textContent = formatTime(timeLeft);
            document.getElementById('score').textContent = score;
            updateStats();
            if (timeLeft <= 0) endGame();
        }, 1000);
    } else {
        timeLeft = 0;
        document.getElementById('timer').textContent = '\u221E';
        // No timer for tutorial/free mode, but still update stats
        timerInterval = setInterval(function() {
            timeLeft++;
            document.getElementById('timer').textContent = formatTime(timeLeft);
            document.getElementById('score').textContent = score;
            updateStats();
        }, 1000);
    }

    if (missionTasks.length > 0) {
        updateMission();
    } else {
        document.getElementById('missionText').textContent = 'Modo Libre \u2014 Explora el almacen';
    }

    updateInventoryDisplay();
    updateFuelDisplay();
    updateForkHeightDisplay();
    updateCameraIndicator();

    // Show mission title
    setTimeout(function() {
        showNotification("\uD83C\uDFAF " + mission.title, "#3498db");
    }, 500);

    if (missionTasks.length > 0 && missionTasks[0].concept) {
        setTimeout(function() {
            showNotification("\uD83D\uDCA1 " + missionTasks[0].concept, "#00ffcc");
        }, 2500);
    }

    startTipTimer();
}

function calculateFinalScore() {
    const collisionPenalty = collisions * 25;
    const fuelPenalty = fuel < 20 ? 50 : 0;
    return Math.max(0, score - collisionPenalty - fuelPenalty);
}

function endGame() {
    gameEnded = true;
    clearInterval(timerInterval);
    endShift();

    checkAchievements();

    const finalScore = calculateFinalScore();
    const mission = MISSIONS[currentMission];
    var timeUsed;
    if (mission && mission.timeLimit > 0) {
        timeUsed = mission.timeLimit - timeLeft;
    } else {
        timeUsed = timeLeft;
    }
    const efficiency = Math.max(0, 100 - (collisions * 5));

    let grade, gradeClass, feedback;
    if (finalScore >= 600) { grade = 'A+'; gradeClass = 'grade-a-plus'; feedback = '\u00A1EXCELENTE! Eres un experto en logistica.'; }
    else if (finalScore >= 500) { grade = 'A'; gradeClass = 'grade-a'; feedback = '\u00A1Muy bien! Tienes un excelente desempeno.'; }
    else if (finalScore >= 400) { grade = 'B'; gradeClass = 'grade-b'; feedback = 'Buen trabajo. Sigue practicando.'; }
    else if (finalScore >= 300) { grade = 'C'; gradeClass = 'grade-c'; feedback = 'Aprobado. Necesitas mejorar.'; }
    else if (finalScore >= 200) { grade = 'D'; gradeClass = 'grade-d'; feedback = 'Repite la practica.'; }
    else { grade = 'F'; gradeClass = 'grade-f'; feedback = 'Necesitas repasar el curso teorico.'; }

    if (finalScore >= 600) unlockAchievement('master');

    // Save simulator progress
    if (currentMission > 0) {
        saveSimulatorProgress(currentMission, finalScore, timeUsed);
    }

    document.getElementById('grade').textContent = grade;
    document.getElementById('grade').className = gradeClass;
    document.getElementById('finalTime').textContent = formatTime(timeUsed);
    document.getElementById('finalScore').textContent = finalScore;
    document.getElementById('finalCollisions').textContent = collisions;
    document.getElementById('finalEfficiency').textContent = efficiency + '%';
    document.getElementById('feedback').textContent = feedback;

    // Add mission name to results if element exists
    const missionTitle = document.getElementById('resultMissionTitle');
    if (missionTitle && currentMission !== null) {
        missionTitle.textContent = MISSIONS[currentMission]?.title || 'Modo Libre';
    }

    // Add quiz accuracy
    const quizAccuracy = document.getElementById('finalQuizAccuracy');
    if (quizAccuracy) {
        quizAccuracy.textContent = quizzesTotal > 0 ? Math.round((quizzesCorrect / quizzesTotal) * 100) + '%' : 'N/A';
    }

    const achievementsGrid = document.getElementById('achievementsList');
    achievementsGrid.innerHTML = '';
    Object.keys(ACHIEVEMENTS).forEach(key => {
        const ach = ACHIEVEMENTS[key];
        achievementsGrid.innerHTML += `<div class="achievement-badge ${achievements.includes(key) ? '' : 'locked'}"><span class="badge-icon">${ach.icon}</span><span class="badge-name">${achievements.includes(key) ? ach.name : '???'}</span></div>`;
    });

    document.getElementById('inventorySummary').innerHTML = `
        <div class="inv-summary-item"><span class="inv-summary-color" style="background:#3498db"></span>Almacen: ${zoneCounts.ALMACEN}</div>
        <div class="inv-summary-item"><span class="inv-summary-color" style="background:#9b59b6"></span>Picking: ${zoneCounts.PICKING}</div>
        <div class="inv-summary-item"><span class="inv-summary-color" style="background:#2ecc71"></span>Despacho: ${zoneCounts.DESPACHO}</div>
    `;

    saveScore(finalScore, timeLeft, collisions);
    resultsScreen.style.display = 'flex';
}

function resetGame() { resultsScreen.style.display = 'none'; if (currentMission !== null) { startGame(currentMission); } }
function showRanking() { startScreen.style.display = 'none'; rankingScreen.style.display = 'flex'; displayRanking(); }

function backToMenu() {
    rankingScreen.style.display = 'none';
    resultsScreen.style.display = 'none';

    // Clean up game state
    gameStarted = false;
    gameEnded = false;
    gamePaused = false;
    visitTaskTriggered = false;
    clearInterval(timerInterval);
    // Limpiar turno y métricas
    if (shiftTimer) { clearInterval(shiftTimer); shiftTimer = null; }
    stopInventoryMetrics();

    // Hide HUD
    hud.style.display = 'none';
    progressBar.style.display = 'none';
    minimap.style.display = 'none';
    cameraModeIndicator.style.display = 'none';
    cameraPanel.style.display = 'none';
    inventoryPanel.style.display = 'none';
    statsPanel.style.display = 'none';
    shiftPanel.style.display = 'none';
    zoomIndicator.style.display = 'none';
    crosshair.style.display = 'none';

    // Hide overlays
    var quizOv = document.getElementById('quizOverlay');
    if (quizOv) quizOv.style.display = 'none';
    var conceptOv = document.getElementById('conceptCard');
    if (conceptOv) conceptOv.style.display = 'none';

    // Re-render mission select with updated progress
    renderMissionSelect();

    startScreen.style.display = 'flex';
}

function goToCourse() { window.open('curso.html', '_self'); }

// ==================== EVENT LISTENERS ====================
// startBtn removed — missions use selectMission()
if (viewRankingBtn) viewRankingBtn.addEventListener('click', showRanking);
if (restartBtn) restartBtn.addEventListener('click', resetGame);
if (menuBtn) menuBtn.addEventListener('click', backToMenu);
if (backToMenuBtn) backToMenuBtn.addEventListener('click', backToMenu);

// Boton de musica
const musicToggleBtn = document.getElementById('musicToggle');
if (musicToggleBtn) {
    musicToggleBtn.addEventListener('click', toggleMusic);
}

// ==================== BUCLE DE ANIMACION ====================
let beaconPulse = 0;

function animate() {
    requestAnimationFrame(animate);

    if (gameStarted && !gameEnded && !gamePaused) {
        // ===== FÍSICA ESCALAR: velocidad bloqueada al eje del chasis (no hay deslizamiento lateral) =====
        const forwardKey = keys['w'] || keys['arrowup'];
        const reverseKey = keys['s'] || keys['arrowdown'];

        // Límite de velocidad según carga
        const maxFwd = MAX_SPEED * (hasLoad ? 0.65 : 1.0);
        const maxRev = MAX_SPEED * REVERSE_RATIO * (hasLoad ? 0.65 : 1.0);

        // Velocidad objetivo (escalar: + = adelante, - = reversa)
        let targetSpeed = 0;
        if (forwardKey && !reverseKey) targetSpeed = BASE_SPEED;
        else if (reverseKey && !forwardKey) targetSpeed = -BASE_SPEED * REVERSE_RATIO;

        // Si la entrada es opuesta al movimiento → freno (más fuerte que aceleración)
        const brakingInput = (forwardKey && playerSpeed < -0.01) || (reverseKey && playerSpeed > 0.01);
        if (brakingInput) {
            playerSpeed *= (1 - BRAKE_FORCE);
        } else if (targetSpeed !== 0) {
            // Aceleración progresiva hacia el objetivo
            playerSpeed += (targetSpeed - playerSpeed) * ACCELERATION;
        } else {
            // Sin entrada → fricción de rodadura
            playerSpeed *= (1 - DECELERATION);
            if (Math.abs(playerSpeed) < 0.001) playerSpeed = 0;
        }

        // Clamp a velocidad máxima
        if (playerSpeed > maxFwd) playerSpeed = maxFwd;
        if (playerSpeed < -maxRev) playerSpeed = -maxRev;

        // ===== ROTACIÓN con steering realista =====
        // El montacargas (dirección por ruedas traseras) puede pivotar en reposo pero más lento.
        const speedMag = Math.abs(playerSpeed);
        const speedFactor = Math.min(speedMag / BASE_SPEED, 1);
        const rotateMul = 0.35 + 0.65 * speedFactor;           // 35% parado, 100% a velocidad crucero
        const effectiveRotate = rotateSpeed * rotateMul;

        // Dirección invertida en reversa (como un carro real)
        const rotSign = playerSpeed < -0.005 ? -1 : 1;
        let turningSharp = false;
        if (keys['a'] || keys['arrowleft'])  { playerRotation += effectiveRotate * rotSign; playerGroup.rotation.y = playerRotation; turningSharp = true; }
        if (keys['d'] || keys['arrowright']) { playerRotation -= effectiveRotate * rotSign; playerGroup.rotation.y = playerRotation; turningSharp = true; }

        // Pérdida leve de velocidad al girar a alta velocidad (simula agarre)
        if (turningSharp && speedMag > BASE_SPEED * 0.6) {
            playerSpeed *= 0.985;
        }

        // ===== TRASLACIÓN: velocidad siempre alineada con el chasis (sin patinaje) =====
        playerVelocityX = Math.sin(playerRotation) * playerSpeed;
        playerVelocityZ = Math.cos(playerRotation) * playerSpeed;

        if (playerSpeed !== 0) {
            const newX = playerGroup.position.x + playerVelocityX;
            const newZ = playerGroup.position.z + playerVelocityZ;
            if (!checkCollision(newX, newZ)) {
                playerGroup.position.x = newX;
                playerGroup.position.z = newZ;
            } else {
                // Colisión: detener totalmente (impacto)
                playerSpeed = 0;
                playerVelocityX = 0;
                playerVelocityZ = 0;
                if (!collisionsHit) {
                    collisions++;
                    collisionsHit = true;
                    playSoundCollision();
                    setTimeout(() => collisionsHit = false, 500);
                    showNotification("\u26A0\uFE0F \u00A1Colision!", "#e94560", "error");
                }
            }
        }
        if (hasLoad && currentLoad) { 
            currentLoad.position.x = playerGroup.position.x + Math.sin(playerRotation) * 2.5; 
            currentLoad.position.z = playerGroup.position.z + Math.cos(playerRotation) * 2.5; 
            currentLoad.position.y = forkHeight + 0.3; // Elevar con horquillas
            currentLoad.rotation.y = playerRotation; 
        }

        // Check visit-type tasks (guard evita disparar múltiples veces por frame)
        if (currentMission !== null && currentMission !== 0 && !visitTaskTriggered) {
            const task = missionTasks[currentTask];
            if (task && task.type === 'visit') {
                const targetZone = zones.find(z => z.label === task.zone);
                if (targetZone) {
                    const distToZone = Math.sqrt(
                        Math.pow(playerGroup.position.x - targetZone.x, 2) +
                        Math.pow(playerGroup.position.z - targetZone.z, 2)
                    );
                    if (distToZone < 7) {
                        visitTaskTriggered = true; // bloquear re-disparo
                        if (task.quiz) {
                            showQuiz(task.quiz, function() {
                                score += 50;
                                showNotification('\u2705 ' + (task.concept || 'Zona visitada'), '#00ffcc');
                                playSoundComplete();
                                visitTaskTriggered = false;
                                advanceTask();
                            });
                        } else {
                            score += 50;
                            showNotification('\u2705 ' + (task.concept || 'Zona visitada'), '#00ffcc');
                            visitTaskTriggered = false;
                            advanceTask();
                        }
                    }
                }
            }
        }

        updateCameraPosition();
        updateMinimap();
        updateZoneLabels();
    }

    // ==================== ANIMACIÓN DE RUEDAS Y DIRECCIÓN ====================
    if (playerGroup && playerGroup.userData.rearWheels && playerGroup.userData.frontWheels) {
        const currentSpeed = Math.sqrt(playerVelocityX * playerVelocityX + playerVelocityZ * playerVelocityZ);
        
        // 1. ROTACIÓN DE RUEDAS TRASERAS (motrices, pequeñas) - proporcional a la velocidad
        if (currentSpeed > 0.001) {
            const wheelRotationSpeed = currentSpeed * 3.0; // factor de giro
            const rotationDirection = (playerVelocityX * Math.sin(playerRotation) + playerVelocityZ * Math.cos(playerRotation)) > 0 ? 1 : -1;
            
            // Solo las ruedas TRASERAS (pequeñas) giran sobre su eje
            playerGroup.userData.rearWheels.forEach(wheelGroup => {
                if (wheelGroup.userData.wheel) {
                    wheelGroup.userData.wheel.rotation.x += wheelRotationSpeed * rotationDirection;
                }
            });
            // Las ruedas DELANTERAS (grandes) NO giran sobre su eje, solo giran para dirección
        }
        
        // 2. DIRECCIÓN (giro de ruedas delanteras grandes)
        let targetSteeringAngle = 0;
        const maxSteeringAngle = Math.PI / 6; // 30 grados
        
        if (keys['a'] || keys['arrowleft']) {
            targetSteeringAngle = maxSteeringAngle;
        } else if (keys['d'] || keys['arrowright']) {
            targetSteeringAngle = -maxSteeringAngle;
        }
        
        // Suavizar transición del ángulo de dirección
        playerGroup.userData.steeringAngle = playerGroup.userData.steeringAngle || 0;
        playerGroup.userData.steeringAngle += (targetSteeringAngle - playerGroup.userData.steeringAngle) * 0.2;
        
        // Aplicar ángulo de dirección a cada rueda delantera
        playerGroup.userData.frontWheels.forEach(wheelGroup => {
            wheelGroup.rotation.y = playerGroup.userData.steeringAngle;
        });
        
        // 3. EFECTO DE SUSPENSIÓN (ligero movimiento vertical)
        const time = Date.now() * 0.001;
        // Ruedas TRASERAS (pequeñas) - altura inicial 0.3
        playerGroup.userData.rearWheels.forEach((wheelGroup, idx) => {
            const phase = idx * Math.PI;
            const bounce = Math.sin(time * 2 + phase) * 0.02 * currentSpeed;
            wheelGroup.position.y = 0.3 + bounce;
        });
        // Ruedas DELANTERAS (grandes) - altura inicial 0.4
        playerGroup.userData.frontWheels.forEach((wheelGroup, idx) => {
            const phase = idx * Math.PI;
            const bounce = Math.sin(time * 2 + phase) * 0.02 * currentSpeed;
            wheelGroup.position.y = 0.4 + bounce;
        });
    }

    // Animar luz de advertencia (parpadeo)
    if (playerGroup && playerGroup.userData.warningLight) {
        beaconPulse += 0.1;
        const warningLight = playerGroup.userData.warningLight;
        const intensity = (Math.sin(beaconPulse) + 1) / 2;
        const baseColor = new THREE.Color(0xff8800);
        const brightColor = new THREE.Color(0xffdd00);
        warningLight.material.color.lerpColors(baseColor, brightColor, intensity);
    }

    // Parpadeo de luces fluorescentes del almacén
    flickerTime += 0.05;
    warehouseLightRefs.forEach((light, idx) => {
        // Cada luz tiene un desfase único basado en su índice
        const phase = idx * 0.7;
        const flicker = Math.sin(flickerTime + phase) * 0.1 + Math.sin(flickerTime * 2.3 + phase) * 0.05;
        const baseIntensity = idx === 0 ? 0.5 : 0.4; // intensidad base original
        light.intensity = Math.max(0.1, baseIntensity + flicker * 0.1);
    });

    // Animación de partículas en zona fría
    if (coldZoneParticles) {
        coldZoneParticles.rotation.y += 0.001;
        coldZoneParticles.rotation.x += 0.0005;
        const positions = coldZoneParticles.geometry.attributes.position;
        const time = Date.now() * 0.001;
        for (let i = 0; i < positions.count; i++) {
            const i3 = i * 3;
            // Movimiento vertical suave
            positions.array[i3 + 1] += Math.sin(time + i) * 0.005;
            // Limitar altura
            if (positions.array[i3 + 1] > 10) positions.array[i3 + 1] = 0;
            if (positions.array[i3 + 1] < 0) positions.array[i3 + 1] = 10;
        }
        positions.needsUpdate = true;
    }

    renderer.render(scene, camera);
}

try {
    init();
} catch(e) {
    console.error("Error inicializando:", e);
    alert("Error: " + e.message);
}
