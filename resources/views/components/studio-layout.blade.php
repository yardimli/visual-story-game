@props(['title' => 'Creator studio', 'story' => null, 'page' => null, 'companion' => true])
<!DOCTYPE html>
<html @if($story && $page) class="editor-document" @endif lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><x-favicon /><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} · Visual Story Game</title><x-theme-init />@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="bg-stone-100 dark:bg-stone-950 text-stone-900 dark:text-stone-100 antialiased">
<x-toasts />
<div @class(['min-h-screen','editor-shell'=>$story && $page])>
<header x-data="{ observer:null, init(){this.observer=new ResizeObserver(()=>document.documentElement.style.setProperty('--studio-header-height',this.$el.offsetHeight+'px'));this.observer.observe(this.$el)}, destroy(){this.observer?.disconnect()} }" class="studio-top-header sticky top-0 z-20 border-b border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 px-5 lg:px-9">
<div class="mx-auto flex h-full items-center gap-3">
<x-brand :href="route('dashboard')" />
@if($story)
<div class="studio-header-story flex min-w-0 flex-1 items-center gap-x-3 border-l border-stone-300 pl-3 dark:border-stone-600" x-data="{storyRevision:{{ $story->revision }}}" @vsg-story-revision.window="storyRevision=$event.detail.revision">
<a href="{{ route('dashboard') }}" class="shrink-0 text-xs hover:underline">← All stories</a>
<h1 class="min-w-0 truncate font-serif text-lg" title="{{ $story->title }}">{{ $story->title }}</h1>
<span class="whitespace-nowrap text-[10px] opacity-70" x-text="'Draft · revision '+storyRevision">Draft · revision {{ $story->revision }}</span>
</div>
@endif
<nav class="ml-auto flex shrink-0 items-center gap-3 whitespace-nowrap text-xs"><x-theme-toggle /><a class="hover:text-amber-700" href="{{ route('dashboard') }}">My stories</a><a class="hover:text-amber-700" href="{{ route('ai.edit') }}">AI settings</a><a href="{{ route('profile.edit') }}" class="grid h-7 w-7 place-items-center rounded-full bg-amber-100 dark:bg-amber-950 font-semibold" title="Profile">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="text-stone-500 dark:text-stone-400">Sign out</button></form></nav>
</div></header>
<div @class(['studio-with-companion'=>$companion]) @if($companion) x-data="companionResize()" :style="{'--chat-width':width+'px'}" @pointermove.window="resize($event)" @pointerup.window="finish()" @endif>
<div class="studio-main min-w-0">{{ $slot }}</div>
@if($companion)<div class="companion-splitter" role="separator" aria-label="Resize chat panel" aria-orientation="vertical" tabindex="0" :aria-valuenow="width" aria-valuemin="300" :aria-valuemax="Math.round(window.innerWidth*.65)" @pointerdown.prevent="dragging=true;$el.setPointerCapture($event.pointerId)" @pointercancel="finish()" @keydown.left.prevent="keyboard(20)" @keydown.right.prevent="keyboard(-20)"></div><x-companion :story="$story" :page="$page" />@endif
</div>
</div><x-image-viewer /></body></html>
