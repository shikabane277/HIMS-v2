<?php

namespace App\Http\Controllers;

use App\Contracts\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiController extends Controller
{
    public function __construct(private AiProvider $ai) {}

    /** Load last 50 messages for the current user (called by the bubble on open) */
    public function history(Request $request)
    {
        $messages = DB::table('ai_chat_messages')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get(['role', 'message', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    /** Handle a new query — save both sides, return AI reply */
    public function query(Request $request)
    {
        $request->validate(['query' => 'required|string|max:1000']);

        $prompt = $request->input('query');
        $userId = auth()->id();
        $now    = now();

        // Save user message
        DB::table('ai_chat_messages')->insert([
            'id'         => Str::uuid(),
            'user_id'    => $userId,
            'role'       => 'user',
            'message'    => $prompt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Get AI response
        $response = $this->ai->ask($prompt);

        // Save AI reply
        DB::table('ai_chat_messages')->insert([
            'id'         => Str::uuid(),
            'user_id'    => $userId,
            'role'       => 'ai',
            'message'    => $response,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // AJAX / fetch — return JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['response' => $response]);
        }

        // Normal form POST fallback
        return back()->with('ai_response', $response);
    }

    /** Clear the current user's chat history */
    public function clearHistory(Request $request)
    {
        DB::table('ai_chat_messages')->where('user_id', auth()->id())->delete();
        return response()->json(['ok' => true]);
    }
}
