# PROMPT PARA DEEPSEEK — SIMULADOR 3D SST INTEP
# Copia y pega TODO este contenido directamente a DeepSeek

---

Eres un motor de renderizado 3D avanzado. Genera un archivo HTML único y
autocontenido que sea un simulador 3D de alta fidelidad visual usando
Three.js r158 (CDN). El objetivo es código de nivel AAA que demuestre
el máximo potencial matemático y algorítmico posible con WebGL.

NO uses modelos externos. TODA la geometría se construye proceduralmente
con matemáticas puras. Esto es un reto de ingeniería computacional.

══════════════════════════════════════════════════════════════════
SISTEMA DE PERSONAJE — ESQUELETO PROCEDURAL CON IK
══════════════════════════════════════════════════════════════════

Construye el personaje con un árbol jerárquico de huesos (Skeleton system)
usando THREE.Group anidados. Cada segmento es geometría procedural:

GEOMETRÍA PROCEDURAL DEL CUERPO:

// Torso: caja con bisel en vértices (suavizado matemático)
// Usa BufferGeometry customizada con normales calculadas manualmente
// torsoGeom.setAttribute('normal', calcularNormalesHalfEdge(vertices))

// Cabeza: icosaedro subdividido 2 veces (Loop subdivision algorithm)
// Aplica displacement map procedural para simular rasgos faciales básicos

// Extremidades: cilindros con tapering matemático
// radio(t) = r_base * (1 - t * 0.3) donde t ∈ [0,1] a lo largo del eje
// Con sección transversal elíptica: x = a*cos(θ), y = b*sin(θ)

// Manos: procedural con 5 dedos usando bezier curves
// Cada dedo = 3 segmentos con articulaciones tipo bisagra


SISTEMA DE ANIMACIÓN CON CINEMÁTICA INVERSA (IK):
Implementa FABRIK (Forward And Backward Reaching Inverse Kinematics):

Cadena IK para brazo (3 huesos: hombro → codo → muñeca → mano):

FORWARD PASS:
  p[n] = target
  para i = n-1 hasta 0:
    dirección = normalize(p[i] - p[i+1])
    p[i] = p[i+1] + dirección * longitud[i]

BACKWARD PASS:
  p[0] = raíz (hombro fijo)
  para i = 1 hasta n:
    dirección = normalize(p[i] - p[i-1])
    p[i] = p[i-1] + dirección * longitud[i]

Iterar 10 veces hasta convergencia < 0.001 unidades

Aplica IK para:
- Manos alcanzando objetos (el personaje extiende el brazo naturalmente)
- Pies siguiendo el suelo con raycast (footplanting)
- Cabeza mirando hacia puntos de interés (look-at suavizado con slerp)


CICLO DE MARCHA PROCEDURAL (Procedural Walk Cycle):
Usa síntesis de movimiento por funciones sinusoidales acopladas:

  t = tiempo_global * velocidad_marcha

  // Oscilación de caderas
  cadera.rotation.y = A_cadera * sin(2πt)
  cadera.position.y = base_y + A_rebote * |sin(2πt)|

  // Pierna izquierda/derecha (desfase 180°)
  muslo_izq.rotation.x  = A_muslo   * sin(2πt)
  rodilla_izq.rotation.x = A_rodilla * max(0, sin(2πt + π/4))
  pie_izq.rotation.x    = A_pie     * sin(2πt + π/3)

  muslo_der.rotation.x  = A_muslo   * sin(2πt + π)   // desfase 180°
  // ... espejo

  // Brazos balanceados (contrafase con piernas)
  brazo_izq.rotation.x = -A_brazo * sin(2πt)          // opuesto a pierna derecha
  brazo_der.rotation.x = -A_brazo * sin(2πt + π)

  // Columna vertebral: ondulación sutil
  columna.rotation.z  = A_columna * sin(2πt) * 0.3
  cabeza.rotation.y   = -A_cabeza * sin(2πt) * 0.2    // mira hacia donde va

  // Transición suave velocidad 0 → marcha: lerp(A_actual, A_objetivo, 0.1)


