@php $u = $kind === 'klant'; @endphp
{!! $u ? 'Beste '.$naam.',' : 'Hoi '.$voornaam.',' !!}

@if ($again)
{!! $u ? 'Hierbij ontvangt u een nieuwe link voor uw klantportaal van Renovion Groep BV. Een eerdere link of een eerder wachtwoord werkt niet meer.' : 'Hierbij een nieuwe link om in te loggen bij Renovion Groep BV. Een eerdere link of een eerder wachtwoord werkt niet meer.' !!}
@elseif ($kind === 'klant')
{!! 'Uw persoonlijke klantportaal van Renovion Groep BV staat klaar'.($project ? ' voor '.$project : '').'. U volgt er de voortgang van uw project en vindt er uw offerte, betalingen en documenten. Ook stelt u er vragen aan uw projectleider.' !!}
@elseif ($kind === 'partner')
Je bent uitgenodigd voor het partnerportaal van Renovion Groep BV. Na het inloggen vul je in een paar stappen je bedrijfsgegevens, specialismen, tarief en documenten in. Daarna vind je er je klussen en werkbonnen, geef je door wanneer je beschikbaar bent, stel je vragen en dien je je facturen in.
@else
{!! ($by ? $by.' heeft een account voor je aangemaakt' : 'Er is een account voor je aangemaakt').' in het systeem van Renovion Groep BV, waarin we projecten, planning en taken bijhouden.' !!}
@endif

{!! $u ? 'Kies via deze link een eigen wachtwoord; u bent dan meteen ingelogd:' : 'Kies via deze link een eigen wachtwoord; je bent dan meteen ingelogd:' !!}
{!! $link !!}

{!! $u ? 'De link is '.$dagen.' dagen geldig. Daarna logt u in op '.$loginUrl.' met '.$email.' en uw eigen wachtwoord.' : 'De link is '.$dagen.' dagen geldig. Daarna log je in op '.$loginUrl.' met '.$email.' en je eigen wachtwoord.' !!}

{!! $u ? 'Heeft u vragen? Bel +31 6 395 353 00 of mail info@renovion.nl.' : 'Vragen? Bel +31 6 395 353 00 of mail info@renovion.nl.' !!}

Renovion Groep BV, Mercatorweg 28, 6827 DC Arnhem. KVK 95384782.
