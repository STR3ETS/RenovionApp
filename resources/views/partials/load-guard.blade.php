{{-- Valt de verbinding weg terwijl de pagina laadt, dan komt de HTML wel binnen maar de CSS niet: kale
     links en reusachtige iconen. Dit moet inline staan en vóór @vite, want app.js laadt dan ook niet.
     Vlak na het terugkomen van de verbinding gebeurt dit ook, dus eerst zelf een paar keer herladen. --}}
<script>
    (() => {
        const KEY = 'renovion-load-guard';
        const tries = () => { try { return Number(sessionStorage.getItem(KEY)) || 0; } catch { return 3; } };
        const remember = (value) => { try { value ? sessionStorage.setItem(KEY, value) : sessionStorage.removeItem(KEY); } catch { /* geen opslag */ } };
        let failed = false;

        document.addEventListener('error', (event) => {
            if (!event.target.matches?.('link[rel="stylesheet"][href*="/build/"]')) return;
            failed = true;
            document.documentElement.innerHTML = '<body style="margin:0;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:24px;box-sizing:border-box;background:#070724;color:#fff;text-align:center;font-family:system-ui,sans-serif">'
                + '<h1 style="margin:0;font-size:20px">Geen verbinding</h1>'
                + '<p style="margin:0 0 16px;max-width:280px;font-size:15px;line-height:1.5;color:#b4b4cc">De pagina kon niet helemaal worden geladen. Controleer je internet en probeer het opnieuw.</p>'
                + '<button onclick="location.reload()" style="border:0;border-radius:14px;padding:14px 28px;background:#F85B0B;color:#fff;font:inherit;font-size:15px;font-weight:700">Opnieuw proberen</button>'
                + '</body>';
            document.documentElement.style.background = '#070724';

            // app.js laadt hier ook niet, dus de splash en de statusbalk regelen we zelf.
            window.Capacitor?.Plugins?.SplashScreen?.hide?.();
            window.Capacitor?.Plugins?.SystemBars?.setStyle?.({ style: 'DARK' });

            if (tries() < 3) {
                remember(tries() + 1);
                setTimeout(() => location.reload(), 2500);
            }
        }, true);

        window.addEventListener('load', () => failed || remember(0));
    })();
</script>
