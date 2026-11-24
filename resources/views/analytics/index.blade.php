<x-app-layout>
    <x-slot name="header">{{ __('Аналитика') }}</x-slot>

    {{-- Период --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="am-tabs">
            @foreach ([7 => __(':n дней', ['n' => 7]), 30 => __(':n дней', ['n' => 30]), 90 => __(':n дней', ['n' => 90])] as $d => $label)
                <a href="{{ route('analytics.index', ['days' => $d]) }}"
                   class="am-tab {{ $days === $d ? 'am-tab-active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        @if ($summary['focus_sec'] === 0)
            <span class="text-sm text-slate-500">{{ __('За период нет записей времени — запустите таймер.') }}</span>
        @endif
    </div>

    {{-- Плитки --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile :label="__('Фокус за период')" icon="target"
                     :value="$summary['focus_label']"
                     :hint="$summary['trend'] === null ? __('Нет данных для сравнения с прошлым периодом') : (($summary['trend'] >= 0 ? '▲ +' : '▼ ').__(':pct% к предыдущим :days дням', ['pct' => $summary['trend'], 'days' => $days]))" />

        <x-stat-tile :label="__('В среднем за активный день')" icon="clock" tone="sky"
                     :value="$summary['avg_focus_label']"
                     :hint="__('Активных дней: :n из :days', ['n' => $summary['active_days'], 'days' => $days]).($summary['best_day'] ? ' · '.__('рекорд: :day (:focus)', ['day' => $summary['best_day']['label'], 'focus' => $summary['best_day']['focus_label']]) : '')" />

        <x-stat-tile :label="__('Средняя продуктивность')" icon="chart" tone="amber"
                     :value="$summary['avg_score'].' / 100'"
                     :hint="__('Цель по фокусу выполнена: :n из :days дней', ['n' => $summary['goal_days'], 'days' => $days])" />

        <x-stat-tile :label="__('Задач выполнено')" icon="check" tone="green"
                     :value="$summary['tasks_done']"
                     :hint="$summary['habit_rate'] !== null ? __('Привычки: :pct% выполнения', ['pct' => $summary['habit_rate']]) : __('Привычек пока нет')" />
    </div>

    {{-- Динамика --}}
    <div class="am-card mt-6">
        <h3 class="am-card-title">{{ __('Динамика: фокус и продуктивность') }}</h3>
        <div class="relative h-72">
            <canvas id="analyticsDaily" data-config='@json($daily)'></canvas>
        </div>
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <div class="am-card">
            <h3 class="am-card-title">{{ __('В какие часы вы работаете') }}</h3>
            <div class="relative h-64">
                <canvas id="analyticsHours" data-config='@json($hours)'></canvas>
            </div>
        </div>

        <div class="am-card">
            <h3 class="am-card-title">{{ __('Фокус по дням недели (среднее, ч)') }}</h3>
            <div class="relative h-64">
                <canvas id="analyticsWeekdays" data-config='@json($weekdays)'></canvas>
            </div>
        </div>

        <div class="am-card">
            <h3 class="am-card-title">{{ __('Время по категориям') }}</h3>
            @if (count($categories['labels']))
                <div class="relative h-64">
                    <canvas id="analyticsCategories" data-config='@json($categories)'></canvas>
                </div>
            @else
                <p class="py-16 text-center text-sm text-slate-500">{{ __('Нет данных по категориям.') }}</p>
            @endif
        </div>

        <div class="am-card">
            <h3 class="am-card-title">{{ __('Топ приложений (Desktop Agent)') }}</h3>
            @if (count($apps['labels']))
                <div class="relative h-64">
                    <canvas id="analyticsApps" data-config='@json($apps)'></canvas>
                </div>
            @else
                <p class="py-16 text-center text-sm text-slate-500">{{ __('Данных от Desktop Agent пока нет.') }} <a href="{{ route('devices.index') }}" class="am-link">{{ __('Подключить устройство') }}</a></p>
            @endif
        </div>
    </div>

    {{-- Выводы --}}
    <div class="am-card mt-6">
        <h3 class="am-card-title">{{ __('Выводы') }}</h3>
        <ul class="space-y-2 text-sm text-slate-300">
            @foreach ($insights as $line)
                <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-slate-500"></span><span>{{ $line }}</span></li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
