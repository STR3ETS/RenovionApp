{{-- Weergave van de iOS/Android-app (users.app_theme): licht, donker of volgens de telefoon. In de <head>,
     vóór de stijl, zodat er geen flits van het andere thema is. html.os-dark zet de donkere laag van
     native-app.css aan; window.renovionTheme laat het zijmenu wisselen zonder te herladen. --}}
<script>
    (() => {
        const root = document.documentElement;
        const query = window.matchMedia('(prefers-color-scheme: dark)');
        let theme = @js(auth()->user()?->app_theme ?? 'licht');
        const apply = () => {
            root.classList.toggle('os-dark', theme === 'donker' || (theme === 'telefoon' && query.matches));
            root.dataset.theme = theme;
            window.dispatchEvent(new Event('renovion-top-changed'));
        };
        window.renovionTheme = { get: () => theme, set: (value) => { theme = value; apply(); } };
        query.addEventListener?.('change', apply);
        apply();
    })();
</script>
