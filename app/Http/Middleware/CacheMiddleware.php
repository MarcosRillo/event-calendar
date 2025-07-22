<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class CacheMiddleware
{
    /**
     * Handle an incoming request with response caching.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  int  $ttl  Cache time to live in seconds (default 300 = 5 minutes)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, int $ttl = 300): BaseResponse
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Create unique cache key based on URL and query parameters
        $cacheKey = $this->generateCacheKey($request);

        // Try to get cached response
        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            
            return response($cachedData['content'], $cachedData['status'])
                ->withHeaders($cachedData['headers'])
                ->header('X-Cache', 'HIT');
        }

        // Get fresh response
        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() === 200) {
            $this->cacheResponse($cacheKey, $response, $ttl);
            $response->headers->set('X-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * Generate a unique cache key for the request
     */
    private function generateCacheKey(Request $request): string
    {
        $url = $request->url();
        $queryParams = $request->query();
        $user = $request->user();
        
        // Include user ID in cache key to prevent data leakage
        $userKey = $user ? $user->id : 'guest';
        
        // Sort query parameters for consistent cache keys
        ksort($queryParams);
        
        return 'api_cache:' . md5($url . serialize($queryParams) . $userKey);
    }

    /**
     * Cache the response
     */
    private function cacheResponse(string $cacheKey, BaseResponse $response, int $ttl): void
    {
        try {
            $cachedData = [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all()
            ];

            Cache::put($cacheKey, $cachedData, $ttl);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::warning('Failed to cache response', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
    }
}
