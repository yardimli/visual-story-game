<x-studio-layout :companion="false" title="Your account">
    @if (isset($header))<header class="mx-auto max-w-7xl px-6 pt-8">{{ $header }}</header>@endif
    <main>{{ $slot }}</main>
</x-studio-layout>
