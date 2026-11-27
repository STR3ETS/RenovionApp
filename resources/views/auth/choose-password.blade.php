<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Eigen wachtwoord kiezen - Renovion Groep</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0D1A2E">
<link rel="icon" type="image/png" href="/os/img/icon-i192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<script>try{var t=localStorage.getItem('rn-theme');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t}catch(e){}</script>
@vite(['resources/css/os.css', 'resources/css/shell.css'])
</head>
<body class="login-body">
<main class="login">
  <div class="login-brand">
    <svg viewBox="70 88 578 160" role="img" aria-label="Renovion Groep"><path d="M117 205H80V136L140 99L200 136V152" fill="none" stroke="var(--orange)" stroke-width="7"/><text x="124" y="205" textLength="288" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="500" fill="#FFFFFF">Renovion</text><text x="428" y="205" textLength="204" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="700" fill="var(--orange)">Groep</text><text x="128" y="241" font-family="'Plus Jakarta Sans', sans-serif" font-size="19" font-weight="500" textLength="504" lengthAdjust="spacing" fill="#A9B5C8">ÉÉN PLATFORM. HELE BOUWPROJECTEN.</text></svg>
  </div>
  <form class="card login-card" method="POST" action="{{ route('password.choose.store') }}">
    @csrf
    <div>
      <h1>Kies je eigen wachtwoord</h1>
      <p class="muted">Hoi {{ \Illuminate\Support\Str::before($user->name, ' ') }}, {{ session('via_uitnodiging') ? 'welkom bij Renovion Groep BV' : 'je bent ingelogd met een tijdelijk wachtwoord' }}. Kies een eigen wachtwoord dat alleen jij kent: minstens 10 tekens, met letters en cijfers.</p>
    </div>
    @if ($errors->any())
      <div class="alert bad" role="alert"><div class="grow">{{ $errors->first() }}</div></div>
    @endif
    <input type="hidden" name="username" value="{{ $user->email }}" autocomplete="username">
    <div class="f">
      <label for="password">Nieuw wachtwoord</label>
      <input class="in" type="password" name="password" id="password" required minlength="10" autofocus autocomplete="new-password">
    </div>
    <div class="f">
      <label for="password_confirmation">Nog een keer</label>
      <input class="in" type="password" name="password_confirmation" id="password_confirmation" required minlength="10" autocomplete="new-password">
    </div>
    <button class="btn pri lg" type="submit">Wachtwoord opslaan</button>
    <button class="btn ghost" type="submit" form="logoutform">Uitloggen</button>
  </form>
  <form id="logoutform" method="POST" action="{{ route('logout') }}" hidden>@csrf</form>
  <p class="login-foot">Renovion Groep BV, Mercatorweg 28, 6827 DC Arnhem</p>
</main>
</body>
</html>
