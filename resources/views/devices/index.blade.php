<x-app-layout>
    <x-slot name="header">Устройства</x-slot>

    {{-- Токен показывается один раз --}}
    @if (session('new_token'))
        <div class="am-card mb-6 border-brand-400/40" x-data="{ copied: false }">
            <h3 class="am-card-title !mb-1"><x-icon name="key" class="h-4 w-4 text-brand-400" />Токен для «{{ session('new_device') }}»</h3>
            <p class="mt-1 text-sm text-slate-400">Скопируйте его сейчас и вставьте в <code class="am-code">config.ini</code> агента. Повторно показать токен нельзя — при потере выпустите новый.</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <code id="newToken" class="flex-1 break-all rounded-xl border border-white/10 bg-surface-700 px-3 py-2 font-mono text-sm text-slate-100">{{ session('new_token') }}</code>
                <button type="button" class="am-btn"
                        @click="navigator.clipboard.writeText(document.getElementById('newToken').innerText); copied = true">
                    <span x-text="copied ? 'Скопировано ✓' : 'Копировать'"></span>
                </button>
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="am-card xl:col-span-2">
            <h3 class="am-card-title">Ваши устройства</h3>

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
                                Последняя синхронизация: {{ $device->last_sync_at->copy()->locale('ru')->diffForHumans() }}
                            @else
                                Ещё не синхронизировалось
                            @endif
                            · записей активности: {{ $device->activity_logs_count }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-sm">
                        <form method="POST" action="{{ route('devices.token', $device) }}"
                              onsubmit="return confirm('Выпустить новый токен? Старый перестанет работать.')">
                            @csrf
                            <button class="am-btn-ghost">Новый токен</button>
                        </form>
                        <form method="POST" action="{{ route('devices.destroy', $device) }}"
                              onsubmit="return confirm('Удалить устройство и всю собранную им активность?')">
                            @csrf @method('DELETE')
                            <button class="am-btn-danger">Удалить</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">Устройств пока нет. Добавьте первое справа.</p>
            @endforelse
        </div>

        <div class="space-y-4">
            <form method="POST" action="{{ route('devices.store') }}" class="am-card">
                @csrf
                <h3 class="am-card-title">Добавить устройство</h3>
                <label class="am-label" for="name">Название</label>
                <input id="name" name="name" type="text" required maxlength="100" value="{{ old('name') }}" class="am-input" placeholder="Например: Ноутбук">
                @error('name') <p class="am-error">{{ $message }}</p> @enderror
                <button class="am-btn mt-4 w-full">Создать и получить токен</button>
            </form>

            <div class="am-card text-sm text-slate-300">
                <h3 class="am-card-title">Как подключить агента</h3>
                <ol class="list-decimal space-y-1 pl-5 text-slate-400">
                    <li>Создайте устройство и скопируйте токен.</li>
                    <li>В папке <code class="am-code">desktop-agent</code> скопируйте <code class="am-code">config.example.ini</code> в <code class="am-code">config.ini</code>.</li>
                    <li>Впишите <code class="am-code">url = {{ url('/') }}</code> и <code class="am-code">token = …</code>.</li>
                    <li>Проверьте: <code class="am-code">python agent.py --check</code>, затем запустите: <code class="am-code">python agent.py</code>.</li>
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>
