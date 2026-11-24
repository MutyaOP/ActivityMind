<?php

namespace App\Http\Middleware;

use App\Support\Preferences;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Включает выбранные пользователем язык и тему для каждого запроса веб-интерфейса. */
class ApplyPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Preferences::locale($request);
        App::setLocale($locale);
        Carbon::setLocale($locale);

        View::share('theme', Preferences::theme($request));

        return $next($request);
    }
}
