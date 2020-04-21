<?php

namespace App\Listeners;

use App\Events\EntryCreating;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class EntryCreatingListener // implements ShouldQueue
{
    public function handle(EntryCreating $event)
    {
        Redis::incr(env('APP_URL').'::entries');
    }
}
