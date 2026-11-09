<x-studio-layout :title="'Image generation · '.$story->title" :story="$story" :page="$page">
<div x-data="studioAutosave({{ $story->revision }})" class="image-workspace story-editor-workspace">
@include('studio.story-header')
<section class="image-workspace-grid" x-data="sceneEditor(@js(['url'=>route('visuals.show',[$story,$page]),'generate'=>route('images.generate',[$story,$page]),'moveBase'=>url('/stories/'.$story->id.'/pages/'.$page->id.'/images'),'upload'=>route('assets.store',$story),'model'=>$story->image_model,'generationMode'=>true,'draftUrl'=>route('images.draft',[$story,$page]),'nameUrl'=>route('images.name',$story),'writingModel'=>$story->llm_model,'assetBase'=>url('/assets')]))">
<div class="image-controls-column min-w-0">
<div class="editor-tabs min-w-0 px-4 pt-5 lg:px-5">
@include('studio.tabs',['generation'=>true])
</div>
<div class="image-controls min-w-0 p-4 lg:p-5">
<template x-if="visual"><div x-ref="generationForm" class="space-y-3">
@include('studio.image-form')
</div></template>


</div></div>
<aside class="image-results border-t border-stone-200 bg-stone-50 dark:border-stone-700 dark:bg-stone-950 md:border-r md:border-t-0" aria-label="Images">
<header class="flex items-center justify-between border-b border-stone-200 p-4 dark:border-stone-700"><h2 class="font-serif text-lg">Images</h2><span class="text-xs text-stone-500" x-text="visibleJobs.length"></span></header>
<div class="image-results-scroll space-y-3 p-3" x-ref="imageResults">
<p x-show="!historyJobs.length" class="p-2 text-sm text-stone-500">Your images and prepared prompts will appear here.</p>
<template x-for="job in visibleJobs" :key="job.id"><details :open="cardOpen(job)" @toggle="rememberCard(job,$el.open)" :class="selectedImageId===job.id?'ring-2 ring-amber-500 bg-amber-50 dark:bg-stone-800':'bg-white dark:bg-stone-900'" @click="if(!$event.target.closest('button,input,label,a'))selectImageCard(job)" class="rounded-xl border border-stone-200 dark:border-stone-700">
<summary class="cursor-pointer p-3 text-sm"><span class="font-semibold break-words" x-text="jobTitle(job)"></span><span class="ml-1 text-xs" :class="job.status==='failed'?'text-red-600 dark:text-red-400':'text-stone-500'" x-show="!job.sourcePage" x-text="job.status"></span></summary>
<div class="space-y-3 px-3 pb-3">
<button type="button" x-show="!job.sourcePage && ['draft','completed','failed'].includes(job.status)" class="text-xs underline" :aria-pressed="selectedImageId===job.id" @click="selectImageCard(job)" x-text="selectedImageId===job.id?'Selected image':'Select image'"></button>
<p x-show="job.status==='draft'" class="grid aspect-video place-items-center rounded-lg bg-stone-100 text-xs text-stone-500 dark:bg-stone-800">Image not generated</p>
<button type="button" x-show="job.localFailure" class="text-xs underline" @click="jobs=jobs.filter(item=>item.id!==job.id)">Dismiss failed request</button>

