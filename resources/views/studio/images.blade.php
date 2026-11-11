<x-studio-layout :title="'Image generation · '.$story->title" :story="$story" :page="$page">
<div x-data="studioAutosave({{ $story->revision }})" class="image-workspace story-editor-workspace">
@include('studio.story-header',['generation'=>true])
<section class="image-workspace-grid" x-data="sceneEditor(@js(['url'=>route('visuals.show',[$story,$page,'all_images'=>1]),'orderUrl'=>route('images.order',$story),'deleteDraftBase'=>url('/stories/'.$story->id.'/image-drafts'),'generate'=>route('images.generate',[$story,$page]),'moveBase'=>url('/stories/'.$story->id.'/pages/'.$page->id.'/images'),'upload'=>route('assets.store',$story),'model'=>$story->image_model,'generationMode'=>true,'draftUrl'=>route('images.draft',[$story,$page]),'nameUrl'=>route('images.name',$story),'writingModel'=>$story->llm_model,'assetBase'=>url('/assets')]))">
<div class="image-controls-column min-w-0">
<div class="image-controls min-w-0 p-4 lg:p-5">
<template x-if="visual"><div x-ref="generationForm" class="space-y-3">
@include('studio.image-form')
</div></template>


</div></div>
<aside class="image-results border-t border-stone-200 bg-stone-50 dark:border-stone-700 dark:bg-stone-950 md:border-r md:border-t-0" aria-label="Images">
<header class="flex items-center justify-between border-b border-stone-200 p-4 dark:border-stone-700"><h2 class="font-serif text-lg">Images</h2><span class="text-xs text-stone-500" x-text="visibleJobs.length"></span></header>
<div class="image-results-scroll space-y-3 p-3" x-ref="imageResults">
<p x-show="!historyJobs.length" class="p-2 text-sm text-stone-500">Your images and prepared prompts will appear here.</p>
<template x-for="job in visibleJobs" :key="job.id"><details :open="cardOpen(job)" :data-moved="movedImageId===job.id" @animationend.self="if(movedImageId===job.id)movedImageId=null" @toggle="rememberCard(job,$el.open)" :class="selectedImageId===job.id?'ring-2 ring-amber-500 bg-amber-50 dark:bg-stone-800':'bg-white dark:bg-stone-900'" @click="if(!$event.target.closest('button,input,label,a'))selectImageCard(job)" class="image-history-card rounded-xl border border-stone-200 dark:border-stone-700">
<summary class="cursor-pointer p-3 text-sm"><span class="float-right ml-2 inline-flex gap-2" @click.stop.prevent @pointerdown.stop><button type="button" class="btn-secondary image-order-button" :disabled="orderingImages || visibleJobs[0]?.id===job.id" :aria-label="'Move image up: '+jobTitle(job)" title="Move image up" @click.prevent.stop="moveImageOrder(job.id,-1)">↑</button><button type="button" class="btn-secondary image-order-button" :disabled="orderingImages || visibleJobs[visibleJobs.length-1]?.id===job.id" :aria-label="'Move image down: '+jobTitle(job)" title="Move image down" @click.prevent.stop="moveImageOrder(job.id,1)">↓</button></span><span class="font-semibold break-words" x-text="jobTitle(job)"></span><span class="ml-1 text-xs" :class="job.status==='failed'?'text-red-600 dark:text-red-400':'text-stone-500'" x-text="job.status"></span><span x-show="job.status==='running' && job.started_at" class="mt-1 block text-xs tabular-nums text-stone-500 dark:text-stone-400"><span x-text="'Started '+renderStarted(job)"></span><span class="mx-1">·</span><span x-text="renderElapsed(job)+' elapsed'"></span></span></summary>
<div class="space-y-3 px-3 pb-3">
<button type="button" x-show="['draft','completed','failed'].includes(job.status)" class="text-xs underline" :aria-pressed="selectedImageId===job.id" @click="selectImageCard(job)" x-text="selectedImageId===job.id?'Selected image':'Select image'"></button>
<button type="button" x-show="job.status==='draft'" class="text-xs text-red-600 underline dark:text-red-400" @click="deleteImageId=job.id;$refs.deleteImageDialog.showModal()" :disabled="generating || saving || deletingImage">Delete image draft</button>
<p x-show="job.status==='draft'" class="grid aspect-video place-items-center rounded-lg bg-stone-100 text-xs text-stone-500 dark:bg-stone-800">Image not generated</p>
<button type="button" x-show="job.localFailure" class="text-xs underline" @click="jobs=jobs.filter(item=>item.id!==job.id)">Dismiss failed request</button>

