<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CacheService
{
    /**
     * Cache TTL constants (in seconds)
     */
    public const TTL_SHORT = 300;      // 5 minutes
    public const TTL_MEDIUM = 1800;    // 30 minutes
    public const TTL_LONG = 3600;      // 1 hour
    public const TTL_VERY_LONG = 86400; // 24 hours

    /**
     * Cache key prefixes
     */
    public const PREFIX_USER = 'user:';
    public const PREFIX_ORGANIZATION = 'org:';
    public const PREFIX_EVENT = 'event:';
    public const PREFIX_INVITATION = 'invitation:';
    public const PREFIX_STATS = 'stats:';

    /**
     * Remember a value in cache with automatic invalidation
     */
    public function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache operation failed, falling back to direct call', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return $callback();
        }
    }

    /**
     * Invalidate cache by pattern or specific key
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning('Cache forget operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Invalidate all cache keys with a specific prefix
     */
    public function forgetByPrefix(string $prefix): bool
    {
        try {
            // For database cache, we'll use a simple approach
            // In production with Redis, this would be more efficient
            $driver = config('cache.default');
            
            if ($driver === 'redis') {
                $keys = Cache::getRedis()->keys($prefix . '*');
                
                if (!empty($keys)) {
                    Cache::getRedis()->del($keys);
                }
            } else {
                // For database/file cache, we'll track cache keys and clear them individually
                // This is less efficient but works across all cache drivers
                $this->clearPrefixFromDatabase($prefix);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::warning('Cache prefix forget operation failed', [
                'prefix' => $prefix,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Clear cache prefix from database cache store
     */
    private function clearPrefixFromDatabase(string $prefix): void
    {
        try {
            // For database cache, we'll query the cache table directly
            if (Schema::hasTable('cache')) {
                DB::table('cache')
                    ->where('key', 'like', $prefix . '%')
                    ->delete();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear cache prefix from database', [
                'prefix' => $prefix,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get organization cache key
     */
    public function getOrganizationKey(int $orgId, string $suffix = ''): string
    {
        return self::PREFIX_ORGANIZATION . $orgId . ($suffix ? ':' . $suffix : '');
    }

    /**
     * Get user cache key
     */
    public function getUserKey(int $userId, string $suffix = ''): string
    {
        return self::PREFIX_USER . $userId . ($suffix ? ':' . $suffix : '');
    }

    /**
     * Get event cache key
     */
    public function getEventKey(int $eventId, string $suffix = ''): string
    {
        return self::PREFIX_EVENT . $eventId . ($suffix ? ':' . $suffix : '');
    }

    /**
     * Get invitation cache key
     */
    public function getInvitationKey(int $invitationId, string $suffix = ''): string
    {
        return self::PREFIX_INVITATION . $invitationId . ($suffix ? ':' . $suffix : '');
    }

    /**
     * Get stats cache key
     */
    public function getStatsKey(string $type, string $suffix = ''): string
    {
        return self::PREFIX_STATS . $type . ($suffix ? ':' . $suffix : '');
    }

    /**
     * Invalidate organization related caches
     */
    public function invalidateOrganization(int $orgId): void
    {
        $this->forgetByPrefix($this->getOrganizationKey($orgId));
        $this->forget($this->getStatsKey('organizations'));
    }

    /**
     * Invalidate user related caches
     */
    public function invalidateUser(int $userId): void
    {
        $this->forgetByPrefix($this->getUserKey($userId));
        $this->forget($this->getStatsKey('users'));
    }

    /**
     * Invalidate event related caches
     */
    public function invalidateEvent(int $eventId): void
    {
        $this->forgetByPrefix($this->getEventKey($eventId));
        $this->forget($this->getStatsKey('events'));
    }

    /**
     * Invalidate invitation related caches
     */
    public function invalidateInvitation(int $invitationId): void
    {
        $this->forgetByPrefix($this->getInvitationKey($invitationId));
        $this->forget($this->getStatsKey('invitations'));
    }
}
