<select aria-label="Color theme" title="Color theme" class="theme-toggle pr-8"
    x-data="{ theme: document.documentElement.dataset.theme || 'light' }" x-model="theme"
    @vsg-theme-change.window="theme = $event.detail" @change="window.setVsgTheme(theme)">
    <option value="light">Light</option>
    <option value="dark">Dark</option>
    <option value="paper">Paper</option>
</select>
