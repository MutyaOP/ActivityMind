@php
    $dateLabel = \Illuminate\Support\Str::ucfirst($date->locale('ru')->isoFormat('dddd, D MMMM'));
    $maxApp = $day['apps'][0]['sec'] ?? 1;
@endphp
<x-app-layout>
    <x-slot name="header">Активность</x-slot>

    {{-- Навигация по дням --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="am-tabs">
                <a href="{{ route('activity.index') }}" class="am-tab {{ $isToday ? 'am-tab-active' : '' }}">Текущий сеанс</a>
                <a href="{{ route('activity.index', ['date' => $isToday ? $prevDate : $date->toDateString()]) }}"
                   class="am-tab {{ $isToday ? '' : 'am-tab-active' }}">По дням</a>
            </div>
            @unless ($isToday)
                <div class="flex items-center gap-1">
                    <a href="{{ route('activity.index', ['date' => $prevDate]) }}" class="am-btn-ghost" aria-label="Предыдущий день">←</a>
                    <span class="px-1 text-sm text-slate-200">{{ $dateLabel }}</span>
                    <a href="{{ route('activity.index', ['date' => $nextDate]) }}" class="am-btn-ghost" aria-label="Следующий день">→</a>
                </div>
            @endunless
        </div>
        <form method="GET" action="{{ route('activity.index') }}" class="flex items-center gap-2">
            <label for="date" class="text-sm text-slate-500">Открыть день</label>
            <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now($tz)->toDateString() }}"
                   class="am-input !mt-0 !w-auto" onchange="this.form.submit()">
        </form>
    </div>

    {{-- Сейчас: состояние агентов --}}
    @if ($isToday)
        <div class="am-card mb-4">
            <h3 class="am-card-title !mb-3"><x-icon name="device" class="h-4 w-4" />Сейчас</h3>
            @forelse ($devices as $device)
                @php $current = $device->currentApp(); @endphp
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-white/[0.05] py-2 text-sm last:border-0">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $current ? 'bg-emerald-400' : ($device->isOnline(5) && $device->is_paused ? 'bg-brand-400' : 'bg-slate-600') }}"></span>
                    <span class="text-slate-100">{{ $device->name }}</span>
                    <span class="text-slate-400">
                        @if ($current)
                            открыто {{ \App\Services\ActivityDayService::appLabel($current) }}
                            @if ($device->current_since)
                                · {{ \App\Services\AnalyticsService::humanDuration((int) $device->current_since->diffInSeconds(now(), true)) }}
                            @endif
                        @elseif ($device->isOnline(5) && $device->is_paused)
                            учёт на паузе
                        @elseif ($device->isOnline(5))
                            на связи, активности сейчас нет
                        @elseif ($device->last_sync_at)
                            агент не в сети · последняя синхронизация {{ $device->last_sync_at->copy()->locale('ru')->diffForHumans() }}
                        @else
                            агент ещё не подключался
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Чтобы видеть время в программах, подключите Desktop Agent. <a href="{{ route('devices.index') }}" class="am-link">Добавить устройство</a></p>
            @endforelse
        </div>
    @endif

    @if ($day['active_sec'] === 0)
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="am-card grid place-items-center py-14 text-center text-sm text-slate-500 xl:col-span-2">
                {{ $isToday ? 'Сегодня агент ещё не прислал активность. Данные появляются раз в минуту, пока агент запущен.' : 'За этот день данных от Desktop Agent нет.' }}
            </div>
            <div>@include('activity._days')</div>
        </div>
    @else
        {{-- Плитки --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile :label="$isToday ? 'За компьютером сегодня' : 'За компьютером'" :value="$day['active_label']"
                         :hint="'С '.$day['first_at'].' до '.$day['last_at'].' · простой: '.$day['idle_label']" />
            <x-stat-tile label="Работа и учёба" :value="$day['productive_pct'].'%'" :progress="$day['productive_pct']"
                         hint="Доля времени в рабочих и учебных программах" />
            <x-stat-tile label="Отдых и развлечения" :value="$day['rest_pct'].'%'" :progress="$day['rest_pct']"
                         hint="Мессенджеры, игры, видео" />
            <x-stat-tile label="Переключений между программами" :value="$day['switches']"
                         :hint="'Программ за день: '.count($day['apps'])" />
        </div>

        {{-- Лента дня --}}
        <div class="am-card mt-4">
            <h3 class="am-card-title">Лента дня</h3>
            <div class="relative h-9 overflow-hidden rounded-lg bg-surface-700">
                @foreach ($day['timeline']['segments'] as $seg)
                    <div class="absolute inset-y-0" title="{{ $seg['hint'] }}"
                         style="left: {{ $seg['left'] }}%; width: {{ $seg['width'] }}%; background: {{ $seg['color'] }};"></div>
                @endforeach
            </div>
            <div class="am-num mt-2 flex justify-between text-xs text-slate-500">
                @foreach (range($day['timeline']['from'], $day['timeline']['to']) as $h)
                    <span>{{ sprintf('%02d', $h % 24) }}</span>
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
                @foreach ($day['categories'] as $cat)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-sm" style="background: {{ $cat['color'] }}"></span>{{ $cat['name'] }} · {{ $cat['label'] }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-3">
            {{-- Программы --}}
            <div class="am-card xl:col-span-2">
                <h3 class="am-card-title">Программы</h3>
                <div class="space-y-3">
                    @foreach ($day['apps'] as $app)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate text-slate-100">
                                    {{ $app['name'] }}
                                    <span class="ml-1 text-xs text-slate-500">{{ $app['category'] }}</span>
                                </span>
                                <span class="am-num shrink-0 text-slate-300">{{ $app['label'] }} <span class="text-xs text-slate-500">· {{ $app['share'] }}%</span></span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-700">
                                <div class="h-full rounded-full" style="width: {{ max(1, $app['sec'] / $maxApp * 100) }}%; background: {{ $app['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if (! $isToday && collect($day['apps'])->contains('is_browser', true))
                    <p class="mt-4 text-xs text-slate-500">Для прошлых дней браузер показан общим временем, без вкладок.</p>
                @endif
            </div>

            <div class="space-y-4">
                {{-- Вкладки браузера (только текущий сеанс) --}}
                @if ($isToday)
                    <div class="am-card">
                        <h3 class="am-card-title !mb-1">Вкладки браузера</h3>
                        <p class="mb-3 text-xs text-slate-500">Вкладки, на которых сегодня провели от 5 минут</p>
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
                            <p class="py-4 text-sm text-slate-500">Пока нет вкладок, открытых дольше 5 минут.</p>
                        @endforelse
                    </div>
                @endif

                @include('activity._days')
            </div>
        </div>
    @endif

    @if ($isToday)
        {{-- Текущий сеанс обновляется раз в минуту, пока вкладка на экране --}}
        <script>
            setInterval(() => { if (!document.hidden) location.reload(); }, 60000);
        </script>
    @endif
</x-app-layout>
