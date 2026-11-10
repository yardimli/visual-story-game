<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><x-favicon />
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name') }} · {{ request()->routeIs('register') ? 'Create your account' : 'Your creative studio' }}</title>
<x-theme-init />@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-100 dark:bg-stone-950 text-stone-900 dark:text-stone-100 antialiased">
<x-toasts />
<header class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-6 lg:px-8"><x-brand /><x-theme-toggle /></header>
<main class="mx-auto grid min-h-[calc(100vh-180px)] max-w-7xl items-center gap-12 px-5 py-8 lg:grid-cols-2 lg:gap-24 lg:px-8">
<aside class="hidden lg:block">
<p class="eyebrow">A PLACE FOR YOUR IMAGINATION</p><h1 class="mt-6 font-serif text-6xl leading-[1.08]">Every great adventure<br>starts with <em class="text-amber-800 dark:text-amber-200">a little<br>curiosity.</em></h1>
<p class="mt-7 max-w-md text-base leading-7 text-stone-500 dark:text-stone-400">Plan a scene. Follow an idea. Build a world that invites someone to look a little closer.</p>
<div class="story-cover mt-10 max-w-lg rounded-2xl p-8 text-white"><p class="text-[10px] uppercase tracking-[.2em] text-amber-200">YOUR NEXT CHAPTER</p><p class="mt-4 font-serif text-2xl leading-relaxed">“The door was locked.<br>The story was just beginning.”</p><div class="mt-6 flex items-center gap-3 text-xs text-white/70"><span class="h-px w-8 bg-white/30"></span>One scene. A short story. An entire novel.</div></div>
</aside>
<section class="mx-auto w-full max-w-md">
<div class="rounded-2xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm sm:p-9">{{ $slot }}</div>
<p class="mt-6 text-center text-xs text-stone-400"><a href="{{ url('/') }}" class="hover:text-amber-700">← Back to Visual Story Game</a></p>
</section>
</main>
<footer class="mx-auto max-w-7xl px-5 py-7 text-xs text-stone-400 lg:px-8">VSG · A world of stories, one page at a time.</footer>
</body></html>