Estados de animación con máquina de estados finitos (FSM):
- IDLE    : respiración (torso sube/baja A*sin(2πt*0.3)), peso corporal shift
- WALK    : ciclo procedural arriba
- RUN     : misma base * 1.8 frecuencia + inclinación torso hacia adelante
- CROUCH  : IK rodillas flexionadas, torso inclinado, altura -40%
- GRAB    : IK brazo derecho → target, dedos se cierran proceduralmente
- CARRY   : brazo izq sujeta objeto (constraint position), cuerpo inclina levemente
- INSPECT : cuerpo agachado, cabeza mira objeto, mano señala


CHALECO "INTEP":

  // Geometría del chaleco: clonar torso geometry + offset exterior 0.05u
  // Material: MeshStandardMaterial amarillo neón #FFD700
  // Texto "INTEP" en espalda via CanvasTexture:
  const canvas = document.createElement('canvas'); // 512x512
  canvas.getContext('2d').font = 'bold 80px Arial';
  canvas.getContext('2d').fillStyle = '#1a1a1a';
  canvas.getContext('2d').fillText('INTEP', 50, 280);
  // Reflective strips: 2 bandas horizontales con MeshStandardMaterial metalness:0.8

  // Casco: procedural — semiesfera + ala circular extruida
  // Color naranja #FF6B35, con ratchet trasero modelado


══════════════════════════════════════════════════════════════════
BODEGA — GENERACIÓN PROCEDURAL DEL ENTORNO
══════════════════════════════════════════════════════════════════

ARQUITECTURA PARAMÉTRICA:

  const BODEGA = {
    ancho: 40, largo: 60, alto: 8,   // metros
    columnas: { filas: 4, cols: 6, radio: 0.3 },
    racks: { filas: 2, cols: 4, niveles: 3, separacion: 8 }
  }


SUELO CON SHADER PROCEDURAL (GLSL):

  // Fragment shader para suelo industrial
  uniform float time;
  varying vec2 vUv;

  void main() {
    // Grid de líneas amarillas (zonas de seguridad)
    float lineX = step(0.97, fract(vUv.x * 5.0));
    float lineY = step(0.97, fract(vUv.y * 8.0));
    float lines = max(lineX, lineY);

    // Textura de concreto procedural via ruido Perlin / fbm
    float noise = fbm(vUv * 20.0);   // fractal brownian motion

    // Manchas de aceite con SDF circular
    float manchas = smoothstep(0.3, 0.0,
                    length(vUv - vec2(0.3, 0.7)) - 0.1);

    vec3 colorBase  = mix(vec3(0.45), vec3(0.35), noise);
    vec3 colorFinal = mix(colorBase, vec3(0.9, 0.8, 0.1), lines * 0.8);
    colorFinal      = mix(colorFinal, vec3(0.1, 0.08, 0.05), manchas);

    gl_FragColor = vec4(colorFinal, 1.0);
  }


PAREDES CON SHADER DE LADRILLO PROCEDURAL (GLSL):

  float brick(vec2 uv, float escala) {
    uv *= escala;
    uv.x += floor(uv.y) * 0.5;            // offset fila alternada
    vec2 id = floor(uv);
    vec2 gv = fract(uv);
    float mortero = min(smoothstep(0.0, 0.03, gv.x),
                        smoothstep(0.0, 0.05, gv.y));
    float variacion = hash(id) * 0.15;     // cada ladrillo distinto
    return mortero * (0.7 + variacion);
  }


