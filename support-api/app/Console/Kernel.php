<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {
        // $schedule->command('inspire')->hourly();

        $schedule->job(new \App\Jobs\TicketStats)->everyFiveMinutes(); //ogni 5 min
        $schedule->job(new \App\Jobs\PlatformActivity)->dailyAt('08:00'); //ogni giorno alle 8
        // $schedule->job(new \App\Jobs\TicketStats)->everyMinute();
    }



    /**
     * Register the commands for the application.
     */
    protected function commands(): void {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
