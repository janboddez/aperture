<?php

namespace Tests\Feature;

use App\Channel;
use App\Entry;
use App\Source;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PagingTest extends TestCase
{
    use RefreshDatabase;

    private function _microsub($user_id, $params)
    {
        return $this->withHeaders([
      'Authorization' => 'Bearer 1234',
    ])->get('/microsub/'.$user_id.'?'.http_build_query($params));
    }

    public function testPagingWithBatchOrdering()
    {
        $user = factory(User::class)->create();
        $channel = $user->channels()->where('user_id', $user->id)->where('uid', '!=', 'notifications')->first();

        Cache::shouldReceive('get')
      ->with('token:1234')
      ->andReturn('{"scope": ["create", "update", "media", "read"], "client_id": "https://aperture.p3k.io", "me": "https://aaronparecki.com/","user_id": '.$user->id.'}');

        $source = factory(Source::class)->create();

        // Generate 30 entries all with the same created_at date
        $entries = [];
        for ($i = 30; $i >= 1; --$i) {
            $date = '2017-10-01 08:00:00';
            $published = '2017-12-'.sprintf('%02d', $i).' 08:00:00';
            $entries[] = factory(Entry::class)->create([
        'source_id' => $source->id,
        'published' => $published,
        'created_at' => $date,
        'updated_at' => $date,
      ]);
        }

        // Add all the entries to the channel with different batch_orders
        foreach ($entries as $i=>$entry) {
            $channel->entries()->attach($entry->id, ['created_at'=>$entry->created_at, 'batch_order'=>$i]);
        }

        // Retrieve the latest 20 entries
        $response = $this->_microsub($user->id, [
      'action' => 'timeline',
      'channel' => $channel->uid,
      'limit' => 5,
    ]);

        $response->assertStatus(200);

        $data = json_decode($response->content(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(5, $data['items']);

        // Check the timestamps of the first and last item
        $this->assertEquals('2017-12-30T08:00:00+00:00', $data['items'][0]['published']);
        $this->assertEquals('2017-12-26T08:00:00+00:00', $data['items'][4]['published']);

        $this->assertArrayHasKey('before', $data['paging']);
        $this->assertArrayHasKey('after', $data['paging']);

        $before = $data['paging']['before'];
        $after = $data['paging']['after'];

        // Fetch the next page of data

        $response = $this->_microsub($user->id, [
      'action' => 'timeline',
      'channel' => $channel->uid,
      'limit' => 5,
      'after' => $after,
    ]);

        $response->assertStatus(200);
        $data = json_decode($response->content(), true);
        $this->assertArrayHasKey('items', $data);

        // The next 5 items should be returned
        $this->assertCount(5, $data['items']);

        // Check the timestamps of the first and last item
        $this->assertEquals('2017-12-25T08:00:00+00:00', $data['items'][0]['published']);
        $this->assertEquals('2017-12-21T08:00:00+00:00', $data['items'][4]['published']);
    }

    public function testPagingParameters()
    {
        $user = factory(User::class)->create();
        $channel = $user->channels()->where('user_id', $user->id)->where('uid', '!=', 'notifications')->first();

        Cache::shouldReceive('get')
      ->with('token:1234')
      ->andReturn('{"scope": ["create", "update", "media", "read"], "client_id": "https://aperture.p3k.io", "me": "https://aaronparecki.com/","user_id": '.$user->id.'}');

        $source = factory(Source::class)->create();

        // Generate 30 entries all dated different days in October
        $entries = [];
        for ($i = 30; $i >= 1; --$i) {
            $date = '2017-10-'.sprintf('%02d', $i).' 08:00:00';
            $entries[] = factory(Entry::class)->create([
        'source_id' => $source->id,
        'published' => $date,
        'created_at' => $date,
        'updated_at' => $date,
      ]);
        }

        // Add all the entries to the channel
        foreach ($entries as $i=>$entry) {
            $channel->entries()->attach($entry->id, ['created_at'=>$entry->created_at, 'batch_order'=>0]);
        }

        // Retrieve the latest 20 entries
        $response = $this->_microsub($user->id, [
      'action' => 'timeline',
      'channel' => $channel->uid,
      'limit' => 20,
    ]);

        $response->assertStatus(200);

        $data = json_decode($response->content(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(20, $data['items']);

        // Check the timestamps of the first and last item
        $this->assertEquals('2017-10-30T08:00:00+00:00', $data['items'][0]['published']);
        $this->assertEquals('2017-10-11T08:00:00+00:00', $data['items'][19]['published']);

        // Make sure both "before" and "after" cursors are returned
        $this->assertArrayHasKey('before', $data['paging']);
        $this->assertArrayHasKey('after', $data['paging']);

        $before = $data['paging']['before'];
        $after = $data['paging']['after'];

        // Fetch the next page of data
        $response = $this->_microsub($user->id, [
      'action' => 'timeline',
      'channel' => $channel->uid,
      'limit' => 20,
      'after' => $after,
    ]);

        $response->assertStatus(200);
        $data = json_decode($response->content(), true);
        $this->assertArrayHasKey('items', $data);

        // The remaining 10 items should be returned
        $this->assertCount(10, $data['items']);

        // Check the timestamps of the first and last item
        $this->assertEquals('2017-10-10T08:00:00+00:00', $data['items'][0]['published']);
        $this->assertEquals('2017-10-01T08:00:00+00:00', $data['items'][9]['published']);

        // There should not be an "after" page since this is the end of the list
        $this->assertArrayNotHasKey('after', $data['paging']);
        // There should not be a "before" page since it would be the same as the "after" parameter provided in the request
        $this->assertArrayNotHasKey('before', $data['paging']);

        // Poll for new items, there should be none
        $response = $this->_microsub($user->id, [
      'action' => 'timeline',
      'channel' => $channel->uid,
      'limit' => 20,
      'before' => $before,
    ]);
        $response->assertStatus(200);
        $data = json_decode($response->content(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(0, $data['items']);
        $this->assertArrayNotHasKey('after', $data['paging']);
        $this->assertArrayNotHasKey('before', $data['paging']);

        // Add 30 new entries newer than the existing ones

        // Generate 30 entries all dated different days in November
        $entries = [];
        for ($i = 30; $i >= 1; --$i) {
            $date = '2017-11-'.sprintf('%02d', $i).' 07:00:00';
            $entries[] = factory(Entry::class)->create([
        'source_id' => $source->id,
        'published' => $date,
        'created_at' => $date,
        'updated_at' => $date,
      ]);
        }

        // Add all the entries to the channel
        foreach ($entries as $i=>$entry) {
            $channel->entries()->attach($entry->id, ['created_at'=>$entry->created_at, 'batch_order'=>0]);
        }

        // Request newer items in the timeline using the previously stored "before"
        $response = $this->_microsub($user->id, [
      'action' => 'timeline',
      'channel' => $channel->uid,
      'limit' => 20,
      'before' => $before,
    ]);

        $response->assertStatus(200);
        $data = json_decode($response->content(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(20, $data['items']);

        // Check the timestamps of the first and last item
        $this->assertEquals('2017-11-30T07:00:00+00:00', $data['items'][0]['published']);
        $this->assertEquals('2017-11-11T07:00:00+00:00', $data['items'][19]['published']);

        // Make sure both "before" and "after" cursors are returned
        $this->assertArrayHasKey('before', $data['paging']);
        $this->assertArrayHasKey('after', $data['paging']);

        // Use the new "after" and make a request to load the next set
        $after = $data['paging']['after'];

        $response = $this->_microsub($user->id, [
      'action' => 'timeline',
      'channel' => $channel->uid,
      'limit' => 20,
      'before' => $before, // use the same "before" we used previously
      'after' => $after,    // use the new "after"
    ]);

        echo "\nREQUEST FOR NEW PAGE\n";
        echo "\nBEFORE: $before\nAFTER: $after\n\n";

        $response->assertStatus(200);
        $data = json_decode($response->content(), true);

        $this->assertArrayHasKey('items', $data);

        // The remaining 10 items should be returned, none from October
        $this->assertCount(10, $data['items']);
        $this->assertEquals('2017-11-10T07:00:00+00:00', $data['items'][0]['published']);
        $this->assertEquals('2017-11-01T07:00:00+00:00', $data['items'][9]['published']);

        // There should not be a "before" or "after" paging response since no more items can be fetched
        $this->assertArrayNotHasKey('after', $data['paging']);
        $this->assertArrayNotHasKey('before', $data['paging']);
    }
}
