<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Renovion Groep</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0D1A2E">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Renovion">
<meta name="format-detection" content="telephone=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="{{ route('manifest') }}">
<link rel="icon" type="image/png" href="/os/img/icon-i192.png">
<link rel="apple-touch-icon" href="/os/img/icon-i180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Caveat:wght@500;700&display=swap">
<script>try{var t=localStorage.getItem('rn-theme');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t}catch(e){}</script>
<script>window.OS_BOOT=@json($boot);</script>
@vite(['resources/css/os.css', 'resources/css/shell.css', 'resources/js/os/main.js'])
</head>
<body>
<div class="app" id="app">
  <aside class="side" id="side">
    <div class="sk-side" aria-hidden="true">
      <span class="sk sk-logo"></span>
      @foreach ([4, 6, 5, 4] as $group)
        <span class="sk sk-group"></span>
        @for ($i = 0; $i < $group; $i++)<span class="sk sk-item"></span>@endfor
      @endforeach
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="iconbtn burger" data-act="burger" aria-label="Menu openen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      <div class="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="gsearch" type="search" placeholder="Zoek klanten, projecten, taken, medewerkers…" autocomplete="off" aria-label="Zoeken">
        <div class="sres" id="sres" hidden></div>
      </div>
      <div class="top-sp"></div>
      <span class="demo-date" id="demodate"></span>
      <button class="iconbtn hide-s" data-act="nova" aria-label="Nova openen" title="Nova"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/></svg></button>
      <button class="iconbtn" data-act="notif" aria-label="Meldingen" id="notifbtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg><span class="dot" id="notifdot" hidden></span></button>
      <button class="btn pri" data-act="plus" id="plusbtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg><span>Nieuw</span></button>
    </header>
    <main id="view">
      <div class="page sk-page" aria-busy="true" aria-label="Renovion Groep wordt geladen">
        <div class="sk sk-band"></div>
        <div class="grid kpis">
          <div class="card sk-card"><span class="sk sk-line w40"></span><span class="sk sk-num"></span><span class="sk sk-line w60"></span></div>
          <div class="card sk-card"><span class="sk sk-line w40"></span><span class="sk sk-num"></span><span class="sk sk-line w60"></span></div>
          <div class="card sk-card"><span class="sk sk-line w40"></span><span class="sk sk-num"></span><span class="sk sk-line w60"></span></div>
          <div class="card sk-card"><span class="sk sk-line w40"></span><span class="sk sk-num"></span><span class="sk sk-line w60"></span></div>
        </div>
        <div class="grid g-main">
          <div class="card sk-card">@for ($i = 0; $i < 4; $i++)<div class="sk-row"><span class="sk sk-ic"></span><span class="sk-tx"><span class="sk sk-line w70"></span><span class="sk sk-line w40"></span></span></div>@endfor</div>
          <div class="card sk-card">@for ($i = 0; $i < 4; $i++)<div class="sk-row"><span class="sk sk-ic"></span><span class="sk-tx"><span class="sk sk-line w60"></span><span class="sk sk-line w30"></span></span></div>@endfor</div>
        </div>
      </div>
      <noscript><div class="page"><div class="card card-b">Renovion Groep heeft JavaScript nodig.</div></div></noscript>
    </main>
  </div>
</div>
<div class="toasts" id="toasts"></div>
<div id="layer"></div>
<form id="logoutform" method="POST" action="{{ route('logout') }}" hidden>@csrf</form>
</body>
</html>
