<?php
// ============================================
// MAIL HELPER — INTEP Portal
// ============================================

/**
 * Envía correo de bienvenida al estudiante recién creado.
 * Si falla, registra en error_log pero NO interrumpe el flujo principal.
 */
function enviarCorreoBienvenida($nombre, $email, $username, $password) {
    if (empty($email)) {
        error_log("INTEP Mail: no se envió bienvenida a '$nombre' — email vacío.");
        return false;
    }

    $vendorPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($vendorPath)) {
        error_log("INTEP Mail: vendor/autoload.php no encontrado.");
        return false;
    }

    require_once $vendorPath;

    $nombreSafe   = htmlspecialchars($nombre,   ENT_QUOTES, 'UTF-8');
    $usernameSafe = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $passSafe     = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $fecha        = date('d/m/Y');

    // Logo: se adjunta con CID para que Gmail lo muestre correctamente
    $appUrl    = rtrim(Config::get('APP_URL', 'http://localhost/intep'), '/');
    $loginUrl  = $appUrl . '/login.php';

    $logoPath  = __DIR__ . '/img/Logo.png';
    $tienelogo = file_exists($logoPath);
    $logoImg   = $tienelogo
        ? '<img src="cid:logo_intep" alt="INTEP" style="height:48px;display:block;" />'
        : '<span style="font-family:Arial,sans-serif;font-size:22px;font-weight:900;color:#ffffff;">INTEP</span>';

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Bienvenido a INTEP</title>
</head>
<body style="margin:0;padding:0;background:#1a1a2e;font-family:Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#1a1a2e;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,0.7);">

  <!-- HEADER -->
  <tr>
    <td style="background:linear-gradient(160deg,#072918 0%,#0a3d20 60%,#0d5a2a 100%);padding:0;">

      <!-- Logo bar -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding:28px 40px 24px;border-bottom:1px solid rgba(255,255,255,0.08);">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>' . $logoImg . '</td>
                <td align="right" style="font-size:12px;color:rgba(255,255,255,0.4);">' . $fecha . '</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Hero -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding:36px 40px 48px;">
            <div style="display:inline-block;background:rgba(16,185,129,0.18);border:1px solid rgba(16,185,129,0.4);border-radius:100px;padding:5px 16px;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:#10B981;margin-bottom:20px;">
              &#9679; NUEVO ESTUDIANTE
            </div>
            <br/>
            <div style="font-size:38px;font-weight:900;color:#ffffff;line-height:1.15;margin-top:8px;">
              &#161;Bienvenido al<br/>
              <span style="color:#10B981;">Portal Estudiantil!</span>
            </div>
            <div style="font-size:15px;color:rgba(255,255,255,0.5);margin-top:12px;line-height:1.6;">
              Tu cuenta ha sido creada con &#233;xito.<br/>
              Un nuevo cap&#237;tulo comienza hoy.
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Separador -->
  <tr>
    <td style="height:4px;background:linear-gradient(to right,#0a3d20,#10B981,#0a3d20);"></td>
  </tr>

  <!-- BODY -->
  <tr>
    <td style="background:#F5F2EC;padding:40px 40px 32px;">

      <div style="font-size:22px;font-weight:700;color:#0d2818;margin-bottom:12px;">
        Hola, ' . $nombreSafe . ' &#128075;
      </div>
      <div style="font-size:15px;color:#2d5a3d;line-height:1.75;margin-bottom:32px;">
        Nos alegra darte la bienvenida a la familia INTEP. Tu cuenta de acceso
        al portal estudiantil ha sido activada. Desde aqu&#237; podr&#225;s consultar tus
        m&#243;dulos, notas, asistencia y radicar solicitudes. A continuaci&#243;n
        encontrar&#225;s tus credenciales de acceso:
      </div>

      <!-- Credenciales -->
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(10,40,24,0.12);margin-bottom:28px;">
        <tr>
          <td style="background:linear-gradient(135deg,#0a2818 0%,#166535 100%);padding:14px 24px;">
            <span style="font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:rgba(255,255,255,0.75);">
              &#128274; CREDENCIALES DE ACCESO
            </span>
          </td>
        </tr>
        <tr>
          <td style="padding:18px 24px;border-bottom:1px solid #edfaf2;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:40px;height:40px;background:linear-gradient(135deg,#0a2818,#166535);border-radius:10px;text-align:center;vertical-align:middle;">
                  <span style="color:#10B981;font-size:18px;">&#128100;</span>
                </td>
                <td style="padding-left:14px;">
                  <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#3aaa5c;margin-bottom:3px;">Usuario</div>
                  <div style="font-size:17px;font-weight:700;color:#0d2818;font-family:\'Courier New\',monospace;">' . $usernameSafe . '</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:18px 24px;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:40px;height:40px;background:linear-gradient(135deg,#0a2818,#166535);border-radius:10px;text-align:center;vertical-align:middle;">
                  <span style="color:#10B981;font-size:18px;">&#128273;</span>
                </td>
                <td style="padding-left:14px;">
                  <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#3aaa5c;margin-bottom:3px;">Contrase&#241;a</div>
                  <div style="font-size:19px;font-weight:700;color:#059669;font-family:\'Courier New\',monospace;">' . $passSafe . '</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Quote -->
      <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#0a2818 0%,#166535 100%);border-radius:14px;overflow:hidden;margin-bottom:28px;">
        <tr>
          <td style="padding:26px 30px;">
            <div style="font-size:17px;color:#ffffff;line-height:1.7;font-style:italic;">
              &#8220;La educaci&#243;n es el pasaporte hacia el futuro, porque el ma&#241;ana
              pertenece a quienes se preparan para &#233;l hoy.&#8221;
            </div>
            <div style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#10B981;margin-top:12px;">
              &#8212; Malcolm X
            </div>
          </td>
        </tr>
      </table>

      <!-- CTA -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
        <tr>
          <td align="center">
            <a href="' . $loginUrl . '"
               style="display:inline-block;background:linear-gradient(135deg,#059669 0%,#10B981 100%);color:#ffffff;text-decoration:none;padding:16px 44px;border-radius:100px;font-size:15px;font-weight:700;">
              Ingresar al Portal &#8594;
            </a>
            <div style="font-size:12px;color:#3aaa5c;margin-top:10px;">
              ' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '
            </div>
          </td>
        </tr>
      </table>

      <!-- Pasos -->
      <div style="font-size:11px;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;color:#0a5c2a;margin-bottom:14px;">
        &#191;C&#243;mo empezar?
      </div>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;margin-bottom:8px;">
        <tr><td style="padding:14px 18px;">
          <table cellpadding="0" cellspacing="0"><tr>
            <td style="width:26px;height:26px;background:linear-gradient(135deg,#059669,#10B981);border-radius:50%;text-align:center;vertical-align:middle;font-size:12px;font-weight:800;color:#ffffff;">1</td>
            <td style="padding-left:12px;font-size:14px;color:#2d5a3d;line-height:1.5;">Ingresa con tu n&#250;mero de documento como usuario y la contrase&#241;a indicada arriba.</td>
          </tr></table>
        </td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;margin-bottom:8px;">
        <tr><td style="padding:14px 18px;">
          <table cellpadding="0" cellspacing="0"><tr>
            <td style="width:26px;height:26px;background:linear-gradient(135deg,#059669,#10B981);border-radius:50%;text-align:center;vertical-align:middle;font-size:12px;font-weight:800;color:#ffffff;">2</td>
            <td style="padding-left:12px;font-size:14px;color:#2d5a3d;line-height:1.5;">Explora tus m&#243;dulos, revisa tu asistencia y consulta tus calificaciones en tiempo real.</td>
          </tr></table>
        </td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;margin-bottom:28px;">
        <tr><td style="padding:14px 18px;">
          <table cellpadding="0" cellspacing="0"><tr>
            <td style="width:26px;height:26px;background:linear-gradient(135deg,#059669,#10B981);border-radius:50%;text-align:center;vertical-align:middle;font-size:12px;font-weight:800;color:#ffffff;">3</td>
            <td style="padding-left:12px;font-size:14px;color:#2d5a3d;line-height:1.5;">&#191;Alg&#250;n inconveniente? Radica una solicitud directamente desde el portal.</td>
          </tr></table>
        </td></tr>
      </table>

      <table width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;">
        <tr><td style="height:1px;background:linear-gradient(to right,transparent,#b8e6cb,transparent);"></td></tr>
      </table>
      <div style="font-size:12px;color:#4a7a5c;text-align:center;line-height:1.6;">
        Este mensaje fue generado autom&#225;ticamente por el sistema de INTEP.<br/>
        Por favor no respondas directamente a este correo.
      </div>
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style="background:#071a0e;padding:28px 40px;text-align:center;">
      <div style="margin-bottom:12px;">' . $logoImg . '</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.35);line-height:1.8;">
        institutointepmadrid@gmail.com &#183; Calle 7 # 3&#8211;98, Madrid, Cundinamarca<br/>
        Lun&#8211;Vie: 10:00&#8211;13:00 / 16:30&#8211;19:00 &#183; S&#225;b: 09:00&#8211;13:00
      </div>
      <div style="margin-top:14px;font-size:11px;color:#10B981;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:10px;padding:10px 16px;display:inline-block;">
        &#128274; Por seguridad, cambia tu contrase&#241;a en el primer inicio de sesi&#243;n.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = Config::get('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = Config::get('MAIL_USER', 'institutointepmadrid@gmail.com');
        $mail->Password   = Config::get('MAIL_PASS', '');
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) Config::get('MAIL_PORT', 587);
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(
            Config::get('MAIL_USER', 'institutointepmadrid@gmail.com'),
            'Portal Estudiantil INTEP'
        );
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = '¡Bienvenido a INTEP! Tus credenciales de acceso';
        $mail->Body    = $html;
        $mail->AltBody = "Bienvenido a INTEP, $nombre.\n\nUsuario: $username\nContraseña: $password\n\nIngresa en: portal.institutointep.edu.co";

        // Adjuntar logo con CID para que Gmail lo muestre correctamente
        if ($tienelogo) {
            $mail->addEmbeddedImage($logoPath, 'logo_intep', 'Logo.png', 'base64', 'image/png');
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("INTEP Mail bienvenida error: " . $e->getMessage());
        return false;
    }
}
