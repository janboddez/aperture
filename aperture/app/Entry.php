<?php

namespace App;

use App\Events\EntryCreating;
use App\Events\EntryDeleting;
use DB;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $fillable = [
        'source_id', 'unique', 'data',
    ];

    protected $dispatchesEvents = [
        'deleting' => EntryDeleting::class,
        'creating' => EntryCreating::class,
    ];

    public function source()
    {
        return $this->belongsTo('\App\Source');
    }

    public function channels()
    {
        return $this->belongsToMany('\App\Channel');
    }

    public function media()
    {
        return $this->belongsToMany('\App\Media');
    }

    public function permalink()
    {
        return env('APP_URL').'/entry/'.$this->source->id.'/'.$this->unique;
    }

    public function to_array($channel = false)
    {
        $data = json_decode($this->data, true);

        if ($channel) {
            $ce = DB::table('channel_entry')
                ->where('channel_id', $channel->id)
                ->where('entry_id', $this->id)
                ->first();

            if (! empty($ce->original_data)) {
                $data = json_decode($ce->original_data, true);
            }

            if ('disabled' != $channel->read_tracking_mode) {
                $data['_is_read'] = (bool) $ce->seen;
            }
        }

        unset($data['uid']); // don't include mf2 uid in the response

        // Include some Microsub info
        $data['_id'] = (string) $this->id;
        $data['_source'] = (string) $this->source_id;

        return $data;
    }

    public function matches_keyword($keyword)
    {
        $data = json_decode($this->data, true);

        $matches = false;

        // Check the name, content.text, and category values for a keyword match

        if (isset($data['name'])) {
            if (false !== stripos($data['name'], $keyword)) {
                $matches = true;
            }
        }

        if (! $matches && isset($data['content'])) {
            if (false !== stripos($data['content']['text'], $keyword)) {
                $matches = true;
            }
        }

        if (! $matches && isset($data['category'])) {
            foreach ($data['category'] as $c) {
                if (strtolower($c) == strtolower($keyword)) {
                    $matches = true;
                }
            }
        }

        return $matches;
    }

    public function has_photo()
    {
        $data = json_decode($this->data, true);

        return isset($data['photo']);
    }

    public function post_type()
    {
        $data = json_decode($this->data, true);

        // Implements Post Type Discovery
        // https://www.w3.org/TR/post-type-discovery/#algorithm

        if ('event' == $data['type']) {
            return 'event';
        }

        // Experimental
        if ('card' == $data['type']) {
            return 'card';
        }

        // Experimental
        if ('review' == $data['type']) {
            return 'review';
        }

        // Experimental
        if ('recipe' == $data['type']) {
            return 'recipe';
        }

        if (isset($data['rsvp'])) {
            return 'rsvp';
        }

        if (isset($data['repost-of'])) {
            return 'repost';
        }

        if (isset($data['like-of'])) {
            return 'like';
        }

        if (isset($data['in-reply-to'])) {
            return 'reply';
        }

        // Experimental
        if (isset($data['bookmark-of'])) {
            return 'bookmark';
        }

        // Experimental
        if (isset($data['checkin'])) {
            return 'checkin';
        }

        if (isset($data['video'])) {
            return 'video';
        }

        if (isset($data['photo'])) {
            return 'photo';
        }

        // XRay has already done the content/name normalization

        if (isset($data['name'])) {
            return 'article';
        }

        return 'note';
    }
}
