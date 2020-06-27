<?php

namespace App;

use App\Events\EntryCreating;
use App\Events\EntryDeleting;
use Illuminate\Database\Eloquent\Model;
use Log;
use p3k\XRay;
use p3k\XRay\Formats\Format;

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
        return $this->belongsToMany('\App\Channel')
            ->withPivot(['seen', 'original_data']);
    }

    public function media()
    {
        return $this->belongsToMany('\App\Media');
    }

    public function permalink()
    {
        return env('APP_URL').'/entry/'.$this->source->id.'/'.$this->unique;
    }

    public function to_array()
    {
        $data = json_decode($this->data, true);

        if (! empty($this->channels) && count($this->channels) === 1) {
            // Loaded through a specific channel, or belonging to just one
            // channel.
            $channel = $this->channels[0];
        }

        if (! empty($channel->pivot->original_data)) {
            // If loaded through channel and "original data" exists.
            $data = json_decode($channel->pivot->original_data, true);
        }

        if (isset($channel) && $channel->read_tracking_mode !== 'disabled') {
            $data['_is_read'] = (bool) $channel->pivot->seen;
        } else {
            // Most likely loaded through "Unread" channel (and part of multiple
            // channels). Consider unread.
            $data['_is_read'] = false;
        }

        unset($data['uid']);

        // Include some Microsub info.
        $data['_id'] = (string) $this->id;
        $data['_source'] = (string) $this->source_id;
        $data['_channel'] = $channel->uid ?? $this->channels[0]->uid ?? null;

        return $data;
    }

    public function matches_keyword($keyword)
    {
        $data = json_decode($this->data, true);

        $matches = false;

        // Check the name, content.text, and category values for a keyword match

        if (isset($data['name'])) {
            if (stripos($data['name'], $keyword) !== false) {
                $matches = true;
            }
        }

        if (! $matches && isset($data['content'])) {
            if (stripos($data['content']['text'], $keyword) !== false) {
                $matches = true;
            }
        }

        if (! $matches && isset($data['category'])) {
            foreach ($data['category'] as $c) {
                if (strtolower($c) === strtolower($keyword)) {
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

        if ($data['type'] === 'event') {
            return 'event';
        }

        // Experimental
        if ($data['type'] === 'card') {
            return 'card';
        }

        // Experimental
        if ($data['type'] === 'review') {
            return 'review';
        }

        // Experimental
        if ($data['type'] === 'recipe') {
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

    public function fetchOriginalContent(Channel $channel)
    {
        $originalData = null;
        $item = json_decode($this->data, true);

        // The XPath selector, used to extract HTML, lives in the `channel_source` table.
        $source = $channel->sources()
            ->where('channel_source.source_id', $this->source_id)
            ->firstOrFail();

        if (isset($item['url'])) {
            Log::info('Trying to fetch original content at '.$item['url']);

            if ($source->format === 'microformats' && empty($source->pivot->xpath_selector)) {
                // Expecting microformats. Let XRay handle things.
                $xray = new XRay();
                $data = $xray->parse($item['url'], ['timeout' => 15]);

                if (! empty($data['data'])) {
                    $originalData = json_encode($data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                } elseif (! empty($data['error_description'])) {
                    Log::error('Fetching failed: '.$data['error_description']);
                }
            } else {
                // Going to just fetch the HTML and sanitize it.
                // To do: add headers to `file_get_contents()`, etc.
                try {
                    $html = file_get_contents($item['url']);
                    $html = mb_convert_encoding($html, 'HTML-ENTITIES', mb_detect_encoding($html));

                    libxml_use_internal_errors(true);

                    $doc = new \DOMDocument();
                    $doc->loadHTML($html, LIBXML_HTML_NODEFDTD);

                    $xpath = new \DOMXPath($doc);

                    $selector = $source->pivot->xpath_selector ?? '//main';

                    $result = $xpath->query($selector);
                    $value = '';

                    foreach ($result as $node) {
                        // As is, multiple matching nodes will be concatenated.
                        $value .= $doc->saveHTML($node).PHP_EOL;
                    }

                    $value = trim($value);

                    if (! empty($value)) {
                        // Using reflections to call protected methods of the (abstract) Format class.
                        $sanitizeHTML = new \ReflectionMethod(Format::class, 'sanitizeHTML');
                        $sanitizeHTML->setAccessible(true);
                        $stripHTML = new \ReflectionMethod(Format::class, 'stripHTML');
                        $stripHTML->setAccessible(true);

                        // Sanitize and inject the newly fetched entry content.
                        $item['content']['html'] = $sanitizeHTML->invoke(null, $value);
                        $item['content']['text'] = $stripHTML->invoke(null, $value);

                        $originalData = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                } catch (\Exception $e) {
                    // Something went wrong.
                    Log::debug($e->getMessage());
                }
            }
        }

        // Update the database.
        $channel->entries()->updateExistingPivot($this->id, [
            'original_data' => $originalData,
        ]);
    }
}
