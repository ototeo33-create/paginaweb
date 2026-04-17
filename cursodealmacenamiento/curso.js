// ============================================
// CURSO: ALMACENAMIENTO Y APROVISIONAMIENTO - INTEP
// Basado en PROGRAMACIÓN BIMESTRAL ALMACENAMIENTO
// ============================================

const STORAGE_KEY = 'intep_almacenamiento_progress';
const MAX_SCORE = 100;
const PASS_SCORE = 70;

// Función para mezclar arrays (Fisher-Yates shuffle)
function shuffleArray(array) {
    const shuffled = [...array];
    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return shuffled;
}

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

// Preguntas de evaluación (6 por módulo) - Según Parcelador INTEP
const QUIZ_DATA = {
    1: [
        { q: '¿Cuál es el objetivo principal de un Centro de Distribución?', a: ['Almacenar todo', 'Optimizar flujo de mercancías', 'Vender más', 'Reducir personal'], c: 1 },
        { q: '¿Cuáles son las tres funciones principales de un Centro de Distribución?', a: ['Solo guardar', 'Recibir, almacenar, despachar', 'Solo vender', 'Solo transportar'], c: 1 },
        { q: '¿Cómo han evolucionado los almacenes tradicionales hacia los Centros de Distribución modernos?', a: ['Mayor complejidad y tecnología', 'Cierran todos', 'Menor tecnología', 'Más personal'], c: 0 },
        { q: '¿Qué son los principios macro para operar un Centro de Distribución eficientemente?', a: ['Reglas básicas de operación', 'Sin reglas', 'Solo para moda'], c: 0 },
        { q: '¿Qué son las guías básicas para el almacenamiento adecuado en un Centro de Distribución?', a: ['Protocolos para recepción, almacenamiento y despacho', 'Instrucciones para eliminar productos', 'Reglas para contar inventario'], c: 0 },
        { q: '¿Cuáles son los factores clave que determinan la eficiencia de un Centro de Distribución?', a: ['Infraestructura adecuada, procesos optimizados y organización', 'Solo espacio físico', 'Solo cantidad de personal'], c: 0 }
    ],
    2: [
        { q: '¿Cuál es la diferencia entre recibo paletizado y recibo a granel?', a: ['Paletizado: mercancía en pallets; granel: sin empaque individual', 'Son exactamente iguales', 'Granel usa pallets y paletizado no'], c: 0 },
        { q: '¿Qué son muelles y plataformas en un Centro de Distribución?', a: ['Áreas designadas para carga y descarga de vehículos', 'Oficinas administrativas del CD', 'Zonas de descanso para operarios'], c: 0 },
        { q: '¿Para qué sirve el etiquetado y marcado en la recepción?', a: ['Identificar y registrar productos con códigos', 'Decorar los empaques de los productos', 'Eliminar etiquetas del proveedor'], c: 0 },
        { q: '¿Qué equipos se utilizan principalmente en la zona de recibo?', a: ['Montacargas y transpaletas', 'Solo carros de mano simples', 'No se requiere equipo especializado'], c: 0 },
        { q: '¿Qué es la zona de acumulación en recepción?', a: ['Área donde se ubican productos pendientes de verificación', 'Zona donde se guardan productos listos para despacho', 'Área de almacenamiento permanente'], c: 0 },
        { q: '¿Qué son los sistemas EDI en el proceso de recibo?', a: ['Intercambio Electrónico de Datos que agiliza la documentación', 'Documentación manual en papel', 'Sistema de comunicación interna por correo'], c: 0 }
    ],
    3: [
        { q: '¿Qué es el cubicaje y cuál es su objetivo principal?', a: ['Calcular volúmenes para optimizar el uso del espacio', 'Medir únicamente el peso de la mercancía', 'Pintar y marcar los contenedores'], c: 0 },
        { q: '¿Cómo se calcula el volumen de un contenedor?', a: ['Largo × Ancho × Alto', 'Peso ÷ Densidad', 'Altura × Número de cajas'], c: 0 },
        { q: '¿Cuál es la función del código de barras EAN-13 en el almacén?', a: ['Identificar productos de forma rápida y precisa mediante escáner', 'Decorar los empaques con líneas estéticas', 'Registrar el precio de venta al consumidor'], c: 0 },
        { q: '¿En qué se diferencia el RFID del código de barras?', a: ['RFID usa radiofrecuencia y no necesita contacto visual', 'Son tecnologías idénticas con diferente nombre', 'El código de barras es más moderno que el RFID'], c: 0 },
        { q: '¿Qué son los equipos de manejo de materiales?', a: ['Montacargas, carretillas y transpaletas para mover mercancía', 'Computadores para gestión administrativa', 'Muebles de oficina del almacén'], c: 0 },
        { q: '¿Qué ventaja ofrece el EDI en la gestión de inventarios?', a: ['Automatiza el intercambio de documentos entre empresas', 'Reemplaza completamente el trabajo humano', 'Solo sirve para comunicación interna'], c: 0 }
    ],
    4: [
        { q: '¿Cuáles son los tipos principales de contenedores de carga?', a: ['20 pies, 40 pies, High Cube y Reefer (refrigerado)', 'Solo existe un tipo estándar universal', 'Se clasifican únicamente por color'], c: 0 },
        { q: '¿Qué información contiene la identificación de un contenedor?', a: ['Código alfanumérico único con prefijo del propietario y número serial', 'Solo el peso máximo permitido', 'Únicamente el país de fabricación'], c: 0 },
        { q: '¿Qué se verifica en el control previo al uso de un contenedor?', a: ['Estado estructural, pisos, paredes, sellos y puertas', 'Solo el color exterior del contenedor', 'Únicamente los documentos aduaneros'], c: 0 },
        { q: '¿Para qué sirven los precintos de seguridad en los contenedores?', a: ['Garantizar que el contenedor no fue abierto sin autorización', 'Reemplazar el candado de la puerta', 'Identificar el tipo de mercancía'], c: 0 },
        { q: '¿Qué indican los símbolos de seguridad en los contenedores?', a: ['Peligros, restricciones y condiciones de manejo de la carga', 'Solo el destino del contenedor', 'El peso y volumen de la mercancía'], c: 0 },
        { q: '¿Cuál es la capacidad de carga aproximada de un contenedor de 20 pies?', a: ['Aproximadamente 28 toneladas de carga útil', 'Exactamente 100 toneladas', 'No tiene límite de carga definido'], c: 0 }
    ],
    5: [
        { q: '¿Cuál es la función principal del almacenamiento en la cadena logística?', a: ['Custodiar mercancías manteniendo su calidad y disponibilidad', 'Vender directamente al consumidor final', 'Transformar materias primas en productos terminados'], c: 0 },
        { q: '¿Cuáles son los principios fundamentales del almacenamiento?', a: ['Orden, ubicación adecuada y seguridad', 'Almacenar donde haya espacio disponible', 'Priorizar velocidad sobre organización'], c: 0 },
        { q: '¿En qué consiste la gestión de ubicación en un almacén?', a: ['Asignar posiciones fijas o dinámicas a cada referencia de producto', 'Colocar productos donde haya espacio libre', 'Mover productos diariamente sin criterio'], c: 0 },
        { q: '¿Qué diferencia al sistema Drive-In del almacenamiento convencional?', a: ['Drive-In permite entrada del montacargas al rack para mayor densidad', 'Son sistemas idénticos con diferente nombre', 'El convencional tiene mayor densidad de almacenaje'], c: 0 },
        { q: '¿Por qué es importante el control de inventarios en el almacén?', a: ['Evitar faltantes, sobrantes y pérdidas económicas', 'No tiene importancia en operaciones modernas', 'Solo para cumplir requisitos legales'], c: 0 },
        { q: '¿Qué medidas de seguridad son esenciales en un almacén?', a: ['Señalización, EPP, límites de carga y rutas de evacuación', 'Solo usar cascos de seguridad', 'No se requieren medidas especiales'], c: 0 }
    ],
    6: [
        { q: '¿Qué es la trazabilidad de mercancías y por qué es importante?', a: ['Seguimiento del producto desde el origen hasta el destino final', 'Calcular el precio de venta del producto', 'Registrar únicamente las devoluciones'], c: 0 },
        { q: '¿En qué consiste el método ABC de clasificación de inventarios?', a: ['Clasifica productos por valor e impacto: A (alta importancia) a C (baja)', 'Ordena productos alfabéticamente por nombre', 'Clasifica por tamaño físico del producto'], c: 0 },
        { q: 'Según el método ABC, la Zona A representa:', a: ['El 20% de referencias que genera el 80% del valor del inventario', 'El 50% de referencias con el 50% del valor', 'El 80% de referencias con el 20% del valor'], c: 0 },
        { q: '¿Qué es el punto de reorden y cómo se usa?', a: ['Nivel de stock que activa automáticamente un nuevo pedido al proveedor', 'El precio más bajo al que se puede comprar', 'La última cantidad vendida en el período'], c: 0 },
        { q: '¿Qué es el stock de seguridad y cuándo se utiliza?', a: ['Reserva adicional para cubrir demanda imprevista o retrasos del proveedor', 'Mercancía averiada que no se puede vender', 'Productos apartados para promociones especiales'], c: 0 },
        { q: '¿Qué es el lead time y cómo afecta el reabastecimiento?', a: ['Tiempo entre el pedido y la recepción; a mayor lead time, mayor stock necesario', 'Tiempo de producción en la planta del proveedor', 'Duración del turno laboral del operario de bodega'], c: 0 }
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

// Examen Final - 6 preguntas (1 por módulo)
const FINAL_EXAM_DATA = [
    // Módulo 1 - Procesos CD
    { q: '¿Cuáles son los factores clave que determinan la eficiencia de un Centro de Distribución?', a: ['Infraestructura adecuada, procesos optimizados y organización', 'Solo el espacio físico disponible', 'Solo la cantidad de personal contratado', 'Solo la tecnología instalada'], c: 0 },

    // Módulo 2 - Recepción
    { q: '¿Qué son los sistemas EDI en el proceso de recibo de mercancías?', a: ['Intercambio Electrónico de Datos que agiliza la documentación entre empresas', 'Documentación manual en papel', 'Comunicación interna por correo electrónico', 'Facturas impresas por el proveedor'], c: 0 },

    // Módulo 3 - Cubicaje y Código de Barras
    { q: '¿En qué se diferencia el RFID del código de barras tradicional?', a: ['RFID usa radiofrecuencia y no necesita contacto visual con el lector', 'Son tecnologías idénticas con diferente nombre comercial', 'El código de barras es la tecnología más moderna', 'RFID solo funciona en exteriores'], c: 0 },

    // Módulo 4 - Contenedores y Seguridad
    { q: '¿Para qué sirven los precintos de seguridad en los contenedores?', a: ['Garantizar que el contenedor no fue abierto sin autorización durante el transporte', 'Reemplazar el candado de la puerta del contenedor', 'Identificar visualmente el tipo de mercancía', 'Registrar el peso de la carga'], c: 0 },

    // Módulo 5 - Sistemas de Almacenamiento
    { q: '¿Qué diferencia al sistema Drive-In del almacenamiento convencional en rack?', a: ['Drive-In permite que el montacargas ingrese al rack para mayor densidad de almacenaje', 'El convencional tiene mayor capacidad de almacenaje por m²', 'Son sistemas equivalentes con diferente nombre', 'Drive-In solo se usa para productos refrigerados'], c: 0 },

    // Módulo 6 - Reabastecimiento e Inventarios
    { q: 'Según el método ABC, ¿qué características tienen los productos de la Zona A?', a: ['Son el 20% de referencias pero generan el 80% del valor del inventario', 'Son el 80% de referencias y generan el 80% del valor', 'Son los productos más baratos y de menor rotación', 'Representan el 50% del inventario en cantidad y valor'], c: 0 }
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

// ============================================
// BADGES DEL SIMULADOR
// ============================================
function updateSimulatorBadges() {
    try {
        const simData = localStorage.getItem('intep_simulador_progress');
        if (!simData) return;

        const progress = JSON.parse(simData);
        if (!progress.missions) return;

        for (let i = 1; i <= 6; i++) {
            if (progress.missions[i]?.completed) {
                const btn = document.querySelector('.module-btn[data-module="' + i + '"]');
                if (btn && !btn.querySelector('.sim-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'sim-badge';
                    badge.textContent = '🎮';
                    badge.title = 'Practicado en simulador — Score: ' + (progress.missions[i].score || 0);
                    badge.style.cssText = 'margin-left:5px;font-size:0.85em;';
                    btn.appendChild(badge);
                }
            }
        }
    } catch(e) {
        // Silent fail
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
    let preguntas = QUIZ_DATA[modNum];
    
    // Mezclar el orden de las preguntas
    preguntas = shuffleArray(preguntas);
    
    let html = '<div class="quiz-container">';
    
    preguntas.forEach((p, i) => {
        // Mezclar las opciones de respuesta
        const opcionesConIndices = p.a.map((texto, indice) => ({ texto, indice }));
        const opcionesMezcladas = shuffleArray(opcionesConIndices);
        
        // Encontrar el nuevo índice de la respuesta correcta después de mezclar
        let nuevoIndiceCorrecto = 0;
        for (let j = 0; j < opcionesMezcladas.length; j++) {
            if (opcionesMezcladas[j].indice === p.c) {
                nuevoIndiceCorrecto = j;
                break;
            }
        }
        
        html += '<div class="question" data-correct="' + nuevoIndiceCorrecto + '">';
        html += '<p class="question-text"><strong>' + (i+1) + '.</strong> ' + p.q + '</p>';
        html += '<div class="options">';
        
        opcionesMezcladas.forEach((op, j) => {
            html += '<label class="option"><input type="radio" name="q' + modNum + '_' + i + '" value="' + j + '"> ' + op.texto + '</label>';
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
            // Mezclar las opciones del ejercicio
            const opcionesMezcladas = shuffleArray([...e.o]);
            html += '<div class="exercise-options">';
            opcionesMezcladas.forEach(op => {
                // Escapar comillas simples en el texto para JavaScript
                const opEscapada = op.replace(/'/g, "\\'");
                html += '<button class="option-btn" onclick="verificarEjercicio(' + modNum + ',' + i + ',\'' + opEscapada + '\')">' + op + '</button>';
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
    html += '<p class="exam-instructions">Responde las 6 preguntas (una por módulo). Mínimo 70% para aprobar.</p>';
    html += '</div>';
    html += '<div class="quiz-container" id="finalExamQuiz">';
    
    // Mezclar preguntas del examen final
    let preguntasExamen = shuffleArray(FINAL_EXAM_DATA);
    
    preguntasExamen.forEach((p, i) => {
        // Mezclar las opciones de respuesta
        const opcionesConIndices = p.a.map((texto, indice) => ({ texto, indice }));
        const opcionesMezcladas = shuffleArray(opcionesConIndices);
        
        // Encontrar el nuevo índice de la respuesta correcta después de mezclar
        let nuevoIndiceCorrecto = 0;
        for (let j = 0; j < opcionesMezcladas.length; j++) {
            if (opcionesMezcladas[j].indice === p.c) {
                nuevoIndiceCorrecto = j;
                break;
            }
        }
        
        html += '<div class="question" data-correct="' + nuevoIndiceCorrecto + '">';
        html += '<p class="question-text"><strong>' + (i+1) + '.</strong> ' + p.q + '</p>';
        html += '<div class="options">';
        
        opcionesMezcladas.forEach((op, j) => {
            html += '<label class="option"><input type="radio" name="fq' + i + '" value="' + j + '"> ' + op.texto + '</label>';
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

function loadStudentHeader() {
    try {
        const raw = localStorage.getItem('intep_student');
        if (!raw) return;
        const student = JSON.parse(raw);

        // Nombre
        const nameEl = document.getElementById('headerStudentName');
        if (nameEl && student.nombre) {
            // Mostrar solo el primer nombre + primer apellido
            const parts = student.nombre.trim().split(/\s+/);
            const short = parts.length >= 2 ? parts[0] + ' ' + parts[1] : parts[0];
            nameEl.textContent = short;
        }

        // Foto
        const photoEl = document.getElementById('headerStudentPhoto');
        if (photoEl && student.foto) {
            photoEl.innerHTML = `<img src="${student.foto}" alt="Foto" onerror="this.parentElement.innerHTML='<span class=\\'user-icon-fallback\\'>👤</span>'">`;
        }
    } catch(e) {}
}

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

    // Datos del estudiante en el header
    loadStudentHeader();

    // Actualizar estado inicial de botones
    updateSidebarButtons();
    updateSimulatorBadges();

    showModule(1);
});

console.log('curso.js listo');