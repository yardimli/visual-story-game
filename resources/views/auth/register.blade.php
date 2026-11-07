<x-guest-layout>
<p class="eyebrow">YOUR FIRST CHAPTER</p><h2 class="mt-3 font-serif text-3xl">Make room for an idea.</h2><p class="mb-7 mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">Create your free account. Start with $1 of included AI writing credit.</p>
<form method="POST" action="{{ route('register') }}" class="space-y-5">@csrf
<div><x-input-label for="name" value="Your name" /><x-text-input id="name" class="mt-2 w-full" name="name" :value="old('name')" required autofocus autocomplete="name" /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
<div><x-input-label for="email" value="Email address" /><x-text-input id="email" class="mt-2 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="you@example.com" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
<div><x-input-label for="password" value="Password" /><x-text-input id="password" class="mt-2 w-full" type="password" name="password" required autocomplete="new-password" /><p class="mt-2 text-xs text-stone-400">At least 8 characters.</p><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
<div><x-input-label for="password_confirmation" value="Confirm password" /><x-text-input id="password_confirmation" class="mt-2 w-full" type="password" name="password_confirmation" required autocomplete="new-password" /><x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" /></div>
<x-primary-button class="w-full">Create account →</x-primary-button>
</form>
<div class="mt-7 border-t border-stone-200 dark:border-stone-700 pt-6 text-center text-sm text-stone-500 dark:text-stone-400">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-stone-900 dark:text-stone-100 underline underline-offset-4">Sign in</a></div>
</x-guest-layout>
