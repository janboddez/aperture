<?php

namespace App\Http\Controllers;

use App\Channel;
use App\Events\SourceAdded;
use App\Source;
use Auth;
use DB;
use Gate;
use Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard()
    {
        $channels = Auth::user()->channels()->get();
        $archived = Auth::user()->archived_channels()->get();

        return view('dashboard', [
            'channels' => $channels,
            'archived' => $archived,
        ]);
    }

    public function create_channel()
    {
        $channel = Auth::user()->create_channel(Request::input('name'));

        return redirect(route('dashboard'));
    }

    public function channel(Channel $channel)
    {
        if (! Gate::allows('edit-channel', $channel)) {
            abort(401);
        }

        $sources = $channel->sources()
            ->withCount('entries')
            ->get();

        $channels = Auth::user()->channels()
            ->get();

        return view('channel', [
            'channel' => $channel,
            // Sort by name (case-insensitive). (To do: improve attribute naming!)
            'sources' => $sources->sortBy('domain', SORT_NATURAL | SORT_FLAG_CASE),
            'channels' => $channels,
        ]);
    }

    public function add_source(Channel $channel)
    {
        if (! Gate::allows('edit-channel', $channel)) {
            abort(401);
        }

        // Create or load the source
        $source = Source::where('url', Request::input('url'))->first();

        if (! $source) {
            $source = new Source();
            $source->created_by = Auth::user()->id;
            $source->url = Request::input('url');
            $source->format = Request::input('format');
            $source->token = str_random(32);
            $source->is_new = true;
            $source->save();
        }

        if ($channel->sources()->where('source_id', $source->id)->count() === 0) {
            $channel->sources()->attach($source->id, [
                'created_at' => date('Y-m-d H:i:s'),
                'site_url' => Request::input('site_url'),
            ]);
        }

        event(new SourceAdded($source, $channel));

        return response()->json([
            'result' => 'ok',
        ]);
    }

    public function save_channel(Channel $channel)
    {
        if (! Gate::allows('edit-channel', $channel)) {
            abort(401);
        }

        $channel->name = Request::input('name');

        $channel->include_only = Request::input('include_only') ?: '';
        $channel->exclude_types = Request::input('exclude_types') ?: '';

        $keywords = preg_split('/[ ,]+/', Request::input('include_keywords'));
        $channel->include_keywords = implode(' ', $keywords);

        $keywords = preg_split('/[ ,]+/', Request::input('exclude_keywords'));
        $channel->exclude_keywords = implode(' ', $keywords);

        $channel->read_tracking_mode = Request::input('read_tracking_mode') ?: 'counts';

        $channel->hide_in_demo_mode = Request::input('hide_in_demo_mode') ? 1 : 0;
        $channel->archived = Request::input('archived') ? 1 : 0;

        $channel->default_destination = Request::input('default_destination') ?: '';

        // Users with an unlimited retention policy can override per channel
        if (Auth::user()->retention_days === 0) {
            $channel->retention_days = Request::input('retention_days') ?: 0;
        }

        $channel->save();

        return response()->json([
            'result' => 'ok',
        ]);
    }

    public function save_source(Channel $channel, Source $source)
    {
        if (! Gate::allows('edit-channel', $channel)) {
            abort(401);
        }

        /*
        $subscription = DB::table('channel_source')
            ->where('channel_id', $channel->id)
            ->where('source_id', $source->id)
            ->update([
                'name' => Request::input('name'),
                'site_url' => Request::input('site_url'),
                'fetch_original' => ('true' === Request::input('fetch_original') ? 1 : 0),
                'xpath_selector' => Request::input('xpath_selector'),
            ]);
        */

        $channels = Request::input('channels');

        if (empty($channels) || ! is_array($channels)) {
            return response()->json(['result' => 'error'], 400);
        }

        // Fetch the logged in user's channel IDs that match these UIDs.
        $channelIds = Channel::where('channels.user_id', Auth::id())
            ->whereIn('channels.uid', $channels)
            ->pluck('channels.id');

        $channels = [];

        foreach ($channelIds as $channelId) {
            $channels[$channelId] = [
                'name' => Request::input('name'),
                'site_url' => Request::input('site_url'),
                'fetch_original' => (Request::input('fetch_original') === 'true' ? 1 : 0),
                'xpath_selector' => Request::input('xpath_selector'),
            ];
        }

        // Add this source, and above pivot column values, to the matching
        // channels.
        $source->channels()->sync($channels);

        return response()->json(['result' => 'ok']);
    }

    public function remove_source(Channel $channel)
    {
        if (! Gate::allows('edit-channel', $channel)) {
            abort(401);
        }

        $source = Source::where('id', Request::input('source_id'))->first();

        if ($source) {
            $channel->remove_source($source, (bool) Request::input('remove_entries'));
        }

        return response()->json([
            'result' => 'ok',
        ]);
    }

    public function delete_channel(Channel $channel)
    {
        if (! Gate::allows('edit-channel', $channel)) {
            abort(401);
        }

        if (in_array($channel->uid, ['notifications'])) {
            abort(400);
        }

        $channel->entries()->delete();
        $channel->delete();

        return response()->json([
            'result' => 'ok',
        ]);
    }

    public function set_channel_order()
    {
        if (! is_array(Request::input('channels'))) {
            return response()->json(['result' => 'error']);
        }

        $sorted = Auth::user()->set_channel_order(Request::input('channels'));
        if ($sorted) {
            return response()->json(['result' => 'ok']);
        } else {
            return response()->json(['result' => 'error']);
        }
    }

    public function add_apikey(Channel $channel)
    {
        if (! Gate::allows('edit-channel', $channel)) {
            abort(401);
        }

        // Create a new source for this API key
        $source = new Source();
        $source->token = str_random(32);
        $source->name = Request::input('name') ?: '';
        $source->format = 'apikey';
        $source->created_by = Auth::user()->id;
        // always download images when creating posts via micropub
        $source->download_images = true;
        $source->save();

        $channel->sources()->attach($source->id, ['created_at'=>date('Y-m-d H:i:s')]);

        return response()->json([
            'result' => 'ok',
        ]);
    }

    public function find_feeds()
    {
        $url = Request::input('url');
        if (preg_match('/^[a-z][a-z0-9]+$/', $url)) {
            $url = $url.'.com';
        }
        $url = \p3k\url\normalize($url);

        $http = new \p3k\HTTP(env('USER_AGENT'));
        $http->set_timeout(30);
        $xray = new \p3k\XRay();
        $xray->http = $http;
        $response = $xray->feeds($url);

        $feeds = [];

        if (! isset($response['error']) && $response['code'] == 200) {
            $feeds = $response['feeds'];
        }

        return response()->json([
            'feeds' => $feeds,
            'error' => $response['error'] ?? false,
            'error_description' => $response['error_description'] ?? false,
        ]);
    }
}