ILUMINACIÓN FÍSICA AVANZADA:

  // 1. Luz ambiente HDR: HemisphereLight(#87CEEB, #8B7355, intensidad)
  // 2. Claraboyas: 4 RectAreaLight(#FFF5E0, 3, 4, 2) en el techo
  //    animación de nubes: intensidad = 3 + sin(t*0.1)*0.5
  // 3. Lámparas industriales: SpotLight, decay=2, penumbra=0.3, castShadow=true
  //    generadas en loop sobre cuadrícula del techo
  // 4. Post-processing con EffectComposer:
  //    - SSAO (Screen Space Ambient Occlusion)
  //    - Bloom selectivo en lámparas (threshold=0.8, strength=0.4)
  //    - FXAA antialiasing
  // 5. shadowMap.type = THREE.PCFSoftShadowMap
  //    shadow.mapSize = 2048x2048, shadow.radius = 3


RACKS METÁLICOS PROCEDURALES:

  // Perfil en C extruido a lo largo de un path (ExtrudeGeometry)
  // Travesaños cada 0.5m con tornillos modelados (CylinderGeometry pequeño)
  // Material: MeshStandardMaterial
  //   roughness = 0.6 + noise(uv * 50) * 0.3   (metal rayado)
  //   metalness = 0.85
  // Color: #4A4A4A con variación por rack

  // Cajas con CanvasTexture procedural:
  function generarEtiquetaCaja(tipo) {
    const c = document.createElement('canvas'); // 256x256
    // dibuja borde, código de barras (líneas verticales), peso, símbolo peligro
    // color de fondo por categoría: rojo=peligroso, azul=frágil, verde=normal
  }


FÍSICA SIMPLIFICADA (SIN PHYSIJS, implementación manual AABB):

  class PhysicsBody {
    constructor(mesh, mass) {
      this.mesh     = mesh;
      this.velocity = new THREE.Vector3();
      this.mass     = mass;
      this.onGround = false;
    }
    update(dt) {
      if (!this.onGround) this.velocity.y -= 9.8 * dt;   // gravedad
      this.mesh.position.addScaledVector(this.velocity, dt);
      if (this.mesh.position.y <= this.groundY) {
        this.mesh.position.y = this.groundY;
        this.velocity.y      = 0;
        this.onGround        = true;
      }
    }
  }

  // Objetos interactuables con física:
  // - Cajas que el personaje puede agarrar (IK + constraint)
  // - Extintor que se activa (partículas CO2)
  // - Pallets que se pueden mover


══════════════════════════════════════════════════════════════════
SISTEMA DE INTERACCIÓN — RAYCAST + IK GRAB
══════════════════════════════════════════════════════════════════

MECÁNICA DE AGARRAR OBJETOS:

  // 1. Raycast desde cámara → detecta objeto interactuable
  // 2. Activar IK: target_mano_derecha = objeto.position
  // 3. FABRIK converge: brazo se extiende naturalmente hacia objeto
  // 4. Cuando distancia_mano < 0.2u:
  //    → objeto.parent = mano_derecha_bone
  //    → objeto sigue la mano en world space
  // 5. Animación dedos:
  //    dedo.rotation.z = lerp(abierto, cerrado, progreso)

  const INTERACTUABLES = [
    { id: 'extintor',       accion: 'USAR_EXTINTOR',           requiere: 'mision_3' },
    { id: 'casco_tirado',   accion: 'RECOGER_EPP',             requiere: 'mision_1' },
    { id: 'tablet',         accion: 'VER_CHECKLIST',           requiere: null       },
    { id: 'tambor_quimico', accion: 'INSPECCIONAR',            requiere: 'mision_2' },
    { id: 'botiquin',       accion: 'USAR_PRIMEROS_AUXILIOS',  requiere: 'mision_4' },
  ]


