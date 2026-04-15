<?php
ini_set('display_errors', 0);
error_reporting(0);
// ============================================================
// INTEP INGLÉS — Ejercicios hardcodeados por nivel (sin IA)
// ============================================================
ob_start();
require_once '../config.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$est_id = (int)$_SESSION['estudiante_id'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── Helper: obtener nivel del estudiante ────────────────
function get_nivel(int $est_id, $db): string {
    $st = mysqli_prepare($db, "SELECT nivel_actual FROM idiomas_nivel WHERE estudiante_id = ?");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    return $r['nivel_actual'] ?? 'A1';
}

// ── Helper: evaluar traducción sin IA ──────────────────
function evaluar_simple(string $resp, string $correcta): array {
    $norm = fn(string $s) => mb_strtolower(trim(preg_replace('/[^\p{L}\s]/u', '', $s)));
    $r = $norm($resp);
    $c = $norm($correcta);
    if ($r === $c) return ['es_correcto' => true,  'explicacion' => '¡Perfecto! Esa es la respuesta correcta.'];
    similar_text($r, $c, $pct);
    if ($pct >= 75) return ['es_correcto' => true,  'explicacion' => '¡Correcto! La respuesta exacta es: ' . $correcta];
    return            ['es_correcto' => false, 'explicacion' => 'La respuesta correcta es: ' . $correcta];
}

// ============================================================
// BANCO DE EJERCICIOS — 30 por nivel
// ============================================================
$EJERCICIOS = [

// ────────────────────────────────────────────────────────────
// NIVEL A1 — Principiante absoluto
// ────────────────────────────────────────────────────────────
'A1' => [
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'ROJO','traduccion_ayuda'=>null,'opciones'=>['A. red','B. blue','C. green','D. yellow'],'correcta'=>'A','respuesta_texto'=>'red','explicacion'=>'Rojo en inglés es RED.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'AZUL','traduccion_ayuda'=>null,'opciones'=>['A. red','B. blue','C. black','D. white'],'correcta'=>'B','respuesta_texto'=>'blue','explicacion'=>'Azul en inglés es BLUE.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'VERDE','traduccion_ayuda'=>null,'opciones'=>['A. red','B. yellow','C. green','D. purple'],'correcta'=>'C','respuesta_texto'=>'green','explicacion'=>'Verde en inglés es GREEN.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'AMARILLO','traduccion_ayuda'=>null,'opciones'=>['A. orange','B. pink','C. white','D. yellow'],'correcta'=>'D','respuesta_texto'=>'yellow','explicacion'=>'Amarillo en inglés es YELLOW.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'NEGRO','traduccion_ayuda'=>null,'opciones'=>['A. black','B. gray','C. brown','D. blue'],'correcta'=>'A','respuesta_texto'=>'black','explicacion'=>'Negro en inglés es BLACK.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'BLANCO','traduccion_ayuda'=>null,'opciones'=>['A. black','B. white','C. gray','D. brown'],'correcta'=>'B','respuesta_texto'=>'white','explicacion'=>'Blanco en inglés es WHITE.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'PERRO','traduccion_ayuda'=>null,'opciones'=>['A. cat','B. dog','C. bird','D. fish'],'correcta'=>'B','respuesta_texto'=>'dog','explicacion'=>'Perro en inglés es DOG.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'GATO','traduccion_ayuda'=>null,'opciones'=>['A. cat','B. dog','C. cow','D. bird'],'correcta'=>'A','respuesta_texto'=>'cat','explicacion'=>'Gato en inglés es CAT.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'PÁJARO','traduccion_ayuda'=>null,'opciones'=>['A. fish','B. horse','C. bird','D. pig'],'correcta'=>'C','respuesta_texto'=>'bird','explicacion'=>'Pájaro en inglés es BIRD.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'PEZ','traduccion_ayuda'=>null,'opciones'=>['A. bird','B. cow','C. dog','D. fish'],'correcta'=>'D','respuesta_texto'=>'fish','explicacion'=>'Pez en inglés es FISH.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'VACA','traduccion_ayuda'=>null,'opciones'=>['A. cow','B. pig','C. sheep','D. horse'],'correcta'=>'A','respuesta_texto'=>'cow','explicacion'=>'Vaca en inglés es COW.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice el número en inglés?','pregunta'=>'TRES (3)','traduccion_ayuda'=>null,'opciones'=>['A. one','B. two','C. three','D. four'],'correcta'=>'C','respuesta_texto'=>'three','explicacion'=>'El número 3 en inglés es THREE.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice el número en inglés?','pregunta'=>'SIETE (7)','traduccion_ayuda'=>null,'opciones'=>['A. five','B. six','C. seven','D. eight'],'correcta'=>'C','respuesta_texto'=>'seven','explicacion'=>'El número 7 en inglés es SEVEN.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cuánto es "ten" en español?','pregunta'=>'TEN','traduccion_ayuda'=>null,'opciones'=>['A. 6','B. 8','C. 9','D. 10'],'correcta'=>'D','respuesta_texto'=>'diez (10)','explicacion'=>'TEN en español es DIEZ (10).'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'HOLA','traduccion_ayuda'=>null,'opciones'=>['A. bye','B. hello','C. please','D. thanks'],'correcta'=>'B','respuesta_texto'=>'hello','explicacion'=>'Hola en inglés es HELLO.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'GRACIAS','traduccion_ayuda'=>null,'opciones'=>['A. hello','B. sorry','C. thank you','D. please'],'correcta'=>'C','respuesta_texto'=>'thank you','explicacion'=>'Gracias en inglés es THANK YOU.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'POR FAVOR','traduccion_ayuda'=>null,'opciones'=>['A. please','B. sorry','C. hello','D. bye'],'correcta'=>'A','respuesta_texto'=>'please','explicacion'=>'Por favor en inglés es PLEASE.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'MAMÁ','traduccion_ayuda'=>null,'opciones'=>['A. father','B. sister','C. mother','D. brother'],'correcta'=>'C','respuesta_texto'=>'mother','explicacion'=>'Mamá en inglés es MOTHER o MOM.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'PAPÁ','traduccion_ayuda'=>null,'opciones'=>['A. father','B. sister','C. mother','D. uncle'],'correcta'=>'A','respuesta_texto'=>'father','explicacion'=>'Papá en inglés es FATHER o DAD.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'HERMANO','traduccion_ayuda'=>null,'opciones'=>['A. sister','B. cousin','C. brother','D. uncle'],'correcta'=>'C','respuesta_texto'=>'brother','explicacion'=>'Hermano en inglés es BROTHER.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'LIBRO','traduccion_ayuda'=>null,'opciones'=>['A. pen','B. book','C. table','D. chair'],'correcta'=>'B','respuesta_texto'=>'book','explicacion'=>'Libro en inglés es BOOK.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'LÁPIZ','traduccion_ayuda'=>null,'opciones'=>['A. book','B. table','C. pencil','D. door'],'correcta'=>'C','respuesta_texto'=>'pencil','explicacion'=>'Lápiz en inglés es PENCIL.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'AGUA','traduccion_ayuda'=>null,'opciones'=>['A. milk','B. juice','C. water','D. food'],'correcta'=>'C','respuesta_texto'=>'water','explicacion'=>'Agua en inglés es WATER.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa la frase:','pregunta'=>'I ___ a student.','traduccion_ayuda'=>'Yo ___ estudiante.','opciones'=>['A. am','B. is','C. are','D. be'],'correcta'=>'A','respuesta_texto'=>'am','explicacion'=>'Con "I" usamos AM: I am a student.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa la frase:','pregunta'=>'She ___ happy.','traduccion_ayuda'=>'Ella ___ feliz.','opciones'=>['A. am','B. is','C. are','D. be'],'correcta'=>'B','respuesta_texto'=>'is','explicacion'=>'Con "she/he/it" usamos IS: She is happy.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa la frase:','pregunta'=>'They ___ friends.','traduccion_ayuda'=>'Ellos ___ amigos.','opciones'=>['A. am','B. is','C. are','D. be'],'correcta'=>'C','respuesta_texto'=>'are','explicacion'=>'Con "they/we/you" usamos ARE: They are friends.'],
    ['tipo'=>'multiple_choice','instruccion'=>'Elige la respuesta correcta:','pregunta'=>'¿Cómo se dice CASA en inglés?','traduccion_ayuda'=>null,'opciones'=>['A. car','B. house','C. school','D. park'],'correcta'=>'B','respuesta_texto'=>'house','explicacion'=>'Casa en inglés es HOUSE.'],
    ['tipo'=>'multiple_choice','instruccion'=>'Elige la respuesta correcta:','pregunta'=>'¿De qué color es el cielo? (What color is the sky?)','traduccion_ayuda'=>null,'opciones'=>['A. red','B. green','C. blue','D. yellow'],'correcta'=>'C','respuesta_texto'=>'blue','explicacion'=>'El cielo es azul - BLUE.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Buenos días','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'Good morning','explicacion'=>'Buenos días en inglés es GOOD MORNING.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Buenas noches','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'Good night','explicacion'=>'Buenas noches en inglés es GOOD NIGHT.'],
],

