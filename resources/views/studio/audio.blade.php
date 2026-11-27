<section
    x-show="tab==='audio'"
    x-cloak
    @vsg-editor-tab.window="if($event.detail.tab!=='audio')$el.querySelectorAll('audio').forEach(a=>a.pause())"
    x-data="storyAudio(@js(route('audio.index', $story)))"
    class="space-y-6"
>
    <header>
        <div class="flex flex-wrap items-center justify-between gap-3">

            <div class="flex flex-wrap gap-2"><button
                    type="button"
                    class="btn-secondary"
                    @click="error='';$refs.audioSearchDialog.showModal()"
                >Search sounds</button><button
                    type="button"
                    class="btn-primary"
                    @click="error='';$el.closest('section').querySelectorAll('audio').forEach(a=>a.pause());$refs.audioGenerateDialog.showModal()"
                >Generate audio</button><button
                    type="button"
                    class="btn-secondary"
                    :disabled="uploading"
                    @click="$refs.audioUpload.click()"
                    x-text="uploading?'Uploading…':'Upload audio'"
                ></button><input
                    x-ref="audioUpload"
                    type="file"
                    class="hidden"
                    accept=".mp3,.wav,.ogg,.flac,.m4a,.aac,audio/mpeg,audio/wav,audio/ogg,audio/flac,audio/mp4,audio/aac"
                    @change="uploadAudio($event)"
                    :disabled="uploading"
                ></div>
        </div>

    </header>
    @include('studio.audio-pending')
    <p
        x-show="error"
        x-text="error"
        role="alert"
        class="text-sm text-red-600"
    ></p>
    <dialog
        x-ref="audioSearchDialog"
        class="audio-workspace-dialog rounded-2xl border bg-white p-6 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        @close="$el.querySelectorAll('audio').forEach(a=>a.pause())"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-serif text-xl">Search sounds</h2><button
                type="button"
                class="btn-secondary"
                @click="$refs.audioSearchDialog.close()"
            >Close</button>
        </header>
        <div class="audio-dialog-content">
            <p
                x-show="error"
                x-text="error"
                role="alert"
                class="mb-3 text-sm text-red-700"
            ></p>
            <div class="min-w-0 space-y-3">
                <h3 class="text-xs font-semibold">Freesound</h3>
                <form
                    @submit.prevent="$busy($event.submitter, async () => (search(1)))"
                    class="flex flex-wrap items-end gap-2"
                ><label class="field min-w-0 flex-1 basis-40">Search<input
                            x-model="query"
                            placeholder="Rain, creaking door, footsteps..."
                            maxlength="200"
                            required
                        ></label><label class="field w-28">Max seconds<input
                            type="number"
                            min="0.1"
                            max="3600"
                            step="0.1"
                            x-model="maxDuration"
                            placeholder="Any"
                            :disabled="searching"
                            @change="results=[];page=1;hasMore=false;if(query.trim())search(1)"
                        ></label><button
                        class="btn-primary"
                        :disabled="searching"
                        x-text="searching?'Searching…':'Search'"
                    ></button></form>
                <div class="flex flex-wrap gap-2"><template
                        x-for="q in searches"
                        :key="q"
                    ><button
                            type="button"
                            class="text-xs underline"
                            @click="$busy($el, async () => { query=q; return await search(1); })"
                            x-text="q"
                        ></button></template></div>
                <p class="text-xs text-stone-500">High-quality MP3 previews. Creator and license credits are saved with
                    each sound.</p>
                <div class="space-y-2"><template
                        x-for="s in results"
                        :key="s.id"
                    >
                        <article
                            class="min-w-0 rounded-lg border border-stone-200 p-3 dark:border-stone-700 space-y-2 text-xs"
                        >
                            <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1"><a
                                    :href="s.source_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="break-words text-xs font-semibold underline"
                                    x-text="s.name"
                                ></a><span
                                    class="text-xs text-stone-500"
                                    x-text="s.creator"
                                ></span><a
                                    :href="s.license"
                                    :title="s.license"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="min-w-0 max-w-40 truncate text-xs underline"
                                    x-text="s.license"
                                ></a><span
                                    class="ml-auto shrink-0 text-xs"
                                    x-text="Number(s.duration).toFixed(1)+'s'"
                                ></span></div>
                            <div class="flex min-w-0 items-center gap-2"><audio
                                    controls
                                    preload="none"
                                    :src="s.preview_url"
                                    class="h-10 min-w-0 flex-1"
                                    @play="$el.closest('section').querySelectorAll('audio').forEach(a=>{if(a!==$el)a.pause()})"
                                ></audio><button
                                    type="button"
                                    class="btn-secondary shrink-0 !px-3 !py-2 !text-xs"
                                    :disabled="busy || added(s.id)"
                                    @click="$busy($el, async () => (add(s.id)))"
                                    :aria-label="'Add ' + s.name + ' to project'"
                                    x-text="added(s.id)?'Added':'Add'"
                                ></button></div>
                        </article>
                    </template></div>
                <div
                    x-show="results.length"
                    class="flex items-center justify-between text-sm"
                ><button
                        type="button"
                        class="underline"
                        :disabled="searching || page === 1"
                        @click="$busy($el, async () => (search(page-1)))"
                    >Previous</button><span x-text="'Page '+page+' · '+count+' results'"></span><button
                        type="button"
                        class="underline"
                        :disabled="searching || !hasMore"
                        @click="$busy($el, async () => (search(page+1)))"
                    >Next</button></div>
            </div>
        </div>
    </dialog>
    <dialog
        x-ref="audioGenerateDialog"
        class="audio-workspace-dialog rounded-2xl border bg-white p-6 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-serif text-xl">Generate audio</h2><button
                type="button"
                class="btn-secondary"
                :disabled="generating"
                @click="$refs.audioGenerateDialog.close()"
            >Close</button>
        </header>
        <div class="audio-dialog-content">
            <p
                x-show="error"
                x-text="error"
                role="alert"
                class="mb-3 text-sm text-red-700"
            ></p>
            <div class="min-w-0 space-y-3"><label class="field">Model<select
                        x-model="model"
                        @change="seconds=Math.min(seconds,model==='minimax/music-3'?300:(model==='cassetteai/sound-effects-generator'?30:47))"
                    >
                        <option value="minimax/music-3">MiniMax Music 3</option>
                        <option value="fal-ai/stable-audio">Stable Audio</option>
                        <option value="cassetteai/sound-effects-generator">CassetteAI sound effects</option>
                    </select></label>
                <form
                    id="audio-generation-form"
                    @submit.prevent="$busy($event.submitter, async () => (generate()))"
                    class="space-y-3"
                ><label class="field">Name<input
                            x-model="name"
                            required
                            maxlength="160"
                            placeholder="A heavy wooden door opening"
                        ></label><label class="field">Sound prompt
                        <textarea
                            x-model="prompt"
                            required
                            maxlength="3000"
                            rows="4"
                            placeholder="Describe the sound, atmosphere, and timing..."
                        ></textarea>
                    </label>
                    <label
                        class="field"
                        x-show="model==='minimax/music-3'"
                    >Lyrics / structure
                        <textarea
                            x-model="lyrics"
                            rows="5"
                            maxlength="10000"
                        ></textarea>
                        <span class="text-xs font-normal">Use [instrumental] for background music, or add lyrics with
                            each
                            section tag on its own line. $0.002 per second.</span>
                    </label><label class="field">Duration (seconds)<input
                            type="number"
                            min="1"
                            :max="model === 'minimax/music-3' ? 300 : (model === 'cassetteai/sound-effects-generator' ? 30 :
                                47)"
                            x-model="seconds"
                            required
                        ></label>
                </form>
            </div>
        </div>
        <footer class="dialog-actions"><button
                type="submit"
                form="audio-generation-form"
                class="btn-primary"
                :disabled="generating"
                x-text="generating?'Submitting…':'Generate sound'"
            ></button></footer>
    </dialog>
    <section class="min-w-0 rounded-xl border border-stone-200 p-4 dark:border-stone-700 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <select
                class="audio-library-select"
                aria-label="Filter project sounds"
                :value="categoryFilter"
                @change="categoryFilter=$event.target.value;$el.closest('section').querySelectorAll('audio').forEach(a=>a.pause())"
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
            </select><button
                type="button"
                class="text-xs underline"
                @click="$refs.audioCategoriesDialog.showModal()"
            >Edit categories</button>
        </div>
        <template x-teleport="body">
            <dialog
                x-ref="audioCategoriesDialog"
                class="image-picker-dialog rounded-2xl border bg-white p-5 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
                aria-label="Edit audio categories"
                @click="if($event.target===$el)$el.close()"
            >
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-serif text-xl">Audio categories</h2><button
                        type="button"
                        class="btn-secondary"
                        @click="$refs.audioCategoriesDialog.close()"
                    >Close</button>
                </header>
                <div class="max-h-[60vh] space-y-3 overflow-y-auto"><template
                        x-for="(name,key) in categories"
                        :key="key"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                x-show="editingCategory!==key"
                                class="min-w-0 flex-1 break-words"
                                x-text="name"
                            ></span>
                            <label
                                x-show="editingCategory===key"
                                class="field min-w-0 flex-1"
                            ><span class="sr-only">Category name</span><input
                                    x-model="categoryNameDraft"
                                    maxlength="80"
                                    :disabled="categoryBusy"
                                    @keydown.enter.prevent="finishCategoryEdit(key)"
                                    @keydown.escape.stop.prevent="editingCategory=null"
                                ></label>
                            <button
                                type="button"
                                class="btn-secondary shrink-0"
                                :disabled="categoryBusy || (editingCategory === key && !categoryNameDraft.trim())"
                                :aria-label="editingCategory === key ? 'Save category name' : 'Rename ' + name"
                                :title="editingCategory === key ? 'Save' : 'Rename'"
                                @click="$busy($el, async () => (editingCategory===key ? finishCategoryEdit(key) : editCategory(key)))"
                            ><span
                                    aria-hidden="true"
                                    x-text="editingCategory===key ? '\u2713' : '\u270E'"
                                ></span></button>
                            <button
                                type="button"
                                class="btn-secondary shrink-0"
                                :disabled="categoryBusy || categoryUsed(key)"
                                :aria-label="'Delete ' + name"
                                :title="categoryUsed(key) ? 'Category is in use' : 'Delete empty category'"
                                @click="$busy($el, async () => (deleteCategory(key)))"
                            ><svg
                                    aria-hidden="true"
                                    width="16"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7" />
                                </svg></button>
                        </div>
                    </template>
                    <div class="flex gap-2"><input
                            aria-label="New category"
                            x-model="newCategory"
                            maxlength="80"
                            placeholder="New category"
                        ><button
                            type="button"
                            class="btn-secondary"
                            @click="$busy($el, async () => (addCategory()))"
                        >Add</button></div>
                </div>
            </dialog>
        </template>
        <p
            x-show="!filteredSounds.length && !libraryJobs.length"
            class="text-sm text-stone-500"
            x-text="sounds.length?'No sounds in this category.':'No sounds yet.'"
        ></p>
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <template
                x-for="job in libraryJobs"
                :key="'job-' + job.id"
            >
                <article class="min-w-0 rounded-xl border border-stone-200 p-4 dark:border-stone-700 space-y-2">
                    <h4
                        class="text-sm font-semibold"
                        x-text="job.name"
                    ></h4>
                    <p
                        class="text-xs"
                        role="status"
                        x-text="job.status"
                    ></p>
                    <p
                        class="text-xs"
                        x-show="job.queue_position != null"
                        x-text="'Queue position '+job.queue_position"
                    ></p>
                    <p
                        class="whitespace-pre-wrap text-xs"
                        x-text="job.prompt"
                    ></p>
                    <p
                        class="text-sm text-red-600"
                        x-show="job.error"
                        x-text="job.error"
                        role="alert"
                    ></p>
                </article>
            </template><template
                x-for="s in filteredSounds"
                :key="s.id"
            >
                <article class="min-w-0 rounded-xl border border-stone-200 p-4 dark:border-stone-700 space-y-2">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-xs">
                        <h4
                            class="break-words text-xs font-semibold"
                            x-text="s.name"
                        ></h4><span
                            x-show="s.creator"
                            class="text-xs text-stone-500"
                            x-text="s.creator"
                        ></span><a
                            x-show="s.license"
                            :href="s.license"
                            :title="s.license"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="max-w-40 truncate text-xs underline"
                        >License</a><a
                            x-show="s.source_url"
                            :href="s.source_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs underline"
                        >Source</a><span
                            class="ml-auto text-xs"
                            x-text="s.provider==='freesound'?'Freesound':s.provider==='upload'?'Uploaded':'AI generated'"
                        ></span>
                    </div>
                    <div class="flex items-center gap-3"><select
                            class="audio-library-select"
                            aria-label="Sound category"
                            :value="s.category || 'effects'"
                            @change="categorize(s,$event.target.value)"
                        ><template
                                x-for="(name,key) in categories"
                                :key="key"
                            >
                                <option
                                    :value="key"
                                    :selected="key === (s.category || 'effects')"
                                    x-text="name"
                                ></option>
                            </template></select><button
                            type="button"
                            class="text-xs text-red-700 underline dark:text-red-400"
                            @click="$busy($el, async () => (deleteSound(s)))"
                        >Delete audio</button></div><audio
                        controls
                        preload="none"
                        :src="s.url"
                        class="w-full"
                    ></audio>
                    <p
                        x-show="s.prompt"
                        class="text-xs whitespace-pre-wrap"
                        x-text="s.prompt"
                    ></p>
                </article>
            </template>
        </div>
    </section>
</section>
