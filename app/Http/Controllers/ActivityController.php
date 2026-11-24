<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\ActivityDayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** «Активность»: текущий сеанс (сегодня) и история по дням — какие программы, сколько времени. */
class ActivityController extends Controller
{
    public function index(Request $request, ActivityDayService $service)
    {
        $user = $request->user();
        $tz = $user->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();

        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($data['date']) ? CarbonImmutable::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay() : $today;
        if ($date->gt($today)) {
            $date = $today;
        }
        $isToday = $date->equalTo($today);

        return view('activity.index', [
            'date'     => $date,
            'isToday'  => $isToday,
            'prevDate' => $date->subDay()->toDateString(),
            'nextDate' => $isToday ? null : $date->addDay()->toDateString(),
            'day'      => $service->day($user, $date, withTabs: $isToday),
            'recent'   => $service->recentDays($user),
            'devices'  => $isToday ? Device::where('user_id', $user->id)->orderBy('name')->get() : collect(),
            'tz'       => $tz,
        ]);
    }
}
