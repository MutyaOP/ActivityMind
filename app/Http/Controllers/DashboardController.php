<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics, RecommendationService $recommendations)
    {
        $user = $request->user();

        // Не чаще раза в 6 часов; ошибки генерации не ломают страницу
        $recommendations->generateIfStale($user);

        return view('dashboard', $analytics->dashboard($user));
    }
}
