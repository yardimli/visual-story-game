<section
    x-show="tab==='video'"
    x-cloak
    x-data="storyVideo(@js(route('videos.index', $story)), @js(route('assets.store', $story)))"
    class="space-y-5"
>
    <header class="flex flex-wrap items-center gap-3">
        <button
            type="button"
            class="btn-primary"
            @click="createVideo()"
            :disabled="busy"
        >Create video</button>
        <button
            type="button"
            class="btn-secondary"
            :disabled="busy || !jobs.some(j=>j.status==='draft')"
            @click="queueAll()"
        >Generate all pending</button>
    </header>
    <p
        x-show="error"
        x-text="error"
        role="alert"
        class="rounded-lg border border-red-400 p-3 text-red-700"
    ></p>
    <p
        x-show="notice"
        x-text="notice"
        role="status"
        class="text-sm"
    ></p>
    <dialog
        x-ref="videoFormDialog"
        class="image-form-dialog dialog-panel rounded-2xl border bg-white p-6 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        @cancel="if(busy)$event.preventDefault()"
    >
        <form
            x-ref="form"
            @submit.prevent="save(true)"
            class="dialog-panel-form min-w-0"
        >
            <header class="flex items-center justify-between gap-3">
                <h3
                    class="font-serif text-xl"
                    x-text="editing?'Edit pending video':'Create video'"
                ></h3><button
                    type="button"
                    class="btn-secondary"
                    :disabled="busy"
                    @click="$refs.videoFormDialog.close()"
                >Close</button>
            </header>
            <div class="dialog-scroll-body space-y-4">
                <p
                    x-show="error"
                    x-text="error"
                    role="alert"
                    class="text-sm text-red-700"
                ></p>
                <label class="field">Name<input
                        type="text"
                        x-model="form.name"
                        required
                        maxlength="160"
                    ></label>
                <label class="field">Video engine<select
                        x-model="form.engine"
                        @change="selectEngine()"
                        required
                    ><template
                            x-for="[id, engine] in Object.entries(engines)"
                            :key="id"
                        >
                            <option
                                :value="id"
                                x-text="engine.label"
                            ></option>
                        </template></select></label>
                <p class="text-sm" x-text="engines[form.engine]?.description"></p>
                <div class="grid grid-cols-2 gap-4">
                    <template
                        x-for="frame in ['first','last']"
                        :key="frame"
                    >
                        <div
                            x-show="frame==='first' || engines[form.engine]?.supports_last_frame"
                            class="min-w-0 space-y-2"
                        >
                            <div class="field"><span
                                    x-text="frame==='first'?'First frame':'Last frame (optional)'"></span>
                                <button
                                    type="button"
                                    class="btn-secondary w-full text-left"
                                    @click="framePicker=frame"
                                    x-text="asset(form[frame+'_asset_id'])?.name || (frame==='first'?'Choose image':'Choose image (optional)')"
                                ></button>
                                <button
                                    type="button"
                                    class="text-xs underline"
                                    x-show="frame==='last' && form.last_asset_id"
                                    @click="form.last_asset_id=''"
                                >Remove last frame</button>
                            </div>
                            <button
                                type="button"
                                x-show="asset(form[frame+'_asset_id'])"
                                @click="zoom=asset(form[frame+'_asset_id'])?.url"
                                class="block w-full"
                                :aria-label="'Enlarge ' + frame + ' frame'"
                            ><img
                                    loading="lazy"
                                    decoding="async"
                                    :src="$thumbnail(asset(form[frame + '_asset_id'])?.url)"
                                    :alt="frame + ' frame'"
                                    class="h-40 w-full object-contain"
                                ></button>
                            <label class="field text-xs">Upload image<input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    :disabled="busy"
                                    @change="upload($event,frame)"
                                ></label>
                        </div>
                    </template>
                </div>
                <div x-show="engines[form.engine]?.max_references" class="space-y-3 rounded-lg border border-stone-300 p-3">
                    <h4 class="font-semibold">Reference images</h4>
                    <p class="text-sm">Use these for character appearance, objects, or style. First and last frames above control where the video starts and ends. Describe each reference in the prompt using its Picture tag. For several views of one character, say they show the same person.</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <template x-for="(id,index) in form.reference_asset_ids" :key="id">
                            <div class="space-y-2 rounded border border-stone-300 p-2">
                                <strong class="text-sm" x-text="'<Picture '+(index+1)+'>'"></strong>
                                <button type="button" class="block w-full" @click="zoom=asset(id)?.url" :aria-label="'Enlarge reference '+(index+1)">
                                    <img :src="$thumbnail(asset(id)?.url)" :alt="asset(id)?.name || 'Reference image'" class="h-28 w-full object-contain">
                                </button>
                                <p class="text-xs" x-text="asset(id)?.name || 'Image unavailable'"></p>
                                <button type="button" class="text-xs underline" @click="form.reference_asset_ids.splice(index,1)">Remove</button>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs" x-show="form.reference_asset_ids.length">Picture numbers follow this order. After removing an image, check any Picture tags in your prompt.</p>
                    <button type="button" class="btn-secondary" @click="framePicker='reference'" :disabled="form.reference_asset_ids.length >= (engines[form.engine]?.max_references || 0)">Add reference image</button>
                    <label class="field text-xs">Upload reference image<input type="file" accept="image/png,image/jpeg,image/webp" :disabled="busy || form.reference_asset_ids.length >= (engines[form.engine]?.max_references || 0)" @change="upload($event,'reference')"></label>
                    <p class="text-xs" x-text="form.reference_asset_ids.length+' / '+(engines[form.engine]?.max_references || 0)+' references'"></p>
                </div>
                <label class="field">Prompt
                    <textarea
                        x-model="form.prompt"
                        rows="5"
                        required
                        maxlength="10000"
                        placeholder="Describe the movement and what changes during this video."
                    ></textarea>
                </label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <template
                        x-for="[key, parameter] in Object.entries(engines[form.engine]?.parameters || {})"
                        :key="form.engine + '-' + key"
                    >
                        <label class="field min-w-0"><span x-text="parameter.label"></span>
                            <template x-if="parameter.type==='select'"><select x-model="form.parameters[key]"><template
                                        x-for="[value,label] in Object.entries(parameter.options)"
                                        :key="value"
                                    >
                                        <option
                                            :value="value"
                                            x-text="label"
                                        ></option>
                                    </template></select></template>
                            <template x-if="parameter.type!=='select'"><input
                                    type="number"
                                    x-model.number="form.parameters[key]"
                                    :min="parameter.min"
                                    :max="parameter.max"
                                    :step="parameter.step"
                                    required
                                ></template>
                        </label>
                    </template>
                </div>
            </div>
            <footer class="dialog-actions flex flex-wrap gap-2"><button
                    class="btn-primary"
                    :disabled="busy"
                    x-text="busy?'Saving…':'Queue video'"
                ></button><button
                    type="button"
                    class="btn-secondary"
                    :disabled="busy"
                    @click="save(false)"
                >Save pending draft</button><button
                    type="button"
                    x-show="editing"
                    class="btn-secondary"
                    @click="$refs.videoFormDialog.close()"
                >Cancel edit</button></footer>
        </form>
    </dialog>
    <div class="min-w-0 space-y-4">

        <p
            x-show="!jobs.length"
            class="text-sm"
        >No videos yet. Create one here or ask the chat to prepare a video draft.</p>
        <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
            <template
                x-for="job in jobs"
                :key="job.id"
            >
                <article class="space-y-3 rounded-xl border border-stone-300 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4
                            class="font-semibold"
                            x-text="job.name"
                        ></h4><span
                            class="text-xs"
                            x-text="status(job)"
                        ></span>
                    </div>
                    <p
                        class="text-xs"
                        x-text="(engines[job.engine]?.label || job.engine)+' · '+Object.entries(job.parameters).map(([key,value])=>(engines[job.engine]?.parameters[key]?.label || key)+': '+value).join(' · ')"
                    ></p>
                    <div class="grid grid-cols-2 gap-3">
                        <template
                            x-for="frame in ['first','last']"
                            :key="frame"
                        >
                            <div
                                x-show="job[frame+'_frame_url']"
                                class="min-w-0"
                            >
                                <p
                                    class="mb-1 text-xs"
                                    x-text="frame==='first'?'First frame':'Last frame'"
                                ></p>
                                <button
                                    type="button"
                                    class="block w-full"
                                    @click="zoom=job[frame+'_frame_url']"
                                    :aria-label="'Enlarge ' + frame + ' frame of ' + job.name"
                                >
                                    <img
                                        :src="$thumbnail(job[frame + '_frame_url'])"
                                        :alt="frame + ' frame of ' + job.name"
                                        loading="lazy"
                                        class="h-32 w-full rounded-lg object-contain"
                                    >
                                </button>
                            </div>
                        </template>
                    </div>
                    <p
                        class="text-sm whitespace-pre-wrap"
                        x-text="job.prompt"
                    ></p>
                    <template x-if="job.url"><video
                            :src="job.url"
                            controls
                            preload="metadata"
                            class="w-full"
                        ></video></template>
                    <div
                        x-show="!['draft','completed','failed'].includes(job.status)"
                        class="flex items-center gap-3 text-sm"
                        role="status"
                    ><span
                            class="h-5 w-5 animate-spin rounded-full border-2 border-stone-400 border-t-transparent"></span><span
                            x-text="job.worker_offline?'Worker disconnected; waiting for it to resume.':status(job)"
                        ></span><span
                            x-show="job.progress>0"
                            x-text="job.progress+'%'"
                        ></span></div>
                    <p
                        x-show="job.started_at"
                        class="text-xs"
                    ><span x-text="'Started '+new Date(job.started_at).toLocaleString()"></span> · <span
                            x-text="elapsed(job)"
                        ></span></p>
                    <p
                        x-show="job.error || job.sync_error"
                        x-text="job.error || job.sync_error"
                        role="alert"
                        class="text-sm text-red-700"
                    ></p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            x-show="job.status==='draft'"
                            class="btn-primary"
                            :disabled="busy"
                            @click="edit(job)"
                        >Generate</button>
                        <button
                            type="button"
                            x-show="job.status==='draft'"
                            class="btn-secondary"
                            :disabled="busy"
                            @click="edit(job)"
                        >Edit</button>
                        <button
                            type="button"
                            x-show="['completed','failed'].includes(job.status)"
                            class="btn-secondary"
                            :disabled="busy"
                            @click="edit(job,true)"
                        >Create another version</button>
                        <a
                            x-show="job.url"
                            :href="job.url"
                            download
                            class="btn-secondary"
                        >Download MP4</a>
                        <button
                            type="button"
                            x-show="['draft','completed','failed'].includes(job.status)"
                            class="btn-secondary"
                            :disabled="busy"
                            @click="remove(job)"
                        >Delete</button>
                    </div>
                </article>
            </template>
        </div>
    </div>
    <dialog
        x-effect="if(framePicker && !$el.open)$el.showModal();else if(!framePicker && $el.open)$el.close()"
        @close="framePicker=null"
        @click.self="framePicker=null"
        class="image-form-dialog rounded-2xl border bg-white p-0 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        role="dialog"
        aria-modal="true"
        aria-label="Choose video frame"
    >
        <div class="flex max-h-[85vh] w-full max-w-5xl flex-col rounded-xl bg-white p-5 dark:bg-stone-900">
            <header class="mb-4 flex flex-wrap items-center gap-3">
                <h3
                    class="font-serif text-xl"
                    x-text="framePicker==='reference'?'Choose reference image':(framePicker==='first'?'Choose first frame':'Choose last frame')"
                ></h3>
                <label class="field"><span class="sr-only">Image category</span><select
                        x-model="imageCategoryFilter"
                    >
                        <option value="">All categories</option>
                        <template
                            x-for="[key,name] in Object.entries(imageCategories)"
                            :key="key"
                        >
                            <option
                                :value="key"
                                x-text="name"
                            ></option>
                        </template>
                    </select></label>
                <button
                    type="button"
                    class="btn-secondary ml-auto"
                    @click="framePicker=null"
                >Close</button>
            </header>
            <div class="min-h-0 overflow-y-auto">
                <p
                    x-show="!frameImages.length"
                    class="p-4 text-sm"
                >No images in this category.</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <template
                        x-for="image in frameImages"
                        :key="image.id"
                    >
                        <button
                            type="button"
                            @click="chooseFrame(image)"
                            class="min-w-0 rounded-lg border border-stone-300 p-2 text-left"
                            :class="form[framePicker + '_asset_id'] == image.id ? 'ring-2 ring-amber-500' : ''"
                        >
                            <img
                                :src="$thumbnail(image.url)"
                                :alt="image.name"
                                loading="lazy"
                                class="h-40 w-full object-contain"
                            >
                            <span
                                class="mt-2 block break-words text-sm"
                                x-text="image.name"
                            ></span><span
                                class="mt-1 block text-xs"
                                x-show="image.composition"
                                x-text="image.composition?(image.composition.description || 'Combined: '+image.composition.sources.map(s=>s.name).join(' + ')):''"
                            ></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </dialog>
    <dialog
        x-effect="if(zoom && !$el.open)$el.showModal();else if(!zoom && $el.open)$el.close()"
        @close="zoom=null"
        @click.self="zoom=null"
        class="image-form-dialog rounded-xl bg-stone-900 p-8"
        role="dialog"
        aria-modal="true"
        aria-label="Frame preview"
    ><button
            type="button"
            @click="zoom=null"
            class="absolute right-5 top-5 btn-secondary"
        >Close</button><img
            loading="lazy"
            decoding="async"
            :src="zoom"
            alt="Video frame preview"
            class="max-h-full max-w-full object-contain"
        ></dialog>
</section>
