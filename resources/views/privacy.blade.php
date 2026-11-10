@php
    $bewaardagen = config('renovion.transcription.audio_retention_days');
    $secties = [
        'wie' => 'Wie zijn wij',
        'gegevens' => 'Welke gegevens',
        'waarom' => 'Waarom en op welke grond',
        'nova' => 'Nova en AI',
        'gesprekken' => 'Gespreksopnames',
        'delen' => 'Met wie we gegevens delen',
        'bewaren' => 'Hoe lang we bewaren',
        'app' => 'De app en cookies',
        'beveiliging' => 'Beveiliging',
        'rechten' => 'Uw rechten',
    ];
@endphp
<!DOCTYPE html>
<html lang="nl" @nativeapp class="native-app" @endnativeapp>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Privacyverklaring - Renovion</title>
    <meta name="description" content="Hoe Renovion omgaat met persoonsgegevens in Renovion Dash, de app en het klantportaal.">
    @include('partials.favicon')
    @include('partials.fonts')
    @include('partials.load-guard')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    <header class="sticky top-0 z-30 bg-navy-950 pt-safe text-white">
        <div class="mx-auto flex h-16 w-full max-w-3xl items-center justify-between gap-3 px-4">
            @nativeapp
                <x-os-logo class="h-12 w-auto" />
            @else
            <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
            @endnativeapp
            {{-- Terug naar waar je vandaan kwam (ook het scherm in de app); zonder geschiedenis naar inloggen of Vandaag. --}}
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
               onclick="if (history.length > 1) { event.preventDefault(); history.back(); }"
               class="tap flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-navy-200 transition hover:bg-navy-900 hover:text-white">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Terug
            </a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-3xl px-4 py-6 pb-[calc(1.5rem_+_env(safe-area-inset-bottom))]">
        <p class="text-xs font-bold tracking-widest text-brand-700 uppercase">Privacy</p>
        <h1 class="mt-1 text-2xl font-bold text-navy-950 sm:text-3xl">Privacyverklaring</h1>
        <p class="mt-2 text-sm text-gray-600">Voor Renovion Dash, de app Renovion OS, het klantportaal en de offertelinks. Laatst bijgewerkt op 10 november 2026.</p>

        <nav aria-label="Inhoud" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">Inhoud</p>
            <ol class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @foreach ($secties as $id => $kop)
                    <li><a href="#{{ $id }}" class="block py-1 font-medium text-navy-900 hover:text-brand-700">{{ $loop->iteration }}. {{ $kop }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="mt-4 space-y-4 text-[15px] leading-relaxed text-gray-700 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-navy-950 [&_li]:mt-1 [&_p+p]:mt-3 [&_section]:scroll-mt-24 [&_section]:rounded-2xl [&_section]:border [&_section]:border-gray-200 [&_section]:bg-white [&_section]:p-5 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">

            <section id="wie">
                <h2>1. Wie zijn wij</h2>
                <p class="mt-2">Renovion is een renovatiebedrijf in Arnhem. Wij gebruiken Renovion Dash om aanvragen, offertes, projecten en de uitvoering bij te houden. Wij zijn verantwoordelijk voor de persoonsgegevens die daarin staan.</p>
                <p>Renovion, Mercatorweg 28, 6827 DC Arnhem, KVK 95384782. Vragen over privacy: <a href="mailto:info@renovion.nl" class="font-semibold text-brand-700 underline">info@renovion.nl</a> of +31 6 395 353 00.</p>
                <p>Deze verklaring geldt voor klanten en mensen die een aanvraag doen, voor bezoekers van een offertelink of het klantportaal, en voor medewerkers en partners die met Renovion Dash of de app werken.</p>
            </section>

            <section id="gegevens">
                <h2>2. Welke gegevens we verwerken</h2>
                <p class="mt-2"><b class="text-navy-950">Van klanten en aanvragers:</b></p>
                <ul>
                    <li>naam, adres, postcode, woonplaats, e-mailadres en telefoonnummer;</li>
                    <li>wat u ons vertelt over de klus: de aanvraag, de gewenste startdatum, notities van contactmomenten en gesprekken;</li>
                    <li>calculaties, offertes en de versies daarvan, de planning en de voortgang van uw project;</li>
                    <li>foto's van de werkzaamheden in en om uw woning, die wij maken als bewijs van het werk;</li>
                    <li>uw akkoord: als u een offerte, fase of opleverrapport ondertekent, bewaren we uw naam, de datum en tijd en uw IP-adres;</li>
                    <li>als u een portaalaccount heeft: uw e-mailadres en een versleuteld wachtwoord.</li>
                </ul>
                <p class="mt-3"><b class="text-navy-950">Van medewerkers en partners:</b></p>
                <ul>
                    <li>naam, e-mailadres, functie en rol;</li>
                    <li>taken, planning, chatberichten, foto's en notities die u in het systeem zet;</li>
                    <li>gespreksopnames, de uitgeschreven tekst en het verslag (zie hoofdstuk 5);</li>
                    <li>van uw telefoon: een code om pushmeldingen te sturen, het soort toestel en uw meldingsvoorkeuren.</li>
                </ul>
                <p class="mt-3"><b class="text-navy-950">Van iedereen:</b> wie wat wanneer heeft gewijzigd (een logboek), en technische gegevens zoals uw IP-adres en het moment van inloggen.</p>
            </section>

            <section id="waarom">
                <h2>3. Waarom en op welke grond</h2>
                <ul>
                    <li><b class="text-navy-950">Om uw opdracht uit te voeren</b> (uitvoering van een overeenkomst): een offerte maken, het werk plannen en uitvoeren, u op de hoogte houden en opleveren.</li>
                    <li><b class="text-navy-950">Om uw aanvraag op te volgen</b> (stappen vóór een overeenkomst): contact opnemen, een intake plannen en een offerte sturen.</li>
                    <li><b class="text-navy-950">Om aan te tonen wat er is gebeurd</b> (gerechtvaardigd belang): foto-bewijs, akkoorden en het logboek, bijvoorbeeld bij vragen over garantie of meerwerk.</li>
                    <li><b class="text-navy-950">Om het werk goed te organiseren</b> (gerechtvaardigd belang): taken, planning, teamchat, meldingen en verslagen van overleggen.</li>
                    <li><b class="text-navy-950">Omdat de wet het vraagt</b> (wettelijke plicht): onze administratie, zoals offertes en afspraken die bij de boekhouding horen.</li>
                </ul>
                <p class="mt-3">We gebruiken uw gegevens niet voor reclame van anderen en verkopen ze nooit.</p>
            </section>

            <section id="nova">
                <h2>4. Nova en AI</h2>
                <p class="mt-2">In Renovion Dash helpt Nova, een assistent op basis van kunstmatige intelligentie. Nova maakt voorstellen: een taak of afspraak uit een ingesproken zin, calculatieregels uit een omschrijving, een tekst voor een offerte, een samenvatting van een gesprek of een overzicht van de dag.</p>
                <p>Daarvoor gaat de tekst die nodig is naar de AI-dienst, bijvoorbeeld uw naam, de omschrijving van de klus of een stuk van een gesprek. Een voorstel van Nova is altijd een concept: een medewerker controleert het en beslist. Nova neemt geen besluiten over u zonder dat er een mens naar kijkt.</p>
                <p>Inspreken (dicteren) gebruikt de spraakherkenning van uw telefoon of browser, van Google of Apple.</p>
            </section>

            <section id="gesprekken">
                <h2>5. Gespreksopnames</h2>
                <p class="mt-2">Een medewerker kan een overleg opnemen, zoals een bouwvergadering of een gesprek bij u thuis. Dat gebeurt alleen als iedereen die meedoet weet dat het gesprek wordt opgenomen; de medewerker moet dat bevestigen voordat de opname start.</p>
                <p>Het geluid wordt omgezet naar tekst, daarna maakt Nova een verslag met besluiten, acties en open vragen. Het geluid wordt na {{ $bewaardagen }} dagen automatisch verwijderd. De tekst en het verslag blijven bij het project staan. Wilt u niet dat een gesprek wordt opgenomen, zeg het dan: dan nemen we het niet op. Een opname kan ook achteraf worden verwijderd.</p>
            </section>

            <section id="delen">
                <h2>6. Met wie we gegevens delen</h2>
                <p class="mt-2">Binnen Renovion ziet iedereen alleen wat nodig is voor het eigen werk: een uitvoerder ziet alleen de projecten waar die aan werkt, een klant alleen het eigen project in het portaal. Daarnaast werken we met deze dienstverleners, die gegevens alleen in onze opdracht verwerken:</p>
                <ul>
                    <li><b class="text-navy-950">De partij die onze server host</b>, waar Renovion Dash en alle gegevens staan.</li>
                    <li><b class="text-navy-950">Anthropic</b> (Claude), voor de voorstellen en verslagen van Nova.</li>
                    <li><b class="text-navy-950">OpenAI</b> (Whisper) of <b class="text-navy-950">AssemblyAI</b> (in de EU), om gespreksopnames naar tekst om te zetten.</li>
                    <li><b class="text-navy-950">Google Firebase</b>, om pushmeldingen naar telefoons te sturen. Die krijgt alleen de code van de telefoon en de korte tekst van de melding.</li>
                    <li><b class="text-navy-950">Bunny.net</b>, voor de lettertypen van de website.</li>
                </ul>
                <p class="mt-3">Anthropic, OpenAI en Google zitten in de Verenigde Staten. Gegevens gaan daarheen alleen met de waarborgen die de AVG vraagt, zoals het EU-VS Data Privacy Framework of de standaardcontractbepalingen van de Europese Commissie. Gegevens die via hun zakelijke diensten binnenkomen, gebruiken zij niet om hun AI te trainen.</p>
                <p>Verder delen we gegevens alleen als dat nodig is voor uw project (bijvoorbeeld een adres voor een leverancier) of als de wet ons dat verplicht.</p>
            </section>

            <section id="bewaren">
                <h2>7. Hoe lang we bewaren</h2>
                <ul>
                    <li>Geluid van gespreksopnames: {{ $bewaardagen }} dagen, daarna automatisch verwijderd.</li>
                    <li>Een aanvraag waar geen opdracht uit komt: tot twee jaar na het laatste contact.</li>
                    <li>Projecten, foto's, akkoorden en opleverrapporten: zolang de garantie loopt en daarna nog zolang er vragen over het werk kunnen komen, tot maximaal tien jaar na oplevering.</li>
                    <li>Gegevens die bij onze administratie horen: zeven jaar, want dat vraagt de wet.</li>
                    <li>Accounts van medewerkers en klanten: tot het account wordt opgeheven.</li>
                    <li>Een ingelogde telefoon: na 60 dagen zonder gebruik moet u opnieuw inloggen, en bij uitloggen krijgt die telefoon geen meldingen meer.</li>
                </ul>
            </section>

            <section id="app">
                <h2>8. De app en cookies</h2>
                <p class="mt-2">De website gebruikt alleen cookies die nodig zijn om in te loggen en formulieren veilig te versturen. Er zijn geen cookies voor statistieken of reclame en we volgen u niet.</p>
                <p>De app Renovion OS bewaart de laatst bekeken schermen op uw telefoon, zodat u ook zonder verbinding verder kunt. Die gegevens worden gewist als u uitlogt. De app gebruikt de microfoon alleen voor inspreken en gespreksopnames, de camera of uw foto's alleen als u zelf een foto toevoegt, en meldingen voor pushberichten. Microfoon en meldingen vraagt de app pas als u die functie gebruikt; u kunt ze altijd uitzetten in de instellingen van uw telefoon. Welke meldingen u krijgt, kiest u zelf in Instellingen, Meldingen.</p>
            </section>

            <section id="beveiliging">
                <h2>9. Beveiliging</h2>
                <p class="mt-2">Alle verbindingen zijn versleuteld (https). Wachtwoorden bewaren we alleen versleuteld. Foto's en opnames staan niet openbaar op internet, maar worden pas getoond aan iemand die ingelogd is en ze mag zien. Iedere medewerker heeft een eigen account met een rol die bepaalt wat er te zien is, en belangrijke wijzigingen komen in een logboek. Merken we dat er toch iets mis is gegaan met uw gegevens, dan melden we dat aan de Autoriteit Persoonsgegevens en, als het nodig is, aan u.</p>
            </section>

            <section id="rechten">
                <h2>10. Uw rechten</h2>
                <p class="mt-2">U mag ons vragen welke gegevens we van u hebben en die laten verbeteren, aanvullen of verwijderen. U kunt bezwaar maken tegen een verwerking, vragen om die te beperken of uw gegevens in een gangbaar bestand meekrijgen. Heeft u ergens toestemming voor gegeven, dan kunt u die altijd weer intrekken.</p>
                <p>Mail daarvoor naar <a href="mailto:info@renovion.nl" class="font-semibold text-brand-700 underline">info@renovion.nl</a>. We reageren binnen een maand. Gegevens die we volgens de wet moeten bewaren, kunnen we niet eerder verwijderen; dat laten we u dan weten. Wilt u uw account in de app of het portaal laten opheffen, dan kan dat op dezelfde manier.</p>
                <p>Bent u niet tevreden over hoe we met uw gegevens omgaan, laat het ons dan eerst weten. U kunt ook een klacht indienen bij de <a href="https://www.autoriteitpersoonsgegevens.nl" class="font-semibold text-brand-700 underline" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>
                <p>Verandert er iets aan deze verklaring, dan zetten we de nieuwe versie hier, met de datum bovenaan.</p>
            </section>
        </div>

        <footer class="mt-6 text-center text-xs text-gray-600">
            <p class="font-semibold">Renovion - geen half werk en vage beloftes</p>
            <p class="mt-1">Mercatorweg 28, 6827 DC Arnhem, info@renovion.nl, +31 6 395 353 00, KVK 95384782</p>
        </footer>
    </main>
</body>
</html>
