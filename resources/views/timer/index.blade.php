<x-app-layout>
    <x-slot name="header">{{ __('Таймер') }}</x-slot>

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Основной блок: идёт таймер или форма запуска --}}
        <div class="am-card xl:col-span-2">
            @if ($running)
                <div x-data="{
                        start: new Date('{{ $running->started_at->toIso8601String() }}'),
                        now: new Date(),
                        get label() {
                            const s = Math.max(0, Math.floor((this.now - this.start) / 1000));
                            const p = n => String(n).padStart(2, '0');
                            return p(Math.floor(s / 3600)) + ':' + p(Math.floor(s % 3600 / 60)) + ':' + p(s % 60);
                        }
                     }"
                     x-init="setInterval(() => now = new Date(), 1000)"
                     class="py-6 text-center">
                    <div class="mb-6 inline-flex items-center justify-center gap-2 text-sm text-slate-400">
                        <span class="am-rec"></span>
                        {{ __('Идёт запись') }}
                    </div>
                    <div class="am-num text-6xl text-slate-100 md:text-[80px] md:leading-none" x-text="label"></div>
                    <div class="mt-6 text-slate-200">{{ $running->task?->title ?? __('Без задачи') }}</div>
                    <div class="mt-1 text-xs text-slate-500">
                        {{ __($running->category?->name ?? 'Без категории') }}
                        @if ($running->note) · {{ $running->note }} @endif
                        · {{ __('начато в') }} {{ $running->started_at->copy()->setTimezone($tz)->format('H:i') }}
                    </div>

                    <form method="POST" action="{{ route('timer.stop') }}" class="mt-6">
                        @csrf
                        <button class="am-btn h-11 px-6"><x-icon name="stop" class="h-4 w-4" />{{ __('Остановить') }}</button>
                    </form>
                </div>
            @else
                <h2 class="am-card-title text-lg">{{ __('Запустить таймер') }}</h2>
                <form method="POST" action="{{ route('timer.start') }}" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="am-label" for="task_id">{{ __('Задача') }}</label>
                        <select id="task_id" name="task_id" class="am-input">
                            <option value="">— {{ __('без задачи') }} —</option>
                            @foreach ($tasks as $t)
                                <option value="{{ $t->id }}" @selected(old('task_id', request('task_id')) == $t->id)>{{ $t->title }}</option>
                            @endforeach
                        </select>
                        @error('task_id') <p class="am-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="am-label" for="category_id">{{ __('Категория') }}</label>
                        <select id="category_id" name="category_id" class="am-input">
                            <option value="">— {{ __('как у задачи') }} —</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ __($c->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="am-label" for="note">{{ __('Заметка') }}</label>
                        <input id="note" name="note" type="text" maxlength="255" value="{{ old('note') }}" class="am-input" placeholder="{{ __('Над чем работаете (необязательно)') }}">
                    </div>
                    <div class="md:col-span-2">
                        <button class="am-btn h-11 px-6"><x-icon name="play" class="h-4 w-4" />{{ __('Старт') }}</button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Итог дня + ручная запись --}}
        <div class="space-y-4">
            <div class="am-card">
                <div class="text-sm text-slate-400">{{ __('Записано сегодня') }}</div>
                <div class="am-num mt-3 text-[28px] leading-8 text-slate-100">{{ $todayLabel }}</div>
            </div>

            <details class="am-card" @if ($errors->has('started_at') || $errors->has('ended_at')) open @endif>
                <summary class="cursor-pointer font-medium text-slate-100">{{ __('Добавить запись вручную') }}</summary>
                <form method="POST" action="{{ route('timer.manual') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="am-label !text-xs !text-slate-400" for="m_start">{{ __('Начало') }}</label>
                        <input id="m_start" name="started_at" type="datetime-local" required value="{{ old('started_at') }}" class="am-input">
                        @error('started_at') <p class="am-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="am-label !text-xs !text-slate-400" for="m_end">{{ __('Конец') }}</label>
                        <input id="m_end" name="ended_at" type="datetime-local" required value="{{ old('ended_at') }}" class="am-input">
                        @error('ended_at') <p class="am-error">{{ $message }}</p> @enderror
                    </div>
                    <select name="task_id" class="am-input">
                        <option value="">— {{ __('без задачи') }} —</option>
                        @foreach ($tasks as $t)
                            <option value="{{ $t->id }}">{{ $t->title }}</option>
                        @endforeach
                    </select>
                    <select name="category_id" class="am-input">
                        <option value="">— {{ __('без категории') }} —</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ __($c->name) }}</option>
                        @endforeach
                    </select>
                    <input name="note" type="text" maxlength="255" placeholder="{{ __('Заметка') }}" class="am-input">
                    <button class="am-btn w-full">{{ __('Добавить') }}</button>
                </form>
            </details>
        </div>
    </div>

    {{-- История --}}
    <div class="am-card mt-6 overflow-x-auto">
        <h3 class="am-card-title">{{ __('История записей') }}</h3>
        <table class="am-table w-full text-left text-sm">
            <thead class="text-slate-400">
                <tr>
                    <th class="pb-3">{{ __('Дата и время') }}</th>
                    <th class="pb-3">{{ __('Задача') }}</th>
                    <th class="pb-3">{{ __('Категория') }}</th>
                    <th class="pb-3">{{ __('Длительность') }}</th>
                    <th class="pb-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.05]">
                @forelse ($entries as $e)
                    <tr>
                        <td class="py-3 text-slate-300">
                            {{ $e->started_at->copy()->setTimezone($tz)->format('d.m H:i') }}
                            – {{ $e->ended_at->copy()->setTimezone($tz)->format('H:i') }}
                        </td>
                        <td class="py-3">
                            {{ $e->task?->title ?? '—' }}
                            @if ($e->note) <span class="text-xs text-slate-500">· {{ $e->note }}</span> @endif
                        </td>
                        <td class="py-3">
                            @if ($e->category)
                                <span class="am-badge am-badge-slate">{{ __($e->category->name) }}</span>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </td>
                        <td class="py-3">{{ \App\Services\AnalyticsService::humanDuration((int) $e->duration_sec) }}</td>
                        <td class="py-3 text-right">
                            <form method="POST" action="{{ route('timer.destroy', $e) }}" onsubmit="return confirm(@js(__('Удалить запись?')))">
                                @csrf @method('DELETE')
                                <button class="am-btn-danger">{{ __('Удалить') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('Записей пока нет — запустите таймер.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $entries->links() }}</div>
    </div>
</x-app-layout>
