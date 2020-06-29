<?php

namespace App\Http\Controllers;

use Auth;
use Request;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $channels = Auth::user()->channels()
            ->orderBy('sort')
            ->get();

        return view('settings', [
            'demo_mode_enabled' => Auth::user()->demo_mode_enabled,
        ]);
    }

    public function save(Request $request)
    {
        $user = Auth::user();

        if ($request->has('demo_mode_enabled')) {
            $user->demo_mode_enabled = 1;
        } else {
            $user->demo_mode_enabled = 0;
        }

        if ($request->has('opml_endpoint_enabled')) {
            $user->opml_endpoint_enabled = 1;
        } else {
            $user->opml_endpoint_enabled = 0;
        }

        if ($request->has('fetch_original_enabled')) {
            $user->fetch_original_enabled = 1;
        } else {
            $user->fetch_original_enabled = 0;
        }

        $user->save();

        session()->flash('settings', 'Settings were saved');

        return redirect('settings');
    }

    public function reload_micropub_config()
    {
        if (session('access_token')) {
            $user = Auth::user();
            $user->reload_micropub_config(session('access_token'));
            $user->save();
        }

        return response()->json(['json' => $user->micropub_config]);
    }
}
