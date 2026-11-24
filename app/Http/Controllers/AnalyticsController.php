<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $data = $request->validate(['days' => ['nullable', 'in:7,30,90']]);
        $days = (int) ($data['days'] ?? 30);

        return view('analytics.index', $stats->analytics($request->user(), $days));
    }
}
