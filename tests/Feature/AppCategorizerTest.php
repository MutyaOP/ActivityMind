<?php

namespace Tests\Feature;

use App\Services\AppCategorizer;
use Tests\TestCase;

class AppCategorizerTest extends TestCase
{
    private function categoryOf(string $app, ?string $title = null): ?string
    {
        return (new AppCategorizer)->categoryName($app, $title);
    }

    public function test_known_apps(): void
    {
        $this->assertSame('Программирование', $this->categoryOf('Code', 'main.php - Visual Studio Code'));
        $this->assertSame('Программирование', $this->categoryOf('WindowsTerminal'));
        $this->assertSame('Работа', $this->categoryOf('WINWORD', 'Диплом.docx'));
        $this->assertSame('Отдых', $this->categoryOf('Spotify'));
        $this->assertSame('Учёба', $this->categoryOf('Anki'));
    }

    public function test_browser_depends_on_window_title(): void
    {
        $this->assertSame('Учёба', $this->categoryOf('chrome', 'Мой курс — Google Classroom'));
        $this->assertSame('Программирование', $this->categoryOf('chrome', 'laravel/laravel: GitHub'));
        $this->assertSame('Отдых', $this->categoryOf('chrome', 'YouTube'));
        $this->assertSame('Прочее', $this->categoryOf('chrome', 'Новая вкладка'));
        $this->assertSame('Прочее', $this->categoryOf('msedge'));
    }

    public function test_unknown_app_has_no_category(): void
    {
        $this->assertNull($this->categoryOf('mystery-tool'));
    }

    public function test_matching_is_case_insensitive(): void
    {
        $this->assertSame('Отдых', $this->categoryOf('SPOTIFY'));
        $this->assertSame('Отдых', $this->categoryOf('chrome', 'YOUTUBE — главная'));
    }
}
