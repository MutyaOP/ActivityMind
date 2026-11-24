<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\AnalyticsService;
use App\Services\TimerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tz = $user->timezone ?: config('app.timezone');

        $running = TimeEntry::with(['task', 'category'])
            ->where('user_id', $user->id)
            ->running()
            ->latest('started_at')
            ->first();

        $entries = TimeEntry::with(['task', 'category'])
            ->where('user_id', $user->id)
            ->whereNotNull('ended_at')
            ->latest('started_at')
            ->paginate(10);

        $todaySec = (int) TimeEntry::where('user_id', $user->id)
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', now($tz)->startOfDay()->utc())
            ->sum('duration_sec');

        return view('timer.index', [
            'tz'         => $tz,
            'running'    => $running,
            'entries'    => $entries,
            'todayLabel' => AnalyticsService::humanDuration($todaySec),
            'tasks'      => Task::where('user_id', $user->id)->where('status', '!=', 'done')->orderBy('title')->get(),
            'categories' => $this->categoriesFor($user->id),
        ]);
    }

    public function start(Request $request, TimerService $timer)
    {
        $data = $this->validateEntryFields($request);

        $timer->start($request->user(), $data['task_id'] ?? null, $data['category_id'] ?? null, $data['note'] ?? null);

        return redirect()->route('timer.index')->with('status', 'Таймер запущен.');
    }

    public function stop(Request $request, TimerService $timer)
    {
        $stopped = $timer->stop($request->user());

        return redirect()->route('timer.index')->with(
            'status',
            $stopped
                ? 'Записано: '.AnalyticsService::humanDuration((int) $stopped->duration_sec).'.'
                : 'Таймер не был запущен.'
        );
    }

    public function manual(Request $request, TimerService $timer)
    {
        $data = $this->validateEntryFields($request) + $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at'   => ['required', 'date', 'after:started_at'],
        ]);

        $user = $request->user();
        $tz = $user->timezone ?: config('app.timezone');
        $seconds = \Carbon\Carbon::parse($data['ended_at'], $tz)->diffInSeconds(\Carbon\Carbon::parse($data['started_at'], $tz));

        if ($seconds > 86400) {
            throw ValidationException::withMessages(['ended_at' => 'Запись не может быть длиннее 24 часов.']);
        }

        $timer->addManual($user, $data['started_at'], $data['ended_at'], $data['task_id'] ?? null, $data['category_id'] ?? null, $data['note'] ?? null);

        return redirect()->route('timer.index')->with('status', 'Запись добавлена.');
    }

    public function destroy(TimeEntry $entry)
    {
        Gate::authorize('delete', $entry);

        $entry->delete();

        return back()->with('status', 'Запись удалена.');
    }

    private function validateEntryFields(Request $request): array
    {
        $userId = $request->user()->id;

        return $request->validate([
            'task_id'     => ['nullable', Rule::exists('tasks', 'id')->where('user_id', $userId)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')
                                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function categoriesFor(int $userId)
    {
        return Category::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
            ->orderBy('name')
            ->get();
    }
}
