<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\EntrySaved' => [
            'App\Listeners\EntrySavedListener',
        ],
        'App\Events\EntryCreating' => [
            'App\Listeners\EntryCreatingListener',
        ],
        'App\Events\EntryDeleting' => [
            'App\Listeners\EntryDeletingListener',
        ],
        'App\Events\MediaDeleting' => [
            'App\Listeners\MediaDeletingListener',
        ],
        'App\Events\SourceAdded' => [
            'App\Listeners\SourceAddedListener',
        ],
        'App\Events\SourceRemoved' => [
            'App\Listeners\SourceRemovedListener',
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
    }
}
