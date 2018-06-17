<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array 
     */
    protected $commands = [
        //
		 '\App\Console\Commands\tokens',
		 '\App\Console\Commands\fiat',
		 '\App\Console\Commands\rates',
		 '\App\Console\Commands\tx',
		 '\App\Console\Commands\btc',
		 '\App\Console\Commands\ltc',
		 '\App\Console\Commands\bch',
		 '\App\Console\Commands\btg',
		 '\App\Console\Commands\dash',
		 '\App\Console\Commands\zcash',
		 '\App\Console\Commands\btctestnet',
		 '\App\Console\Commands\btgtestnet',
		 '\App\Console\Commands\bchtestnet'
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('activations:clean')->daily();
		$schedule->command('fiat:update')->hourly();
		$schedule->command('tokens:update')->everyMinute();
		$schedule->command('tx:update')->everyMinute()->withoutOverlapping();
		$schedule->command('rates:update')->everyMinute()->withoutOverlapping();
		$schedule->command('btc:run')->everyTenMinutes();
		$schedule->command('ltc:run')->everyFiveMinutes();
		$schedule->command('bch:run')->everyTenMinutes();
		$schedule->command('btg:run')->everyTenMinutes();
		$schedule->command('dash:run')->everyTenMinutes();
		$schedule->command('zcash:run')->everyFiveMinutes();
		$schedule->command('btctestnet:run')->everyTenMinutes();
		$schedule->command('bchtestnet:run')->everyTenMinutes();
		
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
