<?php
// ============================================================
// INTEP INGLÉS — Módulo principal
// ============================================================
require_once 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: login.php'); exit;
}

$est_id = (int)$_SESSION['estudiante_id'];

// Datos del estudiante + programa
$st = mysqli_prepare($conexion, "SELECT e.nombre, e.foto, e.programa_id FROM estudiantes e WHERE e.id = ?");
mysqli_stmt_bind_param($st, 'i', $est_id);
mysqli_stmt_execute($st);
$est = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

// ¿Es estudiante de inglés? (programa IDs 16-19)
$es_ingles = in_array((int)($est['programa_id'] ?? 0), [16,17,18,19]);

// Datos de inglés
$st2 = mysqli_prepare($conexion,
    "SELECT n.*, COUNT(s.id) as total_ejercicios,
            SUM(CASE WHEN s.es_correcto=1 THEN 1 ELSE 0 END) as total_correctos
     FROM idiomas_nivel n
     LEFT JOIN idiomas_sesiones s ON s.estudiante_id = n.estudiante_id AND s.es_quiz_nivel = 0
     WHERE n.estudiante_id = ?");
mysqli_stmt_bind_param($st2, 'i', $est_id);
mysqli_stmt_execute($st2);
$ing = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));

$tiene_perfil    = !empty($ing);
$quiz_completado = (int)($ing['quiz_completado'] ?? 0);
$nivel_actual    = $ing['nivel_actual'] ?? 'A1';
$xp_total        = (int)($ing['xp_total'] ?? 0);
$racha           = (int)($ing['racha_actual'] ?? 0);
$apodo           = $ing['apodo'] ?? '';
$total_ej        = (int)($ing['total_ejercicios'] ?? 0);
$total_ok        = (int)($ing['total_correctos'] ?? 0);
$precision       = $total_ej > 0 ? round($total_ok / $total_ej * 100) : 0;

// XP para barra de progreso
$xp_map = ['A1'=>[0,300],'A2'=>[300,700],'B1'=>[700,1200],'B2'=>[1200,2000]];
$next_map= ['A1'=>'A2','A2'=>'B1','B1'=>'B2','B2'=>'B2'];
$range   = $xp_map[$nivel_actual];
$xp_pct  = $nivel_actual === 'B2' ? 100 : min(100, round(($xp_total - $range[0]) / ($range[1] - $range[0]) * 100));
$xp_next = $range[1] - $xp_total;

// Preferencia de ejercicios por sesión
$ejercicios_sesion = (int)($ing['ejercicios_sesion'] ?? 15);
if (!in_array($ejercicios_sesion, [10,15,20])) $ejercicios_sesion = 15;

// Foto del estudiante
$foto_url = '';
if (!empty($est['foto']) && file_exists(__DIR__ . '/fotos/' . $est['foto'])) {
    $foto_url = '/intep/fotos/' . $est['foto'];
}
$inicial = strtoupper(mb_substr($est['nombre'] ?? 'E', 0, 1));

// Ranking — top 10
$ranking = [];
$r = mysqli_query($conexion,
    "SELECT n.apodo, n.nivel_actual, n.xp_total, n.racha_actual, n.estudiante_id,
            e.nombre, e.foto,
            SUM(CASE WHEN DATE(s.created_at) = CURDATE() AND s.es_quiz_nivel=0 THEN 1 ELSE 0 END) as hoy_ejercicios
     FROM idiomas_nivel n
     JOIN estudiantes e ON e.id = n.estudiante_id
     LEFT JOIN idiomas_sesiones s ON s.estudiante_id = n.estudiante_id
     WHERE n.quiz_completado = 1
     GROUP BY n.estudiante_id
     ORDER BY n.xp_total DESC
     LIMIT 10");
if ($r) $ranking = mysqli_fetch_all($r, MYSQLI_ASSOC);

// Mi posición en el ranking
$mi_pos = 0;
foreach ($ranking as $i => $row) {
    if ((int)$row['estudiante_id'] === $est_id) { $mi_pos = $i + 1; break; }
}

// Logros
$logros = [];
$r2 = mysqli_prepare($conexion, "SELECT logro_icon, logro_nombre FROM idiomas_logros WHERE estudiante_id = ? ORDER BY desbloqueado_at DESC");
mysqli_stmt_bind_param($r2, 'i', $est_id);
mysqli_stmt_execute($r2);
$logros = mysqli_fetch_all(mysqli_stmt_get_result($r2), MYSQLI_ASSOC);

date_default_timezone_set('America/Bogota');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>INTEP Inglés</title>
<link rel="stylesheet" href="/intep/css/estilos.css">
<link rel="icon" href="/intep/favicon/favicon.svg" type="image/svg+xml">
<style>
/* ════════════════════════════════════════
   INTEP INGLÉS — Estilos del módulo
════════════════════════════════════════ */
:root {
  --ing-dark:    #0A1628;
  --ing-dark2:   #0F2040;
  --ing-dark3:   #162850;
  --ing-card:    #1A3060;
  --ing-card2:   #203570;
  --ing-azul:    #3B82F6;
  --ing-azul2:   #1D4ED8;
  --ing-gold:    #F5C842;
  --ing-verde:   #10B981;
  --ing-rojo:    #EF4444;
  --ing-text:    #E2E8F0;
  --ing-soft:    #64748B;
  --ing-border:  rgba(59,130,246,0.2);
}

* { box-sizing: border-box; }

body {
  background: var(--ing-dark);
  color: var(--ing-text);
  font-family: 'Nunito', 'Segoe UI', system-ui, sans-serif;
  min-height: 100vh;
  margin: 0;
}

/* ── HEADER ── */
.ing-header {
  background: linear-gradient(160deg, var(--ing-dark2) 0%, var(--ing-dark3) 100%);
  padding: 16px 20px 20px;
  position: sticky; top: 0; z-index: 100;
  border-bottom: 1px solid var(--ing-border);
}
.ing-header-top {
  display: flex; align-items: center; gap: 12px;
}
.ing-back {
  width: 38px; height: 38px;
  background: rgba(255,255,255,0.06);
  border: 1px solid var(--ing-border);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--ing-text); text-decoration: none; font-size: 18px;
  flex-shrink: 0;
}
.ing-logo { font-size: 22px; font-weight: 900; flex: 1; }
.ing-logo span { background: linear-gradient(90deg,#60A5FA,#93C5FD); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }

/* Avatar pequeño en header */
.ing-avatar-sm {
  width: 38px; height: 38px; border-radius: 50%;
  background: linear-gradient(135deg, var(--ing-azul2), var(--ing-azul));
  display: flex; align-items: center; justify-content: center;
  font-weight: 900; font-size: 16px; flex-shrink: 0;
  overflow: hidden; border: 2px solid var(--ing-azul);
}
.ing-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }

/* ── TABS NAV ── */
.ing-tabs {
  display: flex; gap: 4px; margin-top: 14px;
  background: rgba(0,0,0,0.25); border-radius: 12px; padding: 4px;
}
.ing-tab {
  flex: 1; padding: 8px 4px;
  background: transparent; border: none;
  color: var(--ing-soft); font-family: inherit;
  font-size: 12px; font-weight: 700; cursor: pointer;
  border-radius: 8px; transition: all .2s;
  display: flex; align-items: center; justify-content: center; gap: 4px;
}
.ing-tab.active { background: var(--ing-azul); color: white; }

/* ── PANTALLAS ── */
.ing-screen { display: none; padding: 20px; }
.ing-screen.active { display: block; }

/* ── CARDS ── */
.ing-card {
  background: var(--ing-card); border-radius: 18px;
  border: 1px solid var(--ing-border); padding: 18px;
  margin-bottom: 14px;
}

/* ── LEVEL CARD ── */
.level-hero {
  background: linear-gradient(135deg, var(--ing-azul2) 0%, var(--ing-azul) 100%);
  border-radius: 20px; padding: 20px; margin-bottom: 14px;
  position: relative; overflow: hidden;
}
.level-hero::after {
  content: attr(data-nivel);
  position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
  font-size: 52px; font-weight: 900; opacity: .12; color: white;
}
.level-hero-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.level-tag {
  background: rgba(255,255,255,0.18); padding: 4px 12px; border-radius: 50px;
  font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
}
.streak-pill {
  display: flex; align-items: center; gap: 4px;
  background: rgba(245,200,66,0.2); border: 1px solid rgba(245,200,66,0.35);
  padding: 4px 12px; border-radius: 50px; color: var(--ing-gold);
  font-size: 13px; font-weight: 800;
}
.level-xp { font-size: 30px; font-weight: 900; }
.level-sub { font-size: 12px; opacity: .75; margin-bottom: 12px; }
.xp-bar { background: rgba(255,255,255,0.2); border-radius: 50px; height: 8px; overflow: hidden; }
.xp-bar-fill { height: 100%; background: var(--ing-gold); border-radius: 50px; transition: width 1s ease; }
.xp-labels { display: flex; justify-content: space-between; font-size: 11px; opacity: .7; margin-top: 4px; }

/* ── STATS GRID ── */
.stats-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 14px; }
.stat-box {
  background: var(--ing-card); border: 1px solid var(--ing-border);
  border-radius: 14px; padding: 14px 8px; text-align: center;
}
.stat-icon2 { font-size: 20px; margin-bottom: 4px; }
.stat-val { font-size: 20px; font-weight: 900; }
.stat-lbl2 { font-size: 10px; color: var(--ing-soft); font-weight: 700; }

/* ── BTN PRACTICAR ── */
.btn-practicar {
  width: 100%; padding: 18px; border: none; border-radius: 16px;
  background: linear-gradient(135deg, var(--ing-azul), var(--ing-azul2));
  color: white; font-family: inherit; font-size: 17px; font-weight: 800;
  cursor: pointer; box-shadow: 0 8px 24px rgba(59,130,246,0.4);
  transition: all .2s; margin-bottom: 10px;
}
.btn-practicar:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(59,130,246,0.5); }
.btn-ranking-sm {
  width: 100%; padding: 14px 20px; border: 1px solid var(--ing-border);
  border-radius: 16px; background: var(--ing-card); color: var(--ing-text);
  font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer;
  display: flex; justify-content: space-between; align-items: center;
  transition: background .2s;
}
.btn-ranking-sm:hover { background: var(--ing-card2); }

/* ── EJERCICIO ── */
.ej-header {
  display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
}
.hearts { display: flex; gap: 3px; }
.heart { font-size: 18px; }
.heart.lost { opacity: .2; filter: grayscale(1); }
.prog-bar { flex: 1; background: rgba(255,255,255,0.1); border-radius: 50px; height: 9px; overflow: hidden; }
.prog-bar-fill { height: 100%; background: linear-gradient(90deg, var(--ing-azul), var(--ing-verde)); border-radius: 50px; transition: width .4s ease; }
.ej-counter { font-size: 12px; color: var(--ing-soft); font-weight: 700; white-space: nowrap; }

.tipo-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.25);
  color: var(--ing-azul); padding: 5px 14px; border-radius: 50px;
  font-size: 11px; font-weight: 700; margin-bottom: 14px;
  text-transform: uppercase; letter-spacing: .5px;
}

.ej-instruccion { font-size: 12px; color: var(--ing-soft); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.ej-pregunta { font-size: 20px; font-weight: 800; line-height: 1.4; margin-bottom: 6px; }
.ej-traduccion { font-size: 13px; color: var(--ing-soft); font-style: italic; margin-bottom: 22px; }

.opciones-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.opcion-btn {
  padding: 16px 10px; background: var(--ing-card2);
  border: 2px solid var(--ing-border); border-radius: 14px;
  color: var(--ing-text); font-family: inherit; font-size: 15px; font-weight: 700;
  cursor: pointer; text-align: center; transition: all .15s;
}
.opcion-btn:hover:not(:disabled) { border-color: var(--ing-azul); transform: scale(1.02); }
.opcion-btn.correcta { background: rgba(16,185,129,.15); border-color: var(--ing-verde); color: var(--ing-verde); }
.opcion-btn.incorrecta { background: rgba(239,68,68,.12); border-color: var(--ing-rojo); color: var(--ing-rojo); }

/* Traducción input */
.traduccion-wrap { margin-bottom: 16px; }
.traduccion-input {
  width: 100%; padding: 16px; background: var(--ing-card2);
  border: 2px solid var(--ing-border); border-radius: 14px;
  color: var(--ing-text); font-family: inherit; font-size: 16px;
  outline: none; transition: border-color .2s; resize: none;
}
.traduccion-input:focus { border-color: var(--ing-azul); }
.btn-comprobar {
  width: 100%; padding: 14px; border: none; border-radius: 14px;
  background: var(--ing-azul); color: white; font-family: inherit;
  font-size: 15px; font-weight: 800; cursor: pointer; margin-bottom: 14px;
}

/* Feedback */
.feedback {
  border-radius: 14px; padding: 14px 16px;
  display: flex; gap: 12px; align-items: flex-start;
  margin-bottom: 14px; opacity: 0; transform: translateY(8px);
  transition: all .3s; pointer-events: none;
}
.feedback.show { opacity: 1; transform: translateY(0); pointer-events: auto; }
.feedback.ok  { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); }
.feedback.mal { background: rgba(239,68,68,.1);   border: 1px solid rgba(239,68,68,.3); }
.fb-icon { font-size: 22px; }
.fb-titulo { font-size: 14px; font-weight: 800; margin-bottom: 2px; }
.fb-titulo.ok  { color: var(--ing-verde); }
.fb-titulo.mal { color: var(--ing-rojo); }
.fb-texto { font-size: 12px; color: var(--ing-soft); }

