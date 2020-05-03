<?php

namespace App\Http\Controllers;

class PublicController extends Controller
{
    public function docs()
    {
        return view('docs');
    }
}
