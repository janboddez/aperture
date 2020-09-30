<?php

namespace App\Events;

use App\Channel;
use App\User;

class UserCreated
{
    public function __construct(User $user)
    {
        $channel = new Channel();
        $channel->user_id = $user->id;
        $channel->uid = 'notifications';
        $channel->name = 'Notifications';
        $channel->sort = 0;
        $channel->save();

        $channel = new Channel();
        $channel->user_id = $user->id;
        $channel->uid = str_random(24);
        $channel->name = 'Home';
        $channel->sort = 1;
        $channel->save();
    }
}
