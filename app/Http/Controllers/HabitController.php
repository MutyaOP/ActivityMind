<?php

namespace App\Http\Controllers;

use App\Http\Requests\HabitRequest;
use App\Models\Habit;
use App\Services\HabitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HabitController extends Controller
{
    public function index(Request $request, HabitService $service)
    {
        $user = $request->user();
        $tz = $user->timezone ?: config('app.timezone');

        $items = Habit::where('user_id', $user->id)
            ->orderByDesc('is_active')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Habit $h) => ['habit' => $h, 'stats' => $service->stats($h, $tz)]);

        return view('habits.index', ['items' => $items]);
    }

    public function create()
    {
        return view('habits.create', [
            'habit' => new Habit(['frequency' => 'daily', 'target_per_period' => 1, 'color' => '#06b6d4', 'is_active' => true]),
        ]);
    }

    public function store(HabitRequest $request)
    {
        Habit::create($this->payload($request) + ['user_id' => $request->user()->id]);

        return redirect()->route('habits.index')->with('status', __('Привычка добавлена.'));
    }

    public function edit(Habit $habit)
    {
        Gate::authorize('update', $habit);

        return view('habits.edit', ['habit' => $habit]);
    }

    public function update(HabitRequest $request, Habit $habit)
    {
        Gate::authorize('update', $habit);

        $habit->update($this->payload($request));

        return redirect()->route('habits.index')->with('status', __('Привычка обновлена.'));
    }

    public function destroy(Habit $habit)
    {
        Gate::authorize('delete', $habit);

        $habit->delete();

        return redirect()->route('habits.index')->with('status', __('Привычка удалена вместе с историей.'));
    }

    /** Отметка за сегодня или за любой из последних 30 дней. */
    public function toggle(Request $request, Habit $habit, HabitService $service)
    {
        Gate::authorize('update', $habit);

        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $tz = $request->user()->timezone ?: config('app.timezone');
        $today = now($tz)->toDateString();
        $date = $data['date'] ?? $today;

        abort_if($date > $today || $date < now($tz)->subDays(30)->toDateString(), 422, __('Недопустимая дата.'));

        $service->toggle($habit, $date);

        return back();
    }

    private function payload(HabitRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['color'] = $data['color'] ?? '#06b6d4';
        $data['target_per_period'] = $data['frequency'] === 'weekly'
            ? ($data['target_per_period'] ?? 3)
            : 1;

        return $data;
    }
}
