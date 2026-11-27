<template x-teleport="body">
    <dialog @toggle="editorDialogOpen.previewInventoryDialog=$el.open"
        x-ref="previewInventoryDialog"
        class="image-picker-dialog reference-dialog rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100"
        aria-labelledby="preview-inventory-title"
        @click="if($event.target===$el)$el.close()"
    >
        <div class="flex max-h-[85dvh] flex-col">
            <header class="flex items-center justify-between gap-3 border-b border-stone-200 p-4 dark:border-stone-700">
                <h2
                    id="preview-inventory-title"
                    class="font-serif text-xl"
                >Starting inventory</h2><button
                    type="button"
                    class="btn-secondary"
                    @click="$refs.previewInventoryDialog.close()"
                >Cancel</button>
            </header>
            <div class="min-h-0 space-y-3 overflow-y-auto p-4">
                <label class="field">Start at page &amp; scene<select x-model="previewStartKey"><template
                            x-for="destination in pageSceneDestinations"
                            :key="destination.key"
                        >
                            <option
                                :value="destination.key"
                                :selected="destination.key === previewStartKey"
                                x-text="destination.label"
                            ></option>
                        </template></select></label>
                <div
                    x-show="flagDefinitions.length"
                    class="space-y-2"
                >
                    <h3 class="text-sm font-semibold">Starting story flags</h3>
                    <div class="flex flex-wrap gap-3"><template
                            x-for="flag in flagDefinitions"
                            :key="flag.key"
                        ><label class="area-checkbox"><input
                                    type="checkbox"
                                    :value="flag.key"
                                    x-model="previewFlags"
                                ><span x-text="flag.label"></span></label></template></div>
                </div>
                <p class="text-sm text-stone-500">Choose items to carry during this test, or start with an empty
                    inventory.</p>
                <p
                    x-show="!previewInventoryItems.length"
                    class="text-sm text-stone-500"
                >No object items have been created yet.</p>
                <template
                    x-for="item in previewInventoryItems"
                    :key="item.id"
                ><label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 p-3 dark:border-stone-700">
                        <input
                            type="checkbox"
                            class="mt-1 shrink-0 rounded"
                            :value="item.id"
                            x-model="previewInventorySelection"
                        >
                        <img
                            loading="lazy"
                            decoding="async"
                            x-show="asset(item.asset_id)"
                            :src="editorDialogOpen.previewInventoryDialog ? ($thumbnail(asset(item.asset_id)?.url)) : null"
                            alt=""
                            class="h-16 w-20 shrink-0 rounded object-contain"
                        >
                        <span class="min-w-0"><span
                                class="block font-semibold"
                                x-text="item.name"
                            ></span><span
                                class="mt-1 block whitespace-pre-wrap break-words text-sm"
                                x-text="item.description || 'No description yet.'"
                            ></span></span>
                    </label></template>
            </div>
            @include('studio.public-play')
            <footer class="flex items-center justify-between gap-3 border-t border-stone-200 p-4 dark:border-stone-700">
                <span
                    class="text-sm"
                    x-text="previewInventorySelection.length+' selected'"
                ></span><button
                    type="button"
                    class="btn-primary"
                    @click="confirmPreviewInventory()"
                >Start test</button><button
                    type="button"
                    class="btn-secondary"
                    @click="confirmPreviewInventory(true)"
                >Start test (skip first video)</button>
            </footer>
        </div>
    </dialog>
</template>
