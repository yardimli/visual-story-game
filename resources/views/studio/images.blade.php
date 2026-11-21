<x-studio-layout
    :title="'Image · ' . $story->title"
    :story="$story"
    :page="$page"
>
    <div
        x-data="studioAutosave({{ $story->revision }})"
        class="image-workspace story-editor-workspace"
    >
        @include('studio.story-header', ['generation' => true])
        <section
            class="image-workspace-grid"
            x-data="sceneEditor(@js(['categoriesUrl' => route('images.categories', $story), 'url' => route('visuals.show', [$story, $page, 'all_images' => 1]), 'orderUrl' => route('images.order', $story), 'deleteDraftBase' => url('/stories/' . $story->id . '/image-drafts'), 'generate' => route('images.generate', [$story, $page]), 'moveBase' => url('/stories/' . $story->id . '/pages/' . $page->id . '/images'), 'upload' => route('assets.store', $story), 'model' => $story->image_model, 'generationMode' => true, 'draftUrl' => route('images.draft', [$story, $page]), 'nameUrl' => route('images.name', $story), 'writingModel' => $story->llm_model, 'assetBase' => url('/assets')]))"
        >
            <dialog
                x-ref="imageFormDialog"
                class="image-form-dialog rounded-2xl border bg-white p-6 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
                @click="if($event.target===$el)$el.close()"
            >
                <header class="mb-5 flex items-center justify-between gap-3">
                    <h2
                        class="font-serif text-xl"
                        x-text="selectedComposition?'Saved scene image':'Create image'"
                    ></h2><button
                        type="button"
                        class="btn-secondary"
                        @click="$refs.imageFormDialog.close()"
                    >Close</button>
                </header>
                <p
                    x-show="error"
                    x-text="error"
                    role="alert"
                    class="mb-3 text-sm text-red-700"
                ></p><template x-if="selectedComposition">
                    <div class="space-y-3">
                        <p
                            class="text-sm"
                            x-text="compositionText(selectedComposition)"
                        ></p><img
                            :src="asset(jobs.find(j => j.id === selectedImageId)?.asset_ids?.[0])?.url"
                            class="max-h-[60vh] w-full object-contain"
                            alt="Scene with changed state applied"
                        >
                    </div>
                </template>
                <template x-if="visual && !selectedComposition">
                    <div
                        x-ref="generationForm"
                        class="space-y-3"
                    >@include('studio.image-form')</div>
                </template>
            </dialog>
            <aside
                @vsg-editor-tab.window="unusedImageIds=[]"
                @pageshow.window="if($event.persisted)unusedImageIds=[]"
                class="image-results border-t border-stone-200 bg-stone-50 dark:border-stone-700 dark:bg-stone-950 md:border-r md:border-t-0"
                aria-label="Images"
            >
                <header class="flex items-center justify-between border-b border-stone-200 p-4 dark:border-stone-700">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">

                        <button
                            type="button"
                            class="btn-primary"
                            @click="openImageForm()"
                        >Create image</button>

                        <select
                            class="audio-library-select"
                            aria-label="Filter images by category"
                            x-model="imageCategoryFilter"
                        >
                            <option value="">All categories</option><template
                                x-for="(name,key) in imageCategories"
                                :key="key"
                            >
                                <option
                                    :value="key"
                                    x-text="name"
                                ></option>
                            </template>
                        </select>
                        <button
                            type="button"
                            class="btn-secondary"
                            aria-label="Edit image categories"
                            title="Edit categories"
                            @click="$refs.imageCategoriesDialog.showModal()"
                        ><span aria-hidden="true">&#9998;</span></button>
                    </div><span
                        class="text-xs text-stone-500"
                        x-text="visibleJobs.length"
                    ></span>
                </header>
                <div
                    class="image-results-scroll image-gallery p-3"
                    x-ref="imageResults"
                >
                    <p
                        x-show="!historyJobs.length"
                        class="p-2 text-sm text-stone-500"
                    >Your images and prepared prompts will appear here.</p>
                    <template
                        x-for="job in visibleJobs"
                        :key="job.id"
                    >
                        <article
                            @dragover="overImage(job,$event,$el)"
                            @drop.prevent.stop="dropImage(job.id)"
                            :style="imageDropId === job.id && draggedImageId !== job.id ? (imageDropAfter ?
                                'border-bottom:3px solid #d97706' : 'border-top:3px solid #d97706') : ''"
                            :data-moved="movedImageId === job.id"
                            @animationend.self="if(movedImageId===job.id)movedImageId=null"
                            :class="selectedImageId === job.id ? 'ring-2 ring-amber-500 bg-amber-50 dark:bg-stone-800' :
                                'bg-white dark:bg-stone-900'"
                            @click="if(!$event.target.closest('button,input,select,label,a'))selectImageCard(job)"
                            class="image-history-card min-w-0 self-start rounded-xl border border-stone-200 dark:border-stone-700"
                        >
                            <header
                                class="cursor-pointer px-3 pt-3 pb-[3px] text-sm"
                                :style="(job.asset_ids || []).some(id => unusedImage(id)) ?
                                    'box-shadow:inset 0 0 0 2px #ef4444;border-radius:.75rem' : ''"
                            > <button
                                    type="button"
                                    class="btn-secondary image-order-button float-right ml-2 cursor-grab active:cursor-grabbing"
                                    :draggable="!orderingImages && Number.isInteger(job.id)"
                                    :disabled="orderingImages || !Number.isInteger(job.id)"
                                    :aria-label="'Drag to reorder ' + jobTitle(job)"
                                    title="Drag to reorder; arrow keys move up or down"
                                    @click.stop.prevent
                                    @pointerdown.stop
                                    @dragstart.stop="startImageDrag(job,$event)"
                                    @dragend="endImageDrag()"
                                    @keydown.up.prevent.stop="moveImageOrder(job.id,-1)"
                                    @keydown.down.prevent.stop="moveImageOrder(job.id,1)"
                                >
                                    <svg
                                        aria-hidden="true"
                                        width="16"
                                        height="20"
                                        viewBox="0 0 16 20"
                                        fill="currentColor"
                                    >
                                        <circle
                                            cx="5"
                                            cy="4"
                                            r="1.5"
                                        />
                                        <circle
                                            cx="11"
                                            cy="4"
                                            r="1.5"
                                        />
                                        <circle
                                            cx="5"
                                            cy="10"
                                            r="1.5"
                                        />
                                        <circle
                                            cx="11"
                                            cy="10"
                                            r="1.5"
                                        />
                                        <circle
                                            cx="5"
                                            cy="16"
                                            r="1.5"
                                        />
                                        <circle
                                            cx="11"
                                            cy="16"
                                            r="1.5"
                                        />
                                    </svg>
                                </button><span
                                    class="font-semibold break-words"
                                    x-text="jobTitle(job)"
                                ></span><span
                                    class="ml-1 text-xs"
                                    :class="job.status === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-stone-500'"
                                    x-text="job.status"
                                ></span><span
                                    x-show="job.status==='running' && job.started_at"
                                    class="mt-1 block text-xs tabular-nums text-stone-500 dark:text-stone-400"
                                ><span x-text="'Started '+renderStarted(job)"></span><span class="mx-1">·</span><span
                                        x-text="renderElapsed(job)+' elapsed'"
                                    ></span></span></header>
                            <div class="space-y-1 px-3 pb-3">
                                <div x-show="!jobAssets(job).length"> <select
                                        aria-label="Image category"
                                        class="audio-library-select"
                                        :value="job.category || 'scene'"
                                        :disabled="!!savingImageCategory[job.id]"
                                        @change="await categorizeImage(job,$event.target.value);$el.value=jobs.find(item=>item.id===job.id)?.category || 'scene'"
                                    ><template
                                            x-for="(name,key) in imageCategories"
                                            :key="key"
                                        >
                                            <option
                                                :value="key"
                                                :selected="key === (job.category || 'scene')"
                                                x-text="name"
                                            ></option>
                                        </template></select></div>

                                <button
                                    type="button"
                                    x-show="job.status==='draft'"
                                    class="text-xs text-red-600 underline dark:text-red-400"
                                    @click="deleteImageId=job.id;$refs.deleteImageDialog.showModal()"
                                    :disabled="generating || saving || deletingImage"
                                >Delete image draft</button>
                                <p
                                    x-show="job.status==='draft'"
                                    class="grid aspect-video place-items-center rounded-lg bg-stone-100 text-xs text-stone-500 dark:bg-stone-800"
                                >Image not generated</p>
                                <button
                                    type="button"
                                    x-show="job.localFailure"
                                    class="text-xs underline"
                                    @click="jobs=jobs.filter(item=>item.id!==job.id)"
                                >Dismiss failed request</button>

                                <button
                                    type="button"
                                    x-show="job.status==='failed' && job.retry_url"
                                    class="btn-secondary"
                                    @click="retryImage(job)"
                                    :disabled="generating"
                                    x-text="retryingJob===job.id?'Retrying…':'Try again'"
                                ></button>

                                <p
                                    x-show="job.error"
                                    class="break-words text-xs text-red-600 dark:text-red-400"
                                    x-text="job.error"
                                ></p>
                                <div
                                    x-show="['submitting','queued','running'].includes(job.status)"
                                    class="relative grid aspect-[4/3] w-full place-items-center rounded-lg bg-stone-100 dark:bg-stone-800"
                                    role="status"
                                    :aria-label="job.status === 'submitting' ? 'Submitting image request' :
                                        'Image generation in progress'"
                                >
                                    <span
                                        class="image-render-spinner"
                                        aria-hidden="true"
                                    ></span>
                                    <span
                                        class="absolute inset-x-0 bottom-3 text-center text-xs text-stone-500"
                                        x-text="job.status==='running'?'Rendering…':job.status==='queued'?'Queued…':'Submitting…'"
                                    ></span>
                                </div>
                                <template
                                    x-for="id in jobAssets(job)"
                                    :key="id"
                                >
                                    <div class="space-y-2">
                                        <div class="field image-card-name"><span
                                                class="flex min-w-0 items-baseline gap-1 whitespace-nowrap"
                                            ><label
                                                    :for="'image-name-' + id"
                                                    class="shrink-0"
                                                >Image name</label><span
                                                    class="min-w-0 truncate font-normal text-stone-500"
                                                    :title="job.model"
                                                    x-text="job.composition?'Scene image':job.model"
                                                ></span></span>
                                            <div class="flex min-w-0 items-end gap-2"><input
                                                    class="min-w-0 flex-1"
                                                    :id="'image-name-' + id"
                                                    type="text"
                                                    maxlength="255"
                                                    :value="assetNames[id] ?? asset(id)?.name"
                                                    @input="assetNames[id]=$event.target.value"
                                                    @change="renameAsset(id)"
                                                    @keydown.enter.prevent="$el.blur()"
                                                    :disabled="assetSaving[id]"
                                                ><select
                                                    aria-label="Image category"
                                                    class="audio-library-select"
                                                    :value="job.category || 'scene'"
                                                    :disabled="!!savingImageCategory[job.id]"
                                                    @change="await categorizeImage(job,$event.target.value);$el.value=jobs.find(item=>item.id===job.id)?.category || 'scene'"
                                                ><template
                                                        x-for="(name,key) in imageCategories"
                                                        :key="key"
                                                    >
                                                        <option
                                                            :value="key"
                                                            :selected="key === (job.category || 'scene')"
                                                            x-text="name"
                                                        ></option>
                                                    </template></select></div>
                                        </div>
                                        <div
                                            class="relative"
                                            :class="unusedImage(id) ? 'ring-4 ring-red-500 rounded-lg' : ''"
                                        ><span
                                                x-show="unusedImage(id)"
                                                class="absolute right-1 top-1 z-10 rounded bg-red-700 px-2 py-1 text-xs text-white"
                                            >Unused in scenes</span><img
                                                :src="asset(id)?.url"
                                                :alt="asset(id)?.name"
                                                loading="lazy"
                                                class="max-h-60 w-full rounded-lg object-contain"
                                            ><button
                                                type="button"
                                                class="image-zoom-button"
                                                :aria-label="'Enlarge ' + asset(id)?.name"
                                                @click.stop="$dispatch('vsg-image',asset(id))"
                                                title="Zoom image"
                                            ><x-zoom-icon /></button></div>
                                        <label class="flex items-center gap-2 text-xs"><input
                                                type="checkbox"
                                                :value="String(id)"
                                                x-model="references"
                                                @change="changed()"
                                            >Include as reference</label>
                                        <div class="flex flex-wrap gap-2"><template
                                                x-for="use in asset(id)?.uses||[]"
                                                :key="use.url"
                                            ><a
                                                    class="text-xs underline"
                                                    :href="use.url"
                                                    x-text="(use.kind==='object'?'Object · ':'Scene · ')+use.name"
                                                ></a></template></div>
                                        <div class="flex flex-wrap gap-2"><template
                                                x-for="reference in asset(id)?.plan_references||[]"
                                                :key="reference.id"
                                            ><a
                                                    class="rounded border border-stone-300 px-2 py-1 text-xs dark:border-stone-600"
                                                    :href="reference.url"
                                                    x-text="reference.label"
                                                ></a></template></div>

                                    </div>
                                </template>
                                <button
                                    type="button"
                                    class="text-xs underline"
                                    @click="openImageForm(job)"
                                    x-text="job.composition?'View combined image':'Open image form'"
                                ></button>
                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2"><span
                                                class="text-xs font-semibold"
                                                x-text="job.composition?'Combined images':'Prompt used'"
                                            ></span>
                                            <button
                                                type="button"
                                                x-show="!job.composition && promptLong(job)"
                                                class="text-xs underline"
                                                :aria-expanded="!!expandedPrompts[job.id]"
                                                @click="expandedPrompts[job.id]=!expandedPrompts[job.id]"
                                                x-text="expandedPrompts[job.id]?'Show less':'Show all'"
                                            ></button>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <button
                                                type="button"
                                                class="text-xs underline"
                                                x-show="!job.composition"
                                                @click="copyPrompt(job)"
                                                x-text="copiedPrompt===job.id?'Copied!':'Copy prompt'"
                                            ></button>
                                            <template
                                                x-for="id in jobAssets(job)"
                                                :key="id"
                                            > <button
                                                    type="button"
                                                    class="text-xs underline"
                                                    @click="updateAsset(id,{archived:!asset(id)?.archived})"
                                                    x-show="asset(id)?.archived || !(asset(id)?.uses?.length)"
                                                    :disabled="assetSaving[id]"
                                                    x-text="asset(id)?.archived?'Restore image':'Archive image'"
                                                ></button></template>
                                        </div>
                                    </div>
                                    <p
                                        class="mt-2 whitespace-pre-wrap break-words text-xs text-stone-600 dark:text-stone-300"
                                        x-text="job.composition?compositionText(job.composition):(expandedPrompts[job.id]?job.prompt:promptShort(job))"
                                    ></p>
                                </div>
                            </div>
                        </article>
                    </template>
                    <div class="col-span-full flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                        <button
                            type="button"
                            class="btn-secondary"
                            :disabled="flaggingUnusedImages"
                            @click="flagUnusedImages()"
                            x-text="flaggingUnusedImages?'Checking images…':'Flag images unused in scenes'"
                        ></button>
                        <button
                            type="button"
                            class="text-sm underline"
                            @click="showArchived=!showArchived"
                            x-text="showArchived?'Hide archived images':'Show archived images ('+archivedCount+')'"
                        ></button>
                        <button
                            type="button"
                            class="text-sm underline"
                            :aria-expanded="showFailed"
                            @click="showFailed=!showFailed"
                            x-text="showFailed?'Hide failed generations':'Show failed generations ('+failedCount+')'"
                        ></button>
                    </div>
                </div>
            </aside>
            <dialog
                x-ref="referenceDialog"
                class="reference-dialog rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100"
                aria-labelledby="reference-title"
                @click="if($event.target===$el)$el.close()"
            >
                <div class="flex min-h-0 flex-col">
                    <header
                        class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-stone-200 p-4 dark:border-stone-700"
                    >
                        <h2
                            id="reference-title"
                            class="font-serif text-xl"
                        >Reference images</h2>
                        <label class="field"><span class="sr-only">Image category</span><select
                                x-model="referenceCategoryFilter"
                            >
                                <option value="">All categories</option><template
                                    x-for="(name,key) in imageCategories"
                                    :key="key"
                                >
                                    <option
                                        :value="key"
                                        x-text="name"
                                    ></option>
                                </template>
                            </select></label>

                        <div class="ml-auto flex min-w-0 items-center gap-2"><button
                                type="button"
                                class="btn-secondary"
                                @click="$refs.referenceDialog.close()"
                            >Done</button></div>
                    </header>
                    <div
                        class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4"
                        x-ref="referenceImagesScroll"
                    >
                        <label
                            class="field rounded-lg border border-dashed border-stone-300 p-3 dark:border-stone-600">Upload
                            images<input
                                type="file"
                                multiple
                                accept="image/png,image/jpeg,image/webp"
                                @change="uploadReferences($event.target)"
                                :disabled="uploading || generating"
                            ><span class="text-xs font-normal text-stone-500">Up to 8 images, 10 MB
                                each.</span></label>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><template
                                x-for="a in referenceAssets"
                                :key="a.id"
                            >
                                <div
                                    class="overflow-hidden rounded-xl border"
                                    :class="references.includes(String(a.id)) ? 'border-amber-500 ring-1 ring-amber-500' :
                                        'border-stone-200 dark:border-stone-700'"
                                >
                                    <div class="relative"><img
                                            :src="a.url"
                                            :alt="a.name"
                                            loading="lazy"
                                            class="aspect-square w-full object-contain"
                                        ><button
                                            type="button"
                                            class="image-zoom-button"
                                            :aria-label="'Enlarge reference ' + a.name"
                                            @click.stop="$dispatch('vsg-image',a)"
                                            title="Zoom image"
                                        ><x-zoom-icon /></button></div>
                                    <label class="flex items-start gap-2 p-2 text-xs"><input
                                            type="checkbox"
                                            :value="String(a.id)"
                                            x-model="references"
                                            @change="changed()"
                                            class="mt-0.5 shrink-0"
                                        ><span
                                            class="min-w-0 break-words"
                                            x-text="a.name"
                                        ></span></label>
                                    <p
                                        class="px-2 pb-2 text-xs"
                                        x-show="assetComposition(a.id)"
                                        x-text="compositionText(assetComposition(a.id))"
                                    ></p>
                                </div>
                            </template></div>
                        <p
                            x-show="!referenceAssets.length"
                            class="text-sm text-stone-500"
                        >No images available.</p>
                    </div>
                </div>
            </dialog>
            <dialog
                x-ref="deleteImageDialog"
                class="rounded-xl border border-stone-200 bg-white p-5 text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100"
                @click="if($event.target===$el && !deletingImage)$el.close()"
                @cancel="if(deletingImage)$event.preventDefault()"
            >
                <h2 class="font-serif text-xl">Delete image draft?</h2>
                <p class="mt-2 text-sm">This permanently removes the ungenerated image and its prompt.</p>
                <div class="mt-5 flex justify-end gap-2"><button
                        type="button"
                        class="btn-secondary"
                        :disabled="deletingImage"
                        @click="$refs.deleteImageDialog.close()"
                    >Cancel</button><button
                        type="button"
                        class="btn-primary"
                        :disabled="deletingImage"
                        @click="deleteImageDraft()"
                        x-text="deletingImage?'Deleting…':'Delete draft'"
                    ></button></div>
            </dialog>
            @include('studio.preview')

            <template x-teleport="body">
                <dialog
                    x-ref="imageCategoriesDialog"
                    class="image-picker-dialog rounded-2xl border bg-white p-5 text-stone-900 dark:bg-stone-900 dark:text-stone-100"
                    aria-label="Edit image categories"
                    @click="if($event.target===$el)$el.close()"
                >
                    <header class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="font-serif text-xl">Image categories</h2><button
                            type="button"
                            class="btn-secondary"
                            @click="$refs.imageCategoriesDialog.close()"
                        >Close</button>
                    </header>
                    <div class="max-h-[60vh] space-y-3 overflow-y-auto"><template
                            x-for="(name,key) in imageCategories"
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
                                    @click="editingCategory===key ? finishCategoryEdit(key) : editCategory(key)"
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
                                    @click="deleteCategory(key)"
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
                        </template></div>
                    <div class="mt-4 flex gap-2"><input
                            aria-label="New image category"
                            x-model="newImageCategory"
                            maxlength="80"
                            placeholder="New category"
                            @keydown.enter.prevent="addImageCategory()"
                        ><button
                            type="button"
                            class="btn-secondary"
                            :disabled="categoryBusy || !newImageCategory.trim()"
                            @click="addImageCategory()"
                        >Add</button></div>
                </dialog>
            </template>
        </section>
    </div>
</x-studio-layout>
