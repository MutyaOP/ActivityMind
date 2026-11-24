<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Recommendation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Console\Command;

class SeedDemoData extends Command
{
    protected $signature = 'activitymind:demo-data {email : Email существующего пользователя} {--days=14 : За сколько дней создать данные} {--fresh : Сначала удалить прежние задачи, время, привычки и советы пользователя}';

    protected $description = 'Добавляет демо-данные (задачи, время, привычки) пользователю, чтобы наполнить дашборд';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Пользователь не найден.');
            return self::FAILURE;
        }

        if (Category::whereNull('user_id')->doesntExist()) {
            (new CategorySeeder)->run();
        }
        $categories = Category::whereNull('user_id')->get();
        $tz = $user->timezone ?: config('app.timezone');

        if ($this->option('fresh')) {
            TimeEntry::where('user_id', $user->id)->delete();
            Task::where('user_id', $user->id)->delete();
            Recommendation::where('user_id', $user->id)->delete();
            Habit::where('user_id', $user->id)->each(function (Habit $habit) {
                HabitLog::where('habit_id', $habit->id)->delete();
                $habit->delete();
            });
            $this->line('Прежние данные пользователя удалены.');
        }
        $days = max(1, (int) $this->option('days'));

        // --- привычки ---
        $habits = collect(['Зарядка', 'Чтение 20 минут', 'Английский', 'Сон до 23:00'])
            ->map(fn ($title) => Habit::create(['user_id' => $user->id, 'title' => $title]));

        // --- по дням ---
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now($tz)->startOfDay()->subDays($i);

            // записи времени
            if (rand(1, 10) > 1) {
                foreach (range(1, rand(2, 4)) as $n) {
                    $start = $day->copy()->setTime(rand(9, 19), rand(0, 59));
                    $duration = rand(25, 90) * 60;
                    $cat = $categories->random();

                    TimeEntry::create([
                        'user_id'      => $user->id,
                        'category_id'  => $cat->id,
                        'started_at'   => $start->copy()->utc(),
                        'ended_at'     => $start->copy()->addSeconds($duration)->utc(),
                        'duration_sec' => $duration,
                    ]);
                }
            }

            // привычки
            foreach ($habits as $habit) {
                if (rand(1, 10) <= 7) {
                    HabitLog::create(['habit_id' => $habit->id, 'log_date' => $day->toDateString(), 'is_done' => true]);
                }
            }
        }

        // --- задачи ---
        $titles = [
            'Подготовить главу диплома', 'Сдать лабораторную по Laravel', 'Исправить баги в CRUD',
            'Нарисовать UML-диаграммы', 'Прочитать лекцию по Oracle', 'Оформить README',
            'Разобрать задачи по Git', 'Подготовить презентацию',
        ];
        foreach ($titles as $idx => $title) {
            $done = $idx < 3;
            Task::create([
                'user_id'      => $user->id,
                'category_id'  => $categories->random()->id,
                'title'        => $title,
                'priority'     => ['low', 'medium', 'high'][rand(0, 2)],
                'status'       => $done ? 'done' : ($idx === 3 ? 'in_progress' : 'todo'),
                'due_date'     => now($tz)->addDays(rand(-2, 7))->toDateString(),
                'completed_at' => $done ? now($tz)->subDays(rand(0, 5))->utc() : null,
            ]);
        }

        Recommendation::create([
            'user_id' => $user->id, 'type' => 'focus', 'source' => 'rule',
            'text' => 'Ваши самые длинные сессии фокуса приходятся на первую половину дня. Планируйте сложные задачи на утро.',
        ]);
        Recommendation::create([
            'user_id' => $user->id, 'type' => 'break', 'source' => 'rule',
            'text' => 'После 90 минут непрерывной работы делайте перерыв 10–15 минут.',
        ]);

        // дневные агрегаты для графиков аналитики
        $this->call('activitymind:recalc-stats', ['--days' => $days, '--user' => $user->id]);

        $this->info("Демо-данные добавлены пользователю {$user->email} за {$days} дн.");

        return self::SUCCESS;
    }
}
