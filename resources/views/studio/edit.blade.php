<x-studio-layout :title="$story->title" :story="$story" :page="$page">
<div class="story-editor-workspace" x-data="studioAutosave({{ $story->revision }}, {tabs:true,pageId:{{ $page->id }},position:{{ $page->position }},imageBase:'{{ url('/stories/'.$story->id.'/pages') }}'})" @input="changed($event)" @change="changed($event)" @companion-filled.window="tab=$event.detail.tab || tab">
@include('studio.story-header')
<div class="story-editor-grid grid grid-cols-1">
<div class="story-editor-column min-w-0">
<div id="scene-toolbar" x-show="tab==='visuals'" x-cloak class="editor-tabs scene-toolbar-slot min-w-0 px-5 lg:px-8"></div>
<main class="story-editor-content min-w-0 p-5 lg:p-8" :style="tab==='visuals'?'padding-top: 0':null">
@include('studio.page-documents')
<section x-show="tab==='story'" x-cloak><h2 class="mb-6 font-serif text-2xl">The world behind the story</h2>
<form data-autosave @submit.prevent="flush()" data-companion-form="story" method="POST" action="{{ route('stories.update',$story) }}" class="space-y-5">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $story->revision }}">
@foreach(['title'=>'Title','genre'=>'Genre','language'=>'Language'] as $field=>$label)<label class="field">{{ $label }}<input name="{{ $field }}" required value="{{ $story->$field }}"></label>@endforeach
@foreach(['premise'=>'Premise','style'=>'Visual direction','bible'=>'Complete story bible'] as $field=>$label)<label class="field">{{ $label }}<textarea name="{{ $field }}" rows="{{ $field==='bible'?12:3 }}">{{ $story->$field }}</textarea></label>@endforeach
<label class="field">Default LLM model ID<input name="llm_model" value="{{ $story->llm_model }}" placeholder="Choose a model in the chat selector"></label>
<div x-data="modelPicker(@js($story->image_model ?? ''), 'image')"><label class="field">Image model for this story<select name="image_model" x-model="selected" @change="remember()"><option value="">Choose later</option><template x-for="m in models" :key="m.id"><option :value="m.id" x-text="modelLabel(m)"></option></template></select></label><p class="mt-2 text-xs text-stone-400">Dedicated OpenRouter image catalog. Pricing below is per provider and may depend on quality, size, references, and token usage. This saves your preference; image generation is in development.</p><div class="mt-3 space-y-3 text-xs" aria-live="polite"><p x-show="pricingLoading">Loading image pricing…</p><button type="button" x-show="pricingError" @click="loadPricing()" class="underline">Retry pricing</button><template x-for="(endpoint,index) in endpoints" :key="index"><div class="rounded-lg border border-stone-200 dark:border-stone-700 p-3"><p class="font-semibold" x-text="endpoint.provider_name || endpoint.provider_slug"></p><template x-for="(line,i) in (endpoint.pricing || [])" :key="i"><p class="mt-1" x-text="imagePriceLabel(line)"></p></template><p x-show="!endpoint.pricing?.length" class="mt-1">Pricing unavailable for this endpoint.</p></div></template><p x-show="selected && !pricingLoading && !pricingError && !endpoints.length">No endpoint pricing available.</p></div></div>
<p class="text-xs text-stone-400">Changes save automatically.</p></form>
@include('studio.story-flags')
@include('studio.audio-settings')
</section>
@include('studio.visuals')
@include('studio.inventory')
@include('studio.audio')
@include('studio.speech')


</main></div>
</div></div></x-studio-layout>
