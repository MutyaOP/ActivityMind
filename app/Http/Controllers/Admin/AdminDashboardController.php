<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'stats' => [
                'Пользователи'      => User::count(),
                'Задачи'            => Task::count(),
                'Записи времени'    => TimeEntry::count(),
                'Логи активности'   => ActivityLog::count(),
            ],
            'latestUsers' => User::latest()->take(5)->get(),
        ]);
    }
}
