<dialog
    x-ref="containerPlanDialog"
    class="w-full max-w-lg rounded-xl border bg-white p-5 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
>
    <h2 class="font-serif text-xl">Existing container plans</h2>
    <label class="field mt-4">Drawn area<select
            x-model="containerArea"
            @change="containerPlanId=selectedContainerPlans[0]?.id||''"
        ><template
                x-for="a in containerAreas"
                :key="a.id"
            >
                <option
                    :value="a.id"
                    x-text="a.label"
                ></option>
            </template></select></label>
    <label class="field mt-4">Saved plan<select x-model="containerPlanId">
            <option value="">No plan selected</option><template
                x-for="p in selectedContainerPlans"
                :key="p.id"
            >
                <option
                    :value="p.id"
                    x-text="containerPlanLabel(p)"
                ></option>
            </template>
        </select></label>
    <label
        class="field mt-3"
        x-show="containerPlan"
    >Plan name<input
            :value="containerPlan?.name || ''"
            maxlength="160"
            @change="renameContainerPlan($event.target.value)"
        ></label>
    <p
        class="mt-2 text-xs"
        x-show="containerPlan"
        x-text="planSummary(containerPlan?.batch)"
    ></p>
    <p
        class="text-sm text-red-600"
        x-text="containerError"
    ></p>
    <div class="mt-5 flex flex-wrap justify-end gap-2"><button
            type="button"
            class="btn-secondary"
            @click="$refs.containerPlanDialog.close()"
        >Cancel</button><button
            type="button"
            class="btn-secondary"
            @click="chooseContainerPlan(false)"
        >Create new plan</button><button
            type="button"
            class="btn-primary"
            :disabled="!containerPlan"
            @click="chooseContainerPlan(true)"
        >Edit existing plan</button></div>
