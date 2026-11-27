<div
    x-data="animatedDescription({ separateActions: {{ !empty($actionButtons) ? 'true' : 'false' }}, independentScene: {{ !empty($sceneIndependent) ? 'true' : 'false' }}, persistent: {{ !empty($persistent) ? 'true' : 'false' }} })"
    x-effect="hideAfterMs={{ $hideAfterMs ?? 'null' }};persistent={{ $persistentExpression ?? (!empty($persistent) ? 'true' : 'false') }};play({{ $text }}, {{ $position }}, {{ $identity }}, {{ $animate ?? 'true' }}, {{ $area ?? 'null' }})"
    x-show="visible"
    x-transition.opacity
    class="animated-description description-framed {{ !empty($actionButtons) ? 'description-with-actions' : '' }} {{ $extraClass ?? '' }}"
    :class="'description-' + placement"
    :style="boxStyle"
    @resize.window="measure()"
    x-ref="box"
    @description-area-click.window="clickFromArea($event.detail.key)"
    @click.stop="skipSpeech()"
    @keydown.enter="if($event.target===$el){$event.preventDefault();skipSpeech()}"
    @keydown.space="if($event.target===$el){$event.preventDefault();skipSpeech()}"
    @pointerdown.window="dismissOutside($event)"
    tabindex="0"
    role="region"
    aria-label="Description. Click to reveal text and stop narration."
>
        <div class="description-text-panel" x-ref="textBox" :style="{height: height + 'px'}">
        <div class="dialog-content-scroll" x-ref="scroll">
    <div x-ref="words"><template x-for="(word,index) in words" :key="signature + ':' + index"><span
        :class="index < count ? 'description-word' : 'description-word-pending'"
        :aria-hidden="index >= count"
        x-text="word"
    ></span></template></div>
        </div>
        </div>
    @if (!empty($actionButtons))
        <div class="description-actions" x-ref="actions" @click.stop>
            <template x-if="!independentScene && narration?.actionAreaId && currentHotspots.some(h=>h.id===narration.actionAreaId)">
                <button type="button" class="game-action game-choice"
                    :disabled="!completed || narrationSpeaking || sceneTransition || !!pendingInventoryUse"
                    @click.stop="runPresentedAction()"
                ><span class="choice-arrow" aria-hidden="true">&#10095;&#10095;</span><span x-text="buttonLabel(currentHotspots.find(h=>h.id===narration.actionAreaId))"></span></button>
            </template>
            <button type="button" class="game-action game-choice game-choice-close" @click.stop="closeDialogue()"
            ><span class="choice-arrow" aria-hidden="true">&#10095;&#10095;</span><span>Close</span></button>
        </div>
    @endif
</div>
