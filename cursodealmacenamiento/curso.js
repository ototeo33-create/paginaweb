// ============================================
// CURSO: ALMACENAMIENTO Y APROVISIONAMIENTO - INTEP
// Basado en PROGRAMACIÓN BIMESTRAL ALMACENAMIENTO
// ============================================

const STORAGE_KEY = 'intep_almacenamiento_progress';
const MAX_SCORE = 100;
const PASS_SCORE = 70;

// Estado inicial del curso
let courseState = {
    currentModule: 1,
    moduleProgress: {},
    finalExamPassed: false,
    finalScore: 0
};

// Inicializar progreso
for (let i = 1; i <= 6; i++) {
    courseState.moduleProgress[i] = { completed: false, score: 0, examPassed: false };
}

// Preguntas de evaluación (10 por módulo) - Según Parcelador INTEP
const QUIZ_DATA = {
    1: [
        { q: '¿Cuál es el objetivo principal de un Centro de Distribución?', a: ['Almacenar todo', 'Optimizar flujo de mercancías', 'Vender más', 'Reducir personal'], c: 1 },
        { q: '¿Qué son las funciones de un Centro de Distribución?', a: ['Solo guardar', 'Recibir, almacenar, despachar', 'Solo vender', 'Solo transportar'], c: 1 },
        { q: '¿Cuál es la evolución de almacenes tradicionales?', a: ['CD más complejos', 'Cierran todo', 'Menor tecnología', 'Más personal'], c: 0 },
        { q: '¿Qué son los principios macros para operar un CD?', a: ['Reglas básicas', 'Sin reglas', 'Solo para moda'], c: 0 },
        { q: '¿Qué son guías básicas de almacenamiento?', a: ['Normas para guardar', 'Eliminar productos', 'Solo contar'], c: 0 },
        { q: '¿Cuáles son factores claves en un CD?', a: ['Infraestructura, procesos, organización', 'Solo espacio', 'Solo personas'], c: 0 },
        { q: '¿Qué es la Planeación y Control de Producción?', a: ['Tema del parcelador', 'No existe', 'Fuera del tema'], c: 0 },
        { q: '¿Cuál es propósito de la introducción del programa?', a: ['Dar lineamientos y metodología', 'Solo presentar', 'Evaluar'], c: 0 },
        { q: '¿Qué recursos se usan en clase?', a: ['Aula, tablero, marcadores, videos', 'Solo libro', 'Nada'], c: 0 },
        { q: '¿Qué metodología se aplica?', a: ['Exposición docente y ejercicios prácticos', 'Solo lectura', 'Solo exámenes'], c: 0 }
    ],
    2: [
        { q: '¿Qué son los principios de recepción?', a: ['Reglas básicas del recibo', 'Rechazar todo', 'Sin importancia'], c: 0 },
        { q: '¿Qué es el método de recibo por paletizado?', a: ['Recibir en pallets', 'Recibir a granel', 'Sin método'], c: 0 },
        { q: '¿Qué es el recibo a granel?', a: ['Sin empaque', 'Solo pallets', 'Cajas'], c: 0 },
        { q: '¿Qué es etiquetado y marcado?', a: ['Identificar productos', 'Eliminar etiquetas', 'Solo pintar'], c: 0 },
        { q: '¿Qué son muelles y plataformas?', a: ['Área de recibo', 'Oficina', 'Baño'], c: 0 },
        { q: '¿Qué equipos se usan en recibo?', a: ['Montacargas, transpaletas', 'Carros de mano', 'Nada'], c: 0 },
        { q: '¿Qué es la zona de recibo físico?', a: ['Área donde se recibe', 'Pasillo', 'Oficina'], c: 0 },
        { q: '¿Qué es acumulación de mercancías?', a: ['Productos pendientes de validación', 'Productos terminados', 'Basura'], c: 0 },
        { q: '¿Qué son terminales portátiles?', a: ['Dispositivos para recibir', 'Teléfonos', 'Computadores'], c: 0 },
        { q: '¿Qué son sistemas EDI en recibo?', a: ['Documentos electrónicos', 'Papeles', 'Correo'], c: 0 }
    ],
    3: [
        { q: '¿Qué es cubicaje?', a: ['Calcular espacio', 'Pintar', 'Medir peso'], c: 0 },
        { q: '¿Para qué sirve cubicaje?', a: ['Optimizar espacio', 'Gastar tiempo', 'Sin utilidad'], c: 0 },
        { q: '¿Cómo se cubicaje un contenedor?', a: ['Calculando volumen', 'A ojo', 'Sin método'], c: 0 },
        { q: '¿Qué son equipos de manejo de materiales?', a: ['Montacargas, carretillas', 'Computadores', 'Escritorios'], c: 0 },
        { q: '¿Para qué sirve código de barras?', a: ['Identificar productos', 'Decorar', 'Tirar'], c: 0 },
        { q: '¿Qué es RFID?', a: ['Identificación por radio', 'Factura', 'Recibo'], c: 0 },
        { q: '¿Qué es codificación de inventarios?', a: ['Asignar códigos únicos', 'Sin código', 'Eliminar códigos'], c: 0 },
        { q: '¿Cuántas clases de codificación hay?', a: ['Varios tipos', 'Una', 'Sin clases'], c: 0 },
        { q: '¿Qué es etiquetado?', a: ['Colocar etiquetas', 'Quitar etiquetas', 'Sin usar'], c: 0 },
        { q: '¿Qué es marcación?', a: ['Marcar productos', 'Sin marcar', 'Tirar'], c: 0 }
    ],
    4: [
        { q: '¿Cuántos tipos principales de contenedores hay?', a: ['Varios tipos', 'Uno', 'Ninguno'], c: 0 },
        { q: '¿Qué son medidas de seguridad en contenedores?', a: ['Normas de uso', 'Sin importancia', 'Para tirar'], c: 0 },
        { q: '¿Qué es identificación de contenedores?', a: ['Códigos únicos', 'Sin identificar', 'Aleatorio'], c: 0 },
        { q: '¿Qué es inspección de seguridad?', a: ['Verificar estado', 'Ignorar', 'Tirar'], c: 0 },
        { q: '¿Qué es control previo al uso?', a: ['Verificar antes de usar', 'Usar sin revisar', 'Ninguno'], c: 0 },
        { q: '¿Qué son precintos de seguridad?', a: ['Sellos oficiales', 'Cinta cualquiera', 'Nada'], c: 0 },
        { q: '¿Qué son símbolos de seguridad?', a: ['Iconos de peligro', 'Decoración', 'Sin uso'], c: 0 },
        { q: '¿Qué es control del contenedor en uso?', a: ['Monitorear durante uso', 'Ignorar', 'Ninguno'], c: 0 },
        { q: '¿Qué dimensiones tienen contenedores estándar?', a: ['20 y 40 pies', '100 pies', '1 pie'], c: 0 },
        { q: '¿Qué es capacidad de carga?', a: ['Peso máximo', 'Espacio', 'Sin límite'], c: 0 }
    ],
    5: [
        { q: '¿Qué es la función del almacenamiento?', a: ['Guardar mercancías', 'Tirar productos', 'Vender'], c: 0 },
        { q: '¿Qué son objetivos del almacenamiento?', a: ['Mantener calidad y disponibilidad', 'Solo guardar', 'Ninguno'], c: 0 },
        { q: '¿Qué es evolución del almacenamiento?', a: ['Cambios con tecnología', 'Igual siempre', 'Cierra'], c: 0 },
        { q: '¿Qué son necesidades de sistema?', a: ['Requisitos para operar', 'Sin importancia', 'Ninguno'], c: 0 },
        { q: '¿Qué es responsabilidad de inventarios?', a: ['Controlar existencias', 'Ignorar', 'Ninguno'], c: 0 },
        { q: '¿Por qué es importante el control?', a: ['Evitar faltantes y sobrantes', 'Sin razón', 'Ninguno'], c: 0 },
        { q: '¿Cuáles son principios de almacenamiento?', a: ['Orden, ubicación, seguridad', 'Cualquier forma', 'Ninguno'], c: 0 },
        { q: '¿Qué es seguridad en almacenamiento?', a: ['Prevenir accidentes', 'No importa', 'Ninguno'], c: 0 },
        { q: '¿Qué es gestión de ubicación?', a: ['Asignar lugares', 'Donde cabe', 'Ninguno'], c: 0 },
        { q: '¿Qué condiciona la distribución?', a: ['Características de productos', 'Sin criterio', 'Ninguno'], c: 0 }
    ],
    6: [
        { q: '¿Qué es identificación de ubicaciones?', a: ['Asignar códigos a lugares', 'Sin identificar', 'Aleatorio'], c: 0 },
        { q: '¿Qué es trazabilidad?', a: ['Seguir productos', 'Perder productos', 'Sin uso'], c: 0 },
        { q: '¿Qué es método ABC?', a: ['Clasificar por importancia', 'Ordenar por gusto', 'Aleatorio'], c: 0 },
        { q: 'En ABC, Zona A representa:', a: ['20% items, 80% valor', '50% todo', '80% items'], c: 0 },
        { q: '¿Qué productos van en Zona A?', a: ['Alta rotación', 'Baja rotación', 'Sin rotación'], c: 0 },
        { q: '¿Qué es reabastecimiento?', a: ['Renovar inventario', 'Eliminar productos', 'Vender'], c: 0 },
        { q: '¿Qué es punto de reorden?', a: ['Nivel para nuevo pedido', 'Precio mínimo', 'Última venta'], c: 0 },
        { q: '¿Qué es stock de seguridad?', a: ['Reserva extra', 'Mercancía dañada', 'Promoción'], c: 0 },
        { q: '¿Qué es demanda histórica?', a: ['Datos de ventas pasadas', 'Proyecciones', 'Inventario'], c: 0 },
        { q: '¿Qué es lead time?', a: ['Tiempo de entrega', 'Tiempo de producción', 'Tiempo de venta'], c: 0 }
    ]
};

