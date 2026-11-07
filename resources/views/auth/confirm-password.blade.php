<x-guest-layout>
<p class="eyebrow">A QUICK CHECK</p><h2 class="mt-3 font-serif text-3xl">Confirm it is you.</h2><p class="mb-7 mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">Enter your password to continue securely.</p>
    <div class="mb-4 text-sm text-gray-600 dark:text-stone-300">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
<p class="mt-6 text-center text-sm text-stone-500 dark:text-stone-400"><a href="{{ route('login') }}" class="underline underline-offset-4">Back to sign in</a></p>
</x-guest-layout>
