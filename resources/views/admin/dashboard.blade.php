<x-app-layout>
    <x-slot name="header">Админ-панель</x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="am-card">
                <div class="text-sm text-slate-400">{{ $label }}</div>
                <div class="am-num mt-3 text-[28px] leading-8 text-slate-100">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="am-card mt-6">
        <h2 class="am-card-title">Новые пользователи</h2>
        <ul class="divide-y divide-white/[0.05] text-sm">
            @foreach ($latestUsers as $u)
                <li class="flex justify-between py-2">
                    <span>{{ $u->name }} <span class="text-slate-500">· {{ $u->email }}</span></span>
                    <span class="text-slate-500">{{ $u->created_at->format('d.m.Y') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
