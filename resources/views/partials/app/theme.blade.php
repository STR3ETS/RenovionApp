{{-- Weergave van de iOS/Android-app (users.app_theme): licht, donker of volgens de telefoon (standaard). In de
     <head>, vóór de stijl, zodat er geen flits van het andere thema is. html.os-dark zet de donkere laag van
     native-app.css aan. Kiezen gebeurt in Instellingen in de app. --}}
<script>
    (() => {
        const root = document.documentElement;
        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const theme = @js(auth()->user()?->app_theme ?? \App\Models\User::DEFAULT_APP_THEME);
        const apply = () => {
            root.classList.toggle('os-dark', theme === 'donker' || (theme === 'telefoon' && query.matches));
            root.dataset.theme = theme;
            window.dispatchEvent(new Event('renovion-top-changed'));
        };
        query.addEventListener?.('change', apply);
        apply();
    })();
</script>
