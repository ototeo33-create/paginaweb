# INTEP - Automatización de Correos de Matrícula

Script para enviar correos masivos personalizados con el banner del bimestre a estudiantes y egresados.

---

## Requisitos previos

- Python 3.9 o superior
- Cuenta de Google con acceso a las hojas de cálculo

---

## Configuración inicial (solo una vez)

### 1. Crear proyecto en Google Cloud

1. Ve a https://console.cloud.google.com
2. Crea un proyecto nuevo (ej: "INTEP Emails")
3. Activa las siguientes APIs:
   - **Google Sheets API**
   - **Google Drive API**

### 2. Crear cuenta de servicio

1. En el proyecto, ve a **IAM y administración → Cuentas de servicio**
2. Clic en **Crear cuenta de servicio**
3. Nombre: `intep-email-bot`
4. Clic en **Crear y continuar** → **Listo**
5. Abre la cuenta creada → pestaña **Claves** → **Agregar clave → JSON**
6. Descarga el archivo JSON y renómbralo `credentials.json`
7. Copia `credentials.json` dentro de la carpeta `email_automation/`

### 3. Compartir las hojas con la cuenta de servicio

1. Abre `credentials.json` y copia el valor de `"client_email"` (algo como `intep-email-bot@proyecto.iam.gserviceaccount.com`)
2. Abre cada Google Sheet
3. Clic en **Compartir** y pega ese correo con permisos de **Editor**

### 4. Obtener contraseña de aplicación de Gmail

1. Ve a https://myaccount.google.com con la cuenta `intep.matriculas@gmail.com`
2. **Seguridad → Verificación en dos pasos** (actívala si no está activa)
3. **Seguridad → Contraseñas de aplicaciones**
4. Selecciona "Correo" y "Otro (nombre personalizado)" → escribe "INTEP Bot"
5. Copia la contraseña generada (16 caracteres)
6. Abre `config.py` y pégala en `SENDER_APP_PASSWORD`

### 5. Instalar dependencias

```bash
cd email_automation
pip install -r requirements.txt
```

---

## Uso

```bash
cd email_automation
python main.py
```

El script:
- Lee todas las hojas configuradas en `config.py`
- Omite filas donde ESTADO = "ENVIADO" (no reenvía)
- Envía hasta 100 correos por ejecución (configurable en `config.py`)
- Marca cada fila con ESTADO y FECHA_ENVIO al enviar

---

## Agregar nuevas bases de datos

Abre `config.py` y agrega un bloque al listado `SPREADSHEETS`:

```python
{
    "id": "ID_DEL_NUEVO_SHEET",   # Del URL de Google Sheets
    "sheet_gid": None,             # None = primera pestaña
    "label": "Nombre del grupo"
},
```

---

## Actualizar el banner (nuevo bimestre)

1. Sube el nuevo banner a Google Drive
2. Copia el ID del archivo (del link compartido)
3. Actualiza `BANNER_DRIVE_ID` en `config.py`

---

## Estructura de las hojas

Las columnas mínimas requeridas son `NOMBRE` y `CORREO`.
El script agrega automáticamente las columnas `ESTADO` y `FECHA_ENVIO` si no existen.