SISTEMA DE PARTÍCULAS PROCEDURAL (Object Pool):

  class ParticleSystem {
    constructor(maxParticles) {
      this.positions  = new Float32Array(maxParticles * 3);
      this.velocities = [];
      this.lifetimes  = new Float32Array(maxParticles);
      // BufferGeometry con atributo de posición dinámico
    }
    emit(origin, config) { /* reutiliza partículas muertas del pool */ }
    update(dt) {
      // Integración de Euler:
      //   velocidad += gravedad * dt
      //   posición  += velocidad * dt
      //   lifetime  -= dt  →  si <= 0, reciclar
      //   BufferAttribute.needsUpdate = true
    }
  }

  // Efectos de partículas:
  // - Polvo al caminar       (marrón,  20 partículas, velocidad baja)
  // - Chispas caja eléctrica (naranja, 50 partículas, velocidad alta)
  // - CO2 extintor           (blanco, 200 partículas, spread cónico)
  // - Confetti misión        (multicolor, gravedad + rebote)
  // - Humo zona química      (gris, escala up + fadeout)


══════════════════════════════════════════════════════════════════
MISIONES DEL JUEGO — MODO INSPECTOR SST
══════════════════════════════════════════════════════════════════

MISIÓN 1 — "Cacería de Peligros"  (Lección 6: Identificación de Peligros)
  Objetivo: encuentra y marca los 8 peligros ocultos en la bodega
  Lista de peligros:
    1. Cables en el piso          (zona recepción, brillan al acercarse)
    2. Extintor vencido           (revisar etiqueta con clic)
    3. Caja mal apilada           (animación oscilante sutil)
    4. Señal de emergencia tapada (bloqueada por pallet)
    5. Tambor químico sin tapa    (con etiqueta borrada)
    6. Escalera sin antideslizante
    7. Iluminación fundida        (zona oscura en esquina)
    8. Casco tirado en el piso
  Mecánica: al acercarse a 2m aparece tooltip "¿Es un peligro? [E]"
  Al presionar E → mini-quiz de 2 opciones
  Puntuación: +10 correcto / -5 falso positivo
  Tiempo límite: 5 minutos (reloj visible en HUD)

MISIÓN 2 — "Aplica los Controles"  (Lección 10: Jerarquía de Controles)
  Objetivo: asigna el control correcto a cada peligro encontrado
  Panel lateral con 5 controles arrastrables (raycasting):
    Eliminación / Sustitución / Ingeniería / Administrativo / EPP
  Ejemplo: cables en piso → Eliminación
           ruido          → EPP (protección auditiva)
  Si correcto: peligro desaparece con animación
  Si incorrecto: objeto vibra en rojo

MISIÓN 3 — "Señaliza la Bodega"  (Lección 12: Señalización)
  Objetivo: coloca 6 señales de seguridad en posiciones correctas
  Pool de señales (planos 3D con textura canvas):
    Prohibición / Advertencia / Obligación / Emergencia
  Mecánica: clic en señal del inventario → cursor cambia →
            clic en pared para ubicar
  Validación: ±30 cm de la posición correcta = válido

MISIÓN 4 — "Atiende el Accidente"  (Lección 13: Accidente Laboral)
  Escena: trabajador animado cae de una escalera
  El estudiante tiene 60 segundos para el protocolo correcto:
    Paso 1: asegurar la escena   (clic en zona peligrosa)
    Paso 2: llamar al médico     (clic en teléfono de pared)
    Paso 3: no mover accidentado (opción SI / NO)
    Paso 4: reportar a la ARL    (clic en computador de oficina)
  Si pasos en orden correcto → animación de ambulancia llegando
  Si falla              → pantalla roja "Procedimiento incorrecto"

MISIÓN 5 — "Examen Final de la Bodega"
  10 preguntas flotantes aparecen en distintas partes de la bodega
  El personaje camina hasta cada pregunta y responde
  Necesita 70% para completar el simulador


══════════════════════════════════════════════════════════════════
HUD — INTERFAZ EN PANTALLA
══════════════════════════════════════════════════════════════════

Esquina superior izquierda:
  - Logo INTEP (texto estilizado verde oscuro)
  - Nombre del estudiante (desde localStorage 'intep_student')
  - Misión actual con barra de progreso

Esquina superior derecha:
  - Puntuación: ⭐ 0/100
  - Reloj cuenta regresiva
  - Nivel de Inspector: Aprendiz → Técnico → Experto

