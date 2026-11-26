<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ ($theme ?? 'dark') === 'light' ? 'light' : 'dark' }}">
<head>
    @include('layouts.partials.head', ['title' => (isset($header) ? trim(strip_tags($header)).' · ' : '').'ActivityMind'])
</head>
<body class="min-h-screen">
@php
    $user = auth()->user();
    $nav = [
        [__('Дашборд'),      'dashboard',             'home'],
        [__('Задачи'),       'tasks.index',           'check'],
        [__('Таймер'),       'timer.index',           'timer'],
        [__('Привычки'),     'habits.index',          'repeat'],
        [__('Аналитика'),    'analytics.index',       'chart'],
        [__('Активность'),   'activity.index',        'clock'],
        [__('Рекомендации'), 'recommendations.index', 'bulb'],
        [__('Устройства'), 'devices.index',         'device'],
    ];
    $unreadCount  = $user->recommendations()->where('is_read', false)->count();
    $timerRunning = $user->timeEntries()->running()->exists();
    $isActive = fn (string $route) => request()->routeIs(\Illuminate\Support\Str::before($route, '.') . '*');
    $linkBase = 'group flex h-9 items-center gap-2.5 rounded-lg px-2.5 text-sm transition-colors';
    $linkOn   = 'bg-surface-700 text-slate-100 shadow-glow';
    $linkOff  = 'text-slate-400 hover:bg-white/[0.04] hover:text-slate-100';
@endphp
<div x-data="{ open: false }" class="flex min-h-screen">

    {{-- Затемнение под мобильным меню --}}
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-30 bg-black/60 md:hidden"></div>

    {{-- Боковое меню --}}
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 transform flex-col border-r border-white/[0.06] bg-surface-950 px-3 py-4 transition-transform md:sticky md:top-0 md:h-screen md:translate-x-0">
        <a href="{{ url('/') }}" class="mb-6 flex h-10 items-center gap-2.5 px-2.5">
            <x-application-logo />
            <span class="text-base font-medium text-slate-100">ActivityMind</span>
        </a>

        <nav class="flex-1 space-y-0.5 overflow-y-auto">
            @foreach ($nav as [$label, $route, $icon])
                <a href="{{ route($route) }}" class="{{ $linkBase }} {{ $isActive($route) ? $linkOn : $linkOff }}">
                    <x-icon :name="$icon" class="h-[18px] w-[18px] shrink-0 {{ $isActive($route) ? 'text-slate-200' : 'text-slate-500 group-hover:text-slate-300' }}" />
                    <span class="flex-1">{{ $label }}</span>
                    @if ($route === 'timer.index' && $timerRunning)
                        <span class="am-rec" title="{{ __('Таймер идёт') }}"></span>
                    @endif
                    @if ($route === 'recommendations.index' && $unreadCount > 0)
                        <span class="am-num text-xs text-slate-500">{{ $unreadCount }}</span>
                    @endif
                </a>
            @endforeach

            @if ($user->isAdmin())
                <div class="px-2.5 pb-2 pt-6 text-xs text-slate-500">{{ __('Администрирование') }}</div>
                <a href="{{ route('admin.dashboard') }}" class="{{ $linkBase }} {{ request()->routeIs('admin.dashboard') ? $linkOn : $linkOff }}">
                    <x-icon name="shield" class="h-[18px] w-[18px] shrink-0 text-slate-500" /> {{ __('Обзор') }}
                </a>
                <a href="{{ route('admin.users.index') }}" class="{{ $linkBase }} {{ request()->routeIs('admin.users.*') ? $linkOn : $linkOff }}">
                    <x-icon name="users" class="h-[18px] w-[18px] shrink-0 text-slate-500" /> {{ __('Пользователи') }}
                </a>
            @endif
        </nav>

        {{-- Настройки — над аккаунтом --}}
        <a href="{{ route('settings.edit') }}" class="mt-4 {{ $linkBase }} {{ $isActive('settings.edit') ? $linkOn : $linkOff }}">
            <x-icon name="gear" class="h-[18px] w-[18px] shrink-0 {{ $isActive('settings.edit') ? 'text-slate-200' : 'text-slate-500 group-hover:text-slate-300' }}" />
            <span class="flex-1">{{ __('Настройки') }}</span>
        </a>

        {{-- Профиль внизу меню --}}
        <div class="mt-2 flex items-center gap-2.5 border-t border-white/[0.06] px-1 pt-3">
            <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg p-1.5 transition-colors hover:bg-white/[0.04]">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-surface-700 text-sm text-slate-200">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm text-slate-100">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ $user->isAdmin() ? __('Администратор') : __('Профиль') }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition-colors hover:bg-white/[0.06] hover:text-slate-100" title="{{ __('Выйти') }}" aria-label="{{ __('Выйти') }}">
                    <x-icon name="logout" class="h-4 w-4" />
                </button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Верхняя панель --}}
        <header class="sticky top-0 z-20 flex h-14 items-center justify-between gap-3 border-b border-white/[0.06] bg-surface-900 px-4 md:px-8">
            <div class="flex items-center gap-3">
                <button @click="open = !open" class="grid h-8 w-8 place-items-center rounded-lg text-slate-300 hover:bg-white/[0.06] md:hidden" aria-label="{{ __('Меню') }}">
                    <x-icon name="menu" />
                </button>
                @isset($header)
                    <h1 class="text-[15px] font-medium">{{ $header }}</h1>
                @endisset
            </div>
            <div class="hidden text-sm text-slate-500 sm:block">
                {{ \Illuminate\Support\Str::ucfirst(now()->setTimezone($user->timezone ?: config('app.timezone'))->locale(app()->getLocale())->isoFormat('dddd, D MMMM')) }}
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 md:px-8 md:py-8">
            @if (session('status'))
                <div x-data="{ show: true }" x-show="show"
                     class="mb-6 flex items-center gap-3 rounded-xl border border-white/[0.07] bg-surface-800 px-4 py-3 text-sm text-slate-200">
                    <x-icon name="tick" class="h-4 w-4 shrink-0 text-[#4cc38a]" />
                    <span class="flex-1">{{ session('status') }}</span>
                    <button type="button" @click="show = false" class="grid h-6 w-6 place-items-center rounded text-slate-500 hover:text-slate-200" aria-label="{{ __('Закрыть') }}">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
