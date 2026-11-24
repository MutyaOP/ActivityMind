<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация Desktop Agent по токену устройства (Authorization: Bearer amd_...).
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        // API всегда отвечает JSON, даже если агент забыл заголовок Accept
        $request->headers->set('Accept', 'application/json');

        $plain = $request->bearerToken();
        $device = $plain
            ? Device::where('token_hash', Device::hashToken($plain))->first()
            : null;

        if (! $device) {
            return response()->json(['message' => 'Неверный или отсутствующий токен устройства.'], 401);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
