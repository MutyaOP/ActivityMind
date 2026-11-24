{{-- Плитка-показатель: подпись, число и пояснение. Иконка и тон оставлены в props для совместимости, но не рисуются. --}}
@props(['label', 'value', 'hint' => null, 'progress' => null, 'icon' => null, 'tone' => null])
<div {{ $attributes->merge(['class' => 'am-card flex flex-col']) }}>
    <div class="text-sm text-slate-400">{{ $label }}</div>
    <div class="am-num mt-3 text-[28px] leading-8 text-slate-100">{{ $value }}</div>

    @if ($progress !== null)
        <div class="am-progress mt-4"><div style="width: {{ min(100, max(0, $progress)) }}%"></div></div>
    @endif

    @if ($hint)
        <div class="mt-3 text-xs text-slate-500">{{ $hint }}</div>
    @endif
</div>
