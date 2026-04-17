/**
 * INTEP Cursos API — Guardado de progreso, certificados y UI global
 * Requiere que la página defina window.__CURSO = {nivel:'A1', num:1}
 * antes de cargar este script.
 */
(function () {
    'use strict';

    // ── Guardar progreso ────────────────────────────────────────
    window.INTEP_guardarProgreso = function (opciones) {
        var curso = window.__CURSO || {};
        var nivel = opciones.nivel || curso.nivel || 'A1';
        var num   = opciones.num   || curso.num   || 1;
        var pct   = opciones.porcentaje  !== undefined ? opciones.porcentaje  : 100;
        var done  = opciones.completado  !== undefined ? opciones.completado  : true;
        var xp    = opciones.xp_ganado   !== undefined ? opciones.xp_ganado   : 50;

        return fetch('/intep/cursoingles/api/progreso.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nivel:       nivel,
                modulo_num:  num,
                porcentaje:  pct,
                completado:  done,
                xp_ganado:   xp,
            }),
        })
        .then(function (r) { return r.json(); })
        .catch(function (e) { console.warn('INTEP API error:', e); });
    };

    // ── Agregar botón de certificado en el modal de victoria ────
    function inyectarBotonCertificado() {
        var curso = window.__CURSO || {};
        if (!curso.nivel || !curso.num) return;

        var victoryBox = document.querySelector('.victory-box') || document.querySelector('#victoryModal .victory-box');
        if (!victoryBox) return;

        // Evitar duplicados
        if (victoryBox.querySelector('.cert-btn-intep')) return;

        var certUrl = '/intep/cursoingles/certificado.php?nivel=' + encodeURIComponent(curso.nivel) + '&modulo=' + encodeURIComponent(curso.num);

        var btn = document.createElement('a');
        btn.href = certUrl;
        btn.target = '_blank';
        btn.className = 'cert-btn-intep';
        btn.innerHTML = '📜 Ver Certificado';
        btn.style.cssText = [
            'display:block',
            'width:100%',
            'padding:13px',
            'margin-top:10px',
            'border-radius:12px',
            'border:2px solid #6366f1',
            'background:transparent',
            'color:#6366f1',
            'font-family:inherit',
            'font-weight:700',
            'font-size:1rem',
            'cursor:pointer',
            'text-align:center',
            'text-decoration:none',
            'transition:background 0.2s',
            'box-sizing:border-box',
        ].join(';');
        btn.onmouseover = function(){ btn.style.background='rgba(99,102,241,0.1)'; };
        btn.onmouseout  = function(){ btn.style.background='transparent'; };

        // Insertar antes del último botón del modal
        victoryBox.appendChild(btn);
    }

    // ── Parchar triggerVictory (módulos A1/A2/B1) ───────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Botón flotante "← Portal INTEP" (visible en móvil cuando no hay sidebar)
        var portalBtn = document.createElement('a');
        portalBtn.href = '/intep/dashboard.php';
        portalBtn.innerHTML = '🏠 Portal';
        portalBtn.style.cssText = [
            'position:fixed',
            'bottom:20px',
            'left:20px',
            'background:rgba(15,23,42,0.85)',
            'color:#cbd5e1',
            'padding:10px 18px',
            'border-radius:25px',
            'font-family:Outfit,sans-serif',
            'font-size:0.85rem',
            'font-weight:600',
            'text-decoration:none',
            'border:1px solid rgba(255,255,255,0.15)',
            'backdrop-filter:blur(8px)',
            'z-index:999',
            'transition:all 0.2s',
            'display:none',       // solo en móvil
        ].join(';');
        portalBtn.onmouseover = function(){ portalBtn.style.background='rgba(99,102,241,0.85)'; portalBtn.style.color='white'; };
        portalBtn.onmouseout  = function(){ portalBtn.style.background='rgba(15,23,42,0.85)';   portalBtn.style.color='#cbd5e1'; };
        // Mostrar solo si NO hay sidebar visible (pantallas pequeñas o páginas de módulo)
        function checkPortalBtn() {
            var sidebar = document.querySelector('.sidebar') || document.querySelector('.back-nav');
            if (!sidebar || window.innerWidth <= 900) {
                portalBtn.style.display = 'inline-block';
            } else {
                portalBtn.style.display = 'none';
            }
        }
        document.body.appendChild(portalBtn);
        checkPortalBtn();
        window.addEventListener('resize', checkPortalBtn);

        // Parchar triggerVictory
        var originalFn = window.triggerVictory;
        if (typeof originalFn === 'function' && window.__CURSO) {
            window.triggerVictory = function () {
                originalFn();
                window.INTEP_guardarProgreso({
                    nivel:      window.__CURSO.nivel,
                    num:        window.__CURSO.num,
                    porcentaje: 100,
                    completado: true,
                    xp_ganado:  50,
                }).then(function() {
                    // Una vez guardado en el server, inyectar botón de certificado
                    inyectarBotonCertificado();
                });
            };
        }

        // Parchar completarModulo (cursos Kids)
        var originalKids = window.completarModulo;
        if (typeof originalKids === 'function' && window.__CURSO) {
            window.completarModulo = function () {
                originalKids();
                window.INTEP_guardarProgreso({
                    nivel:      window.__CURSO.nivel,
                    num:        window.__CURSO.num,
                    porcentaje: 100,
                    completado: true,
                    xp_ganado:  30,
                }).then(function() {
                    inyectarBotonCertificado();
                });
            };
        }
    });

    // ── Mini-juego: sincronizar wins con el servidor ────────────
    window.INTEP_getWins = function () {
        return parseInt(localStorage.getItem('lingua_builder_wins')) || 0;
    };
    window.INTEP_saveWin = function () {
        var wins = window.INTEP_getWins() + 1;
        localStorage.setItem('lingua_builder_wins', wins);
        if (window.__INTEP && window.__INTEP.ok) {
            window.INTEP_guardarProgreso({
                nivel:      'A1',
                num:        0,
                porcentaje: 100,
                completado: true,
                xp_ganado:  50,
            });
        }
    };
})();