.btn-siguiente {
  width: 100%; padding: 15px; border: none; border-radius: 14px;
  background: var(--ing-verde); color: white; font-family: inherit;
  font-size: 16px; font-weight: 800; cursor: pointer;
  opacity: 0; transition: opacity .3s; pointer-events: none;
}
.btn-siguiente.show { opacity: 1; pointer-events: auto; }
.btn-siguiente:hover { background: #059669; }

/* Cargando */
.cargando {
  text-align: center; padding: 40px 20px;
  display: none;
}
.cargando.show { display: block; }
.spinner {
  width: 44px; height: 44px; border-radius: 50%;
  border: 3px solid rgba(59,130,246,0.2);
  border-top-color: var(--ing-azul);
  animation: spin .8s linear infinite; margin: 0 auto 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── PANTALLA FIN DE SESIÓN ── */
.finish-wrap {
  display: none; flex-direction: column; align-items: center;
  padding: 30px 20px; text-align: center;
}
.finish-wrap.show { display: flex; }
.confetti-canvas { position: fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:50; }
.finish-trophy { font-size: 64px; margin-bottom: 8px; animation: pop .5s cubic-bezier(.34,1.56,.64,1) both; }
@keyframes pop { from { transform: scale(0); opacity:0; } to { transform: scale(1); opacity:1; } }
.finish-titulo { font-size: 24px; font-weight: 900; margin-bottom: 4px; }
.finish-sub { font-size: 13px; color: var(--ing-soft); margin-bottom: 24px; }
.finish-streak {
  background: rgba(245,200,66,.12); border: 1px solid rgba(245,200,66,.3);
  border-radius: 18px; padding: 16px 20px; width: 100%; margin-bottom: 14px;
  display: flex; align-items: center; gap: 14px;
  animation: subir .4s .2s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes subir { from { transform: translateY(24px); opacity:0; } to { transform: translateY(0); opacity:1; } }
.finish-stats-row {
  display: grid; grid-template-columns: repeat(3,1fr); gap: 10px;
  width: 100%; margin-bottom: 14px;
  animation: subir .4s .3s cubic-bezier(.34,1.56,.64,1) both;
}
.finish-stat {
  background: var(--ing-card); border: 1px solid var(--ing-border);
  border-radius: 14px; padding: 14px 8px; text-align: center;
}
.xp-ganados {
  background: linear-gradient(135deg, var(--ing-azul2), var(--ing-azul));
  border-radius: 14px; padding: 14px 18px; width: 100%;
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px;
  animation: subir .4s .4s cubic-bezier(.34,1.56,.64,1) both;
}

/* ── RANKING ── */
.podio-wrap {
  display: flex; align-items: flex-end; justify-content: center;
  gap: 10px; margin-bottom: 20px;
}
.podio-item { text-align: center; }
.podio-av {
  width: 50px; height: 50px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 900; margin: 0 auto 6px;
  border: 3px solid transparent; overflow: hidden;
}
.podio-av img { width:100%; height:100%; object-fit:cover; }
.podio-av.oro    { background: var(--ing-gold); border-color: var(--ing-gold); color: #111; box-shadow: 0 0 18px rgba(245,200,66,.4); }
.podio-av.plata  { background: #CBD5E1; border-color: #CBD5E1; color: #111; }
.podio-av.bronce { background: #CD7C3E; border-color: #CD7C3E; color: #111; }
.podio-nombre { font-size: 11px; font-weight: 700; }
.podio-xp { font-size: 10px; color: var(--ing-soft); }
.podio-bloque { border-radius: 8px 8px 0 0; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 900; color: rgba(255,255,255,.2); }
.p1 .podio-bloque { background: rgba(245,200,66,.15); height: 64px; width: 76px; }
.p2 .podio-bloque { background: rgba(203,213,225,.1);  height: 46px; width: 66px; }
.p3 .podio-bloque { background: rgba(205,124,62,.12); height: 32px; width: 66px; }

.rank-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; background: var(--ing-card);
  border-radius: 14px; margin-bottom: 8px; border: 1px solid var(--ing-border);
}
.rank-item.yo { background: rgba(59,130,246,.1); border-color: rgba(59,130,246,.4); }
.rank-num { font-size: 14px; font-weight: 900; color: var(--ing-soft); width: 22px; }
.rank-av {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, var(--ing-azul2), var(--ing-azul));
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; font-weight: 800; flex-shrink: 0; overflow: hidden;
}
.rank-av img { width:100%; height:100%; object-fit:cover; }
.rank-info { flex: 1; }
.rank-nick { font-size: 13px; font-weight: 800; }
.rank-det { font-size: 11px; color: var(--ing-soft); margin-top: 1px; }
.nivel-badge {
  display: inline-block; padding: 2px 7px; border-radius: 50px;
  font-size: 10px; font-weight: 700; margin-left: 5px;
}
.nb-a1 { background: rgba(245,200,66,.18); color: var(--ing-gold); }
.nb-a2 { background: rgba(16,185,129,.18); color: var(--ing-verde); }
.nb-b1 { background: rgba(59,130,246,.18); color: var(--ing-azul); }
.nb-b2 { background: rgba(168,85,247,.18); color: #A855F7; }
.rank-xp { font-size: 13px; font-weight: 800; color: var(--ing-gold); }

/* ── QUIZ DE NIVEL ── */
.quiz-wrap { padding: 20px; }
.quiz-dots { display: flex; gap: 4px; margin-bottom: 20px; }
.qdot { flex: 1; height: 5px; border-radius: 50px; background: rgba(255,255,255,.1); transition: background .3s; }
.qdot.done    { background: var(--ing-azul); }
.qdot.current { background: var(--ing-gold); }

/* ── AMIGOS ── */
.friend-card2 {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; background: var(--ing-card);
  border-radius: 14px; margin-bottom: 8px; border: 1px solid var(--ing-border);
  position: relative; overflow: hidden;
}
.friend-card2.activo { border-color: rgba(16,185,129,.35); }
.friend-card2.inactivo { opacity: .55; }
.friend-av2 {
  width: 44px; height: 44px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; font-weight: 800; flex-shrink: 0; overflow: hidden;
  position: relative;
}
.friend-av2 img { width:100%; height:100%; object-fit:cover; }
.friend-badge2 {
  position: absolute; bottom:-2px; right:-2px;
  width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid var(--ing-dark); display: flex; align-items: center; justify-content: center;
  font-size: 9px;
}
.fb2-ok   { background: var(--ing-verde); }
.fb2-no   { background: var(--ing-soft); }
.friend-name2 { font-size: 13px; font-weight: 800; }
.friend-det2  { font-size: 11px; color: var(--ing-soft); margin-top: 1px; }
.friend-streak2 { margin-left: auto; text-align: right; }
.fs-big { font-size: 17px; font-weight: 900; color: var(--ing-gold); }
.fs-lbl { font-size: 10px; color: var(--ing-soft); }
.friend-bar { position: absolute; bottom:0; left:0; height:3px; background: var(--ing-verde); opacity:.35; border-radius:0 2px 0 0; }

/* ── SELECTOR EJERCICIOS ── */
.sel-wrap {
  min-height: 75vh; display: flex; flex-direction: column;
  align-items: center; justify-content: center; padding: 30px 20px; text-align: center;
}
.nivel-result {
  background: linear-gradient(135deg, var(--ing-azul2), var(--ing-azul));
  border-radius: 20px; padding: 20px 24px; width: 100%; margin-bottom: 24px;
  animation: subir .5s cubic-bezier(.34,1.56,.64,1) both;
}
.sel-opciones { display: flex; gap: 12px; width: 100%; margin-bottom: 20px; }
.sel-opt {
  flex: 1; padding: 20px 8px;
  background: var(--ing-card); border: 2px solid var(--ing-border);
  border-radius: 18px; color: var(--ing-text); font-family: inherit;
  font-size: 22px; font-weight: 900; cursor: pointer;
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  transition: all .2s;
}
.sel-opt span { font-size: 11px; font-weight: 700; color: var(--ing-soft); }
.sel-opt:hover, .sel-opt.selected {
  border-color: var(--ing-azul);
  background: rgba(59,130,246,.15);
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(59,130,246,.25);
}
.sel-opt.selected span { color: var(--ing-azul); }

/* ── ONBOARDING (apodo) ── */
.onboard-wrap {
  min-height: 80vh; display: flex; flex-direction: column;
  align-items: center; justify-content: center; padding: 30px 20px; text-align: center;
}
.onboard-icon { font-size: 60px; margin-bottom: 16px; }
.onboard-titulo { font-size: 24px; font-weight: 900; margin-bottom: 8px; }
.onboard-sub { font-size: 14px; color: var(--ing-soft); margin-bottom: 28px; line-height: 1.6; }
.apodo-input {
  width: 100%; padding: 16px; background: var(--ing-card2);
  border: 2px solid var(--ing-border); border-radius: 14px;
  color: var(--ing-text); font-family: inherit; font-size: 18px;
  font-weight: 700; text-align: center; outline: none; margin-bottom: 14px;
  transition: border-color .2s;
}
.apodo-input:focus { border-color: var(--ing-azul); }
.apodo-hint { font-size: 11px; color: var(--ing-soft); margin-bottom: 20px; }

/* ── LOGROS ── */
.logro-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; background: var(--ing-card);
  border-radius: 12px; margin-bottom: 8px; border: 1px solid var(--ing-border);
}
.logro-icon { font-size: 26px; }
.logro-nombre { font-size: 13px; font-weight: 700; }

/* ── MISC ── */
.seccion-label2 {
  font-size: 10px; letter-spacing: 2px; text-transform: uppercase;
  color: var(--ing-soft); font-weight: 700; margin-bottom: 10px;
}
.mi-pos-card {
  background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.3);
  border-radius: 14px; padding: 12px 16px; margin-bottom: 14px;
  display: flex; align-items: center; justify-content: space-between;
}

/* Responsive */
@media (max-width: 400px) {
  .opciones-grid { grid-template-columns: 1fr; }
  .ej-pregunta { font-size: 18px; }
}

/* Loading dots animation */
.dot-loader { display: inline-flex; gap: 4px; align-items: center; }
.dot-loader span {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--ing-azul); opacity: 0;
  animation: dotblink .8s infinite;
}
.dot-loader span:nth-child(2) { animation-delay: .15s; }
.dot-loader span:nth-child(3) { animation-delay: .3s; }
@keyframes dotblink { 0%,80%,100% { opacity:.1; } 40% { opacity:1; } }

/* ════════════════════════════════════════
   MY TEACHER GUS — Estilos (solo inglés)
════════════════════════════════════════ */

/* Avatar flotante */
.gus-bubble {
  position: fixed; bottom: 24px; right: 20px; z-index: 9000;
  cursor: pointer; user-select: none;
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  animation: gusBounce 2.8s ease-in-out infinite;
}
@keyframes gusBounce {
  0%,100% { transform: translateY(0); }
  50%      { transform: translateY(-8px); }
}
.gus-bubble:hover { animation: none; transform: scale(1.07); }

.gus-label {
  background: linear-gradient(135deg,#1D4ED8,#3B82F6);
  color: white; font-size: 10px; font-weight: 900; letter-spacing: .3px;
  padding: 3px 10px; border-radius: 50px;
  box-shadow: 0 2px 10px rgba(59,130,246,.5);
  white-space: nowrap;
}

.gus-avatar-wrap {
  width: 70px; height: 70px; position: relative;
}

/* Burbuja de texto ocasional */
.gus-callout {
  position: absolute; bottom: 78px; right: 20px; z-index: 8999;
  background: white; color: #1e293b; font-size: 12px; font-weight: 700;
  padding: 8px 12px; border-radius: 14px 14px 4px 14px;
  box-shadow: 0 4px 20px rgba(0,0,0,.25);
  max-width: 160px; text-align: center; line-height: 1.4;
  opacity: 0; pointer-events: none;
  transition: opacity .3s ease;
}
.gus-callout.visible { opacity: 1; }
.gus-callout::after {
  content: ''; position: absolute; bottom: -8px; right: 18px;
  border: 8px solid transparent; border-top-color: white; border-bottom: none;
}

/* ── Modal GUS ── */
.gus-modal-bg {
  position: fixed; inset: 0; z-index: 9500;
  background: rgba(5,10,20,.85); backdrop-filter: blur(6px);
  display: none; align-items: flex-end; justify-content: center;
}
.gus-modal-bg.open { display: flex; }

.gus-modal {
  background: linear-gradient(170deg, #0F2040 0%, #0A1628 100%);
  border: 1px solid rgba(59,130,246,.25);
  border-radius: 28px 28px 0 0;
  width: 100%; max-width: 480px;
  height: 88vh; max-height: 700px;
  display: flex; flex-direction: column;
  overflow: hidden;
  animation: slideUp .35s cubic-bezier(.22,1,.36,1);
}
@keyframes slideUp {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0);   opacity: 1; }
}

/* Header del modal */
.gus-modal-head {
  display: flex; align-items: center; gap: 12px;
  padding: 16px 18px; border-bottom: 1px solid rgba(59,130,246,.15);
  flex-shrink: 0;
}
.gus-head-av {
  width: 46px; height: 46px; flex-shrink: 0;
}
.gus-head-info { flex: 1; }
.gus-head-name { font-size: 15px; font-weight: 900; color: white; }
.gus-head-sub  { font-size: 11px; color: rgba(255,255,255,.5); font-weight: 600; }
.gus-head-close {
  width: 34px; height: 34px; border-radius: 50%;
  background: rgba(255,255,255,.07); border: none;
  color: white; font-size: 18px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}

/* Tema actual */
.gus-topic-bar {
  padding: 8px 18px;
  background: rgba(59,130,246,.08);
  border-bottom: 1px solid rgba(59,130,246,.1);
  font-size: 11px; color: rgba(255,255,255,.55);
  font-weight: 600; flex-shrink: 0;
}
.gus-topic-bar span { color: #60A5FA; font-weight: 800; }

/* Chat */
.gus-chat {
  flex: 1; overflow-y: auto; padding: 16px;
  display: flex; flex-direction: column; gap: 10px;
}
.gus-chat::-webkit-scrollbar { width: 4px; }
.gus-chat::-webkit-scrollbar-thumb { background: rgba(59,130,246,.3); border-radius: 4px; }

.gus-msg {
  max-width: 85%; border-radius: 18px; padding: 10px 14px;
  font-size: 14px; line-height: 1.5; animation: msgIn .25s ease both;
}
@keyframes msgIn {
  from { opacity:0; transform: translateY(8px); }
  to   { opacity:1; transform: translateY(0); }
}
.gus-msg.gus    { background: rgba(59,130,246,.15); border: 1px solid rgba(59,130,246,.25); color: var(--ing-text); align-self: flex-start; border-bottom-left-radius: 4px; }
.gus-msg.user   { background: var(--ing-azul); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
.gus-msg.system { background: rgba(245,200,66,.1); border: 1px solid rgba(245,200,66,.25); color: var(--ing-gold); align-self: center; text-align: center; font-size: 12px; font-weight: 700; max-width: 100%; }

/* Indicador "GUS está escribiendo" */
.gus-typing {
  align-self: flex-start;
  background: rgba(59,130,246,.12); border: 1px solid rgba(59,130,246,.2);
  border-radius: 18px 18px 18px 4px; padding: 10px 16px;
  display: none;
}
.gus-typing.show { display: flex; align-items: center; gap: 4px; }
.gus-typing span {
  width: 7px; height: 7px; border-radius: 50%; background: var(--ing-azul);
  animation: typingDot .9s infinite;
}
.gus-typing span:nth-child(2) { animation-delay: .15s; }
.gus-typing span:nth-child(3) { animation-delay: .30s; }
@keyframes typingDot { 0%,60%,100%{opacity:.2;transform:scale(.8)} 30%{opacity:1;transform:scale(1)} }

/* XP ganada al completar */
.gus-xp-banner {
  background: linear-gradient(135deg,#F5C842,#F59E0B);
  color: #1e293b; font-weight: 900; font-size: 15px;
  text-align: center; padding: 12px; flex-shrink: 0;
  display: none;
}
.gus-xp-banner.show { display: block; }

/* Input area */
.gus-input-area {
  padding: 12px 14px; border-top: 1px solid rgba(59,130,246,.15);
  display: flex; align-items: flex-end; gap: 8px; flex-shrink: 0;
}
.gus-input {
  flex: 1; background: rgba(255,255,255,.06); border: 1px solid rgba(59,130,246,.2);
  border-radius: 16px; color: white; font-family: inherit; font-size: 14px;
  padding: 10px 14px; resize: none; max-height: 100px; outline: none;
  line-height: 1.4;
}
.gus-input::placeholder { color: rgba(255,255,255,.3); }
.gus-input:focus { border-color: rgba(59,130,246,.5); }

.gus-btn-send {
  width: 44px; height: 44px; border-radius: 50%; border: none;
  background: var(--ing-azul); color: white; font-size: 18px;
  cursor: pointer; flex-shrink: 0; transition: all .2s;
  display: flex; align-items: center; justify-content: center;
}
.gus-btn-send:hover { background: var(--ing-azul2); }
.gus-btn-send:disabled { opacity: .4; cursor: not-allowed; }

.gus-btn-mic {
  width: 44px; height: 44px; border-radius: 50%; border: none;
  background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
  color: white; font-size: 18px; cursor: pointer; flex-shrink: 0;
  transition: all .2s; display: flex; align-items: center; justify-content: center;
}
.gus-btn-mic.listening {
  background: rgba(239,68,68,.2); border-color: rgba(239,68,68,.5);
  animation: micPulse 1s ease-in-out infinite;
}
@keyframes micPulse { 0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)} 50%{box-shadow:0 0 0 8px rgba(239,68,68,0)} }

/* Barra de historial de lecciones */
.gus-history-btn {
  background: none; border: none; color: rgba(255,255,255,.4);
  font-size: 11px; font-weight: 700; cursor: pointer;
  padding: 0; display: flex; align-items: center; gap: 4px;
}
.gus-history-btn:hover { color: rgba(255,255,255,.7); }

/* Historial lecciones panel */
.gus-hist-panel {
  background: linear-gradient(170deg, #0F2040 0%, #0A1628 100%);
  border-top: 1px solid rgba(59,130,246,.15);
  padding: 12px 16px; flex-shrink: 0;
  max-height: 160px; overflow-y: auto; display: none;
}
.gus-hist-panel.open { display: block; }
.gus-hist-item {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 0; border-bottom: 1px solid rgba(59,130,246,.08);
  font-size: 12px;
}
.gus-hist-item:last-child { border-bottom: none; }
.gus-hist-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
  background: var(--ing-verde);
}
.gus-hist-dot.pending { background: var(--ing-azul); }

/* Celebration overlay */
.gus-celebrate {
  position: absolute; inset: 0; z-index: 10;
  background: rgba(5,10,20,.92);
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 12px;
  opacity: 0; pointer-events: none;
  transition: opacity .4s ease;
  border-radius: 28px 28px 0 0;
}
.gus-celebrate.show { opacity: 1; pointer-events: all; }
.gus-cel-trophy { font-size: 64px; animation: bounce .6s ease infinite alternate; }
@keyframes bounce { from{transform:scale(.9)} to{transform:scale(1.05)} }
.gus-cel-title { font-size: 22px; font-weight: 900; color: white; text-align: center; }
.gus-cel-xp    { font-size: 32px; font-weight: 900; color: var(--ing-gold); }
.gus-cel-sub   { font-size: 13px; color: rgba(255,255,255,.6); text-align: center; max-width: 240px; }
.gus-cel-btn {
  padding: 12px 28px; border-radius: 14px; border: none;
  background: var(--ing-azul); color: white; font-family: inherit;
  font-size: 15px; font-weight: 800; cursor: pointer; margin-top: 8px;
}

/* ══ VOICE MODE ══════════════════════════════ */
.gus-voice-toggle {
  width:34px;height:34px;border-radius:50%;
  background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);
  color:white;font-size:15px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all .2s;
}
.gus-voice-toggle:hover{background:rgba(59,130,246,.3);}
.gus-voice-toggle.vactive{background:#EF4444;border-color:#EF4444;animation:micPulse 1.2s ease-in-out infinite;}

.gus-voice-panel {
  position:absolute;left:0;right:0;bottom:0;top:74px;
  background:linear-gradient(180deg,#050A14 0%,#080F20 100%);
  z-index:16;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:14px;
  padding:16px 24px 20px;opacity:0;pointer-events:none;
  transition:opacity .3s ease;
}
.gus-voice-panel.vopen{opacity:1;pointer-events:all;}

.voice-txt-gus {
  min-height:54px;max-height:80px;overflow:hidden;
  text-align:center;font-size:14px;line-height:1.55;
  color:rgba(255,255,255,.88);font-weight:500;width:100%;
  transition:color .3s;
}
.voice-txt-user {
  min-height:22px;text-align:center;font-size:13px;
  color:rgba(255,255,255,.4);font-style:italic;width:100%;
}
.voice-topic-lbl {
  font-size:10px;color:rgba(255,255,255,.35);font-weight:700;
  text-transform:uppercase;letter-spacing:.5px;
}

/* Orb */
.gus-orb-wrap {
  position:relative;width:170px;height:170px;
  flex-shrink:0;cursor:pointer;user-select:none;
}
.orb-ring {
  position:absolute;border-radius:50%;
  top:50%;left:50%;transform:translate(-50%,-50%);
  border:2px solid transparent;pointer-events:none;
}
/* listening */
.gus-orb-wrap.listening .orb-ring-1{width:80px;height:80px;border-color:rgba(59,130,246,.9);animation:lisR 1.5s ease-out infinite;}
.gus-orb-wrap.listening .orb-ring-2{width:80px;height:80px;border-color:rgba(59,130,246,.5);animation:lisR 1.5s ease-out .3s infinite;}
.gus-orb-wrap.listening .orb-ring-3{width:80px;height:80px;border-color:rgba(59,130,246,.2);animation:lisR 1.5s ease-out .6s infinite;}
@keyframes lisR{0%{transform:translate(-50%,-50%) scale(.9);opacity:1}100%{transform:translate(-50%,-50%) scale(2.3);opacity:0}}
/* thinking */
.gus-orb-wrap.thinking .orb-ring-1{width:145px;height:145px;border-top-color:#F5C842;border-right-color:rgba(245,200,66,.3);animation:spinR .8s linear infinite;}
.gus-orb-wrap.thinking .orb-ring-2{width:125px;height:125px;border-bottom-color:#F59E0B;border-left-color:rgba(245,158,11,.3);animation:spinR 1.2s linear reverse infinite;}
.gus-orb-wrap.thinking .orb-ring-3{display:none;}
@keyframes spinR{to{transform:translate(-50%,-50%) rotate(360deg)}}
/* speaking */
.gus-orb-wrap.speaking .orb-ring-1{width:100px;height:100px;border-color:rgba(16,185,129,.7);animation:speakP .5s ease-in-out infinite alternate;}
.gus-orb-wrap.speaking .orb-ring-2{width:135px;height:135px;border-color:rgba(16,185,129,.35);animation:speakP .5s ease-in-out .15s infinite alternate;}
.gus-orb-wrap.speaking .orb-ring-3{width:165px;height:165px;border-color:rgba(16,185,129,.15);animation:speakP .5s ease-in-out .3s infinite alternate;}
@keyframes speakP{0%{transform:translate(-50%,-50%) scale(.93);opacity:.6}100%{transform:translate(-50%,-50%) scale(1.07);opacity:1}}

.orb-center {
  position:absolute;width:78px;height:78px;
  top:50%;left:50%;transform:translate(-50%,-50%);
  background:linear-gradient(135deg,#1D4ED8,#3B82F6);
  border-radius:50%;display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 35px rgba(59,130,246,.45);
  transition:background .3s,box-shadow .3s;
}
.gus-orb-wrap.thinking .orb-center{background:linear-gradient(135deg,#92400E,#D97706);box-shadow:0 0 35px rgba(245,200,66,.4);}
.gus-orb-wrap.speaking .orb-center{background:linear-gradient(135deg,#065F46,#10B981);box-shadow:0 0 35px rgba(16,185,129,.5);}

.orb-state-lbl{font-size:12px;font-weight:700;text-align:center;transition:color .3s;letter-spacing:.2px;}

/* Waveform */
.voice-wave{display:flex;align-items:center;gap:3px;height:22px;opacity:0;transition:opacity .3s;}
.gus-voice-panel.spk .voice-wave{opacity:1;}
.wbar{width:3px;border-radius:2px;background:#10B981;animation:wA .45s ease-in-out infinite alternate;}
.wbar:nth-child(1){height:6px;animation-delay:.00s}.wbar:nth-child(2){height:16px;animation-delay:.10s}
.wbar:nth-child(3){height:22px;animation-delay:.05s}.wbar:nth-child(4){height:13px;animation-delay:.15s}
.wbar:nth-child(5){height:19px;animation-delay:.08s}.wbar:nth-child(6){height:9px;animation-delay:.12s}
.wbar:nth-child(7){height:17px;animation-delay:.03s}
@keyframes wA{to{height:3px}}

.voice-btn-chat{
  padding:7px 18px;border-radius:50px;border:1px solid rgba(255,255,255,.15);
  background:rgba(255,255,255,.06);color:rgba(255,255,255,.55);
  font-family:inherit;font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;
}
.voice-btn-chat:hover{background:rgba(255,255,255,.12);color:white;}
</style>
</head>
<body>

<!-- HEADER -->
<div class="ing-header">
  <div class="ing-header-top">
    <a href="dashboard.php" class="ing-back">←</a>
    <div class="ing-logo">INTEP <span>Inglés</span></div>
    <div class="ing-avatar-sm">
      <?php if ($foto_url): ?>
        <img src="<?php echo htmlspecialchars($foto_url); ?>" alt="">
      <?php else: ?>
        <?php echo $inicial; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($tiene_perfil && $quiz_completado): ?>
  <div class="ing-tabs">
    <button class="ing-tab active" onclick="showTab('inicio')">🏠 Inicio</button>
    <button class="ing-tab" onclick="showTab('practicar')">✏️ Practicar</button>
    <button class="ing-tab" onclick="showTab('ranking')">🏆 Ranking</button>
    <button class="ing-tab" onclick="showTab('amigos')">🔥 Amigos</button>
  </div>
  <?php endif; ?>
</div>

<!-- ════════════════════ ONBOARDING: sin perfil ════════════════════ -->
<?php if (!$tiene_perfil || !$apodo): ?>
<div class="ing-screen active" id="screen-onboard">
  <div class="onboard-wrap">
    <div class="onboard-icon">🌐</div>
    <div class="onboard-titulo">¡Bienvenido a INTEP Inglés!</div>
    <div class="onboard-sub">
      Una IA te generará ejercicios personalizados, ganarás XP, escalarás niveles y competirás con tus compañeros.<br><br>
      Primero, ¿cómo quieres llamarte en el ranking?
    </div>
    <input type="text" class="apodo-input" id="apodo-input" maxlength="25"
           placeholder="Ej: CarlosRocks" autocomplete="off">
    <div class="apodo-hint">Solo letras, números y espacios. Máx. 25 caracteres.</div>
    <button class="btn-practicar" onclick="guardarApodo()">Continuar →</button>
    <div id="apodo-error" style="color:var(--ing-rojo);font-size:13px;margin-top:8px;display:none"></div>
  </div>
</div>

<!-- ════════════════════ QUIZ DE NIVEL ════════════════════ -->
<?php elseif (!$quiz_completado): ?>
<div class="ing-screen active" id="screen-quiz">
  <div class="quiz-wrap">
    <div style="text-align:center;margin-bottom:20px;">
      <div style="font-size:36px;margin-bottom:8px">🎯</div>
      <div style="font-size:20px;font-weight:900;margin-bottom:4px">Test de Nivel</div>
      <div style="font-size:13px;color:var(--ing-soft)">15 preguntas · Detectamos tu nivel A1 → B2</div>
    </div>
    <div class="quiz-dots" id="quiz-dots">
      <?php for ($i=0;$i<15;$i++): ?>
      <div class="qdot<?php echo $i===0?' current':''; ?>" id="qdot-<?php echo $i; ?>"></div>
      <?php endfor; ?>
    </div>
    <div id="quiz-content">
      <div style="font-size:11px;color:var(--ing-soft);font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px" id="quiz-num">Pregunta 1 de 15</div>
      <div style="font-size:19px;font-weight:800;margin-bottom:20px;line-height:1.4" id="quiz-pregunta"></div>
      <div id="quiz-opciones" style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px"></div>
      <div class="feedback" id="quiz-feedback">
        <div class="fb-icon" id="quiz-fb-icon"></div>
        <div><div class="fb-titulo" id="quiz-fb-titulo"></div><div class="fb-texto" id="quiz-fb-texto"></div></div>
      </div>
      <button class="btn-siguiente" id="quiz-next" onclick="siguienteQuiz()">Siguiente →</button>
    </div>
  </div>
</div>

<!-- ════════════════════ SELECTOR DE EJERCICIOS ════════════════════ -->
<div class="ing-screen" id="screen-selector" style="display:none">
  <div class="sel-wrap">
    <div style="font-size:52px;margin-bottom:12px;animation:pop .5s cubic-bezier(.34,1.56,.64,1) both">🎯</div>
    <div style="font-size:22px;font-weight:900;margin-bottom:6px">¡Nivel detectado!</div>
    <div style="font-size:13px;color:var(--ing-soft);margin-bottom:20px">Basado en tus respuestas, tu nivel es:</div>
    <div class="nivel-result">
      <div style="font-size:42px;font-weight:900;color:white" id="sel-nivel-big">A1</div>
      <div style="font-size:14px;opacity:.8" id="sel-nivel-nombre">Principiante</div>
    </div>
    <div style="font-size:15px;font-weight:800;margin-bottom:14px">¿Cuántos ejercicios quieres por sesión?</div>
    <div class="sel-opciones">
      <button class="sel-opt" onclick="seleccionarCantidad(10, this)">
        10 <span>~5 min</span>
      </button>
      <button class="sel-opt selected" onclick="seleccionarCantidad(15, this)">
        15 <span>~8 min</span>
      </button>
      <button class="sel-opt" onclick="seleccionarCantidad(20, this)">
        20 <span>~12 min</span>
      </button>
    </div>
    <div style="font-size:12px;color:var(--ing-soft);margin-bottom:20px">Puedes cambiar esto después desde tu perfil</div>
    <button class="btn-practicar" id="sel-btn-comenzar" onclick="confirmarSelector()">
      ¡Comenzar a practicar! →
    </button>
  </div>
</div>

<!-- ════════════════════ PORTAL PRINCIPAL ════════════════════ -->
<?php else: ?>

<!-- TAB: INICIO -->
<div class="ing-screen active" id="screen-inicio">

  <!-- Level hero -->
  <div class="level-hero" data-nivel="<?php echo $nivel_actual; ?>">
    <div class="level-hero-top">
      <div class="level-tag">📚 <?php echo $nivel_actual; ?> · <?php
        $nombres = ['A1'=>'Principiante','A2'=>'Elemental','B1'=>'Intermedio','B2'=>'Intermedio alto'];
        echo $nombres[$nivel_actual];
      ?></div>
      <div class="streak-pill">🔥 <?php echo $racha; ?> días</div>
    </div>
    <div class="level-xp"><?php echo $xp_total; ?> XP</div>
    <div class="level-sub">
      <?php if ($nivel_actual !== 'B2'): ?>
        Próximo nivel <?php echo $next_map[$nivel_actual]; ?> — faltan <?php echo max(0,$xp_next); ?> XP
      <?php else: ?>
        ¡Nivel máximo alcanzado! 🏆
      <?php endif; ?>
    </div>
    <div class="xp-bar"><div class="xp-bar-fill" style="width:<?php echo $xp_pct; ?>%"></div></div>
    <div class="xp-labels">
      <span><?php echo $nivel_actual; ?></span>
      <span><?php echo $xp_total; ?>/<?php echo $range[1]; ?></span>
      <span><?php echo $next_map[$nivel_actual]; ?></span>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-3">
    <div class="stat-box">
      <div class="stat-icon2">✅</div>
      <div class="stat-val"><?php echo $total_ej; ?></div>
      <div class="stat-lbl2">Ejercicios</div>
    </div>
    <div class="stat-box">
      <div class="stat-icon2">🎯</div>
      <div class="stat-val"><?php echo $precision; ?>%</div>
      <div class="stat-lbl2">Precisión</div>
    </div>
    <div class="stat-box">
      <div class="stat-icon2">🏆</div>
      <div class="stat-val"><?php echo $mi_pos ?: '—'; ?></div>
      <div class="stat-lbl2">Posición</div>
    </div>
  </div>

  <!-- Botones -->
  <button class="btn-practicar" onclick="showTab('practicar');iniciarSesion()">⚡ Practicar ahora</button>
  <button class="btn-ranking-sm" onclick="showTab('ranking')">
    <span>🏆 Ver ranking completo</span>
    <span style="color:var(--ing-azul);font-weight:800">#<?php echo $mi_pos ?: '—'; ?> →</span>
  </button>

  <!-- Logros -->
  <?php if (!empty($logros)): ?>
  <div style="margin-top:18px">
    <div class="seccion-label2">Mis logros</div>
    <?php foreach ($logros as $l): ?>
    <div class="logro-item">
      <div class="logro-icon"><?php echo $l['logro_icon']; ?></div>
      <div class="logro-nombre"><?php echo htmlspecialchars($l['logro_nombre']); ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- /screen-inicio -->

<!-- TAB: PRACTICAR -->
<div class="ing-screen" id="screen-practicar">

  <!-- Cargando -->
  <div class="cargando" id="ej-cargando">
    <div class="spinner"></div>
    <div style="color:var(--ing-soft);font-size:14px">Generando tu ejercicio <div class="dot-loader"><span></span><span></span><span></span></div></div>
  </div>

  <!-- Ejercicio -->
  <div id="ej-wrap" style="display:none">
    <div class="ej-header">
      <div class="hearts" id="hearts-wrap"></div>
      <div class="prog-bar"><div class="prog-bar-fill" id="prog-fill" style="width:0%"></div></div>
      <div class="ej-counter" id="ej-counter">0/15</div>
    </div>
    <div class="tipo-badge" id="ej-tipo-badge">✍️ Ejercicio</div>
    <div class="ej-instruccion" id="ej-instruccion"></div>
    <div class="ej-pregunta" id="ej-pregunta"></div>
    <div class="ej-traduccion" id="ej-traduccion"></div>
    <!-- opciones múltiple -->
    <div class="opciones-grid" id="opciones-wrap"></div>
    <!-- traducción libre -->
    <div class="traduccion-wrap" id="traduccion-wrap" style="display:none">
      <textarea class="traduccion-input" id="traduccion-input" rows="3" placeholder="Escribe tu traducción en inglés..."></textarea>
      <button class="btn-comprobar" id="btn-comprobar" onclick="comprobarTraduccion()">Comprobar ✓</button>
    </div>
    <!-- feedback -->
    <div class="feedback" id="feedback">
      <div class="fb-icon" id="fb-icon"></div>
      <div>
        <div class="fb-titulo" id="fb-titulo"></div>
        <div class="fb-texto" id="fb-texto"></div>
      </div>
    </div>
    <button class="btn-siguiente" id="btn-siguiente" onclick="siguienteEjercicio()">Continuar →</button>
  </div>

  <!-- Fin de sesión -->
  <div class="finish-wrap" id="finish-wrap">
    <canvas class="confetti-canvas" id="confetti-canvas"></canvas>
    <div class="finish-trophy">🏆</div>
    <div class="finish-titulo">¡Sesión completada!</div>
    <div class="finish-sub" id="finish-sub">Racha del día guardada</div>
    <div class="finish-streak">
      <div style="font-size:36px">🔥</div>
      <div>
        <div style="font-size:28px;font-weight:900;color:var(--ing-gold)" id="finish-racha">0</div>
        <div style="font-size:12px;color:var(--ing-soft);font-weight:700">días seguidos</div>
      </div>
    </div>
    <div class="finish-stats-row">
      <div class="finish-stat">
        <div style="font-size:18px">✅</div>
        <div style="font-size:18px;font-weight:900;color:var(--ing-verde)" id="finish-ok">0</div>
        <div style="font-size:10px;color:var(--ing-soft);font-weight:700">Correctas</div>
      </div>
      <div class="finish-stat">
        <div style="font-size:18px">⚡</div>
        <div style="font-size:18px;font-weight:900;color:var(--ing-azul)" id="finish-pct">0%</div>
        <div style="font-size:10px;color:var(--ing-soft);font-weight:700">Precisión</div>
      </div>
      <div class="finish-stat">
        <div style="font-size:18px">⭐</div>
        <div style="font-size:18px;font-weight:900;color:var(--ing-gold)" id="finish-xp">+0</div>
        <div style="font-size:10px;color:var(--ing-soft);font-weight:700">XP ganados</div>
      </div>
    </div>
    <button class="btn-practicar" style="margin-top:8px" onclick="reiniciarSesion()">🔄 Practicar de nuevo</button>
    <button class="btn-ranking-sm" style="margin-top:8px" onclick="showTab('inicio')">← Volver al inicio</button>
  </div>

</div><!-- /screen-practicar -->

<!-- TAB: RANKING -->
<div class="ing-screen" id="screen-ranking">
  <?php if (!empty($ranking)): ?>

  <!-- Podio top 3 -->
  <div class="podio-wrap">
    <?php
    // Ordenar: posición 2, 1, 3 para el podio visual
    $podio_order = [1=>null, 0=>null, 2=>null];
    foreach ([1,0,2] as $idx) {
      if (isset($ranking[$idx])) $podio_order[$idx] = $ranking[$idx];
    }
    $clases = [0=>'oro p1', 1=>'plata p2', 2=>'bronce p3'];
    $nums   = [0=>'1', 1=>'2', 2=>'3'];
    foreach ([1,0,2] as $pi):
      if (!isset($ranking[$pi])) continue;
      $r = $ranking[$pi];
      $av_ini = strtoupper(mb_substr($r['apodo'] ?: $r['nombre'], 0, 1));
      $pf = !empty($r['foto']) && file_exists(__DIR__.'/fotos/'.$r['foto']) ? '/intep/fotos/'.$r['foto'] : '';
    ?>
    <div class="podio-item p<?php echo $pi===0?'1':($pi===1?'2':'3'); ?>">
      <div class="podio-av <?php echo $pi===0?'oro':($pi===1?'plata':'bronce'); ?>">
        <?php if ($pf): ?><img src="<?php echo htmlspecialchars($pf); ?>" alt=""><?php else: echo $av_ini; endif; ?>
      </div>
      <div class="podio-nombre"><?php echo htmlspecialchars(substr($r['apodo']?:$r['nombre'],0,10)); ?></div>
      <div class="podio-xp"><?php echo $r['xp_total']; ?> XP</div>
      <div class="podio-bloque"><?php echo $pi===0?'1':($pi===1?'2':'3'); ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Mi posición -->
  <?php if ($mi_pos > 3): ?>
  <div class="mi-pos-card">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="font-size:22px;font-weight:900;color:var(--ing-azul)">#<?php echo $mi_pos; ?></div>
      <div>
        <div style="font-weight:800;color:var(--ing-azul);font-size:13px"><?php echo htmlspecialchars($apodo); ?> — Tú</div>
        <div style="font-size:11px;color:var(--ing-soft)"><?php echo $nivel_actual; ?> · <?php echo $xp_total; ?> XP</div>
      </div>
    </div>
    <div style="font-size:11px;color:var(--ing-soft);text-align:right">Posición<br>#<?php echo $mi_pos; ?></div>
  </div>
  <?php endif; ?>

  <!-- Lista completa -->
  <div class="seccion-label2">Clasificación completa</div>
  <?php foreach ($ranking as $pos => $r):
    $es_yo = (int)$r['estudiante_id'] === $est_id;
    $av_ini = strtoupper(mb_substr($r['apodo']?:$r['nombre'], 0, 1));
    $pf = !empty($r['foto']) && file_exists(__DIR__.'/fotos/'.$r['foto']) ? '/intep/fotos/'.$r['foto'] : '';
    $nb_class = 'nb-'.strtolower($r['nivel_actual']);
  ?>
  <div class="rank-item <?php echo $es_yo?'yo':''; ?>">
    <div class="rank-num"><?php echo $pos+1; ?></div>
    <div class="rank-av">
      <?php if ($pf): ?><img src="<?php echo htmlspecialchars($pf); ?>" alt=""><?php else: echo $av_ini; endif; ?>
    </div>
    <div class="rank-info">
      <div class="rank-nick">
        <?php echo htmlspecialchars($r['apodo']?:$r['nombre']); ?>
        <?php if ($es_yo): ?><span style="font-size:11px;color:var(--ing-azul)"> ← Tú</span><?php endif; ?>
        <span class="nivel-badge <?php echo $nb_class; ?>"><?php echo $r['nivel_actual']; ?></span>
      </div>
      <div class="rank-det">🔥 <?php echo $r['racha_actual']; ?> días · <?php echo $r['hoy_ejercicios']; ?> hoy</div>
    </div>
    <div class="rank-xp"><?php echo $r['xp_total']; ?></div>
  </div>
  <?php endforeach; ?>

  <?php else: ?>
  <div style="text-align:center;padding:40px 20px;color:var(--ing-soft)">
    <div style="font-size:40px;margin-bottom:12px">📊</div>
    <div style="font-weight:700">Aún no hay datos de ranking.<br>¡Sé el primero en practicar!</div>
  </div>
  <?php endif; ?>
</div><!-- /screen-ranking -->

<!-- TAB: AMIGOS -->
<div class="ing-screen" id="screen-amigos">
  <?php
  // Cargar todos los compañeros con progreso de hoy
  $amigos = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT n.apodo, n.nivel_actual, n.racha_actual, n.xp_total, n.ultima_sesion, n.estudiante_id,
            e.nombre, e.foto,
            SUM(CASE WHEN DATE(s.created_at) = CURDATE() AND s.es_quiz_nivel=0 THEN 1 ELSE 0 END) as hoy_ej
     FROM idiomas_nivel n
     JOIN estudiantes e ON e.id = n.estudiante_id
     LEFT JOIN idiomas_sesiones s ON s.estudiante_id = n.estudiante_id
     WHERE n.quiz_completado=1
     GROUP BY n.estudiante_id
     ORDER BY hoy_ej DESC, n.racha_actual DESC"), MYSQLI_ASSOC);

  $hoy = date('Y-m-d');
  $activos   = array_filter($amigos, fn($a) => $a['ultima_sesion'] === $hoy && (int)$a['estudiante_id'] !== $est_id);
  $inactivos = array_filter($amigos, fn($a) => $a['ultima_sesion'] !== $hoy && (int)$a['estudiante_id'] !== $est_id);
  ?>

  <!-- Mi racha -->
  <div class="ing-card" style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
    <div style="font-size:32px">🔥</div>
    <div>
      <div style="font-size:26px;font-weight:900;color:var(--ing-gold)"><?php echo $racha; ?> días</div>
      <div style="font-size:12px;color:var(--ing-soft);font-weight:700">Tu racha actual</div>
    </div>
    <?php if ($ing['ultima_sesion'] !== $hoy): ?>
    <div style="margin-left:auto;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:6px 10px;font-size:10px;font-weight:700;color:var(--ing-rojo);text-align:center;line-height:1.4">
      ¡Practica hoy<br>para no perderla!
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($activos)): ?>
  <div class="seccion-label2">Practicaron hoy ✅</div>
  <?php foreach ($activos as $a):
    $av_ini = strtoupper(mb_substr($a['apodo']?:$a['nombre'], 0, 1));
    $pf = !empty($a['foto']) && file_exists(__DIR__.'/fotos/'.$a['foto']) ? '/intep/fotos/'.$a['foto'] : '';
    $pct = $a['hoy_ej'] > 0 ? min(100, ($a['hoy_ej']/15)*100) : 0;
  ?>
  <div class="friend-card2 activo">
    <div class="friend-av2" style="background:linear-gradient(135deg,#059669,#10B981)">
      <?php if ($pf): ?><img src="<?php echo htmlspecialchars($pf); ?>" alt=""><?php else: echo $av_ini; endif; ?>
      <div class="friend-badge2 fb2-ok">✓</div>
    </div>
    <div>
      <div class="friend-name2"><?php echo htmlspecialchars($a['apodo']?:$a['nombre']); ?></div>
      <div class="friend-det2"><?php echo $a['nivel_actual']; ?> · <?php echo $a['hoy_ej']; ?> ejercicios hoy</div>
    </div>
    <div class="friend-streak2">
      <div class="fs-big">🔥<?php echo $a['racha_actual']; ?></div>
      <div class="fs-lbl">días</div>
    </div>
    <div class="friend-bar" style="width:<?php echo $pct; ?>%"></div>
  </div>
  <?php endforeach; endif; ?>

  <?php if (!empty($inactivos)): ?>
  <div class="seccion-label2" style="margin-top:16px">Aún no han practicado hoy</div>
  <?php foreach ($inactivos as $a):
    $av_ini = strtoupper(mb_substr($a['apodo']?:$a['nombre'], 0, 1));
    $pf = !empty($a['foto']) && file_exists(__DIR__.'/fotos/'.$a['foto']) ? '/intep/fotos/'.$a['foto'] : '';
    $racha_rota = $a['racha_actual'] === 0;
  ?>
  <div class="friend-card2 inactivo">
    <div class="friend-av2" style="background:linear-gradient(135deg,#374151,#6B7280)">
      <?php if ($pf): ?><img src="<?php echo htmlspecialchars($pf); ?>" alt=""><?php else: echo $av_ini; endif; ?>
      <div class="friend-badge2 fb2-no">–</div>
    </div>
    <div>
      <div class="friend-name2"><?php echo htmlspecialchars($a['apodo']?:$a['nombre']); ?></div>
      <div class="friend-det2"><?php echo $a['nivel_actual']; ?> · <?php echo $racha_rota ? 'Racha perdida 💔' : 'Racha en riesgo ⚠️'; ?></div>
    </div>
    <div class="friend-streak2">
      <div class="fs-big" style="color:<?php echo $racha_rota?'var(--ing-rojo)':'var(--ing-soft)'; ?>">
        <?php echo $racha_rota ? '💔' : '🔥'; ?><?php echo $a['racha_actual']; ?>
      </div>
      <div class="fs-lbl">días</div>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <?php if (empty($activos) && empty($inactivos)): ?>
  <div style="text-align:center;padding:40px 20px;color:var(--ing-soft)">
    <div style="font-size:40px;margin-bottom:12px">👥</div>
    <div style="font-weight:700">Aún no hay compañeros en el ranking.<br>¡Invítalos a practicar!</div>
  </div>
  <?php endif; ?>

</div><!-- /screen-amigos -->

<?php endif; // fin portal principal ?>

<script>
// ════════════════════════════════════════
// INTEP INGLÉS — JavaScript principal
// ════════════════════════════════════════

// ── Tabs ──────────────────────────────
function showTab(nombre) {
  document.querySelectorAll('.ing-screen').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.ing-tab').forEach(t => t.classList.remove('active'));
  const s = document.getElementById('screen-' + nombre);
  if (s) s.classList.add('active');
  const tabs = document.querySelectorAll('.ing-tab');
  const map = {inicio:0, practicar:1, ranking:2, amigos:3};
  if (tabs[map[nombre]]) tabs[map[nombre]].classList.add('active');
  if (nombre === 'practicar' && !sesionIniciada) iniciarSesion();
}

// ── ONBOARDING: apodo ──────────────────
function guardarApodo() {
  const apodo = document.getElementById('apodo-input').value.trim();
  const err   = document.getElementById('apodo-error');
  if (apodo.length < 2) { err.textContent='Mínimo 2 caracteres'; err.style.display='block'; return; }
  err.style.display = 'none';

  fetch('api/idiomas_ejercicio.php', {
    method: 'POST',
    body: new URLSearchParams({accion:'set_apodo', apodo})
  })
  .then(r=>r.json())
  .then(d => {
    if (d.ok) location.reload();
    else { err.textContent = d.error||'Error'; err.style.display='block'; }
  });
}

// ── QUIZ DE NIVEL — 15 preguntas hardcodeadas (sin llamadas a la IA) ──
<?php if (!$tiene_perfil || (!$quiz_completado && $tiene_perfil && $apodo)): ?>

const QUIZ_PREGUNTAS = [
  // ── A1 (1-5): gramática y vocabulario básico ──
  {
    nivel: 'A1',
    pregunta: 'What is the correct form? "I ___ a student."',
    opciones: ['A. am', 'B. is', 'C. are', 'D. be'],
    correcta: 'A',
    explicacion: 'Con "I" el verbo "to be" es siempre "am".'
  },
  {
    nivel: 'A1',
    pregunta: 'Choose the correct word: "I have ___ apple."',
    opciones: ['A. a', 'B. an', 'C. the', 'D. some'],
    correcta: 'B',
    explicacion: '"An" se usa antes de palabras que empiezan con sonido de vocal (apple).'
  },
  {
    nivel: 'A1',
    pregunta: 'What does "book" mean in Spanish?',
    opciones: ['A. Mesa', 'B. Silla', 'C. Libro', 'D. Lápiz'],
    correcta: 'C',
    explicacion: '"Book" significa libro en español.'
  },
  {
    nivel: 'A1',
    pregunta: 'Which sentence is correct?',
    opciones: ['A. She have a dog.', 'B. She has a dog.', 'C. She haves a dog.', 'D. She is have a dog.'],
    correcta: 'B',
    explicacion: 'Con she/he/it en presente simple, el verbo lleva -s: has.'
  },
  {
    nivel: 'A1',
    pregunta: 'How do you say "Hola, ¿cómo estás?" in English?',
    opciones: ['A. Goodbye, how are you?', 'B. Hello, what is your name?', 'C. Hi, how are you?', 'D. Good night, how are you?'],
    correcta: 'C',
    explicacion: '"Hi, how are you?" es la traducción directa de "Hola, ¿cómo estás?".'
  },

  // ── A2 (6-10): presente simple, tiempos, vocabulario ──
  {
    nivel: 'A2',
    pregunta: 'Complete: "Yesterday I ___ to school by bus."',
    opciones: ['A. go', 'B. goes', 'C. went', 'D. going'],
    correcta: 'C',
    explicacion: '"Yesterday" indica pasado. El pasado de "go" es "went".'
  },
  {
    nivel: 'A2',
    pregunta: 'Which question is correct?',
    opciones: ['A. Where you live?', 'B. Where do you live?', 'C. Where does you live?', 'D. Where you does live?'],
    correcta: 'B',
    explicacion: 'Las preguntas en presente simple usan "do" con I/you/we/they.'
  },
  {
    nivel: 'A2',
    pregunta: 'Choose the correct comparative: "This box is ___ than that one."',
    opciones: ['A. heavy', 'B. heavier', 'C. more heavy', 'D. heaviest'],
    correcta: 'B',
    explicacion: 'Para adjetivos de 2 sílabas o menos, el comparativo es -er: heavier.'
  },
  {
    nivel: 'A2',
    pregunta: '"I am going to the store." What tense is this?',
    opciones: ['A. Simple past', 'B. Present simple', 'C. Present continuous', 'D. Future simple'],
    correcta: 'C',
    explicacion: '"Am going" es presente continuo (to be + verb-ing), usado para acciones en progreso.'
  },
  {
    nivel: 'A2',
    pregunta: 'What is the plural of "child"?',
    opciones: ['A. childs', 'B. childes', 'C. children', 'D. childrens'],
    correcta: 'C',
    explicacion: '"Child" tiene un plural irregular: "children". No sigue la regla del -s.'
  },

  // ── B1 (11-13): tiempos compuestos, condiciones, vocabulario avanzado ──
  {
    nivel: 'B1',
    pregunta: 'Complete: "If it rains tomorrow, I ___ stay at home."',
    opciones: ['A. will', 'B. would', 'C. should', 'D. must'],
    correcta: 'A',
    explicacion: 'El primer condicional (situación probable) usa "if + present, will + infinitive".'
  },
  {
    nivel: 'B1',
    pregunta: 'Which sentence uses the Present Perfect correctly?',
    opciones: [
      'A. I have seen that movie yesterday.',
      'B. I have never eaten sushi.',
      'C. She has went to Paris last year.',
      'D. They have finished the work since Monday.'
    ],
    correcta: 'B',
    explicacion: 'Present perfect con "never" es correcto. No se usa con tiempo específico (yesterday/last year).'
  },
  {
    nivel: 'B1',
    pregunta: 'Choose the correct passive voice: "The letter ___ by Maria."',
    opciones: ['A. wrote', 'B. is written', 'C. was wrote', 'D. writes'],
    correcta: 'B',
    explicacion: 'La voz pasiva en presente simple es: "is/are + past participle". "Written" es el participio de "write".'
  },

  // ── B2 (14-15): vocabulario avanzado, matices, estructuras complejas ──
  {
    nivel: 'B2',
    pregunta: 'Which word best completes the sentence? "Despite the heavy rain, the event was not ___."',
    opciones: ['A. cancelled', 'B. cancel', 'C. cancelling', 'D. to cancel'],
    correcta: 'A',
    explicacion: 'Después de "was" (verbo to be) se usa el participio pasado en voz pasiva: "cancelled".'
  },
  {
    nivel: 'B2',
    pregunta: 'Choose the correct form: "I wish I ___ more time to study last week."',
    opciones: ['A. have had', 'B. had had', 'C. would have', 'D. had'],
    correcta: 'B',
    explicacion: '"I wish + had + past participle" expresa arrepentimiento sobre el pasado (wish con pasado perfecto).'
  }
];

let quizNum = 0;
let quizCorrectas = 0;
// Peso por nivel para calcular el resultado
const NIVEL_PESOS = { A1: 1, A2: 2, B1: 3, B2: 4 };

function mostrarPreguntaQuiz() {
  const p = QUIZ_PREGUNTAS[quizNum];
  document.getElementById('quiz-num').textContent = 'Pregunta ' + (quizNum + 1) + ' de 15';
  document.getElementById('quiz-pregunta').textContent = p.pregunta;

  // Reset feedback y botón
  document.getElementById('quiz-feedback').className = 'feedback';
  document.getElementById('quiz-next').classList.remove('show');

  // Renderizar opciones
  const wrap = document.getElementById('quiz-opciones');
  wrap.innerHTML = '';
  p.opciones.forEach(op => {
    const btn = document.createElement('button');
    btn.className = 'opcion-btn';
    btn.textContent = op;
    const letra = op.charAt(0);
    btn.onclick = () => responderQuiz(btn, letra);
    wrap.appendChild(btn);
  });
}

function responderQuiz(btn, letra) {
  const p = QUIZ_PREGUNTAS[quizNum];
  document.querySelectorAll('#quiz-opciones .opcion-btn').forEach(b => b.disabled = true);
  const esOk = letra === p.correcta;
  btn.classList.add(esOk ? 'correcta' : 'incorrecta');
  if (!esOk) {
    document.querySelectorAll('#quiz-opciones .opcion-btn').forEach(b => {
      if (b.textContent.charAt(0) === p.correcta) b.classList.add('correcta');
    });
  }
  if (esOk) quizCorrectas++;

  const fb = document.getElementById('quiz-feedback');
  fb.className = 'feedback ' + (esOk ? 'ok' : 'mal') + ' show';
  document.getElementById('quiz-fb-icon').textContent   = esOk ? '🎉' : '💡';
  document.getElementById('quiz-fb-titulo').className   = 'fb-titulo ' + (esOk ? 'ok' : 'mal');
  document.getElementById('quiz-fb-titulo').textContent = esOk ? '¡Correcto!' : 'Respuesta correcta: ' + p.correcta;
  document.getElementById('quiz-fb-texto').textContent = p.explicacion;
  document.getElementById('quiz-next').classList.add('show');
}

function siguienteQuiz() {
  // Avanzar dot
  const dot = document.getElementById('qdot-' + quizNum);
  if (dot) { dot.classList.remove('current'); dot.classList.add('done'); }
  quizNum++;
  const nextDot = document.getElementById('qdot-' + quizNum);
  if (nextDot) nextDot.classList.add('current');

  if (quizNum >= 15) {
    const pct = quizCorrectas / 15;
    let nivel = 'A1';
    if (pct >= 0.87)      nivel = 'B2';
    else if (pct >= 0.67) nivel = 'B1';
    else if (pct >= 0.40) nivel = 'A2';

    // Guardar nivel en DB
    fetch('api/idiomas_ejercicio.php', {
      method: 'POST',
      body: new URLSearchParams({accion: 'set_nivel_quiz', nivel})
    }).then(() => {
      // Mostrar selector de ejercicios en lugar de recargar
      const nombres = {A1:'Principiante',A2:'Elemental',B1:'Intermedio',B2:'Intermedio alto'};
      document.getElementById('sel-nivel-big').textContent    = nivel;
      document.getElementById('sel-nivel-nombre').textContent = nombres[nivel] || nivel;
      document.getElementById('screen-quiz').style.display    = 'none';
      document.getElementById('screen-selector').style.display = 'block';
      window._nivelQuiz = nivel;
    });
    return;
  }
  mostrarPreguntaQuiz();
}

// Mostrar primera pregunta inmediatamente
mostrarPreguntaQuiz();

// ── Selector de cantidad de ejercicios ──
let _cantidadSel = 15;
function seleccionarCantidad(n, btn) {
  _cantidadSel = n;
  document.querySelectorAll('.sel-opt').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
}
function confirmarSelector() {
  const btn = document.getElementById('sel-btn-comenzar');
  btn.textContent = 'Guardando...';
  btn.disabled = true;
  fetch('api/idiomas_ejercicio.php', {
    method: 'POST',
    body: new URLSearchParams({accion: 'set_ejercicios_sesion', cantidad: _cantidadSel})
  }).then(() => location.reload());
}
<?php endif; ?>

// ── SESIÓN DE EJERCICIOS ───────────────
<?php if ($tiene_perfil && $quiz_completado): ?>
let sesionIniciada = false;
let ejActual = null;
let ejNum    = 0;
let ejTotal  = <?php echo $ejercicios_sesion; ?>; // preferencia del estudiante (10/15/20)
let ejOk     = 0;
let vidas    = 5;
let xpSesion = 0;
let rachaSesion = <?php echo $racha; ?>;

function iniciarSesion() {
  if (sesionIniciada) return;
  sesionIniciada = true;
  ejNum = 0; ejOk = 0; vidas = 5; xpSesion = 0;
  renderCorazones();
  cargarEjercicio();
}

function reiniciarSesion() {
  sesionIniciada = false;
  document.getElementById('finish-wrap').classList.remove('show');
  document.getElementById('ej-wrap').style.display = 'none';
  document.getElementById('ej-cargando').classList.remove('show');
  iniciarSesion();
}

function renderCorazones() {
  const wrap = document.getElementById('hearts-wrap');
  wrap.innerHTML = '';
  for (let i = 0; i < 5; i++) {
    const span = document.createElement('span');
    span.className = 'heart' + (i >= vidas ? ' lost' : '');
    span.textContent = '❤️';
    wrap.appendChild(span);
  }
}

function cargarEjercicio() {
  document.getElementById('ej-cargando').classList.add('show');
  document.getElementById('ej-wrap').style.display = 'none';
  document.getElementById('feedback').className = 'feedback';
  document.getElementById('btn-siguiente').className = 'btn-siguiente';

  fetch('api/idiomas_ejercicio.php', {
    method: 'POST',
    body: new URLSearchParams({accion:'generar'})
  })
  .then(r=>r.json())
  .then(d => {
    document.getElementById('ej-cargando').classList.remove('show');
    if (!d.ok || d.error) {
      alert('Error generando ejercicio: ' + (d.error||'inténtalo de nuevo'));
      return;
    }
    ejActual = d.ejercicio;
    mostrarEjercicio(ejActual);
  })
  .catch(() => {
    document.getElementById('ej-cargando').classList.remove('show');
    alert('Error de conexión. Verifica tu internet e intenta de nuevo.');
  });
}

const tipoLabels = {
  fill_blank:      '✍️ Completa la frase',
  multiple_choice: '❓ Opción múltiple',
  traduccion:      '🔤 Traducción',
  corrige_error:   '🔍 Corrige el error',
  vocabulario:     '📖 Vocabulario',
  dialogo:         '💬 Diálogo',
};

function mostrarEjercicio(ej) {
  document.getElementById('ej-wrap').style.display = 'block';
  document.getElementById('ej-tipo-badge').textContent = tipoLabels[ej.tipo] || ej.tipo;
  document.getElementById('ej-instruccion').textContent = ej.instruccion || '';
  document.getElementById('ej-pregunta').textContent   = ej.pregunta || '';
  document.getElementById('ej-traduccion').textContent = ej.traduccion_ayuda || '';

  // Progreso
  ejNum++;
  document.getElementById('ej-counter').textContent = ejNum + '/' + ejTotal;
  document.getElementById('prog-fill').style.width = ((ejNum-1)/ejTotal*100) + '%';

  const opWrap = document.getElementById('opciones-wrap');
  const trWrap = document.getElementById('traduccion-wrap');
  const trInput= document.getElementById('traduccion-input');

  if (ej.tipo === 'traduccion') {
    opWrap.style.display = 'none';
    trWrap.style.display = 'block';
    trInput.value = '';
    trInput.focus();
  } else {
    opWrap.style.display = 'grid';
    trWrap.style.display = 'none';
    opWrap.innerHTML = '';
    (ej.opciones || []).forEach(op => {
      const btn = document.createElement('button');
      btn.className = 'opcion-btn';
      btn.textContent = op;
      const letra = op.charAt(0);
      btn.onclick = () => elegirOpcion(btn, letra);
      opWrap.appendChild(btn);
    });
  }
}

function elegirOpcion(btn, letra) {
  const esOk = letra === ejActual.correcta;
  document.querySelectorAll('.opcion-btn').forEach(b => b.disabled = true);
  btn.classList.add(esOk ? 'correcta' : 'incorrecta');
  if (!esOk) {
    document.querySelectorAll('.opcion-btn').forEach(b => {
      if (b.textContent.charAt(0) === ejActual.correcta) b.classList.add('correcta');
    });
    vidas--; renderCorazones();
  }
  mostrarFeedback(esOk, ejActual.explicacion);
  guardarRespuesta(ejActual.tipo, ejActual.pregunta, ejActual.respuesta_texto, letra === ejActual.correcta ? ejActual.respuesta_texto : letra, esOk, ejActual.explicacion);
}

function comprobarTraduccion() {
  const resp = document.getElementById('traduccion-input').value.trim();
  if (!resp) return;
  document.getElementById('btn-comprobar').disabled = true;

  fetch('api/idiomas_ejercicio.php', {
    method: 'POST',
    body: new URLSearchParams({
      accion: 'evaluar_traduccion',
      pregunta: ejActual.pregunta,
      correcta: ejActual.respuesta_texto,
      respuesta: resp
    })
  })
  .then(r=>r.json())
  .then(d => {
    document.getElementById('btn-comprobar').disabled = false;
    const esOk = d.resultado?.es_correcto || false;
    const expl = d.resultado?.explicacion || '';
    if (!esOk) { vidas--; renderCorazones(); }
    mostrarFeedback(esOk, expl);
    guardarRespuesta('traduccion', ejActual.pregunta, ejActual.respuesta_texto, resp, esOk, expl);
  });
}

function mostrarFeedback(esOk, explicacion) {
  if (esOk) { ejOk++; xpSesion += 10; } else { xpSesion += 2; }

  const fb = document.getElementById('feedback');
  fb.className = 'feedback ' + (esOk?'ok':'mal') + ' show';
  document.getElementById('fb-icon').textContent = esOk ? '🎉' : '💡';
  document.getElementById('fb-titulo').className = 'fb-titulo ' + (esOk?'ok':'mal');
  document.getElementById('fb-titulo').textContent = esOk ? '¡Correcto! +10 XP' : 'Respuesta incorrecta';
  document.getElementById('fb-texto').textContent = explicacion || '';
  document.getElementById('btn-siguiente').classList.add('show');
}

let nivelActual = '<?php echo $nivel_actual; ?>';

function guardarRespuesta(tipo, pregunta, correcta, dada, esOk, expl) {
  fetch('api/idiomas_ejercicio.php', {
    method: 'POST',
    body: new URLSearchParams({
      accion:'guardar', tipo, nivel: ejActual.nivel || nivelActual,
      pregunta, respuesta_correcta: correcta,
      respuesta_dada: dada, es_correcto: esOk?1:0,
      explicacion: expl||'', es_quiz:0
    })
  })
  .then(r=>r.json())
  .then(d => {
    if (!d.ok) return;
    rachaSesion = d.racha || rachaSesion;

    // ¿Subió de nivel?
    if (d.nivel && d.nivel !== nivelActual) {
      const anterior = nivelActual;
      nivelActual = d.nivel;
      // Mostrar notificación de nivel encima del feedback
      const fb = document.getElementById('feedback');
      const noti = document.createElement('div');
      noti.style.cssText = 'background:linear-gradient(135deg,#1D4ED8,#3B82F6);border-radius:14px;padding:14px 16px;margin-bottom:10px;text-align:center;animation:subir .4s ease both';
      noti.innerHTML = '<div style="font-size:28px">🎉</div><div style="font-size:16px;font-weight:900;color:white">¡Subiste de nivel!</div><div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:2px">' + anterior + ' → <strong>' + nivelActual + '</strong> — Los ejercicios ahora serán más desafiantes</div>';
      fb.parentNode.insertBefore(noti, fb);
      setTimeout(() => noti.remove(), 6000);
    }
  });
}

function siguienteEjercicio() {
  // Sin vidas o completó los 15 → fin
  if (vidas <= 0 || ejNum >= ejTotal) {
    mostrarFin(); return;
  }
  document.getElementById('feedback').className = 'feedback';
  document.getElementById('btn-siguiente').className = 'btn-siguiente';
  cargarEjercicio();
}

function mostrarFin() {
  document.getElementById('ej-wrap').style.display = 'none';
  const fw = document.getElementById('finish-wrap');
  fw.classList.add('show');
  document.getElementById('finish-racha').textContent = rachaSesion;
  document.getElementById('finish-ok').textContent = ejOk + '/' + (ejNum);
  document.getElementById('finish-pct').textContent = ejNum > 0 ? Math.round(ejOk/ejNum*100)+'%' : '0%';
  document.getElementById('finish-xp').textContent = '+' + xpSesion;
  lanzarConfetti();
}

// ── Confetti ──────────────────────────
function lanzarConfetti() {
  const canvas = document.getElementById('confetti-canvas');
  const ctx    = canvas.getContext('2d');
  canvas.width  = window.innerWidth;
  canvas.height = window.innerHeight;
  const colores = ['#F5C842','#3B82F6','#10B981','#EF4444','#D946A8','#ffffff'];
  const piezas  = Array.from({length:80}, () => ({
    x: Math.random() * canvas.width,
    y: Math.random() * -200,
    r: Math.random() * 6 + 3,
    c: colores[Math.floor(Math.random()*colores.length)],
    vx: (Math.random()-0.5)*2,
    vy: Math.random()*3+1,
    rot: Math.random()*360,
    vrot: (Math.random()-0.5)*5,
  }));
  let frame = 0;
  function draw() {
    ctx.clearRect(0,0,canvas.width,canvas.height);
    piezas.forEach(p => {
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot * Math.PI/180);
      ctx.fillStyle = p.c;
      ctx.fillRect(-p.r/2, -p.r/2, p.r, p.r*1.5);
      ctx.restore();
      p.x += p.vx; p.y += p.vy; p.rot += p.vrot;
    });
    frame++;
    if (frame < 120) requestAnimationFrame(draw);
    else ctx.clearRect(0,0,canvas.width,canvas.height);
  }
  draw();
}
<?php endif; ?>

// Tecla Enter para apodo
const apIn = document.getElementById('apodo-input');
if (apIn) apIn.addEventListener('keydown', e => { if (e.key==='Enter') guardarApodo(); });

// Tecla Enter para traducción
const trIn = document.getElementById('traduccion-input');
if (trIn) trIn.addEventListener('keydown', e => { if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); comprobarTraduccion(); } });
</script>

<?php if ($es_ingles): ?>
<!-- ════════════════════════════════════════
     MY TEACHER GUS — Interfaz flotante
════════════════════════════════════════ -->

<!-- Callout burbuja -->
<div class="gus-callout" id="gusCallout">¡Hola! ¿Practicamos inglés hoy? 😊</div>

<!-- Avatar flotante -->
<div class="gus-bubble" id="gusBubble" onclick="abrirGUS()" title="My Teacher GUS">
  <div class="gus-avatar-wrap">
    <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" style="width:70px;height:70px;filter:drop-shadow(0 6px 14px rgba(59,130,246,.55))">
      <!-- Gorra -->
      <rect x="16" y="14" width="48" height="8" rx="3" fill="#1D4ED8"/>
      <rect x="24" y="10" width="32" height="8" rx="4" fill="#2563EB"/>
      <polygon points="40,6 56,14 24,14" fill="#1D4ED8"/>
      <!-- Borla gorra -->
      <circle cx="40" cy="6" r="3" fill="#F5C842"/>
      <line x1="40" y1="9" x2="44" y2="15" stroke="#F5C842" stroke-width="1.5"/>
      <!-- Cabeza -->
      <ellipse cx="40" cy="34" rx="20" ry="20" fill="#FDDCB5"/>
      <!-- Ojos -->
      <circle cx="33" cy="30" r="4" fill="white"/>
      <circle cx="47" cy="30" r="4" fill="white"/>
      <circle cx="34" cy="30" r="2.5" fill="#1e3a5f"/>
      <circle cx="48" cy="30" r="2.5" fill="#1e3a5f"/>
      <circle cx="35" cy="29" r="1" fill="white"/>
      <circle cx="49" cy="29" r="1" fill="white"/>
      <!-- Gafas -->
      <rect x="28" y="26" width="12" height="9" rx="3" fill="none" stroke="#374151" stroke-width="1.5"/>
      <rect x="41" y="26" width="12" height="9" rx="3" fill="none" stroke="#374151" stroke-width="1.5"/>
      <line x1="40" y1="30" x2="41" y2="30" stroke="#374151" stroke-width="1.5"/>
      <line x1="28" y1="30" x2="24" y2="29" stroke="#374151" stroke-width="1.5"/>
      <line x1="53" y1="30" x2="57" y2="29" stroke="#374151" stroke-width="1.5"/>
      <!-- Sonrisa -->
      <path d="M33 39 Q40 45 47 39" stroke="#C07030" stroke-width="2" fill="none" stroke-linecap="round"/>
      <!-- Bigote -->
      <path d="M35 36 Q37 37.5 40 37 Q43 37.5 45 36" stroke="#8B5E3C" stroke-width="1.2" fill="none"/>
      <!-- Orejitas -->
      <ellipse cx="20" cy="34" rx="4" ry="5" fill="#FDDCB5"/>
      <ellipse cx="60" cy="34" rx="4" ry="5" fill="#FDDCB5"/>
      <!-- Cuello + camisa -->
      <rect x="28" y="52" width="24" height="18" rx="4" fill="#1D4ED8"/>
      <!-- Corbata -->
      <polygon points="40,54 37,58 40,70 43,58" fill="#EF4444"/>
      <polygon points="37,54 43,54 41,57 39,57" fill="#DC2626"/>
      <!-- Cuello camisa -->
      <polygon points="34,52 40,58 40,52" fill="white"/>
      <polygon points="46,52 40,58 40,52" fill="white"/>
      <!-- Hombros -->
      <ellipse cx="24" cy="56" rx="8" ry="6" fill="#1D4ED8"/>
      <ellipse cx="56" cy="56" rx="8" ry="6" fill="#1D4ED8"/>
    </svg>
  </div>
  <div class="gus-label">My Teacher GUS</div>
</div>

<!-- Modal GUS -->
<div class="gus-modal-bg" id="gusModalBg" onclick="cerrarGUSfuera(event)">
  <div class="gus-modal" id="gusModal">
    <!-- Celebration overlay -->
    <div class="gus-celebrate" id="gusCelebrate">
      <div class="gus-cel-trophy">🎓</div>
      <div class="gus-cel-title">¡Lección completada!</div>
      <div class="gus-cel-xp" id="gusCelXP">+0 XP</div>
      <div class="gus-cel-sub">GUS está orgulloso de tu progreso. ¡Sigue así!</div>
      <button class="gus-cel-btn" onclick="nuevaLeccion()">Nueva lección →</button>
    </div>

    <!-- Voice Mode Panel -->
    <div class="gus-voice-panel" id="gusVoicePanel">
      <div class="voice-topic-lbl" id="voiceTopicLbl">—</div>
      <div class="voice-txt-gus" id="voiceTxtGus"></div>
      <div class="gus-orb-wrap idle" id="gusOrb" onclick="orbTap()">
        <div class="orb-ring orb-ring-1"></div>
        <div class="orb-ring orb-ring-2"></div>
        <div class="orb-ring orb-ring-3"></div>
        <div class="orb-center">
          <svg viewBox="0 0 80 80" style="width:60px;height:60px">
            <ellipse cx="40" cy="34" rx="20" ry="20" fill="#FDDCB5"/>
            <circle cx="33" cy="30" r="4" fill="white"/><circle cx="47" cy="30" r="4" fill="white"/>
            <circle cx="34" cy="30" r="2.5" fill="#1e3a5f"/><circle cx="48" cy="30" r="2.5" fill="#1e3a5f"/>
            <rect x="28" y="26" width="12" height="9" rx="3" fill="none" stroke="#374151" stroke-width="1.5"/>
            <rect x="41" y="26" width="12" height="9" rx="3" fill="none" stroke="#374151" stroke-width="1.5"/>
            <path d="M33 39 Q40 45 47 39" stroke="#C07030" stroke-width="2" fill="none" stroke-linecap="round"/>
            <rect x="28" y="52" width="24" height="18" rx="4" fill="currentColor" style="color:#1D4ED8"/>
            <polygon points="40,54 37,58 40,70 43,58" fill="#EF4444"/>
            <ellipse cx="20" cy="34" rx="4" ry="5" fill="#FDDCB5"/>
            <ellipse cx="60" cy="34" rx="4" ry="5" fill="#FDDCB5"/>
          </svg>
        </div>
      </div>
      <div class="orb-state-lbl" id="orbStateLbl" style="color:rgba(255,255,255,.4)">Toca el orbe para activar</div>
      <div class="voice-wave" id="voiceWave">
        <div class="wbar"></div><div class="wbar"></div><div class="wbar"></div>
        <div class="wbar"></div><div class="wbar"></div><div class="wbar"></div><div class="wbar"></div>
      </div>
      <div class="voice-txt-user" id="voiceTxtUser"></div>
      <button class="voice-btn-chat" onclick="desactivarModoVoz()">💬 Modo Chat</button>
    </div>

    <!-- Header -->
    <div class="gus-modal-head">
      <div class="gus-head-av">
        <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" style="width:46px;height:46px">
          <rect x="16" y="14" width="48" height="8" rx="3" fill="#1D4ED8"/>
          <rect x="24" y="10" width="32" height="8" rx="4" fill="#2563EB"/>
          <polygon points="40,6 56,14 24,14" fill="#1D4ED8"/>
          <circle cx="40" cy="6" r="3" fill="#F5C842"/>
          <ellipse cx="40" cy="34" rx="20" ry="20" fill="#FDDCB5"/>
          <circle cx="33" cy="30" r="4" fill="white"/><circle cx="47" cy="30" r="4" fill="white"/>
          <circle cx="34" cy="30" r="2.5" fill="#1e3a5f"/><circle cx="48" cy="30" r="2.5" fill="#1e3a5f"/>
          <rect x="28" y="26" width="12" height="9" rx="3" fill="none" stroke="#374151" stroke-width="1.5"/>
          <rect x="41" y="26" width="12" height="9" rx="3" fill="none" stroke="#374151" stroke-width="1.5"/>
          <line x1="40" y1="30" x2="41" y2="30" stroke="#374151" stroke-width="1.5"/>
          <path d="M33 39 Q40 45 47 39" stroke="#C07030" stroke-width="2" fill="none" stroke-linecap="round"/>
          <rect x="28" y="52" width="24" height="18" rx="4" fill="#1D4ED8"/>
          <polygon points="40,54 37,58 40,70 43,58" fill="#EF4444"/>
          <polygon points="37,54 43,54 41,57 39,57" fill="#DC2626"/>
          <polygon points="34,52 40,58 40,52" fill="white"/>
          <polygon points="46,52 40,58 40,52" fill="white"/>
          <ellipse cx="20" cy="34" rx="4" ry="5" fill="#FDDCB5"/>
          <ellipse cx="60" cy="34" rx="4" ry="5" fill="#FDDCB5"/>
          <ellipse cx="24" cy="56" rx="8" ry="6" fill="#1D4ED8"/>
          <ellipse cx="56" cy="56" rx="8" ry="6" fill="#1D4ED8"/>
        </svg>
      </div>
      <div class="gus-head-info">
        <div class="gus-head-name">My Teacher GUS</div>
        <div class="gus-head-sub" id="gusStatus">Great Understanding System · INTEP</div>
      </div>
      <button class="gus-voice-toggle" id="gusVoiceToggle" onclick="toggleModoVoz()" title="Modo Voz Continua">🎤</button>
      <button class="gus-head-close" onclick="cerrarGUS()">✕</button>
    </div>

    <!-- Tema actual + historial toggle -->
    <div class="gus-topic-bar" id="gusTopicBar" style="display:flex;align-items:center;justify-content:space-between">
      <span>Tema: <span id="gusTopicTxt">—</span></span>
      <button class="gus-history-btn" onclick="toggleHistorial()">📚 Historial</button>
    </div>

    <!-- Historial de lecciones -->
    <div class="gus-hist-panel" id="gusHistPanel">
      <div id="gusHistContent" style="color:rgba(255,255,255,.4);font-size:12px;text-align:center;padding:8px">Cargando historial...</div>
    </div>

    <!-- Chat -->
    <div class="gus-chat" id="gusChat">
      <div class="gus-typing" id="gusTyping">
        <span></span><span></span><span></span>
      </div>
    </div>

    <!-- Input -->
    <div class="gus-input-area">
      <button class="gus-btn-mic" id="gusMicBtn" onclick="toggleMic()" title="Hablar con GUS">🎤</button>
      <textarea class="gus-input" id="gusInput" placeholder="Escribe tu respuesta en inglés..." rows="1"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();enviarMensaje()}"
        oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,100)+'px'"></textarea>
      <button class="gus-btn-send" id="gusSendBtn" onclick="enviarMensaje()" title="Enviar">➤</button>
    </div>
  </div>
</div>

<script>
// ════════════════════════════════════════
// MY TEACHER GUS — JavaScript
// ════════════════════════════════════════

let gusLeccionId  = null;
let gusCargando   = false;
let gusTTSActivo  = true;
let gusRecognizer = null;
let gusMicActive  = false;
let gusLeccionDone = false;

// Callout ocasional
setTimeout(() => {
  const c = document.getElementById('gusCallout');
  if (c) { c.classList.add('visible'); setTimeout(() => c.classList.remove('visible'), 4000); }
}, 8000);
setInterval(() => {
  const c = document.getElementById('gusCallout');
  const frases = [
    '¿Listo para practicar inglés? 🎓',
    'Let\'s learn something new! ✨',
    '¡Hola! ¿Una lección rápida? 😊',
    'Practice makes perfect! 💪',
    'Your English is getting better! 🌟',
  ];
  if (c && !document.getElementById('gusModalBg').classList.contains('open')) {
    c.textContent = frases[Math.floor(Math.random()*frases.length)];
    c.classList.add('visible');
    setTimeout(() => c.classList.remove('visible'), 4000);
  }
}, 60000);

// ── Abrir GUS ──────────────────────────
function abrirGUS() {
  document.getElementById('gusModalBg').classList.add('open');
  document.getElementById('gusCallout').classList.remove('visible');
  if (!gusLeccionId && !gusCargando) iniciarLeccion();
}

function cerrarGUS() {
  document.getElementById('gusModalBg').classList.remove('open');
  if (gusRecognizer) { gusRecognizer.stop(); gusMicActive = false; document.getElementById('gusMicBtn').classList.remove('listening'); }
}

function cerrarGUSfuera(e) {
  if (e.target === document.getElementById('gusModalBg')) cerrarGUS();
}

// ── Iniciar lección ────────────────────
function iniciarLeccion() {
  gusCargando = true;
  gusLeccionDone = false;
  document.getElementById('gusCelebrate').classList.remove('show');
  setGusStatus('GUS está preparando tu lección...');
  mostrarTyping(true);

  fetch('api/gus.php', {
    method: 'POST',
    body: new URLSearchParams({ accion: 'iniciar' })
  })
  .then(r => r.json())
  .then(d => {
    mostrarTyping(false);
    gusCargando = false;
    if (d.error) { agregarMensaje('⚠️ ' + d.error, 'system'); return; }

    gusLeccionId = d.leccion_id;
    document.getElementById('gusTopicTxt').textContent = d.tema || '—';

    // Sync topic label in voice panel
    document.getElementById('voiceTopicLbl').textContent = d.tema || '—';

    if (d.retomando && d.historial) {
      setGusStatus('Retomando lección · ' + (d.tema||''));
      agregarMensaje('📌 Estamos retomando tu lección anterior sobre "' + d.tema + '"', 'system');
      d.historial.forEach(m => agregarMensaje(m.content, m.role === 'assistant' ? 'gus' : 'user', false));
      // En modo voz, resume con escucha inmediata
      if (voiceModeActive) { setOrb('listening'); startVoiceRec(); }
    } else if (d.mensaje) {
      setGusStatus('En clase · ' + (d.tema||''));
      if (voiceModeActive) {
        agregarMensaje(d.mensaje, 'gus', false);
        voiceHandleGusMessage(d.mensaje);
      } else {
        agregarMensaje(d.mensaje, 'gus', true);
      }
    }
    cargarHistorial();
  })
  .catch(() => { mostrarTyping(false); gusCargando = false; agregarMensaje('⚠️ Error de conexión', 'system'); });
}

// ── Enviar mensaje ─────────────────────
function enviarMensaje() {
  const input = document.getElementById('gusInput');
  const texto = input.value.trim();
  if (!texto || gusCargando || gusLeccionDone) return;
  if (!gusLeccionId) { iniciarLeccion(); return; }

  input.value = '';
  input.style.height = 'auto';
  agregarMensaje(texto, 'user', false);
  gusCargando = true;
  mostrarTyping(true);
  document.getElementById('gusSendBtn').disabled = true;

  fetch('api/gus.php', {
    method: 'POST',
    body: new URLSearchParams({ accion:'mensaje', leccion_id: gusLeccionId, mensaje: texto })
  })
  .then(r => r.json())
  .then(d => {
    mostrarTyping(false);
    gusCargando = false;
    document.getElementById('gusSendBtn').disabled = false;

    if (d.error) { agregarMensaje('⚠️ ' + d.error, 'system'); return; }

    agregarMensaje(d.mensaje, 'gus', true);

    if (d.completada) {
      gusLeccionDone = true;
      gusLeccionId   = null;
      setTimeout(() => mostrarCelebracion(d.xp || 0), 1200);
    }
  })
  .catch(() => {
    mostrarTyping(false);
    gusCargando = false;
    document.getElementById('gusSendBtn').disabled = false;
    agregarMensaje('⚠️ GUS no responde, intenta de nuevo', 'system');
  });
}

// ── Nueva lección ──────────────────────
function nuevaLeccion() {
  // Limpiar chat
  const chat = document.getElementById('gusChat');
  chat.innerHTML = '<div class="gus-typing" id="gusTyping"><span></span><span></span><span></span></div>';
  gusLeccionId = null;
  gusLeccionDone = false;
  document.getElementById('gusCelebrate').classList.remove('show');
  document.getElementById('gusTopicTxt').textContent = '—';
  iniciarLeccion();
}

// ── TTS: GUS habla ─────────────────────
function gusHabla(texto) {
  if (!gusTTSActivo || !window.speechSynthesis) return;
  window.speechSynthesis.cancel();
  // Limpiar texto de emojis y markdown
  const limpio = texto.replace(/[\u{1F000}-\u{1FFFF}]/gu,'').replace(/\*+/g,'').replace(/#+/g,'').substring(0, 300);
  if (!limpio.trim()) return;

  const utt = new SpeechSynthesisUtterance(limpio);
  utt.lang = 'en-US';
  utt.rate = 0.92;
  utt.pitch = 0.85; // voz más grave = masculina
  // Priorizar voz masculina
  const voices = speechSynthesis.getVoices();
  const voz = voices.find(v => v.name === 'Google UK English Male')
           || voices.find(v => v.name === 'Microsoft David Desktop')
           || voices.find(v => v.name === 'Microsoft David - English (United States)')
           || voices.find(v => v.name.toLowerCase().includes('david'))
           || voices.find(v => v.lang.startsWith('en') && v.name.toLowerCase().includes('male'))
           || voices.find(v => v.lang === 'en-US' && !v.name.toLowerCase().includes('female') && !v.name.toLowerCase().includes('zira') && !v.name.toLowerCase().includes('samantha'))
           || voices.find(v => v.lang.startsWith('en-US'))
           || voices.find(v => v.lang.startsWith('en'));
  if (voz) utt.voice = voz;
  speechSynthesis.speak(utt);
}

// Esperar a que voices estén listas
if (window.speechSynthesis) {
  speechSynthesis.onvoiceschanged = () => {};
  setTimeout(() => speechSynthesis.getVoices(), 100);
}

// ── Micrófono ──────────────────────────
function toggleMic() {
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    agregarMensaje('⚠️ Tu navegador no soporta reconocimiento de voz. Usa Chrome.', 'system');
    return;
  }
  if (gusMicActive) {
    gusRecognizer.stop();
    return;
  }
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  gusRecognizer = new SpeechRecognition();
  gusRecognizer.lang = 'en-US';
  gusRecognizer.interimResults = false;
  gusRecognizer.maxAlternatives = 1;

  gusRecognizer.onstart = () => {
    gusMicActive = true;
    document.getElementById('gusMicBtn').classList.add('listening');
    document.getElementById('gusMicBtn').textContent = '🔴';
    setGusStatus('Escuchando... habla en inglés');
  };
  gusRecognizer.onresult = (e) => {
    const texto = e.results[0][0].transcript;
    document.getElementById('gusInput').value = texto;
    setGusStatus('GUS está respondiendo...');
    setTimeout(enviarMensaje, 300);
  };
  gusRecognizer.onerror = () => {
    agregarMensaje('⚠️ No pude escucharte, intenta de nuevo', 'system');
  };
  gusRecognizer.onend = () => {
    gusMicActive = false;
    document.getElementById('gusMicBtn').classList.remove('listening');
    document.getElementById('gusMicBtn').textContent = '🎤';
    setGusStatus('En clase · ' + (document.getElementById('gusTopicTxt').textContent||''));
  };
  gusRecognizer.start();
}

// ── Helpers UI ─────────────────────────
function agregarMensaje(texto, tipo, tts = false) {
  const chat = document.getElementById('gusChat');
  const div  = document.createElement('div');
  div.className = 'gus-msg ' + tipo;
  // Convertir saltos de línea y **negrita**
  div.innerHTML = texto
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g,'<br>');
  // Insertar antes del indicador de typing
  const typing = document.getElementById('gusTyping');
  chat.insertBefore(div, typing);
  chat.scrollTop = chat.scrollHeight;
  if (tts && tipo === 'gus' && !voiceModeActive) gusHabla(texto);
}

function mostrarTyping(show) {
  const t = document.getElementById('gusTyping');
  if (t) t.className = 'gus-typing' + (show ? ' show' : '');
  const chat = document.getElementById('gusChat');
  if (chat) chat.scrollTop = chat.scrollHeight;
}

function setGusStatus(txt) {
  const s = document.getElementById('gusStatus');
  if (s) s.textContent = txt;
}

function mostrarCelebracion(xp) {
  document.getElementById('gusCelXP').textContent = '+' + xp + ' XP';
  document.getElementById('gusCelebrate').classList.add('show');
  setGusStatus('¡Lección completada! 🎉');
  // Confetti pequeño dentro del modal
  window.speechSynthesis && window.speechSynthesis.cancel();
  if (window.speechSynthesis) {
    const utt = new SpeechSynthesisUtterance('Excellent work! You completed the lesson!');
    utt.lang = 'en-US'; utt.rate = 0.9;
    speechSynthesis.speak(utt);
  }
}

// ── Historial de lecciones ─────────────
let historialVisible = false;
function toggleHistorial() {
  const panel = document.getElementById('gusHistPanel');
  historialVisible = !historialVisible;
  panel.classList.toggle('open', historialVisible);
  if (historialVisible) cargarHistorial();
}

function cargarHistorial() {
  fetch('api/gus.php', {
    method: 'POST',
    body: new URLSearchParams({ accion: 'historial' })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.ok || !d.lecciones) return;
    const cont = document.getElementById('gusHistContent');
    if (!d.lecciones.length) {
      cont.innerHTML = '<div style="text-align:center;padding:8px;color:rgba(255,255,255,.4)">Sin lecciones aún</div>';
      return;
    }
    cont.innerHTML = d.lecciones.map(l => `
      <div class="gus-hist-item">
        <div class="gus-hist-dot ${l.completada?'':'pending'}"></div>
        <div style="flex:1">
          <div style="color:${l.completada?'var(--ing-text)':'rgba(255,255,255,.5)'};font-weight:700">${escHtml(l.tema)}</div>
          <div style="color:rgba(255,255,255,.35);font-size:11px">${l.nivel} · ${l.completada?'+'+l.xp_ganados+' XP':'en progreso'}</div>
        </div>
        <div style="font-size:11px;color:rgba(255,255,255,.3)">${formatFecha(l.created_at)}</div>
      </div>
    `).join('');
  });
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function formatFecha(f) {
  if (!f) return '';
  const d = new Date(f);
  return d.toLocaleDateString('es-CO', {day:'numeric', month:'short'});
}

// ════════════════════════════════════════
// GUS VOICE MODE — Conversación continua
// ════════════════════════════════════════
let voiceModeActive  = false;
let voiceOrbState    = 'idle';
let voiceRec         = null;
let voiceRecRunning  = false;
let ttsQueue         = [];
let ttsPlaying       = false;
let streamBuf        = '';
let capturedSpeech   = '';
let silTimer         = null;

function toggleModoVoz() {
  voiceModeActive ? desactivarModoVoz() : activarModoVoz();
}

function activarModoVoz() {
  if (!window.SpeechRecognition && !window.webkitSpeechRecognition) {
    agregarMensaje('⚠️ Usa Google Chrome para el modo voz.', 'system'); return;
  }
  voiceModeActive = true;
  document.getElementById('gusVoicePanel').classList.add('vopen');
  document.getElementById('gusVoiceToggle').classList.add('vactive');
  document.getElementById('gusVoiceToggle').textContent = '🔴';
  // Update topic label
  const tema = document.getElementById('gusTopicTxt').textContent;
  document.getElementById('voiceTopicLbl').textContent = tema !== '—' ? tema : 'Preparando lección...';
  if (!gusLeccionId && !gusCargando) iniciarLeccion();
  else if (gusLeccionId) { setOrb('listening'); startVoiceRec(); }
}

function desactivarModoVoz() {
  voiceModeActive = false;
  document.getElementById('gusVoicePanel').classList.remove('vopen', 'spk');
  document.getElementById('gusVoiceToggle').classList.remove('vactive');
  document.getElementById('gusVoiceToggle').textContent = '🎤';
  if (voiceRec) { try { voiceRec.stop(); } catch(e){} }
  voiceRecRunning = false;
  window.speechSynthesis.cancel();
  ttsQueue = []; ttsPlaying = false;
  clearTimeout(silTimer);
  setOrb('idle');
}

function orbTap() {
  if (voiceOrbState === 'speaking') {
    // Interrupt: stop TTS, start listening
    window.speechSynthesis.cancel();
    ttsQueue = []; ttsPlaying = false;
    document.getElementById('gusVoicePanel').classList.remove('spk');
    setOrb('listening'); startVoiceRec();
  } else if (voiceOrbState === 'idle') {
    activarModoVoz();
  }
}

function setOrb(state) {
  voiceOrbState = state;
  const orb = document.getElementById('gusOrb');
  const lbl = document.getElementById('orbStateLbl');
  const panel = document.getElementById('gusVoicePanel');
  if (!orb) return;
  orb.className = 'gus-orb-wrap ' + state;
  const map = {
    idle:      ['Toca el orbe para activar', 'rgba(255,255,255,.4)'],
    listening: ['🎤 Escuchando... habla en inglés', '#3B82F6'],
    thinking:  ['🤔 GUS está pensando...', '#F5C842'],
    speaking:  ['🔊 GUS está hablando  •  toca para interrumpir', '#10B981'],
  };
  const [txt, color] = map[state] || map.idle;
  if (lbl) { lbl.textContent = txt; lbl.style.color = color; }
  if (panel) panel.classList.toggle('spk', state === 'speaking');
}

function startVoiceRec() {
  if (!voiceModeActive || voiceRecRunning) return;
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  voiceRec = new SR();
  voiceRec.lang = 'en-US';
  voiceRec.continuous = false;
  voiceRec.interimResults = true;
  voiceRecRunning = true;
  capturedSpeech = '';

  voiceRec.onstart = () => {
    setOrb('listening');
    document.getElementById('voiceTxtUser').textContent = '';
  };

  voiceRec.onresult = (e) => {
    clearTimeout(silTimer);
    let interim = ''; capturedSpeech = '';
    for (let i = 0; i < e.results.length; i++) {
      if (e.results[i].isFinal) capturedSpeech += e.results[i][0].transcript + ' ';
      else interim += e.results[i][0].transcript;
    }
    const showing = (capturedSpeech + interim).trim();
    document.getElementById('voiceTxtUser').textContent = showing ? '"' + showing + '"' : '';
    if (capturedSpeech.trim()) {
      silTimer = setTimeout(() => { try { voiceRec.stop(); } catch(e){} }, 900);
    }
  };

  voiceRec.onend = () => {
    voiceRecRunning = false;
    const text = capturedSpeech.trim();
    if (text && voiceModeActive) {
      sendVoiceMsg(text);
    } else if (voiceModeActive && voiceOrbState === 'listening') {
      setTimeout(() => { if (voiceModeActive && voiceOrbState === 'listening') startVoiceRec(); }, 250);
    }
  };

  voiceRec.onerror = (e) => {
    voiceRecRunning = false;
    if (e.error === 'no-speech' || e.error === 'aborted') {
      if (voiceModeActive && voiceOrbState === 'listening')
        setTimeout(() => startVoiceRec(), 300);
    } else {
      document.getElementById('voiceTxtUser').textContent = '⚠️ ' + e.error;
    }
  };

  try { voiceRec.start(); } catch(e) {
    voiceRecRunning = false;
    setTimeout(() => startVoiceRec(), 500);
  }
}

function sendVoiceMsg(texto) {
  if (!gusLeccionId || gusCargando || gusLeccionDone) return;
  clearTimeout(silTimer);
  setOrb('thinking');
  agregarMensaje(texto, 'user', false);
  document.getElementById('voiceTxtGus').textContent = '';
  document.getElementById('voiceTxtUser').textContent = '"' + texto + '"';
  enviarVozStream(texto);
}

async function enviarVozStream(texto) {
  gusCargando = true;
  streamBuf = ''; ttsQueue = []; ttsPlaying = false;
  try {
    const resp = await fetch('api/gus_stream.php', {
      method: 'POST',
      body: new URLSearchParams({ leccion_id: gusLeccionId, mensaje: texto })
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);

    const reader = resp.body.getReader();
    const dec = new TextDecoder();
    let raw = '', fullText = '', firstChunk = true;

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      raw += dec.decode(value, { stream: true });
      const lines = raw.split('\n'); raw = lines.pop() ?? '';

      for (const line of lines) {
        if (!line.startsWith('data: ')) continue;
        let data; try { data = JSON.parse(line.slice(6)); } catch { continue; }

        if (data.error) {
          setOrb('listening');
          document.getElementById('voiceTxtGus').textContent = '⚠️ ' + data.error;
          setTimeout(() => { if (voiceModeActive) startVoiceRec(); }, 800);
          return;
        }
        if (data.chunk) {
          fullText += data.chunk;
          streamBuf += data.chunk;
          document.getElementById('voiceTxtGus').textContent =
            fullText.replace(/\[LECCION_COMPLETADA\]/g,'').trim();
          if (firstChunk) { firstChunk = false; setOrb('speaking'); }
          speakPending();
        }
        if (data.done) {
          const rem = streamBuf.replace(/\[LECCION_COMPLETADA\]/g,'').trim();
          if (rem) { queueVTTS(rem); streamBuf = ''; }
          if (data.full) agregarMensaje(data.full, 'gus', false);
          if (data.completada) {
            gusLeccionDone = true; gusLeccionId = null;
            const iv = setInterval(() => {
              if (!ttsPlaying && !ttsQueue.length) {
                clearInterval(iv); desactivarModoVoz();
                setTimeout(() => mostrarCelebracion(data.xp || 0), 400);
              }
            }, 300);
          }
        }
      }
    }
  } catch(e) {
    setOrb('listening');
    document.getElementById('voiceTxtGus').textContent = '⚠️ Error de conexión';
    setTimeout(() => { if (voiceModeActive) startVoiceRec(); }, 1000);
  } finally { gusCargando = false; }
}

function speakPending() {
  while (true) {
    const m = streamBuf.match(/^(.*?[.!?])\s+(.*)/s);
    if (!m) break;
    const sent = m[1].replace(/\[LECCION_COMPLETADA\]/g,'').trim();
    streamBuf = m[2];
    if (sent.length > 2) queueVTTS(sent);
  }
}

function queueVTTS(text) {
  text = text.replace(/[*_#\[\]]/g,'').replace(/\[LECCION_COMPLETADA\]/g,'').trim();
  if (!text) return;
  ttsQueue.push(text);
  if (!ttsPlaying) processVQueue();
}

function processVQueue() {
  if (!ttsQueue.length) {
    ttsPlaying = false;
    if (voiceModeActive && !gusLeccionDone) {
      setTimeout(() => {
        if (voiceModeActive && !gusLeccionDone) {
          document.getElementById('gusVoicePanel').classList.remove('spk');
          setOrb('listening');
          document.getElementById('voiceTxtUser').textContent = '';
          startVoiceRec();
        }
      }, 500);
    }
    return;
  }
  ttsPlaying = true;
  const text = ttsQueue.shift();
  const utt  = new SpeechSynthesisUtterance(text);
  utt.lang = 'en-US'; utt.rate = 0.92; utt.pitch = 0.85;
  const vs = speechSynthesis.getVoices();
  const voz = vs.find(v => v.name === 'Google UK English Male')
           || vs.find(v => v.name === 'Microsoft David Desktop')
           || vs.find(v => v.name === 'Microsoft David - English (United States)')
           || vs.find(v => v.name.toLowerCase().includes('david'))
           || vs.find(v => v.lang.startsWith('en') && v.name.toLowerCase().includes('male'))
           || vs.find(v => v.lang === 'en-US' && !v.name.toLowerCase().includes('female') && !v.name.toLowerCase().includes('zira') && !v.name.toLowerCase().includes('samantha'))
           || vs.find(v => v.lang.startsWith('en-US'))
           || vs.find(v => v.lang.startsWith('en'));
  if (voz) utt.voice = voz;
  utt.onend = utt.onerror = () => setTimeout(processVQueue, 80);
  speechSynthesis.speak(utt);
}

// Voice mode hook: después de que GUS saluda en iniciarLeccion,
// habla el primer mensaje y pasa a escuchar automáticamente.
// Se llama desde iniciarLeccion() cuando voiceModeActive está activo.
function voiceHandleGusMessage(texto) {
  const clean = texto.replace(/[*_#\[\]]/g,'').substring(0, 300).trim();
  document.getElementById('voiceTxtGus').textContent = clean;
  setOrb('speaking');
  queueVTTS(clean);
}
</script>
<?php endif; ?>
</body>
</html>
