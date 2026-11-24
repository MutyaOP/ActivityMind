<x-app-layout>
    <x-slot name="header">{{ __('Пользователи') }}</x-slot>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex max-w-md items-center gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Поиск по имени или email') }}" class="am-input">
        <button class="am-btn-outline"><x-icon name="search" class="h-4 w-4" />{{ __('Найти') }}</button>
    </form>

    <div class="am-card overflow-x-auto">
        <table class="am-table w-full text-left text-sm">
            <thead class="text-slate-400">
                <tr>
                    <th class="pb-3">{{ __('Имя') }}</th>
                    <th class="pb-3">Email</th>
                    <th class="pb-3">{{ __('Роль') }}</th>
                    <th class="pb-3">{{ __('Регистрация') }}</th>
                    <th class="pb-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.05]">
                @forelse ($users as $u)
                    <tr>
                        <td class="py-3">
                            <a href="{{ route('admin.users.show', $u) }}" class="text-slate-100 hover:text-brand-300">{{ $u->name }}</a>
                        </td>
                        <td class="py-3 text-slate-400">{{ $u->email }}</td>
                        <td class="py-3">
                            @if ($u->is(auth()->user()))
                                <span class="am-badge am-badge-brand">{{ $u->role }} ({{ __('вы') }})</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.update', $u) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <select name="role" onchange="this.form.submit()" class="am-input !w-auto !py-1" aria-label="{{ __('Роль') }}">
                                        <option value="user" @selected($u->role === 'user')>user</option>
                                        <option value="admin" @selected($u->role === 'admin')>admin</option>
                                    </select>
                                </form>
                            @endif
                        </td>
                        <td class="py-3 text-slate-400">{{ $u->created_at->format('d.m.Y') }}</td>
                        <td class="py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.users.show', $u) }}" class="am-btn-ghost">
                                    <x-icon name="eye" class="h-4 w-4" />{{ __('Данные') }}
                                </a>
                                @unless ($u->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                          onsubmit="return confirm(@js(__('Удалить пользователя :name?', ['name' => $u->name])))">
                                        @csrf @method('DELETE')
                                        <button class="am-btn-danger">{{ __('Удалить') }}</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="am-empty">{{ __('Никого не нашлось.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>
