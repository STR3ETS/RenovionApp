@php
    $u = $kind === 'klant';
    $knop = 'Wachtwoord kiezen en inloggen';
    $kop = match (true) {
        $again => $u ? 'Een nieuwe link voor uw klantportaal' : 'Een nieuwe link om in te loggen',
        $kind === 'klant' => 'Uw klantportaal staat klaar',
        $kind === 'partner' => 'Welkom in het partnerportaal',
        default => 'Je account staat klaar',
    };
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $onderwerp }}</title>
</head>
<body style="margin:0;padding:0;background:#F3F5F8;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#17233A;-webkit-text-size-adjust:100%">
<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $u ? 'Kies een eigen wachtwoord en bekijk uw project.' : 'Kies een eigen wachtwoord en log in.' }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F5F8">
<tr><td align="center" style="padding:24px 12px">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:14px;overflow:hidden;border:1px solid #E3E8EF">
    <tr><td style="background:#0D1A2E;padding:22px 28px">
      <span style="font-size:24px;font-weight:600;color:#FFFFFF;letter-spacing:.01em">Renovion</span><span style="font-size:24px;font-weight:700;color:#F26A1B"> Groep</span>
    </td></tr>
    <tr><td style="padding:30px 28px 8px">
      <h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;font-weight:600;color:#0D1A2E">{{ $kop }}</h1>
      <p style="margin:0 0 14px;font-size:15px;line-height:1.6">{{ $u ? 'Beste '.$naam.',' : 'Hoi '.$voornaam.',' }}</p>
@if ($again)
      <p style="margin:0 0 14px;font-size:15px;line-height:1.6">{{ $u
          ? 'Hierbij ontvangt u een nieuwe link voor uw klantportaal van Renovion Groep BV. Een eerdere link of een eerder wachtwoord werkt niet meer.'
          : 'Hierbij een nieuwe link om in te loggen bij Renovion Groep BV. Een eerdere link of een eerder wachtwoord werkt niet meer.' }}</p>
@elseif ($kind === 'klant')
      <p style="margin:0 0 14px;font-size:15px;line-height:1.6">Uw persoonlijke klantportaal van Renovion Groep BV staat klaar{{ $project ? ' voor '.$project : '' }}. U volgt er de voortgang van uw project en vindt er uw offerte, betalingen en documenten. Ook stelt u er vragen aan uw projectleider.</p>
@elseif ($kind === 'partner')
      <p style="margin:0 0 14px;font-size:15px;line-height:1.6">Je bent uitgenodigd voor het partnerportaal van Renovion Groep BV. Na het inloggen vul je in een paar stappen je bedrijfsgegevens, specialismen, tarief en documenten in. Daarna vind je er je klussen en werkbonnen, geef je door wanneer je beschikbaar bent, stel je vragen en dien je je facturen in.</p>
@else
      <p style="margin:0 0 14px;font-size:15px;line-height:1.6">{{ $by ? $by.' heeft een account voor je aangemaakt' : 'Er is een account voor je aangemaakt' }} in het systeem van Renovion Groep BV, waarin we projecten, planning en taken bijhouden.</p>
@endif
      <p style="margin:0 0 22px;font-size:15px;line-height:1.6">{{ $u ? 'Met de knop hieronder kiest u een eigen wachtwoord en bent u meteen ingelogd.' : 'Met de knop hieronder kies je een eigen wachtwoord en ben je meteen ingelogd.' }}</p>
      <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:10px;background:#F26A1B">
        <a href="{{ $link }}" style="display:inline-block;padding:14px 26px;font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:10px">{{ $knop }}</a>
      </td></tr></table>
      <p style="margin:22px 0 14px;font-size:14px;line-height:1.6;color:#4A5873">{{ $u
          ? 'De link is '.$dagen.' dagen geldig. Daarna logt u in op'
          : 'De link is '.$dagen.' dagen geldig. Daarna log je in op' }} <a href="{{ $loginUrl }}" style="color:#C2500C">{{ preg_replace('#^https?://#', '', $loginUrl) }}</a> met {{ $email }} en {{ $u ? 'uw' : 'je' }} eigen wachtwoord.</p>
      <p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#6B7890">{{ $u ? 'Werkt de knop niet? Kopieer dan deze link naar uw browser:' : 'Werkt de knop niet? Kopieer dan deze link naar je browser:' }}</p>
      <p style="margin:0 0 22px;font-size:12px;line-height:1.5;color:#6B7890;word-break:break-all">{{ $link }}</p>
    </td></tr>
    <tr><td style="padding:18px 28px 24px;border-top:1px solid #E3E8EF">
      <p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#4A5873">{{ $u ? 'Heeft u vragen? Bel' : 'Vragen? Bel' }} <a href="tel:+31639535300" style="color:#C2500C">+31 6 395 353 00</a> of mail <a href="mailto:info@renovion.nl" style="color:#C2500C">info@renovion.nl</a>.</p>
      <p style="margin:0;font-size:12px;line-height:1.6;color:#6B7890">Renovion Groep BV, Mercatorweg 28, 6827 DC Arnhem. KVK 95384782.</p>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
