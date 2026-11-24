{{-- Другие дни --}}
<div class="am-card">
    <h3 class="am-card-title">Другие дни</h3>
    <div class="space-y-0.5">
        @foreach ($recent as $d => $sec)
            @php $dd = \Carbon\CarbonImmutable::parse($d, $tz); @endphp
            <a href="{{ route('activity.index', $dd->isToday() ? [] : ['date' => $d]) }}"
               class="flex items-center justify-between rounded-lg px-2 py-1.5 text-sm transition-colors {{ $d === $date->toDateString() ? 'bg-surface-700 text-slate-100' : 'text-slate-400 hover:bg-white/[0.04] hover:text-slate-100' }}">
                <span>{{ $dd->isToday() ? 'Сегодня' : \Illuminate\Support\Str::ucfirst($dd->locale('ru')->isoFormat('dd, D MMM')) }}</span>
                <span class="am-num {{ $sec ? '' : 'text-slate-600' }}">{{ $sec ? \App\Services\AnalyticsService::humanDuration($sec) : '—' }}</span>
            </a>
        @endforeach
    </div>
</div>
