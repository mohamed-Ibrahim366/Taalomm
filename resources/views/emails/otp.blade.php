<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:40px 0;">
  <tr>
    <td align="center">

      <table role="presentation" width="480" cellpadding="0" cellspacing="0"
             style="background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.06); max-width:480px; width:100%;">

        <tr>
          <td align="center" style="background:linear-gradient(135deg,#4f46e5,#7c3aed); padding:36px 24px;">
            <div style="font-size:24px; font-weight:700; color:#ffffff; letter-spacing:0.5px;">
              Taalom
            </div>
          </td>
        </tr>

        <tr>
          <td style="padding:40px 36px 8px 36px;">
            <p style="margin:0 0 4px 0; font-size:15px; color:#6b7280;">
              {{ $name ? 'Hello, ' . $name . '!' : 'Hello!' }}
            </p>
            <h1 style="margin:0 0 12px 0; font-size:22px; color:#111827; font-weight:700;">
              {{ $subject }}
            </h1>
            <p style="margin:0 0 28px 0; font-size:15px; line-height:1.6; color:#4b5563;">
              {{ $intro }}
            </p>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:0 36px 28px 36px;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td align="center"
                    style="background-color:#f5f3ff; border:1.5px dashed #7c3aed; border-radius:12px; padding:22px 20px;">
                  <div style="font-size:13px; font-weight:600; color:#7c3aed; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:10px;">
                    Verification Code
                  </div>
                  <div style="font-size:36px; font-weight:800; color:#111827; letter-spacing:10px; font-family:'Courier New', monospace;">
                    {{ $code }}
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:0 36px 8px 36px;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td style="background-color:#fffbeb; border-left:4px solid #f59e0b; border-radius:6px; padding:12px 16px;">
                  <p style="margin:0; font-size:13.5px; color:#92400e; line-height:1.5;">
                    ⏱ This code expires in <strong>10 minutes</strong>. Please don't share it with anyone.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:28px 36px 8px 36px;">
            <p style="margin:0; font-size:13px; line-height:1.6; color:#9ca3af;">
              If you did not request this code, you can safely ignore this email — no changes will be made to your account.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:28px 36px 32px 36px;">
            <hr style="border:none; border-top:1px solid #e5e7eb; margin:0 0 20px 0;">
            <p style="margin:0; font-size:12px; color:#9ca3af; text-align:center;">
              © {{ date('Y') }} Taalom. All rights reserved.
            </p>
          </td>
        </tr>

      </table>

      <p style="margin:20px 0 0 0; font-size:12px; color:#9ca3af;">
        Sent by Taalom · Please do not reply to this automated email.
      </p>

    </td>
  </tr>
</table>

</body>
</html>
