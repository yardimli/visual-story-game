<script>
(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    let preference;
    try { preference = localStorage.getItem('vsg-theme'); } catch (_) {}
    const apply = dark => {
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        window.dispatchEvent(new CustomEvent('vsg-theme-change', {detail: dark}));
    };
    apply(preference === 'dark' || (preference !== 'light' && media.matches));
    window.toggleVsgTheme = () => {
        preference = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        try { localStorage.setItem('vsg-theme', preference); } catch (_) {}
        apply(preference === 'dark');
    };
    media.addEventListener('change', event => { if (!preference) apply(event.matches); });
    window.addEventListener('storage', event => {
        if (event.key !== 'vsg-theme') return;
        preference = event.newValue;
        apply(preference === 'dark' || (!preference && media.matches));
    });
})();
</script>
