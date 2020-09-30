<?php

namespace App\Events;

use App\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntrySaved
{
    use Dispatchable;
    use SerializesModels;

    public $entry;

    public function __construct(Entry $entry)
    {
        $this->entry = $entry;
    }
}
