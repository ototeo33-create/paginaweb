// =============================================
// SISTEMA DE PROGRESO Y EXÁMENES - INTEP
// =============================================

const CourseSystem = {
    STORAGE_KEY: 'intep_course_progress',
    PASSING_SCORE: 70,
    
    init() {
        if (!localStorage.getItem(this.STORAGE_KEY)) {
            const initialProgress = {
                a1: { 
                    m1: { completed: true, examScore: 100, attempts: 1 },
                    m2: { completed: true, examScore: 85, attempts: 1 },
                    m3: { completed: false, examScore: 0, attempts: 0 },
                    m4: { completed: false, examScore: 0, attempts: 0 },
                    m5: { completed: false, examScore: 0, attempts: 0 },
                    m6: { completed: false, examScore: 0, attempts: 0 },
                    m7: { completed: false, examScore: 0, attempts: 0 },
                    m8: { completed: false, examScore: 0, attempts: 0 }
                },
                a2: {
                    m1: { completed: false, examScore: 0, attempts: 0 },
                    m2: { completed: false, examScore: 0, attempts: 0 },
                    m3: { completed: false, examScore: 0, attempts: 0 },
                    m4: { completed: false, examScore: 0, attempts: 0 },
                    m5: { completed: false, examScore: 0, attempts: 0 },
                    m6: { completed: false, examScore: 0, attempts: 0 },
                    m7: { completed: false, examScore: 0, attempts: 0 },
                    m8: { completed: false, examScore: 0, attempts: 0 }
                },
                b1: {
                    m1: { completed: false, examScore: 0, attempts: 0 },
                    m2: { completed: false, examScore: 0, attempts: 0 },
                    m3: { completed: false, examScore: 0, attempts: 0 },
                    m4: { completed: false, examScore: 0, attempts: 0 },
                    m5: { completed: false, examScore: 0, attempts: 0 },
                    m6: { completed: false, examScore: 0, attempts: 0 },
                    m7: { completed: false, examScore: 0, attempts: 0 },
                    m8: { completed: false, examScore: 0, attempts: 0 }
                },
                totalXP: 100,
                streak: 0
            };
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(initialProgress));
        }
    },
    
    getProgress() {
        return JSON.parse(localStorage.getItem(this.STORAGE_KEY));
    },
    
    saveProgress(progress) {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(progress));
    },
    
    isModuleUnlocked(level, module) {
        const progress = this.getProgress();
        const moduleNum = parseInt(module.replace('m', ''));
        
        if (moduleNum === 1) return true;
        
        const prevModule = `m${moduleNum - 1}`;
        return progress[level] && progress[level][prevModule] && progress[level][prevModule].completed;
    },
    
    completeModule(level, module, score) {
        const progress = this.getProgress();
        
        if (!progress[level]) progress[level] = {};
        if (!progress[level][module]) progress[level][module] = { completed: false, examScore: 0, attempts: 0 };
        
        progress[level][module].attempts++;
        progress[level][module].examScore = Math.max(progress[level][module].examScore, score);
        
        if (score >= this.PASSING_SCORE) {
            progress[level][module].completed = true;
            progress.totalXP += 50;
        }
        
        this.saveProgress(progress);
        return score >= this.PASSING_SCORE;
    },
    
    getModuleStatus(level, module) {
        const progress = this.getProgress();
        if (!progress[level] || !progress[level][module]) {
            return { completed: false, examScore: 0, attempts: 0, unlocked: module === 'm1' };
        }
        return {
            ...progress[level][module],
            unlocked: this.isModuleUnlocked(level, module)
        };
    },
    
    resetProgress() {
        localStorage.removeItem(this.STORAGE_KEY);
        this.init();
    }
};

// =============================================
// GENERADOR DE EXÁMENES
// =============================================

