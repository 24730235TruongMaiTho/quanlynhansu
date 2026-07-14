<?php

namespace App\Http\Controllers\Frontend;

use Illuminate\Routing\Controller;

class HomeController extends Controller
{
    public function index()
    {
        return view('frontend.home');
    }
}