// Ejercicios prácticos (5 por módulo) - Según Parcelador INTEP
const PRACTICA_DATA = {
    1: [
        { t: 'Identificar funciones CD', d: 'El Centro de Distribución tiene las siguientes funciones:', p: 'opc', o: ['Recibir, almacenar, despachar', 'Solo vender', 'Solo almacenar'], r: 'Recibir, almacenar, despachar' },
        { t: 'Clasificar principio CD', d: 'Uno de los principios macros para operar un CD es:', p: 'opc', o: ['Optimizar flujo', 'Cerrar pronto', 'Reducir personal'], r: 'Optimizar flujo' },
        { t: 'Identificar factores CD', d: 'Los factores claves en un CD son:', p: 'opc', o: ['Infraestructura, procesos, organización', 'Solo espacio', 'Solo personas'], r: 'Infraestructura, procesos, organización' },
        { t: 'Reconocer evolución', d: 'La evolución de almacenes tradicionales a CDs incluye:', p: 'opc', o: ['Mayor complejidad y tecnología', 'Cierran todos', 'Menor capacidad'], r: 'Mayor complejidad y tecnología' },
        { t: 'Identificar propósito intro', d: 'El propósito de la introducción es:', p: 'opc', o: ['Dar lineamientos y metodología', 'Solo presentar', 'Evaluar'], r: 'Dar lineamientos y metodología' }
    ],
    2: [
        { t: 'Identificar método recibo', d: 'Método de recibo donde se reciben productos sin empaque individual:', p: 'opc', o: ['A granel', 'Paletizado', 'En arrume'], r: 'A granel' },
        { t: 'Clasificar área recibo', d: 'Zona donde se recibe físicamente la mercancía:', p: 'opc', o: ['Zona de recibo físico', 'Oficina', 'Almacén'], r: 'Zona de recibo físico' },
        { t: 'Identificar equipo', d: 'Equipo usado para mover pallets en recibo:', p: 'opc', o: ['Montacargas', 'Carro de mano', 'Grúa'], r: 'Montacargas' },
        { t: 'Identificar terminal', d: 'Dispositivo portátil para recibir mercancía:', p: 'opc', o: ['Terminal portátil', 'Teléfonos', 'Computador'], r: 'Terminal portátil' },
        { t: 'Identificar sistema', d: 'Sistema de documentos electrónicos para recibo:', p: 'opc', o: ['EDI', 'Email', 'Papel'], r: 'EDI' }
    ],
    3: [
        { t: 'Calcular cubicaje', d: 'Contenedor de 10x5x5 metros. Volumen:', p: 'calc', r: 250, f: 'Largo × Ancho × Alto' },
        { t: 'Identificar código', d: 'Código que se lee con escáner:', p: 'opc', o: ['Código de barras', 'Código QR', 'RFID'], r: 'Código de barras' },
        { t: 'Identificar RFID', d: 'Tecnología de identificación por:', p: 'opc', o: ['Frecuencia radio', 'Línea visible', 'Contacto'], r: 'Frecuencia radio' },
        { t: 'Clasificar codificación', d: 'Asignar código único a productos se llama:', p: 'opc', o: ['Codificar', 'Tirar', 'Vender'], r: 'Codificar' },
        { t: 'Identificar equipo manejo', d: 'Equipo para mover materiales pesados:', p: 'opc', o: ['Montacargas', 'Escritorio', 'Computador'], r: 'Montacargas' }
    ],
    4: [
        { t: 'Identificar tipo', d: 'Contenedor estándar de 40 pies es:', p: 'opc', o: [' contenedor de 40 pies', 'Contenedor de 20 pies', 'Contenedor de 100 pies'], r: ' contenedor de 40 pies' },
        { t: 'Identificar precinto', d: 'Sello de seguridad en contenedor se llama:', p: 'opc', o: ['Precinto', 'Cinta', 'Candado'], r: 'Precinto' },
        { t: 'Identificar inspección', d: 'Verificar estado del contenedor antes de usar:', p: 'opc', o: ['Inspección de seguridad', 'Tirar', 'Ignorar'], r: 'Inspección de seguridad' },
        { t: 'Clasificar símbolo', d: 'Los símbolos de seguridad indican:', p: 'opc', o: ['Peligro', 'Decoración', 'Información'], r: 'Peligro' },
        { t: 'Identificar control', d: 'Monitorear contenedor durante uso:', p: 'opc', o: ['Control en uso', 'Ignorar', 'Tirar'], r: 'Control en uso' }
    ],
    5: [
        { t: 'Identificar función almacenamiento', d: 'La función principal del almacenamiento es:', p: 'opc', o: ['Guardar mercancías', 'Tirar productos', 'Vender'], r: 'Guardar mercancías' },
        { t: 'Identificar principio', d: 'Uno de los principios de almacenamiento es:', p: 'opc', o: ['Orden y ubicación', 'Cualquier forma', 'Sin orden'], r: 'Orden y ubicación' },
        { t: 'Identificar responsabilidad', d: 'La responsabilidad de inventarios incluye:', p: 'opc', o: ['Controlar existencias', 'Ignorar', 'Tirar'], r: 'Controlar existencias' },
        { t: 'Identificar seguridad', d: 'La seguridad en almacenamiento previene:', p: 'opc', o: ['Accidentes', 'Ventas', 'Gastos'], r: 'Accidentes' },
        { t: 'Identificar evolución', d: 'La evolución del almacenamiento incluye:', p: 'opc', o: ['Cambios con tecnología', 'Igual siempre', 'Cierra'], r: 'Cambios con tecnología' }
    ],
    6: [
        { t: 'Clasificar ABC', d: 'Zona A en método ABC representa:', p: 'opc', o: ['20% items, 80% valor', '50% todo', '80% items'], r: '20% items, 80% valor' },
        { t: 'Identificar ubicación', d: 'Asignar códigos a ubicaciones se llama:', p: 'opc', o: ['Identificación de ubicaciones', 'Tirar', 'Ignorar'], r: 'Identificación de ubicaciones' },
        { t: 'Calcular stock seguridad', d: 'Demanda=100, Lead time=5 días. Stock seguridad mínimo:', p: 'calc', r: 500, f: 'Demanda × Lead time' },
        { t: 'Identificar reabastecimiento', d: 'Renovar inventario se llama:', p: 'opc', o: ['Reabastecimiento', 'Tirar', 'Ignorar'], r: 'Reabastecimiento' },
        { t: 'Identificar trazabilidad', d: 'Seguir productos desde origen se llama:', p: 'opc', o: ['Trazabilidad', 'Perdida', 'Tirar'], r: 'Trazabilidad' }
    ]
};

