<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.gemini.api_key', env('GEMINI_API_KEY',''));
        $this->endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    }

    public function ask(string $prompt): string
    {
        if (empty($this->apiKey)) {
            return '⚠️ Gemini API key not configured. Please add GEMINI_API_KEY to your .env file.';
        }

        $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-pro'];

        foreach ($models as $model) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
                $systemContext = "You are an AI assistant for a Hospital Information Management System (HIMS) "
                    . "in the Philippines. You help HR officers with performance reviews, competency gaps, "
                    . "succession planning, training schedules, learning pathways, and employee recognition. "
                    . "You understand both English and Tagalog/Taglish. Be concise and helpful. "
                    . "Always be professional and sensitive to healthcare context.";

                $response = Http::timeout(30)->post("{$endpoint}?key={$this->apiKey}", [
                    'contents' => [
                        ['role' => 'user', 'parts' => [
                            ['text' => $systemContext . "\n\nUser question: " . $prompt]
                        ]]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 1024,
                    ],
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['candidates'][0]['content']['parts'][0]['text']
                        ?? 'No response generated.';
                }

                if ($response->status() === 404) {
                    continue; // try next model
                }

                $errData = $response->json();
                $msg = $errData['error']['message'] ?? $response->body();
                Log::warning('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
                return "⚠️ Gemini API Error ({$response->status()}): {$msg}";

            } catch (\Exception $e) {
                Log::error('Gemini request failed', ['error' => $e->getMessage()]);
                return '⚠️ AI service error: ' . $e->getMessage();
            }
        }

        return '⚠️ Unable to connect to Gemini API. Please check your GEMINI_API_KEY in .env.';
    }

    /**
     * Check performance review text for potential bias
     */
    public function checkBias(string $reviewText): array
    {
        $prompt = "Analyze this performance review text for potential unconscious bias (gender, age, nationality, etc). "
            . "Return a JSON object with: {has_bias: bool, flags: [{type, excerpt, suggestion}], confidence: 0-1}. "
            . "Review text: " . $reviewText;

        $response = $this->ask($prompt);

        try {
            $json = json_decode(preg_replace('/```json|```/', '', $response), true);
            return $json ?? ['has_bias' => false, 'flags' => []];
        } catch (\Exception) {
            return ['has_bias' => false, 'flags' => []];
        }
    }

    /**
     * Generate quiz questions from course module content
     */
    public function generateQuizQuestions(string $content, int $count = 5): array
    {
        $prompt = "Generate {$count} multiple-choice quiz questions based on this content. "
            . "Return JSON array: [{question_text, options: [4 strings], correct_answer, explanation}]. "
            . "Content: " . Str::limit($content, 2000);

        $response = $this->ask($prompt);

        try {
            $json = json_decode(preg_replace('/```json|```/', '', $response), true);
            return is_array($json) ? $json : [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Analyze training feedback sentiment
     */
    public function analyzeSentiment(string $feedbackText): array
    {
        $prompt = "Analyze the sentiment of this training feedback. "
            . "Return JSON: {label: 'positive'|'neutral'|'negative', score: 0-1, summary: string}. "
            . "Feedback: " . $feedbackText;

        $response = $this->ask($prompt);

        try {
            $json = json_decode(preg_replace('/```json|```/', '', $response), true);
            return $json ?? ['label' => 'neutral', 'score' => 0.5];
        } catch (\Exception) {
            return ['label' => 'neutral', 'score' => 0.5];
        }
    }
}
