<x-studio-layout :title="'Image generation · '.$story->title" :story="$story" :page="$page">
<div x-data="studioAutosave({{ $story->revision }})" class="image-workspace story-editor-workspace">
@include('studio.story-header')
<section class="image-workspace-grid" x-data="sceneEditor(@js(['url'=>route('visuals.show',[$story,$page]),'generate'=>route('images.generate',[$story,$page]),'moveBase'=>url('/stories/'.$story->id.'/pages/'.$page->id.'/images'),'upload'=>route('assets.store',$story),'model'=>$story->image_model,'prompt'=>$page->scene_prompt,'generationMode'=>true]))">
<div class="image-controls-column min-w-0">
<div class="editor-tabs min-w-0 px-4 pt-5 lg:px-5">
@include('studio.tabs',['generation'=>true])
</div>
<div class="image-controls min-w-0 p-4 lg:p-5">
<div class="mb-4"><p class="text-xs text-stone-500">Current visual</p><h2 class="mt-1 break-words font-serif text-xl" x-text="visual?.name || 'No visual selected'"></h2><p class="text-xs text-stone-500" x-text="visual?.kind==='object'?'Object / close-up':'Scene state'"></p></div>
<template x-if="visual"><div x-ref="generationForm" class="space-y-3">
@include('studio.image-form')
</div></template>
<p x-show="!loading && !visual" class="py-8 text-sm text-stone-500">Return to Scene content & interactions to select or create a visual.</p>

