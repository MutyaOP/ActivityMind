<x-app-layout>
    <x-slot name="header">{{ __('Настройки') }}</x-slot>

    @php
        $themes = [
            'dark'   => [__('Тёмная'), 'moon', __('Графитовый фон, светлый текст')],
            'light'  => [__('Светлая'), 'sun', __('Светлый фон, тёмный текст')],
            'system' => [__('Как в системе'), 'device', __('Следует настройке Windows')],
        ];
        $currentTheme = old('theme', $user->theme ?? 'dark');
        $currentLocale = old('locale', $user->locale ?? app()->getLocale());
    @endphp

    <form method="POST" action="{{ route('settings.update') }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PATCH')

        <section class="am-card sm:p-8">
            <h2 class="am-card-title"><x-icon name="sun" class="h-[18px] w-[18px]" /> {{ __('Тема оформления') }}</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($themes as $value => [$label, $icon, $hint])
                    <label class="group relative cursor-pointer">
                        <input type="radio" name="theme" value="{{ $value }}" class="peer sr-only" @checked($currentTheme === $value)>
                        <span class="block rounded-xl border border-white/[0.08] bg-surface-700 p-3 transition-colors group-hover:border-white/20 peer-checked:border-brand-400 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400/70">
                            {{-- Мини-превью темы --}}
                            <span data-theme="{{ $value === 'system' ? 'dark' : $value }}"
                                  class="mb-3 flex h-16 overflow-hidden rounded-lg border border-white/[0.08] bg-surface-900">
                                <span class="w-1/4 border-r border-white/[0.06] bg-surface-950"></span>
                                <span class="flex flex-1 flex-col gap-1.5 p-2">
                                    <span class="h-1.5 w-2/3 rounded-full bg-slate-100"></span>
                                    <span class="h-1.5 w-1/2 rounded-full bg-slate-500"></span>
                                    <span class="mt-auto h-4 w-full rounded bg-surface-800 ring-1 ring-white/[0.07]"></span>
                                </span>
                                @if ($value === 'system')
                                    <span data-theme="light" class="flex w-1/3 flex-col gap-1.5 border-l border-white/[0.08] bg-surface-900 p-2">
                                        <span class="h-1.5 w-2/3 rounded-full bg-slate-100"></span>
                                        <span class="mt-auto h-4 w-full rounded bg-surface-800 ring-1 ring-white/[0.07]"></span>
                                    </span>
                                @endif
                            </span>
                            <span class="flex items-center gap-2 text-sm text-slate-100">
                                <x-icon :name="$icon" class="h-4 w-4 text-slate-500" /> {{ $label }}
                            </span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('theme') <p class="am-error">{{ $message }}</p> @enderror
        </section>

        <section class="am-card sm:p-8">
            <h2 class="am-card-title"><x-icon name="globe" class="h-[18px] w-[18px]" /> {{ __('Язык интерфейса') }}</h2>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach (\App\Support\Preferences::LOCALES as $code => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="locale" value="{{ $code }}" class="peer sr-only" @checked($currentLocale === $code)>
                        <span class="flex h-11 items-center gap-3 rounded-xl border border-white/[0.08] bg-surface-700 px-3 text-sm text-slate-200 transition-colors hover:border-white/20 peer-checked:border-brand-400 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400/70">
                            <span class="am-num w-6 text-xs text-slate-500">{{ \App\Support\Preferences::SHORT[$code] }}</span>
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('locale') <p class="am-error">{{ $message }}</p> @enderror
        </section>

        <div class="flex items-center gap-3">
            <button class="am-btn">{{ __('Сохранить') }}</button>
            <a href="{{ route('profile.edit') }}" class="am-btn-ghost">{{ __('Профиль и пароль') }}</a>
        </div>
    </form>
</x-app-layout>
