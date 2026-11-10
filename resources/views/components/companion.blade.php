@props(['story' => null, 'page' => null])
@php
$initial = $story ? $story->messages()->latest('id')->limit(40)->get()->reverse()->values() : [];
$options = ['endpoint'=>$story ? route('chat.store',$story) : route('companion.store'), 'changesBase'=>$story ? url('/stories/'.$story->id.'/messages') : null, 'history'=>$story ? null : route('companion.history'), 'messages'=>$initial, 'model'=>$story?->llm_model ?? '', 'pageId'=>$page?->id, 'position'=>$page?->position, 'imageStudio'=>$story && $page ? route('images.studio',[$story,$page]) : null, 'revision'=>$story?->revision, 'remaining'=>auth()->user()->availableCredit()/1000000];
@endphp
<aside class="companion-panel" aria-label="Creative companion" @vsg-models-reload.window="loadModels()" @vsg-credit.window="remaining=$event.detail" x-data="creativeCompanion(@js($options))">
<header class="border-b border-stone-200 dark:border-stone-700 px-4 py-3"><h2 class="font-serif text-lg">Creative companion</h2></header>
<div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3" x-ref="history" aria-live="polite">
<template x-if="!messages.length"><div><h3 class="font-serif text-2xl">What shall we make?</h3><p class="mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">Ask me to plan a one-page mystery, shape a short story, or help with your current page.</p></div></template>
<template x-for="(item,index) in messages" :key="item.id ?? 'local-'+index"><article class="rounded-xl p-3" :class="item.role==='user' ? 'bg-stone-100 dark:bg-stone-800 ml-4' : 'bg-amber-50/50 dark:bg-stone-950'">

<p class="break-words text-sm leading-6"><span class="whitespace-pre-wrap" x-text="(item.streaming?streamingMessage(item.content).text:displayMessage(item)).trimEnd()"></span>
<template x-if="item.proposal"><span x-data="proposalReview(item)">
<a href="#" class="text-xs underline underline-offset-2 hover:no-underline" @click.prevent="$refs.reviewDialog.showModal()" aria-haspopup="dialog">Review proposed changes</a>.
<template x-teleport="body"><dialog x-ref="reviewDialog" class="proposal-review-dialog rounded-xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100" aria-label="Review proposed changes" @click="if($event.target===$el)$el.close()">
<div class="flex h-full min-h-0 flex-col"><header class="flex shrink-0 items-center justify-between border-b p-4 dark:border-stone-700"><h2 class="font-serif text-xl">Review proposed changes</h2><button type="button" class="btn-secondary" @click="$refs.reviewDialog.close()">Close</button></header><div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5">
<p x-show="item.proposal._review?.error" class="text-xs text-red-600 dark:text-red-300" x-text="item.proposal._review?.error"></p>
<ul class="list-disc space-y-3 pl-4"><template x-for="entry in entries" :key="entry.id"><li class="text-xs"><div class="flex items-start gap-2"><input type="checkbox" class="mt-1 shrink-0 rounded" :value="String(entry.id)" x-model="chosen" @change="toggle()" :disabled="unavailable(entry) || applying" :aria-label="'Apply '+entry.title"><section class="min-w-0 flex-1"><h3 class="break-words leading-5 font-semibold" x-text="entry.title"></h3><div class="mt-2 space-y-2"><template x-if="entry.before!==undefined"><div><p class="font-semibold text-stone-500">Current</p><div x-html="structuredPreview(entry.before)"></div></div></template><p class="font-semibold">Proposed result</p><template x-if="entry.rich_delta"><div class="rich-proposal-preview" x-html="richPreview(entry.rich_delta)"></div></template><div x-show="!entry.rich_delta" x-html="structuredPreview(entry.after)"></div></div></section></div><p x-show="unavailable(entry)" class="mt-1 text-stone-500">Select the object preparation to enable this step.</p></li></template></ul>
</div><footer class="shrink-0 border-t p-4 dark:border-stone-700"><button type="button" class="btn-primary w-full" :disabled="sending || applying || !chosen.length" @click="applyReviewed(item,chosen)" x-text="applying?'Applying…':'Confirm '+chosen.length+' selected change'+(chosen.length===1?'':'s')"></button></footer>
</div></dialog></template>
</span></template></p>
<p x-show="item.streaming" class="mt-2 flex items-center gap-1 text-xs text-stone-500 dark:text-stone-400" role="status"><span x-text="streamingMessage(item.content).preparing?'Preparing changes':(!item.content?'Thinking':'Writing')"></span><span class="companion-stream-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span></p>
<p x-show="item.interrupted" class="mt-2 text-xs text-stone-500">Response interrupted. No proposed changes were applied.</p>
</article></template>
<p x-show="sending && waitingForResponse" class="flex items-center gap-1 p-3 text-sm text-stone-500 dark:text-stone-400" role="status"><span>Thinking</span><span class="companion-stream-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span></p>
</div>
<form @submit.prevent="send()" class="space-y-2 border-t border-stone-200 dark:border-stone-700 p-3">
<div class="field"><textarea aria-label="Message" x-model="prompt" @keydown.enter="if (!$event.shiftKey &amp;&amp; !$event.isComposing &amp;&amp; $event.keyCode !== 229) { $event.preventDefault(); if (!$event.repeat &amp;&amp; !sending) $event.target.form.requestSubmit(); }" required maxlength="8000" rows="2" :disabled="sending" placeholder="Message…&#10;Enter to send · Shift+Enter for a new line"></textarea></div>
<div class="flex items-center gap-2"><div class="field min-w-0 flex-1"><select aria-label="Writing model" class="companion-model" x-model="model" @change="remember()" required :disabled="sending"><option value="">Select model</option><template x-for="m in models" :key="m.id"><option :value="m.id" x-text="modelLabel(m)"></option></template></select></div><button class="btn-primary companion-send" :disabled="sending" x-text="sending ? 'Writing…' : 'Send ↑'"></button></div>
<div class="flex items-center gap-3 text-[10px] text-stone-500 dark:text-stone-400"><a href="{{ route('ai.edit') }}">@if(auth()->user()->openrouter_key)Using personal key @else<span x-text="'$'+remaining.toFixed(2)+' credit left'"></span>@endif</a><button type="button" class="underline hover:text-stone-900 dark:hover:text-white" @click="$refs.companionHelp.showModal()" aria-haspopup="dialog">Help</button></div>
</form>
@include('studio.companion-help')
</aside>
