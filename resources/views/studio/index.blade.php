<x-studio-layout :companion="false">
<main @companion-filled.window="create=true" class="mx-auto max-w-7xl px-5 py-12 lg:px-8" x-data="{create: {{ $errors->any() ? 'true' : 'false' }} }">
<div class="mb-10 flex flex-wrap items-end justify-between gap-6">
<div><p class="eyebrow">YOUR NEXT WORLD STARTS HERE</p><h1 class="mt-3 font-serif text-4xl md:text-5xl">Stories in the making.</h1><p class="mt-4 max-w-xl text-stone-500 dark:text-stone-400">A single page or an entire adventure. Plan the story, shape the world, and bring every page to life.</p></div>
<button @click="create = !create" class="btn-primary">+ Create a story</button></div>
<section x-show="create" x-cloak class="mb-10 rounded-2xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6">
<form data-companion-form="create" action="{{ route('stories.store') }}" method="POST" class="grid gap-5 md:grid-cols-2">@csrf
<label class="field">Story title<input name="title" required maxlength="160" value="{{ old('title') }}" placeholder="The last light in the harbour"></label>
<label class="field">Length<select name="page_count"><option value="1">Single-page story · 1 page</option>@foreach(range(2,100) as $n)<option value="{{ $n }}" @selected(old('page_count')==$n)>{{ $n }} pages{{ $n<=5?' · short story':' · novel' }}</option>@endforeach</select></label>
<label class="field md:col-span-2">Premise<textarea name="premise" rows="3" required maxlength="5000" placeholder="Who is here, what do they want, and what stands in their way?">{{ old('premise') }}</textarea></label>
<label class="field">Genre<select name="genre">@foreach(['Mystery','Fantasy','Science fiction','Adventure','Horror','Drama'] as $genre)<option>{{ $genre }}</option>@endforeach</select></label>
<div class="flex items-end gap-3"><button class="btn-primary">Create draft →</button><button type="button" @click="create=false" class="btn-secondary">Cancel</button></div>
</form></section>
<div class="mb-5 flex items-center justify-between"><h2 class="text-sm font-semibold">Your projects <span class="ml-2 text-stone-400">{{ $stories->count() }}</span></h2><span class="text-xs text-stone-400">Most recently edited</span></div>
<div class="grid gap-6 md:grid-cols-2 2xl:grid-cols-3">
@forelse($stories as $story)
<article class="group overflow-hidden rounded-2xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 transition hover:-translate-y-1 hover:shadow-lg">
<div class="story-cover flex h-44 items-end justify-between p-6"><span class="font-serif text-5xl text-white/80">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><span class="rounded-full bg-white/15 px-3 py-1 text-xs text-white backdrop-blur">{{ $story->genre }}</span></div>
<div class="p-6"><div class="mb-3 flex items-center justify-between"><span class="eyebrow">{{ $story->pages_count===1?'SINGLE PAGE':($story->pages_count<=5?'SHORT STORY':'NOVEL') }}</span><span class="badge">Draft</span></div><h3 class="font-serif text-2xl"><a href="{{ route('stories.edit',$story) }}">{{ $story->title }}</a></h3><p class="mt-3 text-xs text-stone-500 dark:text-stone-400">{{ $story->ready_pages_count }} / {{ $story->pages_count }} page plans complete · {{ filled($story->bible) ? 'Bible ready' : 'Bible needed' }}</p><p class="mt-2 text-xs text-stone-400">Production: not started · Media tools in development</p><p class="mt-3 line-clamp-2 text-sm leading-6 text-stone-500 dark:text-stone-400">{{ $story->premise }}</p><div class="mt-6 flex justify-between border-t border-stone-100 dark:border-stone-800 pt-4 text-xs text-stone-400"><span>{{ $story->pages_count }} {{ Str::plural('page',$story->pages_count) }}</span><span>Edited {{ $story->updated_at->diffForHumans() }} ↗</span></div></div>
<div class="flex justify-between px-6 pb-5 text-sm"><a class="underline" href="{{ route('stories.edit',$story) }}">Open story →</a><a class="underline" href="{{ route('dashboard',['history'=>$story->id]) }}#story-history">History</a></div></article>
@empty
<div class="col-span-full rounded-2xl border border-dashed border-stone-300 dark:border-stone-600 bg-white/60 dark:bg-stone-900/60 px-8 py-16 text-center"><div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-full bg-amber-100 dark:bg-amber-950 font-serif text-3xl">✧</div><h3 class="font-serif text-2xl">Every world begins with an idea.</h3><p class="mx-auto mt-3 max-w-md text-sm leading-6 text-stone-500 dark:text-stone-400">Create a story to open your workspace. Your writing, page plans, reference images, and AI collaborator will live together.</p><button @click="create=true" class="btn-primary mt-6">Start your first story</button></div>
@endforelse</div>
@if($historyStory)
<section id="story-history" class="mt-10 rounded-xl border border-stone-200 p-5 dark:border-stone-700">
<div class="mb-4 flex justify-between gap-3"><h2 class="font-serif text-xl">History · {{ $historyStory->title }}</h2><a class="text-sm underline" href="{{ route('dashboard') }}">Close</a></div>
<p class="mb-4 text-sm text-stone-500">Restore an earlier draft without losing the current one.</p>
@forelse($revisions as $rev)
<div class="mb-3 flex items-center justify-between gap-3 rounded-lg border border-stone-200 p-3 dark:border-stone-700"><div><p class="text-sm">Revision {{ $rev->revision }} · {{ $rev->reason }}</p><p class="text-xs text-stone-400">{{ $rev->created_at->diffForHumans() }}</p></div><form method="POST" action="{{ route('stories.restore',[$historyStory,$rev]) }}">@csrf<input type="hidden" name="revision" value="{{ $historyStory->revision }}"><button class="btn-secondary">Restore</button></form></div>
@empty<p class="text-sm text-stone-400">Your first save will create a revision.</p>@endforelse
</section>
@endif
<div class="mt-12 grid gap-6 border-t border-stone-200 dark:border-stone-700 pt-8 text-sm md:grid-cols-3">
<div><span class="eyebrow">01 / PLAN</span><p class="mt-2 text-stone-500 dark:text-stone-400">Shape the journey as you create.</p></div><div><span class="eyebrow">02 / CREATE</span><p class="mt-2 text-stone-500 dark:text-stone-400">Build consistent scenes from your references.</p></div><div><span class="eyebrow">03 / BRING TO LIFE</span><p class="mt-2 text-stone-500 dark:text-stone-400">Add motion, discoveries, and little surprises.</p></div></div>
</main></x-studio-layout>
