@php
    use App\Models\Setting;
@endphp
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
                            <p style="margin:0 0 16px;">{{ $greeting }}</p>
                            @foreach ($lines as $line)
                                <p style="margin:0 0 16px;">{{ $line }}</p>
                            @endforeach
                            <p style="margin:24px 0 0;">Met vriendelijke groet,<br><strong>{{ Setting::get('bedrijf.naam') }}</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px; background:#f9fafb; color:#9ca3af; font-size:12px;">
                            {{ Setting::get('bedrijf.naam') }}, {{ Setting::get('bedrijf.adres') }}, {{ Setting::get('bedrijf.postcode') }}, {{ Setting::get('bedrijf.email') }}, {{ Setting::get('bedrijf.telefoon') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
