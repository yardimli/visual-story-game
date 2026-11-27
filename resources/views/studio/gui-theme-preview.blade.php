@php
    $themeService = app(\App\Services\GuiThemes::class);
    $themeOptions = $themeService->options();
    $previewThemes = [];
    foreach ($themeOptions as $option) {
        $previewThemes[$option['id']] = $themeService->forStory(new \App\Models\Story(['ui_theme' => $option['id']]));
    }
@endphp
<div x-data="guiThemePreview(@js($story->ui_theme ?? 'text'), @js($previewThemes))" class="space-y-3">
    <label class="field">Game UI theme
        <select name="ui_theme" x-model="selected">
            @foreach ($themeOptions as $theme)
                <option value="{{ $theme['id'] }}" @selected(($story->ui_theme ?? 'text') === $theme['id'])>{{ $theme['label'] }}</option>
            @endforeach
        </select>
        <span class="text-sm text-stone-500">Styles story and inventory dialogs in test and published games.</span>
    </label>
    <section class="gui-theme-preview" aria-label="Game UI theme preview"
        :class="{'png-ui': theme?.renderer === 'png'}" :style="previewStyle">
        <p class="gui-theme-preview-caption">Theme preview</p>
        <div class="description-text-panel">
            You brush the dust from an old wooden chest. A small brass key rests beside it.
        </div>
        <div class="description-actions">
            <button type="button" class="game-action game-choice" @click.stop>
                <span class="choice-arrow" aria-hidden="true">&#10095;&#10095;</span><span>Look closer</span>
            </button>
            <button type="button" class="game-action game-choice" disabled>
                <span class="choice-arrow" aria-hidden="true">&#10095;&#10095;</span><span>Take key</span>
            </button>
            <button type="button" class="game-action game-choice game-choice-close" @click.stop>
                <span class="choice-arrow" aria-hidden="true">&#10095;&#10095;</span><span>Close</span>
            </button>
        </div>
        <p class="gui-theme-preview-hint">Hover or focus a button to preview its highlight. “Take key” shows the disabled state.</p>
    </section>
</div>
