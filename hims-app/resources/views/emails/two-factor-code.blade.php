<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HIMS Verification Code</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 15px; color: #1e293b;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 540px; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06); border: 1px solid #e2e8f0;">
        <!-- Header -->
        <tr>
            <td align="center" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 28px 32px;">
                <div style="display: inline-block; background: rgba(13, 148, 136, 0.2); border: 1px solid #0d9488; border-radius: 10px; padding: 10px 18px; margin-bottom: 8px;">
                    <span style="color: #2dd4bf; font-size: 20px; font-weight: 800; letter-spacing: 2px;">HIMS</span>
                </div>
                <h1 style="color: #ffffff; font-size: 18px; font-weight: 600; margin: 6px 0 0; letter-spacing: 0.5px;">Hospital Information & Management System</h1>
                <p style="color: #94a3b8; font-size: 12px; margin: 4px 0 0;">Performance & Development Module</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 32px;">
                <p style="font-size: 15px; line-height: 1.6; margin: 0 0 16px; color: #334155;">
                    Hello <strong>{{ $userName }}</strong>,
                </p>
                <p style="font-size: 14px; line-height: 1.6; margin: 0 0 24px; color: #475569;">
                    A sign-in attempt requires two-factor authentication. Please enter the following 6-character verification code to complete your login:
                </p>

                <!-- 6-character Code Display -->
                <div style="background: #f1f5f9; border: 2px dashed #0d9488; border-radius: 10px; padding: 24px 16px; text-align: center; margin: 0 0 24px;">
                    <span style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace; font-size: 38px; font-weight: 800; letter-spacing: 12px; color: #0f172a; display: inline-block; padding-left: 12px;">{{ $code }}</span>
                </div>

                <!-- Case Sensitivity Callout -->
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 12px 16px; margin-bottom: 24px;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td style="vertical-align: top; width: 24px; font-size: 16px;">⚠️</td>
                            <td style="padding-left: 8px; font-size: 13px; line-height: 1.5; color: #92400e;">
                                <strong>Case-Sensitive Verification:</strong><br>
                                This code is strictly case-sensitive. Please make sure to enter both <strong>uppercase and lowercase</strong> characters exactly as shown above.
                            </td>
                        </tr>
                    </table>
                </div>

                <p style="font-size: 13px; line-height: 1.5; color: #64748b; margin: 0 0 8px;">
                    • This code expires in <strong>10 minutes</strong>.
                </p>
                <p style="font-size: 13px; line-height: 1.5; color: #64748b; margin: 0;">
                    • If you did not initiate this request, someone else may have entered your password. Please notify your HIMS system administrator immediately.
                </p>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td align="center" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 32px;">
                <p style="font-size: 11px; color: #94a3b8; margin: 0; line-height: 1.5;">
                    This is an automated security transmission from HIMS.<br>
                    Please do not reply directly to this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
