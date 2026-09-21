<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#0a0a28; padding: 20px 32px;">
                            <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" height="36" style="display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; color:#1f2937; font-size:15px; line-height:1.6;">
                            {{-- Template uit de technische briefing (§9) --}}
                            <p style="margin:0 0 16px;">Hallo {{ $firstName }},</p>
                            <p style="margin:0 0 16px;">
                                Bedankt voor je aanvraag bij Renovion. We zouden graag even telefonisch contact opnemen
                                om je wensen goed te inventariseren voordat we een voorstel maken.
                            </p>
                            <p style="margin:0 0 16px;">Zou je je telefoonnummer met ons willen delen?</p>
                            <p style="margin:24px 0 0;">Met vriendelijke groet,<br><strong>Renovion</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px; background:#f9fafb; color:#9ca3af; font-size:12px;">
                            Renovion · Mercatorweg 28, 6827 DC Arnhem · info@renovion.nl · +31 6 395 353 00
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
