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
        $schedule->command('order-files-process')->hourlyAt(01)->withoutOverlapping();
        $schedule->command('order-files-process-secondary')->hourlyAt(15)->withoutOverlapping();
        $schedule->command('get-vendor-products')->weeklyOn(1,'8:00')->withoutOverlapping();
        $schedule->command('get-vendor-products', [15080])->weeklyOn(1,'9:00')->withoutOverlapping();
        $schedule->command('get-vendor-products', [15391])->weeklyOn(1,'10:00')->withoutOverlapping();
        $schedule->command('read-emails')->hourlyAt(30)->withoutOverlapping();
        $schedule->command('process-seawide-orders')->everyTwoHours(50)->withoutOverlapping();
        $schedule->command('app:new-orders-excel')->everyThirtyMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