// Examen Final - 15 preguntas de todos los módulos
const FINAL_EXAM_DATA = [
    // Módulo 1 - Procesos CD (3 preguntas)
    { q: '¿Cuál es el objetivo principal de un Centro de Distribución?', a: ['Almacenar todo', 'Optimizar flujo de mercancías', 'Vender más', 'Reducir personal'], c: 1 },
    { q: '¿Qué son las funciones de un Centro de Distribución?', a: ['Solo guardar', 'Recibir, almacenar, despachar', 'Solo vender', 'Solo transportar'], c: 1 },
    { q: '¿Cuáles son factores claves en un CD?', a: ['Infraestructura, procesos, organización', 'Solo espacio', 'Solo personas', 'Solo tecnología'], c: 0 },
    
    // Módulo 2 - Recepción (3 preguntas)
    { q: '¿Qué es el método de recibo por paletizado?', a: ['Recibir en pallets', 'Recibir a granel', 'Sin método', 'Recibir en cajas'], c: 0 },
    { q: '¿Qué equipos se usan en recibo?', a: ['Montacargas, transpaletas', 'Carros de mano', 'Nada', 'Solo grúas'], c: 0 },
    { q: '¿Qué son sistemas EDI en recibo?', a: ['Documentos electrónicos', 'Papeles', 'Correo', 'Facturas'], c: 0 },
    
    // Módulo 3 - Cubicaje (2 preguntas)
    { q: '¿Qué es cubicaje?', a: ['Calcular espacio', 'Pintar', 'Medir peso', 'Contar productos'], c: 0 },
    { q: '¿Qué son equipos de manejo de materiales?', a: ['Montacargas, carretillas', 'Computadores', 'Escritorios', 'Sillas'], c: 0 },
    
    // Módulo 4 - Contenedores (3 preguntas)
    { q: '¿Qué son medidas de seguridad en contenedores?', a: ['Normas de uso', 'Sin importancia', 'Para tirar', 'Para vender'], c: 0 },
    { q: '¿Qué es inspección de seguridad?', a: ['Verificar estado', 'Ignorar', 'Tirar', 'Vender'], c: 0 },
    { q: '¿Qué dimensiones tienen contenedores estándar?', a: ['20 y 40 pies', '100 pies', '1 pie', '50 pies'], c: 0 },
    
    // Módulo 5 - Almacenamiento (2 preguntas)
    { q: '¿Qué es la función del almacenamiento?', a: ['Guardar mercancías', 'Tirar productos', 'Vender', 'Transportar'], c: 0 },
    { q: '¿Cuáles son principios de almacenamiento?', a: ['Orden, ubicación, seguridad', 'Cualquier forma', 'Sin orden', 'Solo espacio'], c: 0 },
    
    // Módulo 6 - Reabastecimiento (2 preguntas)
    { q: '¿Qué es trazabilidad?', a: ['Seguir productos', 'Perder productos', 'Sin uso', 'Tirar productos'], c: 0 },
    { q: '¿Qué es reabastecimiento?', a: ['Renovar inventario', 'Eliminar productos', 'Vender', 'Guardar'], c: 0 }
];

