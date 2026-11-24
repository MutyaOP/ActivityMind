{{--
    Отчёт об активности за компьютером за один день: плитки, лента дня и программы.
    Используется на странице «Активность» и в админке (страница пользователя).
    Слот — правая колонка рядом со списком программ.
--}}
@props(['day', 'isToday' => false, 'showBrowserNote' => true])
@php $maxApp = $day['apps'][0]['sec'] ?? 1; @endphp

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-tile :label="$isToday ? __('За компьютером сегодня') : __('За компьютером')" :value="$day['active_label']"
                 :hint="__('С :from до :to · простой: :idle', ['from' => $day['first_at'], 'to' => $day['last_at'], 'idle' => $day['idle_label']])" />
    <x-stat-tile :label="__('Работа и учёба')" :value="$day['productive_pct'].'%'" :progress="$day['productive_pct']"
                 :hint="__('Доля времени в рабочих и учебных программах')" />
    <x-stat-tile :label="__('Отдых и развлечения')" :value="$day['rest_pct'].'%'" :progress="$day['rest_pct']"
                 :hint="__('Мессенджеры, игры, видео')" />
    <x-stat-tile :label="__('Переключений между программами')" :value="$day['switches']"
                 :hint="__('Программ за день: :count', ['count' => count($day['apps'])])" />
</div>

{{-- Лента дня --}}
<div class="am-card mt-4">
    <h3 class="am-card-title">{{ __('Лента дня') }}</h3>
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
        <h3 class="am-card-title">{{ __('Программы') }}</h3>
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
        @if ($showBrowserNote && collect($day['apps'])->contains('is_browser', true))
            <p class="mt-4 text-xs text-slate-500">{{ __('Для прошлых дней браузер показан общим временем, без вкладок.') }}</p>
        @endif
    </div>

    <div class="space-y-4">
        {{ $slot }}
    </div>
</div>
