<?php
// Widget flotante "Lina" — Asistente Admin
// Incluir al final del <body> de cualquier pagina admin.
// Requiere sesion admin activa (verifica internamente y se oculta si no).
if (($_SESSION['usuario_rol'] ?? '') !== 'admin') return;
$asistente_csrf = csrf_token();
?>
<style>
/* ===== Widget Asistente Admin "Lina" ===== */
#lina-toggle {
    position: fixed;
    right: 22px;
    bottom: 22px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    font-size: 28px;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.4);
    z-index: 9998;
    transition: transform .2s, box-shadow .2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
#lina-toggle:hover { transform: scale(1.08); box-shadow: 0 10px 30px rgba(5,150,105,0.55); }
#lina-toggle:active { transform: scale(0.95); }
#lina-toggle .lina-dot {
    position: absolute;
    top: 8px; right: 8px;
    width: 12px; height: 12px;
    background: #fbbf24;
    border: 2px solid white;
    border-radius: 50%;
    display: none;
}

#lina-panel {
    position: fixed;
    right: 22px;
    bottom: 95px;
    width: 380px;
    max-width: calc(100vw - 30px);
    height: 540px;
    max-height: calc(100vh - 120px);
    background: white;
    border-radius: 18px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.25), 0 4px 12px rgba(0,0,0,0.08);
    z-index: 9999;
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(16,185,129,0.15);
    animation: lina-pop .25s ease-out;
}
#lina-panel.activo { display: flex; }
@keyframes lina-pop {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.lina-header {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.lina-header .avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}
.lina-header .info { flex: 1; min-width: 0; }
.lina-header .nombre { font-weight: 800; font-size: 15px; line-height: 1.1; }
.lina-header .estado { font-size: 11px; opacity: 0.9; display: flex; align-items: center; gap: 5px; margin-top: 2px; }
.lina-header .estado .dot { width: 7px; height: 7px; background: #4ade80; border-radius: 50%; box-shadow: 0 0 0 2px rgba(74,222,128,0.4); }
.lina-header .acciones { display: flex; gap: 4px; }
.lina-header .acciones button {
    background: rgba(255,255,255,0.18);
    border: none; color: white; cursor: pointer;
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; transition: background .15s;
}
.lina-header .acciones button:hover { background: rgba(255,255,255,0.32); }

.lina-mensajes {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.lina-msg {
    max-width: 85%;
    padding: 9px 13px;
    border-radius: 14px;
    font-size: 13.5px;
    line-height: 1.45;
    word-wrap: break-word;
    white-space: pre-wrap;
}
.lina-msg.user {
    align-self: flex-end;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-bottom-right-radius: 4px;
}
.lina-msg.assistant {
    align-self: flex-start;
    background: white;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.lina-msg.assistant.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.lina-msg.tools {
    align-self: flex-start;
    font-size: 11px;
    color: #64748b;
    background: #eef2f7;
    border-radius: 8px;
    padding: 5px 10px;
    font-style: italic;
}

.lina-typing {
    align-self: flex-start;
    background: white;
    border: 1px solid #e2e8f0;
    padding: 10px 14px;
    border-radius: 14px;
    border-bottom-left-radius: 4px;
    display: inline-flex; gap: 4px;
}
.lina-typing span {
    width: 7px; height: 7px;
    background: #94a3b8;
    border-radius: 50%;
    animation: lina-blink 1.2s infinite;
}
.lina-typing span:nth-child(2) { animation-delay: .15s; }
.lina-typing span:nth-child(3) { animation-delay: .3s; }
@keyframes lina-blink {
    0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
    40% { opacity: 1; transform: scale(1); }
}

.lina-sugerencias {
    display: flex; flex-wrap: wrap; gap: 6px;
    padding: 0 14px 8px;
    background: #f8fafc;
}
.lina-sugerencias button {
    background: white;
    border: 1px solid #d1fae5;
    color: #059669;
    font-size: 11.5px;
    padding: 6px 11px;
    border-radius: 99px;
    cursor: pointer;
    transition: all .15s;
    font-weight: 600;
}
.lina-sugerencias button:hover { background: #ecfdf5; border-color: #10b981; }

.lina-input-area {
    border-top: 1px solid #e2e8f0;
    padding: 10px 12px;
    background: white;
    display: flex; gap: 8px; align-items: flex-end;
}
.lina-input {
    flex: 1;
    border: 1px solid #d1d5db;
    border-radius: 18px;
    padding: 9px 14px;
    font-size: 13.5px;
    font-family: inherit;
    resize: none;
    outline: none;
    max-height: 90px;
    min-height: 38px;
    line-height: 1.4;
    transition: border-color .15s;
}
.lina-input:focus { border-color: #10b981; }
.lina-enviar {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    width: 40px; height: 38px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    transition: transform .1s, box-shadow .15s;
    flex-shrink: 0;
}
.lina-enviar:hover { box-shadow: 0 4px 12px rgba(16,185,129,0.4); }
.lina-enviar:active { transform: scale(0.92); }
.lina-enviar:disabled { opacity: 0.5; cursor: not-allowed; }

@media (max-width: 480px) {
    #lina-panel {
        right: 10px; left: 10px; bottom: 80px;
        width: auto; height: calc(100vh - 110px);
    }
    #lina-toggle { right: 16px; bottom: 16px; }
}
</style>

<button id="lina-toggle" title="Asistente Admin (Lina)" aria-label="Abrir asistente">
    <span>🤖</span>
    <span class="lina-dot" id="lina-dot"></span>
</button>

<div id="lina-panel" role="dialog" aria-label="Asistente Lina">
    <div class="lina-header">
        <div class="avatar">🤖</div>
        <div class="info">
            <div class="nombre">Lina</div>
            <div class="estado"><span class="dot"></span> Asistente Admin · IA</div>
        </div>
        <div class="acciones">
            <button id="lina-limpiar" title="Limpiar conversacion" aria-label="Limpiar">🗑️</button>
            <button id="lina-cerrar" title="Cerrar" aria-label="Cerrar">✕</button>
        </div>
    </div>

    <div class="lina-mensajes" id="lina-mensajes"></div>

    <div class="lina-sugerencias" id="lina-sugerencias">
        <button data-q="Envia una alerta de pago a todos los estudiantes morosos">💰 Alerta a morosos</button>
        <button data-q="Dame estadisticas generales del portal">📊 Estadisticas</button>
        <button data-q="Cuantos estudiantes hay activos hoy?">👥 Activos hoy</button>
    </div>

    <div class="lina-input-area">
        <textarea id="lina-input" class="lina-input" rows="1" placeholder="Pregunta o pide algo a Lina..."></textarea>
        <button id="lina-enviar" class="lina-enviar" aria-label="Enviar">➤</button>
    </div>
</div>

<script>
(function() {
    const CSRF_KEY_STORE = 'lina_csrf';
    const HIST_KEY = 'lina_historial';
    let csrfToken = <?php echo json_encode($asistente_csrf); ?>;

    const $toggle    = document.getElementById('lina-toggle');
    const $panel     = document.getElementById('lina-panel');
    const $mensajes  = document.getElementById('lina-mensajes');
    const $input     = document.getElementById('lina-input');
    const $enviar    = document.getElementById('lina-enviar');
    const $cerrar    = document.getElementById('lina-cerrar');
    const $limpiar   = document.getElementById('lina-limpiar');
    const $suger     = document.getElementById('lina-sugerencias');

    let historial = [];
    try {
        const saved = localStorage.getItem(HIST_KEY);
        if (saved) historial = JSON.parse(saved) || [];
    } catch (e) { historial = []; }

    function guardarHist() {
        try { localStorage.setItem(HIST_KEY, JSON.stringify(historial.slice(-20))); } catch (e) {}
    }

    function renderMsg(role, content, extra) {
        const div = document.createElement('div');
        div.className = 'lina-msg ' + role + (extra && extra.error ? ' error' : '');
        div.textContent = content;
        $mensajes.appendChild(div);
        $mensajes.scrollTop = $mensajes.scrollHeight;
        return div;
    }

    function renderTools(tools) {
        if (!tools || !tools.length) return;
        const nombres = tools.map(t => t.tool).join(', ');
        const div = document.createElement('div');
        div.className = 'lina-msg tools';
        div.textContent = '⚙️ ' + nombres;
        $mensajes.appendChild(div);
        $mensajes.scrollTop = $mensajes.scrollHeight;
    }

    function pintarHistorial() {
        $mensajes.innerHTML = '';
        if (historial.length === 0) {
            renderMsg('assistant', '¡Hola! Soy Lina, tu asistente administrativa. Puedo enviar alertas a estudiantes, consultar notas/cartera, mostrarte morosos y abrir paginas del panel. ¿En que te ayudo?');
            return;
        }
        historial.forEach(m => renderMsg(m.role, m.content));
    }
    pintarHistorial();

    function abrirPanel() {
        $panel.classList.add('activo');
        document.getElementById('lina-dot').style.display = 'none';
        setTimeout(() => $input.focus(), 100);
    }
    function cerrarPanel() { $panel.classList.remove('activo'); }

    $toggle.addEventListener('click', () => {
        $panel.classList.contains('activo') ? cerrarPanel() : abrirPanel();
    });
    $cerrar.addEventListener('click', cerrarPanel);

    $limpiar.addEventListener('click', () => {
        if (!confirm('¿Borrar la conversacion?')) return;
        historial = [];
        guardarHist();
        pintarHistorial();
    });

    $suger.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-q]');
        if (!b) return;
        $input.value = b.dataset.q;
        enviarMensaje();
    });

    $input.addEventListener('input', () => {
        $input.style.height = 'auto';
        $input.style.height = Math.min(90, $input.scrollHeight) + 'px';
    });
    $input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            enviarMensaje();
        }
    });
    $enviar.addEventListener('click', enviarMensaje);

    async function enviarMensaje() {
        const texto = $input.value.trim();
        if (!texto) return;
        $input.value = '';
        $input.style.height = 'auto';

        historial.push({ role: 'user', content: texto });
        guardarHist();
        renderMsg('user', texto);

        const typing = document.createElement('div');
        typing.className = 'lina-typing';
        typing.innerHTML = '<span></span><span></span><span></span>';
        $mensajes.appendChild(typing);
        $mensajes.scrollTop = $mensajes.scrollHeight;
        $enviar.disabled = true;

        try {
            const resp = await fetch('/intep/admin/api_asistente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    mensaje: texto,
                    historial: historial.slice(-12, -1) // sin el ultimo (lo manda en `mensaje`)
                }),
            });
            const data = await resp.json();
            typing.remove();
            $enviar.disabled = false;

            if (data.csrf_token) csrfToken = data.csrf_token;

            if (data.error) {
                renderMsg('assistant', '⚠️ ' + data.error, { error: true });
                return;
            }

            if (data.tools_usadas && data.tools_usadas.length) {
                renderTools(data.tools_usadas);
            }

            const respuesta = data.respuesta || 'Listo.';
            historial.push({ role: 'assistant', content: respuesta });
            guardarHist();
            renderMsg('assistant', respuesta);

            // Ejecutar acciones (abrir paginas)
            if (data.acciones && data.acciones.length) {
                data.acciones.forEach(a => {
                    if (a.type === 'abrir' && a.url) {
                        window.open(a.url, '_blank', 'noopener');
                    }
                });
            }
        } catch (e) {
            typing.remove();
            $enviar.disabled = false;
            renderMsg('assistant', '⚠️ No pude conectar con el asistente. Reintenta.', { error: true });
        }
    }
})();
</script>
