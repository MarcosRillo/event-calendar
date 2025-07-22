<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

class CacheClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-app 
                            {--type=all : Type of cache to clear (all, users, organizations, events, invitations, stats)}
                            {--pattern= : Specific pattern to clear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear application-specific cache with granular control';

    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $pattern = $this->option('pattern');

        $this->info('Clearing application cache...');

        if ($pattern) {
            $this->clearByPattern($pattern);
        } else {
            $this->clearByType($type);
        }

        $this->info('Cache cleared successfully!');
    }

    /**
     * Clear cache by type
     */
    private function clearByType(string $type): void
    {
        switch ($type) {
            case 'users':
                $this->cacheService->forgetByPrefix(CacheService::PREFIX_USER);
                $this->line('✓ Users cache cleared');
                break;

            case 'organizations':
                $this->cacheService->forgetByPrefix(CacheService::PREFIX_ORGANIZATION);
                $this->line('✓ Organizations cache cleared');
                break;

            case 'events':
                $this->cacheService->forgetByPrefix(CacheService::PREFIX_EVENT);
                $this->line('✓ Events cache cleared');
                break;

            case 'invitations':
                $this->cacheService->forgetByPrefix(CacheService::PREFIX_INVITATION);
                $this->line('✓ Invitations cache cleared');
                break;

            case 'stats':
                $this->cacheService->forgetByPrefix(CacheService::PREFIX_STATS);
                $this->line('✓ Statistics cache cleared');
                break;

            case 'all':
            default:
                Cache::flush();
                $this->line('✓ All cache cleared');
                break;
        }
    }

    /**
     * Clear cache by specific pattern
     */
    private function clearByPattern(string $pattern): void
    {
        try {
            $driver = config('cache.default');
            
            if ($driver === 'redis') {
                $keys = Cache::getRedis()->keys($pattern);
                
                if (!empty($keys)) {
                    Cache::getRedis()->del($keys);
                    $this->line("✓ Cleared " . count($keys) . " cache keys matching pattern: {$pattern}");
                } else {
                    $this->line("No cache keys found matching pattern: {$pattern}");
                }
            } else {
                // For non-Redis drivers, use the service method
                $this->cacheService->forgetByPrefix($pattern);
                $this->line("✓ Cleared cache keys matching pattern: {$pattern}");
            }
        } catch (\Exception $e) {
            $this->error("Error clearing cache pattern {$pattern}: " . $e->getMessage());
        }
    }
}
