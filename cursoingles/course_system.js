// =============================================
// SISTEMA DE CURSO Y EXAMENES - INTEP
// =============================================

const CourseSystem = {
    STORAGE_KEY: 'intep_course_progress',
    PASSING_SCORE: 70,

    init() {
        if (!localStorage.getItem(this.STORAGE_KEY)) {
            const initialProgress = {
                a1: {},
                a2: {},
                b1: {},
                totalXP: 0,
                streak: 0
            };
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(initialProgress));
        }
    },

    getProgress() {
        return JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '{}');
    },

    saveProgress(progress) {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(progress));
    },

    normalizeLevel(level) {
        return (level || 'a1').toString().toLowerCase();
    },

    isModuleUnlocked(level, module) {
        const normalizedLevel = this.normalizeLevel(level);
        const progress = this.getProgress();
        const moduleNum = parseInt(String(module).replace('m', ''), 10);

        if (moduleNum <= 1) {
            return true;
        }

        const prevModule = 'm' + (moduleNum - 1);
        return !!(progress[normalizedLevel] && progress[normalizedLevel][prevModule] && progress[normalizedLevel][prevModule].completed);
    },

    completeModule(level, module, score) {
        const normalizedLevel = this.normalizeLevel(level);
        const progress = this.getProgress();

        if (!progress[normalizedLevel]) {
            progress[normalizedLevel] = {};
        }
        if (!progress[normalizedLevel][module]) {
            progress[normalizedLevel][module] = { completed: false, examScore: 0, attempts: 0 };
        }

        progress[normalizedLevel][module].attempts += 1;
        progress[normalizedLevel][module].examScore = Math.max(progress[normalizedLevel][module].examScore, score);

        if (score >= this.PASSING_SCORE) {
            progress[normalizedLevel][module].completed = true;
        }

        this.saveProgress(progress);
        return score >= this.PASSING_SCORE;
    },

    getModuleStatus(level, module) {
        const normalizedLevel = this.normalizeLevel(level);
        const progress = this.getProgress();
        const fallback = { completed: false, examScore: 0, attempts: 0 };

        const state = (progress[normalizedLevel] && progress[normalizedLevel][module])
            ? progress[normalizedLevel][module]
            : fallback;

        return {
            completed: !!state.completed,
            examScore: parseInt(state.examScore || 0, 10),
            attempts: parseInt(state.attempts || 0, 10),
            unlocked: this.isModuleUnlocked(normalizedLevel, module)
        };
    },

    resetProgress() {
        localStorage.removeItem(this.STORAGE_KEY);
        this.init();
    }
};

// =============================================
// GENERADOR DE EXAMENES
// =============================================

