<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::latest()->paginate(15),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403, 'Нельзя менять собственную роль.');

        $data = $request->validate(['role' => ['required', 'in:user,admin']]);
        $user->forceFill(['role' => $data['role']])->save();

        return back()->with('status', "Роль пользователя {$user->name} обновлена.");
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403, 'Нельзя удалить самого себя.');

        $user->delete();

        return back()->with('status', 'Пользователь удалён.');
    }
}
