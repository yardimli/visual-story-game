@props(['value'])
<label {{ $attributes->merge(['class' => 'block text-xs font-semibold text-stone-600 dark:text-stone-300']) }}>{{ $value ?? $slot }}</label>
