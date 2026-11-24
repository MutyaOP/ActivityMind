<?php

namespace App\Services;

use App\Models\Category;

/**
 * Определяет системную категорию по имени приложения и (если агент его передаёт) заголовку окна.
 * Правила простые и прозрачные: подстрока в нижнем регистре. Сначала подсказка агента
 * (он определяет категорию вкладки браузера на компьютере, не отправляя её заголовок), затем заголовок, затем приложение.
 */
class AppCategorizer
{
    private const TITLE_RULES = [
        'Учёба' => ['classroom', 'moodle', 'coursera', 'stepik', 'udemy', 'khan academy', 'лекци', 'универ'],
        'Программирование' => ['github', 'gitlab', 'stack overflow', 'stackoverflow', 'laravel', 'php.net', 'developer.mozilla', 'localhost', 'phpmyadmin'],
        'Отдых' => ['youtube', 'netflix', 'twitch', 'instagram', 'tiktok', 'vk.com', 'facebook', 'reddit'],
    ];

    private const APP_RULES = [
        'Программирование' => ['code', 'devenv', 'pycharm', 'phpstorm', 'idea', 'webstorm', 'rider', 'clion', 'sublime', 'notepad++',
            'powershell', 'cmd', 'windowsterminal', 'git', 'xampp', 'postman', 'dbeaver', 'datagrip', 'docker'],
        'Работа' => ['winword', 'excel', 'powerpnt', 'outlook', 'teams', 'slack', 'zoom', 'notion', 'onenote', 'acrobat', '1cv8'],
        'Учёба' => ['anki', 'obsidian', 'kindle', 'foxit'],
        'Отдых' => ['spotify', 'steam', 'discord', 'telegram', 'vlc', 'epicgames', 'netflix', 'whatsapp'],
    ];

    private const BROWSERS = ['chrome', 'msedge', 'firefox', 'opera', 'brave', 'vivaldi', 'yandex', 'browser'];

    /** Подсказки агента → системные категории. */
    public const HINTS = [
        'study'       => 'Учёба',
        'programming' => 'Программирование',
        'work'        => 'Работа',
        'rest'        => 'Отдых',
    ];

    /** Вкладка браузера сохраняется с названием, только если на ней провели не меньше этого времени. */
    public const MIN_TAB_SEC = 300;

    public function isBrowser(string $app): bool
    {
        return $this->containsAny(mb_strtolower($app), self::BROWSERS);
    }

    /** @var array<string,int>|null */
    private ?array $ids = null;

    public function categoryName(string $app, ?string $title = null, ?string $hint = null): ?string
    {
        if ($hint !== null && isset(self::HINTS[$hint])) {
            return self::HINTS[$hint];
        }

        $app = mb_strtolower($app);
        $title = mb_strtolower($title ?? '');

        if ($title !== '') {
            foreach (self::TITLE_RULES as $name => $needles) {
                if ($this->containsAny($title, $needles)) {
                    return $name;
                }
            }
        }

        foreach (self::APP_RULES as $name => $needles) {
            if ($this->containsAny($app, $needles)) {
                return $name;
            }
        }

        return $this->containsAny($app, self::BROWSERS) ? 'Прочее' : null;
    }

    public function categoryId(string $app, ?string $title = null, ?string $hint = null): ?int
    {
        $name = $this->categoryName($app, $title, $hint);
        if ($name === null) {
            return null;
        }

        $this->ids ??= Category::whereNull('user_id')->pluck('id', 'name')->all();

        return $this->ids[$name] ?? null;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
