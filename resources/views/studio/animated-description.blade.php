<div x-data="animatedDescription({independentScene:{{ !empty($sceneIndependent) ? 'true' : 'false' }},persistent:{{ !empty($persistent) ? 'true' : 'false' }}})" x-effect="persistent={{ $persistentExpression ?? (!empty($persistent) ? 'true' : 'false') }};play({{ $text }}, {{ $position }}, {{ $identity }}, {{ $animate ?? 'true' }}, {{ $area ?? 'null' }})"
 x-show="visible" x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
 class="animated-description {{ $extraClass ?? '' }}" :class="'description-'+placement" :style="boxStyle" @resize.window="updateBounds()" x-ref="box"
 @click.stop="reveal()" @keydown.enter.prevent="reveal()" @keydown.space.prevent="reveal()" @pointerdown.window="if(!persistent && !independentScene && visible && !$el.contains($event.target) && !($el.classList.contains('item-narration') && $event.target.closest('.preview-hotspot')))dismiss()"
 tabindex="0" role="region" aria-label="Description. Click to reveal all text.">
 <div x-ref="words"><template x-for="(word,index) in words" :key="signature+':'+index"><span :class="index<count?'description-word':'description-word-pending'" :aria-hidden="index>=count" x-text="word"></span></template></div>
</div>
