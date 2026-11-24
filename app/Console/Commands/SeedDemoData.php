<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Device;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Recommendation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Console\Command;

/**
 * Демо-данные с правдоподобными закономерностями, чтобы аналитике и AI-модулю было что находить:
 * в «собранные» дни человек выполняет привычки, работает утром длинными сессиями и мало отвлекается;
 * в «рассеянные» — работает днём и вечером короткими кусками и часто переключается на развлечения.
 */
class SeedDemoData extends Command
{
    protected $signature = 'activitymind:demo-data {email : Email существующего пользователя} {--days=14 : За сколько дней создать данные} {--fresh : Сначала удалить прежние задачи, время, привычки, логи агента и советы пользователя}';

    protected $description = 'Добавляет демо-данные (задачи, время, привычки, логи агента) пользователю, чтобы наполнить дашборд';

    private const PRODUCTIVE_APPS = [
        'Программирование' => ['Code.exe', 'WindowsTerminal.exe', 'phpstorm64.exe'],
        'Работа' => ['WINWORD.EXE', 'EXCEL.EXE', 'Teams.exe'],
        'Учёба' => ['Obsidian.exe', 'anki.exe'],
    ];

    private const REST_APPS = ['chrome.exe', 'Telegram.exe', 'Discord.exe', 'steam.exe', 'Spotify.exe'];

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
        $categories = Category::whereNull('user_id')->get()->keyBy('name');
        $tz = $user->timezone ?: config('app.timezone');

        if ($this->option('fresh')) {
            TimeEntry::where('user_id', $user->id)->delete();
            ActivityLog::where('user_id', $user->id)->delete();
            Task::where('user_id', $user->id)->delete();
            Recommendation::where('user_id', $user->id)->delete();
            Habit::where('user_id', $user->id)->each(function (Habit $habit) {
                HabitLog::where('habit_id', $habit->id)->delete();
                $habit->delete();
            });
            $this->line('Прежние данные пользователя удалены.');
        }
        $days = max(1, (int) $this->option('days'));

        $device = Device::firstOrCreate(['user_id' => $user->id, 'name' => 'Демо-ПК'], ['platform' => 'windows']);
        $device->forceFill(['last_sync_at' => now()])->save();

        // --- привычки ---
        $habits = collect(['Зарядка', 'Чтение 20 минут', 'Английский', 'Сон до 23:00'])
            ->map(fn ($title) => Habit::create(['user_id' => $user->id, 'title' => $title]));

        $workCats = $categories->filter(fn (Category $c) => isset(self::PRODUCTIVE_APPS[$c->name]))->values();
        $logs = [];

        // --- по дням ---
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now($tz)->startOfDay()->subDays($i);
            $weekend = $day->isWeekend();
            if (rand(1, 100) <= ($weekend ? 45 : 8)) {
                continue; // выходной без работы
            }
            $good = rand(1, 100) <= ($weekend ? 35 : 60);

            // привычки: в собранные дни выполняется 3–4 из 4, в рассеянные 0–1
            $doneCount = $good ? rand(3, 4) : rand(0, 1);
            foreach ($habits->shuffle()->take($doneCount) as $habit) {
                HabitLog::create(['habit_id' => $habit->id, 'log_date' => $day->toDateString(), 'is_done' => true]);
            }

            // фокус-сессии таймера
            $cursor = $day->copy()->setTime($good ? rand(8, 9) : rand(13, 16), rand(0, 59));
            foreach (range(1, $good ? rand(3, 4) : rand(1, 3)) as $n) {
                $duration = ($good ? rand(50, 90) : rand(20, 45)) * 60;
                $cat = $workCats->random();
                TimeEntry::create([
                    'user_id'      => $user->id,
                    'category_id'  => $cat->id,
                    'started_at'   => $cursor->copy()->utc(),
                    'ended_at'     => $cursor->copy()->addSeconds($duration)->utc(),
                    'duration_sec' => $duration,
                ]);
                $logs = array_merge($logs, $this->agentSegments($user, $device, $categories, $cat->name, $cursor, $duration, $good));
                $cursor->addSeconds($duration)->addMinutes($good ? rand(10, 25) : rand(30, 120));
            }

            // в собранные дни закрываются маленькие шаги задач
            if ($good) {
                foreach (range(1, rand(1, 3)) as $n) {
                    Task::create([
                        'user_id' => $user->id, 'category_id' => $workCats->random()->id,
                        'title' => 'Шаг '.$n.': '.['конспект', 'раздел отчёта', 'исправление бага', 'упражнения'][rand(0, 3)],
                        'priority' => 'low', 'status' => 'done',
                        'completed_at' => $day->copy()->setTime(rand(11, 18), rand(0, 59))->utc(),
                    ]);
                }
            }
        }

        foreach (array_chunk($logs, 500) as $chunk) {
            ActivityLog::insert($chunk);
        }

        // --- текущие задачи ---
        $titles = [
            'Подготовить главу диплома', 'Сдать лабораторную по Laravel', 'Исправить баги в CRUD',
            'Нарисовать UML-диаграммы', 'Прочитать лекцию по Oracle', 'Оформить README',
            'Разобрать задачи по Git', 'Подготовить презентацию',
        ];
        foreach ($titles as $idx => $title) {
            $done = $idx < 3;
            Task::create([
                'user_id'      => $user->id,
                'category_id'  => $workCats->random()->id,
                'title'        => $title,
                'priority'     => ['low', 'medium', 'high'][rand(0, 2)],
                'status'       => $done ? 'done' : ($idx === 3 ? 'in_progress' : 'todo'),
                'due_date'     => now($tz)->addDays(rand(-2, 7))->toDateString(),
                'completed_at' => $done ? now($tz)->subDays(rand(0, 5))->utc() : null,
            ]);
        }

        // дневные агрегаты для графиков аналитики
        $this->call('activitymind:recalc-stats', ['--days' => $days, '--user' => $user->id]);

        $this->info("Демо-данные добавлены пользователю {$user->email} за {$days} дн. (логов агента: ".count($logs).').');

        return self::SUCCESS;
    }

    /**
     * Логи агента на время одной фокус-сессии: основная программа, вперемешку с отвлечениями.
     * В собранные дни куски длинные и отвлечений мало, в рассеянные — наоборот.
     */
    private function agentSegments(User $user, Device $device, $categories, string $catName, Carbon $start, int $duration, bool $good): array
    {
        $rows = [];
        $now = now();
        $t = $start->copy()->utc();
        $end = $t->copy()->addSeconds($duration);
        $rest = $categories['Отдых'];
        $work = $categories[$catName];

        while ($t->lt($end)) {
            $distract = rand(1, 100) <= ($good ? 12 : 45);
            $len = min($end->diffInSeconds($t, true), ($distract ? rand(2, 10) : ($good ? rand(12, 35) : rand(3, 12))) * 60);
            if ($len < 30) {
                break;
            }
            $rows[] = [
                'user_id' => $user->id, 'device_id' => $device->id,
                'category_id' => $distract ? $rest->id : $work->id,
                'app_name' => $distract ? self::REST_APPS[array_rand(self::REST_APPS)] : self::PRODUCTIVE_APPS[$catName][array_rand(self::PRODUCTIVE_APPS[$catName])],
                'window_title' => null, 'started_at' => $t->copy(), 'duration_sec' => (int) $len,
                'is_idle' => false, 'created_at' => $now, 'updated_at' => $now,
            ];
            $t->addSeconds((int) $len + rand(5, 40));
        }

        return $rows;
    }
}
