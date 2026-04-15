# 📖 Manual de la Plataforma e-Learning: LinguaPro

Este documento sirve como hoja de ruta y justificación técnica/pedagógica de la plataforma de inglés interactiva desarrollada. Está destinado a los administradores del instituto, desarrolladores e instructores.

---

## 1. Arquitectura Técnica (Frontend)
El proyecto ha sido diseñado empleando un stack robusto de tecnologías universales (HTML5 y CSS3 Vainilla) asegurando máxima compatibilidad y rapidez, eliminando la necesidad de frameworks pesados de frontend.

* **Estructura Modular:** Se ha construido un sistema de navegación mediante `dashboards` (A1, A2, B1).
* **UI/UX y "Premium Aesthetic":** 
    * Se utiliza una tipografía moderna de Google Fonts (`Outfit` para títulos, `Inter` para cuerpos de texto).
    * Se implementó un "Progressive Color System" (Sistema de colores progresivos) para identificar niveles:
        * **A1 Beginner:** Gradiente Índigo a Rosa (`#6366f1` -> `#ec4899`).
        * **A2 Elementary:** Gradiente Rosa fuerte a Rojo coral oscuro.
        * **B1 Intermediate:** Gradiente Dorado/Mostaza (`#eab308`).
* **Interactividad (Flashcards 3D):** Los elementos del vocabulario están creados utilizando transformaciones de CSS3D (`rotateY(180deg)`), permitiendo al usuario descubrir la traducción y pronunciación solo por medio de la interacción.

---

## 2. Metodología de Aprendizaje (Pedagogía para Adultos)
El fracaso convencional en adultos aprendiendo inglés se debe al enfoque de "explicar la regla primero, hablar después". LinguaPro invierte ese paradigma utilizando 4 principios respaldados por las normativas CEFR:

1. **Task-Based Learning (TBL):** No se aprende por aprender. Cada módulo (M1 a M8) es una "Misión" a cumplir (ej. Conseguir trabajo, Vender un producto, Ser testigo de un accidente). El estudiante aprende la gramática por la *necesidad de completar la misión*.
2. **Output Activo (Desde el minuto 1):** Obliga al estudiante a producir sonido de inmediato en la sección de Roleplay integrado. Elimina el miedo a equivocarse.
3. **Scaffolding e Inmersión Oculta:** Las explicaciones aburridas han sido reemplazadas por la sección *"Read & Discover"*. Aquí la estructura gramatical (ej. *Present Continuous*) sucede naturalmente dentro de una pequeña historia humorística. El cerebro asimila el patrón antes de leer la regla técnica.
4. **Simplificación ("Grupo Normal vs Grupo VIP"):** En vez de usar terminología como "Tercera persona del singular", usamos nombres memorables e intuitivos (como "El Grupo VIP" que lleva corona y requiere agregar 'S'). 

---

## 3. Estructura de Clases (El ADN de cada módulo)
Para mantener a los alumnos motivados, cada `moduloX.html` respeta una estructura invariable de 4 pasos garantizados:

1. 🖼️ **Vocabulario Interactivo (Action Flashcards):** Sin traducciones frías. Tarjetas visuales que asocian emociones y acción a la palabra.
2. 📖 **Contexto/Historia (Story Box):** Demostración nativa en un contexto realista o humorístico. 
3. 💡 **El "Secreto" Gramatical (Cheat Sheet):** Reglas puestas en cuadros comparativos ultra limpios, fáciles de fotografiar o memorizar visualmente.
4. 🎯 **Misión (Roleplay Épico):** Un juego de identidades secretas donde un alumno toma un rol (ej. Mesero) y otro su contraparte (Cliente Tacaño). Esto garantiza el habla natural.

### Alcance Curricular
Hemos mapeado y programado la estructura de:
* **A1:** 8 Módulos (Desde Verbo To Be y posesiones hasta inicio del pasado simple).
* **A2:** 8 Módulos (Desde Past Continuous profundo, Futuro Will/Going to, y condicionales iniciales).
* **B1:** 8 Módulos (Gramática abstracta: Pasivas, Reported Speech, Condicionales hipotéticos y Perfectos).

### ¿Qué sigue para los Desarrolladores?
Integrar estas plantillas HTML con el backend de autenticación del Instituto. Cada que un alumno presione "Completar Módulo", el sistema debe actualizar la barra en el archivo `dashboard_X.html` aumentando el porcentaje CSS del `.progress-bar`.
