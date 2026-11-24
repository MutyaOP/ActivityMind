{{-- Общая часть <head> для всех страниц: шрифты, стили и тема (до отрисовки, чтобы не было мигания) --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'ActivityMind' }}</title>
@if (($theme ?? 'dark') === 'system')
    <script>
        document.documentElement.dataset.theme = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    </script>
@endif
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
{{-- Подписи графиков (resources/js/charts.js) на языке интерфейса --}}
@php
    $chartLabels = collect(['Фокус, ч', 'Продуктивность', 'часы', 'баллы', 'ч', 'Часы работы', 'Среднее, ч', 'Часы'])
        ->mapWithKeys(fn ($k) => [$k => __($k)]);
@endphp
<script>
    window.AM_I18N = {!! $chartLabels->toJson(JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
