@props(['story' => null, 'page' => null])
@php
$initial = $story ? $story->messages()->latest('id')->limit(40)->get()->reverse()->values() : [];
$options = ['endpoint'=>$story ? route('chat.store',$story) : route('companion.store'), 'history'=>$story ? null : route('companion.history'), 'messages'=>$initial, 'model'=>$story?->llm_model ?? '', 'pageId'=>$page?->id, 'position'=>$page?->position, 'revision'=>$story?->revision, 'remaining'=>max(0,config('story.allowance')-auth()->user()->llm_spent-auth()->user()->llm_reserved)/1000000];
@endphp
<aside class="companion-panel" aria-label="Creative companion" x-data="creativeCompanion(@js($options))">
<header class="border-b border-stone-200 dark:border-stone-700 p-5">
<div class="flex items-center justify-between"><h2 class="font-serif text-xl">Creative companion</h2><span class="text-amber-600">✧</span></div>
<p class="mt-2 text-xs leading-5 text-stone-500 dark:text-stone-400">{{ $story ? 'Planning with you · '.$story->title : 'Your ideas, with a little company.' }}</p>
<p class="mt-2 text-xs leading-5">{{ $story ? 'Apply a suggestion to autosave its changes.' : 'Suggestions fill drafts. You review and create.' }}</p>
</header>
<div class="min-h-32 flex-1 space-y-5 overflow-y-auto p-5" x-ref="history" aria-live="polite">
<template x-if="!messages.length"><div><h3 class="font-serif text-2xl">What shall we make?</h3><p class="mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">Ask me to plan a one-page mystery, shape a short story, or help with your current page.</p></div></template>
<template x-for="(item,index) in messages" :key="item.id ?? 'local-'+index"><article class="rounded-xl p-3" :class="item.role==='user' ? 'bg-stone-100 dark:bg-stone-800 ml-4' : 'bg-amber-50/50 dark:bg-stone-950'">
<p class="mb-2 text-xs font-semibold" x-text="item.role==='user' ? 'You' : 'Companion'"></p>
<p class="whitespace-pre-wrap break-words text-sm leading-6" x-text="displayMessage(item)"></p>
<template x-if="item.proposal"><div class="mt-3"><button type="button" class="btn-secondary" @click="fill(item)" :disabled="sending">Fill form draft</button><p class="mt-2 text-xs text-stone-500 dark:text-stone-400">{{ $story ? 'Updates story details and the current page. Changes autosave.' : 'Fills the new-story form on your dashboard. Nothing is saved.' }}</p></div></template>
</article></template>
</div>
<p x-show="notice" x-text="notice" role="status" class="px-5 py-2 text-xs text-emerald-700 dark:text-emerald-300"></p>
<p x-show="error" x-text="error" role="alert" class="px-5 py-2 text-xs text-red-700 dark:text-red-300"></p>
<form @submit.prevent="send()" class="space-y-3 border-t border-stone-200 dark:border-stone-700 p-5">
<label class="field">Writing model<select x-model="model" @change="remember()" required :disabled="sending"><option value="">Select an OpenRouter model</option><template x-for="m in models" :key="m.id"><option :value="m.id" x-text="modelLabel(m)"></option></template></select></label>
<button x-show="catalogError" @click="loadModels()" type="button" class="text-xs underline">Reload model catalog</button>
<label class="field">Message<textarea x-model="prompt" required maxlength="8000" rows="3" :disabled="sending" placeholder="Help me shape an idea…"></textarea></label>
<div class="flex items-center justify-between gap-2"><a href="{{ route('ai.edit') }}" class="text-xs text-stone-500 dark:text-stone-400">@if(auth()->user()->openrouter_key)Using personal key @else<span x-text="'$'+remaining.toFixed(2)+' credit left'"></span>@endif</a><button class="btn-primary" :disabled="sending" x-text="sending ? 'Writing…' : 'Send ↑'"></button></div>
</form>
</aside>
