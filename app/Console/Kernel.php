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
        // $schedule->command('inspire')->hourly();
        $schedule->command('order-files-process')->hourly()->withoutOverlapping();
        $schedule->command('order-files-process-secondary')->hourly()->withoutOverlapping();
        $schedule->command('get-vendor-products')->weekly()->withoutOverlapping();
        $schedule->command('get-vendor-products',[15080])->weekly()->withoutOverlapping();
        $schedule->command('read-emails')->hourly()->withoutOverlapping();
        $schedule->command('process-seawide-orders')->everyTwoHours()->withoutOverlapping();
        $schedule->command('app:new-orders-excel')->everyThirtyMinutes()->withoutOverlapping();
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
