<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private GeminiService $gemini) {}

    public function query(Request $request)
    {
        $request->validate(['query' => 'required|string|max:500']);

        $response = $this->gemini->ask($request->query);

        return back()->with('ai_response', $response);
    }
}
