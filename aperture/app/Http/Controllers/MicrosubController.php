<?php

namespace App\Http\Controllers;

use App\Channel;
use App\Entry;
use App\Events\SourceAdded;
use App\Source;
use Auth;
use p3k\XRay;
use Request;
use Response;

class MicrosubController extends Controller
{
    private static function _actions()
    {
        return [
            'timeline' => 'read',
            'follow' => 'follow',
            'unfollow' => 'follow',
            'mute' => 'mute',
            'unmute' => 'mute',
            'block' => 'block',
            'unblock' => 'block',
            'read-channels' => 'read',
            'write-channels' => 'channels',
            'search' => '',
            'preview' => '',
        ];
    }

    private function _verifyScopeForAction($action)
    {
        $expect = self::_actions()[$action];

        if ($expect) {
            $tokenData = Request::get('token_data');

            if (isset($tokenData['scope']) && is_array($tokenData['scope'])) {
                return in_array($expect, $tokenData['scope']);
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    private function _verifyAction($action)
    {
        $actions = self::_actions();
        if (! array_key_exists($action, $actions)) {
            return Response::json([
                'error' => 'bad_request',
                'error_description' => 'This operation is not supported',
            ], 400);
        }

        return true;
    }

    private function _getRequestChannel()
    {
        // For channel tokens, force the request channel to the channel defined in the token
        $td = Request::get('token_data');

        if (isset($td['type']) && 'channel' === $td['type']) {
            return Channel::where('id', $td['channel_id'])
                ->first();
        } else {
            if (! Request::input('channel')) {
                return Response::json([
                    'error' => 'invalid_input',
                    'error_description' => 'No channel specified',
                ], 400);
            }

            $uid = Request::input('channel');

            if ('unread' === $uid) {
                return $uid;
            }

            $channel = Channel::where('user_id', Auth::user()->id)
                ->where('uid', $uid)
                ->first();

            if (! $channel) {
                return Response::json([
                    'error' => 'not_found',
                    'error_description' => 'Channel not found',
                ], 404);
            }

            return $channel;
        }
    }

    public function get(Request $request)
    {
        $token_data = Request::get('token_data');

        $action = Request::input('action');

        switch ($action) {
            // Any items that have a different read/write scope, should be added as cases
            case 'channels':
                $scopeKey = 'read-'.$action;
                break;
            default:
                $scopeKey = $action;
        }

        $verify = $this->_verifyAction($scopeKey);
        if (true !== $verify) {
            return $verify;
        }

        if (! method_exists($this, 'get_'.$action)) {
            return Response::json([
                'error' => 'not_implemented',
                'error_description' => 'This method has not yet been implemented',
            ], 400);
        }

        if (! $this->_verifyScopeForAction($scopeKey)) {
            return Response::json([
                'error' => 'unauthorized',
                'error_description' => 'The access token provided does not have the necessary scope for this action',
            ], 401);
        }

        return $this->{'get_'.$action}();
    }

    public function post(Request $request)
    {
        $token_data = Request::get('token_data');

        $action = Request::input('action');

        switch ($action) {
            // Any items that have a different read/write scope, should be added as cases
            case 'channels':
                $scopeKey = 'write-'.$action;
                break;
            default:
                $scopeKey = $action;
        }

        $verify = $this->_verifyAction($scopeKey);
        if (true !== $verify) {
            return $verify;
        }

        if (! method_exists($this, 'post_'.$action)) {
            return Response::json([
                'error' => 'not_implemented',
                'error_description' => 'This method has not yet been implemented',
            ], 400);
        }

        if (! $this->_verifyScopeForAction($scopeKey)) {
            return Response::json([
                'error' => 'unauthorized',
                'error_description' => 'The access token provided does not have the necessary scope for this action',
            ], 401);
        }

        return $this->{'post_'.$action}();
    }

    ////////////////////////////////////////////////////////////////////////////

    private function get_channels()
    {
        $channelIds = Auth::user()->channels()->pluck('id');

        $count = Entry::whereHas('channels', function ($query) use ($channelIds) {
            $query->whereIn('channel_entry.channel_id', $channelIds)
                    ->where('channel_entry.seen', 0);
        })
        ->distinct()
        ->count();

        $channels = [];

        $channels[] = [
            'uid' => 'unread',
            'name' => __('Unread'),
            'unread' => $count,
        ];

        foreach (Auth::user()->channels()->get() as $channel) {
            $channels[] = $channel->to_array();
        }

        return [
            'channels' => $channels,
        ];
    }

    private function post_channels()
    {
        if ('delete' == Request::input('method')) {
            // Delete

            if (! Request::input('channel')) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Missing channel parameter'], 400);
            }

            if (in_array(Request::input('channel'), ['notifications', 'global'])) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Cannot delete system channels'], 400);
            }

            $channel = Auth::user()->channels()->where('uid', Request::input('channel'))->first();

            if (! $channel) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Channel not found'], 400);
            }

            $channel->entries()->delete();
            $channel->delete();

            return Response::json(['deleted' => true]);
        } elseif ('order' == Request::input('method')) {
            // Set Channel Order

            if (! Request::input('channels')) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Missing channels parameter'], 400);
            }

            if (! is_array(Request::input('channels'))) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'channels parameter must be an array'], 400);
            }

            $inputChannels = Request::input('channels');

            $sorted = Auth::user()->set_channel_order($inputChannels);

            if (! $sorted) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'One or more channels were not found'], 400);
            }

            return Response::json(['channels' => $sorted]);
        } elseif (Request::input('channel')) {
            // Update

            if (! Request::input('channel')) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Missing channel parameter'], 400);
            }

            if (in_array(Request::input('channel'), ['notifications', 'global'])) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Cannot rename system channels'], 400);
            }

            $channel = Auth::user()->channels()->where('uid', Request::input('channel'))->first();

            if (! $channel) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Channel not found'], 400);
            }

            if (Request::input('name')) {
                $channel->name = Request::input('name');
                $channel->save();
            }

            return Response::json($channel->to_array());
        } else {
            // Create

            if (! trim(Request::input('name'))) {
                return Response::json(['error' => 'invalid_input', 'error_description' => 'Missing name parameter'], 400);
            }

            $channels = [];
            foreach (Auth::user()->channels()->get() as $channel) {
                $channels[] = $channel->name;
            }

            if (in_array(Request::input('name'), $channels)) {
                return Response::json(['error' => 'duplicate'], 400);
            }

            $channel = Auth::user()->create_channel(Request::input('name'));

            return Response::json($channel->to_array());
        }
    }

    private function post_search()
    {
        if (null == Request::input('channel')) {
            // Search for feeds matching the query

            if (! Request::input('query')) {
                return Response::json(['error' => 'invalid_query'], 400);
            }

            // The query might be:
            // * a full URL
            // * a term that could be normalized to a URL (e.g. "example.com")
            // * a generic term

            $url = false;
            $query = Request::input('query');

            if (\p3k\url\is_url($query)) {
                // Directly entered URL including scheme
                $url = \p3k\url\normalize($query);
            } else {
                if (preg_match('/^[a-z][a-z0-9]+$/', $query)) {
                    // if just a word was entered, append .com
                    $possible_url = $query.'.com';
                } else {
                    $possible_url = $query;
                }
                // Possible URL that may require adding a scheme
                $possible_url = \p3k\url\normalize($possible_url);
                // Check if the hostname has at least one dot
                if (false !== strpos(parse_url($possible_url, PHP_URL_HOST), '.')) {
                    $url = $possible_url;
                }
            }

            $http = new \p3k\HTTP();
            $http->set_user_agent(env('USER_AGENT'));
            $http->timeout = 4;

            $xray = new XRay();
            $xray->http = $http;
            $response = $xray->feeds($url);

            $feeds = [];

            if (! isset($response['error']) && 200 == $response['code']) {
                $feeds = $response['feeds'];
            }

            foreach ($feeds as $i => $feed) {
                $feeds[$i]['type'] = 'feed';
            }

            // TODO: also search existing feeds in the database that may be indexed

            return Response::json([
                'results' => $feeds,
            ]);
        } else {
            // TODO: Search within channels for posts matching the query

            return Response::json([
                'error' => 'not_implemented',
            ], 400);
        }
    }

    private function get_preview()
    {
        // If the feed is already in the database, return those results
        $source = Source::where('url', Request::input('url'))->first();

        $items = [];

        if ($source) {
            $entries = $source->entries()
                ->select('entries.*')
                ->orderByDesc('created_at')
                ->orderByDesc('published')
                ->limit(20)
                ->get();

            foreach ($entries as $entry) {
                $items[] = $entry->to_array();
            }
        } else {
            // Fetch the feed and return the first results
            $http = new \p3k\HTTP();
            $http->set_user_agent(env('USER_AGENT'));
            $http->timeout = 4;

            $xray = new XRay();
            $xray->http = $http;
            $parsed = $xray->parse(Request::input('url'), ['expect' => 'feed']);

            if ($parsed && isset($parsed['data']['type']) && 'feed' == $parsed['data']['type']) {
                $items = $parsed['data']['items'];
            }
        }

        $response = [
            'items' => $items,
        ];

        return Response::json($response);
    }

    private function get_timeline()
    {
        $limit = ((int) Request::input('limit')) ?: 20;

        $entries = Entry::orderBy('published', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1);

        if ('unread' === Request::input('channel')) {
            $entries = $entries->whereHas('channels', function ($query) {
                // Fetch only unseen entries in any of the current user's
                // channels that support read tracking.
                $query->where('channels.user_id', Auth::id())
                    ->where('channels.read_tracking_mode', '!=', 'disabled')
                    ->where('channel_entry.seen', 0);
            })
            ->with(['channels' => function ($query) {
                // Eager load only the current user's related channels.
                $query->where('channels.user_id', Auth::id())
                    ->where('channels.read_tracking_mode', '!=', 'disabled');
            }]);
        } else {
            $entries = $entries->whereHas('channels', function ($query) {
                // Fetch entries in a particular channel, if it belongs to
                // the current user.
                $query->where('channels.user_id', Auth::id())
                    ->where('channels.uid', Request::input('channel'));

                if ('false' === Request::input('is_read')) {
                    // Unread items only.
                    $query->where('channel_entry.seen', 0);
                }
            })
            ->with(['channels' => function ($query) {
                // Eager load only the current channel.
                $query->where('channels.uid', Request::input('channel'));
            }]);
        }

        // Return items in a particular source.
        if (Request::input('source')) {
            $entries = $entries->where('source_id', Request::input('source'));
        }

        if (Request::input('before')) {
            if (! ($before = $this->_parseEntryCursor(Request::input('before')))) {
                return Response::json(['error' => 'invalid_cursor'], 400);
            }

            $entries = $entries->where(function ($query) use ($before) {
                $query->where('entries.published', '>', $before[0])
                    ->orWhere(function ($query) use ($before) {
                        $query->where('entries.published', '=', $before[0])
                            ->where('entries.id', '>', $before[1]);
                    });
            });
        }

        if (Request::input('after')) {
            if (! ($after = $this->_parseEntryCursor(Request::input('after')))) {
                return Response::json(['error' => 'invalid_cursor'], 400);
            }

            $entries = $entries->where(function ($query) use ($after) {
                $query->where('entries.published', '<', $after[0])
                    ->orWhere(function ($query) use ($after) {
                        $query->where('entries.published', '=', $after[0])
                            ->where('entries.id', '<=', $after[1]);
                    });
            });
        }

        $entries = $entries->get();

        if (! $entries) {
            return Response::json([
                'error' => 'not_found',
                'error_description' => 'No entries found',
            ], 404);
        }

        $newbefore = '';
        $newafter = '';
        $items = [];

        foreach ($entries as $i => $entry) {
            if (0 === $i) {
                // Always include a cursor to be able to return newer entries.
                $newbefore = $this->_buildEntryCursor($entry);
            }

            if ($i < $limit) {
                // No need to pass on a channel. It's eager loaded into
                // `$entry->channels[0]`.
                $items[] = $entry->to_array();
            }

            if ($i === $limit) {
                // Don't add the last item, but return a cursor for the next
                // page.
                $newafter = $this->_buildEntryCursor($entry);
            }
        }

        $response = [
            'items' => $items,
            'paging' => [],
        ];

        if (Request::input('source')) {
            $source = Source::where('id', (int) Request::input('source'))->first();
            $response['source'] = $source->to_array();
        }

        if ($newbefore) {
            $response['paging']['before'] = $newbefore;
        }

        if ($newafter) {
            $response['paging']['after'] = $newafter;
        }

        return Response::json($response);
    }

    private function post_timeline()
    {
        $channel = $this->_getRequestChannel();

        // Check that the channel exists.
        if ('unread' !== $channel && Channel::class !== get_class($channel)) {
            return $channel;
        }

        switch (Request::input('method')) {
            case 'fetch_original':
                if (! Request::input('entry')) {
                    return response()->json([
                        'error' => 'not_found',
                        'error_description' => 'The entry ID provided was not found',
                    ], 404);
                }

                $entry = $channel->entries()
                    ->where('entries.id', Request::input('entry'))
                    ->firstOrFail();

                $entry->fetchOriginalContent($channel);

                break;

            case 'mark_read':
                if (Request::input('last_read_entry')) {
                    $entry = $channel->entries()
                        ->where('channel_entry.entry_id', Request::input('last_read_entry'))
                        ->firstOrFail();

                    $result = $channel->mark_entries_read_before($entry);

                    return Response::json([
                        'result' => 'ok',
                        'updated' => $result,
                    ]);
                } elseif (Request::input('entry')) {
                    if (! is_array(Request::input('entry'))) {
                        $entryIds = [Request::input('entry')];
                    } else {
                        $entryIds = Request::input('entry');
                    }

                    if ('unread' === $channel) {
                        $result = 0;

                        // All channels that include at least one of these
                        // entries.
                        $channels = Channel::whereHas('entries', function ($query) use ($entryIds) {
                            $query->whereIn('entries.id', $entryIds);
                        })
                        ->get();

                        foreach ($channels as $channel) {
                            $result += $channel->mark_entries_read($entryIds);
                        }
                    } else {
                        $result = $channel->mark_entries_read($entryIds);
                    }

                    return Response::json([
                        'result' => 'ok',
                        'updated' => $result,
                    ]);
                } else {
                    return Response::json([
                        'error' => 'invalid_input',
                        'error_description' => 'To mark one or more entries as read, include an entry id or last_read_entry parameter',
                    ], 400);
                }

                break;

            case 'mark_unread':
                if (! Request::input('entry')) {
                    return Response::json([
                        'error' => 'invalid_input',
                        'error_description' => 'To mark one or more entries as unread, include an entry id',
                    ], 400);
                }

                if (! is_array(Request::input('entry'))) {
                    $entryIds = [Request::input('entry')];
                } else {
                    $entryIds = Request::input('entry');
                }

                if ('unread' === $channel) {
                    $result = 0;

                    // All channels that include at least one of these
                    // entries.
                    $channels = Channel::whereHas('entries', function ($query) use ($entryIds) {
                        $query->whereIn('entries.id', $entryIds);
                    })
                    ->get();

                    foreach ($channels as $channel) {
                        $result += $channel->mark_entries_unread($entryIds);
                    }
                } else {
                    $result = $channel->mark_entries_unread($entryIds);
                }

                return Response::json([
                    'result' => 'ok',
                    'updated' => $result,
                ]);

            case 'remove':
                if (Request::input('entry')) {
                    return Response::json([
                        'error' => 'invalid_input',
                        'error_description' => 'To mark one or more entries as unread, include an entry id',
                    ], 400);
                }

                if ('unread' === $channel) {
                    return Response::json([
                        'result' => 'ok',
                        'updated' => 0,
                    ]);
                }

                if (! is_array(Request::input('entry'))) {
                    $entryIds = [Request::input('entry')];
                } else {
                    $entryIds = Request::input('entry');
                }

                $result = $channel->remove_entries($entryIds);

                return Response::json([
                    'result' => 'ok',
                    'updated' => $result,
                ]);

            default:
                return Response::json([
                    'error' => 'invalid_method',
                    'error_description' => 'The specified method was not found for this action',
                ], 400);
        }
    }

    private function get_follow()
    {
        $channel = $this->_getRequestChannel();

        if (Channel::class !== get_class($channel)) {
            return $channel;
        }

        $following = [];

        $sources = $channel->sources()
            ->where('url', '!=', '')
            ->get();

        foreach ($sources as $source) {
            $feed = [
                'type' => 'feed',
                'url' => $source->url,
            ];

            if ($source->pivot->name) {
                $feed['name'] = $source->pivot->name;
            }

            $following[] = $feed;
        }

        return [
            'items' => $following,
        ];
    }

    private function post_follow()
    {
        $channel = $this->_getRequestChannel();

        if (Channel::class !== get_class($channel)) {
            return $channel;
        }

        $source = Source::where('url', Request::input('url'))
            ->first();

        if (! $source) {
            $source = new Source();
            $source->created_by = Auth::user()->id;
            $source->url = Request::input('url');
            $source->token = str_random(32);
            $source->is_new = true;
            $source->save();
        }

        if (0 === $channel->sources()->where('source_id', $source->id)->count()) {
            $channel->sources()->attach($source->id, ['created_at' => date('Y-m-d H:i:s')]);
        }

        event(new SourceAdded($source, $channel));

        return [
            'type' => 'feed',
            'url' => $source->url,
        ];
    }

    private function post_unfollow()
    {
        $channel = $this->_getRequestChannel();

        if (Channel::class !== get_class($channel)) {
            return $channel;
        }

        $source = Source::where('url', Request::input('url'))
            // Allow unfollowing by ID, too
            ->orWhere('id', trim(Request::input('url'), '_'))
            ->first();

        if (! $source) {
            return '';
        }

        $channel->remove_source($source);

        return [
            'type' => 'feed',
            'url' => $source->url,
        ];
    }
}