</div></div>
<aside class="image-results border-t border-stone-200 bg-stone-50 dark:border-stone-700 dark:bg-stone-950 md:border-r md:border-t-0" aria-label="Generated versions">
<header class="flex items-center justify-between border-b border-stone-200 p-4 dark:border-stone-700"><h2 class="font-serif text-lg">Generated versions</h2><span class="text-xs text-stone-500" x-text="jobs.length"></span></header>
<div class="image-results-scroll space-y-3 p-3" x-ref="imageResults">
<p x-show="!jobs.length" class="p-2 text-sm text-stone-500">Your generated images will appear here.</p>
<template x-for="job in jobs" :key="job.id"><details :open="job.localFailure || job.status !== 'failed'" class="rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-900">
<summary class="cursor-pointer p-3 text-sm"><span class="font-semibold break-words" x-text="job.name"></span><span class="ml-1 text-xs" :class="job.status==='failed'?'text-red-600 dark:text-red-400':'text-stone-500'" x-text="job.status"></span></summary>
<div class="space-y-3 px-3 pb-3">
<button type="button" x-show="job.localFailure" class="text-xs underline" @click="jobs=jobs.filter(item=>item.id!==job.id)">Dismiss failed request</button>
<p class="break-words text-xs" x-text="document.visuals.find(v=>v.id===job.visual_id)?.name || 'Deleted visual'"></p><button type="button" x-show="job.status==='completed'" class="text-xs underline" @click="openMoveImage(job)">Change scene</button>
<p class="break-words text-xs text-stone-500" x-text="job.model"></p><p x-show="job.error" class="break-words text-xs text-red-600 dark:text-red-400" x-text="job.error"></p>
<div x-show="['submitting','queued','running'].includes(job.status)" class="relative grid aspect-[4/3] w-full place-items-center rounded-lg bg-stone-100 dark:bg-stone-800" role="status" :aria-label="job.status==='submitting'?'Submitting image request':'Image generation in progress'">
<span class="h-8 w-8 animate-spin rounded-full border-2 border-stone-300 border-t-amber-600 motion-reduce:animate-none dark:border-stone-600 dark:border-t-amber-300" aria-hidden="true"></span>
<span class="absolute inset-x-0 bottom-3 text-center text-xs text-stone-500" x-text="job.status==='running'?'Rendering…':job.status==='queued'?'Queued…':'Submitting…'"></span>
</div>
<template x-for="id in job.asset_ids||[]" :key="id"><div class="space-y-2">
<div class="relative"><img :src="asset(id)?.url" :alt="asset(id)?.name" loading="lazy" class="max-h-60 w-full rounded-lg object-contain"><button type="button" class="image-zoom-button" :aria-label="'Enlarge '+asset(id)?.name" @click.stop="$dispatch('vsg-image',asset(id))" title="Zoom image"><x-zoom-icon /></button></div>
<label class="flex items-center gap-2 text-xs"><input type="checkbox" :value="String(id)" x-model="references">Include as reference</label>
<button type="button" class="text-xs underline" @click="useJobImage(job,id)" :disabled="!document.visuals.some(v=>v.id===job.visual_id)">Use in scene</button>
</div></template>
<div><div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold">Prompt used</span><button type="button" class="text-xs underline" @click="copyPrompt(job)" x-text="copiedPrompt===job.id?'Copied!':'Copy prompt'"></button></div><p class="mt-2 whitespace-pre-wrap break-words text-xs text-stone-600 dark:text-stone-300" x-text="job.prompt"></p></div>
</div></details></template>
</div></aside>
<dialog x-ref="moveImageDialog" class="reference-dialog rounded-xl bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100" aria-labelledby="move-image-title">
<form class="space-y-4 p-5" @submit.prevent="moveImage()"><h2 id="move-image-title" class="font-serif text-xl">Change scene</h2><label class="field">Move this generation to<select x-model="moveTarget" required><template x-for="(v,index) in document.visuals" :key="v.id"><option :value="v.id" x-text="(index+1)+'. '+(v.kind==='object'?'Object · ':'Scene · ')+v.name"></option></template></select></label><p class="text-xs text-stone-500">Moves this generated version. Images already used in a scene stay unchanged.</p><div class="flex justify-end gap-2"><button type="button" class="btn-secondary" @click="$refs.moveImageDialog.close()" :disabled="movingImage">Cancel</button><button class="btn-primary" :disabled="movingImage || !moveTarget" x-text="movingImage?'Moving…':'Move image'"></button></div></form></dialog>
<dialog x-ref="referenceDialog" class="reference-dialog rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100" aria-labelledby="reference-title" @click="if($event.target===$el)$el.close()">
<div class="flex max-h-[85dvh] flex-col"><header class="flex items-center justify-between gap-4 border-b border-stone-200 p-4 dark:border-stone-700"><h2 id="reference-title" class="font-serif text-xl">Reference images</h2><button type="button" class="btn-secondary" @click="$refs.referenceDialog.close()">Done</button></header>
<div class="min-h-0 space-y-4 overflow-y-auto p-4">
<label class="field rounded-lg border border-dashed border-stone-300 p-3 dark:border-stone-600">Upload images<input type="file" multiple accept="image/png,image/jpeg,image/webp" @change="uploadReferences($event.target)" :disabled="uploading || generating"><span class="text-xs font-normal text-stone-500">Up to 8 images, 10 MB each.</span></label>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><template x-for="a in assets" :key="a.id"><div class="overflow-hidden rounded-xl border" :class="references.includes(String(a.id))?'border-amber-500 ring-1 ring-amber-500':'border-stone-200 dark:border-stone-700'">
<div class="relative"><img :src="a.url" :alt="a.name" loading="lazy" class="aspect-square w-full object-contain"><button type="button" class="image-zoom-button" :aria-label="'Enlarge reference '+a.name" @click.stop="$dispatch('vsg-image',a)" title="Zoom image"><x-zoom-icon /></button></div>
<label class="flex items-start gap-2 p-2 text-xs"><input type="checkbox" :value="String(a.id)" x-model="references" class="mt-0.5 shrink-0"><span class="min-w-0 break-words" x-text="a.name"></span></label>
</div></template></div><p x-show="!assets.length" class="text-sm text-stone-500">No images uploaded yet.</p>
</div></div></dialog>
</section></div>
</x-studio-layout>
