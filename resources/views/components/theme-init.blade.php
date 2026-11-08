<script>
(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const valid = value => ['light', 'dark', 'paper'].includes(value);
    let preference;
    try { preference = localStorage.getItem('vsg-theme'); } catch (_) {}
    if (!valid(preference)) preference = null;
    const apply = theme => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.classList.toggle('paper', theme === 'paper');
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
        window.dispatchEvent(new CustomEvent('vsg-theme-change', {detail: theme}));
    };
    const system = () => media.matches ? 'dark' : 'light';
    apply(preference || system());
    window.setVsgTheme = theme => {
        if (!valid(theme)) return;
        preference = theme;
        try { localStorage.setItem('vsg-theme', theme); } catch (_) {}
        apply(theme);
    };
    window.toggleVsgTheme = () => {
        const themes = ['light', 'dark', 'paper'];
        window.setVsgTheme(themes[(themes.indexOf(document.documentElement.dataset.theme) + 1) % themes.length]);
    };
    media.addEventListener('change', () => { if (!preference) apply(system()); });
    window.addEventListener('storage', event => {
        if (event.key !== 'vsg-theme' && event.key !== null) return;
        preference = valid(event.newValue) ? event.newValue : null;
        apply(preference || system());
    });
})();
</script>
