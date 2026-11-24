<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimerController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard')
    : redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Задачи
    Route::resource('tasks', TaskController::class)->except(['show']);
    Route::patch('/tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');

    // Таймер
    Route::get('/timer', [TimerController::class, 'index'])->name('timer.index');
    Route::post('/timer/start', [TimerController::class, 'start'])->name('timer.start');
    Route::post('/timer/stop', [TimerController::class, 'stop'])->name('timer.stop');
    Route::post('/timer/manual', [TimerController::class, 'manual'])->name('timer.manual');
    Route::delete('/timer/{entry}', [TimerController::class, 'destroy'])->name('timer.destroy');

    // Привычки
    Route::resource('habits', HabitController::class)->except(['show']);
    Route::post('/habits/{habit}/toggle', [HabitController::class, 'toggle'])->name('habits.toggle');

    // Аналитика
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Рекомендации
    Route::get('/recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
    Route::post('/recommendations/generate', [RecommendationController::class, 'generate'])
        ->middleware('throttle:6,1')->name('recommendations.generate');
    Route::post('/recommendations/read-all', [RecommendationController::class, 'readAll'])->name('recommendations.readAll');
    Route::patch('/recommendations/{recommendation}/read', [RecommendationController::class, 'read'])->name('recommendations.read');
    Route::delete('/recommendations/{recommendation}', [RecommendationController::class, 'destroy'])->name('recommendations.destroy');

    // Устройства (Desktop Agent)
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::post('/devices/{device}/token', [DeviceController::class, 'token'])->name('devices.token');
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    // Профиль
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/auth.php';
