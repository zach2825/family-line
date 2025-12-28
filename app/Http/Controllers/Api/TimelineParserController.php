<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AI\TimelineParserInterface;
use App\Http\Controllers\Controller;
use App\Models\TimelineEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TimelineParserController extends Controller
{
    // Cache TTL in seconds (1 hour)
    private const CACHE_TTL = 3600;

    public function __construct(
        protected TimelineParserInterface $parser
    ) {}

    /**
     * Parse a natural language description into timeline entry data.
     */
    public function parse(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $text = $request->input('text');
        $cacheKey = 'timeline_parse:' . md5($text);

        try {
            $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($text) {
                return $this->parser->parse($text);
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'cached' => Cache::has($cacheKey),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to parse text',
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get follow-up question for missing fields.
     */
    public function followUp(Request $request): JsonResponse
    {
        $request->validate([
            'current_data' => 'required|array',
            'missing_fields' => 'required|array',
        ]);

        $currentData = $request->input('current_data');
        $missingFields = $request->input('missing_fields');
        $cacheKey = 'timeline_followup:' . md5(json_encode($currentData) . json_encode($missingFields));

        try {
            $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($currentData, $missingFields) {
                return $this->parser->askFollowUp($currentData, $missingFields);
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'cached' => Cache::has($cacheKey),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate follow-up',
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Check for backfill suggestions based on the entry being created.
     * For example, when creating a birthday celebration, suggest creating the birth record.
     */
    public function checkBackfill(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string',
            'people_involved' => 'nullable|array',
            'event_date' => 'nullable|date',
            'title' => 'nullable|string',
        ]);

        $suggestions = [];
        $eventType = $request->input('event_type');
        $people = $request->input('people_involved', []);
        $eventDate = $request->input('event_date');
        $title = $request->input('title', '');
        $teamId = $request->user()->currentTeam?->id;

        // Check if this is a birthday/anniversary celebration
        $isBirthdayCelebration = $this->isBirthdayCelebration($eventType, $title);
        $isAnniversaryCelebration = $this->isAnniversaryCelebration($eventType, $title);

        if ($isBirthdayCelebration && !empty($people)) {
            foreach ($people as $person) {
                // Check if birth record exists for this person
                $birthExists = TimelineEntry::where('team_id', $teamId)
                    ->where('event_type', 'birth')
                    ->where(function ($q) use ($person) {
                        $q->where('title', 'like', "%{$person}%")
                            ->orWhereJsonContains('people_involved', $person);
                    })
                    ->exists();

                if (!$birthExists) {
                    // Calculate birth date from celebration
                    $birthDate = $this->calculateBirthDateFromCelebration($title, $eventDate);

                    $suggestions[] = [
                        'type' => 'birth',
                        'person' => $person,
                        'suggested_date' => $birthDate,
                        'message' => "Create birth record for {$person}",
                        'description' => $birthDate
                            ? "Birth date: {$birthDate} (calculated from celebration)"
                            : "Birth date will need to be set manually",
                        'prefill' => [
                            'title' => "{$person}'s Birth",
                            'event_type' => 'birth',
                            'event_date' => $birthDate,
                            'people_involved' => [$person],
                        ],
                    ];
                }
            }
        }

        if ($isAnniversaryCelebration && !empty($people) && count($people) >= 2) {
            // Check if marriage record exists
            $marriageExists = TimelineEntry::where('team_id', $teamId)
                ->where('event_type', 'marriage')
                ->where(function ($q) use ($people) {
                    foreach ($people as $person) {
                        $q->orWhere('title', 'like', "%{$person}%")
                            ->orWhereJsonContains('people_involved', $person);
                    }
                })
                ->exists();

            if (!$marriageExists) {
                $marriageDate = $this->calculateMarriageDateFromAnniversary($title, $eventDate);
                $coupleNames = implode(' & ', array_slice($people, 0, 2));

                $suggestions[] = [
                    'type' => 'marriage',
                    'people' => array_slice($people, 0, 2),
                    'suggested_date' => $marriageDate,
                    'message' => "Create marriage record for {$coupleNames}",
                    'description' => $marriageDate
                        ? "Wedding date: {$marriageDate} (calculated from anniversary)"
                        : "Wedding date will need to be set manually",
                    'prefill' => [
                        'title' => "{$coupleNames}'s Wedding",
                        'event_type' => 'marriage',
                        'event_date' => $marriageDate,
                        'people_involved' => array_slice($people, 0, 2),
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    private function isBirthdayCelebration(string $eventType, string $title): bool
    {
        $title = strtolower($title);
        $birthdayKeywords = ['birthday', 'bday', 'b-day', 'birth day', 'turned'];

        if (in_array($eventType, ['milestone', 'story', 'memory'])) {
            foreach ($birthdayKeywords as $keyword) {
                if (str_contains($title, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isAnniversaryCelebration(string $eventType, string $title): bool
    {
        $title = strtolower($title);
        $anniversaryKeywords = ['anniversary', 'years married', 'wedding anniversary'];

        if (in_array($eventType, ['milestone', 'story', 'memory'])) {
            foreach ($anniversaryKeywords as $keyword) {
                if (str_contains($title, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function calculateBirthDateFromCelebration(string $title, ?string $eventDate): ?string
    {
        if (!$eventDate) {
            return null;
        }

        $title = strtolower($title);

        // Look for age patterns like "50th birthday", "turned 30"
        if (preg_match('/(\d+)(?:st|nd|rd|th)?\s*(?:birthday|bday)/i', $title, $matches)) {
            $age = (int) $matches[1];
            return date('Y-m-d', strtotime("{$eventDate} - {$age} years"));
        }

        if (preg_match('/turned\s*(\d+)/i', $title, $matches)) {
            $age = (int) $matches[1];
            return date('Y-m-d', strtotime("{$eventDate} - {$age} years"));
        }

        return null;
    }

    private function calculateMarriageDateFromAnniversary(string $title, ?string $eventDate): ?string
    {
        if (!$eventDate) {
            return null;
        }

        $title = strtolower($title);

        // Look for year patterns like "25th anniversary", "10 years married"
        if (preg_match('/(\d+)(?:st|nd|rd|th)?\s*(?:anniversary|years?\s*married)/i', $title, $matches)) {
            $years = (int) $matches[1];
            return date('Y-m-d', strtotime("{$eventDate} - {$years} years"));
        }

        return null;
    }
}
