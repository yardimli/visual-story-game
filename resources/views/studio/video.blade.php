<section
    x-show="tab==='video'"
    x-cloak
    x-data="storyVideo(@js(route('videos.index', $story)), @js(route('assets.store', $story)))"
    class="space-y-5"
>
    <header>
        <h2 class="font-serif text-2xl">Video</h2>
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
    <div class="grid gap-5 xl:grid-cols-2">
        <form
            x-ref="form"
            @submit.prevent="save(true)"
            class="min-w-0 space-y-4 rounded-xl border border-stone-300 p-5"
        >
            <h3
                class="font-serif text-xl"
                x-text="editing?'Edit pending video':'Create video'"
            ></h3>
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
            <div class="grid grid-cols-2 gap-4">
                <template
                    x-for="frame in ['first','last']"
                    :key="frame"
                >
                    <div
                        x-show="frame==='first' || engines[form.engine]?.supports_last_frame"
                        class="min-w-0 space-y-2"
                    >
                        <label class="field"><span
                                x-text="frame==='first'?'First frame':'Last frame (optional)'"></span>
                            <select
                                x-model="form[frame+'_asset_id']"
                                :required="frame === 'first'"
                            >
                                <option
                                    value=""
                                    x-text="frame==='first'?'Choose image':'No last frame'"
                                ></option><template
                                    x-for="image in assets"
                                    :key="image.id"
                                >
                                    <option
                                        :value="image.id"
                                        x-text="image.name"
                                    ></option>
                                </template>
                            </select>
                        </label>
                        <button
                            type="button"
                            x-show="asset(form[frame+'_asset_id'])"
                            @click="zoom=asset(form[frame+'_asset_id'])?.url"
                            class="block w-full"
                            :aria-label="'Enlarge ' + frame + ' frame'"
                        ><img
                                :src="asset(form[frame + '_asset_id'])?.url"
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
            <div class="flex flex-wrap gap-2"><button
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
                    @click="reset()"
                >Cancel edit</button></div>
        </form>
        <div class="min-w-0 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-serif text-xl">Project videos</h3><button
                    type="button"
                    class="btn-secondary"
                    :disabled="busy || !jobs.some(j=>j.status==='draft')"
                    @click="queueAll()"
                >Generate all pending</button>
            </div>
            <p
                x-show="!jobs.length"
                class="text-sm"
            >No videos yet. Create one here or ask the chat to prepare a video draft.</p>
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
                            @click="queue(job)"
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
    <div
        x-show="zoom"
        x-cloak
        @click.self="zoom=null"
        @keydown.escape.window="zoom=null"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-8"
        role="dialog"
        aria-modal="true"
        aria-label="Frame preview"
    ><button
            type="button"
            @click="zoom=null"
            class="absolute right-5 top-5 btn-secondary"
        >Close</button><img
            :src="zoom"
            alt="Video frame preview"
            class="max-h-full max-w-full object-contain"
        ></div>
</section>
