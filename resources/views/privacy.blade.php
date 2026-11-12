@php
    $bewaardagen = config('renovion.transcription.audio_retention_days');
    $secties = [
        'wettelijk' => 'Wettelijke bepalingen',
        'toegang' => 'Toegang tot Renovion Dash',
        'content' => 'De content van Renovion Dash',
        'beheer' => 'Het beheer van Renovion Dash',
        'verantwoordelijkheden' => 'Verantwoordelijkheden',
        'gegevens' => 'Het verzamelen van gegevens',
        'rechten' => 'Uw rechten',
        'verwerking' => 'Met wie we gegevens delen',
        'aanbiedingen' => 'Commerciële aanbiedingen',
        'bewaren' => 'Bewaartermijn gegevens',
        'cookies' => 'Cookies en de app',
        'gesprekken' => 'Nova, AI en gespreksopnames',
        'beeldmateriaal' => 'Beeldmateriaal',
        'recht' => 'Toepasselijk recht',
        'contact' => 'Contact',
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
        <p class="mt-2 text-sm text-gray-600">Voor Renovion Dash, de app Renovion OS, het klantportaal en de offertelinks. Laatst bijgewerkt op 12 november 2026.</p>

        <nav aria-label="Inhoud" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">Inhoud</p>
            <ol class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @foreach ($secties as $id => $kop)
                    <li><a href="#{{ $id }}" class="block py-1 font-medium text-navy-900 hover:text-brand-700">Artikel {{ $loop->iteration }}. {{ $kop }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="mt-4 space-y-4 text-[15px] leading-relaxed text-gray-700 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-navy-950 [&_li]:mt-1 [&_p+p]:mt-3 [&_section]:scroll-mt-24 [&_section]:rounded-2xl [&_section]:border [&_section]:border-gray-200 [&_section]:bg-white [&_section]:p-5 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">

            <section id="inleiding">
                <p>Renovion respecteert de privacy van iedereen met wie we werken: klanten en mensen die een aanvraag doen, bezoekers van een offertelink of het klantportaal, en medewerkers en partners die met Renovion Dash of de app werken. Daarom leggen we hier uit welke persoonsgegevens we verwerken, waarvoor we dat doen, met wie we ze delen en hoe u uw rechten kunt gebruiken. Meer over de bescherming van persoonsgegevens leest u bij de <a href="https://www.autoriteitpersoonsgegevens.nl" class="font-semibold text-brand-700 underline" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>
                <p>Alleen de versie die hier staat geldt, totdat een nieuwe versie haar vervangt. De datum bovenaan laat zien wanneer deze verklaring voor het laatst is bijgewerkt.</p>
            </section>

            <section id="wettelijk">
                <h2>Artikel 1. Wettelijke bepalingen</h2>
                <p class="mt-2">Deze verklaring geldt voor Renovion Dash (de webapplicatie), de app Renovion OS, het klantportaal en de offertelinks, hierna samen "Renovion Dash".</p>
                <p>Verantwoordelijk voor de verwerking van persoonsgegevens (hierna "Renovion" of "wij"): Renovion, Mercatorweg 28, 6827 DC Arnhem, KVK 95384782.</p>
            </section>

            <section id="toegang">
                <h2>Artikel 2. Toegang tot Renovion Dash</h2>
                <p class="mt-2">Toegang tot Renovion Dash en het gebruik ervan is strikt persoonlijk. Deel uw inloggegevens niet met anderen. U gebruikt Renovion Dash, en de gegevens en informatie die daarin staan, alleen voor het werk of het project waarvoor u toegang heeft. U gebruikt ze niet voor commerciële, politieke of publicitaire doeleinden, en in het bijzonder niet voor ongevraagde elektronische aanbiedingen.</p>
            </section>

            <section id="content">
                <h2>Artikel 3. De content van Renovion Dash</h2>
                <p class="mt-2">Alle merken, logo's, afbeeldingen, teksten, illustraties, video's en de techniek waarmee Renovion Dash werkt, zijn beschermd door intellectuele eigendomsrechten. Zonder onze voorafgaande schriftelijke toestemming mag u die niet kopiëren, verspreiden, gebruiken of aanpassen, geheel of gedeeltelijk. Dat geldt niet voor uw eigen gegevens en voor stukken die voor u bedoeld zijn, zoals uw offerte of opleverrapport.</p>
                <p>Als we niet meteen optreden tegen een inbreuk, betekent dat niet dat we die toestaan of afzien van stappen.</p>
            </section>

            <section id="beheer">
                <h2>Artikel 4. Het beheer van Renovion Dash</h2>
                <p class="mt-2">Om Renovion Dash goed te laten werken, kunnen we op elk moment:</p>
                <ul>
                    <li>de toegang voor een bepaalde groep gebruikers stoppen, onderbreken of beperken, voor heel Renovion Dash of een deel ervan;</li>
                    <li>informatie verwijderen die de werking verstoort of in strijd is met de wet of met de omgangsvormen op internet;</li>
                    <li>Renovion Dash tijdelijk niet beschikbaar maken om updates uit te voeren.</li>
                </ul>
            </section>

            <section id="verantwoordelijkheden">
                <h2>Artikel 5. Verantwoordelijkheden</h2>
                <p class="mt-2">We doen ons best om Renovion Dash altijd beschikbaar te houden. Voor zover de wet dat toestaat, zijn we niet aansprakelijk voor storingen, onderbrekingen of fouten waardoor Renovion Dash of een onderdeel ervan niet werkt.</p>
                <p>Hoe u verbinding maakt met Renovion Dash is uw eigen verantwoordelijkheid. Bescherm uw apparatuur en uw gegevens zelf, bijvoorbeeld tegen virussen. Voor zover de wet dat toestaat, zijn we niet aansprakelijk voor schade aan u, aan anderen of aan uw apparatuur door het gebruik van Renovion Dash. Gebruikt u Renovion Dash in strijd met deze verklaring en raken wij daardoor betrokken bij een geschil, dan kunnen we de schade die we daardoor lijden op u verhalen.</p>
                <p>Dit artikel doet niets af aan de afspraken over uw opdracht, zoals de garantie op het werk.</p>
            </section>

            <section id="gegevens">
                <h2>Artikel 6. Het verzamelen van gegevens</h2>
                <p class="mt-2">Persoonsgegevens zijn alle gegevens over een persoon die direct of indirect herkenbaar is, bijvoorbeeld aan een naam, adres, telefoonnummer of een online kenmerk zoals een IP-adres. Renovion verzamelt ze zelf en via de dienstverleners uit artikel 8.</p>
                <p><b class="text-navy-950">Van klanten en aanvragers:</b></p>
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
                    <li>gespreksopnames, de uitgeschreven tekst en het verslag (zie artikel 12);</li>
                    <li>van uw telefoon: een code om pushmeldingen te sturen, het soort toestel en uw meldingsvoorkeuren.</li>
                </ul>
                <p class="mt-3"><b class="text-navy-950">Van iedereen:</b> wie wat wanneer heeft gewijzigd (een logboek), en technische gegevens zoals uw IP-adres en het moment van inloggen.</p>
                <p class="mt-3"><b class="text-navy-950">Waarvoor en op welke grond:</b></p>
                <ul>
                    <li><b class="text-navy-950">Om uw opdracht uit te voeren</b> (uitvoering van een overeenkomst): een offerte maken, het werk plannen en uitvoeren, u op de hoogte houden en opleveren.</li>
                    <li><b class="text-navy-950">Om uw aanvraag op te volgen</b> (stappen vóór een overeenkomst): contact opnemen, een intake plannen en een offerte sturen.</li>
                    <li><b class="text-navy-950">Om aan te tonen wat er is gebeurd</b> (gerechtvaardigd belang): foto-bewijs, akkoorden en het logboek, bijvoorbeeld bij vragen over garantie of meerwerk.</li>
                    <li><b class="text-navy-950">Om het werk goed te organiseren</b> (gerechtvaardigd belang): taken, planning, teamchat, meldingen en verslagen van overleggen.</li>
                    <li><b class="text-navy-950">Omdat de wet het vraagt</b> (wettelijke plicht): onze administratie, zoals offertes en afspraken die bij de boekhouding horen.</li>
                </ul>
                <p class="mt-3">Is een gegeven verplicht om een onderdeel van Renovion Dash te gebruiken, dan zeggen we dat op het moment dat we erom vragen.</p>
            </section>

            <section id="rechten">
                <h2>Artikel 7. Uw rechten met betrekking tot uw gegevens</h2>
                <p class="mt-2">Op grond van artikel 13 lid 2 sub b AVG heeft iedereen recht op inzage, rectificatie (verbeteren of aanvullen) en wissing van zijn persoonsgegevens, en op beperking van de verwerking. U mag bezwaar maken tegen de verwerking en heeft recht op gegevensoverdraagbaarheid: uw gegevens in een gangbaar bestand meekrijgen. Heeft u ergens toestemming voor gegeven, dan kunt u die altijd weer intrekken. U kunt ook uw account in de app of het portaal laten opheffen.</p>
                <p>Mail daarvoor naar <a href="mailto:info@renovion.nl" class="font-semibold text-brand-700 underline">info@renovion.nl</a>. We moeten zeker weten dat het verzoek van u komt. Daarom kunnen we u vragen wie u bent, bijvoorbeeld door u te bellen op het nummer dat we van u hebben. Stuur geen kopie van uw identiteitsbewijs mee, tenzij we daarom vragen.</p>
                <p>Binnen een maand na uw verzoek krijgt u antwoord. Is een verzoek ingewikkeld of krijgen we er veel, dan kan die termijn met twee maanden worden verlengd; dat laten we u binnen de eerste maand weten. Gegevens die we volgens de wet moeten bewaren, kunnen we niet eerder verwijderen. Ook dat laten we u dan weten.</p>
                <p>Bent u niet tevreden over hoe we met uw gegevens omgaan, laat het ons dan eerst weten. U kunt ook een klacht indienen bij de <a href="https://www.autoriteitpersoonsgegevens.nl" class="font-semibold text-brand-700 underline" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>
            </section>

            <section id="verwerking">
                <h2>Artikel 8. Met wie we gegevens delen</h2>
                <p class="mt-2">Binnen Renovion ziet iedereen alleen wat nodig is voor het eigen werk: een uitvoerder ziet alleen de projecten waar die aan werkt, een klant alleen het eigen project in het portaal. Daarnaast werken we met deze dienstverleners, die gegevens alleen in onze opdracht verwerken:</p>
                <ul>
                    <li><b class="text-navy-950">De partij die onze server host</b>, waar Renovion Dash en alle gegevens staan.</li>
                    <li><b class="text-navy-950">Anthropic</b> (Claude), voor de voorstellen en verslagen van Nova.</li>
                    <li><b class="text-navy-950">OpenAI</b> (Whisper) of <b class="text-navy-950">AssemblyAI</b> (in de EU), om gespreksopnames naar tekst om te zetten.</li>
                    <li><b class="text-navy-950">Google Firebase</b>, om pushmeldingen naar telefoons te sturen. Die krijgt alleen de code van de telefoon en de korte tekst van de melding.</li>
                    <li><b class="text-navy-950">Bunny.net</b>, voor de lettertypen van de website.</li>
                </ul>
                <p class="mt-3">Anthropic, OpenAI en Google zitten in de Verenigde Staten. Gegevens gaan daarheen alleen met de waarborgen die de AVG vraagt, zoals het EU-VS Data Privacy Framework of de standaardcontractbepalingen van de Europese Commissie. Gegevens die via hun zakelijke diensten binnenkomen, gebruiken zij niet om hun AI te trainen.</p>
                <p>Verder delen we gegevens alleen als dat nodig is voor uw project (bijvoorbeeld een adres voor een leverancier) of als de wet ons daartoe verplicht. Vragen de autoriteiten om gegevens omdat iemand wordt verdacht van een overtreding, dan geven we die alleen na een uitdrukkelijk en gemotiveerd verzoek, en alleen voor zover de wet dat vraagt.</p>
                <p><b class="text-navy-950">Beveiliging:</b> alle verbindingen zijn versleuteld (https). Wachtwoorden bewaren we alleen versleuteld. Foto's en opnames staan niet openbaar op internet, maar worden pas getoond aan iemand die ingelogd is en ze mag zien. Iedere medewerker heeft een eigen account met een rol die bepaalt wat er te zien is, en belangrijke wijzigingen komen in een logboek. Merken we dat er toch iets mis is gegaan met uw gegevens, dan melden we dat aan de Autoriteit Persoonsgegevens en, als het nodig is, aan u.</p>
            </section>

            <section id="aanbiedingen">
                <h2>Artikel 9. Commerciële aanbiedingen</h2>
                <p class="mt-2">We gebruiken uw gegevens niet voor reclame van anderen en verkopen ze nooit. Wilt u van ons geen berichten krijgen die niet over uw aanvraag of project gaan, mail dan naar <a href="mailto:info@renovion.nl" class="font-semibold text-brand-700 underline">info@renovion.nl</a>.</p>
                <p>Ziet u in Renovion Dash persoonsgegevens van anderen, bijvoorbeeld van een klant of collega, dan gebruikt u die alleen voor uw werk. U verzamelt ze niet en gebruikt ze niet op een andere manier die inbreuk maakt op hun privacy.</p>
            </section>

            <section id="bewaren">
                <h2>Artikel 10. Bewaartermijn gegevens</h2>
                <p class="mt-2">We bewaren gegevens niet langer dan nodig is voor het doel waarvoor we ze hebben verzameld, of zolang de wet dat vraagt:</p>
                <ul>
                    <li>Geluid van gespreksopnames: {{ $bewaardagen }} dagen, daarna automatisch verwijderd.</li>
                    <li>Een aanvraag waar geen opdracht uit komt: zolang we die nodig hebben om op uw aanvraag terug te komen. Daarna verwijderen we hem.</li>
                    <li>Projecten, foto's, akkoorden en opleverrapporten: zolang de garantie loopt en daarna zolang er vragen of aanspraken over het werk kunnen komen. Bij werk aan een woning kan dat volgens de wet tot twintig jaar na oplevering zijn.</li>
                    <li>Gegevens die bij onze administratie horen: zeven jaar, want dat vraagt de wet.</li>
                    <li>Accounts van medewerkers en klanten: tot het account wordt opgeheven.</li>
                    <li>Een ingelogde telefoon: na 60 dagen zonder gebruik moet u opnieuw inloggen, en bij uitloggen krijgt die telefoon geen meldingen meer.</li>
                </ul>
            </section>

            <section id="cookies">
                <h2>Artikel 11. Cookies en de app</h2>
                <p class="mt-2">Een cookie is een klein tekstbestand dat bij een bezoek aan Renovion Dash op uw computer, telefoon of tablet wordt geplaatst. Wij gebruiken alleen functionele cookies: om u ingelogd te houden en formulieren veilig te versturen. Daar is geen toestemming voor nodig, dus u ziet geen cookiemelding. We gebruiken geen analytische cookies, tracking cookies of advertentiecookies, en we volgen u niet. Meer over cookies en hoe u ze beheert of verwijdert, leest u bij de <a href="https://www.autoriteitpersoonsgegevens.nl" class="font-semibold text-brand-700 underline" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>
                <p>De app Renovion OS bewaart de laatst bekeken schermen op uw telefoon, zodat u ook zonder verbinding verder kunt. Die gegevens worden gewist als u uitlogt. De app gebruikt de microfoon alleen voor inspreken en gespreksopnames, de camera of uw foto's alleen als u zelf een foto toevoegt, en meldingen voor pushberichten. Microfoon en meldingen vraagt de app pas als u die functie gebruikt; u kunt ze altijd uitzetten in de instellingen van uw telefoon. Welke meldingen u krijgt, kiest u zelf in Instellingen, Meldingen.</p>
            </section>

            <section id="gesprekken">
                <h2>Artikel 12. Nova, AI en gespreksopnames</h2>
                <p class="mt-2">In Renovion Dash helpt Nova, een assistent op basis van kunstmatige intelligentie. Nova maakt voorstellen: een taak of afspraak uit een ingesproken zin, calculatieregels uit een omschrijving, een tekst voor een offerte, een samenvatting van een gesprek of een overzicht van de dag. Daarvoor gaat de tekst die nodig is naar de AI-dienst, bijvoorbeeld uw naam, de omschrijving van de klus of een stuk van een gesprek. Een voorstel van Nova is altijd een concept: een medewerker controleert het en beslist. Nova neemt geen besluiten over u zonder dat er een mens naar kijkt.</p>
                <p>Inspreken (dicteren) gebruikt de spraakherkenning van uw telefoon of browser, van Google of Apple.</p>
                <p>Een medewerker kan een overleg opnemen, zoals een bouwvergadering of een gesprek bij u thuis. Dat gebeurt alleen als iedereen die meedoet weet dat het gesprek wordt opgenomen; de medewerker moet dat bevestigen voordat de opname start. Het geluid wordt omgezet naar tekst, daarna maakt Nova een verslag met besluiten, acties en open vragen. Het geluid wordt na {{ $bewaardagen }} dagen automatisch verwijderd. De tekst en het verslag blijven bij het project staan. Wilt u niet dat een gesprek wordt opgenomen, zeg het dan: dan nemen we het niet op. Een opname kan ook achteraf worden verwijderd.</p>
            </section>

            <section id="beeldmateriaal">
                <h2>Artikel 13. Beeldmateriaal</h2>
                <p class="mt-2">Aan voorbeeldfoto's, afbeeldingen en illustraties in Renovion Dash, offertes en het portaal kunnen geen rechten worden ontleend. Wat we voor u gaan doen, staat in de offerte die u ondertekent.</p>
            </section>

            <section id="recht">
                <h2>Artikel 14. Toepasselijk recht</h2>
                <p class="mt-2">Op deze verklaring is Nederlands recht van toepassing. Bij een geschil over deze verklaring is de rechtbank van de plaats waar Renovion gevestigd is bevoegd, behalve als de wet iets anders bepaalt.</p>
            </section>

            <section id="contact">
                <h2>Artikel 15. Contact</h2>
                <p class="mt-2">Voor vragen over privacy of over Renovion Dash: <a href="mailto:info@renovion.nl" class="font-semibold text-brand-700 underline">info@renovion.nl</a> of <a href="tel:+31639535300" class="font-semibold text-brand-700 underline">+31 6 395 353 00</a>. Of schrijf naar Renovion, Mercatorweg 28, 6827 DC Arnhem.</p>
            </section>
        </div>

        <footer class="mt-6 text-center text-xs text-gray-600">
            <p class="font-semibold">Renovion - geen half werk en vage beloftes</p>
            <p class="mt-1">Mercatorweg 28, 6827 DC Arnhem, info@renovion.nl, +31 6 395 353 00, KVK 95384782</p>
        </footer>
    </main>
</body>
</html>
