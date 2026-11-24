<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ActivityMind</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="grid min-h-screen lg:grid-cols-[1fr_minmax(480px,40%)]">
        {{-- Левая часть: что делает продукт + фрагмент интерфейса (только на широких экранах) --}}
        <div class="hidden border-r border-white/[0.06] bg-surface-950 p-12 lg:flex lg:flex-col lg:justify-between">
            <a href="/" class="flex items-center gap-2.5 text-base font-medium text-slate-100">
                <x-application-logo /> ActivityMind
            </a>

            <div class="max-w-xl">
                <h2 class="text-[40px] leading-[1.1] tracking-[-0.02em]">Видно, куда ушёл день: задачи, фокус и привычки в одном журнале</h2>
                <p class="mt-4 max-w-md text-lg leading-7 text-slate-400">Таймер и агент для Windows считают время сами, а неделя складывается в график и короткие советы</p>

                {{-- Фрагмент интерфейса: идущий таймер и неделя --}}
                @php $bars = [['Пн', 3.1], ['Вт', 4.2], ['Ср', 2.4], ['Чт', 3.8], ['Пт', 4.6], ['Сб', 1.2], ['Вс', 2.7]]; @endphp
                <div class="mt-10 max-w-md rounded-2xl border border-white/[0.07] bg-surface-800 p-6">
                    <div class="flex items-center gap-3">
                        <span class="am-rec"></span>
                        <span class="flex-1 text-sm text-slate-300">Курсовая работа, глава 2</span>
                        <span class="am-num text-xl text-slate-100">01:24:37</span>
                    </div>
                    <div class="mt-6 flex h-28 items-end gap-3 border-t border-white/[0.06] pt-4">
                        @foreach ($bars as $i => [$d, $h])
                            <div class="flex flex-1 flex-col items-center gap-2">
                                <div class="w-full rounded-sm {{ $i === 6 ? 'bg-brand-400' : 'bg-white/[0.12]' }}" style="height: {{ $h / 4.6 * 72 }}px"></div>
                                <span class="am-num text-xs text-slate-500">{{ $d }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex justify-between text-xs text-slate-500">
                        <span>Фокус за неделю</span><span class="am-num text-slate-300">22 ч 00 мин</span>
                    </div>
                </div>
            </div>

            <p class="text-xs text-slate-600">© {{ date('Y') }} ActivityMind</p>
        </div>

        {{-- Правая часть: форма --}}
        <div class="relative flex flex-col items-center justify-center px-4 pb-12 pt-24 lg:pt-12">
            {{-- Переключатель «Вход / Регистрация» в правом верхнем углу --}}
            <nav class="absolute right-4 top-4 flex items-center gap-1 sm:right-8 sm:top-8" aria-label="Вход и регистрация">
                <a href="{{ route('login') }}"
                   @if (request()->routeIs('login')) aria-current="page" @endif
                   class="inline-flex h-9 items-center rounded-lg px-3.5 text-sm transition-colors {{ request()->routeIs('login') ? 'bg-surface-700 text-slate-100' : 'text-slate-400 hover:bg-white/[0.06] hover:text-slate-100' }}">
                    Войти
                </a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}"
                       @if (request()->routeIs('register')) aria-current="page" @endif
                       class="inline-flex h-9 items-center rounded-lg px-3.5 text-sm transition-colors {{ request()->routeIs('register') ? 'bg-surface-700 text-slate-100' : 'border border-white/[0.12] text-slate-200 hover:bg-white/[0.06] hover:text-slate-100' }}">
                        Зарегистрироваться
                    </a>
                @endif
            </nav>

            <a href="/" class="mb-8 flex items-center gap-2.5 text-lg font-medium text-slate-100 lg:hidden">
                <x-application-logo /> ActivityMind
            </a>
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
