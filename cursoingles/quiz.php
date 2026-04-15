<?php
// INTEP Inglés — Quiz por módulo (A1 / A2 / B1)
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['usuario_id']) || empty($_SESSION['estudiante_id'])) {
    header('Location: /intep/login.php'); exit;
}
$est_id  = (int)$_SESSION['estudiante_id'];
$nivel   = in_array($_GET['nivel'] ?? '', ['A1','A2','B1']) ? $_GET['nivel'] : 'A1';
$modulo  = isset($_GET['modulo']) ? (int)$_GET['modulo'] : 1;
$es_general = ($modulo === 0);

// Nombres de módulos
$nombres = [
    'A1' => [0=>'Quiz General A1',1=>'Nice to meet you!',2=>'My World',3=>'Daily Routines',4=>'I can do that!',5=>'City Life',6=>'Shopping & Food',7=>'What are you doing?',8=>'The Past Weekend'],
    'A2' => [0=>'Quiz General A2',1=>'Past Adventures',2=>'Future Plans',3=>'Comparing Things',4=>'Health & Body',5=>'Travel & Directions',6=>'Work & Jobs',7=>'Environment',8=>'Technology Today'],
    'B1' => [0=>'Quiz General B1',1=>'Current Affairs',2=>'Lifestyle Changes',3=>'Problem Solving',4=>'Culture & Arts',5=>'Science & Discovery',6=>'Media & News',7=>'Global Issues',8=>'Career & Ambition'],
];
$nombre_modulo = $nombres[$nivel][$modulo] ?? "Módulo $modulo";

// Datos del estudiante
$st = mysqli_prepare($conexion,"SELECT e.nombre, e.foto FROM estudiantes e WHERE e.id=? LIMIT 1");
mysqli_stmt_bind_param($st,'i',$est_id); mysqli_stmt_execute($st);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?? [];
$nombre_est = $est['nombre'] ?? 'Estudiante';

// Intentos previos
$st2 = mysqli_prepare($conexion,"SELECT COUNT(*) FROM ingles_quiz_resultados WHERE estudiante_id=? AND nivel=? AND modulo_num=?");
mysqli_stmt_bind_param($st2,'isi',$est_id,$nivel,$modulo);
mysqli_stmt_execute($st2);
mysqli_stmt_bind_result($st2,$intentos_prev); mysqli_stmt_fetch($st2); mysqli_stmt_close($st2);

// Mejor score
$st3 = mysqli_prepare($conexion,"SELECT MAX(score) as best, MAX(aprobado) as aprobado FROM ingles_quiz_resultados WHERE estudiante_id=? AND nivel=? AND modulo_num=?");
mysqli_stmt_bind_param($st3,'isi',$est_id,$nivel,$modulo);
mysqli_stmt_execute($st3);
$best = mysqli_fetch_assoc(mysqli_stmt_get_result($st3)) ?? ['best'=>0,'aprobado'=>0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiz: <?= htmlspecialchars($nombre_modulo) ?> | INTEP</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/intep/cursoingles/lesson.css">
<style>
*{box-sizing:border-box;}
body{margin:0;min-height:100vh;}
.quiz-wrap{max-width:780px;margin:0 auto;padding:30px 20px 80px;}
.quiz-back{display:inline-flex;align-items:center;gap:8px;color:var(--text-muted);text-decoration:none;font-weight:600;margin-bottom:25px;transition:color 0.3s;}
.quiz-back:hover{color:var(--primary);}
.quiz-header{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:25px 30px;margin-bottom:30px;}
.quiz-badge{display:inline-block;background:rgba(99,102,241,0.2);color:#a5b4fc;font-size:0.8rem;font-weight:700;padding:5px 14px;border-radius:20px;margin-bottom:10px;letter-spacing:1px;}
.quiz-title{font-family:'Outfit',sans-serif;font-size:1.8rem;font-weight:800;color:white;margin:0 0 6px;}
.quiz-sub{color:var(--text-muted);font-size:0.95rem;}
.quiz-stats{display:flex;gap:20px;margin-top:15px;flex-wrap:wrap;}
.qstat{background:rgba(255,255,255,0.06);border-radius:10px;padding:8px 16px;font-size:0.85rem;color:#cbd5e1;}
.qstat strong{color:white;}

/* Progress */
.q-progress-wrap{background:rgba(255,255,255,0.08);border-radius:20px;height:8px;margin-bottom:30px;overflow:hidden;}
.q-progress-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--secondary));border-radius:20px;transition:width 0.4s ease;}

