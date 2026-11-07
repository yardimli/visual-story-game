@props(['title' => 'Creator studio', 'story' => null, 'page' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} · Visual Story Game</title><x-theme-init />@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="bg-stone-100 dark:bg-stone-950 text-stone-900 dark:text-stone-100 antialiased">
<div class="min-h-screen">
<header class="sticky top-0 z-20 border-b border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 px-5 lg:px-9">
<div class="mx-auto flex min-h-20 max-w-screen-2xl flex-wrap items-center gap-5 py-4">
<x-brand :href="route('dashboard')" />
<nav class="ml-auto flex flex-wrap items-center gap-4 text-sm"><x-theme-toggle /><a class="hover:text-amber-700" href="{{ route('dashboard') }}">My stories</a><a class="hover:text-amber-700" href="{{ route('ai.edit') }}">AI settings</a><a href="{{ route('profile.edit') }}" class="grid h-9 w-9 place-items-center rounded-full bg-amber-100 dark:bg-amber-950 font-semibold" title="Profile">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="text-stone-500 dark:text-stone-400">Sign out</button></form></nav>
</div></header>
@if(session('status'))<div role="status" class="border-b border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950 px-6 py-3 text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</div>@endif
@if($errors->any())<div role="alert" class="border-b border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-6 py-3 text-sm text-red-800 dark:text-red-200"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="studio-with-companion">
<div class="min-w-0">{{ $slot }}</div>
<x-companion :story="$story" :page="$page" />
</div>
</div></body></html>
