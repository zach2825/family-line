<?php

namespace App\Services\AI;

use App\Contracts\AI\TimelineParserInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeTimelineParser implements TimelineParserInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-3-haiku-20240307');
    }

    public function parse(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Anthropic API key not configured');
        }

        $systemPrompt = <<<PROMPT
You are a helpful assistant that parses natural language descriptions of family events into structured data.

Extract the following information from the user's input:
- title: A clear, concise title for the event
- event_type: One of: birth, death, marriage, milestone, story, photo, document, memory, tradition (use "story" as default if uncertain)
- event_date: The date of the event in YYYY-MM-DD format. Use today's date if "today" is mentioned. If a birthday with age is mentioned (e.g., "50th birthday"), calculate the birth date from today's date minus the age.
- event_end_date: End date if it's a range (YYYY-MM-DD format), otherwise null
- location: Where the event took place, if mentioned
- content: Any additional story or description details
- people_involved: An array of people mentioned (names or relationships like "Mom", "Grandpa", etc.). Include people whose names appear with possessive forms (e.g., "Karla's house" should include "Karla")
- missing_fields: An array of field names that you couldn't determine and should ask about
- confidence: A number from 0 to 1 indicating how confident you are in the parsed data

Today's date is: {$this->getTodayDate()}

Respond with ONLY valid JSON, no other text.
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $text],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to parse with Claude API');
        }

        $content = $response->json('content.0.text', '{}');

        try {
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error('Failed to parse Claude response as JSON', [
                'content' => $content,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Invalid response from Claude API');
        }

        return $this->normalizeResponse($parsed);
    }

    public function askFollowUp(array $currentData, array $missingFields): array
    {
        if (empty($this->apiKey)) {
            return $this->getDefaultFollowUp($missingFields);
        }

        $context = json_encode($currentData);
        $missing = implode(', ', $missingFields);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 256,
            'system' => 'You help users complete timeline entries. Generate a friendly follow-up question to get missing information. Respond with JSON: {"question": "...", "field": "...", "suggestions": [...]}',
            'messages' => [
                ['role' => 'user', 'content' => "Current data: {$context}\nMissing fields: {$missing}\nGenerate a follow-up question for the most important missing field."],
            ],
        ]);

        if (!$response->successful()) {
            return $this->getDefaultFollowUp($missingFields);
        }

        try {
            return json_decode($response->json('content.0.text', '{}'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->getDefaultFollowUp($missingFields);
        }
    }

    protected function normalizeResponse(array $parsed): array
    {
        return [
            'title' => $parsed['title'] ?? '',
            'event_type' => $this->normalizeEventType($parsed['event_type'] ?? 'other'),
            'event_date' => $parsed['event_date'] ?? null,
            'event_end_date' => $parsed['event_end_date'] ?? null,
            'location' => $parsed['location'] ?? null,
            'content' => $parsed['content'] ?? null,
            'people_involved' => $parsed['people_involved'] ?? [],
            'missing_fields' => $parsed['missing_fields'] ?? [],
            'confidence' => (float) ($parsed['confidence'] ?? 0.5),
        ];
    }

    protected function normalizeEventType(string $type): string
    {
        // Valid types must match TimelineEntry::EVENT_TYPES keys
        $valid = ['birth', 'death', 'marriage', 'milestone', 'story', 'photo', 'document', 'memory', 'tradition'];
        $type = strtolower(trim($type));

        // Return the type if valid, otherwise default to 'story'
        return in_array($type, $valid) ? $type : 'story';
    }

    protected function getTodayDate(): string
    {
        return now()->format('Y-m-d');
    }

    protected function getDefaultFollowUp(array $missingFields): array
    {
        $field = $missingFields[0] ?? 'event_date';

        $questions = [
            'event_date' => 'When did this event happen?',
            'location' => 'Where did this take place?',
            'people_involved' => 'Who was involved in this event?',
            'content' => 'Can you tell me more about what happened?',
        ];

        return [
            'question' => $questions[$field] ?? "Can you provide more details about the {$field}?",
            'field' => $field,
            'suggestions' => [],
        ];
    }
}
