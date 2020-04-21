<?php

namespace App\Events;

use App\Channel;
use App\Source;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SourceAdded
{
    use Dispatchable;
    use SerializesModels;

    public $source;
    public $channel;

    public function __construct(Source $source, Channel $channel)
    {
        $this->source = $source;
        $this->channel = $channel;
    }
}
