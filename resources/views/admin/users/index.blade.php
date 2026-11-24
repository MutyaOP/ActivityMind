<x-app-layout>
    <x-slot name="header">Пользователи</x-slot>

    <div class="am-card overflow-x-auto">
        <table class="am-table w-full text-left text-sm">
            <thead class="text-slate-400">
                <tr>
                    <th class="pb-3">Имя</th>
                    <th class="pb-3">Email</th>
                    <th class="pb-3">Роль</th>
                    <th class="pb-3">Регистрация</th>
                    <th class="pb-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.05]">
                @foreach ($users as $u)
                    <tr>
                        <td class="py-3">{{ $u->name }}</td>
                        <td class="py-3 text-slate-400">{{ $u->email }}</td>
                        <td class="py-3">
                            @if ($u->is(auth()->user()))
                                <span class="am-badge am-badge-brand">{{ $u->role }} (вы)</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.update', $u) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <select name="role" onchange="this.form.submit()" class="am-input !w-auto !py-1">
                                        <option value="user" @selected($u->role === 'user')>user</option>
                                        <option value="admin" @selected($u->role === 'admin')>admin</option>
                                    </select>
                                </form>
                            @endif
                        </td>
                        <td class="py-3 text-slate-400">{{ $u->created_at->format('d.m.Y') }}</td>
                        <td class="py-3 text-right">
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                      onsubmit="return confirm('Удалить пользователя {{ $u->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="am-btn-danger">Удалить</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>
