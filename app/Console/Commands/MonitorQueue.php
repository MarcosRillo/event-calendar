<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor queue status and show statistics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📊 Estado del Sistema de Colas');
        $this->line('');

        // Trabajos pendientes
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->line("📧 Emails en cola: <fg=yellow>{$pendingJobs}</>");
        $this->line("❌ Emails fallidos: <fg=red>{$failedJobs}</>");
        
        if ($pendingJobs > 0) {
            $this->line('');
            $this->warn('⚠️  Hay emails esperando a ser procesados');
            
            // Mostrar detalles de los trabajos pendientes
            $jobs = DB::table('jobs')
                ->select('queue', 'created_at', 'payload')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
                
            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $this->line("  • {$jobClass} (Cola: {$job->queue})");
            }
        } else {
            $this->info('✅ Todas las colas están vacías');
        }

        if ($failedJobs > 0) {
            $this->line('');
            $this->error('💥 Hay trabajos fallidos que requieren atención');
            $this->line('Ejecuta: php artisan queue:failed para ver detalles');
            $this->line('Para reintentar: php artisan queue:retry all');
        }

        $this->line('');
        $this->info('🔄 Para procesar manualmente: php artisan queue:work --once');
        
        return 0;
    }
}
