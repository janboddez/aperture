<?php

namespace App\Http\Controllers;

use App\Channel;
use App\Entry;
use App\Events\EntrySaved;
use App\Source;
use Log;
use p3k\XRay;
use p3k\XRay\Formats\Format;
use Request;
use Response;

class WebSubReceiverController extends Controller
{
    public function __construct()
    {
        $this->middleware('websub');
    }

    public function source_callback($token)
    {
        //Log::info('WebSub callback: '.$token);

        $source = Source::where('token', $token)->first();
        if (! $source) {
            Log::warning('Source not found');

            return Response::json(['error' => 'not_found'], 404);
        }

        if ($source->channels()->count() === 0) {
            Log::warning('Source:'.$source->id.' ('.parse_url($source->url, PHP_URL_HOST).') is not associated with any channels, skipping and unsubscribing');
            \App\Jobs\UnsubscribeSource::dispatch($source);

            return Response::json(['result' => 'empty'], 200);
        }

        $source_is_empty = $source->is_new;

        $content_type = Request::header('Content-Type');
        $body = Request::getContent();

        $xray = new XRay();
        $parsed = $xray->parse($source->url, $body, ['expect' => 'feed']);

        if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] === 'feed') {
            $new_entries = 0;
            $entry_ids = []; // keep track of all entries in the currect snapshot

            // Check each entry in the feed to see if we've already seen it
            // Add new entries to any channels that include this source
            foreach ($parsed['data']['items'] as $i => $item) {
                // Prefer uid, then url, then hash the content
                if (isset($item['uid'])) {
                    $unique = '@'.$item['uid'];
                } elseif (isset($item['url'])) {
                    $unique = $item['url'];
                } else {
                    $unique = '#'.md5(json_encode($item));
                }

                // TODO: If the entry reports a URL that is different from the domain that the feed is from,
                // kick off a job to fetch the original post and process it rather than using the data from the feed.

                $data = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                $entry = Entry::where('source_id', $source->id)
                    ->where('unique', $unique)
                    ->first();

                if (! $entry) {
                    $entry = new Entry();
                    $entry->source_id = $source->id;
                    $entry->unique = $unique;
                    $entry_is_new = true;

                    ++$new_entries;
                } else {
                    $entry_is_new = false;
                    $hash = md5($data);
                }

                $entry->data = $data;
                $entry->currently_in_feed = true;

                // Also cache the published date for sorting
                if (isset($item['published'])) {
                    $entry->published = date('Y-m-d H:i:s', strtotime($item['published']));

                    // Replace somehow faulty dates with the current date.
                    if ($entry->published === '1970-01-01 00:00:00') {
                        $entry->published = date('Y-m-d H:i:s');
                    }
                }

                if ($entry_is_new || md5($entry->data) !== $hash) {
                    $entry->save();
                    event(new EntrySaved($entry));
                }

                $entry_ids[] = $entry->id;

                if (! $entry_is_new) {
                    continue;
                }

                Log::info('Adding entry '.$entry->unique.' to channels');
                // Loop through each channel associated with this source and add the entry
                foreach ($source->channels()->get() as $channel) {
                    $shouldAdd = $channel->should_add_entry($entry);

                    if (! $shouldAdd) {
                        Log::info('  Skipping channel '.$channel->name.' #'.$channel->id.' due to filter');
                        continue;
                    }

                    Log::info('  Adding to channel '.$channel->name.' #'.$channel->id);
                    // If the source was previously empty, use the published date on the entry in
                    // order to avoid flooding the channel with new posts
                    // TODO: it's possible that this will create a conflicting record based on the published_date and batch_order.
                    // To really solve this, we'd need to first query the channel_entry table to find any records that match
                    // the `created_at` we're about to use, and increment the `batch_order` higher than any that were found.
                    // This is likely rare enough that I'm not going to worry about it for now.
                    $created_at = ($source_is_empty && $entry->published ? $entry->published : gmdate('Y-m-d H:i:s'));

                    if (strtotime($created_at) <= 0) {
                        $created_at = '1970-01-01 00:00:01';
                    }

                    // $item = json_decode($entry->data, true);
/*
                    $originalData = null;

                    if (isset($item['url']) && $channel->pivot->fetch_original) {
                        // Fetch original content is enabled for this channel.
                        Log::debug('Trying to fetch original content at '.$item['url']);

                        if ($source->format === 'microformats' && empty($channel->pivot->xpath_selector)) {
                            // Expecting microformats. Let XRay handle things.
                            $data = $xray->parse($item['url'], ['timeout' => 15]);

                            if (! empty($data['data'])) {
                                if (! empty($data['data']['published']) && $data['data']['published'] === '1970-01-01 00:00:00') {
                                    $data['data']['published'] = gmdate('Y-m-d H:i:s');
                                }

                                $originalData = json_encode($data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            } elseif (! empty($data['error_description'])) {
                                Log::error('Fetching failed: '.$data['error_description']);
                            }
                        } else {
                            // Going to just fetch the HTML and sanitize it.
                            // To do: add headers, etc.
                            try {
                                $html = file_get_contents($item['url']);
                                $html = mb_convert_encoding($html, 'HTML-ENTITIES', mb_detect_encoding($html));

                                libxml_use_internal_errors(true);

                                $doc = new \DOMDocument();
                                $doc->loadHTML($html, LIBXML_HTML_NODEFDTD);

                                $xpath = new \DOMXPath($doc);

                                $selector = $channel->pivot->xpath_selector ?? '//main';
                                // Log::debug($selector);

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
*/
                    $channel->entries()->attach($entry->id, [
                        'created_at' => $created_at,
                        'seen' => ($channel->read_tracking_mode === 'disabled' || $source_is_empty ? 1 : 0),
                        'batch_order' => $i,
                        // 'original_data' => $originalData,
                    ]);

                    if (isset($item['url']) && $channel->pivot->fetch_original) {
                        // Fetch original content is enabled for this channel.
                        Log::debug('Trying to fetch original content at '.$item['url']);
                        $entry->fetchOriginalContent($channel);
                    }
                }
            }

            if ($new_entries > 0 && $source->is_new) {
                // Mark the source as no longer new if we added any entries
                $source->is_new = false;
                $source->save();
            }

            // Mark any entries that used to be in the feed as no longer in the feed
            $entries = Entry::where('source_id', $source->id)
                ->where('currently_in_feed', true)
                ->whereNotIn('id', $entry_ids)
                ->update([
                    'currently_in_feed' => false,
                ]);
        } else {
            Log::error('Error parsing source from '.$source->url);
        }
    }
}