// ============================================
// NAVEGACIÓN
// ============================================

function showModule(num) {
    // Verificar si puede acceder al módulo
    if (num > 1 && !courseState.moduleProgress[num-1].completed) {
        alert('Debes completar el módulo anterior antes de continuar.');
        return;
    }
    
    // Ocultar todos los módulos
    document.querySelectorAll('.module-content').forEach(m => m.classList.remove('active'));
    // Mostrar el seleccionado
    const mod = document.getElementById('module-' + num);
    if (mod) mod.classList.add('active');
    
    // Actualizar estado de botones en sidebar
    updateSidebarButtons();
    
    courseState.currentModule = num;
    showTab(num, 'teoria');
}

function updateSidebarButtons() {
    for (let i = 1; i <= 6; i++) {
        const btn = document.querySelector('.module-btn[data-module="' + i + '"]');
        const status = document.getElementById('status-' + i);
        if (btn && status) {
            btn.disabled = false;
            if (courseState.moduleProgress[i].completed) {
                btn.classList.add('completed');
                status.textContent = '✓';
                status.classList.add('status-complete');
            } else if (i === 1 || courseState.moduleProgress[i-1]?.completed) {
                status.textContent = '○';
                btn.classList.remove('completed');
            } else {
                btn.disabled = true;
                status.textContent = '🔒';
                btn.classList.add('locked');
            }
        }
    }
}

