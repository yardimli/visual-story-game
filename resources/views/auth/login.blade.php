<x-guest-layout>
<p class="eyebrow">WELCOME BACK</p><h2 class="mt-3 font-serif text-3xl">Your stories are waiting.</h2><p class="mb-7 mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">Sign in and pick up where your imagination left off.</p>
<x-auth-session-status class="mb-4" :status="session('status')" />
<form method="POST" action="{{ route('login') }}" class="space-y-5">@csrf
<div><x-input-label for="email" value="Email address" /><x-text-input id="email" class="mt-2 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
<div><x-input-label for="password" value="Password" /><x-text-input id="password" class="mt-2 w-full" type="password" name="password" required autocomplete="current-password" /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
<div class="flex flex-wrap items-center justify-between gap-3 text-xs"><label for="remember_me" class="inline-flex items-center gap-2 text-stone-500 dark:text-stone-400"><input id="remember_me" type="checkbox" class="rounded border-stone-300 dark:border-stone-600 text-amber-700 dark:text-amber-300 focus:ring-amber-500" name="remember">Remember me</label><a href="{{ route('password.request') }}" class="text-stone-600 dark:text-stone-300 underline-offset-4 hover:underline">Forgot password?</a></div>
<x-primary-button class="w-full">Sign in →</x-primary-button>
</form>
<div class="mt-7 border-t border-stone-200 dark:border-stone-700 pt-6 text-center text-sm text-stone-500 dark:text-stone-400">New to VSG? <a href="{{ route('register') }}" class="font-semibold text-stone-900 dark:text-stone-100 underline underline-offset-4">Create an account</a></div>
</x-guest-layout>
