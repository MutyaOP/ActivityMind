{{-- Строка «что сейчас происходит на устройстве» (страница «Активность» и админка) --}}
@php $current = $device->currentApp(); @endphp
<div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-white/[0.05] py-2 text-sm last:border-0">
    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $current ? 'bg-emerald-400' : ($device->isOnline(5) && $device->is_paused ? 'bg-brand-400' : 'bg-slate-600') }}"></span>
    <span class="text-slate-100">{{ $device->name }}</span>
    <span class="text-slate-400">
        @if ($current)
            {{ __('открыто :app', ['app' => \App\Services\ActivityDayService::appLabel($current)]) }}
            @if ($device->current_since)
                · {{ \App\Services\AnalyticsService::humanDuration((int) $device->current_since->diffInSeconds(now(), true)) }}
            @endif
        @elseif ($device->isOnline(5) && $device->is_paused)
            {{ __('учёт на паузе') }}
        @elseif ($device->isOnline(5))
            {{ __('на связи, активности сейчас нет') }}
        @elseif ($device->last_sync_at)
            {{ __('агент не в сети · последняя синхронизация :ago', ['ago' => $device->last_sync_at->copy()->locale(app()->getLocale())->diffForHumans()]) }}
        @else
            {{ __('агент ещё не подключался') }}
        @endif
    </span>
</div>
