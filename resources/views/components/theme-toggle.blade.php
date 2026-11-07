<button type="button" x-data="{ dark: document.documentElement.classList.contains('dark') }"
    @vsg-theme-change.window="dark = $event.detail" @click="window.toggleVsgTheme()"
    :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'" :aria-pressed="dark.toString()"
    class="theme-toggle" aria-label="Toggle color theme" title="Switch light or dark mode">
    <svg x-show="!dark" aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path d="M20.5 13A8.5 8.5 0 0 1 11 3.5 8.5 8.5 0 1 0 20.5 13Z"/></svg>
    <svg x-show="dark" x-cloak aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>
    <span class="hidden sm:inline" x-text="dark ? 'Light' : 'Dark'">Theme</span>
</button>
