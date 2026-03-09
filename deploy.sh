#!/usr/bin/env bash
# =============================================================================
#  deploy.sh — Script de deployment con rollback automático
#  Ejecutado por webhook.php tras un push a master
# =============================================================================

set -euo pipefail

# ── Configuración ─────────────────────────────────────────────────────────────

REPO_PATH="${REPO_PATH:-/data/data/com.termux/files/home/intep}"
BRANCH="master"
REMOTE="origin"
LOG_PREFIX="[deploy.sh]"

# ── Funciones ─────────────────────────────────────────────────────────────────

log()  { echo "$(date '+%Y-%m-%d %H:%M:%S') $LOG_PREFIX $*"; }
fail() { log "ERROR: $*"; exit 1; }

# ── Ir al repositorio ─────────────────────────────────────────────────────────

cd "$REPO_PATH" || fail "No se puede acceder a $REPO_PATH"

log "===== Inicio de deployment ====="
log "Directorio: $(pwd)"

# ── Guardar commit actual para rollback ───────────────────────────────────────

PREV_COMMIT=$(git rev-parse HEAD)
log "Commit anterior: $PREV_COMMIT"

# ── Asegurar que estamos en master ────────────────────────────────────────────

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ "$CURRENT_BRANCH" != "$BRANCH" ]]; then
    log "Cambiando de '$CURRENT_BRANCH' a '$BRANCH'"
    git checkout "$BRANCH" || fail "No se pudo hacer checkout a $BRANCH"
fi

# ── Fetch + merge ─────────────────────────────────────────────────────────────

log "Ejecutando git fetch..."
git fetch "$REMOTE" "$BRANCH" || fail "git fetch falló"

log "Ejecutando git pull..."
if ! git pull "$REMOTE" "$BRANCH"; then
    log "ERROR: git pull falló — iniciando rollback a $PREV_COMMIT"
    git reset --hard "$PREV_COMMIT"
    fail "Deployment abortado. Rollback completado a $PREV_COMMIT"
fi

NEW_COMMIT=$(git rev-parse HEAD)
log "Nuevo commit: $NEW_COMMIT"

# ── Pasos post-pull (ajustar según el stack del proyecto) ─────────────────────

# PHP puro — no hay composer ni artisan, solo invalidar caché de opcache si aplica
if command -v php &>/dev/null; then
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
    log "PHP $PHP_VER detectado"
fi

# Si en el futuro se agrega composer:
# if [ -f "composer.json" ]; then
#     log "Instalando dependencias composer..."
#     composer install --no-dev --optimize-autoloader || {
#         log "ERROR: composer install falló — rollback"
#         git reset --hard "$PREV_COMMIT"
#         fail "Rollback completado"
#     }
# fi

# ── Ajustar permisos de uploads ───────────────────────────────────────────────

if [ -d "$REPO_PATH/uploads" ]; then
    chmod -R 755 "$REPO_PATH/uploads"
    log "Permisos de uploads ajustados"
fi

# ── Resumen final ─────────────────────────────────────────────────────────────

log "===== Deployment completado ====="
log "  Anterior : $PREV_COMMIT"
log "  Actual   : $NEW_COMMIT"
log "  Cambios  : $(git log --oneline "${PREV_COMMIT}..${NEW_COMMIT}" | wc -l | tr -d ' ') commit(s)"
git log --oneline "${PREV_COMMIT}..${NEW_COMMIT}" | while read -r line; do
    log "    • $line"
done
