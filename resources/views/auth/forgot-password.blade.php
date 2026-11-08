<x-guest-layout>
<p class="eyebrow">A FRESH START</p><h2 class="mt-3 font-serif text-3xl">Find your way back.</h2><p class="mb-7 mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">We will send you a link to reset your password.</p>
    <div class="mb-4 text-sm text-gray-600 dark:text-stone-300">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
<p class="mt-6 text-center text-sm text-stone-500 dark:text-stone-400"><a href="{{ route('login') }}" class="underline underline-offset-4">Back to sign in</a></p>
</x-guest-layout>
