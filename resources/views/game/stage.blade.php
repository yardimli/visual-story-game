<template x-teleport="body">
    <dialog
        x-ref="previewDialog"
        class="preview-dialog"
        :style="gameDialogStyle"
        aria-label="Test scenes"
        @pointermove.window="moveInventoryDrag($event)"
        @pointerup.window="endInventoryDrag($event)"
        @pointercancel.window="endInventoryDrag($event,true)"
        @click="if($event.target===$el)$el.close()"
        @close="closePreview()"
        @cancel="if(modal){$event.preventDefault();modal=null;}"
    >
        <template x-if="introVideo">
            <div
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black"
                @click.stop
                @pointerdown.stop
            >
                <video
                    x-ref="introVideoPlayer"
                    :src="introVideo.url"
                    :aria-label="introVideo.name"
                    class="h-full w-full object-contain"
                    playsinline
                    preload="auto"
                    x-effect="$el.muted=audioMuted;if(!sceneTransition)$nextTick(()=>playIntroVideo($el))"
                    @ended="finishIntroVideo()"
                    x-on:error="introVideoFailed=true"
                ></video>
                <div class="absolute right-4 top-4 flex gap-3">
                    <button
                        type="button"
                        class="btn-secondary"
                        @click="audioMuted=soundPlayer.toggleMute()"
                        x-text="audioMuted?'Unmute':'Mute all'"
                    ></button>
                    <button
                        type="button"
                        class="btn-primary"
                        @click="finishIntroVideo()"
                    >Skip video</button>
                </div>
                <button
                    x-show="introVideoBlocked && !introVideoFailed"
                    type="button"
                    class="btn-primary absolute"
                    @click="playIntroVideo($refs.introVideoPlayer)"
                >Play video</button>
                <p
                    x-show="introVideoFailed"
                    role="alert"
                    class="absolute rounded bg-black/80 p-4 text-white"
                >Video could not load. Use Skip video to continue.</p>
            </div>
        </template>
        <div
            x-cloak
            x-show="sceneTransition"
            class="scene-loading-overlay"
            :class="'scene-transition-' + transitionPhase"
            role="status"
            aria-live="polite"
            aria-label="Loading scene"
            @click.stop
            @pointerdown.stop
        >
            <div x-show="transitionPhase==='loading'" class="flex w-72 flex-col items-center gap-3 text-white">
                <span class="scene-loading-spinner" aria-hidden="true"></span>
                <span x-text="'Loading scene… ' + sceneLoadProgress.percent + '%' "></span>
                <progress class="h-3 w-full" max="100" :value="sceneLoadProgress.percent" aria-label="Scene download progress"></progress>
                <span class="text-sm" x-text="sceneLoadProgress.completed + ' / ' + sceneLoadProgress.total + ' files ready'"></span>
            </div>
        </div>
        <button
            type="button"
            class="fixed right-4 top-3 z-50 rounded-lg border border-white/50 bg-black/70 px-3 py-2 text-sm text-white"
            @click.stop="audioMuted=soundPlayer.toggleMute()"
            :aria-pressed="audioMuted"
            x-text="audioMuted?'Unmute':'Mute all'"
        ></button>
        <div
            class="contents"
            x-data="hoverDescription()"
            @area-hover-start="if(!pendingInventoryUse)narration=null"
            @pointerdown="outsideClick($event)"
            x-effect="if(!preview)resetHover();if(inventoryDrag?.active || pendingInventoryUse)dismiss()"
        >
            <template x-if="preview && !introVideo && asset(displayed?.asset_id)">
                <div
                    class="preview-stage"
                    :style="examineMode ? 'cursor:help' : null"
                    :class="narrationSpeaking ? 'scene-narrating' : ''"
                    @click="if(modalArea?.action==='inventory' && !$event.target.closest('.inventory-interaction-overlay,.game-inventory,.preview-hotspot'))modal=null"
                >
                    <template
                        x-for="scene in [displayed]"
                        :key="previewPageId + ':' + scene.id"
                    ><img
                            :src="asset(scene.asset_id).url"
                            :alt="scene.name"
                            class="preview-scene-image"
                            :class="outgoingScene ? 'scene-fade-in' : ''"
                            draggable="false"
                        ></template>
                    <template x-if="outgoingScene && asset(outgoingScene.asset_id)">
                        <div
                            class="preview-outgoing-image"
                            aria-hidden="true"
                        ><img
                                :src="asset(outgoingScene.asset_id).url"
                                alt=""
                                class="h-full w-full object-contain"
                            ><template
                                x-for="layer in outgoingScene.layers"
                                :key="layer.id"
                            ><img
                                    :src="layer.url"
                                    :style="layer.style"
                                    alt=""
                                    class="absolute"
                                ></template></div>
                    </template>
                    <div
                        class="scene-area-layers"
                        :class="outgoingScene ? 'scene-fade-in' : ''"
                    >
                        <template
                            x-for="h in currentHotspots"
                            :key="h.id"
                        ><button
                                type="button"
                                :style="style(h) + ';z-index:' + areaLayer(h) + ';cursor:' + areaCursor(h)"
                                :aria-label="h.label"
                                :title="areaTooltip(h)"
                                class="preview-hotspot absolute cursor-pointer border-0 bg-transparent focus:outline-none"
                                :disabled="sceneTransition || ((narrationSpeaking || !!pendingInventoryUse) && !hasAreaMessage(h))"
                                @mouseenter="if(!hasAreaMessage(h) && !narrationSpeaking && !sceneTransition && !inventoryDrag?.active && !pendingInventoryUse)hoverArea(hoverDetails(h),previewPageId,previewId)"
                                @mousemove="if(!hasAreaMessage(h) && !narrationSpeaking && !sceneTransition && !inventoryDrag?.active && !pendingInventoryUse)hoverArea(hoverDetails(h),previewPageId,previewId)"
                                @mouseleave="leaveArea()"
                                @focus="if(!hasAreaMessage(h) && !narrationSpeaking && !sceneTransition && !inventoryDrag?.active && !pendingInventoryUse)hoverArea(hoverDetails(h),previewPageId,previewId)"
                                @blur="leaveArea()"
                                @click.stop="if(sceneTransition)return;if(examineMode&&!narrationSpeaking){dismiss();activate(h);return;}if(clickAreaMessage(h))return;if(narrationSpeaking)return;if(h.action==='inventory')inventoryOpen=true;clickArea(h,()=>activate(h,(()=>{const r=$el.closest('.preview-stage').getBoundingClientRect();return $event.detail?{x:($event.clientX-r.left)/r.width,y:($event.clientY-r.top)/r.height}:null;})()))"
                            ><template x-if="areaImage(h)"><img
                                        :src="areaImage(h).url"
                                        :class="h.action !== 'state_v2' && h.overlay_always === false ? 'area-image-on-hover' :
                                            ''"
                                        :style="areaImageStyle(h)"
                                        alt=""
                                        class="h-full w-full object-contain"
                                    ></template></button></template>
                    </div>
                    <div
                        x-show="visible"
                        class="animated-description hover-description"
                        :class="'description-' + placement"
                        :style="hoverBoxStyle"
                        @resize.window="updateHoverBounds()"
                        x-ref="box"
                        x-transition.opacity.duration.150ms
                        @click.stop="skipSpeech()"
                        role="region"
                        aria-label="Area description"
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
                    @include('game.description', [
                        'text' => "preview && !sceneTransition ? sceneIntroText : ''",
                        'position' => "displayed?.description_position || 'top'",
                        'identity' => "previewPageId+':'+previewId",
                        'extraClass' => 'scene-narration',
                        'sceneIndependent' => true,
                    ])
                    <div
                        x-show="!modal && (!visible || !!pendingInventoryUse)"
                        @description-complete="descriptionCompleted($event.detail.key)"
                        @description-hidden="if(!pendingInventoryUse && narration && $event.detail.key===previewId+':'+narrationVersion)narration=null"
                    >
                        @include('game.description', [
                            'text' => "preview && !sceneTransition ? narration?.text : ''",
                            'position' => "narration?.position || 'top'",
                            'identity' => "previewId+':'+narrationVersion",
                            'extraClass' => 'item-narration',
                            'persistentExpression' => '!!pendingInventoryUse || !!narration?.persistent',
                            'animate' => 'narration?.animate!==false',
                            'area' => 'narration?.area || null',
                            'hideAfterMs' => 'narration?.hideAfterMs ?? null',
                        ])
                    </div>
                    <template x-if="pickupFlight?.url"><img
                            :src="pickupFlight.url"
                            alt=""
                            class="pickup-flight"
                            :style="{
                                '--pickup-x': pickupFlight.x + '%',
                                '--pickup-y': pickupFlight.y +
                                    '%',
                                '--pickup-target-x': pickupFlight.targetX + 'px',
                                '--pickup-target-y': pickupFlight
                                    .targetY === null ? 'calc(100% - 56px)' : pickupFlight.targetY + 'px'
                            }"
                        ></template>
                    <template x-if="inventoryDrag?.active && inventoryDrag?.url"><img
                            class="inventory-drag-ghost"
                            :src="inventoryDrag.url"
                            alt=""
                            :style="{ left: inventoryDrag.x + 'px', top: inventoryDrag.y + 'px' }"
                        ></template>
                    <div class="game-inventory">
                        <div class="flex flex-col gap-2 pointer-events-auto">
                            <button
                                type="button"
                                class="inventory-toggle justify-center"
                                :class="examineMode ? 'ring-2 ring-amber-400' : ''"
                                aria-label="Examine an area"
                                title="Examine an area"
                                :aria-pressed="examineMode"
                                :disabled="narrationSpeaking || sceneTransition"
                                @click.stop="examineMode=!examineMode;dismiss()"
                            ><svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="10"
                                        cy="10"
                                        r="6"
                                    />
                                    <path d="m15 15 6 6" />
                                </svg></button>
                            <button
                                type="button"
                                class="inventory-toggle"
                                :class="inventoryPulse ? 'inventory-pulse' : ''"
                                aria-label="Inventory"
                                :aria-expanded="inventoryOpen"
                                :disabled="narrationSpeaking || sceneTransition"
                                @click="inventoryOpen=!inventoryOpen"
                            ><svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    aria-hidden="true"
                                >
                                    <path d="M5 7h14l2 14H3L5 7ZM8 7V5a4 4 0 0 1 8 0v2M8 12h8" />
                                </svg><span
                                    class="text-xs"
                                    x-text="inventory.length"
                                ></span></button>
                        </div>
                        <div
                            x-show="inventoryOpen"
                            class="inventory-tray"
                            x-ref="inventoryTray"
                            aria-label="Inventory items"
                        >
                            <p
                                x-show="!inventory.length"
                                class="p-3 text-sm"
                            >No items collected.</p><template
                                x-for="item in inventory"
                                :key="item.inventoryId || item.id"
                            ><button
                                    type="button"
                                    class="inventory-item"
                                    :data-inventory-item="item.id"
                                    :class="pickupFlight?.itemId === item.id || (inventoryDrag?.active && inventoryDrag
                                        ?.itemId === item.id) || pendingInventoryUse?.inventoryId === (item
                                        .inventoryId || item.id) ? 'inventory-item-arriving' : ''"
                                    :disabled="narrationSpeaking || pickupFlight?.itemId === item.id || sceneTransition || !!
                                        pendingInventoryUse"
                                    @pointerdown="startInventoryDrag($event,item)"
                                    @dragstart.prevent
                                    @click="selectInventoryItemForPlay(item)"
                                    :aria-label="'Inspect ' + item.name + '; drag onto a scene area to use'"
                                ><img
                                        x-show="asset(item.asset_id)"
                                        :src="asset(item.asset_id)?.url"
                                        :alt="item.name"
                                        class="h-14 w-16 object-contain"
                                        draggable="false"
                                    ><span
                                        class="block max-w-24 truncate text-xs"
                                        x-text="item.name"
                                    ></span></button></template>
                        </div>
                    </div>
                </div>
            </template>
            <div
                x-show="!asset(displayed?.asset_id)"
                class="p-8"
            >Assign an image to this scene to preview it. Click outside to close.</div>
            <dialog
                x-ref="objectPreviewDialog"
                class="object-preview-dialog"
                :class="narrationSpeaking ? 'scene-narrating' : ''"
                :style="gameDialogStyle"
                aria-label="Object inspection"
                x-effect="if(modal && preview && modalArea?.action!=='inventory'){$nextTick(()=>{if(modal && preview && modalArea?.action!=='inventory' && !$el.open)$el.showModal();});}else if($el.open){$el.close();}"
                @click="if($event.target===$el)modal=null"
                @cancel="modal=null"
                @close.stop="if(modalArea?.action!=='inventory')modal=null"
            >
                <template x-if="modal && !modal.areaId">
                    <div
                        class="object-preview-stage"
                        :class="!modal.url ? 'object-text-only' : ''"
                    >
                        <img
                            x-show="modal.url"
                            :src="modal.url"
                            :alt="modal.label"
                            class="preview-object-image"
                        >
                        <button
                            type="button"
                            class="game-object-close"
                            aria-label="Back to scene"
                            @click="modal=null"
                        >Ã—</button>
                        <div
                            x-data="animatedDescription({ persistent: true })"
                            x-effect="play(modal?.text,'top',modal?.id+':'+modal?.text,false)"
                            x-show="visible"
                            :style="{ visibility: instantHide && !visible ? 'hidden' : 'visible' }"
                            x-transition.opacity.duration.300ms
                            class="game-item-description"
                            @click.stop="skipSpeech()"
                            @keydown.enter.prevent="skipSpeech()"
                            @keydown.space.prevent="skipSpeech()"
                            tabindex="0"
                            role="region"
                            aria-label="Description. Click to reveal text and stop speech; click again to hide."
                            x-text="modal?.text"
                        ></div>
                        <footer
                            x-show="inventoryActions.length"
                            class="game-object-actions game-dialog-footer"
                        ><template
                                x-for="action in inventoryActions"
                                :key="action.id"
                            ><button
                                    type="button"
                                    class="game-action"
                                    @click="performObjectAction(action.id)"
                                    :disabled="narrationSpeaking || !objects.some(v => v.id === action.target_id)"
                                    x-text="action.label"
                                ></button></template></footer>
                    </div>
                </template>
                <template x-if="modal?.areaId">
                    <div class="area-interaction-panel relative flex flex-col gap-4 p-5">
                        <h3
                            class="font-serif text-xl"
                            x-text="modal.label"
                        ></h3>
                        @include('game.description', [
                            'text' => 'modal?.text',
                            'position' => "modal?.position || 'top'",
                            'identity' => "modal?.areaId+':'+(modal?.version||0)",
                            'extraClass' => 'area-narration',
                            'persistent' => true,
                            'animate' => 'modal?.animate!==false',
                        ])
                        <div
                            x-show="modalArea?.action==='choices'"
                            class="flex flex-wrap gap-2"
                        ><template
                                x-for="(choice,index) in modalArea?.choices||[]"
                                :key="index"
                            ><button
                                    type="button"
                                    class="game-action"
                                    @click="chooseAreaAction(index)"
                                    :disabled="narrationSpeaking"
                                    x-text="choice.label"
                                ></button></template>
                            <p
                                x-show="!modalArea?.choices?.length"
                                class="text-sm"
                            >No actions available.</p>
                        </div>
                    </div>
                </template>
            </dialog>
        </div>
    </dialog>
</template>
