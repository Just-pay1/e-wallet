<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IpController extends Controller
{
    public function store(Request $request)
{
    $ip = $request->input('ip');

    // Example: save to database or log
    
    return response()->json(['message' => 'IP stored successfully']);
}

}
