/**
 * INTEP Cursos API — Guardado de progreso, continuidad entre dispositivos
 * y utilidades compartidas del curso.
 * Requiere window.__CURSO = { nivel: 'A1', num: 1 } o similar.
 */
(function () {
    'use strict';

    var curso = window.__CURSO || {};
    var nivel = (curso.nivel || 'A1').toString().toUpperCase();
    var modulo = parseInt(curso.num || 0, 10) || 0;
    var shouldTrackActivity = curso.trackActivity !== false;
    var defaultActivityType = textOrEmpty(curso.activityType) || 'lesson';
    var pagePath = window.location.pathname;
    var pageUrl = pagePath + window.location.search + window.location.hash;
    var activityListenersBound = false;

    function textOrEmpty(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function resolveDashboardPath(currentNivel) {
        return currentNivel === 'A2'
            ? '/intep/cursoingles/dashboard_a2.php'
            : (currentNivel === 'B1' || currentNivel === 'B2')
                ? '/intep/cursoingles/dashboard_b1.php'
                : '/intep/cursoingles/dashboard.php';
    }

    function getCourseConfig() {
        var currentCourse = window.__CURSO || curso || {};
        var hasCourseContext = textOrEmpty(currentCourse.nivel) !== '' || currentCourse.num !== undefined;
        var currentNivel = hasCourseContext ? (textOrEmpty(currentCourse.nivel).toUpperCase() || nivel) : '';
        var currentModulo = hasCourseContext ? parseInt(currentCourse.num, 10) : 0;
        if (Number.isNaN(currentModulo)) {
            currentModulo = hasCourseContext ? modulo : 0;
        }

        return {
            curso: currentCourse,
            hasCourseContext: hasCourseContext,
            nivel: currentNivel,
            modulo: currentModulo || 0,
            shouldTrackActivity: hasCourseContext && currentCourse.trackActivity !== false && shouldTrackActivity,
            activityType: textOrEmpty(currentCourse.activityType) || defaultActivityType,
            dashboardPath: hasCourseContext ? resolveDashboardPath(currentNivel) : ''
        };
    }

    function syncCoursePageMeta() {
        var current = getCourseConfig();
        window.INTEP_COURSE_PAGE = {
            nivel: current.nivel,
            modulo: current.modulo,
            dashboardPath: current.dashboardPath,
            pagePath: pagePath,
            pageUrl: pageUrl
        };
        return current;
    }

    function getPageTitle() {
        var h1 = document.querySelector('h1');
        if (h1) {
            return textOrEmpty(h1.textContent) || document.title;
        }
        return document.title;
    }

    function getCurrentSectionData() {
        var sections = Array.prototype.slice.call(document.querySelectorAll('.learning-section, .app-container > header, .quiz-header, .game-area, .player-container, .lyrics-viewport'));
        var best = null;
        var bestDelta = Number.POSITIVE_INFINITY;
        var viewportAnchor = window.innerHeight * 0.25;

        sections.forEach(function (section, index) {
            var rect = section.getBoundingClientRect();
            var delta = Math.abs(rect.top - viewportAnchor);
            if (delta < bestDelta) {
                best = section;
                bestDelta = delta;
            }

            if (!section.id) {
                section.id = 'section-' + (index + 1);
            }
        });

        if (!best) {
            return { id: '', title: '' };
        }

        var heading = best.querySelector('h2, h3, .section-title, .question-title, .quiz-title');
        return {
            id: best.id || '',
            title: heading ? textOrEmpty(heading.textContent) : ''
        };
    }

    function debounce(fn, wait) {
        var timeout = null;
        return function () {
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(null, args);
            }, wait);
        };
    }

    function safeFetch(url, options) {
        return fetch(url, options).catch(function (error) {
            console.warn('INTEP API error:', error);
            return null;
        });
    }

    window.INTEP_guardarProgreso = function (opciones) {
        var cfg = opciones || {};
        var current = syncCoursePageMeta();
        return safeFetch('/intep/cursoingles/api/progreso.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nivel: cfg.nivel || current.nivel,
                modulo_num: cfg.modulo_num !== undefined ? cfg.modulo_num : (cfg.num !== undefined ? cfg.num : current.modulo),
                porcentaje: cfg.porcentaje !== undefined ? cfg.porcentaje : 100,
                completado: cfg.completado !== undefined ? cfg.completado : true,
                xp_ganado: cfg.xp_ganado !== undefined ? cfg.xp_ganado : 50,
                aprobado: cfg.aprobado !== undefined ? cfg.aprobado : false
            })
        }).then(function (response) {
            return response ? response.json() : null;
        });
    };

    window.INTEP_guardarActividad = function (opciones) {
        var current = syncCoursePageMeta();
        if (!current.nivel || !current.shouldTrackActivity) {
            return Promise.resolve(null);
        }

        var section = getCurrentSectionData();
        var cfg = opciones || {};

        return safeFetch('/intep/cursoingles/api/actividad.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nivel: cfg.nivel || current.nivel,
                modulo_num: cfg.modulo_num !== undefined ? cfg.modulo_num : (cfg.num !== undefined ? cfg.num : current.modulo),
                page_path: pagePath,
                page_url: pageUrl,
                page_title: cfg.page_title || getPageTitle(),
                section_id: cfg.section_id !== undefined ? cfg.section_id : section.id,
                section_title: cfg.section_title !== undefined ? cfg.section_title : section.title,
                activity_type: cfg.activity_type || current.activityType
            })
        }).then(function (response) {
            return response ? response.json() : null;
        });
    };

    window.INTEP_obtenerUltimaActividad = function (nivelFiltro) {
        var query = nivelFiltro ? ('?nivel=' + encodeURIComponent(nivelFiltro)) : '';
        return safeFetch('/intep/cursoingles/api/actividad.php' + query, {
            method: 'GET',
            credentials: 'include'
        }).then(function (response) {
            return response ? response.json() : null;
        });
    };

    window.INTEP_abrirEnCelular = function () {
        var shareData = {
            title: getPageTitle(),
            text: 'Continua tu curso de ingles INTEP desde el celular.',
            url: window.location.href
        };

        if (navigator.share) {
            return navigator.share(shareData).catch(function () {
                return null;
            });
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(window.location.href).then(function () {
                window.alert('Enlace copiado. Abre este modulo en tu celular para continuar.');
            }).catch(function () {
                window.prompt('Copia este enlace y abrelo en tu celular:', window.location.href);
            });
        }

        window.prompt('Copia este enlace y abrelo en tu celular:', window.location.href);
        return Promise.resolve();
    };

    function injectMobileNav() {
        var current = syncCoursePageMeta();
        if (!current.hasCourseContext || !current.nivel) {
            return;
        }

        var nav = document.querySelector('.course-mobile-nav');
        if (!nav) {
            nav = document.createElement('nav');
            nav.className = 'course-mobile-nav';
            nav.setAttribute('aria-label', 'Navegacion del curso');
            nav.innerHTML = [
                '<a href="' + current.dashboardPath + '" class="cmn-item cmn-home"><span class="cmn-icon">🏠</span><span>Ruta</span></a>',
                '<button type="button" class="cmn-item cmn-button" data-course-action="continue"><span class="cmn-icon">▶</span><span>Seguir</span></button>',
                '<button type="button" class="cmn-item cmn-button" data-course-action="phone"><span class="cmn-icon">📱</span><span>Celular</span></button>',
                '<a href="/intep/cursoingles/logros.html" class="cmn-item"><span class="cmn-icon">🏆</span><span>Logros</span></a>'
            ].join('');
            document.body.appendChild(nav);

            var continueBtn = nav.querySelector('[data-course-action="continue"]');
            var phoneBtn = nav.querySelector('[data-course-action="phone"]');

            if (continueBtn) {
                continueBtn.addEventListener('click', function () {
                    var section = getCurrentSectionData();
                    if (section.id) {
                        document.getElementById(section.id).scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            }

            if (phoneBtn) {
                phoneBtn.addEventListener('click', function () {
                    window.INTEP_abrirEnCelular();
                });
            }
        }

        var homeLink = nav.querySelector('.cmn-home');
        if (homeLink) {
            homeLink.setAttribute('href', current.dashboardPath);
        }
    }

    function injectPhoneButton() {
        var current = syncCoursePageMeta();
        if (!current.hasCourseContext || !current.nivel || document.querySelector('.course-phone-btn')) {
            return;
        }

        var target = document.querySelector('.back-nav') || document.querySelector('.quiz-back') || document.querySelector('.dashboard-eyebrow') || document.querySelector('.close-btn');
        if (!target || !target.parentNode) {
            return;
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'course-phone-btn';
        btn.textContent = 'Seguir en mi celular';
        btn.addEventListener('click', function () {
            window.INTEP_abrirEnCelular();
        });
        target.parentNode.insertBefore(btn, target.nextSibling);
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        navigator.serviceWorker.register('/intep/sw.js').catch(function (error) {
            console.warn('Service worker registration failed:', error);
        });
    }

    function inyectarBotonCertificado() {
        var victoryBox = document.querySelector('.victory-box') || document.querySelector('#victoryModal .victory-box');
        var current = syncCoursePageMeta();
        if (!current.hasCourseContext || !victoryBox || !current.nivel || !current.modulo) {
            return;
        }

        if (victoryBox.querySelector('.cert-btn-intep')) {
            return;
        }

        var certUrl = '/intep/cursoingles/certificado.php?nivel=' + encodeURIComponent(current.nivel) + '&modulo=' + encodeURIComponent(current.modulo);
        var btn = document.createElement('a');
        btn.href = certUrl;
        btn.target = '_blank';
        btn.className = 'cert-btn-intep';
        btn.innerHTML = 'Ver certificado';
        victoryBox.appendChild(btn);
    }

    function patchVictoryFlow() {
        var originalFn = window.triggerVictory;
        if (typeof originalFn !== 'function' || window.__INTEP_VICTORY_PATCHED) {
            return;
        }

        window.__INTEP_VICTORY_PATCHED = true;

        window.triggerVictory = function () {
            originalFn();
            var current = syncCoursePageMeta();
            if (!current.hasCourseContext || !current.nivel || !current.modulo) {
                return;
            }

            var saveProgress = window.INTEP_guardarProgreso({
                nivel: current.nivel,
                num: current.modulo,
                porcentaje: 100,
                completado: true,
                xp_ganado: 50
            });

            var saveActivity = window.INTEP_guardarActividad({
                nivel: current.nivel,
                num: current.modulo,
                activity_type: 'lesson_complete',
                section_title: 'Modulo completado'
            });

            Promise.all([saveProgress, saveActivity]).then(function () {
                inyectarBotonCertificado();
            });
        };
    }

    function patchKidsCompletion() {
        var originalKids = window.completarModulo;
        if (typeof originalKids !== 'function' || window.__INTEP_KIDS_PATCHED) {
            return;
        }

        window.__INTEP_KIDS_PATCHED = true;

        window.completarModulo = function () {
            originalKids();
            var current = syncCoursePageMeta();
            if (!current.hasCourseContext || !current.nivel) {
                return;
            }

            Promise.all([
                window.INTEP_guardarProgreso({
                    nivel: current.nivel,
                    num: current.modulo,
                    porcentaje: 100,
                    completado: true,
                    xp_ganado: 30
                }),
                window.INTEP_guardarActividad({
                    nivel: current.nivel,
                    num: current.modulo,
                    activity_type: 'lesson_complete',
                    section_title: 'Modulo completado'
                })
            ]).then(function () {
                inyectarBotonCertificado();
            });
        };
    }

    function ensureActivityTracking() {
        var current = syncCoursePageMeta();
        if (!current.hasCourseContext || !current.nivel || !current.shouldTrackActivity || activityListenersBound) {
            return;
        }

        activityListenersBound = true;
        window.INTEP_guardarActividad({ activity_type: current.activityType });
        var debouncedActivity = debounce(function () {
            var activeConfig = syncCoursePageMeta();
            window.INTEP_guardarActividad({ activity_type: activeConfig.activityType });
        }, 900);

        window.addEventListener('scroll', debouncedActivity, { passive: true });
        window.addEventListener('beforeunload', function () {
            var activeConfig = syncCoursePageMeta();
            window.INTEP_guardarActividad({ activity_type: activeConfig.activityType });
        });
    }

    function refreshCourseLayer() {
        syncCoursePageMeta();
        injectMobileNav();
        injectPhoneButton();
        ensureActivityTracking();
    }

    function initCourseLayer() {
        if (window.__INTEP_COURSE_LAYER_READY) {
            return;
        }

        window.__INTEP_COURSE_LAYER_READY = true;
        registerServiceWorker();
        refreshCourseLayer();
        patchVictoryFlow();
        patchKidsCompletion();
    }

    window.INTEP_refreshCourseLayer = refreshCourseLayer;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCourseLayer);
    } else {
        initCourseLayer();
    }

    window.INTEP_getWins = function () {
        return parseInt(localStorage.getItem('lingua_builder_wins'), 10) || 0;
    };

    window.INTEP_saveWin = function () {
        var wins = window.INTEP_getWins() + 1;
        localStorage.setItem('lingua_builder_wins', wins);
        if (window.__INTEP && window.__INTEP.ok) {
            window.INTEP_guardarProgreso({
                nivel: 'A1',
                num: 0,
                porcentaje: 100,
                completado: true,
                xp_ganado: 50
            });
        }
    };
})();