// ────────────────────────────────────────────────────────────
// NIVEL A2 — Básico con vocabulario ampliado
// ────────────────────────────────────────────────────────────
'A2' => [
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'MANZANA','traduccion_ayuda'=>null,'opciones'=>['A. orange','B. apple','C. banana','D. grape'],'correcta'=>'B','respuesta_texto'=>'apple','explicacion'=>'Manzana en inglés es APPLE.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'PAN','traduccion_ayuda'=>null,'opciones'=>['A. milk','B. rice','C. bread','D. meat'],'correcta'=>'C','respuesta_texto'=>'bread','explicacion'=>'Pan en inglés es BREAD.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'LECHE','traduccion_ayuda'=>null,'opciones'=>['A. milk','B. water','C. juice','D. tea'],'correcta'=>'A','respuesta_texto'=>'milk','explicacion'=>'Leche en inglés es MILK.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'HUEVO','traduccion_ayuda'=>null,'opciones'=>['A. meat','B. rice','C. cheese','D. egg'],'correcta'=>'D','respuesta_texto'=>'egg','explicacion'=>'Huevo en inglés es EGG.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'CABEZA','traduccion_ayuda'=>null,'opciones'=>['A. hand','B. head','C. foot','D. arm'],'correcta'=>'B','respuesta_texto'=>'head','explicacion'=>'Cabeza en inglés es HEAD.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'MANO','traduccion_ayuda'=>null,'opciones'=>['A. foot','B. eye','C. hand','D. ear'],'correcta'=>'C','respuesta_texto'=>'hand','explicacion'=>'Mano en inglés es HAND.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'OJO','traduccion_ayuda'=>null,'opciones'=>['A. ear','B. nose','C. mouth','D. eye'],'correcta'=>'D','respuesta_texto'=>'eye','explicacion'=>'Ojo en inglés es EYE.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'LUNES','traduccion_ayuda'=>null,'opciones'=>['A. Sunday','B. Tuesday','C. Monday','D. Friday'],'correcta'=>'C','respuesta_texto'=>'Monday','explicacion'=>'Lunes en inglés es MONDAY.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'VIERNES','traduccion_ayuda'=>null,'opciones'=>['A. Monday','B. Friday','C. Saturday','D. Wednesday'],'correcta'=>'B','respuesta_texto'=>'Friday','explicacion'=>'Viernes en inglés es FRIDAY.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'GRANDE','traduccion_ayuda'=>null,'opciones'=>['A. small','B. tall','C. big','D. old'],'correcta'=>'C','respuesta_texto'=>'big','explicacion'=>'Grande en inglés es BIG o LARGE.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'PEQUEÑO','traduccion_ayuda'=>null,'opciones'=>['A. big','B. small','C. short','D. thin'],'correcta'=>'B','respuesta_texto'=>'small','explicacion'=>'Pequeño en inglés es SMALL.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'FELIZ','traduccion_ayuda'=>null,'opciones'=>['A. sad','B. angry','C. happy','D. tired'],'correcta'=>'C','respuesta_texto'=>'happy','explicacion'=>'Feliz en inglés es HAPPY.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'TRISTE','traduccion_ayuda'=>null,'opciones'=>['A. happy','B. bored','C. scared','D. sad'],'correcta'=>'D','respuesta_texto'=>'sad','explicacion'=>'Triste en inglés es SAD.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la forma correcta del verbo:','pregunta'=>'He ___ to school every day.','traduccion_ayuda'=>'Él ___ a la escuela todos los días.','opciones'=>['A. go','B. goes','C. going','D. gone'],'correcta'=>'B','respuesta_texto'=>'goes','explicacion'=>'Con he/she/it en presente simple añadimos -S: He GOES.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa la frase:','pregunta'=>'I ___ English every evening.','traduccion_ayuda'=>'Yo ___ inglés cada tarde.','opciones'=>['A. studys','B. studied','C. study','D. studies'],'correcta'=>'C','respuesta_texto'=>'study','explicacion'=>'Con "I" el presente simple no cambia: I STUDY.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa la pregunta:','pregunta'=>'Do you ___ coffee?','traduccion_ayuda'=>'¿___ te gusta el café?','opciones'=>['A. likes','B. like','C. liked','D. liking'],'correcta'=>'B','respuesta_texto'=>'like','explicacion'=>'Con DO you, el verbo va en forma base: Do you LIKE?'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa la frase:','pregunta'=>'We ___ to the park on Sundays.','traduccion_ayuda'=>'Nosotros ___ al parque los domingos.','opciones'=>['A. goes','B. going','C. go','D. gone'],'correcta'=>'C','respuesta_texto'=>'go','explicacion'=>'Con we/they/I/you el verbo no cambia: We GO.'],
    ['tipo'=>'multiple_choice','instruccion'=>'Elige la forma correcta:','pregunta'=>'Which is correct?','traduccion_ayuda'=>null,'opciones'=>['A. She play tennis','B. She plays tennis','C. She playing tennis','D. She played tennis'],'correcta'=>'B','respuesta_texto'=>'She plays tennis','explicacion'=>'Con she/he/it añadimos -S en presente simple: She PLAYS.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa esta palabra?','pregunta'=>'"HUNGRY"','traduccion_ayuda'=>null,'opciones'=>['A. cansado','B. feliz','C. hambriento','D. aburrido'],'correcta'=>'C','respuesta_texto'=>'hambriento','explicacion'=>'HUNGRY significa tener HAMBRE.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa esta pregunta?','pregunta'=>'"What time is it?"','traduccion_ayuda'=>null,'opciones'=>['A. ¿Cómo estás?','B. ¿Cuánto cuesta?','C. ¿Dónde estás?','D. ¿Qué hora es?'],'correcta'=>'D','respuesta_texto'=>'¿Qué hora es?','explicacion'=>'WHAT TIME IS IT? significa ¿QUÉ HORA ES?'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Cuál es la pregunta correcta?','pregunta'=>'Quieres preguntar si alguien habla español:','traduccion_ayuda'=>null,'opciones'=>['A. Do you speaks Spanish?','B. Does you speak Spanish?','C. Do you speak Spanish?','D. Are you speak Spanish?'],'correcta'=>'C','respuesta_texto'=>'Do you speak Spanish?','explicacion'=>'Con DO + you + verbo base: Do you SPEAK Spanish?'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa?','pregunta'=>'"I wake up at 6 a.m."','traduccion_ayuda'=>null,'opciones'=>['A. Me duermo a las 6','B. Como a las 6','C. Me levanto a las 6','D. Llego a las 6'],'correcta'=>'C','respuesta_texto'=>'Me levanto a las 6','explicacion'=>'WAKE UP significa despertarse / levantarse.'],
    ['tipo'=>'multiple_choice','instruccion'=>'Elige la respuesta correcta:','pregunta'=>'How old are you? — I ___ 20 years old.','traduccion_ayuda'=>null,'opciones'=>['A. have','B. am','C. is','D. be'],'correcta'=>'B','respuesta_texto'=>'am','explicacion'=>'En inglés la edad se expresa con TO BE: I AM 20 years old.'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"She don\'t like coffee."','traduccion_ayuda'=>null,'opciones'=>['A. She not like coffee.','B. She doesn\'t like coffee.','C. She don\'t likes coffee.','D. She isn\'t like coffee.'],'correcta'=>'B','respuesta_texto'=>'She doesn\'t like coffee.','explicacion'=>'Con she/he/it la negación es DOESN\'T (not don\'t).'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"We gos to school by bus."','traduccion_ayuda'=>null,'opciones'=>['A. We go to school by bus.','B. We goes to school by bus.','C. We going to school by bus.','D. We gone to school by bus.'],'correcta'=>'A','respuesta_texto'=>'We go to school by bus.','explicacion'=>'Con WE el verbo no cambia: We GO (no gos).'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con There is / There are:','pregunta'=>'___ two cats in the garden.','traduccion_ayuda'=>null,'opciones'=>['A. There is','B. There are','C. Is there','D. Are there'],'correcta'=>'B','respuesta_texto'=>'There are','explicacion'=>'Con sustantivos PLURALES usamos THERE ARE.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con There is / There are:','pregunta'=>'___ a dog in the house.','traduccion_ayuda'=>null,'opciones'=>['A. There are','B. Are there','C. There is','D. Is there'],'correcta'=>'C','respuesta_texto'=>'There is','explicacion'=>'Con sustantivos SINGULARES usamos THERE IS.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Me llamo Carlos.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'My name is Carlos.','explicacion'=>'My name is... = Me llamo...'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Tengo 20 años.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'I am 20 years old.','explicacion'=>'En inglés la edad usa TO BE: I am 20 years old.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Me gusta el inglés.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'I like English.','explicacion'=>'Me gusta = I like. El verbo LIKE no lleva TO con I.'],
],