function showTab(num,tab) {
    courseState.currentModule = num;
    
    // Botones
    const btns = document.querySelectorAll('#module-' + num + ' .tab-btn');
    btns.forEach(b => b.classList.remove('active'));
    btns.forEach(b => {
        if (b.textContent.toLowerCase().includes(tab)) b.classList.add('active');
    });
    
    // Contenidos
    const tabs = document.querySelectorAll('#module-' + num + ' .tab-content');
    tabs.forEach(t => t.classList.remove('active'));
    document.getElementById('M' + num + '-' + tab).classList.add('active');
    
    // Generar contenido si está vacío
    if (tab === 'eval' || tab === 'practica') {
        const containerId = (tab === 'eval' ? 'eval-M' : 'practice-M') + num;
        const container = document.getElementById(containerId);
        if (container && container.innerHTML.trim() === '') {
            if (tab === 'eval') container.innerHTML = generarEvaluacion(num);
            else container.innerHTML = generarPractica(num);
        }
    }
}

// ============================================
// GENERADORES DE CONTENIDO
// ============================================

function generarEvaluacion(modNum) {
    const preguntas = QUIZ_DATA[modNum];
    let html = '<div class="quiz-container">';
    
    preguntas.forEach((p, i) => {
        html += '<div class="question" data-correct="' + p.c + '">';
        html += '<p class="question-text"><strong>' + (i+1) + '.</strong> ' + p.q + '</p>';
        html += '<div class="options">';
        p.a.forEach((op, j) => {
            html += '<label class="option"><input type="radio" name="q' + modNum + '_' + i + '" value="' + j + '"> ' + op + '</label>';
        });
        html += '</div></div>';
    });
    
    html += '<button onclick="validarQuiz(' + modNum + ')" class="btn-submit">Enviar Respuestas</button>';
    html += '<div class="quiz-result" id="result-M' + modNum + '"></div>';
    html += '</div>';
    return html;
}

