<x-guest-layout>
<p class="eyebrow">ONE LAST STEP</p><h2 class="mt-3 font-serif text-3xl">Check your inbox.</h2><p class="mb-7 mt-3 text-sm leading-6 text-stone-500 dark:text-stone-400">Verify your email to complete your account setup.</p>
    <div class="mb-4 text-sm text-gray-600 dark:text-stone-300">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 dark:text-stone-300 hover:text-gray-900 dark:hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
