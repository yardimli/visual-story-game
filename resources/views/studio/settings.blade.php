<x-studio-layout :companion="false" title="AI settings"><main class="mx-auto max-w-4xl px-5 py-12">
<p class="eyebrow">YOUR CREATIVE TOOLKIT</p><h1 class="mt-3 font-serif text-4xl">AI & usage</h1><p class="mt-4 text-stone-500 dark:text-stone-400">Start with $1 of shared credit for writing and images. Connect your own OpenRouter key to keep creating.</p>
<div class="my-8 grid gap-4 md:grid-cols-3">@foreach(['Spent'=>$user->llm_spent,'Reserved'=>$user->llm_reserved,'Available'=>max(0,config('story.allowance')-$user->llm_spent-$user->llm_reserved)] as $label=>$value)<div class="rounded-2xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6"><p class="eyebrow">{{ $label }}</p><p class="mt-3 font-serif text-3xl">${{ number_format($value/1000000,4) }}</p></div>@endforeach</div>
<p class="mb-8 text-sm leading-6 text-stone-500 dark:text-stone-400">The included allowance is per account and does not reset. Pending requests reserve credit for up to three minutes. A personal key uses your OpenRouter balance, not this allowance. Writing and images share the included credit.</p>
<section class="rounded-2xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-7"><div class="flex justify-between gap-3"><h2 class="font-serif text-2xl">Your OpenRouter key</h2><span class="badge">{{ $user->openrouter_key?'Connected':'Not connected' }}</span></div><p class="mt-3 text-sm text-stone-500 dark:text-stone-400">Stored encrypted on the server. Your saved key is never displayed.</p>
<form method="POST" action="{{ route('ai.update') }}" class="mt-6 space-y-5">@csrf @method('PUT')
<label class="field">New API key<input type="password" name="openrouter_key" required autocomplete="new-password" placeholder="sk-or-…"></label>
<label class="field">Confirm your account password<input type="password" name="current_password" required autocomplete="current-password"></label>
<button class="btn-primary">{{ $user->openrouter_key?'Replace key':'Connect key' }}</button></form>
@if($user->openrouter_key)<details class="mt-8 border-t pt-5"><summary class="cursor-pointer text-sm text-stone-500 dark:text-stone-400">Remove personal key</summary><form action="{{ route('ai.destroy') }}" method="POST" class="mt-4 space-y-4">@csrf @method('DELETE')<label class="field">Account password<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn-secondary">Remove key</button></form></details>@endif
</section>
<section x-data="modelCatalogReload" class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-700 dark:bg-stone-900"><h2 class="font-serif text-xl">Model catalogs</h2><button type="button" class="btn-secondary" @click="reload()" :disabled="busy" x-text="busy?'Reloading…':'Reload models'"></button></section>
<section id="ai-call-history" class="mt-8">
<div class="mb-4 flex items-center justify-between"><h2 class="font-serif text-2xl">AI call history</h2><span class="text-xs text-stone-500">{{ $calls->total() }} calls</span></div>
<div class="space-y-2">
@forelse($calls as $call)
<details x-data="aiCallHistory(@js(route('ai.calls.show',$call->id)))" @toggle="load($el.open)" class="rounded-lg border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-900">
<summary class="cursor-pointer px-3 py-2 text-xs"><span class="inline-flex max-w-[90%] flex-wrap items-center gap-x-3 gap-y-1 align-middle"><span class="font-medium">{{ $call->model }}</span><span class="text-stone-500">{{ $call->imageGeneration ? 'Image · '.$call->imageGeneration->name : ($call->story_id?'Story companion':'Companion') }}</span><span>{{ $call->cost===null?'Cost pending':'$'.number_format($call->cost/1000000,6) }}</span><span class="text-stone-500">{{ $call->imageGeneration?->status ?? $call->status }} · {{ $call->created_at->format('M j, H:i') }}</span></span></summary>
<div class="border-t border-stone-200 p-4 dark:border-stone-700" :aria-busy="busy">
<p x-show="busy" class="text-sm text-stone-500">Loading call contents…</p><button type="button" x-show="failed" class="btn-secondary" @click="load()">Retry loading</button>
<template x-if="loaded"><div class="space-y-4"><p x-show="detail.note" x-text="detail.note" class="text-sm text-stone-500"></p>
<template x-for="part in ['request','response','accounting']" :key="part"><div><h3 class="mb-2 text-sm font-semibold capitalize" x-text="part"></h3><pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words rounded bg-stone-50 p-3 text-xs dark:bg-stone-950" x-text="format(detail[part])"></pre></div></template>
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><template x-for="image in detail.images" :key="image.id"><figure><div class="relative"><img :src="image.url" :alt="image.name" loading="lazy" class="aspect-video w-full rounded object-contain"><button type="button" class="image-zoom-button" :aria-label="'Enlarge '+image.name" title="Zoom image" @click="$dispatch('vsg-image',image)"><x-zoom-icon /></button></div><figcaption class="text-xs" x-text="image.name"></figcaption></figure></template></div>
</div></template></div></details>
@empty<p class="text-sm text-stone-500">Your AI calls will appear here.</p>@endforelse
</div><div class="mt-5">{{ $calls->links() }}</div>
</section>
</main></x-studio-layout>
