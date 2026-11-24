<x-app-layout>
    <x-slot name="header">{{ __('Привычки') }}</x-slot>

    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-400">{{ __('Нажимайте на кружки, чтобы отметить день. Можно исправлять последние 30 дней.') }}</p>
        <a href="{{ route('habits.create') }}" class="am-btn"><x-icon name="plus" class="h-4 w-4" />{{ __('Новая привычка') }}</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($items as $item)
            @php
                $habit = $item['habit'];
                $s = $item['stats'];
            @endphp
            <div class="am-card {{ $habit->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            
                            <h3 class="truncate font-medium text-slate-100">{{ $habit->title }}</h3>
                        </div>
                        <div class="mt-1 text-xs text-slate-400">
                            {{ $habit->frequency === 'weekly' ? __(':n раз(а) в неделю', ['n' => $habit->target_per_period]) : __('Каждый день') }}
                            @unless ($habit->is_active) · {{ __('в архиве') }} @endunless
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-1 text-sm">
                        <a href="{{ route('habits.edit', $habit) }}" class="am-btn-ghost" title="{{ __('Изменить') }}"><x-icon name="edit" class="h-4 w-4" /></a>
                        <form method="POST" action="{{ route('habits.destroy', $habit) }}"
                              onsubmit="return confirm(@js(__('Удалить привычку вместе со всей историей?')))">
                            @csrf @method('DELETE')
                            <button class="am-btn-danger" title="{{ __('Удалить') }}"><x-icon name="trash" class="h-4 w-4" /></button>
                        </form>
                    </div>
                </div>

                {{-- Последние 7 дней --}}
                <div class="mt-4 flex justify-between gap-1">
                    @foreach ($s['week'] as $d)
                        <form method="POST" action="{{ route('habits.toggle', $habit) }}" class="text-center">
                            @csrf
                            <input type="hidden" name="date" value="{{ $d['date'] }}">
                            <div class="text-xs text-slate-500">{{ $d['label'] }}</div>
                            <button type="submit"
                                    class="mt-1 grid h-9 w-9 place-items-center rounded-full border text-xs transition
                                           {{ $d['done'] ? 'border-transparent bg-slate-200 text-surface-900' : 'border-white/10 bg-white/[0.02] text-slate-500 hover:border-white/30 hover:text-slate-200' }}
                                           {{ $d['is_today'] ? 'ring-1 ring-white/40 ring-offset-2 ring-offset-surface-800' : '' }}">
                                {{ $d['done'] ? '✓' : $d['day'] }}
                            </button>
                        </form>
                    @endforeach
                </div>

                {{-- Серии и процент --}}
                <div class="mt-4 grid grid-cols-3 gap-3 rounded-xl bg-white/[0.02] p-3 text-center">
                    <div>
                        <div class="am-num text-2xl text-slate-100">{{ $s['current_streak'] }}</div>
                        <div class="text-[11px] text-slate-500">{{ __('серия') }}, {{ $s['unit'] }}</div>
                    </div>
                    <div>
                        <div class="am-num text-2xl text-slate-100">{{ $s['best_streak'] }}</div>
                        <div class="text-[11px] text-slate-500">{{ __('лучшая') }}, {{ $s['unit'] }}</div>
                    </div>
                    <div>
                        <div class="am-num text-2xl text-slate-100">{{ $s['rate'] }}%</div>
                        <div class="text-[11px] text-slate-500">{{ __('выполнение') }}</div>
                    </div>
                </div>
                <div class="am-progress mt-3 !h-1.5">
                    <div style="width: {{ $s['rate'] }}%"></div>
                </div>
            </div>
        @empty
            <div class="am-card am-empty lg:col-span-2">
                {{ __('Привычек пока нет.') }} <a href="{{ route('habits.create') }}" class="am-link">{{ __('Добавить первую') }}</a>
            </div>
        @endforelse
    </div>
</x-app-layout>
