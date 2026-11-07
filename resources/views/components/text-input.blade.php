@props(['disabled' => false])
<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 text-sm text-stone-900 dark:text-stone-100 shadow-sm focus:border-amber-500 focus:ring-amber-500']) }}>
