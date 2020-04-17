<?php

namespace App\Http\Controllers;

use App\Channel;
use App\User;
use App\Source;
use App\Jobs\SubscribeSource;

use Celd\Opml\Importer;
use Celd\Opml\Model\Category;
use Celd\Opml\Model\FeedList;
use Celd\Opml\Model\Feed;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Auth;
use Gate;

class OpmlController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function import(Request $request)
    {
        $file = $request->file('opml');

        if (! $file->isValid()) {
            // return redirect('dashboard');
            abort(400);
        }

        $importer = new Importer(file_get_contents($file->getPathname()));
        $feedList = $importer->getFeedList();

        foreach ($feedList->getItems() as $item) {
            if ($item->getType() === 'category') {
                $category = trim($item->getTitle());
                $channel = Channel::where('name', $category)
                    ->first();

                if (! $channel) {
                    $channel = Auth::user()->create_channel($category);
                }

                if (! Gate::allows('edit-channel', $channel)) {
                    abort(401);
                }

                foreach ($item->getFeeds() as $feed) {
                    // SubscribeSource::dispatchAfterResponse($channel, $feed);
                    SubscribeSource::dispatch($channel->id, $feed)->onConnection('redis');
                }
            }
        }

        return redirect('dashboard');
    }

    public function export()
    {
        // $user = User::where('id', $user_id)
        //     ->firstOrFail();

        $channels = Channel::where('user_id', Auth::id())
            ->where('hide_in_demo_mode', '!=', 1)
            ->with(['sources' => function ($query) {
                $query->where('format', '!=', 'jsonfeed')
                    ->orderBy('url');
            }])
            ->orderBy('name')
            ->get();

        // Force an explicit content type
        // return Response(
        //     view('layouts.opml', compact('channels')),
        //     200,
        //     ['Content-Type' => 'text/xml; charset=utf-8']
        // );

        $feedList = new FeedList();

        foreach ($channels as $channel) {
            $category = new Category();
            $category->setTitle($channel->name);

            foreach ($channel->sources as $source) {
                $feed = new Feed();
                $feed->setTitle($source->pivot->name);
                $feed->setType($source->format);

		// Decode already encoded ampersands, then encode all ampersands
		$url = str_replace('&amp;', '&', $source->url);
		$url = str_replace('&', '&amp;', $source->url);

                if ($source->format === 'microformats') {
                    $feed->setHtmlUrl($url);
                } else {
                    $feed->setXmlUrl($url);
                }

                $category->addFeed($feed);
            }

            $feedList->addItem($category);
        }

        $importer = new Importer();

        return Response(
            $importer->export($feedList),
            200,
            ['Content-Type' => 'text/xml; charset=utf-8']
        );
    }
}
