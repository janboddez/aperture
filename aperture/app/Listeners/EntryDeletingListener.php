<?php

namespace App\Listeners;

use App\Events\EntryDeleting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class EntryDeletingListener // implements ShouldQueue
{
    public function handle(EntryDeleting $event)
    {
        Redis::decr(env('APP_URL').'::entries');

        $media = $event->entry->media()->get();
        foreach ($media as $file) {
            // Check if this file is used by any other entries
            if (1 == $file->entries()->count()) {
                $file->delete();
            }
        }
    }
}
