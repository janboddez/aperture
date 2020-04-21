<?php

use App\Channel;
use App\User;
use Illuminate\Database\Migrations\Migration;

class FixNotificationsChannelOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Move the notifications channel to the top of the list
        $users = User::all();
        foreach ($users as $user) {
            $channels = Channel::where('user_id', $user->id)
                ->orderByDesc(DB::raw('uid = "notifications"'))
                ->orderBy('sort')
                ->get();

            foreach ($channels as $i => $channel) {
                $channel->sort = $i;
                $channel->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