// ────────────────────────────────────────────────────────────
// NIVEL B1 — Intermedio
// ────────────────────────────────────────────────────────────
'B1' => [
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la forma correcta:','pregunta'=>'I have ___ visited London. (ya)','traduccion_ayuda'=>null,'opciones'=>['A. yet','B. still','C. already','D. just'],'correcta'=>'C','respuesta_texto'=>'already','explicacion'=>'ALREADY = ya (en oraciones afirmativas de presente perfecto).'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la forma correcta:','pregunta'=>'Have you ___ eaten sushi?','traduccion_ayuda'=>null,'opciones'=>['A. yet','B. ever','C. already','D. never'],'correcta'=>'B','respuesta_texto'=>'ever','explicacion'=>'EVER se usa en preguntas de presente perfecto: Have you EVER...?'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la forma correcta:','pregunta'=>'She has just ___ her homework.','traduccion_ayuda'=>null,'opciones'=>['A. finish','B. finishing','C. finished','D. finishes'],'correcta'=>'C','respuesta_texto'=>'finished','explicacion'=>'Presente perfecto: has + PAST PARTICIPLE. Finished es el participio de finish.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa en pasado simple:','pregunta'=>'They ___ to Paris last year.','traduccion_ayuda'=>null,'opciones'=>['A. go','B. goes','C. gone','D. went'],'correcta'=>'D','respuesta_texto'=>'went','explicacion'=>'GO es irregular. Pasado simple: WENT.'],
    ['tipo'=>'fill_blank','instruccion'=>'Condicional — completa:','pregunta'=>'If it rains tomorrow, I ___ stay home.','traduccion_ayuda'=>null,'opciones'=>['A. would','B. will','C. should','D. might'],'correcta'=>'B','respuesta_texto'=>'will','explicacion'=>'First conditional (situación posible): If + present → WILL + verbo base.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con el modal correcto:','pregunta'=>'You ___ see a doctor. You look sick.','traduccion_ayuda'=>null,'opciones'=>['A. must','B. can','C. should','D. will'],'correcta'=>'C','respuesta_texto'=>'should','explicacion'=>'SHOULD expresa consejo o recomendación.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con el modal correcto:','pregunta'=>'She ___ speak three languages. She\'s amazing!','traduccion_ayuda'=>null,'opciones'=>['A. must','B. should','C. might','D. can'],'correcta'=>'D','respuesta_texto'=>'can','explicacion'=>'CAN expresa habilidad o capacidad.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con el modal correcto:','pregunta'=>'You ___ wear a seatbelt. It\'s the law.','traduccion_ayuda'=>null,'opciones'=>['A. can','B. should','C. must','D. might'],'correcta'=>'C','respuesta_texto'=>'must','explicacion'=>'MUST expresa obligación o necesidad estricta.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con going to / will:','pregunta'=>'Look at those clouds! It ___ rain.','traduccion_ayuda'=>null,'opciones'=>['A. will','B. is going to','C. would','D. shall'],'correcta'=>'B','respuesta_texto'=>'is going to','explicacion'=>'IS GOING TO se usa para predicciones basadas en evidencia visible.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con since o for:','pregunta'=>'I have lived here ___ 2015.','traduccion_ayuda'=>null,'opciones'=>['A. for','B. since','C. ago','D. during'],'correcta'=>'B','respuesta_texto'=>'since','explicacion'=>'SINCE + punto de inicio en el tiempo (año, fecha, evento).'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con since o for:','pregunta'=>'She has been sick ___ three days.','traduccion_ayuda'=>null,'opciones'=>['A. since','B. ago','C. for','D. during'],'correcta'=>'C','respuesta_texto'=>'for','explicacion'=>'FOR + periodo de duración (three days, two hours, a week).'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa:','pregunta'=>'He ___ been sick for three days.','traduccion_ayuda'=>null,'opciones'=>['A. have','B. is','C. has','D. had'],'correcta'=>'C','respuesta_texto'=>'has','explicacion'=>'Presente perfecto con he/she/it: HAS + participio.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa en negativo pasado:','pregunta'=>'We didn\'t ___ to the party last night.','traduccion_ayuda'=>null,'opciones'=>['A. went','B. go','C. gone','D. going'],'correcta'=>'B','respuesta_texto'=>'go','explicacion'=>'Con DIDN\'T el verbo va en forma base (infinitivo).'],
    ['tipo'=>'multiple_choice','instruccion'=>'Elige el presente perfecto correcto:','pregunta'=>'Past participle of "go":','traduccion_ayuda'=>null,'opciones'=>['A. goed','B. went','C. gone','D. going'],'correcta'=>'C','respuesta_texto'=>'gone','explicacion'=>'GO → went (pasado) → GONE (participio). Ej: I have GONE.'],
    ['tipo'=>'multiple_choice','instruccion'=>'Elige el pasado simple correcto:','pregunta'=>'Past simple of "eat":','traduccion_ayuda'=>null,'opciones'=>['A. eated','B. eaten','C. eat','D. ate'],'correcta'=>'D','respuesta_texto'=>'ate','explicacion'=>'EAT es irregular. Pasado simple: ATE.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa este phrasal verb?','pregunta'=>'"Give up"','traduccion_ayuda'=>null,'opciones'=>['A. dar un regalo','B. subir','C. rendirse','D. empezar'],'correcta'=>'C','respuesta_texto'=>'rendirse','explicacion'=>'GIVE UP = rendirse, abandonar algo.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa este phrasal verb?','pregunta'=>'"Look up"','traduccion_ayuda'=>null,'opciones'=>['A. mirar arriba','B. buscar información','C. cuidar a alguien','D. apagar'],'correcta'=>'B','respuesta_texto'=>'buscar información','explicacion'=>'LOOK UP = buscar información (en un diccionario, en internet).'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Cuál es la oración correcta?','pregunta'=>'Elige la forma correcta del presente perfecto:','traduccion_ayuda'=>null,'opciones'=>['A. She has work here for 5 years.','B. She has worked here for 5 years.','C. She have worked here for 5 years.','D. She worked here since 5 years.'],'correcta'=>'B','respuesta_texto'=>'She has worked here for 5 years.','explicacion'=>'Presente perfecto: HAS + participio (worked) + FOR + duración.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Cuál expresa posibilidad?','pregunta'=>'"It ___ rain tomorrow, but I\'m not sure."','traduccion_ayuda'=>null,'opciones'=>['A. will','B. must','C. might','D. should'],'correcta'=>'C','respuesta_texto'=>'might','explicacion'=>'MIGHT expresa posibilidad incierta (quizás, tal vez).'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"I have went to the gym yesterday."','traduccion_ayuda'=>null,'opciones'=>['A. I have go to the gym yesterday.','B. I went to the gym yesterday.','C. I have gone to the gym yesterday.','D. I had went to the gym yesterday.'],'correcta'=>'B','respuesta_texto'=>'I went to the gym yesterday.','explicacion'=>'Con "yesterday" (tiempo definido) se usa PASADO SIMPLE: I WENT (no presente perfecto).'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"They was at home when I called."','traduccion_ayuda'=>null,'opciones'=>['A. They were at home when I called.','B. They was at home when I call.','C. They are at home when I called.','D. They been at home when I called.'],'correcta'=>'A','respuesta_texto'=>'They were at home when I called.','explicacion'=>'Con THEY el pasado de TO BE es WERE (not was).'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"If I will have time, I will call you."','traduccion_ayuda'=>null,'opciones'=>['A. If I had time, I will call you.','B. If I have time, I will call you.','C. If I would have time, I will call you.','D. If I have time, I would call you.'],'correcta'=>'B','respuesta_texto'=>'If I have time, I will call you.','explicacion'=>'First conditional: If + PRESENT SIMPLE (not will), + will + verbo base.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la forma correcta:','pregunta'=>'I\'m going ___ buy a new phone next week.','traduccion_ayuda'=>null,'opciones'=>['A. for','B. at','C. to','D. on'],'correcta'=>'C','respuesta_texto'=>'to','explicacion'=>'Going TO + verbo base expresa plan o intención futura.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la forma correcta:','pregunta'=>'By the time I arrived, she had ___ left.','traduccion_ayuda'=>null,'opciones'=>['A. yet','B. still','C. ever','D. already'],'correcta'=>'D','respuesta_texto'=>'already','explicacion'=>'ALREADY en pasado perfecto indica que algo ya había ocurrido.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'RENDIRSE','traduccion_ayuda'=>null,'opciones'=>['A. look up','B. give up','C. wake up','D. put up'],'correcta'=>'B','respuesta_texto'=>'give up','explicacion'=>'Rendirse en inglés es GIVE UP.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Cómo se dice en inglés?','pregunta'=>'DESPERTARSE','traduccion_ayuda'=>null,'opciones'=>['A. give up','B. look up','C. wake up','D. show up'],'correcta'=>'C','respuesta_texto'=>'wake up','explicacion'=>'Despertarse en inglés es WAKE UP.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Qué significa?','pregunta'=>'YET (en negativas/preguntas)','traduccion_ayuda'=>null,'opciones'=>['A. ya (afirmativo)','B. todavía / aún','C. nunca','D. siempre'],'correcta'=>'B','respuesta_texto'=>'todavía / aún','explicacion'=>'YET en negativas y preguntas = todavía / aún. Ej: I haven\'t finished YET.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Nunca he probado sushi.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'I have never tried sushi.','explicacion'=>'Presente perfecto con NEVER: I have NEVER tried sushi.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Deberías estudiar más.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'You should study more.','explicacion'=>'SHOULD = debería. You should study more.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'¿Has estado alguna vez en Europa?','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'Have you ever been to Europe?','explicacion'=>'Presente perfecto con EVER en preguntas: Have you EVER been to...?'],
],

