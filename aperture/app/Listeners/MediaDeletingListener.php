<?php

namespace App\Listeners;

use App\Events\MediaDeleting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log;
use Storage;

class MediaDeletingListener // implements ShouldQueue
{
    public function handle(MediaDeleting $event)
    {
        Log::info('Deleting file: '.$event->file->filename);
        Storage::delete('media/'.$event->file->filename);
    }
}