/* Question card */
.question-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;margin-bottom:20px;}
.q-num{font-size:0.8rem;color:var(--text-muted);font-weight:600;letter-spacing:2px;text-transform:uppercase;margin-bottom:12px;}
.q-text{font-size:1.25rem;color:white;font-weight:600;line-height:1.5;margin-bottom:25px;}
.q-text .blank{display:inline-block;min-width:80px;border-bottom:2px solid var(--primary);padding:0 4px;color:var(--primary-light);}
.options{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:500px){.options{grid-template-columns:1fr;}}
.opt-btn{background:rgba(255,255,255,0.06);border:2px solid rgba(255,255,255,0.15);border-radius:14px;padding:15px 20px;color:white;font-size:1rem;font-weight:500;cursor:pointer;text-align:left;transition:all 0.2s;font-family:'Inter',sans-serif;}
.opt-btn:hover:not(:disabled){background:rgba(99,102,241,0.15);border-color:var(--primary);}
.opt-btn.selected{background:rgba(99,102,241,0.2);border-color:var(--primary);color:#a5b4fc;}
.opt-btn.correct{background:rgba(16,185,129,0.2)!important;border-color:#10b981!important;color:#6ee7b7!important;}
.opt-btn.wrong{background:rgba(239,68,68,0.2)!important;border-color:#ef4444!important;color:#fca5a5!important;}
.opt-btn:disabled{cursor:default;}
.q-feedback{margin-top:16px;padding:12px 18px;border-radius:12px;font-size:0.95rem;display:none;}
.q-feedback.ok{background:rgba(16,185,129,0.15);color:#6ee7b7;border:1px solid rgba(16,185,129,0.3);}
.q-feedback.fail{background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);}

/* Nav buttons */
.quiz-nav{display:flex;justify-content:flex-end;margin-top:15px;}
.btn-next{background:var(--primary);color:white;border:none;border-radius:12px;padding:14px 32px;font-family:'Outfit',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:none;}
.btn-next:hover{background:#4f46e5;transform:translateY(-1px);}
.btn-finish{background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:12px;padding:14px 32px;font-family:'Outfit',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:none;}
.btn-finish:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(16,185,129,0.4);}

/* Result overlay */
.result-overlay{display:none;position:fixed;inset:0;background:rgba(10,15,30,0.92);backdrop-filter:blur(10px);z-index:100;justify-content:center;align-items:center;padding:20px;}
.result-box{background:#0f172a;border-radius:28px;padding:50px 40px;max-width:480px;width:100%;text-align:center;border:1px solid rgba(255,255,255,0.1);animation:popIn 0.4s cubic-bezier(0.175,0.885,0.32,1.275);}
@keyframes popIn{from{transform:scale(0.85);opacity:0;}to{transform:scale(1);opacity:1;}}
.result-emoji{font-size:5rem;margin-bottom:15px;display:block;}
.result-title{font-family:'Outfit',sans-serif;font-size:2rem;font-weight:800;color:white;margin:0 0 8px;}
.result-score{font-size:4rem;font-weight:900;font-family:'Outfit',sans-serif;margin:15px 0;}
.score-pass{background:linear-gradient(to right,#10b981,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.score-fail{background:linear-gradient(to right,#ef4444,#f87171);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.result-sub{color:var(--text-muted);font-size:1rem;margin-bottom:25px;}
.result-btns{display:flex;flex-direction:column;gap:12px;}
.btn-cert{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none;border-radius:14px;padding:15px;font-family:'Outfit',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;display:block;transition:all 0.2s;}
.btn-cert:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(99,102,241,0.4);}
.btn-retry{background:rgba(255,255,255,0.08);color:#cbd5e1;border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:15px;font-family:'Outfit',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all 0.2s;}
.btn-retry:hover{background:rgba(255,255,255,0.12);}
.btn-back-dash{background:transparent;color:#64748b;border:none;font-family:'Outfit',sans-serif;font-size:0.9rem;cursor:pointer;margin-top:5px;transition:color 0.2s;}
.btn-back-dash:hover{color:white;}
.xp-badge-res{display:inline-block;background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#1a1a1a;font-family:'Outfit',sans-serif;font-size:1.4rem;font-weight:800;padding:10px 28px;border-radius:15px;margin:10px 0 20px;}
</style>
</head>
<body>
<script>
(function(){
  fetch('/intep/cursoingles/api/sesion.php',{credentials:'include'})
    .then(function(r){return r.json();})
    .then(function(d){if(!d.ok)window.location.replace('/intep/login.php');window.__INTEP=d;})
    .catch(function(){window.location.replace('/intep/login.php');});
})();
</script>

<div class="quiz-wrap">
    <a href="javascript:history.back()" class="quiz-back">← Volver al módulo</a>

    <div class="quiz-header">
        <div class="quiz-badge">📝 EXAMEN · <?= htmlspecialchars($nivel) ?></div>
        <h1 class="quiz-title"><?= htmlspecialchars($nombre_modulo) ?></h1>
        <p class="quiz-sub"><?= $es_general ? 'Quiz general: cubre todos los módulos del nivel '.$nivel : 'Demuestra lo que aprendiste en este módulo' ?></p>
        <div class="quiz-stats">
            <div class="qstat">Preguntas: <strong id="qTotal">10</strong></div>
            <div class="qstat">Para aprobar: <strong>70%</strong> (7/10)</div>
            <?php if ($intentos_prev > 0): ?>
            <div class="qstat">Intentos: <strong><?= (int)$intentos_prev ?></strong></div>
            <div class="qstat">Mejor score: <strong><?= (int)$best['best'] ?>%</strong></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="q-progress-wrap"><div class="q-progress-fill" id="qProgressFill" style="width:0%"></div></div>

    <div id="quizContainer"></div>

    <div class="quiz-nav">
        <button class="btn-next" id="btnNext" onclick="nextQuestion()">Siguiente →</button>
        <button class="btn-finish" id="btnFinish" onclick="finishQuiz()">Ver Resultados 🎯</button>
    </div>
</div>

<!-- Overlay de resultado -->
<div class="result-overlay" id="resultOverlay">
    <div class="result-box" id="resultBox">
        <span class="result-emoji" id="resEmoji">🎉</span>
        <h2 class="result-title" id="resTitle">¡Lo lograste!</h2>
        <div class="result-score" id="resScore"></div>
        <div id="xpBadge" style="display:none;" class="xp-badge-res"></div>
        <p class="result-sub" id="resSub"></p>
        <div class="result-btns" id="resBtns"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script src="/intep/cursoingles/ufo.js"></script>
<script src="/intep/cursoingles/universe_bg.js"></script>
<script>
// ════════════════════════════════════════════════════
// BANCO DE PREGUNTAS — formato: {q, opts:[...], ans: índice 0-based, exp}
// ════════════════════════════════════════════════════
const BANCO = {
  A1: {
    1: [ // Nice to meet you! - To Be, Greetings
      {q:"Complete: I ___ from Colombia.",opts:["am","is","are","be"],ans:0,exp:"Con I siempre usamos AM"},
      {q:"Complete: She ___ a teacher.",opts:["am","is","are","be"],ans:1,exp:"Con He/She/It usamos IS"},
      {q:"Complete: We ___ happy.",opts:["am","is","are","be"],ans:2,exp:"Con We/You/They usamos ARE"},
      {q:"¿Cómo se dice 'Encantado de conocerte'?",opts:["Hello there","Good morning","Nice to meet you","How are you?"],ans:2,exp:"Nice to meet you = Encantado de conocerte"},
      {q:"¿Qué significa 'Where are you from?'",opts:["¿Cómo estás?","¿De dónde eres?","¿Quién eres tú?","¿Adónde vas?"],ans:1,exp:"Where are you from = ¿De dónde eres?"},
      {q:"Complete: They ___ students.",opts:["am","is","are","was"],ans:2,exp:"Con They usamos ARE"},
      {q:"Complete: He ___ my friend.",opts:["am","is","are","be"],ans:1,exp:"Con He usamos IS"},
      {q:"'What do you do?' pregunta sobre tu...",opts:["Nombre","Ciudad","Profesión","Edad"],ans:2,exp:"What do you do = ¿A qué te dedicas?"},
      {q:"Complete: You ___ from Mexico.",opts:["am","is","are","be"],ans:2,exp:"Con You siempre usamos ARE"},
      {q:"¿Cuál de estas es correcta?",opts:["I is a student","He am tall","She is my sister","We is happy"],ans:2,exp:"She is my sister — IS con She/He/It"},
      {q:"Complete: It ___ a dog.",opts:["am","is","are","have"],ans:1,exp:"Con It usamos IS"},
      {q:"¿Qué significa 'Good morning'?",opts:["Buenas noches","Buenas tardes","Buenos días","Adiós"],ans:2,exp:"Good morning = Buenos días"},
      {q:"¿Cuál usa AM?",opts:["You am here","She am happy","I am tired","They am students"],ans:2,exp:"AM solo se usa con I"},
      {q:"¿Cómo preguntas el nombre de alguien?",opts:["How are you?","What is your name?","Where are you from?","What do you do?"],ans:1,exp:"What is your name? = ¿Cuál es tu nombre?"},
      {q:"Complete: My friend ___ very tall.",opts:["am","are","is","be"],ans:2,exp:"My friend = He/She, usamos IS"},
    ],
    2: [ // My World - Have, Family
      {q:"Complete: I ___ a sister.",opts:["has","have","is","are"],ans:1,exp:"Con I usamos HAVE"},
      {q:"Complete: She ___ a car.",opts:["have","has","is","are"],ans:1,exp:"Con She usamos HAS"},
      {q:"Complete: They ___ two cats.",opts:["has","have","is","are"],ans:1,exp:"Con They usamos HAVE"},
      {q:"¿Cómo se dice 'Él tiene un hermano'?",opts:["He have a brother","He has a brother","He is a brother","He are a brother"],ans:1,exp:"Con He usamos HAS: He has a brother"},
      {q:"Complete: He ___ a dog.",opts:["have","has","is","are"],ans:1,exp:"Con He usamos HAS"},
      {q:"¿Cuál es correcta?",opts:["I has a pen","She have a book","We has a house","They have a car"],ans:3,exp:"They have — con They usamos HAVE"},
      {q:"'My sister' significa...",opts:["Mi hermano","Mi prima","Mi hermana","Mi madre"],ans:2,exp:"Sister = hermana (femenino)"},
      {q:"Complete: We ___ three books.",opts:["has","have","is","are"],ans:1,exp:"Con We usamos HAVE"},
      {q:"Complete: It ___ four legs.",opts:["have","has","is","are"],ans:1,exp:"Con It usamos HAS"},
      {q:"¿Cómo se dice 'Tenemos una casa'?",opts:["We has a house","We have a house","We is a house","We are a house"],ans:1,exp:"We have a house — HAVE con We"},
      {q:"'My parents' significa...",opts:["Mis abuelos","Mis tíos","Mis padres","Mis amigos"],ans:2,exp:"Parents = padres (mamá y papá)"},
      {q:"Complete: You ___ a pen.",opts:["has","have","is","be"],ans:1,exp:"Con You usamos HAVE"},
      {q:"¿Cuál es el plural de 'brother'?",opts:["brotheres","brothers","broths","brothrs"],ans:1,exp:"Brother → Brothers (añadir -s)"},
      {q:"'Her dog' significa...",opts:["Su perro (de él)","Su perro (de ella)","Nuestro perro","Tu perro"],ans:1,exp:"Her = de ella / su (femenino)"},
      {q:"Complete: She ___ long hair.",opts:["have","has","is","are"],ans:1,exp:"Con She usamos HAS"},
    ],
    3: [ // Daily Routines - Simple Present
      {q:"She ___ to school every day.",opts:["go","goes","is going","went"],ans:1,exp:"Con She/He/It el verbo lleva -s/-es: goes"},
      {q:"I ___ coffee in the morning.",opts:["drinks","is drinking","drink","drank"],ans:2,exp:"Con I no se agrega -s al verbo"},
      {q:"¿Cuándo usamos -s/-es en Simple Present?",opts:["Con I y You","Con We y They","Con He, She e It","Con todos siempre"],ans:2,exp:"Solo con He/She/It → works, goes, watches"},
      {q:"He ___ soccer every afternoon.",opts:["play","plays","playing","played"],ans:1,exp:"Con He añadimos -s: plays"},
      {q:"'Every day' significa...",opts:["A veces","Todos los días","Los fines de semana","Nunca"],ans:1,exp:"Every day = todos los días"},
      {q:"¿Cuál es CORRECTA?",opts:["She watch TV","He eat lunch","She brushes her teeth","They wakes up"],ans:2,exp:"She brushes = correcto (-es con verbos en -sh)"},
      {q:"I ___ to music every night.",opts:["listens","listened","listen","listening"],ans:2,exp:"Con I no se agrega -s: listen"},
      {q:"Complete: It ___ at 6 PM.",opts:["start","starts","starting","started"],ans:1,exp:"Con It añadimos -s: starts"},
      {q:"'She watches TV' — ¿por qué -es?",opts:["Es pasado","El verbo termina en -ch","Es pregunta","Es negación"],ans:1,exp:"Verbos en -ch/-sh/-x/-o/-z toman -es"},
      {q:"We ___ on Sundays.",opts:["rests","rest","is resting","rested"],ans:1,exp:"Con We no se agrega -s: rest"},
      {q:"¿Cuál es la forma correcta?",opts:["He drink water","She go to work","They wakes up","He reads a book"],ans:3,exp:"He reads — correcto, con He/-s"},
      {q:"I ___ up at 7 AM every day.",opts:["wakes","wake","waking","woke"],ans:1,exp:"Con I no -s: wake up"},
      {q:"¿Qué significa 'She gets up early'?",opts:["Ella duerme tarde","Ella se levanta temprano","Ella llega a tiempo","Ella come temprano"],ans:1,exp:"Get up = levantarse, early = temprano"},
      {q:"He ___ home at 6 PM.",opts:["arrive","arrives","arriving","arrived"],ans:1,exp:"Con He añadimos -s: arrives"},
      {q:"¿Cuál NO usa -s?",opts:["He works","She eats","I sleep","It runs"],ans:2,exp:"Con I nunca se agrega -s"},
    ],
    4: [ // I can do that! - Can
      {q:"I ___ swim very fast.",opts:["can","cans","is able","could"],ans:0,exp:"Can es invariable: I can, She can"},
      {q:"She ___ speak French.",opts:["cans","can","is can","could"],ans:1,exp:"Can no cambia: She can speak"},
      {q:"¿Qué expresa 'Can'?",opts:["Tiempo futuro","Habilidad o capacidad","Obligación","Pasado"],ans:1,exp:"Can expresa habilidad: I can cook = Sé cocinar"},
      {q:"¿Cuál es la negación de 'can'?",opts:["don't can","doesn't can","cannot / can't","not can"],ans:2,exp:"Cannot o can't es la forma negativa"},
      {q:"¿Cuál es correcta?",opts:["She cans sing","He can sings","They can play","We cans dance"],ans:2,exp:"They can play — can no cambia forma"},
      {q:"'Can you help me?' es...",opts:["Una afirmación","Una pregunta","Una negación","Un saludo"],ans:1,exp:"Can + sujeto = pregunta: Can you...?"},
      {q:"'I can't swim' significa...",opts:["Sé nadar","No sé nadar","Estoy nadando","Nadé ayer"],ans:1,exp:"Can't = cannot = no puedo/no sé"},
      {q:"¿Respuesta correcta a 'Can you cook?'",opts:["Yes, I do","Yes, I am","Yes, I can","Yes, I have"],ans:2,exp:"Se responde: Yes, I can / No, I can't"},
      {q:"He ___ drive a car.",opts:["cans","is can","can","could be"],ans:2,exp:"Can es invariable: He can drive"},
      {q:"'She can fly a plane' — ¿qué habilidad describe?",opts:["Nadar","Cocinar","Pilotear un avión","Cantar"],ans:2,exp:"Fly a plane = pilotear un avión"},
      {q:"Complete: We ___ play chess.",opts:["cans","can","is","are"],ans:1,exp:"Can es invariable: We can play"},
      {q:"¿Cuál es una pregunta con can?",opts:["She can dance","Can she dance?","She cans dance?","Does she can dance?"],ans:1,exp:"Pregunta: Can + sujeto + verbo?"},
      {q:"'I can run fast' significa...",opts:["Corro lento","Puedo correr rápido","Corrí rápido","Estoy corriendo"],ans:1,exp:"Can + verbo = habilidad presente"},
      {q:"¿Cuál es INCORRECTA?",opts:["I can swim","She can't fly","He cans cook","They can dance"],ans:2,exp:"'He cans cook' es INCORRECTO — can no toma -s"},
      {q:"'Can' + sujeto + verbo = ...",opts:["Afirmación","Pregunta","Negación","Pasado"],ans:1,exp:"Can + subject + verb = question: Can you run?"},
    ],
    5: [ // City Life - There is/There are
      {q:"___ a bank near here.",opts:["There are","There is","There was","Is there"],ans:1,exp:"Un banco = singular → There IS"},
      {q:"___ many restaurants downtown.",opts:["There is","There are","There was","Is there"],ans:1,exp:"Many restaurants = plural → There ARE"},
      {q:"¿Cuándo usamos 'There is'?",opts:["Con plural","Con singular","Con preguntas","Con todos"],ans:1,exp:"There is + sustantivo singular"},
      {q:"¿Cuándo usamos 'There are'?",opts:["Con singular","Con preguntas","Con plural","Con I"],ans:2,exp:"There are + sustantivo plural"},
      {q:"Complete: ___ three schools in my city.",opts:["There is","There are","Is there","Are there"],ans:1,exp:"Three schools = plural → There ARE"},
      {q:"¿Cuál es correcta?",opts:["There is two parks","There are a hotel","There are two parks","There is many buses"],ans:2,exp:"Two parks = plural → There ARE two parks"},
      {q:"¿Cómo preguntas si hay una farmacia?",opts:["There is a pharmacy?","Is there a pharmacy?","Are there pharmacy?","There pharmacy?"],ans:1,exp:"Pregunta: Is there a...? (singular)"},
      {q:"Complete: ___ a hospital on Main Street.",opts:["There are","There is","Are there","Is there"],ans:1,exp:"Un hospital = singular → There IS"},
      {q:"'Are there any buses?' pregunta si...",opts:["Hay un bus","Hay buses","Fue un bus","Habrá buses"],ans:1,exp:"Are there any buses? = ¿Hay autobuses?"},
      {q:"Complete: ___ no hotels here.",opts:["There is","There are","Is there","Are there"],ans:1,exp:"Hotels = plural → There ARE no hotels"},
      {q:"Complete: ___ a new mall.",opts:["There are","There is","Is there","Are there"],ans:1,exp:"A new mall = singular → There IS"},
      {q:"¿Qué significa 'There is a park'?",opts:["No hay parque","Hay un parque","Hay parques","¿Hay un parque?"],ans:1,exp:"There is = hay (singular afirmativo)"},
      {q:"Complete: ___ many people in the plaza.",opts:["There is","There are","Is there","Was there"],ans:1,exp:"Many people = plural → There ARE"},
      {q:"'Is there a taxi?' se responde con...",opts:["Yes, there are","Yes, there is","Yes, it is","Yes, there"],ans:1,exp:"Is there...? → Yes, there is / No, there isn't"},
      {q:"¿Cuál es INCORRECTA?",opts:["There is a cat","There are three dogs","There is two birds","There are many trees"],ans:2,exp:"Two birds = plural, debe ser: There ARE two birds"},
    ],
    6: [ // Shopping & Food
      {q:"'How much does it cost?' significa...",opts:["¿De qué tamaño es?","¿Cuánto cuesta?","¿Dónde lo compro?","¿Cuál prefieres?"],ans:1,exp:"How much does it cost = ¿Cuánto cuesta?"},
      {q:"Complete: I'd ___ a burger, please.",opts:["want","like","have","take"],ans:1,exp:"I'd like = Me gustaría (para pedir educadamente)"},
      {q:"'Can I try it on?' se usa para...",opts:["Pagar","Preguntar el precio","Probarse la ropa","Hacer un reclamo"],ans:2,exp:"Try it on = probarse (ropa)"},
      {q:"'How much' vs 'How many': ¿cuál va con incontables?",opts:["How many","How much","How few","How any"],ans:1,exp:"How much + incontable: How much water?"},
      {q:"'The shirt is on sale' significa...",opts:["La camisa es cara","La camisa está agotada","La camisa está en oferta","La camisa es nueva"],ans:2,exp:"On sale = en oferta / descuento"},
      {q:"'I'll take it!' al comprar significa...",opts:["No lo quiero","¿Puedo probármelo?","Me lo llevo","¿Cuánto cuesta?"],ans:2,exp:"I'll take it = Me lo llevo"},
      {q:"'Receipt' en una tienda es...",opts:["Descuento","Recibo/tiquete","Talla","Precio"],ans:1,exp:"Receipt = recibo o tiquete de compra"},
      {q:"¿Cuál es correcta al pedir en un restaurante?",opts:["I want pizza","Give me pizza","I'd like the pasta, please","Pizza me give"],ans:2,exp:"I'd like... please = forma educada de pedir"},
      {q:"Complete: How ___ does this cost?",opts:["many","few","much","some"],ans:2,exp:"How much = ¿cuánto? (precio o cantidad incontable)"},
      {q:"'Change' al pagar significa...",opts:["El total","El descuento","El vuelto/cambio","La propina"],ans:2,exp:"Change = el cambio o vuelto que te devuelven"},
      {q:"'A dozen eggs' son...",opts:["6 huevos","10 huevos","12 huevos","20 huevos"],ans:2,exp:"A dozen = 12 unidades"},
      {q:"¿Qué pregunta típicamente el mesero?",opts:["Where are you from?","What would you like?","Can you cook?","How are you today?"],ans:1,exp:"What would you like? = ¿Qué desea ordenar?"},
      {q:"'Some' se usa con...",opts:["Solo singular","Solo negaciones","Incontables y plurales","Solo preguntas"],ans:2,exp:"Some + plural (some apples) o incontable (some water)"},
      {q:"'I'm just browsing' en una tienda significa...",opts:["Voy a comprar todo","Solo estoy mirando","Necesito ayuda","Está cerrado"],ans:1,exp:"Just browsing = solo estoy mirando, gracias"},
      {q:"'Out of stock' significa...",opts:["En descuento","Recién llegado","Agotado","Disponible"],ans:2,exp:"Out of stock = agotado / no hay existencias"},
    ],
    7: [ // What are you doing? - Present Continuous
      {q:"I ___ TV right now.",opts:["watch","am watching","watches","watched"],ans:1,exp:"Present continuous: am/is/are + verb-ing"},
      {q:"She ___ a book.",opts:["read","reads","is reading","are reading"],ans:2,exp:"Con She: is + verb-ing → is reading"},
      {q:"¿Cuándo se usa Present Continuous?",opts:["Hábitos diarios","Hechos permanentes","Acción en este momento","Pasado reciente"],ans:2,exp:"Present Continuous = lo que está pasando AHORA"},
      {q:"¿Cuál es la estructura del Presente Continuo?",opts:["Subject + verb + -s","Subject + did + verb","Subject + to be + verb-ing","Subject + have + verb"],ans:2,exp:"am/is/are + verb + -ing"},
      {q:"They ___ soccer right now.",opts:["play","plays","are playing","is playing"],ans:2,exp:"Con They: are + verb-ing → are playing"},
      {q:"¿Cuál es correcta?",opts:["She is dance","He are running","They is eating","She is dancing"],ans:3,exp:"She is dancing — IS con she, dance + -ing"},
      {q:"Complete: He ___ now.",opts:["sleep","is sleeping","are sleeping","sleeps"],ans:1,exp:"Con He: is sleeping"},
      {q:"We ___ lunch.",opts:["eat","eats","are eating","is eating"],ans:2,exp:"Con We: are eating"},
      {q:"¿Cómo se forma el -ing de 'run'?",opts:["runing","running","runned","runes"],ans:1,exp:"Run → running (consonante doble antes de -ing)"},
      {q:"'Is he running?' pregunta si...",opts:["Él corrió ayer","Él corre siempre","Él está corriendo ahora","Él puede correr"],ans:2,exp:"Is + sujeto + -ing? = pregunta sobre ahora"},
      {q:"Complete: The cat ___ on the sofa.",opts:["sit","sits","is sitting","are sitting"],ans:2,exp:"Con The cat (it): is sitting"},
      {q:"'She's cooking' es forma corta de...",opts:["She was cooking","She has cooked","She is cooking","She will cook"],ans:2,exp:"She's = She is (contracción)"},
      {q:"Complete: It ___ outside.",opts:["rain","rains","are raining","is raining"],ans:3,exp:"Con It: is raining"},
      {q:"¿Cuál frase usa Presente Continuo?",opts:["She reads every day","He is sleeping now","They ate pizza","I will go tomorrow"],ans:1,exp:"He is sleeping now — is + verb-ing = Presente Continuo"},
      {q:"¿Cómo se forma el -ing de 'make'?",opts:["makeing","makking","making","maked"],ans:2,exp:"Make → making (se quita la -e antes de -ing)"},
    ],
    8: [ // The Past Weekend - Was/Were
      {q:"I ___ happy yesterday.",opts:["am","is","was","were"],ans:2,exp:"I en pasado → WAS"},
      {q:"They ___ at school.",opts:["was","is","are","were"],ans:3,exp:"They en pasado → WERE"},
      {q:"She ___ tired last night.",opts:["is","are","was","were"],ans:2,exp:"She en pasado → WAS"},
      {q:"¿Con quiénes usamos 'was'?",opts:["You/We/They","I/He/She/It","Solo con I","Solo con It"],ans:1,exp:"WAS: I was, He was, She was, It was"},
      {q:"¿Con quiénes usamos 'were'?",opts:["I/He/She","You/We/They","Solo con They","Solo con We"],ans:1,exp:"WERE: You were, We were, They were"},
      {q:"He ___ a student in 2010.",opts:["is","are","was","were"],ans:2,exp:"He en pasado → WAS"},
      {q:"We ___ at the party.",opts:["was","is","were","are"],ans:2,exp:"We en pasado → WERE"},
      {q:"Complete: It ___ cold last week.",opts:["is","are","was","were"],ans:2,exp:"It en pasado → WAS"},
      {q:"¿Cuál es la negación de 'was'?",opts:["was not / wasn't","were not","didn't was","not was"],ans:0,exp:"Was not → contracción: wasn't"},
      {q:"¿Cuál es la negación de 'were'?",opts:["was not","weren't","didn't were","not were"],ans:1,exp:"Were not → contracción: weren't"},
      {q:"Complete: ___ you at home yesterday?",opts:["Was","Were","Did","Is"],ans:1,exp:"Con You en pasado → Were you...?"},
      {q:"Complete: She ___ not ready.",opts:["were","is","was","are"],ans:2,exp:"She en pasado → She was not ready"},
      {q:"They ___ very funny.",opts:["was","is","were","are"],ans:2,exp:"They en pasado → WERE"},
      {q:"¿Cuál es correcta?",opts:["He were my teacher","She was my teacher","I were tired","They was happy"],ans:1,exp:"She was my teacher — correcto"},
      {q:"Complete: I ___ born in Colombia.",opts:["am","is","was","were"],ans:2,exp:"Nacimiento = pasado → I WAS born"},
    ],
  },
  A2: {
    1: [ // Past Adventures - Simple Past
      {q:"She ___ to Paris last year.",opts:["go","goes","went","gone"],ans:2,exp:"Go es irregular: went en Simple Past"},
      {q:"I ___ a movie yesterday.",opts:["watch","watches","watched","watching"],ans:2,exp:"Watch → watched (verbo regular, añadir -ed)"},
      {q:"¿Cuándo se usa Simple Past?",opts:["Acciones actuales","Hábitos presentes","Acciones completadas en el pasado","Planes futuros"],ans:2,exp:"Simple Past = acciones terminadas en el pasado"},
      {q:"He ___ not go to school.",opts:["did","does","didn't","wasn't"],ans:2,exp:"Negación en pasado: didn't + verbo base"},
      {q:"¿Cuál es la pregunta correcta en pasado?",opts:["Did she went?","Did she go?","Does she went?","She did go?"],ans:1,exp:"Did + sujeto + verbo BASE: Did she go?"},
      {q:"They ___ pizza for dinner.",opts:["eat","eats","ate","eaten"],ans:2,exp:"Eat es irregular: ate en Simple Past"},
      {q:"'I worked yesterday' — ¿es regular o irregular?",opts:["Irregular","Regular","No existe","Es presente"],ans:1,exp:"Work → worked (regular, añadir -ed)"},
      {q:"He ___ the book last week.",opts:["read","reads","readed","red"],ans:0,exp:"Read es irregular: read (pasado se pronuncia 'red')"},
      {q:"¿Cuál es la forma pasada de 'buy'?",opts:["buyed","boughted","bought","buyd"],ans:2,exp:"Buy es irregular: bought en pasado"},
      {q:"We ___ to the beach last summer.",opts:["go","goes","went","going"],ans:2,exp:"Go → went (irregular)"},
      {q:"'Did you eat breakfast?' se responde con...",opts:["Yes, I ate","Yes, I did","Yes, I eated","Yes, I do"],ans:1,exp:"Respuesta corta: Yes, I did / No, I didn't"},
      {q:"She ___ her homework.",opts:["finished","finish","finishes","finishing"],ans:0,exp:"Finish → finished (regular, -ed)"},
      {q:"¿Cuál es pasado de 'see'?",opts:["seed","saw","seen","sawed"],ans:1,exp:"See es irregular: saw en pasado"},
      {q:"I ___ very tired after the trip.",opts:["am","was","is","were"],ans:1,exp:"I en pasado del verbo 'to be' → was"},
      {q:"'Last' en 'last night' significa...",opts:["Próximo","Pasado/anterior","Durante","Nunca"],ans:1,exp:"Last = pasado: last night, last year, last week"},
    ],
    2: [ // Future Plans - Will / Going to
      {q:"I ___ visit Paris next year.",opts:["am going to","will","go to","going"],ans:1,exp:"Will + verbo base: I will visit"},
      {q:"'She is going to study tonight' expresa...",opts:["Hábito diario","Plan concreto futuro","Pasado reciente","Sorpresa"],ans:1,exp:"Going to = plan decidido, intención"},
      {q:"¿Cuál usa 'will' correctamente?",opts:["I will going home","She will goes to work","They will help you","He will studied"],ans:2,exp:"Will + verbo BASE: They will help"},
      {q:"Complete: It ___ rain tomorrow.",opts:["going to","is going to","will","is will"],ans:2,exp:"Will o is going to para predicción: It will rain"},
      {q:"'I'm going to call you later' — el 'going to' expresa...",opts:["Pasado","Presente","Intención futura","Hábito"],ans:2,exp:"Going to = intención o plan ya decidido"},
      {q:"Forma negativa de 'will':",opts:["won't","willn't","will not to","doesn't will"],ans:0,exp:"Will not → contracción: won't"},
      {q:"¿Cuál es la pregunta correcta con 'will'?",opts:["Will she goes?","Will she go?","Does she will go?","She will go?"],ans:1,exp:"Will + sujeto + verbo base: Will she go?"},
      {q:"'We are going to buy a house' indica...",opts:["Ya compraron","Están comprando ahora","Tienen plan de comprar","Compraron antes"],ans:2,exp:"Going to = plan o intención futura"},
      {q:"¿Cuándo preferimos 'will'?",opts:["Planes ya decididos","Decisiones en el momento","Acciones pasadas","Hábitos"],ans:1,exp:"Will = decisión espontánea o predicción general"},
      {q:"Complete: She ___ be at the meeting.",opts:["is going","will","going to","go"],ans:1,exp:"Will + verbo base: She will be"},
      {q:"'Won't' es contracción de...",opts:["Would not","Will not","Was not","Were not"],ans:1,exp:"Won't = will not (negación futura)"},
      {q:"'Are you going to study?' se responde...",opts:["Yes, I will going","Yes, I am","Yes, I going to","Yes, I do"],ans:1,exp:"Are you going to...? → Yes, I am / No, I'm not"},
      {q:"¿Cuál expresa predicción con evidencia?",opts:["I will eat pizza","I'm going to fall! (he's slipping)","She will call","We will see"],ans:1,exp:"Going to con evidencia: Look! It's going to rain!"},
      {q:"Complete: They ___ travel to Spain.",opts:["is going to","are going to","will going to","goes to"],ans:1,exp:"They + are going to + verbo base"},
      {q:"¿Qué significa 'I'll think about it'?",opts:["Ya lo pensé","Lo pienso siempre","Lo pensaré (decisión momentánea)","No lo pienso"],ans:2,exp:"I'll = I will → decisión en el momento"},
    ],
    3: [ // Comparing Things - Comparatives & Superlatives
      {q:"Mount Everest is ___ mountain in the world.",opts:["taller","the tallest","tall","more tall"],ans:1,exp:"Superlativo: the + adjetivo + -est"},
      {q:"She is ___ than her brother.",opts:["more tall","tallest","taller","the taller"],ans:2,exp:"Comparativo: adjetivo corto + -er: taller"},
      {q:"'More beautiful' es el comparativo de...",opts:["good","beautiful","bad","big"],ans:1,exp:"Beautiful (3 sílabas) → more beautiful"},
      {q:"'The best' es el superlativo de...",opts:["bad","big","good","well"],ans:2,exp:"Good → comparativo: better, superlativo: the best"},
      {q:"This pizza is ___ than that one.",opts:["more delicious","deliciouser","the most delicious","delicious more"],ans:0,exp:"Adjetivos largos: more + adjetivo → more delicious"},
      {q:"¿Cuál es el comparativo de 'big'?",opts:["more big","biger","bigger","bigest"],ans:2,exp:"Big (1 sílaba, CVC): doblar consonante → bigger"},
      {q:"He is ___ student in the class.",opts:["smart","smarter","the smartest","more smart"],ans:2,exp:"Superlativo de smart: the smartest"},
      {q:"'Worse' es el comparativo de...",opts:["good","bad","well","little"],ans:1,exp:"Bad → worse (comparativo irregular)"},
      {q:"¿Cuál es correcta?",opts:["She is more taller","He is the most tall","This is the cheapest","That is more cheap"],ans:2,exp:"Cheapest = superlativo correcto de cheap"},
      {q:"My house is ___ than yours.",opts:["the biggest","big","bigger","most big"],ans:2,exp:"Comparativo de big: bigger (doble g)"},
      {q:"'The most expensive' es...",opts:["Comparativo","Superlativo","Adjetivo simple","Participio"],ans:1,exp:"The most + adjetivo largo = superlativo"},
      {q:"¿Cuál es el comparativo de 'good'?",opts:["gooder","more good","best","better"],ans:3,exp:"Good → better (irregular)"},
      {q:"This movie is ___ interesting than the book.",opts:["most","more","the most","very"],ans:1,exp:"More + adjetivo largo = comparativo"},
      {q:"'As tall as' significa...",opts:["Más alto que","Menos alto que","Tan alto como","El más alto"],ans:2,exp:"As + adjective + as = tan... como (igual)"},
      {q:"¿Cuál es el superlativo de 'bad'?",opts:["baddest","most bad","the worst","badder"],ans:2,exp:"Bad → worse → the worst (superlativo irregular)"},
    ],
    4: [ // Health & Body
      {q:"'I have a headache' significa...",opts:["Tengo hambre","Tengo dolor de cabeza","Estoy cansado","Tengo fiebre"],ans:1,exp:"Headache = dolor de cabeza"},
      {q:"'You should see a doctor' expresa...",opts:["Obligación","Recomendación","Prohibición","Habilidad"],ans:1,exp:"Should = debería (recomendación)"},
      {q:"¿Cómo se dice 'Tengo fiebre'?",opts:["I have a fever","I am hot","I feel bad","I have headache"],ans:0,exp:"I have a fever = Tengo fiebre"},
      {q:"'Take this medicine twice a day' — 'twice' significa...",opts:["Una vez","Dos veces","Tres veces","Cuatro veces"],ans:1,exp:"Twice = dos veces"},
      {q:"¿Cuál parte del cuerpo es 'elbow'?",opts:["Rodilla","Hombro","Codo","Tobillo"],ans:2,exp:"Elbow = codo"},
      {q:"'I feel under the weather' significa...",opts:["Hace frío","Me siento mal/enfermo","Está nublado","Tengo sueño"],ans:1,exp:"Under the weather = no sentirse bien, estar enfermo"},
      {q:"'Symptom' significa...",opts:["Medicina","Síntoma","Diagnóstico","Tratamiento"],ans:1,exp:"Symptom = síntoma"},
      {q:"¿Cuál es el verbo correcto? 'She ___ a cold.'",opts:["has","is","have","gets"],ans:0,exp:"Have a cold = tener un resfriado"},
      {q:"'Prescription' es...",opts:["Una enfermedad","Una receta médica","Un hospital","Un síntoma"],ans:1,exp:"Prescription = receta médica"},
      {q:"'Should' se usa para...",opts:["Obligación fuerte","Habilidad","Recomendación","Permiso"],ans:2,exp:"Should = recomendación: You should rest"},
      {q:"¿Cómo se dice 'Me duele la garganta'?",opts:["I have a cough","My throat hurts","I have a fever","I feel dizzy"],ans:1,exp:"My throat hurts = me duele la garganta"},
      {q:"'Healthy lifestyle' significa...",opts:["Estilo de vida sedentario","Estilo de vida saludable","Comida rápida","Vida estresante"],ans:1,exp:"Healthy = saludable, lifestyle = estilo de vida"},
      {q:"'I'm allergic to...' se usa para...",opts:["Pedir medicina","Describir una alergia","Dar diagnóstico","Pedir ayuda"],ans:1,exp:"I'm allergic to peanuts = Soy alérgico a los cacahuetes"},
      {q:"¿Qué significa 'dizzy'?",opts:["Cansado","Mareado","Con fiebre","Con tos"],ans:1,exp:"Dizzy = mareado/a"},
      {q:"'Rest' como recomendación médica significa...",opts:["Hacer ejercicio","Descansar","Tomar medicina","Ir al hospital"],ans:1,exp:"Rest = descansar"},
    ],
    5:[{q:"'Excuse me, how do I get to the station?'",opts:["Saludo","Pedido de direcciones","Queja","Pregunta de precio"],ans:1,exp:"How do I get to...? = ¿Cómo llego a...?"},{q:"'Turn left at the traffic lights' significa...",opts:["Gira a la derecha en el semáforo","Sigue recto","Gira a la izquierda en el semáforo","Da la vuelta"],ans:2,exp:"Turn left = gira a la izquierda"},{q:"'It's on your right' significa...",opts:["Está a tu izquierda","Está detrás","Está a tu derecha","Está arriba"],ans:2,exp:"On your right = a tu derecha"},{q:"'Straight ahead' significa...",opts:["A la izquierda","Recto/derecho","A la derecha","Atrás"],ans:1,exp:"Straight ahead = sigue recto"},{q:"'How far is it?' pregunta...",opts:["¿Cuánto cuesta?","¿Qué tan lejos está?","¿Cuánto tiempo toma?","¿Dónde está?"],ans:1,exp:"How far = qué tan lejos"},{q:"'Customs' en un aeropuerto es...",opts:["Migración","Aduana","Puerta de embarque","Equipaje"],ans:1,exp:"Customs = aduana"},{q:"'Round trip ticket' significa...",opts:["Tiquete solo de ida","Tiquete de ida y vuelta","Tiquete de primera clase","Tiquete de bus"],ans:1,exp:"Round trip = ida y vuelta"},{q:"'Platform 3' en una estación de tren significa...",opts:["Carril 3","Andén/plataforma 3","Tren 3","Vagón 3"],ans:1,exp:"Platform = andén"},{q:"'Delayed' en vuelos significa...",opts:["A tiempo","Cancelado","Retrasado","Abordando"],ans:2,exp:"Delayed = retrasado/demorado"},{q:"¿Cómo preguntas si hay un hotel cerca?",opts:["Is there a hotel near here?","There is a hotel?","Can I hotel near?","Where hotel?"],ans:0,exp:"Is there a...? = ¿Hay un...?"},{q:"'Gate B12' en un aeropuerto es...",opts:["La sala de equipajes","La puerta de embarque B12","El pasaporte","La aduana"],ans:1,exp:"Gate = puerta de embarque"},{q:"'Boarding pass' es...",opts:["Pasaporte","Tarjeta de embarque","Visa","Equipaje de mano"],ans:1,exp:"Boarding pass = tarjeta de embarque"},{q:"'Check in' en un hotel significa...",opts:["Salir del hotel","Llegar y registrarse","Pagar la cuenta","Reservar"],ans:1,exp:"Check in = registrarse al llegar"},{q:"'Take the second exit' en una rotonda significa...",opts:["Toma la primera salida","Sigue recto","Toma la segunda salida","Da la vuelta"],ans:2,exp:"Take the second exit = toma la segunda salida"},{q:"'Approximately 10 minutes away' significa...",opts:["Exactamente 10 minutos","Aproximadamente 10 minutos","Más de 10 minutos","Menos de 5 minutos"],ans:1,exp:"Approximately = aproximadamente"}],
    6:[{q:"'What do you do for a living?' pregunta...",opts:["Tu hobby","Tu salario","Tu profesión","Tu educación"],ans:2,exp:"For a living = para ganarse la vida = tu trabajo"},{q:"'I'm in charge of...' significa...",opts:["No tengo responsabilidades","Soy responsable de...","Trabajo con...","Estudio..."],ans:1,exp:"In charge of = responsable de, encargado de"},{q:"'Apply for a job' significa...",opts:["Conseguir un trabajo","Perder un trabajo","Solicitar un empleo","Renunciar"],ans:2,exp:"Apply for a job = solicitar/aplicar a un trabajo"},{q:"'Salary' y 'wage' — ¿qué son?",opts:["Tipos de trabajo","Formas de pago","Tipos de empresa","Requisitos laborales"],ans:1,exp:"Salary (mensual) y wage (por hora) son formas de pago"},{q:"'I was promoted' significa...",opts:["Me despidieron","Me ascendieron","Renuncié","Me transfirieron"],ans:1,exp:"Promoted = ascendido, promovido"},{q:"'Deadline' en el trabajo es...",opts:["Un descanso","Una reunión","Una fecha límite","Un proyecto"],ans:2,exp:"Deadline = fecha límite de entrega"},{q:"'Part-time job' es...",opts:["Trabajo a tiempo completo","Trabajo de medio tiempo","Trabajo temporal","Trabajo desde casa"],ans:1,exp:"Part-time = medio tiempo (no jornada completa)"},{q:"'Colleague' significa...",opts:["Jefe","Empleado","Colega/compañero de trabajo","Cliente"],ans:2,exp:"Colleague = colega, compañero/a de trabajo"},{q:"'She was laid off' significa...",opts:["Fue ascendida","Se jubiló","Fue despedida (recorte)","Renunció"],ans:2,exp:"Laid off = despedido por reducción/recorte de personal"},{q:"'Benefits' en un trabajo incluye...",opts:["El salario base","Extras como seguro, vacaciones","La empresa","Los clientes"],ans:1,exp:"Benefits = beneficios laborales (seguro, vacaciones, etc.)"},{q:"'I'm self-employed' significa...",opts:["Tengo un jefe","Soy empleado público","Trabajo por cuenta propia","Estoy desempleado"],ans:2,exp:"Self-employed = independiente, trabajas para ti mismo"},{q:"'Resume/CV' es...",opts:["Una carta de recomendación","Un contrato laboral","Una hoja de vida/currículum","Un certificado"],ans:2,exp:"Resume (EE.UU.) / CV (UK) = hoja de vida"},{q:"'Overtime' significa...",opts:["Tiempo libre","Hora de descanso","Horas extra de trabajo","Vacaciones"],ans:2,exp:"Overtime = horas extra trabajadas"},{q:"'Interview' en el contexto laboral es...",opts:["Una reunión de trabajo","Una entrevista de trabajo","Un descanso","Una prueba técnica"],ans:1,exp:"Job interview = entrevista de trabajo"},{q:"'Remote work' significa...",opts:["Trabajo en la oficina","Trabajo desde casa o remotamente","Trabajo nocturno","Trabajo en el extranjero"],ans:1,exp:"Remote work = trabajo remoto, desde casa"}],
    7:[{q:"'Pollution' significa...",opts:["Solución","Contaminación","Naturaleza","Energía"],ans:1,exp:"Pollution = contaminación"},{q:"'Recycle' significa...",opts:["Comprar nuevo","Desperdiciar","Reciclar","Contaminar"],ans:2,exp:"Recycle = reciclar"},{q:"'Renewable energy' es...",opts:["Energía nuclear","Energía que se puede renovar (solar, eólica)","Petróleo","Carbón"],ans:1,exp:"Renewable = renovable: solar, wind, hydro..."},{q:"'Carbon footprint' significa...",opts:["Huella de carbono","Contaminación del agua","Energía solar","Reciclaje"],ans:0,exp:"Carbon footprint = huella de carbono de nuestras acciones"},{q:"'Deforestation' es...",opts:["Plantar árboles","La tala de bosques","El cuidado del ambiente","La lluvia ácida"],ans:1,exp:"Deforestation = deforestación, tala de árboles"},{q:"'We should reduce waste' — 'waste' significa...",opts:["Energía","Basura/desperdicios","Agua","Ruido"],ans:1,exp:"Waste = residuos, desperdicios, basura"},{q:"'Global warming' es...",opts:["El enfriamiento global","El calentamiento global","Las tormentas","La contaminación del agua"],ans:1,exp:"Global warming = calentamiento global"},{q:"'Endangered species' son...",opts:["Especies invasivas","Especies en peligro de extinción","Animales domésticos","Plantas comunes"],ans:1,exp:"Endangered = en peligro de extinción"},{q:"'Sustainable' significa...",opts:["Contaminante","Sostenible/sustentable","Peligroso","Temporal"],ans:1,exp:"Sustainable = sostenible, que no daña el futuro"},{q:"'Turn off the lights to save energy' — ¿qué propone?",opts:["Encender las luces","Apagar las luces para ahorrar energía","Comprar más lámparas","Usar energía nuclear"],ans:1,exp:"Turn off = apagar, save energy = ahorrar energía"},{q:"'Drought' es...",opts:["Inundación","Terremoto","Sequía","Tormenta"],ans:2,exp:"Drought = sequía (falta de lluvia y agua)"},{q:"'Biodiversity' significa...",opts:["Un solo tipo de animal","Diversidad de vida en un ecosistema","Contaminación","Deforestación"],ans:1,exp:"Biodiversity = biodiversidad, variedad de especies"},{q:"'Solar panels' se usan para...",opts:["Contaminar el agua","Capturar energía solar","Talar árboles","Producir petróleo"],ans:1,exp:"Solar panels = paneles solares para generar electricidad"},{q:"'Climate change' es...",opts:["Un tipo de contaminación","El cambio climático","La desertificación","La lluvia ácida"],ans:1,exp:"Climate change = cambio climático"},{q:"¿Qué significa 'Go green'?",opts:["Pintar de verde","Comer vegetales","Adoptar hábitos ecológicos","Ir al bosque"],ans:2,exp:"Go green = adoptar prácticas ecológicas y sostenibles"}],
    8:[{q:"'Smartphone' es...",opts:["Computador de escritorio","Televisor inteligente","Teléfono inteligente","Tableta"],ans:2,exp:"Smartphone = teléfono inteligente"},{q:"'To download' significa...",opts:["Subir archivos","Descargar archivos","Borrar archivos","Compartir archivos"],ans:1,exp:"Download = descargar (del internet al dispositivo)"},{q:"'Social media' son...",opts:["Medios de comunicación impresos","Redes sociales digitales","Televisión","Radio"],ans:1,exp:"Social media = redes sociales (Instagram, TikTok, etc.)"},{q:"'Password' es...",opts:["Nombre de usuario","Contraseña","Dirección de email","Número de teléfono"],ans:1,exp:"Password = contraseña"},{q:"'Update' en tecnología significa...",opts:["Apagar","Actualizar","Instalar por primera vez","Borrar"],ans:1,exp:"Update = actualizar (software, app)"},{q:"'Crash' en una computadora significa...",opts:["Actualización","Falla/colapso del sistema","Nueva función","Descarga"],ans:1,exp:"Crash = cuando un programa o sistema falla y se cierra"},{q:"'Wi-Fi' es...",opts:["Un tipo de cable","Conexión inalámbrica a internet","Un sistema operativo","Un tipo de pantalla"],ans:1,exp:"Wi-Fi = conexión inalámbrica a internet"},{q:"'Upload' es lo contrario de...",opts:["Update","Delete","Download","Install"],ans:2,exp:"Upload = subir archivos, download = descargar"},{q:"'Battery life' se refiere a...",opts:["La vida del dispositivo","Cuánto dura la batería","El precio del cargador","La velocidad del procesador"],ans:1,exp:"Battery life = duración de la batería"},{q:"'Screen' es...",opts:["Teclado","Pantalla","Parlante","Cámara"],ans:1,exp:"Screen = pantalla"},{q:"'Bug' en programación es...",opts:["Un virus","Un error en el código","Una actualización","Una nueva función"],ans:1,exp:"Bug = error o fallo en el software"},{q:"'Streaming' significa...",opts:["Descargar para ver después","Ver/escuchar en tiempo real por internet","Grabar un video","Imprimir"],ans:1,exp:"Streaming = transmisión en tiempo real (Netflix, Spotify)"},{q:"'Backup' de datos significa...",opts:["Borrar datos","Copiar datos como respaldo","Actualizar datos","Compartir datos"],ans:1,exp:"Backup = copia de respaldo de tus datos"},{q:"'Notification' es...",opts:["Una contraseña","Un mensaje de error","Un aviso o alerta de una app","Una actualización"],ans:2,exp:"Notification = notificación, aviso de una aplicación"},{q:"'Artificial Intelligence (AI)' es...",opts:["Un tipo de robot físico","Software que simula inteligencia humana","Una red social","Un tipo de hardware"],ans:1,exp:"AI = Inteligencia Artificial"}],
  },
  B1: {
    1:[{q:"'Despite' + noun introduces...",opts:["A reason","A contrast","A condition","A result"],ans:1,exp:"Despite/In spite of = a pesar de (contraste)"},{q:"'I have been living here for 5 years.' — This is...",opts:["Simple Present","Present Perfect Simple","Present Perfect Continuous","Past Continuous"],ans:2,exp:"Have been + -ing = Present Perfect Continuous"},{q:"Present Perfect Continuous implies...",opts:["A finished past action","An ongoing action from past to present","A future plan","A habit"],ans:1,exp:"Been + -ing = acción que inició en el pasado y continúa"},{q:"'Unless you study, you will fail.' — 'Unless' means...",opts:["If","Even if","If not / except if","Although"],ans:2,exp:"Unless = a menos que / si no"},{q:"'She had already left when I arrived.' — Which tense?",opts:["Past Simple","Past Perfect","Past Continuous","Present Perfect"],ans:1,exp:"Had + past participle = Past Perfect (antes del pasado)"},{q:"'The report needs to be finished.' — This uses...",opts:["Active voice","Passive voice","Conditional","Reported speech"],ans:1,exp:"Needs to be + past participle = pasiva"},{q:"¿Cuál es correcta en Reported Speech?",opts:["She said 'I am tired'","She said that she was tired","She told I am tired","She said she is tired"],ans:1,exp:"Reported speech: say + that + pasado → she was"},{q:"'I wish I had more time.' expresses...",opts:["A real possibility","A regret about the present","A plan","A fact"],ans:1,exp:"I wish + past simple = deseo irreal en el presente"},{q:"'By the time she arrived, he had gone.' — 'Had gone' is...",opts:["Past Simple","Past Perfect","Present Perfect","Future Perfect"],ans:1,exp:"Had + past participle = Past Perfect (acción anterior)"},{q:"'Whereas' introduces...",opts:["A reason","A result","A contrast","A condition"],ans:2,exp:"Whereas = mientras que (contraste entre dos ideas)"},{q:"'If I were rich, I would travel more.' — This is...",opts:["First conditional","Second conditional","Third conditional","Zero conditional"],ans:1,exp:"Second conditional: If + past, would + base (irreal presente)"},{q:"Passive voice: 'The letter was written by John.' Active is...",opts:["John write the letter","John wrote the letter","John writes the letter","John is writing the letter"],ans:1,exp:"Pasiva → activa: John (subject) + wrote (past)"},{q:"'The more you practice, the better you get.' This structure is...",opts:["Comparativo doble","Superlativo","Conditional","Relative clause"],ans:0,exp:"The more/less + subject + verb = comparativo doble progresivo"},{q:"'I'd rather stay home than go out.' — 'Rather' expresses...",opts:["Obligation","Preference","Ability","Permission"],ans:1,exp:"Would rather = preferiría (preferencia)"},{q:"'So far' means...",opts:["From now on","Eventually","Up to this point","In the past"],ans:2,exp:"So far = hasta ahora / hasta este momento"}],
    2:[{q:"'I used to play guitar.' implies...",opts:["I still play guitar","I played once","I regularly did in the past but not now","I will play again"],ans:2,exp:"Used to = solía (hábito pasado que ya no existe)"},{q:"'Habits are hard to break' means...",opts:["Los hábitos son fáciles de cambiar","Los hábitos son difíciles de cambiar","Los hábitos son buenos","Los hábitos son malos"],ans:1,exp:"Break a habit = dejar un hábito"},{q:"'She tends to arrive late.' — 'tends to' means...",opts:["She is forced to","She usually does","She will do once","She did before"],ans:1,exp:"Tend to = tender a, soler hacer algo"},{q:"'Making a resolution' means...",opts:["Cumplir una meta","Propósito/compromiso (Año Nuevo)","Celebrar algo","Cambiar de opinión"],ans:1,exp:"New Year's resolution = propósito de Año Nuevo"},{q:"'Cut down on' in lifestyle means...",opts:["Increase","Stop completely","Reduce","Start doing"],ans:2,exp:"Cut down on = reducir (cut down on sugar)"},{q:"'I gave up smoking.' — 'gave up' means...",opts:["Empecé a fumar","Reduje el cigarro","Dejé de fumar","Fumo más"],ans:2,exp:"Give up = dejar, renunciar a algo"},{q:"'Work-life balance' refers to...",opts:["Solo trabajar","Solo vida personal","Equilibrio entre trabajo y vida personal","Trabajar desde casa"],ans:2,exp:"Work-life balance = equilibrio entre trabajo y vida privada"},{q:"'Procrastinate' significa...",opts:["Ser organizado","Aplazar tareas y actividades","Trabajar duro","Planificar bien"],ans:1,exp:"Procrastinate = posponer, aplazar tareas"},{q:"'Mindfulness' is about...",opts:["Ignorar el presente","Atención plena al momento presente","Pensar en el futuro","Meditar solo"],ans:1,exp:"Mindfulness = atención plena, conciencia del presente"},{q:"'She has taken up yoga.' — 'taken up' means...",opts:["Dejó el yoga","Empezó a practicar yoga","Odia el yoga","Estudia yoga"],ans:1,exp:"Take up = empezar a practicar un hobby o actividad"},{q:"'Sedentary lifestyle' means...",opts:["Estilo de vida activo","Estilo de vida saludable","Estilo de vida sedentario (poco movimiento)","Trabajo duro"],ans:2,exp:"Sedentary = sedentario, sin actividad física"},{q:"'I'm trying to cut back on caffeine.' — 'cut back on' means...",opts:["Aumentar","Reducir gradualmente","Eliminar completamente","Empezar a consumir"],ans:1,exp:"Cut back on = reducir gradualmente el consumo de algo"},{q:"'Keep fit' means...",opts:["Mantenerse enojado","Mantenerse en forma físicamente","Guardar ropa","Llegar a tiempo"],ans:1,exp:"Keep fit = mantenerse en forma, hacer ejercicio"},{q:"'Get into a routine' means...",opts:["Romper una rutina","Establecer una rutina regular","Evitar rutinas","Cambiar de rutina"],ans:1,exp:"Get into a routine = establecer y seguir una rutina"},{q:"'She finds it hard to switch off.' — 'switch off' here means...",opts:["Apagar la luz","Relajarse y desconectarse del trabajo","Dormir","Salir de casa"],ans:1,exp:"Switch off = desconectarse, relajarse y dejar de pensar"}],
    3:[{q:"'Come up with' a solution means...",opts:["Ignorar el problema","Crear o encontrar una solución","Empeorar el problema","Pedir ayuda"],ans:1,exp:"Come up with = idear, encontrar (una solución)"},{q:"'If I had studied harder, I would have passed.' — This is...",opts:["First conditional","Second conditional","Third conditional","Zero conditional"],ans:2,exp:"Third conditional: If + past perfect, would have + PP"},{q:"'It is reported that...' uses...",opts:["Active voice","Passive reporting verb","Direct speech","Question form"],ans:1,exp:"It is reported/said/believed = pasiva con verbo de reporte"},{q:"'Look into' a problem means...",opts:["Ignorarlo","Investigarlo/analizarlo","Resolverlo inmediatamente","Evitarlo"],ans:1,exp:"Look into = investigar, examinar"},{q:"'The project was put off.' — 'put off' means...",opts:["Cancelado","Finalizado","Pospuesto/retrasado","Aprobado"],ans:2,exp:"Put off = posponer, aplazar"},{q:"'Pros and cons' means...",opts:["Problemas y soluciones","Ventajas y desventajas","Causas y efectos","Preguntas y respuestas"],ans:1,exp:"Pros = ventajas, cons = desventajas"},{q:"'We need to deal with this issue.' — 'deal with' means...",opts:["Ignorar","Manejar/ocuparse de","Crear","Evitar"],ans:1,exp:"Deal with = encargarse de, tratar un problema"},{q:"'Brainstorm' in a meeting means...",opts:["Criticar ideas","Generar ideas libremente","Tomar decisiones finales","Presentar datos"],ans:1,exp:"Brainstorm = lluvia de ideas, generar soluciones"},{q:"'Break down' a problem means...",opts:["Ignorarlo","Empeorarlo","Dividirlo en partes más pequeñas","Resolverlo inmediatamente"],ans:2,exp:"Break down = descomponer en partes manejables"},{q:"'Compromise' in a conflict means...",opts:["Ceder en todo","Negarse a ceder","Llegar a un acuerdo mutuo cediendo algo cada uno","Ganar el conflicto"],ans:2,exp:"Compromise = llegar a un término medio, acuerdo mutuo"},{q:"'Trial and error' means...",opts:["Investigación científica","Aprender probando y cometiendo errores","Juicio legal","Experimento planificado"],ans:1,exp:"Trial and error = ensayo y error"},{q:"'Feasible' means...",opts:["Imposible","Difícil","Factible/viable","Caro"],ans:2,exp:"Feasible = factible, que se puede hacer"},{q:"'Root cause' of a problem is...",opts:["El efecto","Una consecuencia","La causa principal/raíz","Una solución temporal"],ans:2,exp:"Root cause = causa raíz, origen del problema"},{q:"'Implement a strategy' means...",opts:["Planear una estrategia","Poner en práctica una estrategia","Criticar una estrategia","Cancelar una estrategia"],ans:1,exp:"Implement = implementar, poner en práctica"},{q:"'Contingency plan' is...",opts:["Plan principal","Plan de respaldo para imprevistos","Plan financiero","Plan de marketing"],ans:1,exp:"Contingency plan = plan de contingencia (plan B)"}],
    4:[{q:"'Contemporary art' means...",opts:["Arte antiguo","Arte del presente/actual","Arte clásico","Arte religioso"],ans:1,exp:"Contemporary = contemporáneo, del tiempo actual"},{q:"'A renowned artist' is...",opts:["Un artista desconocido","Un artista aficionado","Un artista famoso/reconocido","Un artista local"],ans:2,exp:"Renowned = renombrado, famoso, reconocido"},{q:"'Literature' refers to...",opts:["Pintura","Música","Obras escritas (novelas, poesía)","Escultura"],ans:2,exp:"Literature = literatura, obras escritas y su estudio"},{q:"'Heritage' in culture means...",opts:["Moda actual","Patrimonio cultural heredado","Arte moderno","Entretenimiento"],ans:1,exp:"Heritage = patrimonio, herencia cultural"},{q:"'Subjective' vs 'objective' in art: 'subjective' means...",opts:["Basado en hechos","Basado en opinión personal","Científicamente probado","Universal"],ans:1,exp:"Subjective = subjetivo, basado en percepción personal"},{q:"'The plot of a novel' is...",opts:["El autor","El título","La trama/historia","El género"],ans:2,exp:"Plot = trama, la historia o argumento de una obra"},{q:"'Avant-garde' art is...",opts:["Arte clásico tradicional","Arte experimental e innovador","Arte religioso","Arte popular"],ans:1,exp:"Avant-garde = vanguardista, experimental e innovador"},{q:"'To portray' means...",opts:["Destruir","Ignorar","Representar/retratar","Criticar"],ans:2,exp:"Portray = retratar, representar (en arte o texto)"},{q:"'A cultural stereotype' is...",opts:["Una tradición única","Una generalización simplificada sobre un grupo","Una celebración","Un idioma"],ans:1,exp:"Stereotype = estereotipo, generalización sobre un grupo"},{q:"'Performing arts' include...",opts:["Pintura y escultura","Teatro, música y danza","Literatura y poesía","Fotografía y cine"],ans:1,exp:"Performing arts = artes escénicas (teatro, danza, música)"},{q:"'Censorship' in media means...",opts:["Libertad de expresión total","Control y restricción de contenido","Distribución gratuita","Publicidad"],ans:1,exp:"Censorship = censura, control del contenido publicado"},{q:"'Masterpiece' is...",opts:["Una obra mediocre","Una obra de arte notable/maestra","Un artista famoso","Un museo"],ans:1,exp:"Masterpiece = obra maestra"},{q:"'Abstract art' focuses on...",opts:["Representación realista","Formas, colores y emociones sin representar cosas reales","Fotografía","Arte religioso"],ans:1,exp:"Abstract = abstracto, no representa objetos reconocibles"},{q:"'Lyrics' are...",opts:["Las notas musicales","La melodía","La letra de una canción","El ritmo"],ans:2,exp:"Lyrics = letra de una canción"},{q:"'Debut' of an artist means...",opts:["Su última actuación","Su primera actuación pública","Su mejor actuación","Su actuación más famosa"],ans:1,exp:"Debut = primera aparición o presentación pública"}],
    5:[{q:"'Breakthrough' in science means...",opts:["Un fracaso","Un descubrimiento importante","Un experimento normal","Una hipótesis"],ans:1,exp:"Breakthrough = avance significativo, descubrimiento clave"},{q:"'Hypothesis' is...",opts:["Una conclusión probada","Una suposición para probar","Un resultado","Una teoría definitiva"],ans:1,exp:"Hypothesis = hipótesis, suposición inicial a probar"},{q:"'Peer review' means...",opts:["Revisión por expertos del mismo campo","Revisión por estudiantes","Revisión por el público","Revisión comercial"],ans:0,exp:"Peer review = revisión por pares, expertos del área"},{q:"'Stem cells' are important because...",opts:["Son decorativas","Pueden convertirse en muchos tipos de células","Son fáciles de ver","Son baratas"],ans:1,exp:"Stem cells = células madre, pueden diferenciarse en otros tipos"},{q:"'Genetically modified' organism (GMO) has been...",opts:["Cultivado orgánicamente","Alterado genéticamente","Extinguido","Descubierto recientemente"],ans:1,exp:"GMO = organismo genéticamente modificado"},{q:"'Clinical trial' is...",opts:["Una cirugía","Una prueba de medicamento en personas","Un diagnóstico","Una vacuna"],ans:1,exp:"Clinical trial = ensayo clínico en seres humanos"},{q:"'Renewable vs non-renewable': oil is...",opts:["Renovable","No renovable","Limpio","Sostenible"],ans:1,exp:"Oil/fossil fuels = no renovable (se agota)"},{q:"'Nanotechnology' works at the scale of...",opts:["Metros","Kilómetros","Nanómetros (muy pequeño)","Milímetros"],ans:2,exp:"Nano = 10⁻⁹ metros, escala atómica y molecular"},{q:"'Evolve' in science means...",opts:["Extinguirse","Cambiar y desarrollarse gradualmente","Mantenerse igual","Aparecer de repente"],ans:1,exp:"Evolve = evolucionar, cambiar gradualmente con el tiempo"},{q:"'Gravity' is...",opts:["Una teoría sin evidencia","La fuerza que atrae objetos entre sí","Un tipo de energía solar","Un elemento químico"],ans:1,exp:"Gravity = gravedad, fuerza de atracción entre masas"},{q:"'Observation' in the scientific method comes...",opts:["Después de la conclusión","Después del experimento","Al inicio, antes de la hipótesis","Solo en teoría"],ans:2,exp:"Observation primero → hipótesis → experimento → conclusión"},{q:"'Fossil fuels' are formed from...",opts:["Plantas vivas","Restos de organismos antiguos","Agua","Rocas volcánicas"],ans:1,exp:"Fossil fuels = combustibles fósiles, de restos orgánicos"},{q:"'Vaccine' works by...",opts:["Curando la enfermedad","Previniendo la enfermedad entrenando el sistema inmune","Matando virus directamente","Reemplazando células"],ans:1,exp:"Vaccine = vacuna, entrena el sistema inmune preventivamente"},{q:"'Theory' in science is...",opts:["Solo una opinión","Una explicación provisional sin evidencia","Una explicación bien sustentada con evidencia","Una suposición inicial"],ans:2,exp:"Scientific theory = explicación respaldada por evidencia"},{q:"'Sustainable development' balances...",opts:["Solo economía","Solo ambiente","Economía, sociedad y ambiente para el futuro","Solo tecnología"],ans:2,exp:"Sustainable development = desarrollo sostenible (los 3 pilares)"}],
    6:[{q:"'Bias' in media means...",opts:["Noticias equilibradas","Prejuicio o parcialidad en la presentación","Información verificada","Periodismo objetivo"],ans:1,exp:"Bias = sesgo, parcialidad o prejuicio"},{q:"'Clickbait' is...",opts:["Noticias confiables","Titulares sensacionalistas para atraer clics","Publicidad honesta","Contenido educativo"],ans:1,exp:"Clickbait = contenido diseñado para atraer clics con títulos exagerados"},{q:"'Breaking news' means...",opts:["Noticias antiguas","Noticias falsas","Noticias de última hora","Noticias locales"],ans:2,exp:"Breaking news = noticias de último momento"},{q:"'Misinformation' is...",opts:["Información correcta","Información falsa o incorrecta","Información científica","Información clasificada"],ans:1,exp:"Misinformation = desinformación, información falsa"},{q:"'Journalism ethics' requires...",opts:["Publicar sin verificar","Omitir hechos importantes","Verificar hechos y ser imparcial","Favorecer una sola perspectiva"],ans:2,exp:"Journalism ethics = ética periodística, verificar y ser imparcial"},{q:"'Go viral' in social media means...",opts:["Contraer un virus","Difundirse masivamente","Ser eliminado","Ser privado"],ans:1,exp:"Go viral = volverse viral, difundirse masivamente en internet"},{q:"'Editorial' in a newspaper is...",opts:["Un anuncio publicitario","La opinión oficial del periódico","Una noticia de último momento","Una entrevista"],ans:1,exp:"Editorial = editorial, posición u opinión oficial del medio"},{q:"'Anonymous source' in journalism is...",opts:["Una fuente identificada","Una fuente cuya identidad se protege","Una fuente poco confiable","Una fuente oficial"],ans:1,exp:"Anonymous source = fuente anónima, identidad protegida"},{q:"'Paparazzi' are...",opts:["Periodistas de investigación","Fotógrafos que siguen a celebridades","Presentadores de noticias","Editores de revistas"],ans:1,exp:"Paparazzi = fotógrafos que siguen a celebridades sin su consentimiento"},{q:"'On the record' means...",opts:["Secreto","La información puede ser publicada con la fuente","Solo para el periodista","Información falsa"],ans:1,exp:"On the record = declaración que puede publicarse citando la fuente"},{q:"'Propaganda' is media designed to...",opts:["Informar objetivamente","Influenciar opiniones con fines políticos","Entretener al público","Enseñar historia"],ans:1,exp:"Propaganda = información diseñada para influenciar con fines ideológicos"},{q:"'Subscription' to a news site means...",opts:["Acceso gratis","Pago regular por acceso al contenido","Publicar noticias","Compartir contenido"],ans:1,exp:"Subscription = suscripción, pago para acceder a contenido"},{q:"'Freelance journalist' works...",opts:["Solo para un medio","Para varios medios de forma independiente","En el gobierno","Como editor"],ans:1,exp:"Freelance = independiente, trabaja para múltiples clientes"},{q:"'Headline' in a newspaper is...",opts:["El cuerpo del artículo","El titular principal de la noticia","El pie de foto","El autor"],ans:1,exp:"Headline = titular, título principal del artículo"},{q:"'Satire' in media is...",opts:["Noticias serias","Humor crítico sobre temas sociales/políticos","Publicidad","Reportaje investigativo"],ans:1,exp:"Satire = sátira, crítica social o política con humor"}],
    7:[{q:"'Inequality' refers to...",opts:["Igualdad de oportunidades","Diferencia injusta en recursos/oportunidades","Paz social","Progreso económico"],ans:1,exp:"Inequality = desigualdad, distribución injusta"},{q:"'Refugee' is a person who...",opts:["Viaja por turismo","Huye de su país por persecución o guerra","Estudia en el extranjero","Trabaja en otro país"],ans:1,exp:"Refugee = refugiado, persona que huye de peligro en su país"},{q:"'Human rights' include...",opts:["Solo derechos políticos","Libertades fundamentales (vida, libertad, expresión)","Solo derechos económicos","Privilegios especiales"],ans:1,exp:"Human rights = derechos humanos fundamentales universales"},{q:"'Poverty line' is...",opts:["Una frontera geográfica","El nivel mínimo de ingresos para cubrir necesidades básicas","Un tipo de impuesto","Una política de precios"],ans:1,exp:"Poverty line = línea de pobreza, umbral mínimo de ingresos"},{q:"'Grassroots movement' starts...",opts:["Con el gobierno","Con grandes corporaciones","Con ciudadanos comunes desde abajo","Con organizaciones internacionales"],ans:2,exp:"Grassroots = movimiento de base, iniciado por ciudadanos comunes"},{q:"'Discrimination' means...",opts:["Respeto hacia todos","Trato injusto a alguien por su raza, género, etc.","Igualdad de derechos","Diversidad cultural"],ans:1,exp:"Discrimination = discriminación, trato injusto e injustificado"},{q:"'NGO' stands for...",opts:["National Government Organization","Non-Governmental Organization","New Global Order","National Geographic Organization"],ans:1,exp:"NGO = Non-Governmental Organization = ONG"},{q:"'Diplomacy' in international relations is...",opts:["Usar la fuerza militar","Negociar pacíficamente entre países","Imponer sanciones","Declarar guerra"],ans:1,exp:"Diplomacy = diplomacia, negociación pacífica entre naciones"},{q:"'Sanction' against a country means...",opts:["Ayuda económica","Medida punitiva (restricciones económicas)","Tratado de paz","Acuerdo comercial"],ans:1,exp:"Sanction = sanción, penalización o restricción impuesta"},{q:"'Asylum seeker' is someone who...",opts:["Trabaja ilegalmente","Pide protección oficial a otro país","Estudia en el extranjero","Hace turismo"],ans:1,exp:"Asylum seeker = solicitante de asilo, pide protección legal"},{q:"'Globalization' refers to...",opts:["Cierre de fronteras","Integración mundial de economías y culturas","Aislamiento de países","Solo comercio local"],ans:1,exp:"Globalization = globalización, integración mundial"},{q:"'Conflict resolution' is...",opts:["Iniciar conflictos","Resolver disputas pacíficamente","Ignorar conflictos","Escalar tensiones"],ans:1,exp:"Conflict resolution = resolución de conflictos de forma pacífica"},{q:"'Aid' in international context means...",opts:["Una deuda","Ayuda humanitaria o económica","Un castigo","Una guerra"],ans:1,exp:"Aid = ayuda (humanitarian aid = ayuda humanitaria)"},{q:"'Sovereignty' of a country means...",opts:["Dependencia de otros países","Poder e independencia para gobernarse a sí mismo","Unión con otros países","Control extranjero"],ans:1,exp:"Sovereignty = soberanía, poder independiente de gobierno"},{q:"'Activist' is someone who...",opts:["Ignora los problemas sociales","Trabaja activamente por un cambio social o político","Solo vota en elecciones","Es político profesional"],ans:1,exp:"Activist = activista, persona que trabaja por el cambio social"}],
    8:[{q:"'Networking' in a professional context means...",opts:["Instalar redes de internet","Construir relaciones profesionales útiles","Trabajar en equipo","Usar redes sociales"],ans:1,exp:"Networking = construir y mantener una red de contactos profesionales"},{q:"'Entrepreneurship' is...",opts:["Trabajar para una gran empresa","Crear y gestionar tu propio negocio","Estudiar administración","Ser empleado público"],ans:1,exp:"Entrepreneurship = emprendimiento, iniciar y gestionar un negocio propio"},{q:"'Career path' means...",opts:["El camino al trabajo","La trayectoria profesional a lo largo del tiempo","El tipo de carrera universitaria","El salario esperado"],ans:1,exp:"Career path = trayectoria o camino de la carrera profesional"},{q:"'Negotiate a salary' means...",opts:["Aceptar el salario sin cuestionarlo","Discutir y acordar el salario","Rechazar cualquier salario","Pedir un aumento impulsivamente"],ans:1,exp:"Negotiate = negociar, discutir términos para llegar a un acuerdo"},{q:"'Cover letter' is...",opts:["Una carta de recomendación","Una carta presentación para una oferta laboral","Un contrato de trabajo","Un certificado laboral"],ans:1,exp:"Cover letter = carta de presentación que acompaña el CV"},{q:"'Ambition' is...",opts:["Falta de motivación","Deseo fuerte de lograr éxito o metas","Miedo al fracaso","Conformismo"],ans:1,exp:"Ambition = ambición, deseo de lograr metas y éxito"},{q:"'Transferable skills' are...",opts:["Habilidades técnicas específicas","Habilidades aplicables en diferentes trabajos","Habilidades obsoletas","Habilidades académicas solo"],ans:1,exp:"Transferable skills = habilidades transferibles (comunicación, liderazgo)"},{q:"'Mentorship' means...",opts:["Supervisión estricta","Guía de un experto a alguien menos experimentado","Trabajo independiente","Evaluación de desempeño"],ans:1,exp:"Mentorship = mentoría, guía de experto a principiante"},{q:"'Work ethic' is...",opts:["El salario de un trabajador","Los valores y actitudes hacia el trabajo","El horario laboral","El contrato de trabajo"],ans:1,exp:"Work ethic = ética laboral, actitud y valores en el trabajo"},{q:"'Internship' is...",opts:["Un trabajo permanente","Una práctica laboral temporal (generalmente estudiantil)","Un contrato a largo plazo","Un trabajo de alto nivel"],ans:1,exp:"Internship = pasantía, práctica profesional generalmente estudiantil"},{q:"'Leadership' skills include...",opts:["Seguir instrucciones","Motivar, guiar y tomar decisiones para un equipo","Trabajar solo","Evitar responsabilidades"],ans:1,exp:"Leadership = liderazgo, capacidad de guiar y motivar equipos"},{q:"'Redundancy' in employment means...",opts:["Un aumento de sueldo","Ser despedido porque el puesto ya no es necesario","Un ascenso","Trabajar horas extra"],ans:1,exp:"Redundancy = despido por restructuración (el cargo desaparece)"},{q:"'Perseverance' is key for success because...",opts:["Permite rendirse fácilmente","Te hace persistir ante obstáculos y fracasos","Garantiza el éxito inmediato","Elimina el trabajo duro"],ans:1,exp:"Perseverance = perseverancia, seguir adelante a pesar de los obstáculos"},{q:"'Job satisfaction' refers to...",opts:["El salario mensual","Qué tan satisfecho te sientes con tu trabajo","Las vacaciones disponibles","El horario de trabajo"],ans:1,exp:"Job satisfaction = satisfacción laboral, nivel de contento con el trabajo"},{q:"'Proactive' employee is one who...",opts:["Espera instrucciones para todo","Toma iniciativa y anticipa problemas","Trabaja solo lo mínimo","Evita responsabilidades"],ans:1,exp:"Proactive = proactivo, toma iniciativa sin esperar que le pidan"}],
  }
};

// ════════════════════════════════════════════════════
// CONFIG DESDE PHP
// ════════════════════════════════════════════════════
const CONFIG = {
  nivel:       <?= json_encode($nivel) ?>,
  modulo:      <?= (int)$modulo ?>,
  esGeneral:   <?= $es_general ? 'true' : 'false' ?>,
  nombreMod:   <?= json_encode($nombre_modulo) ?>,
  nombreEst:   <?= json_encode($nombre_est) ?>,
  intentosPrev:<?= (int)$intentos_prev ?>,
  bestScore:   <?= (int)$best['best'] ?>,
  yaAprobado:  <?= $best['aprobado'] ? 'true' : 'false' ?>,
};

// ════════════════════════════════════════════════════
// LÓGICA DEL QUIZ
// ════════════════════════════════════════════════════
let questions = [], answers = [], current = 0, score = 0;

function getQuestions() {
  let pool = [];
  if (CONFIG.esGeneral) {
    // Quiz general: mezcla preguntas de todos los módulos del nivel
    const mods = BANCO[CONFIG.nivel] || {};
    Object.values(mods).forEach(arr => pool.push(...arr));
  } else {
    pool = (BANCO[CONFIG.nivel] || {})[CONFIG.modulo] || [];
  }
  // Barajar y tomar 10
  const shuffled = [...pool].sort(() => Math.random() - 0.5);
  return shuffled.slice(0, 10);
}

function buildQuestion(idx) {
  const q = questions[idx];
  const opts = [...q.opts].map((o, i) => ({text:o, orig:i})).sort(() => Math.random() - 0.5);
  const correctOpt = opts.findIndex(o => o.orig === q.ans);

  document.getElementById('quizContainer').innerHTML = `
    <div class="question-card" id="qCard">
      <div class="q-num">Pregunta ${idx+1} de ${questions.length}</div>
      <div class="q-text">${q.q}</div>
      <div class="options" id="optGroup">
        ${opts.map((o,i) => `
          <button class="opt-btn" id="opt${i}" onclick="selectOption(${i}, ${o.orig === q.ans ? 1 : 0}, this, '${o.orig === q.ans ? '' : opts[correctOpt] ? opts[correctOpt].text.replace(/'/g,"\\'") : ''}', '${(q.exp||'').replace(/'/g,"\\'")}')">
            ${String.fromCharCode(65+i)}. ${o.text}
          </button>`).join('')}
      </div>
      <div class="q-feedback" id="qFeedback"></div>
    </div>`;

  // Actualizar progreso
  const pct = ((idx) / questions.length) * 100;
  document.getElementById('qProgressFill').style.width = pct + '%';
  document.getElementById('btnNext').style.display = 'none';
  document.getElementById('btnFinish').style.display = 'none';
}

function selectOption(btnIdx, isCorrect, btn, correctText, explanation) {
  // Bloquear todos los botones
  document.querySelectorAll('.opt-btn').forEach(b => b.disabled = true);

  const feedback = document.getElementById('qFeedback');

  if (isCorrect) {
    btn.classList.add('correct');
    score++;
    feedback.className = 'q-feedback ok';
    feedback.innerHTML = '✅ ' + (explanation || '¡Correcto!');
  } else {
    btn.classList.add('wrong');
    // Marcar la correcta
    document.querySelectorAll('.opt-btn').forEach(b => {
      if (b.textContent.trim().slice(3) === correctText.trim()) b.classList.add('correct');
    });
    feedback.className = 'q-feedback fail';
    feedback.innerHTML = '❌ Incorrecto. ' + (explanation || 'Respuesta equivocada.');
  }
  feedback.style.display = 'block';
  answers.push(isCorrect);

  // Mostrar botón siguiente o finalizar
  if (current < questions.length - 1) {
    document.getElementById('btnNext').style.display = 'inline-block';
  } else {
    document.getElementById('btnFinish').style.display = 'inline-block';
  }
}

function nextQuestion() {
  current++;
  buildQuestion(current);
}

async function finishQuiz() {
  const scorePct = Math.round((score / questions.length) * 100);
  const aprobado = scorePct >= 70;

  // Actualizar barra de progreso
  document.getElementById('qProgressFill').style.width = '100%';
  document.getElementById('btnFinish').style.display = 'none';

  // Guardar en servidor
  try {
    await fetch('/intep/cursoingles/api/quiz_guardar.php', {
      method:'POST', credentials:'include',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({nivel:CONFIG.nivel, modulo_num:CONFIG.modulo, score:scorePct})
    });
  } catch(e) { console.warn('Error guardando quiz:', e); }

  // Mostrar resultado
  showResult(scorePct, aprobado);
}

function showResult(scorePct, aprobado) {
  const overlay = document.getElementById('resultOverlay');
  overlay.style.display = 'flex';

  document.getElementById('resEmoji').textContent  = aprobado ? '🏆' : '😕';
  document.getElementById('resTitle').textContent  = aprobado ? '¡Lo lograste!' : 'Casi lo tienes...';
  document.getElementById('resScore').innerHTML    = `<span class="${aprobado?'score-pass':'score-fail'}">${scorePct}%</span>`;
  document.getElementById('resSub').textContent    = aprobado
    ? `Respondiste ${score}/${questions.length} correctas. ¡Módulo aprobado!`
    : `Respondiste ${score}/${questions.length} correctas. Necesitas 7/10 para aprobar.`;

  const xpBadge = document.getElementById('xpBadge');
  if (aprobado) {
    xpBadge.style.display = 'inline-block';
    xpBadge.textContent = CONFIG.esGeneral ? '+200 XP 🌟 ¡Nivel completado!' : '+50 XP ⭐';
    confetti({particleCount:120,spread:70,origin:{y:0.6},colors:['#6366f1','#10b981','#fbbf24']});
  }

  const btns = document.getElementById('resBtns');
  if (aprobado) {
    const certUrl = `/intep/cursoingles/certificado.php?nivel=${CONFIG.nivel}&modulo=${CONFIG.modulo}`;
    btns.innerHTML = `
      <a href="${certUrl}" target="_blank" class="btn-cert">📜 Ver Certificado</a>
      <button class="btn-retry" onclick="goBack()">← Volver al módulo</button>
      <button class="btn-back-dash" onclick="window.location='/intep/cursoingles/dashboard${CONFIG.nivel==='A2'?'_a2':CONFIG.nivel==='B1'?'_b1':''}.php'">🏠 Dashboard del curso</button>`;
  } else {
    btns.innerHTML = `
      <button class="btn-cert" style="background:linear-gradient(135deg,#6366f1,#4f46e5);" onclick="retryQuiz()">🔄 Intentar de nuevo</button>
      <button class="btn-retry" onclick="goBack()">← Volver al módulo</button>`;
    btns.insertAdjacentHTML('afterbegin', `<p style="color:#fca5a5;font-size:0.9rem;margin-bottom:10px;">Las preguntas cambiarán en el próximo intento.</p>`);
  }
}

function retryQuiz() {
  // Resetear y cargar nuevas preguntas aleatorias
  document.getElementById('resultOverlay').style.display = 'none';
  score = 0; current = 0; answers = [];
  questions = getQuestions();
  buildQuestion(0);
}

function goBack() { history.back(); }

// ── Inicializar ──────────────────────────────────────
window.addEventListener('load', () => {
  questions = getQuestions();
  if (questions.length === 0) {
    document.getElementById('quizContainer').innerHTML = '<p style="color:#ef4444;text-align:center;padding:40px;">No hay preguntas disponibles para este módulo.</p>';
    return;
  }
  document.getElementById('qTotal').textContent = questions.length;
  buildQuestion(0);
});
</script>
</body>
</html>