// ────────────────────────────────────────────────────────────
// NIVEL B2 — Intermedio-alto
// ────────────────────────────────────────────────────────────
'B2' => [
    ['tipo'=>'fill_blank','instruccion'=>'Voz pasiva — completa:','pregunta'=>'The letter was ___ by Maria.','traduccion_ayuda'=>null,'opciones'=>['A. write','B. wrote','C. written','D. writing'],'correcta'=>'C','respuesta_texto'=>'written','explicacion'=>'Voz pasiva: was/were + PARTICIPIO PASADO. Write → written.'],
    ['tipo'=>'fill_blank','instruccion'=>'Voz pasiva — completa:','pregunta'=>'The bridge ___ built in 1920.','traduccion_ayuda'=>null,'opciones'=>['A. is','B. was','C. were','D. been'],'correcta'=>'B','respuesta_texto'=>'was','explicacion'=>'Voz pasiva en pasado: WAS + participio (built).'],
    ['tipo'=>'fill_blank','instruccion'=>'Second conditional — completa:','pregunta'=>'If I ___ rich, I would travel the world.','traduccion_ayuda'=>null,'opciones'=>['A. am','B. was','C. were','D. will be'],'correcta'=>'C','respuesta_texto'=>'were','explicacion'=>'Second conditional: If + WERE (subjuntivo), + would. Incluso con I se usa WERE.'],
    ['tipo'=>'fill_blank','instruccion'=>'Reported speech — completa:','pregunta'=>'She said that she ___ studying for the exam.','traduccion_ayuda'=>null,'opciones'=>['A. is','B. will be','C. was','D. has been'],'correcta'=>'C','respuesta_texto'=>'was','explicacion'=>'Reported speech: el presente IS cambia a WAS en el pasado (backshift).'],
    ['tipo'=>'fill_blank','instruccion'=>'Modal pasivo — completa:','pregunta'=>'The report must be ___ by Friday.','traduccion_ayuda'=>null,'opciones'=>['A. submit','B. submitting','C. submitted','D. submits'],'correcta'=>'C','respuesta_texto'=>'submitted','explicacion'=>'Modal + be + PARTICIPIO: must be submitted (voz pasiva con modal).'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa correctamente:','pregunta'=>'She ___ have known about the meeting.','traduccion_ayuda'=>'(debería haber sabido)','opciones'=>['A. would','B. could','C. should','D. might'],'correcta'=>'C','respuesta_texto'=>'should','explicacion'=>'SHOULD HAVE + participio = debería haber... (crítica o arrepentimiento sobre el pasado).'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la estructura correcta:','pregunta'=>'By next year, I will ___ graduated.','traduccion_ayuda'=>null,'opciones'=>['A. be','B. have','C. had','D. been'],'correcta'=>'B','respuesta_texto'=>'have','explicacion'=>'Future perfect: will HAVE + participio (will have graduated).'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la estructura correcta:','pregunta'=>'The results were ___ than expected.','traduccion_ayuda'=>null,'opciones'=>['A. more better','B. best','C. better','D. good'],'correcta'=>'C','respuesta_texto'=>'better','explicacion'=>'Comparativo de GOOD es BETTER (irregular). No "more better".'],
    ['tipo'=>'multiple_choice','instruccion'=>'Transforma a voz pasiva:','pregunta'=>'"They built the bridge in 1920."','traduccion_ayuda'=>null,'opciones'=>['A. The bridge is built in 1920.','B. The bridge was built in 1920.','C. The bridge has built in 1920.','D. The bridge built in 1920.'],'correcta'=>'B','respuesta_texto'=>'The bridge was built in 1920.','explicacion'=>'Voz pasiva: objeto + WAS/WERE + participio. El agente (they) se omite o va con "by".'],
    ['tipo'=>'multiple_choice','instruccion'=>'Reported speech — elige la transformación correcta:','pregunta'=>'She said: "I am tired."','traduccion_ayuda'=>null,'opciones'=>['A. She said that she is tired.','B. She said that she was tired.','C. She said that she were tired.','D. She told that she was tired.'],'correcta'=>'B','respuesta_texto'=>'She said that she was tired.','explicacion'=>'Reported speech: SAY THAT + oración. El presente IS retrocede a WAS.'],
    ['tipo'=>'multiple_choice','instruccion'=>'Third conditional — elige la forma correcta:','pregunta'=>'"If she had studied, she ___ the exam."','traduccion_ayuda'=>null,'opciones'=>['A. would pass','B. will have passed','C. would have passed','D. had passed'],'correcta'=>'C','respuesta_texto'=>'would have passed','explicacion'=>'Third conditional: If + past perfect → WOULD HAVE + participio (situación irreal en el pasado).'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa esta expresión idiomática?','pregunta'=>'"It\'s raining cats and dogs."','traduccion_ayuda'=>null,'opciones'=>['A. Hay animales en la calle.','B. Está lloviendo a cántaros.','C. El tiempo es incierto.','D. Hace frío.'],'correcta'=>'B','respuesta_texto'=>'Está lloviendo a cántaros.','explicacion'=>'"Raining cats and dogs" = lloviendo muchísimo, a cántaros.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa esta expresión idiomática?','pregunta'=>'"Break a leg!"','traduccion_ayuda'=>null,'opciones'=>['A. Ten cuidado.','B. Rompe algo.','C. ¡Buena suerte!','D. Sé valiente.'],'correcta'=>'C','respuesta_texto'=>'¡Buena suerte!','explicacion'=>'"Break a leg!" es una expresión para desear buena suerte (especialmente en teatro).'],
    ['tipo'=>'multiple_choice','instruccion'=>'Collocations — elige el verbo correcto:','pregunta'=>'___ a mistake','traduccion_ayuda'=>null,'opciones'=>['A. do','B. make','C. have','D. take'],'correcta'=>'B','respuesta_texto'=>'make','explicacion'=>'MAKE a mistake (cometer un error). Las collocations hay que memorizar.'],
    ['tipo'=>'multiple_choice','instruccion'=>'Collocations — elige el verbo correcto:','pregunta'=>'___ your homework','traduccion_ayuda'=>null,'opciones'=>['A. make','B. take','C. do','D. have'],'correcta'=>'C','respuesta_texto'=>'do','explicacion'=>'DO your homework (hacer la tarea). DO se usa para tareas y actividades.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Qué significa "Nevertheless"?','pregunta'=>'"The task was hard. Nevertheless, she finished it."','traduccion_ayuda'=>null,'opciones'=>['A. Además','B. Por eso','C. Sin embargo','D. Aunque'],'correcta'=>'C','respuesta_texto'=>'Sin embargo','explicacion'=>'NEVERTHELESS = sin embargo, no obstante. Introduce contraste.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Cuál es más formal?','pregunta'=>'Elige la opción más formal y apropiada para un email de negocios:','traduccion_ayuda'=>null,'opciones'=>['A. I wanna ask you something.','B. Can you help?','C. I would like to request your assistance.','D. Help me with this.'],'correcta'=>'C','respuesta_texto'=>'I would like to request your assistance.','explicacion'=>'WOULD LIKE TO + infinitivo es formal y cortés. "Wanna" es muy informal.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Cuál es el uso correcto de "despite"?','pregunta'=>'"Despite" va seguido de:','traduccion_ayuda'=>null,'opciones'=>['A. Despite + clause (sujeto + verbo)','B. Despite + noun/gerund','C. Despite + adjective only','D. Despite + although'],'correcta'=>'B','respuesta_texto'=>'Despite + noun/gerund','explicacion'=>'DESPITE + sustantivo o gerundio: "Despite the rain..." / "Despite feeling tired..."'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error gramatical:','pregunta'=>'"If I would have money, I would travel more."','traduccion_ayuda'=>null,'opciones'=>['A. If I had money, I would travel more.','B. If I have money, I would travel more.','C. If I would have money, I will travel more.','D. If I had money, I will travel more.'],'correcta'=>'A','respuesta_texto'=>'If I had money, I would travel more.','explicacion'=>'Second conditional: If + PAST SIMPLE (had), + would + verbo base. NUNCA "if I would have".'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"The information are incorrect."','traduccion_ayuda'=>null,'opciones'=>['A. The informations are incorrect.','B. The information is incorrect.','C. The information were incorrect.','D. Informations is incorrect.'],'correcta'=>'B','respuesta_texto'=>'The information is incorrect.','explicacion'=>'"Information" es incontable (uncountable) y siempre va con verbo SINGULAR: is.'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"He suggested me to apply for the job."','traduccion_ayuda'=>null,'opciones'=>['A. He suggested me applying for the job.','B. He suggested to me to apply for the job.','C. He suggested that I apply for the job.','D. He suggested me that I apply.'],'correcta'=>'C','respuesta_texto'=>'He suggested that I apply for the job.','explicacion'=>'SUGGEST no va seguido de object + to-infinitive. Correcto: suggest + THAT + sujeto + verbo base.'],
    ['tipo'=>'corrige_error','instruccion'=>'Encuentra y corrige el error:','pregunta'=>'"She is used to work late every night."','traduccion_ayuda'=>null,'opciones'=>['A. She is used to worked late every night.','B. She is used to working late every night.','C. She used to working late every night.','D. She is use to work late every night.'],'correcta'=>'B','respuesta_texto'=>'She is used to working late every night.','explicacion'=>'"Be used to" va seguido de GERUNDIO (-ing): She is used to WORKING late.'],
    ['tipo'=>'fill_blank','instruccion'=>'Completa con la preposición correcta:','pregunta'=>'She is considered ___ one of the best scientists in the world.','traduccion_ayuda'=>null,'opciones'=>['A. as','B. like','C. for','D. by'],'correcta'=>'A','respuesta_texto'=>'as','explicacion'=>'Consider + AS: She is considered AS one of the best. O simplemente: considered one of the best.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Qué significa esta palabra?','pregunta'=>'REDUNDANT','traduccion_ayuda'=>null,'opciones'=>['A. necesario','B. urgente','C. redundante/innecesario','D. relevante'],'correcta'=>'C','respuesta_texto'=>'redundante / innecesario','explicacion'=>'REDUNDANT = redundante, innecesario, prescindible. Ej: That word is redundant.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Qué significa esta palabra?','pregunta'=>'SUBSEQUENTLY','traduccion_ayuda'=>null,'opciones'=>['A. antes','B. mientras tanto','C. sin embargo','D. posteriormente'],'correcta'=>'D','respuesta_texto'=>'posteriormente / a continuación','explicacion'=>'SUBSEQUENTLY = posteriormente, a continuación, como consecuencia.'],
    ['tipo'=>'vocabulario','instruccion'=>'¿Qué significa esta expresión?','pregunta'=>'TO TAKE SOMETHING FOR GRANTED','traduccion_ayuda'=>null,'opciones'=>['A. agradecer algo','B. dar algo por sentado','C. obtener algo gratis','D. rechazar algo'],'correcta'=>'B','respuesta_texto'=>'dar algo por sentado','explicacion'=>'TAKE FOR GRANTED = dar por sentado, no valorar algo. Ej: Don\'t take your health for granted.'],
    ['tipo'=>'multiple_choice','instruccion'=>'¿Cuál es la diferencia correcta?','pregunta'=>'"Affect" vs "Effect" — elige el uso correcto:','traduccion_ayuda'=>null,'opciones'=>['A. The rain effected the crops. The affect was terrible.','B. The rain affected the crops. The effect was terrible.','C. The rain affected the crops. The affect was terrible.','D. The rain effected the crops. The effect was terrible.'],'correcta'=>'B','respuesta_texto'=>'The rain affected the crops. The effect was terrible.','explicacion'=>'AFFECT (verbo) = afectar. EFFECT (sustantivo) = efecto. Regla: A es el verbo, E es el sustantivo.'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Esto habría podido evitarse.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'This could have been avoided.','explicacion'=>'Could have been + participio = could have been avoided (voz pasiva + modal perfecto).'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'Cuanto antes termines, mejor.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'The sooner you finish, the better.','explicacion'=>'Estructura comparativa: The + comparativo..., the + comparativo...'],
    ['tipo'=>'traduccion','instruccion'=>'Escribe en inglés:','pregunta'=>'No tenía otra opción más que esperar.','traduccion_ayuda'=>null,'opciones'=>[],'correcta'=>null,'respuesta_texto'=>'I had no choice but to wait.','explicacion'=>'Have no choice but to + infinitivo = no tener otra opción más que...'],
],

]; // fin $EJERCICIOS

// ============================================================
// ACCIÓN: generar ejercicio (sin IA)
// ============================================================
if ($accion === 'generar') {
    $nivel = get_nivel($est_id, $conexion);
    if (!isset($EJERCICIOS[$nivel])) $nivel = 'A1';

    $pool = $EJERCICIOS[$nivel];
    $total = count($pool);

    // Indices ya usados en esta sesión (enviados por el cliente)
    $usados_raw = trim($_POST['usados'] ?? '');
    $usados = [];
    if ($usados_raw !== '') {
        foreach (explode(',', $usados_raw) as $u) {
            $u = (int)$u;
            if ($u >= 0 && $u < $total) $usados[] = $u;
        }
    }

    // Disponibles = todos menos los usados
    $disponibles = array_values(array_diff(range(0, $total - 1), $usados));

    // Si ya se usaron todos, reiniciar (nueva vuelta)
    if (empty($disponibles)) {
        $disponibles = range(0, $total - 1);
    }

    // Elegir uno al azar
    $idx = $disponibles[array_rand($disponibles)];
    $ej  = $pool[$idx];
    $ej['nivel'] = $nivel;
    $ej['idx']   = $idx;

    echo json_encode(['ok' => true, 'ejercicio' => $ej]);
    exit;
}

// ============================================================
// ACCIÓN: evaluar traducción (sin IA)
// ============================================================
if ($accion === 'evaluar_traduccion') {
    $correcta  = trim($_POST['correcta']  ?? '');
    $respuesta = trim($_POST['respuesta'] ?? '');
    if (!$respuesta || !$correcta) {
        echo json_encode(['error' => 'Datos incompletos']); exit;
    }
    echo json_encode(['ok' => true, 'resultado' => evaluar_simple($respuesta, $correcta)]);
    exit;
}

// ============================================================
// ACCIÓN: guardar resultado de ejercicio
// ============================================================
if ($accion === 'guardar') {
    $tipo        = $_POST['tipo'] ?? '';
    $nivel       = $_POST['nivel'] ?? 'A1';
    $pregunta    = $_POST['pregunta'] ?? '';
    $correcta    = $_POST['respuesta_correcta'] ?? '';
    $dada        = $_POST['respuesta_dada'] ?? '';
    $es_ok       = (int)($_POST['es_correcto'] ?? 0);
    $explicacion = $_POST['explicacion'] ?? '';
    $es_quiz     = (int)($_POST['es_quiz'] ?? 0);
    $xp          = $es_ok ? 10 : 2;

    $st = mysqli_prepare($conexion,
        "INSERT INTO idiomas_sesiones (estudiante_id, tipo_ejercicio, nivel, pregunta, respuesta_correcta, respuesta_dada, es_correcto, xp_ganado, explicacion, es_quiz_nivel)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $tipo_enum  = in_array($tipo, ['fill_blank','multiple_choice','traduccion','corrige_error','vocabulario','dialogo']) ? $tipo : 'multiple_choice';
    $nivel_enum = in_array($nivel, ['A1','A2','B1','B2']) ? $nivel : 'A1';
    mysqli_stmt_bind_param($st, 'isssssiiis',
        $est_id, $tipo_enum, $nivel_enum, $pregunta, $correcta, $dada, $es_ok, $xp, $explicacion, $es_quiz);
    mysqli_stmt_execute($st);

    if (!$es_quiz) {
        $hoy = date('Y-m-d');
        $st2 = mysqli_prepare($conexion, "SELECT id, xp_total, racha_actual, racha_maxima, ultima_sesion FROM idiomas_nivel WHERE estudiante_id = ?");
        mysqli_stmt_bind_param($st2, 'i', $est_id);
        mysqli_stmt_execute($st2);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));

        if ($row) {
            $nueva_racha = $row['racha_actual'];
            if ($row['ultima_sesion'] === $hoy) {
                // Ya practicó hoy, no suma racha extra
            } elseif ($row['ultima_sesion'] === date('Y-m-d', strtotime('-1 day'))) {
                $nueva_racha++;
            } else {
                $nueva_racha = 1;
            }
            $nuevo_xp  = $row['xp_total'] + $xp;
            $nueva_max = max($row['racha_maxima'], $nueva_racha);

            $nuevo_nivel = 'A1';
            if ($nuevo_xp >= 1200) $nuevo_nivel = 'B2';
            elseif ($nuevo_xp >= 700) $nuevo_nivel = 'B1';
            elseif ($nuevo_xp >= 300) $nuevo_nivel = 'A2';

            $st3 = mysqli_prepare($conexion,
                "UPDATE idiomas_nivel SET xp_total=?, racha_actual=?, racha_maxima=?, nivel_actual=?, ultima_sesion=? WHERE estudiante_id=?");
            mysqli_stmt_bind_param($st3, 'iiissi', $nuevo_xp, $nueva_racha, $nueva_max, $nuevo_nivel, $hoy, $est_id);
            mysqli_stmt_execute($st3);

            verificar_logros($est_id, $nuevo_xp, $nueva_racha, $nuevo_nivel, $conexion);
            echo json_encode(['ok' => true, 'xp' => $nuevo_xp, 'racha' => $nueva_racha, 'nivel' => $nuevo_nivel]);
        } else {
            $st_ins = mysqli_prepare($conexion,
                "INSERT INTO idiomas_nivel (estudiante_id, xp_total, racha_actual, racha_maxima, nivel_actual, ultima_sesion) VALUES (?,?,1,1,'A1',?)");
            mysqli_stmt_bind_param($st_ins, 'iis', $est_id, $xp, $hoy);
            mysqli_stmt_execute($st_ins);
            echo json_encode(['ok' => true, 'xp' => $xp, 'racha' => 1, 'nivel' => 'A1']);
        }
    } else {
        echo json_encode(['ok' => true]);
    }
    exit;
}

// ============================================================
// ACCIÓN: guardar apodo
// ============================================================
if ($accion === 'set_apodo') {
    $apodo = trim($_POST['apodo'] ?? '');
    $apodo = preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ _\-]/', '', $apodo);
    $apodo = substr($apodo, 0, 30);
    if (strlen($apodo) < 2) { echo json_encode(['error' => 'Apodo muy corto']); exit; }

    $st = mysqli_prepare($conexion, "SELECT id FROM idiomas_nivel WHERE estudiante_id = ?");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

    if ($existe) {
        $st2 = mysqli_prepare($conexion, "UPDATE idiomas_nivel SET apodo=? WHERE estudiante_id=?");
        mysqli_stmt_bind_param($st2, 'si', $apodo, $est_id);
    } else {
        $st2 = mysqli_prepare($conexion, "INSERT INTO idiomas_nivel (estudiante_id, apodo) VALUES (?,?)");
        mysqli_stmt_bind_param($st2, 'is', $est_id, $apodo);
    }
    mysqli_stmt_execute($st2);
    echo json_encode(['ok' => true, 'apodo' => $apodo]);
    exit;
}

// ============================================================
// ACCIÓN: guardar nivel del quiz
// ============================================================
if ($accion === 'set_nivel_quiz') {
    $nivel = $_POST['nivel'] ?? 'A1';
    if (!in_array($nivel, ['A1','A2','B1','B2'])) $nivel = 'A1';

    $st = mysqli_prepare($conexion, "SELECT id FROM idiomas_nivel WHERE estudiante_id = ?");
    mysqli_stmt_bind_param($st, 'i', $est_id);
    mysqli_stmt_execute($st);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

    if ($existe) {
        $st2 = mysqli_prepare($conexion, "UPDATE idiomas_nivel SET nivel_actual=?, quiz_completado=1 WHERE estudiante_id=?");
        mysqli_stmt_bind_param($st2, 'si', $nivel, $est_id);
    } else {
        $st2 = mysqli_prepare($conexion, "INSERT INTO idiomas_nivel (estudiante_id, nivel_actual, quiz_completado) VALUES (?,?,1)");
        mysqli_stmt_bind_param($st2, 'is', $est_id, $nivel);
    }
    mysqli_stmt_execute($st2);
    echo json_encode(['ok' => true, 'nivel' => $nivel]);
    exit;
}

// ============================================================
// ACCIÓN: preferencia de ejercicios por sesión
// ============================================================
if ($accion === 'set_ejercicios_sesion') {
    $cantidad = (int)($_POST['cantidad'] ?? 15);
    if (!in_array($cantidad, [10, 15, 20])) $cantidad = 15;
    $st = mysqli_prepare($conexion, "UPDATE idiomas_nivel SET ejercicios_sesion=? WHERE estudiante_id=?");
    mysqli_stmt_bind_param($st, 'ii', $cantidad, $est_id);
    mysqli_stmt_execute($st);
    echo json_encode(['ok' => true, 'ejercicios_sesion' => $cantidad]);
    exit;
}

// ── Verificar y otorgar logros ──────────────────────────────
function verificar_logros(int $est_id, int $xp, int $racha, string $nivel, $db): void {
    $logros = [
        ['key' => 'primer_ejercicio', 'nombre' => 'Primer paso',       'icon' => '🌟', 'cond' => $xp >= 10],
        ['key' => 'racha_7',          'nombre' => 'Racha de 7 días',    'icon' => '🔥', 'cond' => $racha >= 7],
        ['key' => 'racha_30',         'nombre' => 'Un mes seguido',     'icon' => '🏅', 'cond' => $racha >= 30],
        ['key' => 'nivel_a2',         'nombre' => 'Nivel A2 alcanzado', 'icon' => '📗', 'cond' => in_array($nivel, ['A2','B1','B2'])],
        ['key' => 'nivel_b1',         'nombre' => 'Nivel B1 alcanzado', 'icon' => '📘', 'cond' => in_array($nivel, ['B1','B2'])],
        ['key' => 'nivel_b2',         'nombre' => 'Nivel B2 alcanzado', 'icon' => '🏆', 'cond' => $nivel === 'B2'],
        ['key' => 'xp_500',           'nombre' => '500 XP acumulados',  'icon' => '⭐', 'cond' => $xp >= 500],
    ];
    foreach ($logros as $l) {
        if (!$l['cond']) continue;
        $st = mysqli_prepare($db,
            "INSERT IGNORE INTO idiomas_logros (estudiante_id, logro_key, logro_nombre, logro_icon) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($st, 'isss', $est_id, $l['key'], $l['nombre'], $l['icon']);
        mysqli_stmt_execute($st);
    }
}

echo json_encode(['error' => 'Acción no reconocida']);
