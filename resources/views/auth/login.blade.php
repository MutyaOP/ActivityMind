<x-guest-layout>
    <h1 class="mb-1 text-2xl">{{ __('Вход') }}</h1>
    <p class="mb-8 text-sm text-slate-400">{{ __('Ваши задачи, таймер и статистика') }}</p>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-5">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="mt-2"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="am-check" name="remember">
                <span class="ms-2 text-sm text-slate-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="mt-8 flex items-center justify-between">
            @if (Route::has('password.request'))
                <a class="text-sm text-slate-400 underline decoration-white/20 underline-offset-4 transition-colors hover:text-slate-100" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
