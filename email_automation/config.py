# ============================================================
#  CONFIGURACIÓN - INTEP Email Automation
#  Edita este archivo con tus datos antes de ejecutar
# ============================================================

# --- Google Sheets (IDs de las URLs) ---
SPREADSHEETS = [
    {
        "id": "1FUY6uJz2umPqt4ljOmXIMJ1zvCyGICt8",
        "sheet_gid": None,          # None = primera pestaña
        "label": "Estudiantes Viejos"
    },
    {
        "id": "1YIQzpIAwJDnqHs797J051V5EWq8w0-qX",
        "sheet_gid": 1420650680,    # Pestaña específica
        "label": "Egresados Recientes"
    },
    # Para agregar más bases de datos en el futuro, copia el bloque de arriba:
    # {
    #     "id": "ID_DEL_SHEET",
    #     "sheet_gid": None,
    #     "label": "Nombre del grupo"
    # },
]

# --- Google Drive ---
BANNER_DRIVE_ID = "1B_9GtKuHr6YOzs-kbHw4vmkpXHmbaIzA"

# --- Gmail ---
SENDER_EMAIL = "intep.matriculas@gmail.com"
SENDER_APP_PASSWORD = "xxxx xxxx xxxx xxxx"  # Contraseña de aplicación (ver README)

# --- Correo ---
EMAIL_SUBJECT = "¡Matrículas Abiertas INTEP!"

# --- Límite diario de envíos ---
DAILY_LIMIT = 100
