<x-app-layout>
    <x-slot name="header">{{ __('Админ-панель') }}</x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="am-card">
                <div class="text-sm text-slate-400">{{ __($label) }}</div>
                <div class="am-num mt-3 text-[28px] leading-8 text-slate-100">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="am-card mt-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="am-card-title !mb-0">{{ __('Новые пользователи') }}</h2>
            <a href="{{ route('admin.users.index') }}" class="am-btn-ghost">{{ __('Все пользователи') }} <x-icon name="arrow" class="h-4 w-4" /></a>
        </div>
        <ul class="divide-y divide-white/[0.05] text-sm">
            @foreach ($latestUsers as $u)
                <li>
                    <a href="{{ route('admin.users.show', $u) }}" class="-mx-2 flex justify-between rounded-lg px-2 py-2 transition-colors hover:bg-white/[0.04]">
                        <span>{{ $u->name }} <span class="text-slate-500">· {{ $u->email }}</span></span>
                        <span class="text-slate-500">{{ $u->created_at->format('d.m.Y') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