function generarPractica(modNum) {
    const ejer = PRACTICA_DATA[modNum];
    let html = '<div class="practice-content"><h2>Ejercicios Prácticos Módulo ' + modNum + '</h2>';
    
    ejer.forEach((e, i) => {
        html += '<div class="exercise-card">';
        html += '<div class="exercise-header">';
        html += '<span>Ejercicio ' + (i+1) + '</span>';
        html += '<span>' + e.t + '</span>';
        html += '<button class="info-btn" onclick="verExplicacion(' + modNum + ',' + i + ')">?</button>';
        html += '</div>';
        html += '<div class="exercise-body">';
        html += '<p>' + e.d + '</p>';
        
        if (e.p === 'opc') {
            html += '<div class="exercise-options">';
            e.o.forEach(op => {
                html += '<button class="option-btn" onclick="verificarEjercicio(' + modNum + ',' + i + ',\'' + op + '\')">' + op + '</button>';
            });
            html += '</div>';
        } else if (e.p === 'calc') {
            html += '<input type="number" id="input-' + modNum + '-' + i + '" placeholder="Respuesta"> ';
            html += '<button class="btn-calc" onclick="verificarCalc(' + modNum + ',' + i + ',' + e.r + ')">Verificar</button>';
        }
        
        html += '<div class="feedback" id="feedback-' + modNum + '-' + i + '"></div>';
        html += '</div></div>';
    });
    
    html += '</div>';
    return html;
}

// ============================================
// VERIFICACIONES
// ============================================

function validarQuiz(modNum) {
    const container = document.getElementById('eval-M' + modNum);
    const result = document.getElementById('result-M' + modNum);
    const preguntas = container.querySelectorAll('.question');
    
    let correctas = 0;
    let todas = true;
    
    preguntas.forEach((preg, i) => {
        const correcta = preg.dataset.correct;
        const seleccionada = preg.querySelector('input:checked');
        
        if (!seleccionada) {
            todas = false;
            return;
        }
        
        if (parseInt(seleccionada.value) === parseInt(correcta)) {
            correctas++;
            preg.classList.add('show-correct');
        } else {
            preg.classList.add('show-incorrect');
        }
    });
    
    if (!todas) {
        result.innerHTML = 'Responde todas las preguntas';
        result.className = 'quiz-result fail';
        return;
    }
    
    const score = Math.round((correctas / preguntas.length) * MAX_SCORE);
    courseState.moduleProgress[modNum].score = score;
    
    if (score >= PASS_SCORE) {
        courseState.moduleProgress[modNum].examPassed = true;
        result.innerHTML = 'Aprobado! Puntuación: ' + score + '% (' + correctas + '/' + preguntas.length + ')';
        result.className = 'quiz-result success';
        
        setTimeout(function() {
            mostrarFelicitacion(modNum);
        }, 1000);
    } else {
        result.innerHTML = 'Necesitas ' + PASS_SCORE + '%. Tu puntuación: ' + score + '%';
        result.className = 'quiz-result fail';
        
        setTimeout(function() {
            container.querySelectorAll('.question').forEach(q => {
                q.classList.remove('show-correct', 'show-incorrect');
                q.querySelectorAll('input').forEach(i => i.checked = false);
            });
        }, 2000);
    }
}

