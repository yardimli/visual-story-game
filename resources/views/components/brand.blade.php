@props(['href' => '/'])
<a href="{{ $href }}" {{ $attributes->class(['inline-flex shrink-0 items-center gap-3']) }} aria-label="Visual Story Game home">
    <span class="vsg-mark">VSG<span class="text-amber-400">.</span></span>
    <span class="hidden font-serif text-lg font-semibold tracking-tight sm:block">Visual Story Game</span>
</a>
