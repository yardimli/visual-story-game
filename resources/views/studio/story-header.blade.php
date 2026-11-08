<header class="story-editor-bar flex min-w-0 items-center gap-4 border-b border-amber-200 bg-amber-100 px-5 py-2 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
<a href="{{ route('dashboard') }}" class="shrink-0 text-xs hover:underline">← All stories</a>
<h1 class="min-w-0 flex-1 truncate font-serif text-lg" title="{{ $story->title }}">{{ $story->title }}</h1>
<span class="shrink-0 whitespace-nowrap text-[10px] opacity-70" x-text="'Draft · revision '+revision"></span>
</header>
