<?php

namespace App\Services\AI;

use App\Contracts\AI\TimelineParserInterface;

/**
 * A local parser that doesn't require any AI API.
 * Uses pattern matching and heuristics to parse timeline entries.
 * This serves as a fallback when no AI service is configured.
 */
class LocalTimelineParser implements TimelineParserInterface
{
    protected array $familyKeywords = [
        'mom' => 'Mom',
        'mother' => 'Mom',
        'mama' => 'Mom',
        'mum' => 'Mom',
        'dad' => 'Dad',
        'father' => 'Dad',
        'papa' => 'Dad',
        'grandma' => 'Grandma',
        'grandmother' => 'Grandma',
        'granny' => 'Grandma',
        'nana' => 'Grandma',
        'grandpa' => 'Grandpa',
        'grandfather' => 'Grandpa',
        'gramps' => 'Grandpa',
        'brother' => 'Brother',
        'bro' => 'Brother',
        'sister' => 'Sister',
        'sis' => 'Sister',
        'aunt' => 'Aunt',
        'auntie' => 'Aunt',
        'uncle' => 'Uncle',
        'cousin' => 'Cousin',
        'nephew' => 'Nephew',
        'niece' => 'Niece',
        'son' => 'Son',
        'daughter' => 'Daughter',
        'husband' => 'Husband',
        'wife' => 'Wife',
        'spouse' => 'Spouse',
        'baby' => 'Baby',
    ];

    protected array $eventTypeKeywords = [
        'birth' => ['birth', 'born', 'baby', 'newborn', 'arrived', 'delivered'],
        'death' => ['death', 'died', 'passed', 'funeral', 'memorial', 'rip', 'rest in peace', 'passing'],
        'marriage' => ['marriage', 'married', 'wedding', 'engaged', 'engagement', 'wed'],
        'milestone' => ['milestone', 'graduation', 'graduated', 'first', 'achievement', 'promotion', 'retired', 'birthday', 'bday', 'anniversary', 'turning'],
        'story' => ['story', 'remember', 'memory', 'tale', 'told'],
        'photo' => ['photo', 'picture', 'photograph', 'snapshot'],
    ];

    public function parse(string $text): array
    {
        $lowerText = strtolower($text);

        return [
            'title' => $this->generateTitle($text),
            'event_type' => $this->detectEventType($lowerText),
            'event_date' => $this->parseDate($lowerText),
            'event_end_date' => null,
            'location' => $this->parseLocation($text),
            'content' => null,
            'people_involved' => $this->extractPeople($text),
            'missing_fields' => $this->determineMissingFields($text),
            'confidence' => $this->calculateConfidence($text),
        ];
    }

    public function askFollowUp(array $currentData, array $missingFields): array
    {
        $field = $missingFields[0] ?? 'event_date';

        $questions = [
            'event_date' => 'When did this event happen?',
            'location' => 'Where did this take place?',
            'people_involved' => 'Who was involved in this event?',
            'content' => 'Can you tell me more about what happened?',
            'title' => 'What would you like to call this event?',
        ];

        $suggestions = $this->getSuggestions($field, $currentData);

        return [
            'question' => $questions[$field] ?? "Can you provide more details about the {$field}?",
            'field' => $field,
            'suggestions' => $suggestions,
        ];
    }

    protected function generateTitle(string $text): string
    {
        // If text is short enough, use it as title
        if (strlen($text) <= 100) {
            return ucfirst(trim($text));
        }

        // Otherwise, take first sentence or first 100 chars
        $firstSentence = preg_split('/[.!?]/', $text, 2)[0] ?? $text;

        if (strlen($firstSentence) <= 100) {
            return ucfirst(trim($firstSentence));
        }

        return ucfirst(trim(substr($text, 0, 97))) . '...';
    }

