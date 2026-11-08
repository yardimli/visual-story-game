@props(['story' => null, 'page' => null])
@php
$initial = $story ? $story->messages()->latest('id')->limit(40)->get()->reverse()->values() : [];
$options = ['endpoint'=>$story ? route('chat.store',$story) : route('companion.store'), 'changesBase'=>$story ? url('/stories/'.$story->id.'/messages') : null, 'history'=>$story ? null : route('companion.history'), 'messages'=>$initial, 'model'=>$story?->llm_model ?? '', 'pageId'=>$page?->id, 'position'=>$page?->position, 'imageStudio'=>$story && $page ? route('images.studio',[$story,$page]) : null, 'revision'=>$story?->revision, 'remaining'=>max(0,config('story.allowance')-auth()->user()->llm_spent-auth()->user()->llm_reserved)/1000000];
@endphp
<aside class="companion-panel" aria-label="Creative companion" @vsg-models-reload.window="loadModels()" @vsg-credit.window="remaining=$event.detail" x-data="creativeCompanion(@js($options))">
<header class="border-b border-stone-200 dark:border-stone-700 px-4 py-3"><h2 class="font-serif text-lg">Creative companion</h2></header>
<div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3" x-ref="history" aria-live="polite">
<template x-if="!messages.length"><div><h3 class="font-serif text-2xl">What shall we make?</h3><p class="mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">Ask me to plan a one-page mystery, shape a short story, or help with your current page.</p></div></template>
<template x-for="(item,index) in messages" :key="item.id ?? 'local-'+index"><article class="rounded-xl p-3" :class="item.role==='user' ? 'bg-stone-100 dark:bg-stone-800 ml-4' : 'bg-amber-50/50 dark:bg-stone-950'">
<p class="mb-2 text-xs font-semibold" x-text="item.role==='user' ? 'You' : 'Companion'"></p>
<p class="whitespace-pre-wrap break-words text-sm leading-6" x-text="displayMessage(item)"></p>
<template x-if="item.proposal"><div class="mt-3 space-y-3" x-data="proposalReview(item)">
<p class="text-xs font-semibold">Review proposed changes</p>
<p x-show="item.proposal._review?.error" class="text-xs text-red-600 dark:text-red-300" x-text="item.proposal._review?.error"></p>
<ul class="list-disc space-y-3 pl-4"><template x-for="entry in entries" :key="entry.id"><li class="text-xs"><div class="flex items-start gap-2"><input type="checkbox" class="mt-1 shrink-0 rounded" :value="String(entry.id)" x-model="chosen" @change="toggle()" :disabled="unavailable(entry) || applying" :aria-label="'Apply '+entry.title"><details class="min-w-0 flex-1"><summary class="cursor-pointer break-words leading-5" x-text="entry.title"></summary><div class="mt-2 space-y-2"><template x-if="entry.before!==undefined"><div><p class="font-semibold text-stone-500">Current</p><p class="whitespace-pre-wrap break-words" x-text="result(entry.before)"></p></div></template><p class="font-semibold">Proposed result</p><p class="whitespace-pre-wrap break-words" x-text="result(entry.after)"></p></div></details></div><p x-show="unavailable(entry)" class="mt-1 text-stone-500">Select the object preparation to enable this step.</p></li></template></ul>
<button type="button" class="btn-secondary w-full" :disabled="sending || applying || !chosen.length" @click="applyReviewed(item,chosen)" x-text="applying?'Applying…':'Confirm '+chosen.length+' selected change'+(chosen.length===1?'':'s')"></button>
</div></template>
</article></template>
</div>
<form @submit.prevent="send()" class="space-y-2 border-t border-stone-200 dark:border-stone-700 p-3">
<div class="field"><textarea aria-label="Message" x-model="prompt" @keydown.enter="if (!$event.shiftKey &amp;&amp; !$event.isComposing &amp;&amp; $event.keyCode !== 229) { $event.preventDefault(); if (!$event.repeat &amp;&amp; !sending) $event.target.form.requestSubmit(); }" required maxlength="8000" rows="2" :disabled="sending" placeholder="Message…&#10;Enter to send · Shift+Enter for a new line"></textarea></div>
<div class="flex items-center gap-2"><div class="field min-w-0 flex-1"><select aria-label="Writing model" class="companion-model" x-model="model" @change="remember()" required :disabled="sending"><option value="">Select model</option><template x-for="m in models" :key="m.id"><option :value="m.id" x-text="modelLabel(m)"></option></template></select></div><button class="btn-primary companion-send" :disabled="sending" x-text="sending ? 'Writing…' : 'Send ↑'"></button></div>
<a href="{{ route('ai.edit') }}" class="block text-[10px] text-stone-500 dark:text-stone-400">@if(auth()->user()->openrouter_key)Using personal key @else<span x-text="'$'+remaining.toFixed(2)+' credit left'"></span>@endif</a>
</form>
</aside>