function verificarEjercicio(modNum, ejerNum, respuesta) {
    const ejer = PRACTICA_DATA[modNum][ejerNum];
    const feedback = document.getElementById('feedback-' + modNum + '-' + ejerNum);
    
    if (respuesta === ejer.r) {
        feedback.innerHTML = '<span class="correct">Correcto!</span>';
    } else {
        feedback.innerHTML = '<span class="incorrect">Intenta de nuevo</span>';
    }
}

function verificarCalc(modNum, ejerNum, correcta) {
    const input = document.getElementById('input-' + modNum + '-' + ejerNum);
    const feedback = document.getElementById('feedback-' + modNum + '-' + ejerNum);
    const valor = parseFloat(input.value);
    
    if (valor === correcta) {
        feedback.innerHTML = '<span class="correct">Correcto!</span>';
    } else {
        feedback.innerHTML = '<span class="incorrect">La respuesta es ' + correcta + '</span>';
    }
}

function verExplicacion(modNum, ejerNum) {
    alert('Explicación del ejercicio ' + (ejerNum+1) + ': ' + PRACTICA_DATA[modNum][ejerNum].d);
}

// ============================================
// COMPLETAR MÓDULO
// ============================================

function completeModule(modNum) {
    if (!courseState.moduleProgress[modNum].examPassed) {
        alert('Debes aprobar la evaluación (mínimo 70%) antes de continuar.');
        showTab(modNum, 'eval');
        return;
    }
    
    courseState.moduleProgress[modNum].completed = true;
    updateSidebarButtons();
    
    if (modNum < 6) {
        showModule(modNum + 1);
    } else {
        // Curso completado - mostrar examen final
        showFinalExam();
    }
    
    // Guardar progreso
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(courseState));
    } catch(e) {}
}

// ============================================
// MODAL DE FELICITACIÓN
// ============================================

function mostrarFelicitacion(modNum) {
    let modal = document.getElementById('felicitacionModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'felicitacionModal';
        modal.className = 'modal';
        modal.innerHTML = '<div class="modal-content congrats-modal">' +
            '<div class="congrats-icon">✓</div>' +
            '<h2>Felicitaciones!</h2>' +
            '<p>Has aprobado el Módulo ' + modNum + '</p>' +
            '<p class="congrats-score">Puntuación: ' + courseState.moduleProgress[modNum].score + '%</p>' +
            '<button onclick="continuarSiguiente(' + modNum + ')" class="btn-submit">Continuar al Siguiente Módulo</button>' +
            '</div>';
        document.body.appendChild(modal);
    }
    modal.classList.add('show');
}

function continuarSiguiente(modActual) {
    document.getElementById('felicitacionModal').classList.remove('show');
    if (modActual < 6) {
        showModule(modActual + 1);
    }
}

// ============================================
// SIMULADOR
// ============================================

function openSimulator() {
    window.open('index.html', 'simulador');
}

// ============================================
// EXAMEN FINAL
// ============================================

function showFinalExam() {
    // Verificar que todos los módulos estén completados
    let allCompleted = true;
    for (let i = 1; i <= 6; i++) {
        if (!courseState.moduleProgress[i].completed) {
            allCompleted = false;
            break;
        }
    }
    
    if (!allCompleted) {
        alert('Debes completar todos los módulos para presentar el examen final.');
        return;
    }
    
    // Mostrar modal de examen final
    let modal = document.getElementById('finalExamModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'finalExamModal';
        modal.className = 'modal';
        modal.innerHTML = generarExamenFinalHTML();
        document.body.appendChild(modal);
    }
    modal.classList.add('show');
}

