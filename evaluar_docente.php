<?php
require_once 'config.php';
require_once __DIR__ . '/includes/alertas_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: dashboard.php');
    exit;
}

$nombre = sanitizeInput($_SESSION['usuario_nombre']);
$csrf = csrf_token();

// Apagar alerta titilante (si existe) ahora que el estudiante entró al módulo
$_eid_alerta = (int)($_SESSION['estudiante_id'] ?? 0);
if ($_eid_alerta > 0) {
    marcarAlertaVista($conexion, $_eid_alerta, 'evaluacion');
}

// Verificar si hay evaluacion activa
$res_ctrl = mysqli_query($conexion, "SELECT * FROM eval_control WHERE activa = 1 ORDER BY id DESC LIMIT 1");
$eval_activa = mysqli_fetch_assoc($res_ctrl);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluacion Docente - INTEP</title>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --green: #059669;
            --green-light: #10B981;
            --green-dark: #047857;
            --green-pale: #ECFDF5;
            --purple: #4A1942;
            --purple-mid: #6B3FA0;
            --purple-light: #9B6FCF;
            --purple-pale: #f3ecf8;
            --cream: #F5F2EC;
            --warm-white: #F0EDE6;
            --sand: #EBE7DF;
            --text: #2D2235;
            --text-mid: #5A4D66;
            --text-light: #8A7D96;
            --radius: 16px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Exo 2',sans-serif; background:var(--cream); color:var(--text); min-height:100vh; }

        .topbar {
            background: linear-gradient(135deg, #033d2e 0%, var(--green-dark) 50%, #0a5c3f 100%);
            color: white; padding: 15px 25px; display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .topbar-left { display:flex; align-items:center; gap:12px; }
        .topbar-left a { color:white; text-decoration:none; font-size:1.4em; }
        .topbar-left h1 { font-size:1.1em; font-weight:700; }
        .topbar-right { font-size:0.85em; opacity:0.8; }

        .header-text-section { background:var(--cream); padding:30px; text-align:center; }
        .header-text-section h1 { font-size:2em; color:var(--purple); font-weight:800; margin-bottom:8px; }
        .header-text-section p { color:var(--text-mid); font-size:1em; }
        .header-text-section .subtitle { font-size:0.85em; color:var(--text-light); margin-top:5px; letter-spacing:1px; }

        .container { max-width:900px; margin:0 auto; padding:0 20px 40px; }

        .form-section {
            background:white; border-radius:var(--radius); padding:30px; margin-top:25px;
            box-shadow:0 4px 20px rgba(74,25,66,0.06); border:1px solid rgba(74,25,66,0.05);
        }
        .section-title {
            display:flex; align-items:center; gap:12px; margin-bottom:25px;
            padding-bottom:15px; border-bottom:3px solid var(--green);
        }
        .section-title .icon {
            width:40px; height:40px; background:var(--green); color:white; border-radius:10px;
            display:flex; align-items:center; justify-content:center; font-size:1.1em;
        }
        .section-title h2 { font-size:1.2em; color:var(--purple); font-weight:700; }

        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }
        .form-group { display:flex; flex-direction:column; }
        .form-group.full-width { grid-column:1/-1; }
        .form-group label { font-weight:600; color:var(--text); margin-bottom:8px; font-size:0.9em; }
        .form-group label .required { color:#e74c3c; }
        .form-group select, .form-group textarea {
            padding:12px 15px; border:2px solid #e0e0e0; border-radius:10px;
            font-size:0.95em; font-family:'Exo 2',sans-serif; transition:all 0.3s ease;
        }
        .form-group select:focus, .form-group textarea:focus {
            outline:none; border-color:var(--green); box-shadow:0 0 0 4px rgba(5,150,105,0.1);
        }

        .info-notice {
            background:var(--purple-pale); border-left:4px solid var(--purple-mid);
            padding:15px 20px; border-radius:0 10px 10px 0; margin-bottom:20px;
            font-size:0.9em; color:var(--text-mid);
        }
        .info-notice i { color:var(--purple-mid); margin-right:8px; }

        .inactive-notice {
            background:white; border-radius:var(--radius); padding:60px 30px; margin-top:40px;
            text-align:center; box-shadow:0 4px 20px rgba(74,25,66,0.06);
        }
        .inactive-notice i { font-size:4em; color:var(--text-light); margin-bottom:20px; }
        .inactive-notice h2 { color:var(--purple); margin-bottom:10px; }
        .inactive-notice p { color:var(--text-mid); }

        /* Criteria table */
        .criteria-table { width:100%; border-collapse:collapse; margin-top:10px; }
        .criteria-table th { background:var(--purple); color:white; padding:15px; text-align:left; font-weight:600; }
        .criteria-table th:first-child { border-radius:10px 0 0 0; }
        .criteria-table th:last-child { border-radius:0 10px 0 0; text-align:center; width:180px; }
        .criteria-table td { padding:18px 15px; border-bottom:1px solid #eee; vertical-align:middle; }
        .criteria-table tr:nth-child(even) { background:var(--cream); }
        .criteria-table tr:hover { background:var(--green-pale); }
        .criteria-name { font-weight:700; color:var(--text); margin-bottom:8px; font-size:1em; }
        .criteria-description {
            font-size:0.83em; color:var(--purple-mid);
            background:var(--purple-pale); border-left:3px solid var(--purple-light);
            padding:7px 10px; border-radius:0 8px 8px 0; margin-top:4px;
            display:flex; align-items:flex-start; gap:6px;
        }
        .criteria-description::before { content:"\f05a"; font-family:"Font Awesome 6 Free"; font-weight:900; font-size:0.9em; color:var(--purple-light); flex-shrink:0; margin-top:1px; }
        .rating-cell { text-align:center; }
        .rating-options { display:flex; justify-content:center; gap:6px; }
        .rating-btn {
            width:40px; height:40px; border:2px solid #ddd; border-radius:8px; background:white;
            cursor:pointer; font-weight:700; font-size:0.9em; transition:all 0.3s ease;
            font-family:'Exo 2',sans-serif;
        }
        .rating-btn:hover { transform:scale(1.1); }
        .rating-btn.excellent { color:var(--green); border-color:var(--green); }
        .rating-btn.excellent.selected { background:var(--green); color:white; }
        .rating-btn.good { color:#3B82F6; border-color:#3B82F6; }
        .rating-btn.good.selected { background:#3B82F6; color:white; }
        .rating-btn.regular { color:#f59e0b; border-color:#f59e0b; }
        .rating-btn.regular.selected { background:#f59e0b; color:white; }
        .rating-btn.insufficient { color:#ef4444; border-color:#ef4444; }
        .rating-btn.insufficient.selected { background:#ef4444; color:white; }

        .rating-legend {
            display:flex; justify-content:center; gap:20px; margin-top:20px;
            flex-wrap:wrap; font-size:0.8em; color:var(--text-light);
        }
        .rating-legend span { display:flex; align-items:center; gap:6px; }
        .legend-dot { width:12px; height:12px; border-radius:50%; }
        .legend-dot.excellent { background:var(--green); }
        .legend-dot.good { background:#3B82F6; }
        .legend-dot.regular { background:#f59e0b; }
        .legend-dot.insufficient { background:#ef4444; }

        .observations-textarea { width:100%; min-height:100px; padding:15px; border:2px solid #e0e0e0; border-radius:10px; font-size:0.95em; font-family:'Exo 2',sans-serif; resize:vertical; }
        .observations-textarea:focus { outline:none; border-color:var(--green); }

        /* Summary */
        .summary-section {
            background:linear-gradient(135deg,#033d2e 0%,var(--green-dark) 100%);
            padding:30px; border-radius:var(--radius); margin-top:25px; color:white;
        }
        .summary-title { font-size:1.1em; margin-bottom:20px; font-weight:700; display:flex; align-items:center; gap:10px; }
        .summary-title i { color:var(--green-light); }
        .score-display { display:flex; justify-content:space-around; align-items:center; flex-wrap:wrap; gap:25px; }
        .score-item { text-align:center; }
        .score-value { font-size:2.8em; font-weight:800; color:white; line-height:1; }
        .score-label { font-size:0.8em; opacity:0.7; margin-top:5px; text-transform:uppercase; letter-spacing:1px; }
        .score-status { padding:10px 25px; border-radius:30px; font-weight:700; font-size:0.95em; }
        .score-status.excellent { background:var(--green); color:white; }
        .score-status.good { background:#3B82F6; color:white; }
        .score-status.regular { background:#f59e0b; color:white; }
        .score-status.insufficient { background:#ef4444; color:white; }
        .score-status.pending { background:rgba(255,255,255,0.2); color:white; }

        /* Buttons */
        .btn-container { display:flex; gap:15px; justify-content:center; flex-wrap:wrap; margin-top:30px; }
        .btn {
            padding:14px 35px; border:none; border-radius:30px; font-size:0.95em; font-weight:600;
            cursor:pointer; transition:all 0.3s ease; display:flex; align-items:center; gap:8px;
            font-family:'Exo 2',sans-serif;
        }
        .btn-success { background:var(--green); color:white; box-shadow:0 4px 15px rgba(5,150,105,0.3); }
        .btn-success:hover { background:var(--green-dark); transform:translateY(-2px); }
        .btn-success:disabled { opacity:0.5; cursor:not-allowed; transform:none; }
        .btn-secondary { background:var(--sand); color:var(--text); }
        .btn-secondary:hover { background:#ddd; }

        /* Toast */
        .toast {
            position:fixed; bottom:30px; right:30px; background:var(--green); color:white;
            padding:15px 25px; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,0.2);
            transform:translateY(100px); opacity:0; transition:all 0.3s ease; z-index:1000; font-weight:600;
        }
        .toast.show { transform:translateY(0); opacity:1; }
        .toast.error { background:#ef4444; }

        /* Thanks modal */
        .thanks-modal {
            position:fixed; inset:0; background:rgba(0,0,0,0.7); display:none;
            align-items:center; justify-content:center; z-index:10000; padding:20px;
        }
        .thanks-modal.show { display:flex; }
        .thanks-content {
            background:white; border-radius:20px; padding:50px 40px; max-width:500px;
            width:100%; text-align:center; animation:modalIn 0.4s ease;
        }
        @keyframes modalIn { from{transform:scale(0.8);opacity:0} to{transform:scale(1);opacity:1} }
        .thanks-icon { width:100px; height:100px; background:var(--green-pale); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px; }
        .thanks-icon i { font-size:50px; color:var(--green); }
        .thanks-content h2 { color:var(--purple); font-size:1.8em; margin-bottom:15px; }
        .thanks-content p { color:var(--text-mid); font-size:1.1em; margin-bottom:10px; }
        .thanks-message { font-size:0.95em!important; line-height:1.6; margin-top:15px!important; padding:15px; background:var(--green-pale); border-radius:10px; }
        .thanks-buttons { display:flex; gap:15px; justify-content:center; margin-top:30px; flex-wrap:wrap; }
        .thanks-buttons .btn { flex:1; min-width:150px; justify-content:center; }

        /* Selector docente */
        .docente-selector-wrap { position:relative; }
        .docente-search-input {
            width:100%; padding:14px 20px 14px 48px; border:2px solid #e0e0e0; border-radius:12px;
            font-family:'Exo 2',sans-serif; font-size:1em; transition:all 0.3s;
            background:white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' fill='%23059669' viewBox='0 0 24 24'%3E%3Cpath d='M10 2a8 8 0 105.293 14.293l4.707 4.707 1.414-1.414-4.707-4.707A8 8 0 0010 2zm0 2a6 6 0 110 12A6 6 0 0110 4z'/%3E%3C/svg%3E") no-repeat 14px center;
        }
        .docente-search-input:focus { outline:none; border-color:var(--green); box-shadow:0 0 0 4px rgba(5,150,105,0.1); }
        .docente-dropdown {
            position:absolute; top:calc(100% + 6px); left:0; right:0; background:white;
            border:2px solid var(--green); border-radius:12px; max-height:280px; overflow-y:auto;
            z-index:50; box-shadow:0 8px 30px rgba(74,25,66,0.15); display:none;
        }
        .docente-dropdown.open { display:block; }
        .docente-option {
            padding:14px 18px; cursor:pointer; border-bottom:1px solid #f0f0f0;
            display:flex; align-items:center; justify-content:space-between; gap:12px;
            transition:background 0.15s;
        }
        .docente-option:last-child { border-bottom:none; }
        .docente-option:hover { background:var(--green-pale); }
        .docente-option.ya-evaluado { opacity:0.5; cursor:not-allowed; }
        .docente-option .opt-name { font-weight:700; color:var(--text); font-size:0.95em; }
        .docente-option .opt-modulo { font-size:0.78em; color:var(--text-light); margin-top:2px; }
        .docente-option .opt-badge { background:var(--green); color:white; font-size:0.7em; font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap; flex-shrink:0; }

        .selected-docente-info {
            display:none; background:var(--green-pale); border:2px solid var(--green);
            border-radius:12px; padding:16px 20px; margin-top:12px;
            display:flex; align-items:center; gap:14px;
        }
        .selected-docente-info .avatar {
            width:48px; height:48px; background:var(--green); border-radius:50%;
            display:flex; align-items:center; justify-content:center; color:white; font-size:1.4em; flex-shrink:0;
        }
        .selected-docente-info .info-text .name { font-weight:700; color:var(--purple); font-size:1em; }
        .selected-docente-info .info-text .modulo { font-size:0.83em; color:var(--text-mid); margin-top:2px; }
        .selected-docente-info .change-btn {
            margin-left:auto; background:transparent; border:1px solid var(--green); color:var(--green);
            padding:6px 14px; border-radius:8px; font-size:0.82em; font-weight:600; cursor:pointer;
            font-family:'Exo 2',sans-serif; transition:all 0.2s; flex-shrink:0;
        }
        .selected-docente-info .change-btn:hover { background:var(--green); color:white; }

        .confidentiality-banner {
            background:var(--purple); color:white; padding:20px 30px; text-align:center; margin-top:30px;
        }
        .confidentiality-banner p { max-width:700px; margin:0 auto; font-size:0.95em; line-height:1.6; }
        .confidentiality-banner i { color:var(--green-light); margin-right:8px; }

        @media (max-width:768px) {
            .form-grid { grid-template-columns:1fr; }
            .score-display { flex-direction:column; }
            .criteria-table thead { display:none; }
            .criteria-table tr { display:block; margin-bottom:15px; border:1px solid #eee; border-radius:10px; }
            .criteria-table td { display:block; padding:10px; }
            .rating-options { justify-content:flex-start; }
            .btn-container { flex-direction:column; }
            .btn { width:100%; justify-content:center; }
            .docente-grid { grid-template-columns:1fr; }
        }

        @media print {
            body { background:white; }
            .topbar, .btn-container, .confidentiality-banner { display:none; }
            .form-section { box-shadow:none; border:1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i></a>
            <h1>Evaluacion Docente</h1>
        </div>
        <div class="topbar-right">
            <?php echo $nombre; ?> &middot; Estudiante
        </div>
    </div>

    <div class="header-text-section">
        <h1>Evaluacion de Desempeno Docente</h1>
        <p>Instituto Tecnico Pedagogico - INTEP</p>
        <p class="subtitle">Tu opinion ayuda a mejorar la calidad educativa</p>
    </div>

<?php if (!$eval_activa): ?>
    <div class="container">
        <div class="inactive-notice">
            <i class="fas fa-calendar-times"></i>
            <h2>Evaluacion no disponible</h2>
            <p>No hay un periodo de evaluacion activo en este momento. Consulta con secretaria o espera a que se habilite.</p>
            <br>
            <a href="dashboard.php" class="btn btn-secondary" style="display:inline-flex;"><i class="fas fa-home"></i> Volver al inicio</a>
        </div>
    </div>
<?php else: ?>
    <div class="container">
        <div class="form-section">
            <div class="section-title">
                <span class="icon"><i class="fas fa-chalkboard-teacher"></i></span>
                <h2>Selecciona al Docente a Evaluar</h2>
            </div>
            <div class="info-notice">
                <i class="fas fa-info-circle"></i>
                Busca el nombre del docente. Solo puedes evaluarlo <strong>una vez</strong> por periodo.
            </div>

            <div class="docente-selector-wrap">
                <input type="text" id="docenteSearch" class="docente-search-input"
                       placeholder="Escribe el nombre del docente..." autocomplete="off"
                       oninput="filtrarDocentes()" onfocus="abrirDropdown()">
                <div id="docenteDropdown" class="docente-dropdown"></div>
            </div>

            <div id="selectedDocenteInfo" class="selected-docente-info" style="display:none;">
                <div class="avatar"><i class="fas fa-user-tie"></i></div>
                <div class="info-text">
                    <div class="name" id="selectedNombre"></div>
                    <div class="modulo" id="selectedModulo"></div>
                </div>
                <button class="change-btn" onclick="cambiarDocente()"><i class="fas fa-exchange-alt"></i> Cambiar</button>
            </div>

            <div id="loadingDocentes" style="text-align:center;padding:20px;color:var(--text-light);">
                <i class="fas fa-spinner fa-spin"></i> Cargando docentes...
            </div>
        </div>

        <div id="formEvaluacion" style="display:none;">
            <div class="form-section">
                <div class="section-title">
                    <span class="icon"><i class="fas fa-clipboard-check"></i></span>
                    <h2>Criterios de Evaluacion</h2>
                </div>
                <div class="info-notice">
                    <i class="fas fa-star"></i>
                    Evalua cada criterio del 1 al 4:<br>
                    <strong>4 = Excelente</strong> | <strong>3 = Bueno</strong> | <strong>2 = Regular</strong> | <strong>1 = Insuficiente</strong>
                </div>
                <table class="criteria-table">
                    <thead><tr><th>Criterio de Evaluacion</th><th>Calificacion</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><div class="criteria-name">1. Dominio del Contenido</div><div class="criteria-description">El docente demuestra conocimiento profundo de la materia que imparte.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="1"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                        <tr>
                            <td><div class="criteria-name">2. Claridad en la Explicacion</div><div class="criteria-description">Explica los temas de manera clara, comprensible y organizada.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="2"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                        <tr>
                            <td><div class="criteria-name">3. Metodologia de Ensenanza</div><div class="criteria-description">Utiliza metodos variados y dinamicos que facilitan el aprendizaje.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="3"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                        <tr>
                            <td><div class="criteria-name">4. Relacion con los Estudiantes</div><div class="criteria-description">Mantiene una relacion respetuosa, accesible y empatica con los alumnos.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="4"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                        <tr>
                            <td><div class="criteria-name">5. Gestion del Aula</div><div class="criteria-description">Mantiene el orden, la disciplina y aprovecha el tiempo de clase.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="5"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                        <tr>
                            <td><div class="criteria-name">6. Evaluacion y Retroalimentacion</div><div class="criteria-description">Evalua de manera justa y proporciona retroalimentacion constructiva.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="6"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                        <tr>
                            <td><div class="criteria-name">7. Puntualidad y Asistencia</div><div class="criteria-description">Asiste puntualmente a clases y cumple con el calendario academico.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="7"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                        <tr>
                            <td><div class="criteria-name">8. Uso de Recursos Tecnologicos</div><div class="criteria-description">Utiliza adecuadamente recursos tecnologicos y materiales didacticos.</div></td>
                            <td class="rating-cell"><div class="rating-options" data-criteria="8"><button type="button" class="rating-btn excellent" data-value="4">4</button><button type="button" class="rating-btn good" data-value="3">3</button><button type="button" class="rating-btn regular" data-value="2">2</button><button type="button" class="rating-btn insufficient" data-value="1">1</button></div></td>
                        </tr>
                    </tbody>
                </table>
                <div class="rating-legend">
                    <span><span class="legend-dot excellent"></span>Excelente (4)</span>
                    <span><span class="legend-dot good"></span>Bueno (3)</span>
                    <span><span class="legend-dot regular"></span>Regular (2)</span>
                    <span><span class="legend-dot insufficient"></span>Insuficiente (1)</span>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">
                    <span class="icon"><i class="fas fa-comment-alt"></i></span>
                    <h2>Comentarios (Opcional)</h2>
                </div>
                <div class="form-group">
                    <label for="comentariosPositivos">Aspectos positivos del docente</label>
                    <textarea id="comentariosPositivos" class="observations-textarea" placeholder="Ej: Explica muy bien los temas, es muy paciente..."></textarea>
                </div>
                <div class="form-group" style="margin-top:20px;">
                    <label for="comentariosMejora">Aspectos que pueden mejorar</label>
                    <textarea id="comentariosMejora" class="observations-textarea" placeholder="Ej: Podria explicar mas despacio, usar mas recursos visuales..."></textarea>
                </div>
            </div>

            <div class="summary-section">
                <h3 class="summary-title"><i class="fas fa-chart-bar"></i> Resumen de Evaluacion</h3>
                <div class="score-display">
                    <div class="score-item">
                        <div class="score-value" id="totalScore">0</div>
                        <div class="score-label">Puntos</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value">32</div>
                        <div class="score-label">Maximo</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value" id="percentage">0%</div>
                        <div class="score-label">Porcentaje</div>
                    </div>
                    <div class="score-item">
                        <div class="score-status pending" id="status">Sin calificar</div>
                    </div>
                </div>
            </div>

            <div class="btn-container">
                <button type="button" class="btn btn-success" id="btnGuardar" onclick="guardarEvaluacion()">
                    <i class="fas fa-save"></i> Guardar Evaluacion
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-home"></i> Volver al inicio
                </button>
            </div>
        </div>
    </div>

    <div class="confidentiality-banner">
        <p>
            <i class="fas fa-shield-alt"></i>
            <strong>Confidencialidad garantizada:</strong> Esta evaluacion es completamente <strong>anonima</strong>.
            Tu identidad no sera visible para el docente. Los resultados se utilizan unicamente para mejorar la calidad educativa del INTEP.
        </p>
    </div>
<?php endif; ?>

    <div class="toast" id="toast"></div>

    <div class="thanks-modal" id="thanksModal">
        <div class="thanks-content">
            <div class="thanks-icon"><i class="fas fa-check-circle"></i></div>
            <h2>Gracias por tu participacion!</h2>
            <p>Tu evaluacion ha sido guardada exitosamente.</p>
            <p class="thanks-message">Tu opinion es muy valiosa para mejorar la calidad educativa en el INTEP. Con tu granito de arena, estamos construyendo un mejor ambiente de aprendizaje para todos.</p>
            <div class="thanks-buttons">
                <button class="btn btn-success" onclick="resetFormulario()"><i class="fas fa-redo"></i> Evaluar otro docente</button>
                <button class="btn btn-secondary" onclick="window.location.href='dashboard.php'"><i class="fas fa-home"></i> Volver al inicio</button>
            </div>
        </div>
    </div>

<?php if ($eval_activa): ?>
<script>
const CSRF_TOKEN = '<?php echo $csrf; ?>';
let selectedDocente = null;
let todosDocentes = [];
const ratings = {};

// Cargar todos los docentes
async function cargarDocentes() {
    try {
        const resp = await fetch('admin/api_eval_datos.php?action=docentes');
        const data = await resp.json();
        document.getElementById('loadingDocentes').style.display = 'none';

        if (data.error) { showToast(data.error, true); return; }

        todosDocentes = data.docentes || [];
        if (todosDocentes.length === 0) {
            document.getElementById('docenteSearch').placeholder = 'No hay docentes disponibles';
            document.getElementById('docenteSearch').disabled = true;
        }
    } catch (e) {
        document.getElementById('loadingDocentes').style.display = 'none';
        showToast('Error al cargar docentes', true);
    }
}

function filtrarDocentes() {
    const q = document.getElementById('docenteSearch').value.toLowerCase().trim();
    abrirDropdown(q);
}

function abrirDropdown(q = '') {
    const dd = document.getElementById('docenteDropdown');
    if (todosDocentes.length === 0) return;

    const filtrados = q
        ? todosDocentes.filter(d => d.docente_nombre.toLowerCase().includes(q))
        : todosDocentes;

    if (filtrados.length === 0) {
        dd.innerHTML = '<div style="padding:14px 18px;color:var(--text-light);font-size:0.9em;">No se encontraron docentes</div>';
    } else {
        dd.innerHTML = filtrados.map(d => `
            <div class="docente-option ${d.ya_evaluado ? 'ya-evaluado' : ''}"
                 onclick="${d.ya_evaluado ? 'void(0)' : `elegirDocente(${d.docente_id})`}">
                <div>
                    <div class="opt-name"><i class="fas fa-user-tie" style="color:var(--green);margin-right:7px;"></i>${escapeHtml(d.docente_nombre)}</div>
                    <div class="opt-modulo"><i class="fas fa-book" style="margin-right:5px;"></i>${escapeHtml(d.modulos)}</div>
                </div>
                ${d.ya_evaluado ? '<span class="opt-badge">✓ Evaluado</span>' : ''}
            </div>`).join('');
    }

    dd.classList.add('open');
}

function elegirDocente(docenteId) {
    const docente = todosDocentes.find(d => d.docente_id === docenteId);
    if (!docente) return;

    selectedDocente = docenteId;

    // Mostrar info del seleccionado
    document.getElementById('docenteSearch').value = '';
    document.getElementById('docenteDropdown').classList.remove('open');
    document.getElementById('selectedNombre').textContent = docente.docente_nombre;
    document.getElementById('selectedModulo').textContent = docente.modulos;
    document.getElementById('selectedDocenteInfo').style.display = 'flex';

    // Reset calificaciones
    Object.keys(ratings).forEach(k => delete ratings[k]);
    document.querySelectorAll('.rating-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('comentariosPositivos').value = '';
    document.getElementById('comentariosMejora').value = '';
    updateSummary();

    // Mostrar formulario
    document.getElementById('formEvaluacion').style.display = 'block';
    setTimeout(() => {
        document.getElementById('formEvaluacion').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

function cambiarDocente() {
    selectedDocente = null;
    document.getElementById('selectedDocenteInfo').style.display = 'none';
    document.getElementById('formEvaluacion').style.display = 'none';
    document.getElementById('docenteSearch').value = '';
    document.getElementById('docenteSearch').focus();
}

// Cerrar dropdown al hacer click fuera
document.addEventListener('click', e => {
    if (!document.querySelector('.docente-selector-wrap').contains(e.target)) {
        document.getElementById('docenteDropdown').classList.remove('open');
    }
});

// Rating buttons
document.querySelectorAll('.rating-options').forEach(container => {
    container.querySelectorAll('.rating-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const criterio = container.dataset.criteria;
            const value = parseInt(this.dataset.value);
            container.querySelectorAll('.rating-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            ratings[criterio] = value;
            updateSummary();
        });
    });
});

function updateSummary() {
    const values = Object.values(ratings);
    const total = values.reduce((s, v) => s + v, 0);
    const pct = values.length > 0 ? Math.round((total / 32) * 100) : 0;

    document.getElementById('totalScore').textContent = total;
    document.getElementById('percentage').textContent = pct + '%';

    const statusEl = document.getElementById('status');
    statusEl.className = 'score-status';
    if (values.length === 0) { statusEl.textContent = 'Sin calificar'; statusEl.classList.add('pending'); }
    else if (pct >= 90) { statusEl.textContent = 'Excelente'; statusEl.classList.add('excellent'); }
    else if (pct >= 75) { statusEl.textContent = 'Bueno'; statusEl.classList.add('good'); }
    else if (pct >= 50) { statusEl.textContent = 'Regular'; statusEl.classList.add('regular'); }
    else { statusEl.textContent = 'Insuficiente'; statusEl.classList.add('insufficient'); }
}

async function guardarEvaluacion() {
    if (!selectedDocente) {
        showToast('Selecciona un docente primero', true);
        return;
    }

    const criterios = Object.keys(ratings);
    if (criterios.length < 8) {
        showToast('Debes calificar los 8 criterios', true);
        return;
    }

    const btnGuardar = document.getElementById('btnGuardar');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    const calificaciones = criterios.map(c => ({
        criterio_id: parseInt(c),
        calificacion: ratings[c]
    }));

    try {
        const resp = await fetch('admin/api_eval_guardar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: CSRF_TOKEN,
                docente_id: selectedDocente,
                calificaciones: calificaciones,
                comentarios_positivos: document.getElementById('comentariosPositivos').value.trim(),
                comentarios_mejora: document.getElementById('comentariosMejora').value.trim()
            })
        });

        const data = await resp.json();

        if (data.ok) {
            document.getElementById('thanksModal').classList.add('show');
        } else {
            showToast(data.error || 'Error al guardar', true);
        }
    } catch (e) {
        showToast('Error de conexion', true);
    } finally {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Evaluacion';
    }
}

function resetFormulario() {
    document.getElementById('thanksModal').classList.remove('show');
    selectedDocente = null;
    document.getElementById('selectedDocenteInfo').style.display = 'none';
    Object.keys(ratings).forEach(k => delete ratings[k]);
    document.querySelectorAll('.rating-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('comentariosPositivos').value = '';
    document.getElementById('comentariosMejora').value = '';
    document.getElementById('formEvaluacion').style.display = 'none';
    updateSummary();
    cargarDocentes();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showToast(msg, isError) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast' + (isError ? ' error' : '');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

cargarDocentes();
</script>

<?php endif; ?>

<script src="/intep/sesion.js"></script>
</body>
</html>
