@php
    $locale = app()->getLocale();
    $dateLabel = \Illuminate\Support\Str::ucfirst($date->locale($locale)->isoFormat('dddd, D MMMM'));
    $dayLink = fn (?string $d) => route('admin.users.show', $d ? [$user, 'date' => $d] : [$user]);
    $statusLabels = ['todo' => __('К выполнению'), 'in_progress' => __('В работе'), 'done' => __('Выполнено')];
    $statusStyles = ['todo' => 'am-badge am-badge-slate', 'in_progress' => 'am-badge am-badge-amber', 'done' => 'am-badge am-badge-green'];
    $frequency = ['daily' => __('ежедневно'), 'weekly' => __('еженедельно')];
@endphp
<x-app-layout>
    <x-slot name="header">{{ $user->name }}</x-slot>

    <a href="{{ route('admin.users.index') }}" class="am-btn-ghost -ml-2 mb-4">
        <x-icon name="back" class="h-4 w-4" /> {{ __('Все пользователи') }}
    </a>

    {{-- Карточка пользователя --}}
    <div class="am-card mb-4 flex flex-wrap items-center gap-x-8 gap-y-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-surface-700 text-lg text-slate-200">
                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-base text-slate-100">
                    <span class="truncate">{{ $user->name }}</span>
                    <span class="am-badge {{ $user->isAdmin() ? 'am-badge-brand' : 'am-badge-slate' }}">{{ $user->isAdmin() ? __('Администратор') : __('Пользователь') }}</span>
                </div>
                <div class="truncate text-sm text-slate-400">{{ $user->email }}</div>
            </div>
        </div>
        <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
            <div><dt class="text-xs text-slate-500">{{ __('Регистрация') }}</dt><dd class="text-slate-200">{{ $user->created_at->setTimezone($tz)->format('d.m.Y') }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('Последняя активность') }}</dt><dd class="text-slate-200">{{ $lastSeen ? $lastSeen->locale($locale)->diffForHumans() : '—' }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('Часовой пояс') }}</dt><dd class="text-slate-200">{{ $tz }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('Язык') }}</dt><dd class="text-slate-200">{{ \App\Support\Preferences::LOCALES[$user->locale] ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Сводка за 7 дней --}}
    <h2 class="mb-3 mt-6 text-sm text-slate-400">{{ __('Последние 7 дней') }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile :label="__('Фокус по таймеру')" :value="$summary['focus']" />
        <x-stat-tile :label="__('За компьютером')" :value="$summary['computer']" :hint="__('По данным Desktop Agent')" />
        <x-stat-tile :label="__('Задач выполнено')" :value="$summary['tasks_done']" :hint="__('Открытых задач: :count', ['count' => $summary['tasks_open']])" />
        <x-stat-tile :label="__('Активных привычек')" :value="$summary['habits']" />
    </div>

    {{-- Активность за компьютером по дням --}}
    <div class="mb-4 mt-8 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-[15px] text-slate-100">{{ __('Активность за компьютером') }}</h2>
            <div class="flex items-center gap-1">
                <a href="{{ $dayLink($prevDate) }}" class="am-btn-ghost" aria-label="{{ __('Предыдущий день') }}">←</a>
                <span class="px-1 text-sm text-slate-200">{{ $isToday ? __('Сегодня') : $dateLabel }}</span>
                @if ($nextDate)
                    <a href="{{ $dayLink($nextDate) }}" class="am-btn-ghost" aria-label="{{ __('Следующий день') }}">→</a>
                @endif
            </div>
        </div>
        <form method="GET" action="{{ route('admin.users.show', $user) }}" class="flex items-center gap-2">
            <label for="date" class="text-sm text-slate-500">{{ __('Открыть день') }}</label>
            <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now($tz)->toDateString() }}"
                   class="am-input !mt-0 !w-auto" onchange="this.form.submit()">
        </form>
    </div>
    <p class="-mt-2 mb-4 text-xs text-slate-500">{{ __('Администратор видит только программы и время в них — без заголовков окон и вкладок браузера.') }}</p>

    @if ($day['active_sec'] === 0)
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="am-card grid place-items-center py-14 text-center text-sm text-slate-500 xl:col-span-2">
                {{ __('За этот день данных от Desktop Agent нет.') }}
            </div>
            <div>@include('activity._days', ['dayLink' => $dayLink])</div>
        </div>
    @else
        <x-activity-report :day="$day" :is-today="$isToday" :show-browser-note="false">
            @include('activity._days', ['dayLink' => $dayLink])
        </x-activity-report>
    @endif

    {{-- Задачи, таймер, привычки, устройства --}}
    <div class="mt-8 grid gap-4 xl:grid-cols-2">
        <div class="am-card">
            <h3 class="am-card-title"><x-icon name="check" class="h-4 w-4" />{{ __('Задачи') }}</h3>
            @forelse ($tasks as $task)
                <div class="flex items-center justify-between gap-3 border-b border-white/[0.05] py-2 text-sm last:border-0">
                    <span class="min-w-0">
                        <span class="block truncate {{ $task->status === 'done' ? 'text-slate-500 line-through' : 'text-slate-100' }}">{{ $task->title }}</span>
                        <span class="text-xs text-slate-500">
                            {{ $task->category ? __($task->category->name) : __('Без категории') }}
                            @if ($task->due_date) · {{ __('срок') }} {{ $task->due_date->format('d.m.Y') }} @endif
                        </span>
                    </span>
                    <span class="{{ $statusStyles[$task->status] ?? 'am-badge' }} shrink-0">{{ $statusLabels[$task->status] ?? $task->status }}</span>
                </div>
            @empty
                <p class="am-empty !py-6">{{ __('Задач пока нет.') }}</p>
            @endforelse
        </div>

        <div class="am-card">
            <h3 class="am-card-title"><x-icon name="timer" class="h-4 w-4" />{{ __('Последние записи таймера') }}</h3>
            @forelse ($entries as $entry)
                <div class="flex items-center justify-between gap-3 border-b border-white/[0.05] py-2 text-sm last:border-0">
                    <span class="min-w-0">
                        <span class="block truncate text-slate-100">{{ $entry->task?->title ?? $entry->note ?? __('Без задачи') }}</span>
                        <span class="text-xs text-slate-500">{{ $entry->started_at->setTimezone($tz)->locale($locale)->isoFormat('D MMM, HH:mm') }}</span>
                    </span>
                    <span class="am-num shrink-0 text-slate-300">
                        {{ $entry->duration_sec !== null ? \App\Services\AnalyticsService::humanDuration($entry->duration_sec) : __('идёт') }}
                    </span>
                </div>
            @empty
                <p class="am-empty !py-6">{{ __('Записей таймера пока нет.') }}</p>
            @endforelse
        </div>

        <div class="am-card">
            <h3 class="am-card-title"><x-icon name="repeat" class="h-4 w-4" />{{ __('Привычки') }}</h3>
            @forelse ($habits as $habit)
                <div class="flex items-center justify-between gap-3 border-b border-white/[0.05] py-2 text-sm last:border-0">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $habit->color ?: '#85858b' }}"></span>
                        <span class="truncate text-slate-100">{{ $habit->title }}</span>
                    </span>
                    <span class="shrink-0 text-xs text-slate-500">{{ $frequency[$habit->frequency] ?? $habit->frequency }} · {{ $habit->target_per_period }}×</span>
                </div>
            @empty
                <p class="am-empty !py-6">{{ __('Привычек пока нет.') }}</p>
            @endforelse
        </div>

        <div class="am-card">
            <h3 class="am-card-title"><x-icon name="device" class="h-4 w-4" />{{ __('Устройства') }}</h3>
            @forelse ($devices as $device)
                @include('activity._device-state', ['device' => $device])
            @empty
                <p class="am-empty !py-6">{{ __('Desktop Agent не подключён.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
