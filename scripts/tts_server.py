#!/usr/bin/env python3
"""
Edge TTS — Microservicio para INTEP / GUS
==========================================
Usa las voces neuronales de Microsoft (mismas que Azure) gratis.

Instalar en Termux:
    pip install edge-tts

Voces recomendadas (hombre, inglés):
    en-US-GuyNeural     → Americana (GUS por defecto)
    en-US-AndrewNeural  → Americana, más cálida
    en-GB-RyanNeural    → Británica

Ejecutar:
    python3 scripts/tts_server.py

Escucha en: http://127.0.0.1:5001
Endpoint:   POST /tts  { text: "Hello world" }
Health:     GET  /health
"""

import os
import sys
import hashlib
import asyncio
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import parse_qs

# ── Config ────────────────────────────────────────────────────
BASE_DIR  = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE_DIR = os.path.join(BASE_DIR, 'tts_cache')
os.makedirs(CACHE_DIR, exist_ok=True)

PORT  = int(os.environ.get('TTS_PORT', 5001))
VOICE = os.environ.get('TTS_VOICE', 'en-US-GuyNeural')

try:
    import edge_tts
except ImportError:
    print("[ERROR] Falta edge-tts. Instala con: pip install edge-tts")
    sys.exit(1)

print(f"✓ Edge TTS listo. Voz: {VOICE}", flush=True)
print(f"✓ Escuchando en http://127.0.0.1:{PORT}", flush=True)


async def _generar(text: str, path: str) -> bool:
    """Genera MP3 con edge-tts. Retorna True si tiene éxito."""
    try:
        comm = edge_tts.Communicate(text, VOICE)
        await comm.save(path)
        return os.path.exists(path) and os.path.getsize(path) > 500
    except Exception as e:
        print(f"[TTS error] {e}", flush=True)
        return False


def generar_audio(text: str) -> str | None:
    """Genera MP3 (cacheado) y retorna la ruta. None si falla."""
    h          = hashlib.md5(f"{VOICE}:{text}".encode()).hexdigest()
    cache_file = os.path.join(CACHE_DIR, h + '.mp3')

    if os.path.exists(cache_file) and os.path.getsize(cache_file) > 500:
        return cache_file

    ok = asyncio.run(_generar(text, cache_file))
    return cache_file if ok else None


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
        self.send_header('Content-Type', 'audio/mpeg')
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
        pass


if __name__ == '__main__':
    server = HTTPServer(('127.0.0.1', PORT), TTSHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nServidor TTS detenido.")
        server.server_close()
