#!/usr/bin/env python3
"""
Kokoro TTS — Microservicio para INTEP / GUS
=============================================
Instalar dependencias (en Termux):
    pip install kokoro soundfile

Voces masculinas disponibles:
    am_adam   → American English male  (recomendado para GUS)
    am_echo   → American English male
    bm_george → British English male

Ejecutar:
    python3 scripts/kokoro_server.py

Escucha en: http://127.0.0.1:5001
Endpoint:   POST /tts  { text: "Hello world" }
Health:     GET  /health
"""

import os
import sys
import hashlib
import numpy as np
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import parse_qs

# ── Directorios ───────────────────────────────────────────────
BASE_DIR  = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE_DIR = os.path.join(BASE_DIR, 'tts_cache')
os.makedirs(CACHE_DIR, exist_ok=True)

PORT  = int(os.environ.get('TTS_PORT', 5001))
VOICE = os.environ.get('TTS_VOICE', 'am_adam')   # voz GUS
SPEED = float(os.environ.get('TTS_SPEED', '0.9'))

# ── Cargar modelo ─────────────────────────────────────────────
print("Cargando modelo Kokoro... (puede tardar la primera vez)", flush=True)
try:
    from kokoro import KPipeline
    import soundfile as sf
except ImportError as e:
    print(f"\n[ERROR] Dependencia faltante: {e}")
    print("Instala con:  pip install kokoro soundfile\n")
    sys.exit(1)

pipeline = KPipeline(lang_code='a')   # 'a' = American English
print(f"✓ Kokoro listo. Voz: {VOICE} | Velocidad: {SPEED}", flush=True)
print(f"✓ Escuchando en http://127.0.0.1:{PORT}", flush=True)


def generar_audio(text: str) -> str | None:
    """Genera WAV y retorna la ruta al archivo. None si falla."""
    h          = hashlib.md5(f"{VOICE}:{text}".encode()).hexdigest()
    cache_file = os.path.join(CACHE_DIR, h + '.wav')

    if os.path.exists(cache_file) and os.path.getsize(cache_file) > 500:
        return cache_file

    try:
        chunks = []
        for _, _, audio in pipeline(text, voice=VOICE, speed=SPEED):
            chunks.append(audio)

        if not chunks:
            return None

        combined = np.concatenate(chunks)
        sf.write(cache_file, combined, 24000)
        return cache_file
    except Exception as e:
        print(f"[TTS error] {e}", flush=True)
        return None


class TTSHandler(BaseHTTPRequestHandler):

    def do_GET(self):
        if self.path == '/health':
            self._respond(200, b'OK', 'text/plain')
        else:
            self._respond(404, b'Not found', 'text/plain')

    def do_POST(self):
        if self.path != '/tts':
            self._respond(404, b'Not found', 'text/plain')
            return

        length = int(self.headers.get('Content-Length', 0))
        body   = self.rfile.read(length).decode('utf-8', errors='ignore')
        params = parse_qs(body)
        text   = params.get('text', [''])[0].strip()[:500]

        if not text:
            self._respond(400, b'Empty text', 'text/plain')
            return

        cache_file = generar_audio(text)
        if cache_file is None:
            self._respond(500, b'TTS generation failed', 'text/plain')
            return

        with open(cache_file, 'rb') as f:
            data = f.read()

        self.send_response(200)
        self.send_header('Content-Type', 'audio/wav')
        self.send_header('Content-Length', str(len(data)))
        self.send_header('Cache-Control', 'public, max-age=86400')
        self.end_headers()
        self.wfile.write(data)

    def _respond(self, code: int, body: bytes, ctype: str):
        self.send_response(code)
        self.send_header('Content-Type', ctype)
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass   # silenciar logs de HTTP


if __name__ == '__main__':
    server = HTTPServer(('127.0.0.1', PORT), TTSHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nServidor TTS detenido.")
        server.server_close()