</dialog>
<template x-teleport="body">
    <dialog
        x-ref="containerDialog"
        class="reference-dialog image-picker-dialog rounded-xl border bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        style="width:min(1200px,calc(100vw - 2rem))"
        @close="clearTimeout(containerTimer)"
    >
        <div class="flex flex-col">
            <header class="flex items-center justify-between border-b px-6 py-4">
                <h2
                    class="font-serif text-xl"
                    x-text="containerReuseState?'Changed-state collectibles':'Container plans'"
                ></h2><button
                    type="button"
                    class="btn-secondary"
                    @click="closeContainers()"
                >Close</button>
            </header>
            <div
                x-show="containerError"
                x-cloak
                class="shrink-0 border-b border-red-300 bg-red-50 px-6 py-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"
                role="alert"
                aria-live="assertive"
            ><strong>Container request failed</strong>
                <p x-text="containerError"></p>
            </div>
            <div class="min-h-0 space-y-4 overflow-y-auto px-6 py-5"><label
                    x-show="containerPlan"
                    class="field"
                >Plan name<input
                        :value="containerPlan?.name || ''"
                        maxlength="160"
                        @change="renameContainerPlan($event.target.value)"
                    ></label>
                <div class="grid gap-3 sm:grid-cols-2"><label class="field">Drawn container area<select
                            x-model="containerArea"
                            :disabled="containerRun || containerBusy || containerPromptBusy"
                            @change="newContainerPlan()"
                        ><template
                                x-for="a in containerAreas"
                                :key="a.id"
                            >
                                <option
                                    :value="a.id"
                                    x-text="a.label"
                                ></option>
                            </template></select></label><label class="field">Plan<select
                            x-model="containerPlanId"
                            :disabled="containerRun || containerBusy || containerPromptBusy"
                            @change="selectContainerPlan()"
                        >
                            <option value="">Create a new plan</option><template
                                x-for="p in selectedContainerPlans"
                                :key="p.id"
                            >
                                <option
                                    :value="p.id"
                                    x-text="containerPlanLabel(p)"
                                ></option>
                            </template>
                        </select></label></div>
                <template x-if="!containerPlan">
                    <div class="space-y-4">
                        <div
                            x-show="!containerReuseState"
                            class="grid min-w-0 gap-5 md:grid-cols-2"
                        >
                            <div class="space-y-3">
                                <h3 class="font-semibold">Crop padding (pixels)</h3>
                                <div class="flex flex-wrap gap-4"><label class="area-checkbox"><input
                                            type="radio"
                                            name="container-padding-mode"
                                            :checked="containerEqualPadding"
                                            @change="linkContainerPadding(true)"
                                        ><span>Equal distance</span></label><label class="area-checkbox"><input
                                            type="radio"
                                            name="container-padding-mode"
                                            :checked="!containerEqualPadding"
                                            @change="linkContainerPadding(false)"
                                        ><span>Independent sides</span></label></div><template
                                    x-for="side in ['top','right','bottom','left']"
                                    :key="side"
                                ><label class="field"><span class="flex justify-between"><span
                                                class="capitalize"
                                                x-text="side"
                                            ></span><span
                                                x-text="containerPaddingDraft[side]+' px'"></span></span><input
                                            type="range"
                                            min="0"
                                            max="500"
                                            step="1"
                                            :value="containerPaddingDraft[side]"
                                            @input="changeContainerPadding(side,$event.target.value)"
                                            @change="commitContainerPadding()"
                                        ></label></template>
                                <p class="text-xs">Padding stops at the scene edges. The clickable area stays unchanged.
                                </p>
                            </div>
                            <div class="flex min-w-0 items-center justify-center"><canvas
                                    class="max-h-80 max-w-full"
                                    x-effect="if(containerCrop)drawExtractionCrop($el,containerCrop,visual.asset_id)"
                                ></canvas></div>
                        </div>
                        <img
                            loading="lazy"
                            decoding="async"
                            x-show="containerReuseState"
                            :src="$thumbnail(asset(containerAreas.find(a => a.id === containerArea)?.state_asset_id)?.url)"
                            alt="Changed state to extract from"
                            class="max-h-80 max-w-full mx-auto"
                        >
                        <label class="field">Plan name<input
                                x-model="containerName"
                                maxlength="160"
                            ></label>
                        <div class="space-y-2">
                            <h3 class="font-semibold">Inventory items <span
                                    class="text-xs font-normal"
                                    x-text="containerItems.length+'/12 selected'"
                                ></span></h3>
                            <div
                                class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-2">
                                <template
                                    x-for="item in inventoryItems"
                                    :key="item.id"
                                ><label class="flex cursor-pointer items-center gap-3 rounded-lg p-2"><input
                                            type="checkbox"
                                            :checked="containerItems.some(c => c.object_id === item.id)"
                                            :disabled="containerItems.length >= 12 && !containerItems.some(c => c.object_id ===
                                                item.id)"
                                            @change="toggleContainerItem(item,$event.target.checked)"
                                        ><img
                                            loading="lazy"
                                            decoding="async"
                                            x-show="asset(item.asset_id)"
                                            :src="$thumbnail(asset(item.asset_id)?.url)"
                                            alt=""
                                            class="h-12 w-14 object-contain"
                                        ><span x-text="item.name"></span></label></template>
                                <p
                                    x-show="!inventoryItems.length"
                                    class="text-sm"
                                >Add items in the Inventory tab first.</p>
                            </div>
                        </div>
                        <div x-show="!containerReuseState">@include('studio.container-prompt')
                            <label class="field">Image prompt
                                <textarea
                                    x-model="containerPrompt"
                                    rows="5"
                                    maxlength="10000"
                                    placeholder="Draft with AI above, or write the image prompt here."
                                ></textarea>
                            </label>
                        </div>
                        <button
                            type="button"
                            class="btn-primary"
                            :disabled="containerRun || containerBusy || containerPromptBusy || !containerArea || !containerItems
                                .length || (!containerReuseState && !containerPrompt.trim())"
                            @click="createContainerPlan()"
                            x-text="containerBusy?'Preparing crop…':'Create plan'"
                        ></button>
                    </div>
                </template>
                <template x-if="containerPlan">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p
                                class="text-xs"
                                x-text="'Saved crop padding: '+['top','right','bottom','left'].map(side=>side+' '+(containerPlan.padding?.[side]||0)+'px').join(', ')"
                            ></p><button
                                type="button"
                                class="btn-secondary"
                                :disabled="containerRun || containerBusy || containerPromptBusy"
                                @click="copyContainerPlan()"
                            >New plan from this setup</button>
                        </div>
                        <div x-show="containerPlan.stage==='preview' && !containerReuseState">
                            @include('studio.container-prompt')</div>
                        <label class="field">Image model<select x-model="containerModel">
                                <option value="">Choose model</option><template
                                    x-for="m in models"
                                    :key="m.id"
                                >
                                    <option
                                        :value="m.id"
                                        x-text="imageModelLabel(m)"
                                    ></option>
                                </template>
                            </select></label>
                        <template x-if="containerPlan.stage==='preview' && containerPreviewAsset">
                            <div class="space-y-3">
                                <p class="text-sm">Review the open container. Select an item to draw its area, then
                                    drag it or use the corner handles to adjust it.</p>
                                <div class="flex flex-wrap gap-2"><template
                                        x-for="(c,i) in containerItems"
                                        :key="c.area_id"
                                    ><button
                                            type="button"
                                            class="btn-secondary"
                                            :class="containerItemIndex === i ? 'ring-2 ring-amber-500' : ''"
                                            @click="containerItemIndex=i"
                                            x-text="c.name+(c.bounds?' ✓':' — draw area')"
                                        ></button></template></div>
                                <div class="flex gap-2"><button
                                        type="button"
                                        class="btn-secondary"
                                        :disabled="containerItemIndex >= containerItems.length - 1 || !!containerDraw"
                                        @click="moveContainerLayer(1)"
                                    >↑ Up</button><button
                                        type="button"
                                        class="btn-secondary"
                                        :disabled="containerItemIndex === 0 || !!containerDraw"
                                        @click="moveContainerLayer(-1)"
                                    >↓ Down</button></div>
                                <div
                                    x-ref="containerCanvas"
                                    class="relative touch-none cursor-crosshair select-none"
                                    @pointerdown="beginContainerDraw($event)"
                                    @pointermove="moveContainerDraw($event)"
                                    @pointerup="endContainerDraw()"
                                    @pointercancel="endContainerDraw(true)"
                                    @lostpointercapture="endContainerDraw(true)"
                                ><img
                                        loading="lazy"
                                        decoding="async"
                                        :src="$thumbnail(asset(containerPreviewAsset)?.url)"
                                        alt="Open container preview"
                                        class="block w-full"
                                        draggable="false"
                                    ><template
                                        x-for="c in containerItems.filter(c=>c.bounds)"
                                        :key="c.area_id"
                                    >
                                        <div
                                            class="absolute cursor-move border border-amber-400/50"
                                            :style="style(c.bounds) + ';z-index:' + (containerItems.indexOf(c) + 1)"
                                            @pointerdown.stop="beginContainerEdit($event,c.area_id)"
                                        ><span
                                                x-show="containerItems[containerItemIndex]?.area_id===c.area_id"
                                                class="pointer-events-none absolute bottom-full left-0 whitespace-nowrap bg-black/70 px-1 text-xs text-white"
                                                x-text="c.name"
                                            ></span><template
                                                x-for="corner in ['nw','ne','sw','se']"
                                                :key="corner"
                                            ><button
                                                    x-show="containerItems[containerItemIndex]?.area_id===c.area_id"
                                                    type="button"
                                                    :aria-label="'Resize ' + c.name + ' ' + corner"
                                                    class="absolute border border-white bg-amber-500"
                                                    :style="'width:7px;height:7px;padding:0;min-width:0;min-height:0;border-radius:0;' +
                                                    (corner.includes('n') ? 'top:-4px;' : 'bottom:-4px;') + (corner
                                                        .includes('w') ? 'left:-4px;' : 'right:-4px;') + 'cursor:' +
                                                    corner + '-resize'"
                                                    @pointerdown.stop="beginContainerEdit($event,c.area_id,corner)"
                                                ></button></template></div>
                                    </template></div><button
                                    type="button"
                                    class="btn-primary"
                                    :disabled="containerRun || containerBusy || containerPromptBusy || containerOutputs.some(o =>
                                        containerPending(o)) || !containerItems.every(c => c.bounds)"
                                    @click="prepareContainerContents()"
                                    x-text="containerBusy?'Preparing contents…':'Approve preview & prepare contents'"
                                ></button>
                            </div>
                        </template>
                        <template x-if="containerPlan.stage!=='preview'">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-3"><label class="area-checkbox"><input
                                            type="checkbox"
                                            :checked="containerView === 'open'"
                                            @change="containerView=$event.target.checked?'open':'closed'"
                                        ><span>Open container</span></label><template
                                        x-for="c in containerItems"
                                        :key="c.area_id"
                                    ><label class="area-checkbox"><input
                                                type="checkbox"
                                                :value="c.area_id"
                                                x-model="containerHidden"
                                            ><span x-text="'Collected: '+c.name"></span></label></template></div>
                                <div class="relative"><img
                                        loading="lazy"
                                        decoding="async"
                                        :src="$thumbnail(asset(containerView === 'closed' ? containerPlan.source_asset_id :
                                            (
                                                containerOutputs.find(o => o.type === 'switch_on')
                                                ?.accepted_asset_id || containerPreviewAsset))?.url)"
                                        class="block w-full"
                                        alt="Container composition"
                                    ><template
                                        x-for="o in containerOutputs.filter(o=>o.type==='layer'&&o.accepted_asset_id)"
                                        :key="o.id"
                                    ><img
                                            loading="lazy"
                                            decoding="async"
                                            x-show="containerView==='open'&&!containerHidden.includes(o.area_id)"
                                            :src="$thumbnail(asset(o.accepted_asset_id)?.url)"
                                            :style="style(o.bounds)"
                                            class="pointer-events-none absolute"
                                            alt="Content layer"
                                        ></template></div>
                                <p
                                    x-show="!containerOutputs.find(o=>o.type==='switch_on')?.accepted_asset_id"
                                    class="text-xs"
                                >Generate the empty interior to preview independent collection.</p>
                            </div>
                        </template>
                        <div class="flex gap-3"><button
                                type="button"
                                class="btn-primary"
                                :disabled="containerRun || containerBusy || containerPromptBusy || !containerModel || !
                                    containerOutputs.some(o => !o.accepted_asset_id && !containerPending(o))"
                                @click="generateContainerMissing()"
                            >Generate missing outputs</button><button
                                type="button"
                                class="btn-primary"
                                :disabled="containerRun || containerBusy || containerPromptBusy || !containerReady"
                                @click="activateContainer()"
                            >Activate accepted plan</button></div>
                        <template
                            x-for="o in containerOutputs"
                            :key="o.id"
                        >
                            <article class="space-y-3 rounded-lg border p-4">
                                <div class="flex items-center justify-between">
                                    <h3
                                        class="font-semibold"
                                        x-text="o.name"
                                    ></h3><span
                                        class="text-xs"
                                        x-text="containerPending(o)?'Rendering…':o.accepted_asset_id?'Accepted':'Needs generation'"
                                    ></span>
                                </div><img
                                    loading="lazy"
                                    decoding="async"
                                    x-show="o.accepted_asset_id"
                                    :src="$thumbnail(asset(o.accepted_asset_id)?.url)"
                                    class="max-h-52 max-w-full object-contain"
                                    :alt="o.name"
                                ><template
                                    x-if="o.type!=='switch_off' && !(o.type==='container_preview'&&containerPlan.stage!=='preview')"
                                >
                                    <div class="space-y-2"><label class="field">Prompt
                                            <textarea
                                                :value="containerPrompts[o.id] ?? o.prompt"
                                                @input="containerPrompts[o.id]=$event.target.value"
                                                rows="2"
                                            ></textarea>
                                        </label>
                                        <div class="flex gap-2"><button
                                                type="button"
                                                class="btn-secondary"
                                                :disabled="containerRun || containerBusy || containerPromptBusy ||
                                                    containerPending(o) || !containerModel"
                                                @click="containerOutputAction(o,'generate')"
                                                x-text="containerPending(o)?'Generating…':(o.versions?.length?'Generate again':'Generate image')"
                                            >Generate image</button><button
                                                type="button"
                                                class="btn-secondary"
                                                x-show="o.accepted_asset_id"
                                                :disabled="containerRun || containerBusy || containerPromptBusy ||
                                                    containerPending(o)"
                                                @click="containerOutputAction(o,'unaccept')"
                                            >Unaccept</button></div>
                                    </div>
                                </template><template
                                    x-for="j in o.jobs||[]"
                                    :key="j.id"
                                >
                                    <div>
                                        <p
                                            class="text-xs"
                                            x-text="j.error||j.status"
                                        ></p><template
                                            x-for="id in j.asset_ids"
                                            :key="id"
                                        ><button
                                                type="button"
                                                class="btn-secondary"
                                                :disabled="containerRun || containerBusy ||
                                                    containerPromptBusy||(o.type==='container_preview' && containerPlan
                                                    .stage !== 'preview')"
                                                @click="containerOutputAction(o,'accept',id)"
                                            ><img
                                                    loading="lazy"
                                                    decoding="async"
                                                    :src="$thumbnail(asset(id)?.url)"
                                                    class="h-16 w-20 object-contain"
                                                    alt="Generated version"
                                                >Use version</button></template>
                                    </div>
                                </template>
                            </article>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </dialog>
</template>
