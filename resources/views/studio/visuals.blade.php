<section
    class="scene-editor-panel"
    x-show="tab==='visuals'"
    x-cloak
    x-data="sceneEditor(@js(['stateImagesUrl' => route('images.states', $story), 'videoUrl' => route('videos.index', $story), 'playUrl' => route('stories.play', $story), 'flagsUrl' => route('flags.show', $story), 'audioUrl' => route('audio.index', $story), 'url' => route('visuals.show', [$story, $page]), 'extractionUrl' => route('extractions.index', [$story, $page]), 'containerUrl' => route('containers.index', [$story, $page]), 'writingModel' => $story->llm_model, 'generate' => route('images.generate', [$story, $page]), 'upload' => route('assets.store', $story), 'model' => $story->image_model]))"
>
    <p
        x-show="halted"
        class="text-sm"
    >Copy unsaved changes and reload to load the latest version.</p>
    <div
        x-show="!loading"
        class="space-y-3"
    >

        <p
            x-show="!document.visuals.length"
            class="rounded-xl border border-dashed p-5 text-sm"
        >Start with a scene background. Create shared items in the Inventory tab and link them to clickable areas.</p>
        <template x-teleport="#scene-toolbar">
            <div
                x-show="!loading"
                class="flex items-center gap-2 visual-toolbar"
            >
                <div
                    class="field relative min-w-0 flex-1 basis-56"
                    @click.outside="visualMenuOpen=false"
                    @keydown.escape.stop="visualMenuOpen=false;$refs.visualMenuButton.focus()"
                >
                    <button
                        type="button"
                        x-ref="visualMenuButton"
                        class="visual-select-trigger"
                        aria-label="Current page and scene"
                        aria-controls="visual-options"
                        :aria-expanded="visualMenuOpen"
                        @click="toggleVisualMenu()"
                    ><span
                            class="min-w-0 flex-1 truncate text-left"
                            x-text="currentSceneLabel"
                        ></span><svg
                            class="h-4 w-4 shrink-0"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path d="m5 7 5 5 5-5" />
                        </svg></button>
                    <div
                        id="visual-options"
                        x-show="visualMenuOpen"
                        x-cloak
                        class="visual-select-menu"
                        aria-label="Select and reorder visuals"
                    >
                        <div
                            class="visual-select-list"
                            x-ref="visualMenuList"
                        ><template
                                x-for="group in sceneNavigationPages"
                                :key="group.id"
                            >
                                <section :data-page-id="group.id">
                                    <h3
                                        class="px-3 py-2 text-xs font-semibold text-stone-500"
                                        x-text="pageImageLabel(group)"
                                    ></h3>
                                    <ul><template
                                            x-for="(v,index) in group.scenes"
                                            :key="v.id"
                                        >
                                            <li
                                                class="visual-select-row"
                                                :class="{
                                                    'visual-select-current': v.id ===
                                                        selected,
                                                    'visual-select-dragging': draggedVisualId === v
                                                        .id,
                                                    'visual-select-target': visualDropId === v.id
                                                }"
                                                @dragover.prevent="if(group.id===editorPageId && draggedVisualId)visualDropId=v.id"
                                                @dragleave="if(!$el.contains($event.relatedTarget))visualDropId=''"
                                                @drop.prevent="if(group.id===editorPageId)dropVisual(v.id,$event,$el)"
                                            >
                                                <button
                                                    type="button"
                                                    :disabled="group.id !== editorPageId"
                                                    :draggable="group.id === editorPageId"
                                                    class="visual-drag-handle"
                                                    :aria-label="'Drag to reorder ' + v.name"
                                                    :title="group.id === editorPageId ?
                                                        'Drag to reorder; arrow keys move up or down' :
                                                        'Select a scene on this page to reorder it'"
                                                    @dragstart="draggedVisualId=v.id;$event.dataTransfer.effectAllowed='move';$event.dataTransfer.setData('text/plain',v.id)"
                                                    @dragend="draggedVisualId='';visualDropId=''"
                                                    @keydown.up.prevent="moveVisual(v.id,-1)"
                                                    @keydown.down.prevent="moveVisual(v.id,1)"
                                                >⠿</button>
                                                <button
                                                    type="button"
                                                    class="min-w-0 flex-1 truncate py-2 text-left text-sm"
                                                    :aria-pressed="v.id === selected"
                                                    :title="v.name"
                                                    @click="$busy($el, async () => (selectNavigationScene(group.id,v.id)))"
                                                    x-text="(index+1)+'. '+v.name"
                                                ></button>
                                                <button
                                                    type="button"
                                                    x-show="group.id===editorPageId"
                                                    class="visual-order-button"
                                                    :disabled="index === 0"
                                                    :aria-label="'Move ' + v.name + ' up'"
                                                    @click="moveVisual(v.id,-1)"
                                                >↑</button><button
                                                    type="button"
                                                    x-show="group.id===editorPageId"
                                                    class="visual-order-button"
                                                    :disabled="index === group.scenes.length - 1"
                                                    :aria-label="'Move ' + v.name + ' down'"
                                                    @click="moveVisual(v.id,1)"
                                                >↓</button>
                                            </li>
                                        </template>
                                        <li
                                            x-show="!group.scenes.length"
                                            class="p-3 text-sm text-stone-500"
                                        ><button
                                                type="button"
                                                class="underline"
                                                @click="$busy($el, async () => (navigateInteractionPage(group.id)))"
                                            >Open page — no scenes yet</button></li>
                                    </ul>
                                </section>
                            </template></div>
                        <footer class="flex gap-2 border-t border-stone-200 p-2 dark:border-stone-700"><button
                                type="button"
                                class="btn-secondary visual-create-button"
                                @click="visualMenuOpen=false;requestVisualName('scene')"
                                :disabled="document.visuals.length >= 100"
                            >+ New scene</button></footer>
                    </div>
                </div><button
                    type="button"
                    class="btn-secondary shrink-0"
                    @click="requestVisualName('duplicate')"
                    :disabled="!visual"
                >Duplicate visual</button><button
                    type="button"
                    class="btn-secondary shrink-0"
                    @click="requestVisualDelete()"
                    :disabled="!visual"
                    aria-haspopup="dialog"
                >Delete visual</button>
            </div>
        </template>
        <template x-if="visual">
            <div class="space-y-3">
                <div class="border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900">

                    <template x-if="asset(visual?.asset_id)">
                        <div
                            @click="selectSceneBackground()"
                            data-scene-canvas
                            class="relative w-full select-none"
                            :class="drawing ? 'cursor-crosshair touch-none' : ''"
                            @pointerdown="startDraw($event)"
                            @pointermove="moveDraw($event)"
                            @pointerup="endDraw($event)"
                            @pointercancel="cancelDraw()"
                            @lostpointercapture="if(drag)cancelDraw()"
                            @keydown.escape.window="if(drawing)cancelDraw()"
                        >
                            <img
                                :src="asset(visual.asset_id).url"
                                :alt="visual.name"
                                class="block w-full h-auto"
                                draggable="false"
                            ><button
                                type="button"
                                class="image-zoom-button"
                                x-show="!drawing && !preview"
                                @pointerdown.stop
                                :aria-label="'Enlarge ' + visual.name"
                                @click.stop="enlarge(asset(visual.asset_id))"
                                title="Zoom image"
                            ><x-zoom-icon /></button>
                            <template x-if="drawRect">
                                <div
                                    data-draw-preview
                                    class="pointer-events-none absolute z-30 rounded border-2 border-dashed border-amber-400/50 bg-amber-300/25"
                                    :style="style(drawRect)"
                                    aria-hidden="true"
                                ></div>
                            </template>
                            <div
                                class="pointer-events-none absolute inset-0"
                                style="z-index:2"
                                aria-hidden="true"
                            ><template
                                    x-for="h in visual.hotspots"
                                    :key="h.id"
                                ><template x-if="editorAreaImage(h)"><img
                                            :src="editorAreaImage(h).url"
                                            :style="editorAreaImageStyle(h)"
                                            :class="h.overlay_bounds ? 'object-fill' : 'object-contain'"
                                            alt=""
                                            class="pointer-events-none absolute"
                                            draggable="false"
                                        ></template></template></div>
                            <div
                                class="scene-area-layers"
                                :class="drawing ? 'drawing-areas' : ''"
                            >
                                <template
                                    x-for="h in visual.hotspots"
                                    :key="h.id"
                                >
                                    <div
                                        x-show="editorAreaVisible(h)"
                                        @mouseenter="editorHoverAreaId=h.id"
                                        @mouseleave="if(editorHoverAreaId===h.id)editorHoverAreaId=null"
                                        @focus="editorHoverAreaId=h.id"
                                        @blur="if(editorHoverAreaId===h.id)editorHoverAreaId=null"
                                        :style="style(h) + ';z-index:' + areaLayer(h)"
                                        class="absolute rounded border-2 focus:outline-none"
                                        :class="[preview ? 'border-transparent hover:border-amber-400/50' : hotspotId === h.id ?
                                            'border-amber-500/50 bg-amber-300/10' :
                                            'border-amber-400/50 bg-transparent', drawing ? 'pointer-events-none' : '',
                                            !preview ? 'cursor-pointer touch-none' : 'cursor-pointer'
                                        ]"
                                        role="button"
                                        tabindex="0"
                                        :aria-label="h.label"
                                        :aria-pressed="!preview && hotspotId === h.id"
                                        @pointerdown.stop="if(!drawing)hotspotId=h.id"
                                        @pointermove.stop="moveArea($event)"
                                        @pointerup.stop="endArea()"
                                        @pointercancel.stop="endArea(true)"
                                        @keydown="areaKey($event,h)"
                                        @keydown.enter.self.prevent="activate(h)"
                                        @click.stop="activate(h)"
                                    >

                                        <span
                                            x-show="!preview && hotspotId===h.id && renamingId!==h.id && areaDrag?.id!==h.id"
                                            class="absolute left-0 top-0 max-w-full truncate bg-stone-900/90 px-1 text-xs text-white"
                                            @pointerdown.stop
                                            @dblclick.stop="beginRename(h)"
                                            x-text="h.label"
                                        ></span>
                                        <template x-if="renamingId===h.id && !preview"><input
                                                class="absolute left-0 top-0 z-30 w-40 rounded border-amber-500 bg-white p-1 text-xs text-stone-900"
                                                aria-label="Area name"
                                                maxlength="160"
                                                x-model="labelDraft"
                                                x-init="$nextTick(() => {
                                                    $el.focus();
                                                    $el.select();
                                                })"
                                                @pointerdown.stop
                                                @click.stop
                                                @keydown.stop
                                                @keydown.enter.prevent="finishRename(h)"
                                                @keydown.escape.prevent="renamingId=''"
                                                @blur="finishRename(h)"
                                            ></template>
                                        <template x-if="!preview && hotspotId===h.id">
                                            <div>
                                                <button
                                                    type="button"
                                                    x-data="{
                                                        canvasWidth: 0,
                                                        canvasHeight: 0,
                                                        buttonWidth: 28,
                                                        buttonHeight: 28,
                                                        observer: null,
                                                        init() {
                                                            const canvas = this.$el.closest('[data-scene-canvas]');
                                                            this.observer = new ResizeObserver(() => {
                                                                this.canvasWidth = canvas.clientWidth;
                                                                this.canvasHeight = canvas.clientHeight;
                                                                this.buttonWidth = this.$el.offsetWidth;
                                                                this.buttonHeight = this.$el.offsetHeight
                                                            });
                                                            this.observer.observe(canvas);
                                                            this.observer.observe(this.$el)
                                                        },
                                                        destroy() { this.observer?.disconnect() }
                                                    }"
                                                    :style="areaDragButtonStyle(h, canvasWidth, canvasHeight, buttonWidth,
                                                        buttonHeight)"
                                                    class="absolute z-20 grid h-7 w-7 cursor-grab touch-none place-items-center rounded bg-stone-900 text-white active:cursor-grabbing"
                                                    :aria-label="'Drag ' + h.label"
                                                    title="Drag area"
                                                    @pointerdown.stop="startArea($event,h)"
                                                    @pointermove.stop="moveArea($event)"
                                                    @pointerup.stop="endArea()"
                                                    @pointercancel.stop="endArea(true)"
                                                    @lostpointercapture="if(areaDrag?.id===h.id)endArea(true)"
                                                    @click.stop
                                                ><svg
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        aria-hidden="true"
                                                    >
                                                        <path
                                                            d="M12 3v18M3 12h18M8 7l4-4 4 4M8 17l4 4 4-4M7 8l-4 4 4 4M17 8l4 4-4 4"
                                                        />
                                                    </svg></button>
                                                <template
                                                    x-for="corner in ['nw','ne','sw','se']"
                                                    :key="corner"
                                                ><button
                                                        type="button"
                                                        class="absolute z-20 h-3 w-3 rounded-sm border border-white bg-amber-600 touch-none"
                                                        :style="(corner.includes('n') ? 'top:-6px;' : 'bottom:-6px;') + (corner
                                                            .includes('w') ? 'left:-6px;' : 'right:-6px;') + 'cursor:' +
                                                        corner + '-resize'"
                                                        :aria-label="'Resize ' + h.label + ' ' + corner"
                                                        @pointerdown.stop="startArea($event,h,corner)"
                                                        @pointermove.stop="moveArea($event)"
                                                        @pointerup.stop="endArea()"
                                                        @pointercancel.stop="endArea(true)"
                                                        @click.stop
                                                    ></button></template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p
                        x-show="!asset(visual?.asset_id)"
                        class="py-8 text-center text-sm text-stone-500"
                    >Choose an image below to start placing clickable areas.</p>

                </div>
                <section
                    x-show="visual.kind==='object'"
                    class="space-y-3 rounded-xl border border-stone-200 p-4 dark:border-stone-700"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-serif text-xl">Inventory actions</h3><button
                            type="button"
                            class="btn-secondary"
                            @click="addObjectAction()"
                            :disabled="(visual.actions || []).length >= 50"
                        >+ Action</button>
                    </div>
                    <p class="text-xs text-stone-500">Each action replaces this inventory item with another object.
                        Create its image as an object visual, then link it here. Link back to earlier objects for
                        reversible actions.</p>
                    <template
                        x-for="action in visual.actions||[]"
                        :key="action.id"
                    >
                        <div class="flex flex-wrap items-end gap-3"><label class="field min-w-0 flex-1">Action
                                name<input
                                    x-model="action.label"
                                    @input="changed()"
                                    maxlength="160"
                                    placeholder="Open, close, crumple…"
                                ></label><label class="field min-w-0 flex-1">Replace with<select
                                    x-model="action.target_id"
                                    @change="changed()"
                                >
                                    <option :value="null">Choose object</option><template
                                        x-for="(object,index) in objects"
                                        :key="object.id"
                                    >
                                        <option
                                            :value="object.id"
                                            x-text="(index+1)+'. '+object.name"
                                        ></option>
                                    </template>
                                </select></label><button
                                type="button"
                                class="btn-secondary"
                                @click="removeObjectAction(action.id)"
                                :aria-label="'Remove action ' + action.label"
                            >Remove</button></div>
                    </template>
                    <p
                        x-show="!(visual.actions||[]).length"
                        class="text-xs text-stone-500"
                    >No actions yet.</p>
                </section>
                <div
                    x-show="!preview && visual.kind==='scene'"
                    class="space-y-3"
                >
                    <div
                        x-show="!hotspot"
                        class="space-y-3"
                    >
                        <div class="flex flex-wrap items-end gap-3"><label class="field">Dialog color<input
                                    type="color"
                                    :value="dialogPalette(visual).background"
                                    @input="visual.dialog_background=$event.target.value;changed()"
                                ></label><label class="field">Border color<input
                                    type="color"
                                    :value="dialogPalette(visual).border"
                                    @input="visual.dialog_border=$event.target.value;changed()"
                                ></label><label class="field">Footer accent<input
                                    type="color"
                                    :value="dialogPalette(visual).footer"
                                    @input="visual.dialog_footer=$event.target.value;changed()"
                                ></label><button
                                type="button"
                                class="btn-secondary"
                                @click="$busy($el, async () => (autoDialogColors()))"
                                :disabled="paletteBusy || !asset(visual.asset_id)"
                                x-text="paletteBusy?'Calculating…':'Auto'"
                            ></button>
                            <div class="flex flex-wrap gap-2"><button
                                    type="button"
                                    class="btn-secondary"
                                    @click="$busy($el, async () => (openContainers()))"
                                    :disabled="!visual.asset_id || !containerAreas.length"
                                >Create container</button><button
                                    type="button"
                                    class="btn-secondary"
                                    @click="$busy($el, async () => (openExtraction()))"
                                    :disabled="!visual.asset_id"
                                >Extract collectible items</button><button
                                    type="button"
                                    class="btn-secondary"
                                    @click="$busy($el, async () => (openExtraction('switches')))"
                                    :disabled="!visual.asset_id"
                                >Create switches</button><button
                                    type="button"
                                    x-show="!preview && visual.kind==='scene'"
                                    @click="toggleDrawing()"
                                    class="btn-secondary"
                                    x-text="drawing?'Cancel drawing':'Draw area'"
                                ></button><button
                                    type="button"
                                    class="btn-secondary"
                                    @click="$busy($el, async () => (captureSceneImage()))"
                                    :disabled="sceneCaptureBusy || saving || !visual.asset_id"
                                    x-text="sceneCaptureBusy?'Preparing image…':'Save scene as image'"
                                ></button></div>
                        </div>
                        <div class="space-y-3">
                            <div class="grid min-w-0 grid-cols-1 gap-3 lg:grid-cols-4"><label
                                    class="field min-w-0">Visual name<input
                                        x-model="visual.name"
                                        @input="changed()"
                                        maxlength="160"
                                    ></label>
                                <div class="field min-w-0"><span>Selected image</span><button
                                        type="button"
                                        class="btn-secondary min-w-0 justify-start gap-3"
                                        @click="openVisualImagePicker()"
                                        aria-haspopup="dialog"
                                    ><img
                                            x-show="asset(visual.asset_id)"
                                            :src="$thumbnail(asset(visual.asset_id)?.url)"
                                            alt=""
                                            class="h-9 w-12 shrink-0 rounded object-contain"
                                        ><span
                                            class="truncate"
                                            x-text="asset(visual.asset_id)?.name || 'Choose image'"
                                        ></span></button></div>
                                <div class="field min-w-0"><span>Scene intro video</span>
                                    <button
                                        type="button"
                                        class="btn-secondary min-w-0 justify-between"
                                        aria-haspopup="dialog"
                                        @click="openIntroVideoPicker()"
                                    >
                                        <span
                                            class="truncate"
                                            x-text="introVideos.find(v=>v.id===visual.intro_video_id)?.name || (visual.intro_video_id ? 'Selected video' : 'No intro video')"
                                        ></span><span aria-hidden="true">▾</span>
                                    </button>
                                    <label class="inline-flex w-fit items-center gap-2 text-xs font-normal"><input
                                            type="checkbox"
                                            :checked="visual.intro_video_repeat === true"
                                            @change="visual.intro_video_repeat=$event.target.checked;changed()"
                                        >Play video each time the scene loads</label>
                                    <label class="inline-flex w-fit items-center gap-2 text-xs font-normal"><input
                                            type="checkbox"
                                            :checked="visual.intro_video_pause_music === true"
                                            @change="visual.intro_video_pause_music=$event.target.checked;changed()"
                                        >Pause game music while video is playing</label>
                                </div><label class="field min-w-0">Scene entry sound<div
                                        class="audio-select-row"
                                        x-data="audioPreview('scenes')"
                                        x-effect="setSound(audioSounds.find(s=>s.id===Number(visual.sound_id)))"
                                        @vsg-audio-preview-stop.window="stop()"
                                        @vsg-audio-categories.window="categories=$event.detail"
                                    ><select
                                            class="audio-category-select"
                                            aria-label="Audio category"
                                            x-model="category"
                                        >
                                            <option value="">All categories</option><template
                                                x-for="(name,key) in categories"
                                                :key="key"
                                            >
                                                <option
                                                    :value="key"
                                                    x-text="name"
                                                ></option>
                                            </template>
                                        </select>
                                        <div class="min-w-0 flex-1"><select
                                                :value="visual.sound_id || ''"
                                                @change="visual.sound_id=$event.target.value?Number($event.target.value):null;changed()"
                                            >
                                                <option value="">No sound (silent)</option><template
                                                    x-for="s in audioSounds.filter(s=>!category||(s.category||'effects')===category||Number(s.id)===Number(sound?.id))"
                                                    :key="s.id"
                                                >
                                                    <option
                                                        :value="s.id"
                                                        x-text="s.name"
                                                    ></option>
                                                </template>
                                            </select></div><button
                                            type="button"
                                            class="btn-secondary audio-preview-button"
                                            :disabled="!sound?.url"
                                            :aria-label="playing ? 'Stop audio preview' : 'Play selected audio'"
                                            :title="playing ? 'Stop' : 'Listen'"
                                            :aria-pressed="playing"
                                            @click="$busy($el, async () => (toggle()))"
                                        ><span
                                                x-text="playing?'\u25A0':'\u25B6'"
                                                aria-hidden="true"
                                            ></span></button>
                                    </div></label>
                            </div>
                            <div class="grid min-w-0 grid-cols-1 items-end gap-3 lg:grid-cols-3"><label
                                    class="field min-w-0"
                                >Scene music<select
                                        :value="visual.music_mode || 'keep'"
                                        @change="visual.music_mode=$event.target.value;changed()"
                                    >
                                        <option value="keep">Keep playing (if any)</option>
                                        <option value="replace">Change music track</option>
                                        <option value="stop">Stop music</option>
                                    </select></label>
                                <div
                                    x-show="visual.music_mode==='replace'"
                                    class="flex min-w-0 flex-wrap items-end gap-3 lg:col-span-2"
                                ><label class="field min-w-0 flex-1 basis-64">Music track<div
                                            class="audio-select-row"
                                            x-data="audioPreview('music')"
                                            x-effect="setSound(audioSounds.find(s=>s.id===Number(visual.music_id)))"
                                            @vsg-audio-preview-stop.window="stop()"
                                            @vsg-audio-categories.window="categories=$event.detail"
                                        ><select
                                                class="audio-category-select"
                                                aria-label="Audio category"
                                                x-model="category"
                                            >
                                                <option value="">All categories</option><template
                                                    x-for="(name,key) in categories"
                                                    :key="key"
                                                >
                                                    <option
                                                        :value="key"
                                                        x-text="name"
                                                    ></option>
                                                </template>
                                            </select>
                                            <div class="min-w-0 flex-1"><select
                                                    :value="visual.music_id || ''"
                                                    @change="visual.music_id=$event.target.value?Number($event.target.value):null;changed()"
                                                >
                                                    <option value="">Choose music</option><template
                                                        x-for="s in audioSounds.filter(s=>!category||(s.category||'effects')===category||Number(s.id)===Number(sound?.id))"
                                                        :key="s.id"
                                                    >
                                                        <option
                                                            :value="s.id"
                                                            x-text="s.name"
                                                        ></option>
                                                    </template>
                                                </select></div><button
                                                type="button"
                                                class="btn-secondary audio-preview-button"
                                                :disabled="!sound?.url"
                                                :aria-label="playing ? 'Stop audio preview' : 'Play selected audio'"
                                                :title="playing ? 'Stop' : 'Listen'"
                                                :aria-pressed="playing"
                                                @click="$busy($el, async () => (toggle()))"
                                            ><span
                                                    x-text="playing?'\u25A0':'\u25B6'"
                                                    aria-hidden="true"
                                                ></span></button>
                                        </div></label><label class="area-checkbox"><input
                                            type="checkbox"
                                            :checked="!!visual.music_loop"
                                            @change="visual.music_loop=$event.target.checked;changed()"
                                        ><span>Loop music</span></label></div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-x-5 gap-y-2"><label
                                        class="text-xs font-semibold"
                                        :for="'scene-description-' + visual.id"
                                    >Description</label>
                                    <div
                                        class="flex flex-wrap items-center gap-4"
                                        role="radiogroup"
                                        aria-label="Description position"
                                    ><label class="area-checkbox"><input
                                                type="radio"
                                                :name="'scene-description-position-' + visual.id"
                                                value="top"
                                                :checked="(visual.description_position || 'top') === 'top'"
                                                @change="visual.description_position='top';changed()"
                                            ><span>Show top</span></label><label class="area-checkbox"><input
                                                type="radio"
                                                :name="'scene-description-position-' + visual.id"
                                                value="bottom"
                                                :checked="visual.description_position === 'bottom'"
                                                @change="visual.description_position='bottom';changed()"
                                            ><span>Show bottom</span></label></div>
                                </div>
                                <div class="field">
                                    <textarea
                                        :id="'scene-description-' + visual.id"
                                        x-model="visual.description"
                                        @input="changed()"
                                        maxlength="3000"
                                        placeholder="Describe this scene or object for the player."
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold">Scene skip rules</h3><button
                                type="button"
                                class="btn-secondary"
                                @click="addSkipRule()"
                            >Add rule</button>
                        </div>
                        <p class="text-xs text-stone-500">Skip this scene when all selected inventory and flag
                            conditions match. At least one condition is required. Rules run in order on entry; automatic
                            jumps stop after three. Click an area to edit its interaction, or the background to edit
                            these rules.</p>
                        <template
                            x-for="rule in visual.skip_rules||[]"
                            :key="rule.id"
                        >
                            <div class="rounded-lg border border-stone-200 p-3 dark:border-stone-700 space-y-3">
                                <label class="field">Inventory contains<button
                                        type="button"
                                        class="btn-secondary text-left"
                                        @click="chooseSkipItems(rule)"
                                        x-text="skipItemsLabel(rule)"
                                    ></button></label>
                                @include('studio.flag-rules', [
                                    'target' => 'rule',
                                    'field' => 'flag_conditions',
                                    'heading' => 'Required flag states',
                                ])
                                <label class="field">Jump to page &amp; scene<select
                                        :value="destinationValue(rule)"
                                        @change="setSkipDestination(rule,$event.target.value)"
                                    >
                                        <option
                                            value=""
                                            :selected="!destinationValue(rule)"
                                        >Choose a page and scene</option><template
                                            x-for="destination in pageSceneDestinations"
                                            :key="destination.key"
                                        >
                                            <option
                                                :value="destination.key"
                                                :selected="destination.key === destinationValue(rule)"
                                                x-text="destination.label"
                                            ></option>
                                        </template>
                                    </select></label>
                                <button
                                    type="button"
                                    class="text-xs underline"
                                    @click="removeSkipRule(rule.id)"
                                >Remove rule</button>
                            </div>
                        </template>
                    </div>
                    <template x-if="hotspot">
                        <div class="rounded-xl border border-stone-200 dark:border-stone-700 p-4 space-y-4">
                            <div class="flex flex-wrap items-center gap-3"><label
                                    class="field min-w-0 w-56 max-w-full"
                                ><span class="sr-only">Select click area</span><select
                                        aria-label="Select click area"
                                        x-model="hotspotId"
                                    ><template
                                            x-for="area in visual.hotspots"
                                            :key="area.id"
                                        >
                                            <option
                                                :value="area.id"
                                                x-text="(area.label||'Unnamed area')+(editorAreaVisible(area)?'':' (hidden)')"
                                            ></option>
                                        </template></select></label><label class="field min-w-0 flex-1"><span
                                        class="sr-only"
                                    >Area name</span><input
                                        type="text"
                                        x-model="hotspot.label"
                                        @input="changed()"
                                        maxlength="160"
                                        aria-label="Area name"
                                        placeholder="Area name"
                                    ></label>
                                <div class="flex shrink-0 flex-wrap gap-2"><button
                                        type="button"
                                        class="btn-secondary"
                                        :disabled="areaLayer(hotspot) === visual.hotspots.length"
                                        @click="moveAreaLayer(hotspot.id,1)"
                                        title="Move area up one layer"
                                    >↑ Up</button><button
                                        type="button"
                                        class="btn-secondary"
                                        :disabled="areaLayer(hotspot) === 1"
                                        @click="moveAreaLayer(hotspot.id,-1)"
                                        title="Move area down one layer"
                                    >↓ Down</button><button
                                        type="button"
                                        class="btn-secondary text-red-700 dark:text-red-400"
                                        @click="requestAreaDelete(hotspot.id)"
                                        title="Delete clickable area"
                                    >Delete</button><button
                                        type="button"
                                        x-show="['pickup_v2','state_v2'].includes(hotspot.action)"
                                        class="btn-secondary"
                                        :disabled="resettingArea || saving"
                                        @click="$busy($el, async () => (resetAreaPosition(hotspot)))"
                                        x-text="resettingArea?'Resetting…':'Reset to default position'"
                                    ></button></div>
                            </div>
                            <div class="grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-2">
                                <section class="min-w-0 space-y-4">
                                    <h3 class="font-serif text-lg">Image and description</h3>
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                        <label class="flex w-fit items-center gap-2 text-sm"><input
                                                type="checkbox"
                                                x-model="hotspot.overlay"
                                                @change="changed()"
                                            >Show image in area</label>
                                        <label
                                            x-show="hotspot.overlay"
                                            class="area-checkbox"
                                        ><input
                                                type="checkbox"
                                                :checked="hotspot.overlay_always !== false"
                                                @change="hotspot.overlay_always=$event.target.checked;changed()"
                                            ><span
                                                x-text="hotspot.action==='state_v2'?'Show changed state':'Show always'"
                                            ></span></label>
                                    </div>
                                    <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div
                                            x-show="hotspot.overlay"
                                            class="field min-w-0"
                                        ><span
                                                x-text="hotspot.action==='state_v2'?'Changed-state image':'Area image'"></span><button
                                                type="button"
                                                class="btn-secondary justify-start gap-2"
                                                @click="openAreaImagePicker()"
                                                aria-haspopup="dialog"
                                            ><img
                                                    x-show="areaImage(hotspot)"
                                                    :src="$thumbnail(areaImage(hotspot)?.url)"
                                                    alt=""
                                                    class="h-10 w-14 object-contain"
                                                ><span
                                                    x-text="areaImage(hotspot)?.name || 'Choose area image'"></span></button>
                                        </div>
                                        <label
                                            x-show="hotspot.overlay"
                                            class="field min-w-0"
                                        >Image alignment<select
                                                :value="hotspot.overlay_alignment || 'middle'"
                                                @change="hotspot.overlay_alignment=$event.target.value;changed()"
                                            >
                                                <option value="top">Top</option>
                                                <option value="middle">Middle</option>
                                                <option value="bottom">Bottom</option>
                                            </select></label>
                                        <label
                                            x-show="hotspot.overlay"
                                            class="field min-w-0"
                                        >Size (%)<input
                                                type="number"
                                                min="1"
                                                max="500"
                                                step="1"
                                                :value="hotspot.image_size ?? 100"
                                                @change="setAreaImageSize(hotspot,$event.target.value)"
                                            ></label>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="area-description-header">
                                            <label
                                                :for="'hover-description-' + hotspot.id"
                                                class="text-xs font-semibold"
                                            >Description</label>
                                            <div class="area-description-options">

                                                <label class="field">Position<select
                                                        :value="hotspot.description_position || 'top'"
                                                        @change="hotspot.description_position=$event.target.value;changed()"
                                                    >
                                                        <option value="top">Top</option>
                                                        <option value="bottom">Bottom</option>
                                                        <option value="area_top">Top of selection area</option>
                                                        <option value="area_bottom">Bottom of selection area</option>
                                                    </select></label>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <textarea
                                                :id="'hover-description-' + hotspot.id"
                                                x-model="hotspot.description"
                                                @input="changed()"
                                                maxlength="3000"
                                                rows="3"
                                                placeholder="What does the player see or discover?"
                                            ></textarea>
                                        </div>
                                        <label
                                            class="field"
                                            x-show="hotspot.action==='state_v2' || dualAction(hotspot) || (hotspot.action==='pickup_v2' && hotspot.initial_empty)"
                                        >Changed / occupied state description (optional)
                                            <textarea
                                                x-model="hotspot.state_description"
                                                @input="changed()"
                                                maxlength="3000"
                                                rows="3"
                                                placeholder="What does the player see when this switch is in its changed state?"
                                            ></textarea>
                                        </label>
                                    </div>
                                    <div
                                        class="grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                                        <label class="field min-w-0">Click sound<div
                                                class="audio-select-row"
                                                x-data="audioPreview('click_areas')"
                                                x-effect="setSound(audioSounds.find(s=>s.id===Number(soundPlayer.resolve(hotspot.sound_id,audioSettings.defaults?.[hotspot.action]?.click))))"
                                                @vsg-audio-preview-stop.window="stop()"
                                                @vsg-audio-categories.window="categories=$event.detail"
                                            ><select
                                                    class="audio-category-select"
                                                    aria-label="Audio category"
                                                    x-model="category"
                                                >
                                                    <option value="">All categories</option><template
                                                        x-for="(name,key) in categories"
                                                        :key="key"
                                                    >
                                                        <option
                                                            :value="key"
                                                            x-text="name"
                                                        ></option>
                                                    </template>
                                                </select>
                                                <div class="min-w-0 flex-1"><select
                                                        :value="hotspot.sound_id ?? ''"
                                                        @change="hotspot.sound_id=$event.target.value||null;changed()"
                                                    >
                                                        <option value="">Default from story bible</option>
                                                        <option value="silent">No sound (silent)</option><template
                                                            x-for="s in audioSounds.filter(s=>!category||(s.category||'effects')===category||Number(s.id)===Number(sound?.id))"
                                                            :key="s.id"
                                                        >
                                                            <option
                                                                :value="s.id"
                                                                x-text="s.name"
                                                            ></option>
                                                        </template>
                                                    </select></div><button
                                                    type="button"
                                                    class="btn-secondary audio-preview-button"
                                                    :disabled="!sound?.url"
                                                    :aria-label="playing ? 'Stop audio preview' : 'Play selected audio'"
                                                    :title="playing ? 'Stop' : 'Listen'"
                                                    :aria-pressed="playing"
                                                    @click="$busy($el, async () => (toggle()))"
                                                ><span
                                                        x-text="playing?'\u25A0':'\u25B6'"
                                                        aria-hidden="true"
                                                    ></span></button>
                                            </div></label>
                                    </div>
                                </section>
                                <section
                                    class="min-w-0 space-y-4 border-t pt-4 lg:border-t-0 lg:border-l lg:pl-6 lg:pt-0"
                                >
                                    <h3 class="font-serif text-lg">Action</h3>
                                    @include('studio.interaction-flow')
                                    <div class="space-y-2">
                                        @include('studio.flag-rules', [
                                            'target' => 'hotspot',
                                            'field' => 'visible_when',
                                            'heading' => 'Show this area when (all match)',
                                        ])
                                        <p
                                            x-show="hotspot.visible_when?.length"
                                            class="text-xs"
                                        >Controls the area and its image in the game and editor. Preview switch
                                            conditions with Show changed state.</p>
                                        @include('studio.flag-rules', [
                                            'target' => 'hotspot',
                                            'field' => 'disabled_when',
                                            'heading' => 'Disable this area when (all match)',
                                        ])
                                        <p
                                            x-show="hotspot.disabled_when?.length"
                                            class="text-xs"
                                        >Disables descriptions and actions. Clicks pass through to areas
                                            underneath.</p>
                                    </div>
                                    <label class="field"><select
                                            aria-label="Click action"
                                            :value="editorAreaAction(hotspot)"
                                            @change="changeAreaAction($event.target.value)"
                                        >
                                            <option value="examine">Examine</option>
                                            <option value="pickup_v2">Pick up/place object</option>
                                            <option value="state_v2">Change object state</option>
                                            <option value="inventory">Use inventory on area</option>
                                        </select></label>
                                    @include('studio.automatic-state')
                                    <section
                                        x-show="hotspot.action==='state_v2'"
                                        class="space-y-2 rounded-lg border p-3 dark:border-stone-700"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="text-sm font-semibold">Automatic state flag</h4><button
                                                type="button"
                                                class="text-sm underline"
                                                x-show="hotspot.state_flag"
                                                @click="editStateFlag(hotspot)"
                                            >Rename</button>
                                        </div>
                                        <template x-if="hotspot.state_flag">
                                            <div class="space-y-2">
                                                <div class="grid min-w-0 grid-cols-2 items-center gap-3">
                                                    <p
                                                        class="min-w-0 break-words text-sm"
                                                        x-text="flagLabel(hotspot.state_flag)"
                                                    ></p><label class="field min-w-0"><span class="sr-only">Automatic
                                                            state flag key</span><input
                                                            :value="hotspot.state_flag"
                                                            readonly
                                                        ></label>
                                                </div>
                                                <p class="text-xs">On for the changed image; Off for the original.</p>
                                            </div>
                                        </template>
                                        <template x-if="editingStateFlag && editingStateFlag===hotspot.state_flag">
                                            <div class="space-y-3"><label class="field">Name<input
                                                        x-model="stateFlagName"
                                                        maxlength="160"
                                                    ></label><label class="field">Key<input
                                                        x-model="stateFlagKey"
                                                        maxlength="64"
                                                        pattern="[a-z][a-z0-9_]*"
                                                    ></label>
                                                <p class="text-xs">Renaming updates all saved conditions, inventory
                                                    rules, and plan links that use this flag.</p>
                                                <p
                                                    x-show="stateFlagError"
                                                    x-text="stateFlagError"
                                                    class="text-sm text-red-600"
                                                    role="alert"
                                                ></p>
                                                <div class="flex gap-2"><button
                                                        type="button"
                                                        class="btn-primary"
                                                        :disabled="renamingStateFlag"
                                                        @click="$busy($el, async () => (renameStateFlag()))"
                                                        x-text="renamingStateFlag?'Saving…':'Save name and key'"
                                                    ></button><button
                                                        type="button"
                                                        class="btn-secondary"
                                                        :disabled="renamingStateFlag"
                                                        @click="editingStateFlag=null"
                                                    >Cancel</button></div>
                                            </div>
                                        </template>
                                        <p
                                            x-show="!hotspot.state_flag"
                                            class="text-xs"
                                        >Activate a switch plan to create its automatic state flag.</p>
                                    </section>
                                    <button
                                        type="button"
                                        class="btn-secondary"
                                        x-show="hotspot.action==='state_v2' && hotspot.state_asset_id"
                                        @click="$busy($el, async () => (openContainers(true)))"
                                    >Extract collectibles from changed state</button>
                                    <div class="field min-w-0">
                                        <span>Action video (optional)</span>
                                        <button type="button" class="btn-secondary justify-start" @click="openIntroVideoPicker(hotspot)"
                                            x-text="introVideos.find(v=>v.id===hotspot.action_video_id)?.name || (hotspot.action_video_id ? 'Selected video' : 'No action video')"></button>
                                        <p class="text-xs">Plays before the action. When it finishes or is skipped, the action continues: stay here, change state, collect an item, or move to the configured scene.</p>
                                        <label class="flex items-center gap-2" x-show="hotspot.action_video_id">
                                            <input type="checkbox" :checked="hotspot.action_video_pause_music === true" @change="hotspot.action_video_pause_music=$event.target.checked;changed()">Pause game music during this video
                                        </label>
                                    </div>
                                    <div
                                        class="field min-w-0"
                                        x-show="['inspect','pickup','pickup_v2','transition'].includes(hotspot.action)"
                                    ><span>Object image</span><button
                                            type="button"
                                            class="btn-secondary justify-start gap-3"
                                            aria-haspopup="dialog"
                                            @click="openInteractionPicker('object')"
                                        ><img
                                                x-show="visualAsset(hotspot.object_id)"
                                                :src="$thumbnail(visualAsset(hotspot.object_id)?.url)"
                                                alt=""
                                                class="h-10 w-14 object-contain"
                                            ><span
                                                x-text="visualAsset(hotspot.object_id)?.name || 'Choose object image'"
                                            ></span></button></div>
                                    <p
                                        x-show="hotspot.action==='pickup'"
                                        class="text-xs"
                                    >This existing pickup uses a full-scene change. <button
                                            type="button"
                                            class="underline"
                                            @click="changeAreaAction('pickup_v2')"
                                        >Convert to local layer</button>, then prepare its collectible layers.</p><label
                                        x-show="hotspot.action==='examine'"
                                        class="area-checkbox"
                                    ><input
                                            type="checkbox"
                                            x-model="hotspot.move_after_examine"
                                            @change="if(!hotspot.move_after_examine){hotspot.restart_game=false;hotspot.target_page_id=null;hotspot.target_scene_id=null;}changed()"
                                        ><span>Action moves to scene</span></label>
                                    <div
                                        x-show="hotspot.action==='pickup_v2'"
                                        class="flex flex-wrap items-center gap-x-6 gap-y-3"
                                    ><label class="area-checkbox"><input
                                                type="checkbox"
                                                :checked="hotspot.initial_empty === true"
                                                @change="hotspot.initial_empty=$event.target.checked;changed()"
                                            ><span>Location starts empty</span></label><label
                                            class="area-checkbox"><input
                                                type="checkbox"
                                                :checked="hotspot.placement_enabled !== false"
                                                @change="hotspot.placement_enabled=$event.target.checked;configurePlacement()"
                                            ><span>Allow placing inventory items into this empty location</span></label>
                                    </div><label
                                        class="field"
                                        x-show="hotspot.action==='state_v2' && !hotspot.is_container"
                                    >Changed state appearance
                                        <textarea
                                            x-model="hotspot.state_prompt"
                                            @input="changed()"
                                            maxlength="3000"
                                            placeholder="Show the button pressed into its housing; keep all surrounding details unchanged."
                                        ></textarea><span class="text-xs font-normal">Create switches
                                            generates the alternate patch. The action switches between the original and
                                            changed appearances without changing the scene or inventory.</span>
                                    </label><label
                                        class="field"
                                        x-show="hotspot.action==='page'||(hotspot.action==='examine'&&hotspot.move_after_examine)"
                                    >Destination page &amp; scene<select
                                            :value="destinationValue(hotspot)"
                                            @change="selectPageScene($event.target.value)"
                                        >
                                            <option
                                                value=""
                                                :selected="!destinationValue(hotspot)"
                                            >Choose a page and scene</option><template
                                                x-for="destination in pageSceneDestinations"
                                                :key="destination.key"
                                            >
                                                <option
                                                    :value="destination.key"
                                                    :selected="destination.key === destinationValue(hotspot)"
                                                    x-text="destination.label"
                                                ></option>
                                            </template>
                                        </select></label>
                                    <div
                                        x-show="hasAreaAction(hotspot)"
                                        class="space-y-4"
                                    >
                                        <div class="grid min-w-0 grid-cols-1 items-end gap-3"><label
                                                class="field min-w-0"
                                            >Click sound<div
                                                    class="audio-select-row"
                                                    x-data="audioPreview('click_areas')"
                                                    x-effect="setSound(audioSounds.find(s=>s.id===Number(soundPlayer.resolve(hotspot.action_sound_id,hotspot.action==='state_v2'?null:soundPlayer.resolve(objects.find(o=>o.id===hotspot.object_id)?.action_sound_id,audioSettings.defaults?.[hotspot.action]?.action)))))"
                                                    @vsg-audio-preview-stop.window="stop()"
                                                    @vsg-audio-categories.window="categories=$event.detail"
                                                ><select
                                                        class="audio-category-select"
                                                        aria-label="Audio category"
                                                        x-model="category"
                                                    >
                                                        <option value="">All categories</option><template
                                                            x-for="(name,key) in categories"
                                                            :key="key"
                                                        >
                                                            <option
                                                                :value="key"
                                                                x-text="name"
                                                            ></option>
                                                        </template>
                                                    </select>
                                                    <div class="min-w-0 flex-1"><select
                                                            :value="hotspot.action_sound_id ?? ''"
                                                            @change="hotspot.action_sound_id=$event.target.value||null;changed()"
                                                        >
                                                            <option
                                                                value=""
                                                                x-text="hotspot.action==='state_v2'?'No sound (default)':'Default from inventory item / story bible'"
                                                            ></option>
                                                            <option value="silent">No sound (silent)</option><template
                                                                x-for="s in audioSounds.filter(s=>!category||(s.category||'effects')===category||Number(s.id)===Number(sound?.id))"
                                                                :key="s.id"
                                                            >
                                                                <option
                                                                    :value="s.id"
                                                                    x-text="s.name"
                                                                ></option>
                                                            </template>
                                                        </select></div><button
                                                        type="button"
                                                        class="btn-secondary audio-preview-button"
                                                        :disabled="!sound?.url"
                                                        :aria-label="playing ? 'Stop audio preview' : 'Play selected audio'"
                                                        :title="playing ? 'Stop' : 'Listen'"
                                                        :aria-pressed="playing"
                                                        @click="$busy($el, async () => (toggle()))"
                                                    ><span
                                                            x-text="playing?'\u25A0':'\u25B6'"
                                                            aria-hidden="true"
                                                        ></span></button>
                                                </div></label></div>
                                        <label class="area-checkbox"><input
                                                type="checkbox"
                                                :checked="availabilityEnabled(hotspot)"
                                                @change="hotspot.availability_enabled=$event.target.checked;changed()"
                                            ><span>Enable availability conditions</span></label>
                                        <section
                                            x-show="availabilityEnabled(hotspot)"
                                            class="space-y-3 rounded-lg border p-3 dark:border-stone-700"
                                        >
                                            @include('studio.flag-rules', [
                                                'target' => 'hotspot',
                                                'field' => 'flag_conditions',
                                                'heading' => 'Required flag states (all must match)',
                                            ])
                                            <details>
                                                <summary class="cursor-pointer text-xs">Required inventory items
                                                </summary>
                                                <div class="mt-2 flex flex-wrap gap-3"><template
                                                        x-for="item in gameObjects"
                                                        :key="item.id"
                                                    ><label class="area-checkbox"><input
                                                                type="checkbox"
                                                                :checked="(hotspot.required_items || []).includes(item.id)"
                                                                @change="hotspot.required_items=$event.target.checked?[...(hotspot.required_items||[]),item.id]:(hotspot.required_items||[]).filter(id=>id!==item.id);changed()"
                                                            ><span x-text="item.name"></span></label></template></div>
                                            </details>
                                            <label class="field">Explanation
                                                <textarea
                                                    x-model="hotspot.unavailable_text"
                                                    @input="changed()"
                                                    maxlength="3000"
                                                    placeholder="Optional: what happens when the action cannot succeed"
                                                ></textarea>
                                            </label>
                                            <label
                                                class="field"
                                                x-show="dualAction(hotspot)"
                                            >Changed-state explanation (optional)
                                                <textarea
                                                    x-model="hotspot.changed_unavailable_text"
                                                    @input="changed()"
                                                    maxlength="3000"
                                                    placeholder="What happens when the changed-state action cannot succeed?"
                                                ></textarea>
                                            </label>
                                        </section>
                                    </div>
                                    @include('studio.flag-rules', [
                                        'target' => 'hotspot',
                                        'field' => 'flag_effects',
                                        'heading' => 'Set flags after this action',
                                        'inlineHeading' => true,
                                    ])
                                    @include('studio.area-actions')

                                    <div
                                        class="field"
                                        x-show="['inspect','pickup','transition'].includes(hotspot.action)"
                                    ><span>Scene after click</span><button
                                            type="button"
                                            class="btn-secondary justify-start gap-3 text-left"
                                            aria-haspopup="dialog"
                                            @click="openInteractionPicker('scene')"
                                        ><img
                                                x-show="visualAsset(hotspot.target_id)"
                                                :src="$thumbnail(visualAsset(hotspot.target_id)?.url)"
                                                alt=""
                                                class="h-10 w-14 object-contain"
                                            ><span
                                                x-text="scenes.find(v=>v.id===hotspot.target_id)?sceneLabel(scenes.find(v=>v.id===hotspot.target_id)):'Keep current scene'"
                                            ></span></button></div>
                                    <section
                                        x-show="hasAssignedContainer"
                                        class="space-y-2 border-t pt-4"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold">Container</h4><button
                                                type="button"
                                                class="btn-secondary text-red-700 dark:text-red-400"
                                                :disabled="containerBusy || saving"
                                                @click="requestContainerDelete()"
                                            >Delete container</button>
                                        </div><template
                                            x-for="plan in assignedContainerPlans.filter(plan=>plan.id===hotspot.container_plan_id)"
                                            :key="plan.id"
                                        >
                                            <p
                                                class="text-sm"
                                                x-text="plan.name+(plan.id===hotspot.container_plan_id?' · Active':'')"
                                            ></p>
                                        </template>
                                    </section>
                                </section>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </template>
    </div>
    <dialog @toggle="editorDialogOpen.sceneCaptureDialog=$el.open"
        x-ref="sceneCaptureDialog"
        class="image-form-dialog dialog-panel rounded-2xl border bg-white p-6 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-serif text-xl">Save scene as image</h2><button
                type="button"
                class="btn-secondary"
                @click="$refs.sceneCaptureDialog.close()"
                :disabled="sceneCaptureBusy"
            >Cancel</button>
        </header>
        <div class="dialog-scroll-body">
            <p
                x-show="sceneCaptureError"
                x-text="sceneCaptureError"
                role="alert"
                class="mb-3 text-sm text-red-700"
            ></p>
            <img
                :src="editorDialogOpen.sceneCaptureDialog ? (sceneCapture?.preview) : null"
                alt="Scene image without click-area outlines"
                class="mb-4 max-h-[55vh] w-full object-contain"
            >
            <label class="field">Image name<input
                    type="text"
                    maxlength="160"
                    x-model="sceneCaptureName"
                    :disabled="sceneCaptureBusy"
                ></label>
            <p
                class="my-4 text-sm"
                x-text="sceneCapture?.description"
            ></p>
        </div>
        <footer class="dialog-actions"> <button
                type="button"
                class="btn-primary"
                @click="$busy($el, async () => (saveSceneCapture()))"
                :disabled="sceneCaptureBusy || !sceneCaptureName.trim()"
                x-text="sceneCaptureBusy?'Saving…':'Save image'"
            ></button>
        </footer>
    </dialog>
    <dialog
        x-ref="visualTypeDialog"
        class="reference-dialog rounded-xl bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        aria-labelledby="visual-type-title"
    >
        <div class="space-y-4 p-5">
            <h2
                id="visual-type-title"
                class="font-serif text-xl"
            >Change visual type?</h2>
            <p
                class="text-sm"
                x-text="typeChange?.message"
            ></p>
            <p class="text-sm">The image, name, and prompt will be kept.</p>
            <div class="flex justify-end gap-3"><button
                    type="button"
                    class="btn-secondary"
                    autofocus
                    @click="typeChange=null;$refs.visualTypeDialog.close()"
                >Cancel</button><button
                    type="button"
                    class="btn-primary"
                    @click="confirmVisualType()"
                >Change type</button></div>
        </div>
    </dialog>
    <dialog
        x-ref="visualNameDialog"
        class="reference-dialog rounded-xl bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        aria-labelledby="visual-name-title"
    >
        <form
            class="space-y-4 p-5"
            @submit.prevent="createNamedVisual()"
        >
            <h2
                id="visual-name-title"
                class="font-serif text-xl"
                x-text="visualAction==='duplicate'?'Duplicate visual':visualAction==='scene'?'New scene state':'New object / close-up'"
            ></h2><label class="field">Name<input
                    x-ref="visualNameInput"
                    x-model="visualName"
                    required
                    maxlength="160"
                    autocomplete="off"
                ></label>
            <div class="flex justify-end gap-3"><button
                    type="button"
                    class="btn-secondary"
                    @click="$refs.visualNameDialog.close()"
                >Cancel</button><button
                    type="submit"
                    class="btn-primary"
                    x-text="visualAction==='duplicate'?'Duplicate':'Create'"
                ></button></div>
        </form>
    </dialog>
    <dialog
        x-ref="deleteVisualDialog"
        class="reference-dialog rounded-xl bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        aria-labelledby="delete-visual-title"
    >
        <div class="space-y-4 p-5">
            <h2
                id="delete-visual-title"
                class="font-serif text-xl"
            >Delete visual?</h2>
            <p
                class="font-semibold"
                x-text="deletingVisual?.name"
            ></p>
            <p class="text-sm">This removes the visual and its clickable areas. Images remain in your library.</p>
            <p
                x-show="dependentAreas"
                class="text-sm"
                x-text="dependentAreas+' interaction(s) link to this visual. Those links will be cleared; affected actions will become Examine where needed.'"
            ></p>
            <div class="flex justify-end gap-3"><button
                    type="button"
                    class="btn-secondary"
                    autofocus
                    @click="$refs.deleteVisualDialog.close()"
                >Cancel</button><button
                    type="button"
                    class="rounded-lg bg-red-700 px-4 py-2 text-sm text-white"
                    @click="confirmVisualDelete()"
                >Delete visual</button></div>
        </div>
    </dialog>
    <dialog
        x-ref="deleteAreaDialog"
        class="reference-dialog max-w-sm rounded-xl bg-white p-5 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        aria-labelledby="delete-area-title"
    >
        <div class="p-5">
            <h2
                id="delete-area-title"
                class="font-serif text-xl"
            >Delete clickable area?</h2>
            <p
                class="my-4 text-sm"
                x-text="visual?.hotspots.find(h=>h.id===deleteAreaId)?.label"
            ></p>
            <div class="flex justify-end gap-3"><button
                    type="button"
                    class="btn-secondary"
                    autofocus
                    @click="$refs.deleteAreaDialog.close()"
                >Cancel</button><button
                    type="button"
                    class="rounded-lg bg-red-700 px-4 py-2 text-sm text-white"
                    @click="confirmAreaDelete()"
                >Delete area</button></div>
        </div>
    </dialog>
    <dialog @toggle="editorDialogOpen.interactionImagePicker=$el.open"
        x-ref="interactionImagePicker"
        class="reference-dialog rounded-xl bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        aria-labelledby="interaction-picker-title"
    >
        <header class="flex items-center justify-between border-b p-4 dark:border-stone-700">
            <h2
                id="interaction-picker-title"
                class="font-serif text-xl"
                x-text="pickerKind==='object'?'Choose inventory item':'Choose next scene'"
            ></h2><button
                type="button"
                class="btn-secondary"
                @click="$refs.interactionImagePicker.close()"
            >Close</button>
        </header>
        <div class="max-h-[60dvh] overflow-y-auto p-4">

            <div
                x-show="pickerKind==='object'"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            ><template
                    x-for="item in gameObjects"
                    :key="item.id"
                ><button
                        type="button"
                        class="rounded-lg border p-2 text-left dark:border-stone-700"
                        :class="hotspot?.object_id === item.id ? 'ring-2 ring-amber-500' : ''"
                        @click="selectInventoryItem(item.id)"
                    ><img
                            x-show="asset(item.asset_id)"
                            :src="editorDialogOpen.interactionImagePicker ? ($thumbnail(asset(item.asset_id)?.url)) : null"
                            :alt="item.name"
                            loading="lazy"
                            class="aspect-square w-full object-contain"
                        ><span
                            class="block text-sm font-semibold"
                            x-text="item.name"
                        ></span><span
                            class="block text-xs"
                            x-text="item.description"
                        ></span></button></template>
                <p
                    x-show="!gameObjects.length"
                    class="col-span-full text-sm"
                >Create an item in the Inventory tab first.</p>
            </div>
            <div
                x-show="pickerKind==='scene'"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            ><template
                    x-for="v in scenes.filter(v=>v.id!==selected)"
                    :key="v.id"
                ><button
                        type="button"
                        class="rounded-lg border p-2 text-left dark:border-stone-700"
                        :class="hotspot?.target_id === v.id ? 'ring-2 ring-amber-500' : ''"
                        @click="if(hotspot){hotspot.target_id=v.id;changed();$refs.interactionImagePicker.close();}"
                    ><img
                            x-show="asset(v.asset_id)"
                            :src="editorDialogOpen.interactionImagePicker ? ($thumbnail(asset(v.asset_id)?.url)) : null"
                            :alt="v.name"
                            loading="lazy"
                            class="aspect-square w-full object-contain"
                        ><span
                            class="block text-xs break-words"
                            x-text="sceneLabel(v)"
                        ></span></button></template>
                <p
                    x-show="scenes.length<2"
                    class="col-span-full text-sm"
                >Add another scene state to create a destination.</p>
            </div>
        </div>
        <footer class="border-t p-4 dark:border-stone-700"><button
                type="button"
                class="btn-secondary"
                @click="if(hotspot){if(pickerKind==='object'){hotspot.object_id=null;hotspot.overlay=false;if(['inspect','pickup','pickup_v2'].includes(hotspot.action))hotspot.action='examine';}else{hotspot.target_id=null;if(hotspot.action==='transition')hotspot.action='examine';}changed();$refs.interactionImagePicker.close();}"
                x-text="pickerKind==='object'?'No object image':'Keep current scene'"
            ></button></footer>
    </dialog>
    <dialog @toggle="editorDialogOpen.visualImagePicker=$el.open"
        x-ref="visualImagePicker"
        class="image-picker-dialog reference-dialog rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100"
        aria-labelledby="visual-image-picker-title"
        @click="if($event.target===$el)$el.close()"
    >
        <div class="flex max-h-[85dvh] flex-col">
            <header
                class="flex items-center justify-between gap-3 border-b border-stone-200 p-4 dark:border-stone-700">
                <h2
                    id="visual-image-picker-title"
                    class="font-serif text-xl"
                >Choose an image</h2><button
                    type="button"
                    class="btn-secondary"
                    @click="$refs.visualImagePicker.close()"
                >Close</button>
            </header>
            <div class="min-h-0 overflow-y-auto p-4">
                <p
                    x-show="!pickerAssets.length"
                    class="py-8 text-center text-sm text-stone-500"
                >No images yet. Upload references or generate an image first.</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><template
                        x-for="a in pickerAssets"
                        :key="a.id"
                    ><button
                            type="button"
                            class="overflow-hidden rounded-xl border text-left focus:outline-none focus:ring-2 focus:ring-amber-500"
                            :class="pickerSelectedAsset === a.id ? 'border-amber-500 ring-2 ring-amber-500' :
                                'border-stone-200 dark:border-stone-700'"
                            :aria-pressed="pickerSelectedAsset === a.id"
                            :aria-label="'Select ' + a.name"
                            @click="selectPickerImage(a.id)"
                        >
                            <img
                                :src="editorDialogOpen.visualImagePicker ? ($thumbnail(a.url)) : null"
                                :alt="a.name"
                                loading="lazy"
                                class="aspect-square w-full bg-stone-100 object-contain dark:bg-stone-950"
                            ><span
                                class="block break-words p-2 text-xs"
                                x-text="a.name"
                            ></span><span
                                x-show="pickerSelectedAsset===a.id"
                                class="block px-2 pb-2 text-xs font-semibold text-amber-700 dark:text-amber-300"
                            >Selected</span>
                        </button></template></div>
            </div>
            <footer class="flex justify-between gap-3 border-t border-stone-200 p-4 dark:border-stone-700"><button
                    type="button"
                    class="btn-secondary"
                    :disabled="!pickerSelectedAsset"
                    @click="selectPickerImage(null)"
                >Remove selection</button><a
                    class="btn-secondary"
                    href="{{ route('images.studio', [$story, $page]) }}"
                    :href="'{{ route('images.studio', [$story, $page]) }}' + (window.vsgSceneEditor?.visual ?
                        '?visual=' +
                        encodeURIComponent(window.vsgSceneEditor.visual.id) : ' ')"
                    @click.prevent="window.vsgSceneEditor?.openGeneration('{{ route('images.studio', [$story, $page]) }}',false)"
                >Generate an image</a></footer>
        </div>
    </dialog>
    @include('studio.preview')

    <dialog @toggle="editorDialogOpen.skipItemsDialog=$el.open"
        x-ref="skipItemsDialog"
        class="reference-dialog rounded-xl bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        aria-label="Choose skip rule inventory items"
        @click="if($event.target===$el)$el.close()"
    >
        <div class="flex max-h-[calc(85dvh-2px)] flex-col overflow-hidden">
            <header
                class="flex shrink-0 items-center justify-between gap-3 border-b border-stone-200 p-4 dark:border-stone-700"
            >
                <h3 class="font-serif text-xl">Choose inventory items</h3><button
                    type="button"
                    class="btn-secondary"
                    @click="$refs.skipItemsDialog.close()"
                >Cancel</button>
            </header>
            <div class="min-h-0 overflow-y-auto p-4 grid grid-cols-2 md:grid-cols-3 gap-3"><template
                    x-for="item in previewInventoryItems"
                    :key="item.id"
                ><label
                        class="cursor-pointer rounded-lg border border-stone-200 p-3 dark:border-stone-700"
                        :class="skipItemSelection.includes(item.id) ? 'ring-2 ring-amber-500' : ''"
                    ><template x-if="asset(item.asset_id)"><img
                                :src="editorDialogOpen.skipItemsDialog ? ($thumbnail(asset(item.asset_id).url)) : null"
                                :alt="item.name"
                                class="h-28 w-full object-contain mb-2"
                            ></template><span class="flex gap-2 items-start"><input
                                type="checkbox"
                                x-model="skipItemSelection"
                                :value="item.id"
                            ><span><strong
                                    class="block text-sm"
                                    x-text="item.name"
                                ></strong></span></span></label></template>
                <p
                    x-show="!previewInventoryItems.length"
                    class="text-sm"
                >Create an object to use it in a rule.</p>
            </div>
            <footer class="shrink-0 border-t border-stone-200 p-4 dark:border-stone-700"><button
                    type="button"
                    class="btn-primary"
                    @click="saveSkipItems()"
                >Use selected items</button></footer>
        </div>
    </dialog>

    @include('studio.extraction')
    <dialog
        x-ref="deleteContainerDialog"
        class="reference-dialog rounded-xl bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
    >
        <div class="space-y-4 p-5">
            <h2 class="font-serif text-xl">Delete container?</h2>
            <p>Remove this container’s setup, contained click areas, and all its plans. The drawn outer area remains as
                Examine. Generated images and shared inventory items are kept.</p>
            <div class="flex justify-end gap-3"><button
                    type="button"
                    class="btn-secondary"
                    :disabled="containerBusy"
                    @click="$refs.deleteContainerDialog.close()"
                >Cancel</button><button
                    type="button"
                    class="btn-primary"
                    :disabled="containerBusy"
                    @click="$busy($el, async () => (deleteAssignedContainer()))"
                    x-text="containerBusy?'Deleting…':'Delete container'"
                ></button></div>
        </div>
    </dialog>
    @include('studio.containers')

    <template x-teleport="body">
        <dialog @toggle="editorDialogOpen.introVideoPicker=$el.open"
            x-ref="introVideoPicker"
            class="image-picker-dialog reference-dialog rounded-2xl bg-white p-5 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
            aria-label="Choose scene intro video"
            @click="if($event.target===$el)$el.close()"
        >
            <header class="mb-4 flex items-center justify-between gap-3">
                <h2 class="font-serif text-xl" x-text="videoPickerAreaId ? 'Action video' : 'Scene intro video'"></h2><button
                    type="button"
                    class="btn-secondary"
                    @click="$refs.introVideoPicker.close()"
                >Close</button>
            </header>
            <button
                type="button"
                class="btn-secondary mb-4"
                @click="selectIntroVideo(null)"
            >No video</button>
            <p x-show="introVideosLoading">Loading videos…</p>
            <p
                x-show="introVideosError"
                x-text="introVideosError"
                role="alert"
            ></p>
            <p x-show="!introVideosLoading && !introVideosError && !introVideos.length">Generate a video in the Video
                tab first. Completed videos appear here.</p>
            <div class="max-h-[65vh] space-y-4 overflow-y-auto">
                <template
                    x-for="video in introVideos"
                    :key="video.id"
                >
                    <button
                        type="button"
                        class="block w-full rounded-xl border border-stone-300 p-4 text-left dark:border-stone-600"
                        @click="selectIntroVideo(video.id)"
                        :aria-pressed="(videoPickerAreaId ? visual?.hotspots.find(h=>h.id===videoPickerAreaId)?.action_video_id : visual?.intro_video_id) === video.id"
                    >
                        <strong
                            class="mb-3 block"
                            x-text="video.name"
                        ></strong>
                        <div class="grid grid-cols-2 gap-4">
                            <div><span class="mb-1 block text-sm">First frame</span><img
                                    :src="editorDialogOpen.introVideoPicker ? ($thumbnail(video.first_frame_url)) : null"
                                    alt="First frame"
                                    class="h-40 w-full object-contain"
                                ></div>
                            <div x-show="video.last_frame_url"><span class="mb-1 block text-sm">Last frame</span><img
                                    :src="editorDialogOpen.introVideoPicker ? ($thumbnail(video.last_frame_url || null)) : null"
                                    alt="Last frame"
                                    class="h-40 w-full object-contain"
                                ></div>
                        </div>
                    </button>
                </template>
            </div>
        </dialog>
    </template>
</section>
