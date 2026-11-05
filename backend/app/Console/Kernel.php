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
        $schedule->command('billing:generate-monthly-invoices')
            ->monthlyOn((int) config('billing.invoice.monthly_generation_day', 15), '02:00')
            ->withoutOverlapping();

        $schedule->command('billing:generate-payment-reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        $schedule->command('billing:send-payment-reminders --limit=1')
            ->everyMinute()
            ->withoutOverlapping();
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
