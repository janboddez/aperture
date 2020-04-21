<?php

namespace App\Events;

use App\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaDeleting
{
    use Dispatchable;
    use SerializesModels;

    public $file;

    public function __construct(Media $file)
    {
        $this->file = $file;
    }
}
