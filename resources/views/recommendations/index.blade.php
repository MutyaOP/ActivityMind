@php
    $types = [
        'focus'    => ['Фокус',         'am-badge am-badge-brand'],
        'break'    => ['Отдых',         'am-badge am-badge-teal'],
        'habit'    => ['Привычки',      'am-badge am-badge-amber'],
        'planning' => ['Планирование',  'am-badge am-badge-sky'],
        'other'    => ['Другое',        'am-badge am-badge-slate'],
    ];
    $sources = ['rule' => 'по правилам', 'ai' => 'AI-анализ'];
    $onlyUnread = request('filter') === 'unread';
@endphp

<x-app-layout>
    <x-slot name="header">Рекомендации</x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="am-tabs">
            <a href="{{ request()->fullUrlWithQuery(['filter' => null, 'page' => null]) }}"
               class="am-tab {{ ! $onlyUnread ? 'am-tab-active' : '' }}">Все</a>
            <a href="{{ request()->fullUrlWithQuery(['filter' => 'unread', 'page' => null]) }}"
               class="am-tab {{ $onlyUnread ? 'am-tab-active' : '' }}">
                Новые <span class="ml-1 text-xs opacity-70">{{ $unread }}</span>
            </a>
            </div>

            <form method="GET" class="ml-2">
                @if ($onlyUnread) <input type="hidden" name="filter" value="unread"> @endif
                <select name="type" onchange="this.form.submit()" class="am-input !w-auto !py-1.5 text-sm ">
                    <option value="">Все темы</option>
                    @foreach ($types as $key => [$label])
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="flex items-center gap-2">
            @if ($unread > 0)
                <form method="POST" action="{{ route('recommendations.readAll') }}">
                    @csrf
                    <button class="am-btn-outline">Прочитать все</button>
                </form>
            @endif
            <form method="POST" action="{{ route('recommendations.generate') }}">
                @csrf
                <button class="am-btn"><x-icon name="refresh" class="h-4 w-4" />Обновить советы</button>
            </form>
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($items as $rec)
            @php [$typeLabel, $typeStyle] = $types[$rec->type] ?? $types['other']; @endphp
            <div class="am-card flex flex-wrap items-start justify-between gap-4 {{ $rec->is_read ? 'opacity-60' : 'border-white/[0.12]' }}">
                <div class="min-w-0 flex-1">
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="{{ $typeStyle }}">{{ $typeLabel }}</span>
                        <span class="text-slate-500">{{ $sources[$rec->source] ?? $rec->source }}</span>
                        <span class="text-slate-500">· {{ $rec->created_at->copy()->setTimezone(auth()->user()->timezone ?: config('app.timezone'))->format('d.m.Y H:i') }}</span>
                        @unless ($rec->is_read) <span class="am-badge am-badge-brand">новая</span> @endunless
                    </div>
                    <p class="text-slate-200">{{ $rec->text }}</p>
                </div>

                <div class="flex shrink-0 items-center gap-2 text-sm">
                    @unless ($rec->is_read)
                        <form method="POST" action="{{ route('recommendations.read', $rec) }}">
                            @csrf @method('PATCH')
                            <button class="am-btn-ghost"><x-icon name="tick" class="h-3.5 w-3.5" />Прочитано</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('recommendations.destroy', $rec) }}" onsubmit="return confirm('Удалить рекомендацию?')">
                        @csrf @method('DELETE')
                        <button class="am-btn-danger" title="Удалить"><x-icon name="trash" class="h-4 w-4" /></button>
                    </form>
                </div>
            </div>
        @empty
            <div class="am-card am-empty">
                @if ($onlyUnread || request('type'))
                    Ничего не найдено. <a href="{{ route('recommendations.index') }}" class="am-link">Сбросить фильтры</a>
                @else
                    Рекомендаций пока нет. Нажмите «Обновить советы» — они появляются, когда накапливается история задач, времени и привычек.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</x-app-layout>
