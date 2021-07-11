<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Common\Auth\Events\UserCreated' => [
            'App\Listeners\CreateWatchlist',
        ],
        'Common\Auth\Events\UsersDeleted' => ['App\Listeners\DeleteUserLists',
        ],

        'App\Events\Event' => [
            'App\Listeners\EventListener',
        ],
        'moki74\LaravelBtc\Events\ConfirmedPaymentEvent' => [
            'App\Listeners\ConfirmedPaymentListener',
        ],

        'moki74\LaravelBtc\Events\UnconfirmedPaymentEvent' => [
            'App\Listeners\UnconfirmedPaymentListener',
        ],

        'moki74\LaravelBtc\Events\UnknownTransactionEvent' => [
            'App\Listeners\UnknownTransactionListener',
        ],
        // Other events and listeners
        BitpayWebhookReceived::class => [
            ProcessBitpayWebhook::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
