<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ActivityDayService;
use App\Services\AnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim($data['q'] ?? '');

        return view('admin.users.index', [
            'users' => User::query()
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'q' => $q,
        ]);
    }

    /**
     * Данные пользователя для администратора: сводка за 7 дней, активность за компьютером по дням
     * (только программы — без заголовков окон и вкладок браузера), задачи, привычки, устройства.
     */
    public function show(Request $request, User $user, ActivityDayService $activity)
    {
        $tz = $user->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();

        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($data['date']) ? CarbonImmutable::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay() : $today;
        if ($date->gt($today)) {
            $date = $today;
        }

        $weekFrom = $today->subDays(6)->utc();

        $focusSec = (int) TimeEntry::where('user_id', $user->id)->where('started_at', '>=', $weekFrom)->sum('duration_sec');
        $computerSec = (int) ActivityLog::where('user_id', $user->id)->where('is_idle', false)
            ->where('started_at', '>=', $weekFrom)->sum('duration_sec');

        $lastSeen = collect([
            ActivityLog::where('user_id', $user->id)->max('started_at'),
            TimeEntry::where('user_id', $user->id)->max('started_at'),
        ])->filter()->map(fn ($v) => CarbonImmutable::parse($v, 'UTC'))->max();

        return view('admin.users.show', [
            'user'     => $user,
            'tz'       => $tz,
            'date'     => $date,
            'isToday'  => $date->equalTo($today),
            'prevDate' => $date->subDay()->toDateString(),
            'nextDate' => $date->equalTo($today) ? null : $date->addDay()->toDateString(),
            'lastSeen' => $lastSeen?->setTimezone($tz),
            'summary'  => [
                'focus'       => AnalyticsService::humanDuration($focusSec),
                'computer'    => AnalyticsService::humanDuration($computerSec),
                'tasks_done'  => Task::where('user_id', $user->id)->whereNotNull('completed_at')->where('completed_at', '>=', $weekFrom)->count(),
                'tasks_open'  => Task::where('user_id', $user->id)->where('status', '!=', 'done')->count(),
                'habits'      => $user->habits()->where('is_active', true)->count(),
            ],
            'day'      => $activity->day($user, $date, withTabs: false),
            'recent'   => $activity->recentDays($user),
            'tasks'    => $user->tasks()->with('category')->orderByRaw("status = 'done'")->latest('updated_at')->take(8)->get(),
            'entries'  => $user->timeEntries()->with(['task:id,title', 'category'])->latest('started_at')->take(8)->get(),
            'habits'   => $user->habits()->where('is_active', true)->orderBy('title')->get(),
            'devices'  => $user->devices()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403, __('Нельзя менять собственную роль.'));

        $data = $request->validate(['role' => ['required', 'in:user,admin']]);
        $user->forceFill(['role' => $data['role']])->save();

        return back()->with('status', __('Роль пользователя :name обновлена.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403, __('Нельзя удалить самого себя.'));

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', __('Пользователь удалён.'));
    }
}
