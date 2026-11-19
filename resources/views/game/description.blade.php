<div
    x-data="animatedDescription({ independentScene: {{ !empty($sceneIndependent) ? 'true' : 'false' }}, persistent: {{ !empty($persistent) ? 'true' : 'false' }} })"
    x-effect="hideAfterMs={{ $hideAfterMs ?? 'null' }};persistent={{ $persistentExpression ?? (!empty($persistent) ? 'true' : 'false') }};play({{ $text }}, {{ $position }}, {{ $identity }}, {{ $animate ?? 'true' }}, {{ $area ?? 'null' }})"
    x-show="visible"
    x-transition:enter="transition-opacity duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="animated-description {{ $extraClass ?? '' }}"
    :class="'description-' + placement"
    :style="boxStyle"
    @resize.window="updateBounds()"
    x-ref="box"
    @description-area-click.window="clickFromArea($event.detail.key)"
    @click.stop="skipSpeech()"
    @keydown.enter.prevent="skipSpeech()"
    @keydown.space.prevent="skipSpeech()"
    @pointerdown.window="if(!narrationSpeaking && !persistent && !independentScene && visible && !$el.contains($event.target) && !($el.classList.contains('item-narration') && $event.target.closest('.preview-hotspot')))dismiss()"
    tabindex="0"
    role="region"
    aria-label="Description. Click to reveal text and stop speech; click again to hide."
>
    <div x-ref="words"><template
            x-for="(word,index) in words"
            :key="signature + ':' + index"
        ><span
                :class="index < count ? 'description-word' : 'description-word-pending'"
                :aria-hidden="index >= count"
                x-text="word"
            ></span></template></div>
</div>
