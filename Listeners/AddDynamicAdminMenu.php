<?php

namespace App\Listeners;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class AddDynamicAdminMenu
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  BuildingMenu  $event
     * @return void
     */
    public function handle(BuildingMenu $event)
    {
		$event->menu->add('EXCHANGER');
        $event->menu->add([
                'text' => 'Exchange Rates',
                'url' => route('rates.index'),
				'icon' => 'exchange',
         ]);
		 $event->menu->add([
                'text' => 'New Rate',
                'url' => route('rates.create'),
				'icon' => 'edit',
         ]);
		$event->menu->add('MY ACCOUNT');
        $event->menu->add([
                'text' => 'Edit My acccount',
                'url' => 'admin/users/'.\Auth::user()->id.'/edit',
				'icon' => 'user',
         ]);
		$event->menu->add('INFO');
		$event->menu->add([
            'text'       => date('D d M Y'),
            'icon_color' => 'red',
        ]);
		$event->menu->add([
            'text'       => '&copy; ecurrency-hub.com',
            'icon_color' => 'yellow',
        ]);
    }
}
