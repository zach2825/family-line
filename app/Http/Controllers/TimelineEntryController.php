<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\TimelineEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TimelineEntryController extends Controller
{
    public function index(Request $request)
    {
        $entries = TimelineEntry::visibleTo($request->user())
            ->with('user:id,name')
            ->published()
            ->orderedByDate()
            ->paginate(20);

        return Inertia::render('Timeline/Index', [
            'entries' => $entries,
            'eventTypes' => TimelineEntry::EVENT_TYPES,
        ]);
    }

    public function create(Request $request)
    {
        $team = $request->user()->currentTeam;

        $familyMembers = FamilyMember::forTeam($team->id)
            ->select('id', 'first_name', 'last_name', 'nickname', 'photo_path')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('Timeline/Create', [
            'eventTypes' => TimelineEntry::EVENT_TYPES,
            'visibilityOptions' => TimelineEntry::VISIBILITY_OPTIONS,
            'hasApiKey' => !empty(config('services.anthropic.api_key')),
            'familyMembers' => $familyMembers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'event_date' => 'nullable|date',
            'event_end_date' => 'nullable|date|after_or_equal:event_date',
            'event_type' => 'required|string|in:' . implode(',', array_keys(TimelineEntry::EVENT_TYPES)),
            'location' => 'nullable|string|max:255',
            'people_involved' => 'nullable|array',
            'people_involved.*' => 'string|max:255',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:family_members,id',
            'family_surname' => 'nullable|string|max:255',
            'visibility' => 'required|in:immediate_family,extended_family,private',
            'is_published' => 'boolean',
            'backfill_entries' => 'nullable|array',
            'backfill_entries.*.title' => 'required|string|max:255',
            'backfill_entries.*.event_type' => 'required|string|in:' . implode(',', array_keys(TimelineEntry::EVENT_TYPES)),
            'backfill_entries.*.event_date' => 'nullable|date',
            'backfill_entries.*.people_involved' => 'nullable|array',
            'backfill_entries.*.member_ids' => 'nullable|array',
        ]);

        // Extract backfill entries before creating main entry
        $backfillEntries = $validated['backfill_entries'] ?? [];
        $memberIds = $validated['member_ids'] ?? [];
        unset($validated['backfill_entries'], $validated['member_ids']);

        $entry = TimelineEntry::create([
            'team_id' => $request->user()->currentTeam->id,
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        // Link family members - prefer direct member IDs, fall back to name matching
        if (!empty($memberIds)) {
            $entry->familyMembers()->sync($memberIds);
        } else {
            $this->linkFamilyMembers($entry, $validated['people_involved'] ?? []);
        }

        // Create backfill entries
        $createdBackfills = [];
        foreach ($backfillEntries as $backfill) {
            $backfillEntry = TimelineEntry::create([
                'team_id' => $request->user()->currentTeam->id,
                'user_id' => $request->user()->id,
                'title' => $backfill['title'],
                'event_type' => $backfill['event_type'],
                'event_date' => $backfill['event_date'] ?? null,
                'people_involved' => $backfill['people_involved'] ?? [],
                'visibility' => $validated['visibility'],
                'is_published' => $validated['is_published'],
            ]);
            if (!empty($backfill['member_ids'])) {
                $backfillEntry->familyMembers()->sync($backfill['member_ids']);
            } else {
                $this->linkFamilyMembers($backfillEntry, $backfill['people_involved'] ?? []);
            }
            $createdBackfills[] = $backfillEntry;
        }

        $message = 'Timeline entry created successfully.';
        if (count($createdBackfills) > 0) {
            $message .= ' Also created ' . count($createdBackfills) . ' related ' . (count($createdBackfills) === 1 ? 'record' : 'records') . '.';
        }

        // If creating another, redirect back to create page
        if ($request->boolean('create_another')) {
            return redirect()->route('timeline.create')
                ->with('message', $message);
        }

        return redirect()->route('timeline.show', $entry)
            ->with('message', $message);
    }

    public function show(TimelineEntry $entry)
    {
        Gate::authorize('view', $entry);

        $entry->load([
            'user:id,name',
            'familyMembers:id,first_name,last_name,nickname,photo_path',
        ]);

        return Inertia::render('Timeline/Show', [
            'entry' => $entry,
            'eventTypes' => TimelineEntry::EVENT_TYPES,
        ]);
    }

    public function edit(Request $request, TimelineEntry $entry)
    {
        Gate::authorize('update', $entry);

        $team = $request->user()->currentTeam;

        $familyMembers = FamilyMember::forTeam($team->id)
            ->select('id', 'first_name', 'last_name', 'nickname', 'photo_path')
            ->orderBy('first_name')
            ->get();

        // Load the entry's linked family members
        $entry->load('familyMembers:id,first_name,last_name,nickname,photo_path');

        return Inertia::render('Timeline/Edit', [
            'entry' => $entry,
            'eventTypes' => TimelineEntry::EVENT_TYPES,
            'visibilityOptions' => TimelineEntry::VISIBILITY_OPTIONS,
            'familyMembers' => $familyMembers,
        ]);
    }

    public function update(Request $request, TimelineEntry $entry)
    {
        Gate::authorize('update', $entry);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'event_date' => 'nullable|date',
            'event_end_date' => 'nullable|date|after_or_equal:event_date',
            'event_type' => 'required|string|in:' . implode(',', array_keys(TimelineEntry::EVENT_TYPES)),
            'location' => 'nullable|string|max:255',
            'people_involved' => 'nullable|array',
            'people_involved.*' => 'string|max:255',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:family_members,id',
            'family_surname' => 'nullable|string|max:255',
            'visibility' => 'required|in:immediate_family,extended_family,private',
            'is_published' => 'boolean',
        ]);

        $memberIds = $validated['member_ids'] ?? [];
        unset($validated['member_ids']);

        $entry->update($validated);

        // Link family members - prefer direct member IDs, fall back to name matching
        if (!empty($memberIds)) {
            $entry->familyMembers()->sync($memberIds);
        } else {
            $this->linkFamilyMembers($entry, $validated['people_involved'] ?? []);
        }

        return redirect()->route('timeline.show', $entry)
            ->with('message', 'Timeline entry updated successfully.');
    }

    /**
     * Auto-link family members based on people_involved text names.
     */
    private function linkFamilyMembers(TimelineEntry $entry, array $peopleNames): void
    {
        $memberIds = [];

        foreach ($peopleNames as $name) {
            $member = FamilyMember::findByNameOrNickname($entry->team_id, $name);
            if ($member) {
                $memberIds[] = $member->id;
            }
        }

        if (!empty($memberIds)) {
            $entry->familyMembers()->sync($memberIds);
        }
    }

    public function destroy(TimelineEntry $entry)
    {
        Gate::authorize('delete', $entry);

        $entry->delete();

        return redirect()->route('timeline.index')
            ->with('message', 'Timeline entry deleted successfully.');
    }
}
