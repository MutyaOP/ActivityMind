<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestActivityRequest;
use App\Models\ActivityLog;
use App\Services\AppCategorizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentActivityController extends Controller
{
    /**
     * Проверка токена и связи (--check) и состояние работающего агента:
     * на паузе ли учёт и какая программа открыта сейчас — для страницы «Активность».
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');
        $state = $request->validate([
            'paused'        => ['sometimes', 'boolean'],
            'current_app'   => ['nullable', 'string', 'max:255'],
            'current_since' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
        ]);

        $device->forceFill(['last_sync_at' => now()]);
        if (array_key_exists('paused', $state)) {
            $paused = (bool) $state['paused'];
            $device->forceFill([
                'is_paused'     => $paused,
                'current_app'   => $paused ? null : ($state['current_app'] ?? null),
                'current_since' => $paused || empty($state['current_since']) ? null : Carbon::parse($state['current_since'])->utc(),
            ]);
        }
        $device->save();

        return response()->json([
            'ok'          => true,
            'device'      => ['id' => $device->id, 'name' => $device->name],
            'server_time' => now()->utc()->toIso8601String(),
        ]);
    }

    /** Приём пачки сессий активности. Идемпотентно: повтор той же пачки дублей не создаёт. */
    public function store(IngestActivityRequest $request, AppCategorizer $categorizer): JsonResponse
    {
        $device = $request->attributes->get('device');
        $now = now();
        $rows = [];

        foreach ($request->validated('items') as $item) {
            $app = trim($item['app_name']);
            $title = isset($item['window_title']) && $item['window_title'] !== ''
                ? Str::limit(trim($item['window_title']), 255, '')
                : null;
            $idle = (bool) ($item['is_idle'] ?? false);
            $categoryId = $idle ? null : $categorizer->categoryId($app, $title, $item['category_hint'] ?? null);
            // короткие вкладки браузера храним без названия, даже если старый агент его прислал
            if ($title !== null && $categorizer->isBrowser($app) && (int) $item['duration_sec'] < AppCategorizer::MIN_TAB_SEC) {
                $title = null;
            }

            $rows[] = [
                'user_id'      => $device->user_id,
                'device_id'    => $device->id,
                'category_id'  => $categoryId,
                'app_name'     => $app,
                'window_title' => $title,
                'started_at'   => Carbon::parse($item['started_at'])->utc(),
                'duration_sec' => (int) $item['duration_sec'],
                'is_idle'      => $idle,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        $accepted = ActivityLog::insertOrIgnore($rows);
        $device->update(['last_sync_at' => $now]);

        return response()->json([
            'accepted'   => $accepted,
            'duplicates' => count($rows) - $accepted,
        ]);
    }
}
