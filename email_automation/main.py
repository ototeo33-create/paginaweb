"""
INTEP - Automatización de Correos de Matrícula
Ejecutar: python main.py
"""

import io
import time
import datetime
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.image import MIMEImage

import gspread
from google.oauth2.service_account import Credentials
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseDownload

import config

SCOPES = [
    "https://www.googleapis.com/auth/spreadsheets",
    "https://www.googleapis.com/auth/drive.readonly",
]


# ── Auth ──────────────────────────────────────────────────────────────────────

def get_google_clients():
    creds = Credentials.from_service_account_file("credentials.json", scopes=SCOPES)
    gc = gspread.authorize(creds)
    drive = build("drive", "v3", credentials=creds)
    return gc, drive


# ── Banner ────────────────────────────────────────────────────────────────────

def download_banner(drive_service, file_id):
    request = drive_service.files().get_media(fileId=file_id)
    fh = io.BytesIO()
    downloader = MediaIoBaseDownload(fh, request)
    done = False
    while not done:
        _, done = downloader.next_chunk()
    return fh.getvalue()


# ── Columnas de control ───────────────────────────────────────────────────────

def ensure_control_columns(worksheet):
    """Agrega ESTADO y FECHA_ENVIO si no existen. Retorna sus índices (1-based)."""
    headers = worksheet.row_values(1)
    headers_upper = [h.strip().upper() for h in headers]

    def find_or_create(name):
        try:
            return headers_upper.index(name) + 1
        except ValueError:
            col = len(headers) + 1
            worksheet.update_cell(1, col, name)
            headers.append(name)
            headers_upper.append(name)
            return col

    estado_col = find_or_create("ESTADO")
    fecha_col = find_or_create("FECHA_ENVIO")
    return headers, headers_upper, estado_col, fecha_col


# ── Email ─────────────────────────────────────────────────────────────────────

def build_email(nombre, correo, banner_bytes):
    msg = MIMEMultipart("related")
    msg["Subject"] = config.EMAIL_SUBJECT
    msg["From"] = config.SENDER_EMAIL
    msg["To"] = correo

    nombre_display = nombre.strip().title() if nombre.strip() else "estudiante"

    html = f"""
    <html>
    <body style="font-family: Arial, sans-serif; max-width: 620px; margin: 0 auto; color: #333;">
        <p>Hola <strong>{nombre_display}</strong>,</p>

        <p>Nos complace informarte que las <strong>matrículas para el nuevo bimestre
        ya están abiertas</strong>. ¡Es el momento ideal para continuar o retomar
        tu proceso formativo con nosotros!</p>

        <p>No dejes pasar esta oportunidad y asegura tu cupo cuanto antes.</p>

        <br>
        <img src="cid:banner" style="width:100%; max-width:620px; display:block;"
             alt="Matrículas Abiertas INTEP">
        <br>

        <p>Para más información o para realizar tu matrícula, escríbenos a
        <a href="mailto:{config.SENDER_EMAIL}">{config.SENDER_EMAIL}</a>.</p>

        <p>¡Te esperamos!</p>
        <p><strong>Equipo INTEP</strong></p>
    </body>
    </html>
    """

    msg.attach(MIMEText(html, "html"))

    img = MIMEImage(banner_bytes)
    img.add_header("Content-ID", "<banner>")
    img.add_header("Content-Disposition", "inline", filename="banner.jpg")
    msg.attach(img)

    return msg


def send_email(msg):
    with smtplib.SMTP_SSL("smtp.gmail.com", 465) as smtp:
        smtp.login(config.SENDER_EMAIL, config.SENDER_APP_PASSWORD)
        smtp.send_message(msg)


# ── Proceso por hoja ──────────────────────────────────────────────────────────

def process_sheet(gc, sheet_config, banner_bytes, daily_count):
    label = sheet_config["label"]
    print(f"\n📋  {label}")
    print("-" * 40)

    spreadsheet = gc.open_by_key(sheet_config["id"])
    gid = sheet_config.get("sheet_gid")
    worksheet = spreadsheet.get_worksheet_by_id(gid) if gid else spreadsheet.sheet1

    headers, headers_upper, estado_col, fecha_col = ensure_control_columns(worksheet)

    # Buscar columnas de nombre y correo
    nombre_col = next(
        (i + 1 for i, h in enumerate(headers_upper) if h == "NOMBRE"), 1
    )
    correo_col = next(
        (i + 1 for i, h in enumerate(headers_upper) if h == "CORREO"), 5
    )

    all_rows = worksheet.get_all_values()
    sent = errors = skipped = 0

    for row_idx, row in enumerate(all_rows[1:], start=2):
        if daily_count >= config.DAILY_LIMIT:
            print(f"\n⚠️  Límite diario de {config.DAILY_LIMIT} correos alcanzado.")
            break

        # Extender fila si es más corta que las columnas de control
        while len(row) < max(nombre_col, correo_col, estado_col, fecha_col):
            row.append("")

        estado = row[estado_col - 1].strip().upper()
        if estado == "ENVIADO":
            skipped += 1
            continue

        nombre = row[nombre_col - 1].strip()
        correo = row[correo_col - 1].strip()

        if not correo or "@" not in correo:
            print(f"  ⚠️  Fila {row_idx}: correo inválido → '{correo}'")
            worksheet.update_cell(row_idx, estado_col, "ERROR - CORREO INVÁLIDO")
            errors += 1
            continue

        try:
            msg = build_email(nombre, correo, banner_bytes)
            send_email(msg)

            now = datetime.datetime.now().strftime("%d/%m/%Y %H:%M")
            worksheet.update_cell(row_idx, estado_col, "ENVIADO")
            worksheet.update_cell(row_idx, fecha_col, now)

            print(f"  ✅  {correo}")
            sent += 1
            daily_count += 1
            time.sleep(1.2)  # pausa para evitar bloqueos de Gmail

        except smtplib.SMTPRecipientsRefused:
            print(f"  ❌  {correo}: correo rechazado")
            worksheet.update_cell(row_idx, estado_col, "ERROR - RECHAZADO")
            errors += 1

        except Exception as e:
            print(f"  ❌  {correo}: {e}")
            worksheet.update_cell(row_idx, estado_col, "ERROR")
            errors += 1

    print(f"\n  Enviados: {sent}  |  Errores: {errors}  |  Ya enviados: {skipped}")
    return daily_count


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    print("=" * 50)
    print("  INTEP - Correos de Matrícula")
    print(f"  {datetime.datetime.now().strftime('%d/%m/%Y %H:%M')}")
    print("=" * 50)

    print("\n🔑  Conectando con Google...")
    gc, drive = get_google_clients()
    print("✅  Conexión exitosa")

    print("\n📥  Descargando banner desde Drive...")
    banner_bytes = download_banner(drive, config.BANNER_DRIVE_ID)
    print(f"✅  Banner descargado ({len(banner_bytes) // 1024} KB)")

    daily_count = 0
    for sheet_config in config.SPREADSHEETS:
        if daily_count >= config.DAILY_LIMIT:
            break
        daily_count = process_sheet(gc, sheet_config, banner_bytes, daily_count)

    print("\n" + "=" * 50)
    print(f"  TOTAL ENVIADOS HOY: {daily_count}")
    print("=" * 50)


if __name__ == "__main__":
    main()
