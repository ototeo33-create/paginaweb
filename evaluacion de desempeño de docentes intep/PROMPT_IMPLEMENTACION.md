```
PROMPT PARA IMPLEMENTAR SISTEMA DE EVALUACIÓN DOCENTE - INTEP
================================================================

CONTEXTO DEL PROYECTO:
----------------------
Instituto Técnico Pedagógico (INTEP) necesita un sistema de evaluación de desempeño 
docente donde los estudiantes evalúan a sus profesores de forma anónima.

COLORES INSTITUCIONALES:
------------------------
- Verde Principal: #059669
- Verde Claro: #10B981  
- Verde Oscuro: #047857
- Verde Pale: #ECFDF5
- Morado Principal: #4A1942
- Morado Medio: #6B3FA0
- Morado Claro: #9B6FCF
- Crema (fondo): #F5F2EC
- Tipografía: 'Exo 2' (Google Fonts)

ARCHIVOS GENERADOS:
-------------------
1. index.html - Formulario para estudiantes (evaluar docentes)
2. admin.html - Panel del administrador
3. docente.html - Panel del docente (ver resultados)
4. logointep.png - Logo del instituto
5. gracias.html - Modal de agradecimiento (referencia)

================================================================
ESTRUCTURA DE LA BASE DE DATOS NECESARIA:
================================================================

TABLA: evaluacion_control
-------------------------
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- periodo (VARCHAR 50) - ej: "2025-2026 II"
- evaluacion_activa (BOOLEAN) - 1 = activa, 0 = inactiva
- fecha_inicio (DATETIME)
- fecha_fin (DATETIME)
- created_at (DATETIME)
- updated_at (DATETIME)

TABLA: docentes
---------------
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- nombre (VARCHAR 255)
- modulo (VARCHAR 255) - materia o programa que imparte
- email (VARCHAR 255)
- created_at (DATETIME)

TABLA: evaluaciones
-------------------
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- docente_id (INT, FOREIGN KEY -> docentes.id)
- estudiante_programa (VARCHAR 255) - programa del estudiante (confidencial)
- estudiante_jornada (VARCHAR 50) - mañana/tarde/noche
- estudiante_grupo (VARCHAR 50) - número de ficha
- periodo (VARCHAR 50)
- fecha_evaluacion (DATETIME)
- comentarios_positivos (TEXT) - opcional
- comentarios_negativos (TEXT) - opcional
- created_at (DATETIME)

TABLA: evaluacion_respuestas
----------------------------
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- evaluacion_id (INT, FOREIGN KEY -> evaluaciones.id)
- criterio_id (INT) - 1 a 8 (ver criterios abajo)
- calificacion (INT) - 1 a 4

CRITERIOS DE EVALUACIÓN (8 en total):
------------------------------------
1. Dominio del Contenido
2. Claridad en la Explicación
3. Metodología de Enseñanza
4. Relación con los Estudiantes
5. Gestión del Aula
6. Evaluación y Retroalimentación
7. Puntualidad y Asistencia
8. Uso de Recursos Tecnológicos

ESCALA DE CALIFICACIÓN:
-----------------------
- 4 = Excelente
- 3 = Bueno
- 2 = Regular
- 1 = Insuficiente

================================================================
FÓRMULAS DE CÁLCULO:
================================================================

CÁLCULO DE PROMEDIO POR CRITERIO:
---------------------------------
Promedio_Criterio_N = SUM(calificacion) / COUNT(evaluacion_id)
                     WHERE criterio_id = N AND docente_id = X

Ejemplo: Si 18 estudiantes evaluaron y en criterio 1:
- 8 dieron 4, 6 dieron 3, 3 dieron 2, 1 dio 1
- Promedio = (8*4 + 6*3 + 3*2 + 1*1) / 18 = (32+18+6+1)/18 = 57/18 = 3.17

CÁLCULO DE NOTA FINAL (PORCENTAJE):
----------------------------------
1. Sumar todos los promedios de los 8 criterios
2. Dividir entre 8 (número de criterios)
3. Multiplicar por 25 para obtener porcentaje

Formula = (SUM(promedio_criterios) / 8) * 25

Ejemplo:
- Promedios: 3.8, 3.5, 3.2, 3.7, 3.0, 3.6, 3.9, 2.7
- Suma = 27.4
- Promedio general = 27.4 / 8 = 3.425
- Nota final (%) = 3.425 * 25 = 85.6%

CLASIFICACIÓN:
--------------
- 90-100%: Excelente (mensaje motivacional verde)
- 75-89%: Bueno (mensaje motivacional azul)
- 50-74%: Regular (mensaje motivacional amarillo)
- 0-49%: Insuficiente (mensaje motivacional morado)

================================================================
DESCRIPCIÓN DE CADA PÁGINA:
================================================================

1. FORMULARIO ESTUDIANTE (index.html)
=====================================
- Logo INTEP centrado en sección verde
- Título "Evaluación Docente" debajo en crema
- Sección: Datos del Estudiante (confidencial)
  * Programa (select con opciones de programas INTEP)
  * Jornada (select: mañana/tarde/noche/sábados)
  * Grupo/Ficha (input text)
  * Fecha (auto-completada con fecha actual)
  
- Sección: Datos del Docente Evaluado
  * Nombre del docente (input text)
  * Materia/Modulo (input text)
  
- Sección: 8 Criterios de Evaluación
  * Cada criterio tiene nombre, descripción y 4 botones (1-4)
  * Botones de colores según nivel
  * Se actualiza el resumen en tiempo real
  
- Sección: Comentarios (opcionales)
  * Campo para aspectos positivos
  * Campo para áreas de mejora
  
- Resumen visual:
  * Puntos obtenidos / Puntos máximos
  * Porcentaje
  * Estado (badge de color)
  
- Botón "Guardar Evaluación"
  * Valida que todo esté completo
  * Guarda en base de datos
  * Muestra modal de agradecimiento
  
- Modal de agradecimiento:
  * Icono verde de check
  * "¡Gracias por tu participación!"
  * Mensaje motivacional
  * Botón "Nueva Evaluación"
  * Botón "Cerrar"

- Banner inferior:
  * Mensaje de confidencialidad/anonymous

2. PANEL ADMINISTRADOR (admin.html)
===================================
- Topbar verde con menú horizontal:
  * Inicio
  * Evaluación (control toggle)
  * Docentes
  * Reportes
  
- Tarjeta: Control de Evaluación
  * Toggle on/off para activar/desactivar formulario
  * Badge de estado (Activo/Inactivo)
  * Botón "Ver Formulario" (abre index.html)
  * Botón "Guardar Cambios"
  
- Tarjeta: Estadísticas
  * Total estudiantes
  * Total docentes
  * Total evaluaciones
  * Porcentaje completadas
  
- Tarjeta: Historial de Evaluaciones
  * Tabla con: fecha, periodo, docente, evaluaciones, estado, acciones
  * Botón "Ver" para cada fila

3. PANEL DOCENTE (docente.html)
===============================
- Topbar verde con menú:
  * Mis Resultados
  * Historial
  
- Info del docente (sin avatar):
  * Nombre: Prof. María González
  * Módulo: Auxiliar Administrativa

- Tarjeta: Resumen de Puntuación
  * Nota Final (grande, verde): 92%
  * Promedio General (morado): 3.68/4
  * Cantidad de evaluaciones
  * Badge de estado

- Tarjeta: Resultados por Criterio
  * Selector de periodo (3 opciones)
  * Lista de 8 criterios con:
    - Nombre del criterio
    - Barra de progreso (color según nivel)
    - Score numérico (ej: 3.8/4)
  * Colores de barras:
    - Verde: >=3.5 (Excelente)
    - Azul: >=2.5 (Bueno)
    - Amarillo: >=1.5 (Regular)
    - Rojo: <1.5 (Insuficiente)

- Tarjeta: Comparativa (placeholders para gráficos)
  * Gráfico de tendencia por periodo
  * Gráfico de distribución

- Tarjeta: Comentarios de Estudiantes
  * Lista de comentarios
  * Indicador si es positivo o área de mejora
  * Borde verde para positivos, amarillo para mejoras

- Tarjeta: Mensaje Motivacional (dinámico)
  * Aparece según el porcentaje final
  * Icono grande
  * Título motivacional
  * Mensaje personalizado según nivel
  
  Mensajes por nivel:
  - Excelente (90%+): "¡Felicidades! ¡Eres un docente excepcional!"
  - Bueno (75-89%): "¡Muy bien! Estás en el camino correcto"
  - Regular (50-74%): "Tienes potencial, ¡anímate a mejorar!"
  - Insuficiente (<50%): "Este es el momento de reinventarte"

================================================================
LÓGICA DE NEGOCIO:
================================================================

FLUJO DE EVALUACIÓN:
--------------------
1. Admin activa la evaluación desde admin.html
2. Estudiantes acceden a index.html
3. Completan el formulario selecting docente y criterios
4. Guardan (se guarda en base de datos)
5. Ven mensaje de agradecimiento
6. Admin puede ver estadísticas
7. Docentes ven sus resultados en docente.html

CONSULTAS SQL IMPORTANTES:
-------------------------

-- Obtener promedio por criterio para un docente
SELECT 
    er.criterio_id,
    AVG(er.calificacion) as promedio,
    COUNT(*) as total_evaluaciones
FROM evaluacion_respuestas er
JOIN evaluaciones e ON er.evaluacion_id = e.id
WHERE e.docente_id = ? AND e.periodo = ?
GROUP BY er.criterio_id;

-- Obtener nota final de un docente
SELECT 
    AVG(er.calificacion) * 25 as nota_final,
    COUNT(DISTINCT e.id) as total_evaluaciones
FROM evaluacion_respuestas er
JOIN evaluaciones e ON er.evaluacion_id = e.id
WHERE e.docente_id = ? AND e.periodo = ?;

-- Obtener comentarios de un docente
SELECT 
    comentarios_positivos,
    comentarios_negativos
FROM evaluaciones
WHERE docente_id = ? AND periodo = ?;

-- Total de evaluaciones por docente
SELECT 
    d.nombre,
    COUNT(e.id) as total,
    d.modulo
FROM docentes d
LEFT JOIN evaluaciones e ON d.id = e.docente_id
GROUP BY d.id;

================================================================
SEGURIDAD Y CONFIDENCIALIDAD:
==============================

- Datos del estudiante son CONFIDENCIALES (no se muestran al docente)
- Evaluaciones son ANÓNIMAS (docente no ve quién evaluó)
- Solo ve cantidad de evaluaciones y promedios
- Admin tiene control total sobre activación de evaluaciones
- Período académico para filtrar resultados

================================================================
NOTAS DE IMPLEMENTACIÓN:
========================

1. El formulario (index.html) debe verificar si la evaluación está activa
   consultando evaluacion_control.evaluacion_activa

2. El modal de agradecimiento debe cerrarse y limpiar el formulario

3. Los datos del docente (nombre, módulo) se cargan desde la sesión

4. Los resultados del docente se actualizan en tiempo real según periodo seleccionado

5. Los placeholders de gráficos pueden implementarse con Chart.js o similar

6. El toggle del admin debe guardar en evaluacion_control

7. Exportar a PDF es opcional pero recomendado (tcpdf, dompdf, etc.)

================================================================
EJEMPLO DE RESPUESTA JSON AL GUARDAR:
=====================================

{
  "fecha": "2026-04-14T10:30:00.000Z",
  "estudiante": {
    "programa": "Auxiliar Administrativo",
    "jornada": "Mañana",
    "grupo": "2512345",
    "fechaEvaluacion": "2026-04-14"
  },
  "docente": {
    "nombre": "María González",
    "materia": "Contabilidad Básica"
  },
  "calificaciones": {
    "dominio": 4,
    "claridad": 3,
    "metodologia": 4,
    "relacion": 4,
    "gestion": 3,
    "evaluacion": 4,
    "puntualidad": 4,
    "recursos": 3
  },
  "comentarios": {
    "positivos": "Explica muy bien los temas",
    "negativos": "Podría usar más recursos tecnológicos"
  }
}

================================================================
FIN DEL PROMPT
================================================================
```
