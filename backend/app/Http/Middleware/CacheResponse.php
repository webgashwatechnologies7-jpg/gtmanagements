<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $ttl = 60): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Generate cache key
        $cacheKey = 'api_response_' . md5($request->fullUrl() . '_' . $request->user()?->id);

        // Check if cached
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        // Get response
        $response = $next($request);

        // Cache successful responses
        if ($response->getStatusCode() === 200) {
            $content = json_decode($response->getContent(), true);
            if ($content && isset($content['success']) && $content['success']) {
                Cache::put($cacheKey, $content, $ttl);
            }
        }

        return $response;
    }
}
