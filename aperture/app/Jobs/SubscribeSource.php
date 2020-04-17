<?php

namespace App\Jobs;

use App\Channel;
use App\Events\SourceAdded;
use App\Source;
use App\User;
use Celd\Opml\Model\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SubscribeSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $channelId;
    protected $feed;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $channelId, Feed $feed)
    {
        $this->channelId = $channelId;
        $this->feed = $feed;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $channel = Channel::findOrFail($this->channelId);

        if ($this->feed->getType() === 'microformats') {
            $source = Source::where('url', $this->feed->getHtmlUrl())->first();
        } else {
            $source = Source::where('url', $this->feed->getXmlUrl())->first();
        }

        if (! $source) {
            $source = new Source();
            $source->created_by = $channel->user_id;

            if ($this->feed->getType() === 'microformats') {
                $source->url = $this->feed->getHtmlUrl();
            } else {
                $source->url = $this->feed->getXmlUrl();
            }

            $source->format = $this->feed->getType() ?? 'rss';
            $source->token = str_random(32);
            $source->is_new = true;

            $source->save();
        }

        if ($channel->sources()->where('source_id', $source->id)->count() === 0) {
            $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);
            $channel->sources()->updateExistingPivot($source->id, ['name' => $this->feed->getTitle()]);
        }

        event(new SourceAdded($source, $channel));
    }
}