    protected function detectEventType(string $text): string
    {
        foreach ($this->eventTypeKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $type;
                }
            }
        }

        return 'story';
    }

    protected function parseDate(string $text): ?string
    {
        // Relative dates: today, yesterday, last week
        if (preg_match('/\btoday\b/i', $text)) {
            return now()->format('Y-m-d');
        }
        if (preg_match('/\byesterday\b/i', $text)) {
            return now()->subDay()->format('Y-m-d');
        }
        if (preg_match('/\blast\s+week\b/i', $text)) {
            return now()->subWeek()->format('Y-m-d');
        }
        if (preg_match('/\blast\s+month\b/i', $text)) {
            return now()->subMonth()->format('Y-m-d');
        }
        if (preg_match('/\blast\s+year\b/i', $text)) {
            return now()->subYear()->format('Y-m-d');
        }

        // Pattern: Xth birthday, Xst anniversary, etc.
        if (preg_match('/(\d+)(?:st|nd|rd|th)\s*(?:birthday|bday|anniversary)/', $text, $matches)) {
            $years = (int) $matches[1];
            if ($years > 0 && $years < 150) {
                return now()->subYears($years)->format('Y-m-d');
            }
        }

        // Pattern: turning X
        if (preg_match('/turning\s*(\d+)/', $text, $matches)) {
            $years = (int) $matches[1];
            if ($years > 0 && $years < 150) {
                return now()->subYears($years)->format('Y-m-d');
            }
        }

        // Pattern: YYYY-MM-DD or MM/DD/YYYY
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $text, $matches)) {
            return $matches[0];
        }

        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
        }

        // Pattern: Month Day, Year
        $months = 'january|february|march|april|may|june|july|august|september|october|november|december';
        if (preg_match("/($months)\s+(\d{1,2})(?:st|nd|rd|th)?,?\s*(\d{4})/i", $text, $matches)) {
            $month = date('m', strtotime($matches[1]));
            return sprintf('%04d-%02d-%02d', $matches[3], $month, $matches[2]);
        }

        return null;
    }

    protected function parseLocation(string $text): ?string
    {
        // Look for common location patterns
        // "in Chicago", "at the hospital", "in New York, NY"
        if (preg_match('/(?:in|at)\s+([A-Z][a-zA-Z\s,]+)(?:\.|,|$)/', $text, $matches)) {
            $location = trim($matches[1], ', ');
            if (strlen($location) > 2 && strlen($location) < 100) {
                return $location;
            }
        }

        return null;
    }

    protected function extractPeople(string $text): array
    {
        $lowerText = strtolower($text);
        $people = [];

        // Check for family keywords
        foreach ($this->familyKeywords as $keyword => $name) {
            if (preg_match("/\b{$keyword}(?:'s|s)?\b/i", $lowerText)) {
                $people[] = $name;
            }
        }

        // Extract capitalized names (proper nouns)
        $words = preg_split('/\s+/', $text);
        $skipWords = ['The', 'And', 'For', 'With', 'Our', 'Their', 'His', 'Her', 'Day', 'Party', 'Birthday', 'Wedding', 'Anniversary', 'First', 'Last', 'In', 'At', 'On', 'Had', 'Was', 'Were', 'Today', 'Yesterday', 'BBQ', 'House'];

        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^a-zA-Z]/', '', $word);
            // Handle possessives: "Karla's" or "Karlas" -> "Karla"
            if (preg_match("/^([A-Z][a-z]+)(?:'?s)?$/", $cleanWord, $matches)) {
                $name = $matches[1];
                if (strlen($name) > 2 && !in_array($name, $skipWords)) {
                    $people[] = $name;
                }
            }
        }

        return array_values(array_unique($people));
    }

    protected function determineMissingFields(string $text): array
    {
        $missing = [];
        $lowerText = strtolower($text);

        if (!$this->parseDate($lowerText)) {
            $missing[] = 'event_date';
        }

        if (!$this->parseLocation($text)) {
            $missing[] = 'location';
        }

        if (empty($this->extractPeople($text))) {
            $missing[] = 'people_involved';
        }

        return $missing;
    }

    protected function calculateConfidence(string $text): float
    {
        $score = 0.5; // Base score

        $lowerText = strtolower($text);

        // Boost for detected event type
        if ($this->detectEventType($lowerText) !== 'story') {
            $score += 0.1;
        }

        // Boost for parsed date
        if ($this->parseDate($lowerText)) {
            $score += 0.15;
        }

        // Boost for detected people
        if (count($this->extractPeople($text)) > 0) {
            $score += 0.1;
        }

        // Boost for location
        if ($this->parseLocation($text)) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    protected function getSuggestions(string $field, array $currentData): array
    {
        return match ($field) {
            'event_type' => ['birth', 'marriage', 'milestone', 'story'],
            'event_date' => ['today', 'yesterday', 'last week'],
            default => [],
        };
    }
}
