<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Monitorear colas cada minuto
        $schedule->command('queue:monitor')
                 ->everyMinute()
                 ->withoutOverlapping();

        // Limpiar trabajos fallidos antiguos (más de 7 días)
        $schedule->command('queue:prune-failed --hours=168')
                 ->daily();

        // Reiniciar workers cada hora para evitar memory leaks
        $schedule->command('queue:restart')
                 ->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
