(function () {
    'use strict';

    var LEVEL_CONFIG = {
        A1: {
            label: 'A1 Base activa',
            challenge: 1,
            pairCount: 5,
            sessionCount: { builder: 5, match: 5, visual: 5, listening: 2 },
            lives: { builder: 4, visual: 5 }
        },
        A2: {
            label: 'A2 Situaciones reales',
            challenge: 2,
            pairCount: 6,
            sessionCount: { builder: 6, match: 6, visual: 6, listening: 2 },
            lives: { builder: 3, visual: 4 }
        },
        B1: {
            label: 'B1 Precision y contexto',
            challenge: 3,
            pairCount: 7,
            sessionCount: { builder: 7, match: 7, visual: 7, listening: 3 },
            lives: { builder: 3, visual: 4 }
        }
    };

    var MODE_META = {
        builder: {
            A1: { label: 'Constructor', short: 'Frases base', focus: 'Presentacion, rutinas y vida diaria' },
            A2: { label: 'Constructor', short: 'Situaciones A2', focus: 'Pasado, futuro y preguntas reales' },
            B1: { label: 'Constructor', short: 'Precision B1', focus: 'Conectores, opinion y estructuras largas' }
        },
        match: {
            A1: { label: 'Parejas', short: 'Vocabulario base', focus: 'Palabras de uso cotidiano y directo' },
            A2: { label: 'Parejas', short: 'Contexto A2', focus: 'Ciudad, salud, trabajo y viajes' },
            B1: { label: 'Parejas', short: 'Ideas B1', focus: 'Conceptos, medios y vocabulario mas abstracto' }
        },
        visual: {
            A1: { label: 'Visual', short: 'Reconocimiento base', focus: 'Objetos, lugares y acciones concretas' },
            A2: { label: 'Visual', short: 'Escenas A2', focus: 'Contextos funcionales y lectura rapida' },
            B1: { label: 'Visual', short: 'Precision visual', focus: 'Interpretacion y vocabulario fino' }
        },
        listening: {
            A1: { label: 'Escucha y aprende', short: 'Dialogos base', focus: 'Saludo, clase y rutina' },
            A2: { label: 'Escucha y aprende', short: 'Dialogos situacionales', focus: 'Direcciones, planes y oficina' },
            B1: { label: 'Escucha y aprende', short: 'Listening inferencial', focus: 'Opinion, trabajo y comprension fina' }
        }
    };

    var BADGES = {
        A1: [
            { id: 'builder-a1', mode: 'builder', field: 'wins', threshold: 2, icon: '🧱', title: 'Armando ideas', description: 'Completa 2 rondas del Constructor A1.' },
            { id: 'match-a1', mode: 'match', field: 'wins', threshold: 2, icon: '🧩', title: 'Conector rapido', description: 'Completa 2 rondas de Parejas A1.' },
            { id: 'visual-a1', mode: 'visual', field: 'wins', threshold: 2, icon: '👀', title: 'Ojo ingles', description: 'Completa 2 rondas Visual A1.' },
            { id: 'listen-a1', mode: 'listening', field: 'completed', threshold: 2, icon: '🎧', title: 'Oido base', description: 'Resuelve 2 dialogos de escucha A1.' },
            { id: 'consistency-a1', mode: 'builder', field: 'plays_total', threshold: 6, icon: '🔥', title: 'Constancia A1', description: 'Suma 6 sesiones de practica en A1.' }
        ],
        A2: [
            { id: 'builder-a2', mode: 'builder', field: 'wins', threshold: 3, icon: '🧱', title: 'Frases con contexto', description: 'Completa 3 rondas del Constructor A2.' },
            { id: 'match-a2', mode: 'match', field: 'wins', threshold: 3, icon: '🧩', title: 'Mapa mental', description: 'Completa 3 rondas de Parejas A2.' },
            { id: 'visual-a2', mode: 'visual', field: 'wins', threshold: 3, icon: '👀', title: 'Lectura rapida', description: 'Completa 3 rondas Visual A2.' },
            { id: 'listen-a2', mode: 'listening', field: 'completed', threshold: 2, icon: '🎧', title: 'Escucha situacional', description: 'Resuelve 2 dialogos de escucha A2.' },
            { id: 'consistency-a2', mode: 'builder', field: 'plays_total', threshold: 8, icon: '⚡', title: 'Impulso A2', description: 'Suma 8 sesiones de practica en A2.' }
        ],
        B1: [
            { id: 'builder-b1', mode: 'builder', field: 'wins', threshold: 4, icon: '🧱', title: 'Sintaxis segura', description: 'Completa 4 rondas del Constructor B1.' },
            { id: 'match-b1', mode: 'match', field: 'wins', threshold: 4, icon: '🧩', title: 'Red de ideas', description: 'Completa 4 rondas de Parejas B1.' },
            { id: 'visual-b1', mode: 'visual', field: 'wins', threshold: 4, icon: '👀', title: 'Precision visual', description: 'Completa 4 rondas Visual B1.' },
            { id: 'listen-b1', mode: 'listening', field: 'completed', threshold: 3, icon: '🎧', title: 'Oido analitico', description: 'Resuelve 3 dialogos de escucha B1.' },
            { id: 'consistency-b1', mode: 'builder', field: 'plays_total', threshold: 10, icon: '🏁', title: 'Constancia B1', description: 'Suma 10 sesiones de practica en B1.' }
        ]
    };

    function normalizeLevel(value) {
        var level = (value || 'A1').toString().trim().toUpperCase();
        if (level === 'B2') {
            return 'B1';
        }
        return LEVEL_CONFIG[level] ? level : 'A1';
    }

    function getCurrentLevel() {
        return normalizeLevel((window.__CURSO && window.__CURSO.nivel) || (window.__INTEP && window.__INTEP.nivel) || 'A1');
    }

    function getLevelConfig(level) {
        return LEVEL_CONFIG[normalizeLevel(level)];
    }

    function getModeMeta(mode, level) {
        var currentLevel = normalizeLevel(level);
        var modeSet = MODE_META[mode] || {};
        return modeSet[currentLevel] || modeSet.A1 || {};
    }

    function getStatsKey(mode, level) {
        return 'intep_practice_' + mode + '_' + normalizeLevel(level).toLowerCase();
    }

    function getStats(mode, level) {
        try {
            return JSON.parse(localStorage.getItem(getStatsKey(mode, level)) || '{}');
        } catch (error) {
            return {};
        }
    }

    function saveStats(mode, partial, level) {
        var currentLevel = normalizeLevel(level);
        var current = getStats(mode, currentLevel);
        var next = Object.assign({ wins: 0, plays: 0 }, current, partial || {});
        localStorage.setItem(getStatsKey(mode, currentLevel), JSON.stringify(next));
        return next;
    }

    function notePlay(mode, level) {
        var currentLevel = normalizeLevel(level);
        var current = getStats(mode, currentLevel);
        return saveStats(mode, {
            plays: (current.plays || 0) + 1,
            lastPlayedAt: new Date().toISOString()
        }, currentLevel);
    }

    function noteWin(mode, extra, level) {
        var currentLevel = normalizeLevel(level);
        var current = getStats(mode, currentLevel);
        return saveStats(mode, Object.assign({}, extra || {}, {
            wins: (current.wins || 0) + 1,
            lastWonAt: new Date().toISOString()
        }), currentLevel);
    }

    function noteListeningComplete(dialogId, score, level) {
        var currentLevel = normalizeLevel(level);
        var current = getStats('listening', currentLevel);
        var dialogs = Object.assign({}, current.dialogs || {});
        dialogs[dialogId] = Math.max(score || 0, dialogs[dialogId] || 0);

        var bestScore = current.bestScore || 0;
        if ((score || 0) > bestScore) {
            bestScore = score || 0;
        }

        return saveStats('listening', {
            completed: Object.keys(dialogs).length,
            dialogs: dialogs,
            bestScore: bestScore,
            lastDialog: dialogId,
            lastWonAt: new Date().toISOString()
        }, currentLevel);
    }

    function getAllModeStats(level) {
        return {
            builder: getStats('builder', level),
            match: getStats('match', level),
            visual: getStats('visual', level),
            listening: getStats('listening', level)
        };
    }

    function getPracticeSummary(level) {
        var currentLevel = normalizeLevel(level);
        var stats = getAllModeStats(currentLevel);
        var totalPlays = 0;
        var totalWins = 0;

        Object.keys(stats).forEach(function (key) {
            totalPlays += stats[key].plays || 0;
            totalWins += stats[key].wins || 0;
        });

        var badges = getBadges(currentLevel);
        var unlocked = badges.filter(function (badge) {
            return badge.unlocked;
        }).length;

        return {
            level: currentLevel,
            totalPlays: totalPlays,
            totalWins: totalWins,
            listeningCompleted: stats.listening.completed || 0,
            unlockedBadges: unlocked,
            totalBadges: badges.length,
            mastery: badges.length ? Math.round((unlocked / badges.length) * 100) : 0
        };
    }

    function getBadges(level) {
        var currentLevel = normalizeLevel(level);
        var badges = BADGES[currentLevel] || [];
        var totalPlays = getPracticeSummaryBase(currentLevel).totalPlays;

        return badges.map(function (badge) {
            var current = getStats(badge.mode, currentLevel);
            var value = badge.field === 'completed'
                ? (current.completed || 0)
                : (badge.field === 'plays' ? totalPlays : (current[badge.field] || 0));

            return Object.assign({}, badge, {
                value: value,
                unlocked: value >= badge.threshold,
                remaining: Math.max(0, badge.threshold - value)
            });
        });
    }

    function getPracticeSummaryBase(level) {
        var stats = getAllModeStats(level);
        var totalPlays = 0;
        Object.keys(stats).forEach(function (key) {
            totalPlays += stats[key].plays || 0;
        });
        return { totalPlays: totalPlays };
    }

    window.INTEPPractice = {
        normalizeLevel: normalizeLevel,
        getCurrentLevel: getCurrentLevel,
        getLevelConfig: getLevelConfig,
        getModeMeta: getModeMeta,
        getStats: getStats,
        saveStats: saveStats,
        notePlay: notePlay,
        noteWin: noteWin,
        noteListeningComplete: noteListeningComplete,
        getAllModeStats: getAllModeStats,
        getPracticeSummary: getPracticeSummary,
        getBadges: getBadges
    };
})();