<button type="button" x-show="job.status==='failed' && job.retry_url" class="btn-secondary" @click="retryImage(job)" :disabled="generating" x-text="retryingJob===job.id?'Retrying…':'Try again'"></button>
<p class="break-words text-xs text-stone-500" x-text="job.model"></p><p x-show="job.error" class="break-words text-xs text-red-600 dark:text-red-400" x-text="job.error"></p>
<div x-show="['submitting','queued','running'].includes(job.status)" class="relative grid aspect-[4/3] w-full place-items-center rounded-lg bg-stone-100 dark:bg-stone-800" role="status" :aria-label="job.status==='submitting'?'Submitting image request':'Image generation in progress'">
<span class="h-8 w-8 animate-spin rounded-full border-2 border-stone-300 border-t-amber-600 motion-reduce:animate-none dark:border-stone-600 dark:border-t-amber-300" aria-hidden="true"></span>
<span class="absolute inset-x-0 bottom-3 text-center text-xs text-stone-500" x-text="job.status==='running'?'Rendering…':job.status==='queued'?'Queued…':'Submitting…'"></span>
</div>
<template x-for="id in jobAssets(job)" :key="id"><div class="space-y-2">
<label class="field text-xs">Image name<input type="text" maxlength="255" :value="assetNames[id]??asset(id)?.name" @input="assetNames[id]=$event.target.value" @change="renameAsset(id)" @keydown.enter.prevent="$el.blur()" :disabled="assetSaving[id]"></label>
<div class="relative"><img :src="asset(id)?.url" :alt="asset(id)?.name" loading="lazy" class="max-h-60 w-full rounded-lg object-contain"><button type="button" class="image-zoom-button" :aria-label="'Enlarge '+asset(id)?.name" @click.stop="$dispatch('vsg-image',asset(id))" title="Zoom image"><x-zoom-icon /></button></div>
<label class="flex items-center gap-2 text-xs"><input type="checkbox" :value="String(id)" x-model="references" @change="changed()">Include as reference</label>
<div class="flex flex-wrap gap-2"><template x-for="use in asset(id)?.uses||[]" :key="use.url"><a class="text-xs underline" :href="use.url" x-text="(use.kind==='object'?'Object · ':'Scene · ')+use.name"></a></template></div>
<button type="button" class="text-xs underline" @click="updateAsset(id,{archived:!asset(id)?.archived})" :disabled="assetSaving[id]" x-text="asset(id)?.archived?'Restore image':'Archive image'"></button>
</div></template>
<div><div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold">Prompt used</span><button type="button" class="text-xs underline" @click="copyPrompt(job)" x-text="copiedPrompt===job.id?'Copied!':'Copy prompt'"></button></div><p class="mt-2 whitespace-pre-wrap break-words text-xs text-stone-600 dark:text-stone-300" x-text="expandedPrompts[job.id]?job.prompt:promptShort(job)"></p><button type="button" x-show="promptLong(job)" class="mt-1 text-xs underline" :aria-expanded="!!expandedPrompts[job.id]" @click="expandedPrompts[job.id]=!expandedPrompts[job.id]" x-text="expandedPrompts[job.id]?'Show less':'Show all'"></button></div>
</div></details></template>
<div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
<button type="button" class="text-sm underline" @click="showArchived=!showArchived" x-text="showArchived?'Hide archived images':'Show archived images ('+archivedCount+')'"></button>
<button type="button" class="text-sm underline" :aria-expanded="showFailed" @click="showFailed=!showFailed" x-text="showFailed?'Hide failed generations':'Show failed generations ('+failedCount+')'"></button>
</div>
</div></aside>
<dialog x-ref="referenceDialog" class="reference-dialog rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100" aria-labelledby="reference-title" @click="if($event.target===$el)$el.close()">
<div class="flex min-h-0 flex-col"><header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-stone-200 p-4 dark:border-stone-700"><h2 id="reference-title" class="font-serif text-xl">Reference images</h2><div class="ml-auto flex min-w-0 items-center gap-2"><button type="button" class="btn-secondary" @click="$refs.referenceDialog.close()">Done</button></div></header>
<div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4" x-ref="referenceImagesScroll">
<label class="field rounded-lg border border-dashed border-stone-300 p-3 dark:border-stone-600">Upload images<input type="file" multiple accept="image/png,image/jpeg,image/webp" @change="uploadReferences($event.target)" :disabled="uploading || generating"><span class="text-xs font-normal text-stone-500">Up to 8 images, 10 MB each.</span></label>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><template x-for="a in referenceAssets" :key="a.id"><div class="overflow-hidden rounded-xl border" :class="references.includes(String(a.id))?'border-amber-500 ring-1 ring-amber-500':'border-stone-200 dark:border-stone-700'">
<div class="relative"><img :src="a.url" :alt="a.name" loading="lazy" class="aspect-square w-full object-contain"><button type="button" class="image-zoom-button" :aria-label="'Enlarge reference '+a.name" @click.stop="$dispatch('vsg-image',a)" title="Zoom image"><x-zoom-icon /></button></div>
<label class="flex items-start gap-2 p-2 text-xs"><input type="checkbox" :value="String(a.id)" x-model="references" @change="changed()" class="mt-0.5 shrink-0"><span class="min-w-0 break-words" x-text="a.name"></span></label>
</div></template></div><p x-show="!referenceAssets.length" class="text-sm text-stone-500">No images available.</p>
</div></div></dialog>
<dialog x-ref="deleteImageDialog" class="rounded-xl border border-stone-200 bg-white p-5 text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100" @click="if($event.target===$el && !deletingImage)$el.close()" @cancel="if(deletingImage)$event.preventDefault()">
<h2 class="font-serif text-xl">Delete image draft?</h2><p class="mt-2 text-sm">This permanently removes the ungenerated image and its prompt.</p>
<div class="mt-5 flex justify-end gap-2"><button type="button" class="btn-secondary" :disabled="deletingImage" @click="$refs.deleteImageDialog.close()">Cancel</button><button type="button" class="btn-primary" :disabled="deletingImage" @click="deleteImageDraft()" x-text="deletingImage?'Deleting…':'Delete draft'"></button></div>
</dialog>
@include('studio.preview')
</section></div>
</x-studio-layout>
