@php
    $opt = '';
    $statusLabels = ['todo' => __('К выполнению'), 'in_progress' => __('В работе'), 'done' => __('Выполнено')];
    $statusStyles = [
        'todo'        => 'am-badge am-badge-slate',
        'in_progress' => 'am-badge am-badge-sky',
        'done'        => 'am-badge am-badge-green',
    ];
    $priorityLabels = ['high' => __('высокий'), 'medium' => __('средний'), 'low' => __('низкий')];
    $priorityStyles = [
        'high'   => 'am-badge am-badge-rose',
        'medium' => 'am-badge am-badge-amber',
        'low'    => 'am-badge am-badge-slate',
    ];
    $tabs = [
        ''            => [__('Все'), $counts->sum()],
        'todo'        => [__('К выполнению'), $counts['todo'] ?? 0],
        'in_progress' => [__('В работе'), $counts['in_progress'] ?? 0],
        'done'        => [__('Выполнено'), $counts['done'] ?? 0],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">{{ __('Задачи') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="am-tabs">
            @foreach ($tabs as $key => [$label, $count])
                @php $active = (request('status') ?? '') === (string) $key; @endphp
                <a href="{{ request()->fullUrlWithQuery(['status' => $key ?: null, 'page' => null]) }}"
                   class="am-tab {{ $active ? 'am-tab-active' : '' }}">
                    {{ $label }} <span class="ml-1 text-xs opacity-70">{{ $count }}</span>
                </a>
            @endforeach
        </div>
        <a href="{{ route('tasks.create') }}" class="am-btn"><x-icon name="plus" class="h-4 w-4" />{{ __('Новая задача') }}</a>
    </div>

    {{-- Фильтры --}}
    <form method="GET" class="am-card mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="relative sm:col-span-2"><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" /><input name="q" value="{{ request('q') }}" placeholder="{{ __('Поиск по названию') }}" class="am-input pl-9"></div>
        <select name="priority" class="am-input">
            <option value="">{{ __('Любой приоритет') }}</option>
            @foreach ($priorityLabels as $v => $l)
                <option value="{{ $v }}" @selected(request('priority') === $v)>{{ ucfirst($l) }}</option>
            @endforeach
        </select>
        <select name="category_id" class="am-input">
            <option value="">{{ __('Любая категория') }}</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected((string) request('category_id') === (string) $c->id)>{{ __($c->name) }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="am-btn flex-1">{{ __('Применить') }}</button>
            <a href="{{ route('tasks.index') }}" class="am-btn-outline">{{ __('Сброс') }}</a>
        </div>
    </form>

    {{-- Список --}}
    <div class="am-card !p-0">
        @forelse ($tasks as $task)
            @php
                $done = $task->status === 'done';
                $overdue = ! $done && $task->due_date && $task->due_date->toDateString() < $todayKey;
                $spent = (int) $task->time_entries_sum_duration_sec;
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.05] px-5 py-4 transition last:border-0 hover:bg-white/[0.02]">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium {{ $done ? 'text-slate-500 line-through' : '' }}">{{ $task->title }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                        <span class="{{ $statusStyles[$task->status] }}">{{ $statusLabels[$task->status] }}</span>
                        <span class="{{ $priorityStyles[$task->priority] }}">{{ $priorityLabels[$task->priority] }}</span>
                        @if ($task->category)
                            <span class="am-badge am-badge-slate">{{ __($task->category->name) }}</span>
                        @endif
                        @if ($task->due_date)
                            <span class="{{ $overdue ? 'font-semibold text-red-300' : '' }}">{{ $overdue ? __('Просрочено').' · ' : __('До').' ' }}{{ $task->due_date->format('d.m.Y') }}</span>
                        @endif
                        @if ($spent > 0 || $task->estimated_min)
                            <span>
                                ⏱ {{ \App\Services\AnalyticsService::humanDuration($spent) }}
                                @if ($task->estimated_min) / {{ $task->estimated_min }} {{ __('мин') }} @endif
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2 text-sm">
                    @unless ($done)
                        <form method="POST" action="{{ route('timer.start') }}">
                            @csrf
                            <input type="hidden" name="task_id" value="{{ $task->id }}">
                            <button class="am-btn-ghost" title="{{ __('Запустить таймер') }}"><x-icon name="play" class="h-3.5 w-3.5" />{{ __('Таймер') }}</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('tasks.toggle', $task) }}">
                        @csrf @method('PATCH')
                        <button class="am-btn-ghost"><x-icon :name="$done ? 'undo' : 'tick'" class="h-3.5 w-3.5" />{{ $done ? __('Вернуть') : __('Готово') }}</button>
                    </form>
                    <a href="{{ route('tasks.edit', $task) }}" class="am-btn-ghost"><x-icon name="edit" class="h-3.5 w-3.5" /><span class="hidden sm:inline">{{ __('Изменить') }}</span></a>
                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm(@js(__('Удалить задачу?')))">
                        @csrf @method('DELETE')
                        <button class="am-btn-danger" title="{{ __('Удалить') }}"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                    </form>
                </div>
            </div>
        @empty
            <p class="am-empty px-5">
                {{ __('Задач не найдено.') }} <a href="{{ route('tasks.create') }}" class="am-link">{{ __('Создать задачу') }}</a>
            </p>
        @endforelse
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</x-app-layout>
