@php
    $dateLabel = \Illuminate\Support\Str::ucfirst($date->locale(app()->getLocale())->isoFormat('dddd, D MMMM'));
@endphp
<x-app-layout>
    <x-slot name="header">{{ __('Активность') }}</x-slot>

    {{-- Навигация по дням --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="am-tabs">
                <a href="{{ route('activity.index') }}" class="am-tab {{ $isToday ? 'am-tab-active' : '' }}">{{ __('Текущий сеанс') }}</a>
                <a href="{{ route('activity.index', ['date' => $isToday ? $prevDate : $date->toDateString()]) }}"
                   class="am-tab {{ $isToday ? '' : 'am-tab-active' }}">{{ __('По дням') }}</a>
            </div>
            @unless ($isToday)
                <div class="flex items-center gap-1">
                    <a href="{{ route('activity.index', ['date' => $prevDate]) }}" class="am-btn-ghost" aria-label="{{ __('Предыдущий день') }}">←</a>
                    <span class="px-1 text-sm text-slate-200">{{ $dateLabel }}</span>
                    <a href="{{ route('activity.index', ['date' => $nextDate]) }}" class="am-btn-ghost" aria-label="{{ __('Следующий день') }}">→</a>
                </div>
            @endunless
        </div>
        <form method="GET" action="{{ route('activity.index') }}" class="flex items-center gap-2">
            <label for="date" class="text-sm text-slate-500">{{ __('Открыть день') }}</label>
            <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now($tz)->toDateString() }}"
                   class="am-input !mt-0 !w-auto" onchange="this.form.submit()">
        </form>
    </div>

    {{-- Сейчас: состояние агентов --}}
    @if ($isToday)
        <div class="am-card mb-4">
            <h3 class="am-card-title !mb-3"><x-icon name="device" class="h-4 w-4" />{{ __('Сейчас') }}</h3>
            @forelse ($devices as $device)
                @include('activity._device-state', ['device' => $device])
            @empty
                <p class="text-sm text-slate-500">{{ __('Чтобы видеть время в программах, подключите Desktop Agent.') }} <a href="{{ route('devices.index') }}" class="am-link">{{ __('Добавить устройство') }}</a></p>
            @endforelse
        </div>
    @endif

    @if ($day['active_sec'] === 0)
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="am-card grid place-items-center py-14 text-center text-sm text-slate-500 xl:col-span-2">
                {{ $isToday ? __('Сегодня агент ещё не прислал активность. Данные появляются раз в минуту, пока агент запущен.') : __('За этот день данных от Desktop Agent нет.') }}
            </div>
            <div>@include('activity._days')</div>
        </div>
    @else
        <x-activity-report :day="$day" :is-today="$isToday" :show-browser-note="! $isToday">
            {{-- Вкладки браузера (только текущий сеанс) --}}
            @if ($isToday)
                <div class="am-card">
                    <h3 class="am-card-title !mb-1">{{ __('Вкладки браузера') }}</h3>
                    <p class="mb-3 text-xs text-slate-500">{{ __('Вкладки, на которых сегодня провели от 5 минут') }}</p>
                    @forelse ($day['tabs'] as $tab)
                        <div class="flex items-baseline justify-between gap-3 border-b border-white/[0.05] py-2 text-sm last:border-0">
                            <span class="min-w-0">
                                <span class="block truncate text-slate-100" title="{{ $tab['title'] }}">{{ $tab['title'] }}</span>
                                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $tab['color'] }}"></span>{{ $tab['category'] }} · {{ $tab['browser'] }}
                                </span>
                            </span>
                            <span class="am-num shrink-0 text-slate-300">{{ $tab['label'] }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">{{ __('Пока нет вкладок, открытых дольше 5 минут.') }}</p>
                    @endforelse
                </div>
            @endif

            @include('activity._days')
        </x-activity-report>
    @endif

    @if ($isToday)
        {{-- Текущий сеанс обновляется раз в минуту, пока вкладка на экране --}}
        <script>
            setInterval(() => { if (!document.hidden) location.reload(); }, 60000);
        </script>
    @endif
</x-app-layout>
