<x-app-layout>
    <x-slot name="header">{{ __('Дашборд') }}</x-slot>

    {{-- Приветствие + запущенный таймер --}}
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-[28px] leading-9">{{ __('Сегодня') }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('Фокус, задачи и привычки за день и последние 7 дней') }}</p>
        </div>

        @if ($runningEntry)
            <a href="{{ route('timer.index') }}" class="flex items-center gap-4 rounded-xl border border-white/[0.07] bg-surface-800 px-4 py-3 transition-colors hover:bg-surface-700"
                 x-data="{
                    start: new Date('{{ $runningEntry->started_at->toIso8601String() }}'),
                    now: new Date(),
                    get label() {
                        const s = Math.max(0, Math.floor((this.now - this.start) / 1000));
                        const p = n => String(n).padStart(2, '0');
                        return p(Math.floor(s / 3600)) + ':' + p(Math.floor(s % 3600 / 60)) + ':' + p(s % 60);
                    }
                 }"
                 x-init="setInterval(() => now = new Date(), 1000)">
                <span class="am-rec"></span>
                <div>
                    <div class="text-xs text-slate-500">{{ __('Таймер идёт') }}</div>
                    <div class="text-sm text-slate-100">{{ $runningEntry->task?->title ?? __('Без задачи') }}</div>
                </div>
                <div class="am-num text-xl text-slate-100" x-text="label"></div>
            </a>
        @endif
    </div>

    {{-- Плитки --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile :label="__('Фокус сегодня')"
                     :value="$today['focus_label']"
                     :progress="$today['goal_percent']"
                     :hint="__('Цель: :min мин · :pct%', ['min' => $today['goal_min'], 'pct' => $today['goal_percent']])" />

        <x-stat-tile :label="__('Задач выполнено сегодня')"
                     :value="$today['tasks_done']"
                     :hint="__('Активных задач в списке: :count', ['count' => $activeTasks->count()])" />

        <x-stat-tile :label="__('Привычки сегодня')"
                     :value="$today['habits_done'] . ' / ' . $today['habits_total']"
                     :progress="$today['habits_total'] ? (int) round($today['habits_done'] / $today['habits_total'] * 100) : 0" />

        <x-stat-tile :label="__('Продуктивность дня')"
                     :value="$today['score'] . ' / 100'"
                     :hint="__('Среднее за неделю: :avg · фокус за неделю: :focus', ['avg' => $week['avg_score'], 'focus' => $week['focus_label']])" />
    </div>

    {{-- Графики --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="am-card xl:col-span-2">
            <h3 class="am-card-title">{{ __('Неделя: фокус и продуктивность') }}</h3>
            <div class="relative h-72">
                <canvas id="weekChart" data-config='@json(['labels' => $week['labels'], 'focus' => $week['focus'], 'scores' => $week['scores']])'></canvas>
            </div>
        </div>

        <div class="am-card">
            <h3 class="am-card-title">{{ __('Время по категориям (7 дней)') }}</h3>
            @if (count($categories['labels']))
                <div class="relative h-72">
                    <canvas id="categoryChart" data-config='@json($categories)'></canvas>
                </div>
            @else
                <p class="py-16 text-center text-sm text-slate-500">{{ __('Пока нет записей времени.') }}<br>{{ __('Запустите таймер — и здесь появится распределение.') }}</p>
            @endif
        </div>
    </div>

    {{-- Задачи, привычки, рекомендации --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="am-card xl:col-span-2">
            <div class="mb-4 flex items-center justify-between"><h3 class="font-medium text-slate-100">{{ __('Ближайшие задачи') }}</h3><a href="{{ route('tasks.index') }}" class="text-sm am-link">{{ __('Все задачи') }}</a></div>
            @forelse ($activeTasks as $task)
                <div class="flex items-center justify-between gap-3 border-b border-white/[0.06] py-3 last:border-0">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $task->title }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            @if ($task->category)
                                <span class="am-badge am-badge-slate">{{ __($task->category->name) }}</span>
                            @endif
                            @if ($task->due_date)
                                <span class="{{ $task->is_overdue ? 'text-red-300' : '' }}">
                                    {{ $task->is_overdue ? __('Просрочено') . ' · ' : __('До') . ' ' }}{{ $task->due_date->format('d.m.Y') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <span class="shrink-0
                        {{ ['high' => 'am-badge am-badge-rose', 'medium' => 'am-badge am-badge-amber', 'low' => 'am-badge am-badge-slate'][$task->priority] }}">
                        {{ ['high' => __('высокий'), 'medium' => __('средний'), 'low' => __('низкий')][$task->priority] }}
                    </span>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">{{ __('Активных задач нет.') }} <a href="{{ route('tasks.create') }}" class="am-link">{{ __('Создать задачу') }}</a></p>
            @endforelse
        </div>

        <div class="space-y-4">
            <div class="am-card">
                <div class="mb-4 flex items-center justify-between"><h3 class="font-medium text-slate-100">{{ __('Привычки на сегодня') }}</h3><a href="{{ route('habits.index') }}" class="text-sm am-link">{{ __('Все') }}</a></div>
                @forelse ($habits as $habit)
                    <div class="flex items-center gap-3 py-2 text-sm">
                        <form method="POST" action="{{ route('habits.toggle', $habit) }}">
                            @csrf
                            <button type="submit" title="{{ __('Отметить') }}"
                                    class="grid h-5 w-5 place-items-center rounded-full border text-[11px] {{ $habit->done_today ? 'border-slate-200 bg-slate-200 text-surface-900' : 'border-white/20 text-transparent hover:border-white/40' }}">✓</button>
                        </form>
                        <span class="{{ $habit->done_today ? 'text-slate-500 line-through' : '' }}">{{ $habit->title }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('Привычек пока нет.') }} <a href="{{ route('habits.create') }}" class="am-link">{{ __('Добавить') }}</a></p>
                @endforelse
            </div>

            <div class="am-card">
                <div class="mb-4 flex items-center justify-between"><h3 class="font-medium text-slate-100">{{ __('Рекомендации') }}</h3><a href="{{ route('recommendations.index') }}" class="text-sm am-link">{{ __('Все') }}</a></div>
                @forelse ($recommendations as $rec)
                    <div class="flex gap-2 border-b border-white/[0.05] py-2.5 text-sm text-slate-300 last:border-0"><x-icon name="bulb" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" /><span>{{ $rec->text }}</span></div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('Новых рекомендаций нет.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
