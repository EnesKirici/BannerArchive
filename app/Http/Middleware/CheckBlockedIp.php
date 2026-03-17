<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckBlockedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $cacheKey = "blocked_ip_{$ip}";

        $isBlocked = Cache::remember($cacheKey, 60, function () use ($ip) {
            return BlockedIp::isBlocked($ip);
        });

        if ($isBlocked) {
            abort(403, 'Erişiminiz engellenmiştir.');
        }

        return $next($request);
    }
}