<button type="button" x-show="job.status==='failed' && job.retry_url" class="btn-secondary" @click="retryImage(job)" :disabled="generating" x-text="retryingJob===job.id?'Retrying…':'Try again'"></button>
<p x-show="job.sourcePage" class="text-xs text-stone-500" x-text="job.sourcePage"></p><p x-show="!job.sourcePage" class="break-words text-xs text-stone-500" x-text="job.model"></p><p x-show="job.error" class="break-words text-xs text-red-600 dark:text-red-400" x-text="job.error"></p>
<div x-show="['submitting','queued','running'].includes(job.status)" class="relative grid aspect-[4/3] w-full place-items-center rounded-lg bg-stone-100 dark:bg-stone-800" role="status" :aria-label="job.status==='submitting'?'Submitting image request':'Image generation in progress'">
<span class="h-8 w-8 animate-spin rounded-full border-2 border-stone-300 border-t-amber-600 motion-reduce:animate-none dark:border-stone-600 dark:border-t-amber-300" aria-hidden="true"></span>
<span class="absolute inset-x-0 bottom-3 text-center text-xs text-stone-500" x-text="job.status==='running'?'Rendering…':job.status==='queued'?'Queued…':'Submitting…'"></span>
</div>
<template x-for="id in jobAssets(job)" :key="id"><div class="space-y-2">
<label x-show="!job.sourcePage" class="field text-xs">Image name<input type="text" maxlength="255" :value="assetNames[id]??asset(id)?.name" @input="assetNames[id]=$event.target.value" @change="renameAsset(id)" @keydown.enter.prevent="$el.blur()" :disabled="assetSaving[id]"></label>
<div class="relative"><img :src="asset(id)?.url" :alt="asset(id)?.name" loading="lazy" class="max-h-60 w-full rounded-lg object-contain"><button type="button" class="image-zoom-button" :aria-label="'Enlarge '+asset(id)?.name" @click.stop="$dispatch('vsg-image',asset(id))" title="Zoom image"><x-zoom-icon /></button></div>
<label class="flex items-center gap-2 text-xs"><input type="checkbox" :value="String(id)" x-model="references" @change="changed()">Include as reference</label>
<div class="flex flex-wrap gap-2"><template x-for="use in asset(id)?.uses||[]" :key="use.url"><a class="text-xs underline" :href="use.url" x-text="(use.kind==='object'?'Object · ':'Scene · ')+use.name"></a></template></div>
<button type="button" x-show="!job.sourcePage" class="text-xs underline" @click="updateAsset(id,{archived:!asset(id)?.archived})" :disabled="assetSaving[id]" x-text="asset(id)?.archived?'Restore image':'Archive image'"></button>
</div></template>
<div><div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold">Prompt used</span><button type="button" class="text-xs underline" @click="copyPrompt(job)" x-text="copiedPrompt===job.id?'Copied!':'Copy prompt'"></button></div><p class="mt-2 whitespace-pre-wrap break-words text-xs text-stone-600 dark:text-stone-300" x-text="expandedPrompts[job.id]?job.prompt:promptShort(job)"></p><button type="button" x-show="promptLong(job)" class="mt-1 text-xs underline" :aria-expanded="!!expandedPrompts[job.id]" @click="expandedPrompts[job.id]=!expandedPrompts[job.id]" x-text="expandedPrompts[job.id]?'Show less':'Show all'"></button></div>
</div></details></template>
<div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
<button type="button" class="text-sm underline" @click="showArchived=!showArchived" x-text="showArchived?'Hide archived images':'Show archived images ('+archivedCount+')'"></button>
<button type="button" class="text-sm underline" :aria-expanded="showFailed" @click="showFailed=!showFailed" x-text="showFailed?'Hide failed generations':'Show failed generations ('+failedCount+')'"></button>
<template x-for="page in otherImagePages" :key="page.id"><button type="button" class="text-sm underline disabled:opacity-50" @click="togglePageImages(page)" :disabled="loadingImagePages.includes(page.id)" :aria-expanded="loadedImagePages.includes(page.id)" :title="page.title" x-text="loadingImagePages.includes(page.id)?'Loading page '+page.position+'…':(loadedImagePages.includes(page.id)?'Hide page ':'Show page ')+page.position+' images'"></button></template>
</div>
</div></aside>
<dialog x-ref="referenceDialog" class="reference-dialog rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100" aria-labelledby="reference-title" @click="if($event.target===$el)$el.close()">
<div class="flex min-h-0 flex-col"><header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-stone-200 p-4 dark:border-stone-700"><h2 id="reference-title" class="font-serif text-xl">Reference images</h2><div class="ml-auto flex min-w-0 items-center gap-2"><label class="field min-w-0"><span class="sr-only">Filter reference images by page</span><select x-model="referencePage" class="max-w-64" @change="$refs.referenceImagesScroll.scrollTop=0"><option value="all">All images</option><template x-for="page in previewPages" :key="page.id"><option :value="String(page.id)" x-text="pageImageLabel(page)"></option></template></select></label><button type="button" class="btn-secondary" @click="$refs.referenceDialog.close()">Done</button></div></header>
<div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4" x-ref="referenceImagesScroll">
<label class="field rounded-lg border border-dashed border-stone-300 p-3 dark:border-stone-600">Upload images<input type="file" multiple accept="image/png,image/jpeg,image/webp" @change="uploadReferences($event.target)" :disabled="uploading || generating"><span class="text-xs font-normal text-stone-500">Up to 8 images, 10 MB each.</span></label>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><template x-for="a in referenceAssets" :key="a.id"><div class="overflow-hidden rounded-xl border" :class="references.includes(String(a.id))?'border-amber-500 ring-1 ring-amber-500':'border-stone-200 dark:border-stone-700'">
<div class="relative"><img :src="a.url" :alt="a.name" loading="lazy" class="aspect-square w-full object-contain"><button type="button" class="image-zoom-button" :aria-label="'Enlarge reference '+a.name" @click.stop="$dispatch('vsg-image',a)" title="Zoom image"><x-zoom-icon /></button></div>
<label class="flex items-start gap-2 p-2 text-xs"><input type="checkbox" :value="String(a.id)" x-model="references" @change="changed()" class="mt-0.5 shrink-0"><span class="min-w-0 break-words" x-text="a.name"></span></label>
</div></template></div><p x-show="!referenceAssets.length" class="text-sm text-stone-500" x-text="referencePage==='all'?'No images uploaded yet.':'No images on this page yet.'"></p>
</div></div></dialog>
@include('studio.preview')
</section></div>
</x-studio-layout>