const ExamGenerator = {
    currentExam: null,
    currentLevel: null,
    currentModule: null,
    onCompleteCallback: null,

    createExam(questions, level, module) {
        this.currentLevel = (level || 'a1').toString().toLowerCase();
        this.currentModule = module;
        this.currentExam = this.shuffleQuestions(questions || []);
        return this.currentExam;
    },

    shuffleQuestions(questions) {
        const shuffled = Array.isArray(questions) ? [...questions] : [];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    },

    shuffleOptions(options) {
        const shuffled = [...options];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    },

    renderExam(containerId, onComplete) {
        const container = document.getElementById(containerId);
        if (!container || !this.currentExam) {
            return;
        }

        this.onCompleteCallback = onComplete;
        container.innerHTML = '';

        const examSection = document.createElement('section');
        examSection.className = 'learning-section exam-section';
        examSection.dataset.examRoot = 'true';

        let html = '';
        html += '<h2 class="section-title text-gradient">Examen del modulo</h2>';
        html += '<p style="margin-bottom:1.5rem;color:var(--text-muted);">Necesitas ' + CourseSystem.PASSING_SCORE + '% para aprobar.</p>';
        html += '<div class="exam-container">';

        this.currentExam.forEach((question, index) => {
            html += this.renderQuestion(question, index);
        });

        html += '<div class="exam-submit-area">';
        html += '<button type="button" class="exam-submit-btn" onclick="ExamGenerator.submitExam()">Enviar examen</button>';
        html += '</div>';
        html += '</div>';

        examSection.innerHTML = html;
        container.appendChild(examSection);
        this.bindInteractions(container);
    },

    renderQuestion(question, index) {
        let html = '<div class="exam-question" data-question-index="' + index + '">';
        html += '<h3 class="question-title">Pregunta ' + (index + 1) + ': ' + question.question + '</h3>';

        if (question.type === 'multiple') {
            html += '<div class="options-grid">';
            const options = question.shuffle === false ? question.options : this.shuffleOptions(question.options || []);
            options.forEach((option, optionIndex) => {
                html += '<label class="option-label">';
                html += '<input type="radio" name="q' + index + '" value="' + option.value + '" data-correct="' + String(!!option.correct) + '">';
                html += '<span class="option-text">' + option.text + '</span>';
                html += '</label>';
            });
            html += '</div>';
        } else if (question.type === 'fill') {
            html += '<div class="fill-answer">';
            html += '<input type="text" class="fill-input" data-correct="' + this.escapeAttr(question.correctAnswer || '') + '" placeholder="Escribe tu respuesta aqui...">';
            html += '<button type="button" class="check-btn" onclick="ExamGenerator.checkFill(this)">Verificar</button>';
            html += '</div>';
        } else if (question.type === 'translate') {
            html += '<div class="translate-exercise">';
            if (question.sentence) {
                html += '<p style="color:var(--primary-dark);margin-bottom:1rem;">' + question.sentence + '</p>';
            }
            html += '<textarea class="translate-input" placeholder="Escribe tu traduccion..." data-correct="' + this.escapeAttr(question.correctAnswer || '') + '"></textarea>';
            html += '</div>';
        } else if (question.type === 'order') {
            const orderWords = Array.isArray(question.correctOrder) ? question.correctOrder : [];
            html += '<div class="order-exercise">';
            html += '<p style="color:var(--text-muted);margin-bottom:1rem;">Ordena las palabras para formar una oracion correcta.</p>';
            html += '<div class="word-bank" data-correct-order="' + this.escapeAttr(orderWords.join('|')) + '">';
            this.shuffleOptions(orderWords.map(function (word, idx) {
                return { text: word, value: idx };
            })).forEach(function (option) {
                html += '<button type="button" class="word-chip" data-word="' + ExamGenerator.escapeAttr(option.text) + '">' + option.text + '</button>';
            });
            html += '</div>';
            html += '<div class="order-dropzone" data-empty-text="Toca las palabras en orden para construir la frase."></div>';
            html += '</div>';
        } else if (question.type === 'match') {
            const items = Array.isArray(question.items) ? question.items : [];
            html += '<div class="match-container" data-match-question="' + index + '">';
            if (question.instruction) {
                html += '<p style="color:var(--text-muted);margin-bottom:1rem;">' + question.instruction + '</p>';
            }
            html += '<div class="match-columns">';
            html += '<div>';
            items.forEach(function (item, itemIndex) {
                html += '<div class="match-item left" data-match="' + itemIndex + '">' + item.left + '</div>';
            });
            html += '</div>';
            html += '<div>';
            ExamGenerator.shuffleOptions(items.map(function (item, itemIndex) {
                return { text: item.right, value: itemIndex };
            })).forEach(function (option) {
                html += '<div class="match-item right" data-value="' + option.value + '">' + option.text + '</div>';
            });
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    },

    escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    normalizeAnswer(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s'-]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    },

    bindInteractions(root) {
        root.querySelectorAll('.word-bank').forEach((bank) => {
            const zone = bank.parentElement.querySelector('.order-dropzone');
            if (!zone) {
                return;
            }

            const updateZoneState = () => {
                if (!zone.querySelector('.word-chip')) {
                    zone.innerHTML = '<p style="color:var(--text-muted);font-size:0.9rem;">' + (zone.dataset.emptyText || 'Toca las palabras para ordenarlas.') + '</p>';
                }
            };

            updateZoneState();

            bank.querySelectorAll('.word-chip').forEach((chip) => {
                chip.addEventListener('click', () => {
                    zone.querySelector('p')?.remove();
                    zone.appendChild(chip);
                });
            });

            zone.addEventListener('click', (event) => {
                if (!event.target.classList.contains('word-chip')) {
                    return;
                }
                bank.appendChild(event.target);
                updateZoneState();
            });
        });

        root.querySelectorAll('[data-match-question]').forEach((questionEl) => {
            let leftSelected = null;
            let rightSelected = null;

            const clearSelection = () => {
                questionEl.querySelectorAll('.match-item.selected').forEach((item) => item.classList.remove('selected'));
                leftSelected = null;
                rightSelected = null;
            };

            questionEl.querySelectorAll('.match-item.left').forEach((item) => {
                item.addEventListener('click', () => {
                    if (item.classList.contains('matched')) {
                        return;
                    }
                    questionEl.querySelectorAll('.match-item.left.selected').forEach((el) => el.classList.remove('selected'));
                    item.classList.add('selected');
                    leftSelected = item;
                    this.tryResolveMatch(leftSelected, rightSelected, clearSelection);
                });
            });

            questionEl.querySelectorAll('.match-item.right').forEach((item) => {
                item.addEventListener('click', () => {
                    if (item.classList.contains('matched')) {
                        return;
                    }
                    questionEl.querySelectorAll('.match-item.right.selected').forEach((el) => el.classList.remove('selected'));
                    item.classList.add('selected');
                    rightSelected = item;
                    this.tryResolveMatch(leftSelected, rightSelected, clearSelection);
                });
            });
        });
    },

    tryResolveMatch(leftSelected, rightSelected, clearSelection) {
        if (!leftSelected || !rightSelected) {
            return;
        }

        const isCorrect = leftSelected.dataset.match === rightSelected.dataset.value;
        const mark = isCorrect ? 'matched' : 'wrong';
        leftSelected.classList.add(mark);
        rightSelected.classList.add(mark);

        setTimeout(() => {
            if (!isCorrect) {
                leftSelected.classList.remove('wrong', 'selected');
                rightSelected.classList.remove('wrong', 'selected');
            } else {
                leftSelected.classList.remove('selected');
                rightSelected.classList.remove('selected');
            }
            clearSelection();
        }, isCorrect ? 250 : 700);
    },

    checkFill(button) {
        const input = button.parentElement.querySelector('.fill-input');
        if (!input) {
            return;
        }

        const correct = this.normalizeAnswer(input.dataset.correct);
        const userAnswer = this.normalizeAnswer(input.value);
        const isCorrect = userAnswer === correct;

        input.classList.toggle('correct', isCorrect);
        input.classList.toggle('incorrect', !isCorrect);
    },

    evaluateQuestion(questionEl, question) {
        if (question.type === 'multiple') {
            const selected = questionEl.querySelector('input[type="radio"]:checked');
            questionEl.querySelectorAll('input[type="radio"]').forEach((input) => {
                input.disabled = true;
            });

            if (!selected) {
                const correctInput = questionEl.querySelector('input[data-correct="true"]');
                if (correctInput) {
                    correctInput.parentElement.classList.add('correct-answer');
                }
                return false;
            }

            const isCorrect = selected.dataset.correct === 'true';
            selected.parentElement.classList.add(isCorrect ? 'correct-answer' : 'wrong-answer');
            if (!isCorrect) {
                const correctInput = questionEl.querySelector('input[data-correct="true"]');
                if (correctInput) {
                    correctInput.parentElement.classList.add('correct-answer');
                }
            }
            return isCorrect;
        }

        if (question.type === 'fill') {
            const input = questionEl.querySelector('.fill-input');
            if (!input) {
                return false;
            }
            const isCorrect = this.normalizeAnswer(input.value) === this.normalizeAnswer(input.dataset.correct);
            input.classList.toggle('correct', isCorrect);
            input.classList.toggle('incorrect', !isCorrect);
            input.disabled = true;
            const btn = questionEl.querySelector('.check-btn');
            if (btn) {
                btn.disabled = true;
            }
            return isCorrect;
        }

        if (question.type === 'translate') {
            const input = questionEl.querySelector('.translate-input');
            if (!input) {
                return false;
            }
            const isCorrect = this.normalizeAnswer(input.value) === this.normalizeAnswer(input.dataset.correct);
            input.classList.toggle('correct', isCorrect);
            input.classList.toggle('incorrect', !isCorrect);
            input.disabled = true;
            return isCorrect;
        }

        if (question.type === 'order') {
            const bank = questionEl.querySelector('.word-bank');
            const zone = questionEl.querySelector('.order-dropzone');
            if (!bank || !zone) {
                return false;
            }

            const currentOrder = Array.from(zone.querySelectorAll('.word-chip')).map((chip) => chip.dataset.word);
            const correctOrder = (bank.dataset.correctOrder || '').split('|').filter(Boolean);
            const isCorrect = currentOrder.join('|') === correctOrder.join('|');
            zone.classList.toggle('correct', isCorrect);
            zone.classList.toggle('wrong', !isCorrect);
            return isCorrect;
        }

        if (question.type === 'match') {
            const totalItems = questionEl.querySelectorAll('.match-item.left').length;
            const matchedItems = questionEl.querySelectorAll('.match-item.left.matched').length;
            return totalItems > 0 && totalItems === matchedItems;
        }

        return false;
    },

    async submitExam() {
        if (!this.currentExam) {
            return;
        }

        let score = 0;
        let total = 0;

        this.currentExam.forEach((question, index) => {
            const questionEl = document.querySelector('[data-question-index="' + index + '"]');
            if (!questionEl) {
                return;
            }
            total += 1;
            if (this.evaluateQuestion(questionEl, question)) {
                score += 1;
            }
        });

        const percentage = total > 0 ? Math.round((score / total) * 100) : 0;
        const passed = percentage >= CourseSystem.PASSING_SCORE;

        CourseSystem.completeModule(this.currentLevel, this.currentModule, percentage);

        if (typeof window.INTEP_guardarProgreso === 'function' && window.__CURSO) {
            await window.INTEP_guardarProgreso({
                nivel: window.__CURSO.nivel,
                num: window.__CURSO.num,
                porcentaje: percentage,
                completado: passed,
                xp_ganado: passed ? 50 : 0,
                aprobado: passed
            });
        }

        if (typeof window.INTEP_guardarActividad === 'function' && window.__CURSO) {
            await window.INTEP_guardarActividad({
                nivel: window.__CURSO.nivel,
                num: window.__CURSO.num,
                activity_type: passed ? 'quiz_passed' : 'quiz_failed',
                section_title: 'Resultado del examen'
            });
        }

        this.showResults(percentage, passed, score, total);

        if (this.onCompleteCallback && typeof this.onCompleteCallback === 'function') {
            this.onCompleteCallback(passed, percentage);
        }
    },

    showResults(percentage, passed, score, total) {
        const modal = document.getElementById('examResultsModal') || this.createResultsModal();
        const scoreDisplay = document.getElementById('examScoreDisplay');
        const resultMessage = document.getElementById('examResultMessage');
        const resultDetails = document.getElementById('examResultDetails');
        const retryBtn = document.getElementById('examRetryBtn');
        const continueBtn = document.getElementById('examContinueBtn');

        scoreDisplay.textContent = percentage + '%';
        scoreDisplay.style.color = passed ? 'var(--success)' : 'var(--danger)';
        resultMessage.textContent = passed ? 'Aprobaste el examen.' : 'Aun no alcanzas el puntaje necesario.';
        resultMessage.style.color = passed ? 'var(--success)' : 'var(--danger)';
        resultDetails.textContent = 'Respuestas correctas: ' + score + ' de ' + total;

        retryBtn.style.display = passed ? 'none' : 'inline-block';
        continueBtn.style.display = passed ? 'inline-block' : 'none';
        modal.style.display = 'flex';

        if (passed) {
            this.triggerConfetti();
        }
    },

    createResultsModal() {
        const modal = document.createElement('div');
        modal.id = 'examResultsModal';
        modal.className = 'victory-modal-overlay';
        modal.innerHTML = ''
            + '<div class="victory-box">'
            + '  <h2>Resultado del examen</h2>'
            + '  <div id="examScoreDisplay" style="font-size:4rem;font-weight:800;margin:20px 0;">0%</div>'
            + '  <p id="examResultMessage" style="font-size:1.1rem;margin-bottom:10px;"></p>'
            + '  <p id="examResultDetails" style="color:var(--text-muted);margin-bottom:20px;"></p>'
            + '  <button id="examRetryBtn" onclick="ExamGenerator.retryExam()" style="width:100%;padding:15px;border-radius:12px;border:none;background:var(--secondary);color:white;font-weight:bold;font-size:1rem;cursor:pointer;margin-bottom:10px;">Reintentar examen</button>'
            + '  <button id="examContinueBtn" onclick="ExamGenerator.continueAfterExam()" style="width:100%;padding:15px;border-radius:12px;border:none;background:var(--success);color:white;font-weight:bold;font-size:1rem;cursor:pointer;display:none;">Continuar al siguiente modulo</button>'
            + '</div>';
        document.body.appendChild(modal);
        return modal;
    },

    retryExam() {
        const modal = document.getElementById('examResultsModal');
        if (modal) {
            modal.style.display = 'none';
        }
        if (this.currentExam) {
            this.currentExam = this.shuffleQuestions(this.currentExam);
            this.renderExam('examContainer', this.onCompleteCallback);
        }
    },

    continueAfterExam() {
        const modal = document.getElementById('examResultsModal');
        if (modal) {
            modal.style.display = 'none';
        }

        const moduleNum = parseInt(String(this.currentModule).replace('m', ''), 10);
        if (Number.isNaN(moduleNum)) {
            window.location.href = window.INTEP_COURSE_PAGE ? window.INTEP_COURSE_PAGE.dashboardPath : 'dashboard.php';
            return;
        }

        if (this.currentLevel === 'a1') {
            const pages = {
                1: 'modulo2.html',
                2: 'lesson_rutinas.html',
                3: 'modulo4.html',
                4: 'modulo5.html',
                5: 'modulo6.html',
                6: 'modulo7.html',
                7: 'modulo8.html'
            };
            window.location.href = pages[moduleNum] || '/intep/cursoingles/dashboard_a2.php';
            return;
        }

        if (this.currentLevel === 'a2') {
            window.location.href = moduleNum < 8
                ? 'modulo' + (moduleNum + 1) + '_a2.html'
                : '/intep/cursoingles/dashboard_a2.php';
            return;
        }

        if (this.currentLevel === 'b1') {
            window.location.href = moduleNum < 8
                ? 'modulo' + (moduleNum + 1) + '_b1.html'
                : '/intep/cursoingles/dashboard_b1.php';
            return;
        }

        window.location.href = window.INTEP_COURSE_PAGE ? window.INTEP_COURSE_PAGE.dashboardPath : 'dashboard.php';
    },

    triggerConfetti() {
        if (typeof confetti !== 'undefined') {
            confetti({
                particleCount: 90,
                spread: 70,
                origin: { y: 0.65 },
                colors: ['#78c98b', '#9dc4ea', '#c6b2ee']
            });
        }
    }
};

document.addEventListener('DOMContentLoaded', function () {
    CourseSystem.init();
});