Esquina inferior izquierda:
  - Minimapa 2D (OrthographicCamera secundaria en RenderTarget 256x256)
    Punto verde pulsante = jugador:  radio = 4 + sin(t*4)*2
    Puntos rojos parpadeantes = peligros no encontrados:
      frecuencia_parpadeo = 1 + (1/distancia_jugador) * 3

Centro (situacional):
  - Crosshair cuando hay objeto interactuable cerca
  - Tooltip con descripción del objeto
  - Dial de interacción circular (mantén E para activar)

Barra de estado (GLSL procedural):
  // gradiente dinámico: verde(100%) → amarillo(50%) → rojo(20%)
  // latido cuando salud < 30%:
  //   escala = 1.0 + sin(t * 8.0) * 0.05 * step(salud, 0.3)

Sistema de notificaciones con física de texto:
  // emerge desde abajo: y = target * (1 - e^(-5t))
  // se mantiene 2s luego fade: opacity = e^(-3*(t-2)) para t>2
  // CanvasTexture actualizada cada frame para texto dinámico


══════════════════════════════════════════════════════════════════
OPTIMIZACIÓN Y PERFORMANCE
══════════════════════════════════════════════════════════════════

  // 1. InstancedMesh para objetos repetidos (cajas, tornillos, pernos)
  //    Reduce draw calls de ~200 → 3

  // 2. LOD (Level of Detail) para racks lejanos:
  //    < 10u  : geometría full (500 triángulos)
  //    10-20u : geometría media (100 triángulos)
  //    > 20u  : quad billboard con textura

  // 3. Frustum culling manual:
  //    camera.frustum.containsPoint(objeto.position)

  // 4. Texture atlas: todas las CanvasTextures en atlas 2048x2048
  //    con UV mapping matemático por sub-textura

  // 5. Object pooling para partículas (nunca new/delete en runtime)

  // 6. requestAnimationFrame con delta time:
  //    movimiento = velocidad * deltaTime  (independiente de FPS)


══════════════════════════════════════════════════════════════════
ESPECIFICACIONES TÉCNICAS OBLIGATORIAS
══════════════════════════════════════════════════════════════════

- Resultado: UN SOLO archivo HTML completamente funcional
- Todo inline: JS + CSS + GLSL shaders
- Longitud esperada: 3000–5000 líneas de código real (no pseudocódigo)
- CDN permitidos ÚNICAMENTE:
    https://cdnjs.cloudflare.com/ajax/libs/three.js/r158/three.min.js
    https://cdn.jsdelivr.net/npm/three@0.158/examples/jsm/postprocessing/EffectComposer.js
    (y los imports necesarios de jsm/postprocessing)
- CERO assets externos: texturas, modelos y fuentes → todo procedural/canvas
- Target de performance: 60 fps estables en GTX 1060 / Intel Iris Xe
- Controles:
    WASD        → mover personaje
    Mouse       → rotar cámara (pointer lock)
    E           → interactuar / agarrar objeto
    Shift       → correr
    C           → agacharse
    ESC         → pausa con resumen de progreso
- Responsive: mínimo 1024×768; mostrar aviso amigable en pantallas menores
- Al completar el simulador guardar en localStorage:
    localStorage.setItem('sstSimulador', JSON.stringify({
      completado  : true,
      puntuacion  : X,
      tiempo      : Y,
      errores     : [ ...lista de errores con explicación didáctica ]
    }))
- Pantalla de resultados final:
    puntuación / tiempo / errores cometidos + explicación pedagógica de cada uno
- Botón "Volver al Curso" → window.location = 'CURSO_SST_INTERACTIVO.html'
- charset UTF-8, lang="es", title="Simulador SST — INTEP"
- Comentarios en el código explicando cada sistema matemático implementado
- El código debe ser COMPLETO y EJECUTABLE, no pseudocódigo ni esqueletos vacíos
