<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimerService
{
    /**
     * Запускает таймер. Одновременно у пользователя идёт только одна запись:
     * предыдущая автоматически останавливается.
     */
    public function start(User $user, ?int $taskId = null, ?int $categoryId = null, ?string $note = null): TimeEntry
    {
        return DB::transaction(function () use ($user, $taskId, $categoryId, $note) {
            $this->stop($user);

            $task = $taskId ? Task::where('user_id', $user->id)->findOrFail($taskId) : null;

            if ($task && $task->status === 'todo') {
                $task->update(['status' => 'in_progress']);
            }

            return TimeEntry::create([
                'user_id'     => $user->id,
                'task_id'     => $task?->id,
                'category_id' => $categoryId ?? $task?->category_id,
                'started_at'  => now(),
                'note'        => $note,
            ]);
        });
    }

    /**
     * Останавливает все идущие записи пользователя. Возвращает последнюю остановленную.
     */
    public function stop(User $user): ?TimeEntry
    {
        $stopped = null;

        TimeEntry::where('user_id', $user->id)->running()->get()->each(function (TimeEntry $entry) use (&$stopped) {
            $now = now();
            $entry->update([
                'ended_at'     => $now,
                'duration_sec' => max(0, (int) $entry->started_at->diffInSeconds($now)),
            ]);
            $stopped = $entry;
        });

        return $stopped;
    }

    /**
     * Ручная запись времени. Время приходит в часовом поясе пользователя.
     */
    public function addManual(User $user, string $startedAt, string $endedAt, ?int $taskId, ?int $categoryId, ?string $note): TimeEntry
    {
        $tz = $user->timezone ?: config('app.timezone');
        $start = Carbon::parse($startedAt, $tz)->utc();
        $end = Carbon::parse($endedAt, $tz)->utc();

        return TimeEntry::create([
            'user_id'      => $user->id,
            'task_id'      => $taskId,
            'category_id'  => $categoryId,
            'started_at'   => $start,
            'ended_at'     => $end,
            'duration_sec' => (int) $start->diffInSeconds($end),
            'note'         => $note,
        ]);
    }
}