function generarExamenFinalHTML() {
    let html = '<div class="modal-content exam-modal">';
    html += '<div class="exam-header">';
    html += '<h2>🎓 EXAMEN FINAL</h2>';
    html += '<p>Este examen cubre todos los temas del curso</p>';
    html += '<p class="exam-instructions">Responde las 30 preguntas. Minimum 70% para aprobar.</p>';
    html += '</div>';
    html += '<div class="quiz-container" id="finalExamQuiz">';
    
    FINAL_EXAM_DATA.forEach((p, i) => {
        html += '<div class="question" data-correct="' + p.c + '">';
        html += '<p class="question-text"><strong>' + (i+1) + '.</strong> ' + p.q + '</p>';
        html += '<div class="options">';
        p.a.forEach((op, j) => {
            html += '<label class="option"><input type="radio" name="fq' + i + '" value="' + j + '"> ' + op + '</label>';
        });
        html += '</div></div>';
    });
    
    html += '</div>';
    html += '<button onclick="validarExamenFinal()" class="btn-submit">Enviar Examen Final</button>';
    html += '<div class="quiz-result" id="result-final"></div>';
    html += '<button class="btn-close" onclick="closeFinalExam()">Cerrar</button>';
    html += '</div>';
    return html;
}

function validarExamenFinal() {
    const container = document.getElementById('finalExamQuiz');
    const result = document.getElementById('result-final');
    const preguntas = container.querySelectorAll('.question');
    
    let correctas = 0;
    let todas = true;
    
    preguntas.forEach((preg, i) => {
        const correcta = preg.dataset.correct;
        const seleccionada = preg.querySelector('input:checked');
        
        if (!seleccionada) {
            todas = false;
            return;
        }
        
        if (parseInt(seleccionada.value) === parseInt(correcta)) {
            correctas++;
            preg.classList.add('show-correct');
        } else {
            preg.classList.add('show-incorrect');
        }
    });
    
    if (!todas) {
        result.innerHTML = 'Responde todas las preguntas';
        result.className = 'quiz-result fail';
        return;
    }
    
    const score = Math.round((correctas / preguntas.length) * MAX_SCORE);
    courseState.finalScore = score;
    
    if (score >= PASS_SCORE) {
        courseState.finalExamPassed = true;
        result.innerHTML = '🎉 ¡FELICIDADES! Has aprobado el examen final!<br>Puntuación: ' + score + '% (' + correctas + '/' + preguntas.length + ')<br><br>Has completado el curso de Almacenamiento y Aprovisionamiento.';
        result.className = 'quiz-result success';
        
        // Guardar progreso
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(courseState));
        } catch(e) {}
        
        setTimeout(function() {
            closeFinalExam();
            alert('🎉 ¡FELICIDADES! Has completado el curso de Almacenamiento y Aprovisionamiento.\n\nTu puntuación: ' + score + '%');
        }, 1500);
    } else {
        result.innerHTML = '❌ Necesitas ' + PASS_SCORE + '%. Tu puntuación: ' + score + '%<br><br>Intenta de nuevo.';
        result.className = 'quiz-result fail';
        
        setTimeout(function() {
            container.querySelectorAll('.question').forEach(q => {
                q.classList.remove('show-correct', 'show-incorrect');
                q.querySelectorAll('input').forEach(i => i.checked = false);
            });
        }, 2500);
    }
}

function closeFinalExam() {
    const modal = document.getElementById('finalExamModal');
    if (modal) modal.classList.remove('show');
}

function showCertificado() {
    const modal = document.getElementById('certificadoModal');
    if (modal) {
        document.getElementById('certModules').textContent = '6';
        document.getElementById('certScore').textContent = courseState.finalScore + '%';
        document.getElementById('certDate').textContent = new Date().toLocaleDateString('es-CO');
        modal.classList.add('show');
    }
}

// ============================================
// INICIALIZACIÓN
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Curso cargado');
    
    // Cargar progreso guardado
    try {
        const guardado = localStorage.getItem(STORAGE_KEY);
        if (guardado) {
            const parsed = JSON.parse(guardado);
            for (let k in parsed) courseState[k] = parsed[k];
        }
    } catch(e) {}
    
    // Actualizar estado inicial de botones
    updateSidebarButtons();
    
    showModule(1);
});

console.log('curso.js listo');