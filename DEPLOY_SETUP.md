# Pipeline de Auto-Deploy — INTEP

## ¿Qué hace este sistema?

Cada vez que haces `git push origin master` desde tu PC, el servidor en Termux
se actualiza automáticamente sin que tengas que hacer nada más.

```
git push origin master  (tu PC)
        ↓
GitHub detecta el push
        ↓
GitHub envía un POST firmado a tu servidor (webhook)
        ↓
webhook.php valida la firma y ejecuta deploy.sh
        ↓
deploy.sh hace git pull en Termux
        ↓
Servidor actualizado ✓
```

---

## Archivos creados

### `webhook.php`
Receptor del webhook de GitHub. Se encarga de:
- Recibir el POST que envía GitHub al hacer push
- Validar la firma HMAC-SHA256 para verificar que viene de GitHub
- Verificar que el push es a la rama `master`
- Ejecutar `deploy.sh` en background

Variables que usa (definidas en `.bashrc` de Termux):
- `GITHUB_WEBHOOK_SECRET` — debe coincidir con el secret configurado en GitHub
- `REPO_PATH` — ruta del proyecto en Termux

### `deploy.sh`
Script de deployment con rollback automático. Se encarga de:
- Guardar el commit actual antes de actualizar (para poder revertir)
- Hacer `git fetch` y `git pull origin master`
- Si el pull falla, hace `git reset --hard` al commit anterior (rollback)
- Ajustar permisos de la carpeta `uploads/`
- Registrar cada paso en `webhook.log`

### `.github/workflows/notify.yml`
Workflow de GitHub Actions que se ejecuta en cada push a master:
- Hace un ping GET al webhook para verificar que el servidor está accesible
- Genera un resumen del push (autor, commit, mensaje, hora) visible en GitHub Actions

### `config_env.php`
Cargador de variables de entorno. Lee el archivo `.env` del proyecto y
las expone mediante `Config::get('VARIABLE')`. No contiene credenciales,
por eso se quitó del `.gitignore`.

### `.env` (NO está en git)
Archivo con las credenciales reales del servidor (base de datos, etc.).
Debe crearse manualmente en cada entorno (Windows y Termux por separado).

---

## Infraestructura en Termux

| Componente | Descripción |
|------------|-------------|
| **PHP 8.5** | Servidor web built-in (`php -S 0.0.0.0:8082`) |
| **MariaDB 12.2** | Base de datos |
| **cloudflared** | Túnel de Cloudflare que expone el servidor al internet |
| **OpenSSH** | Permite acceso remoto por SSH desde el PC |
| **git** | Control de versiones para recibir los cambios |

### Puertos usados
- `8082` — servidor PHP (apuntado por Cloudflare Tunnel)
- `8022` — servidor SSH de Termux

### URL pública
`https://provide-advertising-empirical-friday.trycloudflare.com`

> Esta URL puede cambiar si se reinicia cloudflared con tunnel temporal.
> Para URL fija, configurar un tunnel nombrado en Cloudflare.

---

## Configuración en GitHub

Ubicación: `https://github.com/ototeo33-create/intep/settings/hooks`

| Campo | Valor |
|-------|-------|
| Payload URL | `https://provide-advertising-empirical-friday.trycloudflare.com/webhook.php` |
| Content type | `application/json` |
| Secret | valor de `GITHUB_WEBHOOK_SECRET` en Termux |
| Events | Solo `push` |

---

## Variables de entorno en Termux (`~/.bashrc`)

```bash
export REPO_PATH="$HOME/intep"
export GITHUB_WEBHOOK_SECRET="tu_secret_aqui"
```

---

## Comandos para levantar el servidor en Termux

```bash
# 1. Iniciar MariaDB
mysqld_safe --user=$(whoami) &

# 2. Iniciar servidor PHP
cd ~/intep && php -S 0.0.0.0:8082 &

# 3. Verificar que cloudflared está corriendo
pgrep -a cloudflared
```

---

## Flujo de trabajo diario

```bash
# Solo desde tu PC — el servidor se actualiza solo
git add .
git commit -m "descripción del cambio"
git push origin master
```

---

## Logs

El archivo `webhook.log` (ignorado por git) registra cada deployment:

```
[2026-03-09 13:26:50] DEPLOY: push de 'ototeo33-create' — commit a3c5c52
[2026-03-09 13:26:50] [deploy.sh] Ejecutando git pull...
[2026-03-09 13:26:50] [deploy.sh] Deployment completado =====
[2026-03-09 13:26:50] [deploy.sh]   Anterior : b4ba85b...
[2026-03-09 13:26:50] [deploy.sh]   Actual   : a3c5c52...
[2026-03-09 13:26:51] [deploy.sh]   Cambios  : 1 commit(s)
```

Para ver el log en tiempo real desde Termux:
```bash
tail -f ~/intep/webhook.log
```

---

## Pendiente

- [ ] Crear `.env` en Termux con credenciales de MariaDB
- [ ] Importar esquema de base de datos en MariaDB de Termux
- [ ] Configurar cloudflared con tunnel nombrado para URL fija
