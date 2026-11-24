<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status'      => ['nullable', 'in:todo,in_progress,done'],
            'priority'    => ['nullable', 'in:low,medium,high'],
            'category_id' => ['nullable', 'integer'],
            'q'           => ['nullable', 'string', 'max:100'],
        ]);
        $userId = $request->user()->id;

        $tasks = Task::with('category')
            ->withSum('timeEntries', 'duration_sec')
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', '%'.$v.'%'))
            ->orderByRaw("status = 'done'")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->paginate(10)
            ->withQueryString();

        $counts = Task::where('user_id', $userId)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return view('tasks.index', [
            'tasks'      => $tasks,
            'counts'     => $counts,
            'categories' => $this->categoriesFor($userId),
            'todayKey'   => now($request->user()->timezone ?: config('app.timezone'))->toDateString(),
        ]);
    }

    public function create(Request $request)
    {
        return view('tasks.create', [
            'task'       => new Task(['priority' => 'medium', 'status' => 'todo']),
            'categories' => $this->categoriesFor($request->user()->id),
        ]);
    }

    public function store(TaskRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['completed_at'] = $data['status'] === 'done' ? now() : null;

        Task::create($data);

        return redirect()->route('tasks.index')->with('status', __('Задача создана.'));
    }

    public function edit(Request $request, Task $task)
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', [
            'task'       => $task,
            'categories' => $this->categoriesFor($request->user()->id),
        ]);
    }

    public function update(TaskRequest $request, Task $task)
    {
        Gate::authorize('update', $task);

        $data = $request->validated();
        $data['completed_at'] = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;

        $task->update($data);

        return redirect()->route('tasks.index')->with('status', __('Задача обновлена.'));
    }

    public function destroy(Task $task)
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return back()->with('status', __('Задача удалена.'));
    }

    /** Быстрое «выполнено / вернуть в работу». */
    public function toggle(Task $task)
    {
        Gate::authorize('update', $task);

        $done = $task->status === 'done';
        $task->update([
            'status'       => $done ? 'todo' : 'done',
            'completed_at' => $done ? null : now(),
        ]);

        return back()->with('status', $done ? __('Задача возвращена в работу.') : __('Задача выполнена.'));
    }

    private function categoriesFor(int $userId)
    {
        return Category::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
            ->orderBy('name')
            ->get();
    }
}
