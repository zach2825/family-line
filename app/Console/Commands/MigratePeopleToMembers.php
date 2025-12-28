<?php

namespace App\Console\Commands;

use App\Models\FamilyMember;
use App\Models\TimelineEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePeopleToMembers extends Command
{
    protected $signature = 'family:migrate-people
                            {--dry-run : Show what would be migrated without making changes}';

    protected $description = 'Migrate text-based people_involved to FamilyMember records';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        $this->info('Scanning timeline entries for people to migrate...');

        // Get all entries with people_involved
        $entries = TimelineEntry::whereNotNull('people_involved')
            ->where('people_involved', '!=', '[]')
            ->with('team')
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No entries with people_involved found.');
            return 0;
        }

        $this->info("Found {$entries->count()} entries with people.");

        // Collect unique names per team
        $teamPeople = [];
        foreach ($entries as $entry) {
            $people = $entry->people_involved;
            if (!is_array($people)) {
                continue;
            }

            $teamId = $entry->team_id;
            if (!isset($teamPeople[$teamId])) {
                $teamPeople[$teamId] = [
                    'names' => [],
                    'entries' => [],
                ];
            }

            foreach ($people as $name) {
                $name = trim($name);
                if (empty($name)) continue;

                if (!isset($teamPeople[$teamId]['names'][$name])) {
                    $teamPeople[$teamId]['names'][$name] = [];
                }
                $teamPeople[$teamId]['names'][$name][] = $entry->id;
            }
        }

        $totalMembers = 0;
        $totalLinks = 0;

        foreach ($teamPeople as $teamId => $data) {
            $this->newLine();
            $this->info("Processing Team ID: {$teamId}");
            $this->info("  Found " . count($data['names']) . " unique names");

            foreach ($data['names'] as $name => $entryIds) {
                // Check if member already exists
                $existingMember = FamilyMember::forTeam($teamId)
                    ->where(function ($q) use ($name) {
                        $q->where('nickname', $name)
                            ->orWhere('first_name', $name)
                            ->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) = ?", [$name]);
                    })
                    ->first();

                if ($existingMember) {
                    $this->line("  - '{$name}' already exists as member #{$existingMember->id}");
                    $member = $existingMember;
                } else {
                    $this->line("  + Creating member for '{$name}'");
                    $totalMembers++;

                    if (!$isDryRun) {
                        // Parse the name
                        $nameParts = explode(' ', $name, 2);
                        $firstName = $nameParts[0];
                        $lastName = $nameParts[1] ?? null;

                        // Get a user from the team to set as creator
                        $team = \App\Models\Team::find($teamId);
                        $creatorId = $team?->owner?->id ?? 1;

                        $member = FamilyMember::create([
                            'team_id' => $teamId,
                            'created_by' => $creatorId,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'nickname' => $name,
                            'is_living' => true,
                        ]);
                    } else {
                        $member = null;
                    }
                }

                // Link to timeline entries
                foreach ($entryIds as $entryId) {
                    if (!$isDryRun && $member) {
                        // Check if link already exists
                        $exists = DB::table('family_member_timeline_entry')
                            ->where('family_member_id', $member->id)
                            ->where('timeline_entry_id', $entryId)
                            ->exists();

                        if (!$exists) {
                            DB::table('family_member_timeline_entry')->insert([
                                'family_member_id' => $member->id,
                                'timeline_entry_id' => $entryId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $totalLinks++;
                        }
                    } else {
                        $totalLinks++;
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Migration Summary:');
        $this->info("  - Members created: {$totalMembers}");
        $this->info("  - Entry links created: {$totalLinks}");

        if ($isDryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            $this->info('Migration completed successfully!');
        }

        return 0;
    }
}
