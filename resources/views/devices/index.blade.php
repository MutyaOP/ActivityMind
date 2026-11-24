<x-app-layout>
    <x-slot name="header">{{ __('Устройства') }}</x-slot>

    {{-- Токен показывается один раз --}}
    @if (session('new_token'))
        <div class="am-card mb-6 border-brand-400/40" x-data="{ copied: false }">
            <h3 class="am-card-title !mb-1"><x-icon name="key" class="h-4 w-4 text-brand-400" />{{ __('Токен для «:name»', ['name' => session('new_device')]) }}</h3>
            <p class="mt-1 text-sm text-slate-400">{!! __('Скопируйте его сейчас и вставьте в :file агента. Повторно показать токен нельзя — при потере выпустите новый.', ['file' => '<code class="am-code">config.ini</code>']) !!}</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <code id="newToken" class="flex-1 break-all rounded-xl border border-white/10 bg-surface-700 px-3 py-2 font-mono text-sm text-slate-100">{{ session('new_token') }}</code>
                <button type="button" class="am-btn"
                        @click="navigator.clipboard.writeText(document.getElementById('newToken').innerText); copied = true">
                    <span x-text="copied ? @js(__('Скопировано ✓')) : @js(__('Копировать'))"></span>
                </button>
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="am-card xl:col-span-2">
            <h3 class="am-card-title">{{ __('Ваши устройства') }}</h3>

            @forelse ($devices as $device)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.05] py-3 last:border-0">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $device->isOnline() ? 'bg-emerald-400 shadow-[0_0_10px] shadow-emerald-400/60' : 'bg-slate-600' }}"></span>
                            <span class="font-medium">{{ $device->name }}</span>
                            <span class="text-xs text-slate-500">{{ $device->platform }}</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-400">
                            @if ($device->last_sync_at)
                                {{ __('Последняя синхронизация') }}: {{ $device->last_sync_at->copy()->locale(app()->getLocale())->diffForHumans() }}
                            @else
                                {{ __('Ещё не синхронизировалось') }}
                            @endif
                            · {{ __('записей активности') }}: {{ $device->activity_logs_count }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-sm">
                        <form method="POST" action="{{ route('devices.token', $device) }}"
                              onsubmit="return confirm(@js(__('Выпустить новый токен? Старый перестанет работать.')))">
                            @csrf
                            <button class="am-btn-ghost">{{ __('Новый токен') }}</button>
                        </form>
                        <form method="POST" action="{{ route('devices.destroy', $device) }}"
                              onsubmit="return confirm(@js(__('Удалить устройство и всю собранную им активность?')))">
                            @csrf @method('DELETE')
                            <button class="am-btn-danger">{{ __('Удалить') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">{{ __('Устройств пока нет. Добавьте первое справа.') }}</p>
            @endforelse
        </div>

        <div class="space-y-4">
            <form method="POST" action="{{ route('devices.store') }}" class="am-card">
                @csrf
                <h3 class="am-card-title">{{ __('Добавить устройство') }}</h3>
                <label class="am-label" for="name">{{ __('Название') }}</label>
                <input id="name" name="name" type="text" required maxlength="100" value="{{ old('name') }}" class="am-input" placeholder="{{ __('Например: Ноутбук') }}">
                @error('name') <p class="am-error">{{ $message }}</p> @enderror
                <button class="am-btn mt-4 w-full">{{ __('Создать и получить токен') }}</button>
            </form>

            <div class="am-card text-sm text-slate-300">
                <h3 class="am-card-title">{{ __('Как подключить агента') }}</h3>
                <ol class="list-decimal space-y-1 pl-5 text-slate-400">
                    <li>{{ __('Создайте устройство и скопируйте токен.') }}</li>
                    <li>{!! __('В папке :dir скопируйте :src в :dst.', ['dir' => '<code class="am-code">desktop-agent</code>', 'src' => '<code class="am-code">config.example.ini</code>', 'dst' => '<code class="am-code">config.ini</code>']) !!}</li>
                    <li>{!! __('Впишите :url и :token.', ['url' => '<code class="am-code">url = '.e(url('/')).'</code>', 'token' => '<code class="am-code">token = …</code>']) !!}</li>
                    <li>{!! __('Проверьте: :check, затем запустите: :run.', ['check' => '<code class="am-code">python agent.py --check</code>', 'run' => '<code class="am-code">python agent.py</code>']) !!}</li>
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>
