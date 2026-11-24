<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ключи переводов — русские строки, поэтому запасной язык всегда русский:
        // иначе при отсутствии ключа в kk.json подставлялся бы английский текст.
        $this->app['translator']->setFallback('ru');
    }
}
