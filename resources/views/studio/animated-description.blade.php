<div x-data="animatedDescription()" x-effect="play({{ $text }}, {{ $position }}, {{ $identity }})"
 x-show="visible" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
 class="animated-description {{ $extraClass ?? '' }}" :class="'description-'+placement" :style="{height:height+'px'}" x-ref="box"
 @click.stop="reveal()" @keydown.enter.prevent="reveal()" @keydown.space.prevent="reveal()" @pointerdown.window="if(visible && !$el.contains($event.target))dismiss()"
 tabindex="0" role="region" aria-label="Description. Click to reveal all text.">
 <div x-ref="words"><template x-for="(word,index) in words.slice(0,count)" :key="signature+':'+index"><span class="description-word" x-text="word"></span></template></div>
</div>
