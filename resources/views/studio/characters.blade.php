<section
    x-data="characterLibrary(@js(['url' => route('characters.index', $story), 'uploadUrl' => route('assets.store', $story), 'pageId' => $page->id]))"
    class="space-y-4"
    @input.stop
    @change.stop
>
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-serif text-xl">Characters</h2>
            <p class="text-sm text-stone-500">Keep people, creatures, and recurring characters consistent with appearance
                notes and reference views.</p>
        </div>
        <div class="flex gap-2"><button
                type="button"
                class="btn-secondary"
                @click="$busy($el, async () => (load()))"
                :disabled="loading"
            >Refresh</button><button
                type="button"
                class="btn-primary"
                @click="edit()"
            >Create character</button></div>
    </header>
    <p
        x-show="error"
        x-text="error"
        role="alert"
        class="text-sm text-red-600"
    ></p>
    <p
        x-show="!loading && !characters.length"
        class="text-sm"
    >Create a character, then upload views, choose project images, or prepare a new reference image.</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <template
            x-for="character in characters"
            :key="character.id"
        >
            <article class="space-y-3 rounded-xl border p-4">
                <h3
                    class="font-semibold"
                    x-text="character.name"
                ></h3>
                <p
                    class="whitespace-pre-wrap text-sm"
                    x-text="character.description"
                ></p>
                <div class="flex gap-2 overflow-x-auto"><template
                        x-for="(view,index) in character.views"
                        :key="index"
                    >
                        <div class="w-24 shrink-0"><img
                                x-show="view.url"
                                :src="$thumbnail(view.url)"
                                :alt="character.name + ' · ' + view.label"
                                class="h-24 w-24 rounded object-contain"
                                loading="lazy"
                            >
                            <p
                                class="text-xs"
                                x-text="view.label"
                            ></p><span
                                x-show="!view.url"
                                class="text-xs"
                                x-text="view.status"
                            ></span>
                        </div>
                    </template></div>
                <div class="flex gap-3"><button
                        type="button"
                        class="btn-secondary"
                        @click="edit(character)"
                    >Edit character & views</button><button
                        type="button"
                        class="text-xs text-red-600 underline"
                        @click="$busy($el, async () => (remove(character)))"
                        :disabled="busy"
                    >Delete</button></div>
            </article>
        </template>
    </div>
    <dialog
        x-ref="characterDialog"
        class="image-form-dialog dialog-panel rounded-xl bg-white p-5 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        @cancel="if(busy)$event.preventDefault()"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2
                class="font-serif text-xl"
                x-text="draft?.id?'Edit character':'Create character'"
            ></h2><button
                type="button"
                class="btn-secondary"
                @click="$refs.characterDialog.close()"
                :disabled="busy"
            >Cancel</button>
        </header>
        <div class="dialog-scroll-body space-y-4">
            <p
                x-show="error"
                x-text="error"
                role="alert"
                class="text-sm text-red-600"
            ></p>
            <template x-if="draft">
                <div class="space-y-4">
                    <label class="field">Name<input
                            x-model="draft.name"
                            maxlength="160"
                            :disabled="busy"
                            placeholder="Mira, the lighthouse keeper"
                        ></label>
                    <label class="field">Appearance & identity
                        <textarea
                            x-model="draft.description"
                            maxlength="5000"
                            rows="4"
                            :disabled="busy"
                            placeholder="Describe face, hair, build, clothing, markings, and details that must remain consistent."
                        ></textarea>
                    </label>
                    <p class="text-sm">Select specific character views using Characters in the image dialog. Chat can
                        use saved profiles and view IDs when preparing image prompts.</p>
                    <div class="flex flex-wrap gap-2"><button
                            type="button"
                            class="btn-secondary"
                            @click="$refs.characterUpload.click()"
                            :disabled="busy || draft.views.length >= 16"
                        >Upload views</button><input
                            x-ref="characterUpload"
                            type="file"
                            multiple
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="upload($event)"
                        ><button
                            type="button"
                            class="btn-secondary"
                            @click="assetSearch='';$refs.characterAssetPicker.showModal()"
                            :disabled="busy || draft.views.length >= 16"
                        >Choose project images</button></div>
                    <div class="grid gap-3 sm:grid-cols-2"><template
                            x-for="(view,index) in draft.views"
                            :key="index"
                        >
                            <div class="space-y-2 rounded border p-3">
                                <img
                                    x-show="view.url"
                                    :src="$thumbnail(view.url)"
                                    :alt="view.label"
                                    class="h-40 w-full object-contain"
                                    loading="lazy"
                                >
                                <label class="field">View label<input
                                        x-model="view.label"
                                        maxlength="160"
                                        :disabled="busy"
                                        placeholder="Front, profile, full body…"
                                    ></label>
                                <p
                                    x-show="!view.url"
                                    class="text-xs"
                                    x-text="view.status"
                                ></p>
                                <a
                                    x-show="view.image_url"
                                    :href="view.image_url"
                                    class="text-xs underline"
                                >Open image form</a>
                                <button
                                    type="button"
                                    class="text-xs text-red-600 underline"
                                    @click="draft.views.splice(index,1)"
                                    :disabled="busy"
                                >Remove view</button>
                            </div>
                        </template></div>
                    <div class="space-y-2 rounded border p-3"><label class="field">New reference view<input
                                x-model="viewLabel"
                                maxlength="160"
                                :disabled="busy"
                                placeholder="Full body, left profile, neutral expression"
                            ></label><button
                            type="button"
                            class="btn-secondary"
                            @click="$busy($el, async () => (prepareView()))"
                            :disabled="busy || !draft.name.trim() || !viewLabel.trim() || draft.views.length >= 16"
                        >Save & prepare image prompt</button>
                        <p class="text-xs">Opens the image form for review. Generation starts only when you click
                            Generate. The finished image will become a character view.</p>
                    </div>
                </div>
            </template>
        </div>
        <footer class="dialog-actions flex justify-end"><button
                type="button"
                class="btn-primary"
                @click="$busy($el, async () => (save()))"
                :disabled="busy || !draft?.name?.trim()"
                x-text="busy?'Saving…':'Save character'"
            ></button></footer>
    </dialog>
    <dialog
        x-ref="characterAssetPicker"
        class="image-form-dialog dialog-panel rounded-xl bg-white p-5 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-serif text-xl">Choose character views</h2><button
                type="button"
                class="btn-secondary"
                @click="$refs.characterAssetPicker.close()"
            >Done</button>
        </header>
        <div class="dialog-scroll-body space-y-3"><label class="field">Search images<input
                    x-model="assetSearch"></label>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><template
                    x-for="asset in assets.filter(a=>a.name.toLowerCase().includes(assetSearch.toLowerCase()))"
                    :key="asset.id"
                ><button
                        type="button"
                        class="rounded border p-2 text-left"
                        @click="addView(asset)"
                        :disabled="draft?.views.length >= 16 || draft?.views.some(v => v.asset_id === asset.id)"
                    ><img
                            :src="$thumbnail(asset.url)"
                            :alt="asset.name"
                            loading="lazy"
                            class="h-32 w-full object-contain"
                        ><span
                            class="text-xs"
                            x-text="asset.name"
                        ></span><span
                            x-show="draft?.views.some(v=>v.asset_id===asset.id)"
                            class="text-xs"
                        > · Added</span></button></template></div>
        </div>
    </dialog>
</section>
