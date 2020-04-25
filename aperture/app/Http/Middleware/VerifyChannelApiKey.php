<?php

namespace App\Http\Middleware;

use App\Source;
use App\User;
use Auth;
use Cache;
use Closure;
use Request;
use Response;

class VerifyChannelApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Check the given access token
        $authorization = $request->header('Authorization');

        if ($authorization) {
            if (! preg_match('/Bearer (.+)/', $authorization, $match)) {
                return Response::json(['error' => 'unauthorized'], 401);
            }
            $token = $match[1];
        } else {
            $token = Request::post('access_token');
        }

        if (! $token) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        // Check the cache
        if ($cache_data = Cache::get('token:'.$token)) {
            $token_data = json_decode($cache_data, true);
            $user = User::where('id', $token_data['user_id'])->first();
        } else {
            // Check the local token database
            $source = Source::where('token', $token)->first();
            if ($source) {
                $token_data = [
                    'type' => 'source',
                    'source_id' => $source->id,
                    'user_id' => $source->created_by,
                ];

                $user = User::where('id', $source->created_by)->first();
                if (! $user) {
                    return Response::json(['error' => 'not_found'], 404);
                }

                // If this source is in only one channel, add the channel_id to the token data too
                if (1 == $source->channels()->count()) {
                    $token_data['channel_id'] = $source->channels()->first()->id;
                }
            } else {
                return Response::json(['error' => 'forbidden'], 403);
            }

            Cache::set('token:'.$token, json_encode($token_data), 300);
        }

        $request->attributes->set('token_data', $token_data);

        // Activate the login for this user for the request
        Auth::login($user);

        return $next($request);
    }
}