const ExamGenerator = {
    currentExam: null,
    currentLevel: null,
    currentModule: null,
    
    createExam(questions, level, module) {
        this.currentLevel = level;
        this.currentModule = module;
        this.currentExam = this.shuffleQuestions(questions);
        return this.currentExam;
    },
    
    shuffleQuestions(questions) {
        const shuffled = [...questions];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    },
    
    renderExam(containerId, onComplete) {
        const container = document.getElementById(containerId);
        if (!container || !this.currentExam) return;
        
        container.innerHTML = '';
        
        const examSection = document.createElement('section');
        examSection.className = 'learning-section exam-section';
        examSection.style.border = '2px solid var(--success)';
        
        let html = `
            <h2 class="section-title text-gradient">📝 EXAMEN DEL MÓDULO</h2>
            <p style="margin-bottom: 1.5rem; color: var(--text-muted);">Demuestra lo que aprendiste. Necesitas ${CourseSystem.PASSING_SCORE}% para aprobar.</p>
            <div class="exam-container">
        `;
        
        this.currentExam.forEach((q, qIndex) => {
            html += this.renderQuestion(q, qIndex);
        });
        
        html += `
                <div class="exam-submit-area">
                    <button onclick="ExamGenerator.submitExam(${onComplete ? 'true' : 'false'})" class="exam-submit-btn">
                        Enviar Examen ✓
                    </button>
                </div>
            </div>
        `;
        
        examSection.innerHTML = html;
        container.appendChild(examSection);
        
        this.onCompleteCallback = onComplete;
    },
    
    renderQuestion(question, index) {
        let html = `<div class="exam-question" data-question-index="${index}">`;
        html += `<h3 class="question-title">Pregunta ${index + 1}: ${question.question}</h3>`;
        
        if (question.type === 'multiple') {
            html += `<div class="options-grid">`;
            const shuffledOptions = question.shuffle !== false ? this.shuffleOptions(question.options) : question.options;
            shuffledOptions.forEach((opt, i) => {
                html += `
                    <label class="option-label">
                        <input type="radio" name="q${index}" value="${opt.value}" data-correct="${opt.correct}">
                        <span class="option-text">${opt.text}</span>
                    </label>
                `;
            });
            html += `</div>`;
        } else if (question.type === 'fill') {
            html += `
                <div class="fill-answer">
                    <input type="text" class="fill-input" data-correct="${question.correctAnswer}" placeholder="Escribe tu respuesta aquí...">
                    <button onclick="ExamGenerator.checkFill(this)" class="check-btn">Verificar</button>
                </div>
            `;
        } else if (question.type === 'match') {
            html += `<div class="match-container">`;
            html += `<p style="color: var(--text-muted); margin-bottom: 1rem;">${question.instruction}</p>`;
            html += `<div class="match-columns">`;
            
            const items = question.items || [];
            const shuffledRight = this.shuffleOptions([...items].map((item, i) => ({ text: item.right, value: i })));
            
            html += `<div class="match-left">`;
            items.forEach((item, i) => {
                html += `<div class="match-item left" data-match="${i}">${item.left}</div>`;
            });
            html += `</div>`;
            
            html += `<div class="match-right">`;
            shuffledRight.forEach((opt, i) => {
                html += `<div class="match-item right" data-value="${opt.value}">${opt.text}</div>`;
            });
            html += `</div>`;
            html += `</div>`;
        } else if (question.type === 'translate') {
            html += `
                <div class="translate-exercise">
                    <p style="color: var(--primary-light); margin-bottom: 1rem;">${question.sentence}</p>
                    <textarea class="translate-input" placeholder="Traduce la oración al inglés..." data-correct="${question.correctAnswer}"></textarea>
                </div>
            `;
        } else if (question.type === 'order') {
            html += `
                <div class="order-exercise">
                    <p style="color: var(--text-muted); margin-bottom: 1rem;">Ordena las palabras para formar una oración correcta:</p>
                    <div class="word-bank" data-correct-order="${question.correctOrder.join(',')}">
                        ${this.shuffleOptions(question.correctOrder.map((w, i) => ({ text: w, value: i }))).map(o => 
                            `<span class="word-chip" data-word="${o.text}">${o.text}</span>`
                        ).join('')}
                    </div>
                    <div class="order-dropzone" id="order-zone-${index}">
                        <p style="color: var(--text-muted); font-size: 0.9rem;">Arrastra las palabras aquí...</p>
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
        return html;
    },
    
    shuffleOptions(options) {
        const shuffled = [...options];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    },
    
    checkFill(button) {
        const input = button.previousElementSibling;
        const correct = input.dataset.correct.toLowerCase().trim();
        const userAnswer = input.value.toLowerCase().trim();
        
        if (userAnswer === correct) {
            input.classList.add('correct');
            input.classList.remove('incorrect');
        } else {
            input.classList.add('incorrect');
            input.classList.remove('correct');
        }
    },
    
    submitExam(hasCallback) {
        if (!this.currentExam) return;
        
        let score = 0;
        let total = 0;
        let results = [];
        
        this.currentExam.forEach((q, index) => {
            const questionEl = document.querySelector(`[data-question-index="${index}"]`);
            if (!questionEl) return;
            
            if (q.type === 'multiple') {
                const selected = questionEl.querySelector('input[type="radio"]:checked');
                total++;
                if (selected) {
                    const isCorrect = selected.dataset.correct === 'true';
                    if (isCorrect) {
                        score++;
                        selected.parentElement.classList.add('correct-answer');
                    } else {
                        selected.parentElement.classList.add('wrong-answer');
                        questionEl.querySelector(`input[data-correct="true"]`).parentElement.classList.add('correct-answer');
                    }
                }
                questionEl.querySelectorAll('input').forEach(i => i.disabled = true);
            } else if (q.type === 'fill') {
                const input = questionEl.querySelector('.fill-input');
                const correct = input.dataset.correct.toLowerCase().trim();
                const userAnswer = input.value.toLowerCase().trim();
                total++;
                if (userAnswer === correct) {
                    score++;
                    input.classList.add('correct');
                } else {
                    input.classList.add('incorrect');
                }
                input.disabled = true;
                button.disabled = true;
            }
        });
        
        const percentage = Math.round((score / total) * 100);
        const passed = percentage >= CourseSystem.PASSING_SCORE;
        
        CourseSystem.completeModule(this.currentLevel, this.currentModule, percentage);
        
        this.showResults(percentage, passed, score, total);
        
        if (this.onCompleteCallback && typeof this.onCompleteCallback === 'function') {
            this.onCompleteCallback(passed, percentage);
        }
    },
    
    showResults(percentage, passed, score, total) {
        const resultsModal = document.getElementById('examResultsModal');
        if (!resultsModal) {
            this.createResultsModal();
        }
        
        const modal = document.getElementById('examResultsModal');
        const scoreDisplay = document.getElementById('examScoreDisplay');
        const resultMessage = document.getElementById('examResultMessage');
        const resultDetails = document.getElementById('examResultDetails');
        
        scoreDisplay.textContent = `${percentage}%`;
        scoreDisplay.style.color = passed ? 'var(--success)' : 'var(--secondary)';
        
        if (passed) {
            resultMessage.innerHTML = '🎉 ¡Felicidades! ¡Aprobaste el examen!';
            resultMessage.style.color = 'var(--success)';
            document.getElementById('examRetryBtn').style.display = 'none';
            document.getElementById('examContinueBtn').style.display = 'inline-block';
        } else {
            resultMessage.innerHTML = '😅 No aprobaste. ¡Intenta de nuevo!';
            resultMessage.style.color = 'var(--secondary)';
            document.getElementById('examRetryBtn').style.display = 'inline-block';
            document.getElementById('examContinueBtn').style.display = 'none';
        }
        
        resultDetails.textContent = `Respuestas correctas: ${score} de ${total}`;
        
        modal.style.display = 'flex';
        
        if (passed) {
            this.triggerConfetti();
        }
    },
    
    createResultsModal() {
        const modal = document.createElement('div');
        modal.id = 'examResultsModal';
        modal.className = 'victory-modal-overlay';
        modal.innerHTML = `
            <div class="victory-box">
                <h2>Resultado del Examen</h2>
                <div id="examScoreDisplay" style="font-size: 4rem; font-weight: 800; margin: 20px 0;">0%</div>
                <p id="examResultMessage" style="font-size: 1.2rem; margin-bottom: 10px;"></p>
                <p id="examResultDetails" style="color: var(--text-muted); margin-bottom: 20px;"></p>
                <button id="examRetryBtn" onclick="ExamGenerator.retryExam()" style="width: 100%; padding: 15px; border-radius: 12px; border: none; background: var(--secondary); color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer; margin-bottom: 10px;">Reintentar Examen</button>
                <button id="examContinueBtn" onclick="ExamGenerator.continueAfterExam()" style="width: 100%; padding: 15px; border-radius: 12px; border: none; background: var(--success); color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer;">Continuar al Siguiente Módulo →</button>
            </div>
        `;
        document.body.appendChild(modal);
    },
    
    retryExam() {
        document.getElementById('examResultsModal').style.display = 'none';
        const examContainer = document.getElementById('examContainer');
        if (examContainer) {
            examContainer.innerHTML = '';
        }
    },
    
    continueAfterExam() {
        document.getElementById('examResultsModal').style.display = 'none';
        const level = this.currentLevel;
        const moduleNum = parseInt(this.currentModule.replace('m', ''));
        const nextModule = `m${moduleNum + 1}`;
        
        if (level === 'a1') {
            const pages = ['', 'modulo1.html', 'modulo2.html', 'lesson_rutinas.html', 'modulo4.html', 'modulo5.html', 'modulo6.html', 'modulo7.html', 'modulo8.html'];
            if (pages[moduleNum + 1]) {
                window.location.href = pages[moduleNum + 1];
            } else {
                window.location.href = 'dashboard_a2.html';
            }
        } else if (level === 'a2') {
            window.location.href = `modulo${moduleNum + 1}_a2.html`;
        } else if (level === 'b1') {
            window.location.href = `modulo${moduleNum + 1}_b1.html`;
        }
    },
    
    triggerConfetti() {
        if (typeof confetti !== 'undefined') {
            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#6366f1', '#ec4899', '#10b981', '#eab308']
            });
        }
    }
};

// =============================================
// INICIALIZACIÓN
// =============================================

document.addEventListener('DOMContentLoaded', () => {
    CourseSystem.init();
    initDragAndDrop();
});

function initDragAndDrop() {
    document.querySelectorAll('.word-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            this.classList.toggle('selected');
        });
    });
    
    document.querySelectorAll('.order-dropzone').forEach(zone => {
        zone.addEventListener('click', function(e) {
            if (e.target.classList.contains('word-chip')) {
                e.target.remove();
            } else {
                const selected = document.querySelector('.word-chip.selected');
                if (selected) {
                    this.appendChild(selected);
                    selected.classList.remove('selected');
                }
            }
        });
    });
}
