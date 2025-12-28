<?php

namespace Database\Seeders;

use App\Models\FamilyMember;
use App\Models\FamilyRelationship;
use App\Models\Team;
use App\Models\TimelineEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FamilySeeder extends Seeder
{
    private array $firstNames = [
        'male' => ['James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles', 'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Mark', 'Steven', 'Paul', 'Andrew', 'Joshua', 'Kenneth', 'Kevin', 'Brian', 'George', 'Timothy', 'Ronald', 'Edward', 'Jason', 'Jeffrey', 'Ryan', 'Jacob', 'Gary', 'Nicholas', 'Eric', 'Jonathan', 'Stephen', 'Larry', 'Justin', 'Scott', 'Brandon', 'Benjamin', 'Samuel', 'Raymond', 'Gregory', 'Frank', 'Alexander', 'Patrick', 'Jack', 'Dennis', 'Jerry', 'Tyler'],
        'female' => ['Mary', 'Patricia', 'Jennifer', 'Linda', 'Barbara', 'Elizabeth', 'Susan', 'Jessica', 'Sarah', 'Karen', 'Lisa', 'Nancy', 'Betty', 'Margaret', 'Sandra', 'Ashley', 'Kimberly', 'Emily', 'Donna', 'Michelle', 'Dorothy', 'Carol', 'Amanda', 'Melissa', 'Deborah', 'Stephanie', 'Rebecca', 'Sharon', 'Laura', 'Cynthia', 'Kathleen', 'Amy', 'Angela', 'Shirley', 'Anna', 'Brenda', 'Pamela', 'Emma', 'Nicole', 'Helen', 'Samantha', 'Katherine', 'Christine', 'Debra', 'Rachel', 'Carolyn', 'Janet', 'Catherine', 'Maria', 'Heather'],
    ];

    private array $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
        'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
        'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
        'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts',
    ];

    private array $locations = [
        'New York, NY', 'Los Angeles, CA', 'Chicago, IL', 'Houston, TX', 'Phoenix, AZ',
        'Philadelphia, PA', 'San Antonio, TX', 'San Diego, CA', 'Dallas, TX', 'San Jose, CA',
        'Austin, TX', 'Jacksonville, FL', 'Fort Worth, TX', 'Columbus, OH', 'Charlotte, NC',
        'San Francisco, CA', 'Indianapolis, IN', 'Seattle, WA', 'Denver, CO', 'Boston, MA',
    ];

    public function run(): void
    {
        $this->command->info('Creating families with members, relationships, and timeline entries...');

        // First, populate the admin user's team if they exist
        $adminUser = User::where('email', 'admin@admin.com')->first();
        if ($adminUser && $adminUser->currentTeam) {
            $this->command->info('Populating admin team with test data...');
            $this->populateExistingTeam($adminUser, $adminUser->currentTeam, 'Admin');
        }

        // Create 5 different families (teams)
        $familyCount = 5;
        $progressBar = $this->command->getOutput()->createProgressBar($familyCount);

        for ($i = 0; $i < $familyCount; $i++) {
            $this->createFamily($i + 1);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('Family seeding completed!');
    }

    private function populateExistingTeam(User $user, Team $team, string $surname): void
    {
        $members = $this->createRealisticFamilyMembers($team, $user, $surname);
        $this->createTimelineEntries($team, $user, $members, rand(40, 60));
    }

    private function createFamily(int $familyIndex): void
    {
        $surname = $this->lastNames[array_rand($this->lastNames)];

        // Create the main user for this family
        $mainUser = User::factory()->withPersonalTeam()->create([
            'name' => $this->getRandomName('male') . ' ' . $surname,
            'email' => strtolower($surname) . $familyIndex . '@example.com',
            'password' => Hash::make('password'),
        ]);

        $team = $mainUser->currentTeam;
        $team->update(['name' => "The {$surname} Family"]);

        $members = $this->createRealisticFamilyMembers($team, $mainUser, $surname);
        $this->createTimelineEntries($team, $mainUser, $members, rand(30, 50));
    }

    /**
     * Create a realistic family tree structure:
     * - Paternal grandparents (grandfather + grandmother)
     * - Maternal grandparents (grandfather + grandmother)
     * - Father and Mother (parents)
     * - Main person + siblings (2-4 siblings)
     * - Spouses for some siblings
     * - Children of siblings
     * - Optionally: step-parent for some families
     */
    private function createRealisticFamilyMembers(Team $team, User $creator, string $surname): array
    {
        $members = [];
        $maternalSurname = $this->lastNames[array_rand($this->lastNames)];

        // === PATERNAL GRANDPARENTS (father's parents) ===
        $paternalGrandfather = $this->createMember($team, $creator, [
            'gender' => 'male',
            'first_name' => $this->getRandomName('male'),
            'last_name' => $surname,
            'nickname' => 'Grandpa ' . substr($surname, 0, 1),
            'birth_date' => Carbon::create(rand(1925, 1940), rand(1, 12), rand(1, 28)),
            'is_living' => rand(0, 100) > 70,
        ]);
        $members['paternal_grandfather'] = $paternalGrandfather;

        $paternalGrandmother = $this->createMember($team, $creator, [
            'gender' => 'female',
            'first_name' => $this->getRandomName('female'),
            'last_name' => $surname,
            'nickname' => 'Grandma ' . substr($surname, 0, 1),
            'birth_date' => Carbon::create(rand(1928, 1943), rand(1, 12), rand(1, 28)),
            'is_living' => rand(0, 100) > 60,
        ]);
        $members['paternal_grandmother'] = $paternalGrandmother;

        // Grandparents married
        FamilyRelationship::createBidirectional($paternalGrandfather, $paternalGrandmother, 'husband');

        // === MATERNAL GRANDPARENTS (mother's parents) ===
        $maternalGrandfather = $this->createMember($team, $creator, [
            'gender' => 'male',
            'first_name' => $this->getRandomName('male'),
            'last_name' => $maternalSurname,
            'nickname' => 'Grandpa ' . substr($maternalSurname, 0, 1),
            'birth_date' => Carbon::create(rand(1925, 1940), rand(1, 12), rand(1, 28)),
            'is_living' => rand(0, 100) > 70,
        ]);
        $members['maternal_grandfather'] = $maternalGrandfather;

        $maternalGrandmother = $this->createMember($team, $creator, [
            'gender' => 'female',
            'first_name' => $this->getRandomName('female'),
            'last_name' => $maternalSurname,
            'nickname' => 'Grandma ' . substr($maternalSurname, 0, 1),
            'birth_date' => Carbon::create(rand(1928, 1943), rand(1, 12), rand(1, 28)),
            'is_living' => rand(0, 100) > 60,
        ]);
        $members['maternal_grandmother'] = $maternalGrandmother;

        // Maternal grandparents married
        FamilyRelationship::createBidirectional($maternalGrandfather, $maternalGrandmother, 'husband');

        // === PARENTS ===
        $father = $this->createMember($team, $creator, [
            'gender' => 'male',
            'first_name' => $this->getRandomName('male'),
            'last_name' => $surname,
            'nickname' => 'Dad',
            'birth_date' => Carbon::create(rand(1955, 1975), rand(1, 12), rand(1, 28)),
            'is_living' => rand(0, 100) > 20,
        ]);
        $members['father'] = $father;

        $mother = $this->createMember($team, $creator, [
            'gender' => 'female',
            'first_name' => $this->getRandomName('female'),
            'last_name' => $surname, // Took husband's name
            'nickname' => 'Mom',
            'birth_date' => Carbon::create(rand(1958, 1978), rand(1, 12), rand(1, 28)),
            'is_living' => true,
        ]);
        $members['mother'] = $mother;

        // Parents married
        FamilyRelationship::createBidirectional($father, $mother, 'husband');

        // Father is child of paternal grandparents
        FamilyRelationship::createBidirectional($father, $paternalGrandfather, 'child_of_father');
        FamilyRelationship::createBidirectional($father, $paternalGrandmother, 'child_of_mother');

        // Mother is child of maternal grandparents
        FamilyRelationship::createBidirectional($mother, $maternalGrandfather, 'child_of_father');
        FamilyRelationship::createBidirectional($mother, $maternalGrandmother, 'child_of_mother');

        // Grandparent relationships
        FamilyRelationship::createBidirectional($paternalGrandfather, $father, 'grandfather');
        FamilyRelationship::createBidirectional($paternalGrandmother, $father, 'grandmother');
        FamilyRelationship::createBidirectional($maternalGrandfather, $mother, 'grandfather');
        FamilyRelationship::createBidirectional($maternalGrandmother, $mother, 'grandmother');

        // === CHILDREN (siblings) ===
        $siblingCount = rand(2, 4);
        $siblings = [];

        for ($i = 0; $i < $siblingCount; $i++) {
            $gender = rand(0, 1) ? 'male' : 'female';
            $birthYear = rand(1985, 2000);

            $sibling = $this->createMember($team, $creator, [
                'gender' => $gender,
                'first_name' => $this->getRandomName($gender),
                'last_name' => $surname,
                'birth_date' => Carbon::create($birthYear, rand(1, 12), rand(1, 28)),
                'is_living' => true,
            ]);
            $siblings[] = $sibling;
            $members['sibling_' . $i] = $sibling;

            // Each child has exactly 1 father and 1 mother
            FamilyRelationship::createBidirectional($sibling, $father, 'child_of_father');
            FamilyRelationship::createBidirectional($sibling, $mother, 'child_of_mother');

            // Father/mother relationship from parent's perspective
            FamilyRelationship::createBidirectional($father, $sibling, 'father');
            FamilyRelationship::createBidirectional($mother, $sibling, 'mother');

            // Grandparent to grandchild relationships
            FamilyRelationship::createBidirectional($paternalGrandfather, $sibling, 'grandfather');
            FamilyRelationship::createBidirectional($paternalGrandmother, $sibling, 'grandmother');
            FamilyRelationship::createBidirectional($maternalGrandfather, $sibling, 'grandfather');
            FamilyRelationship::createBidirectional($maternalGrandmother, $sibling, 'grandmother');
        }

        // Siblings to each other (brother/sister)
        for ($i = 0; $i < count($siblings); $i++) {
            for ($j = $i + 1; $j < count($siblings); $j++) {
                $relType = $siblings[$i]->gender === 'male' ? 'brother' : 'sister';
                FamilyRelationship::createBidirectional($siblings[$i], $siblings[$j], $relType);
            }
        }

        // === SPOUSES FOR SOME SIBLINGS ===
        $marriedCount = rand(1, min(2, $siblingCount));
        for ($i = 0; $i < $marriedCount; $i++) {
            $sibling = $siblings[$i];
            $spouseGender = $sibling->gender === 'male' ? 'female' : 'male';
            $spouseSurname = $this->lastNames[array_rand($this->lastNames)];

            $spouse = $this->createMember($team, $creator, [
                'gender' => $spouseGender,
                'first_name' => $this->getRandomName($spouseGender),
                'last_name' => $sibling->gender === 'male' ? $surname : $spouseSurname,
                'birth_date' => Carbon::parse($sibling->birth_date)->addYears(rand(-3, 3)),
                'is_living' => true,
            ]);
            $members['spouse_' . $i] = $spouse;

            // Marriage relationship
            $marriageType = $sibling->gender === 'male' ? 'husband' : 'wife';
            FamilyRelationship::createBidirectional($sibling, $spouse, $marriageType);

            // === CHILDREN FOR MARRIED SIBLINGS ===
            $childCount = rand(0, 3);
            for ($c = 0; $c < $childCount; $c++) {
                $childGender = rand(0, 1) ? 'male' : 'female';
                $child = $this->createMember($team, $creator, [
                    'gender' => $childGender,
                    'first_name' => $this->getRandomName($childGender),
                    'last_name' => $sibling->gender === 'male' ? $surname : $spouse->last_name,
                    'birth_date' => Carbon::parse($sibling->birth_date)->addYears(rand(25, 35)),
                    'is_living' => true,
                ]);
                $members['grandchild_' . $i . '_' . $c] = $child;

                // Determine who is father/mother
                if ($sibling->gender === 'male') {
                    FamilyRelationship::createBidirectional($child, $sibling, 'child_of_father');
                    FamilyRelationship::createBidirectional($child, $spouse, 'child_of_mother');
                    FamilyRelationship::createBidirectional($sibling, $child, 'father');
                    FamilyRelationship::createBidirectional($spouse, $child, 'mother');
                } else {
                    FamilyRelationship::createBidirectional($child, $spouse, 'child_of_father');
                    FamilyRelationship::createBidirectional($child, $sibling, 'child_of_mother');
                    FamilyRelationship::createBidirectional($spouse, $child, 'father');
                    FamilyRelationship::createBidirectional($sibling, $child, 'mother');
                }
            }
        }

        // === OPTIONAL: STEP-PARENT (20% chance) ===
        if (rand(0, 100) < 20) {
            $stepParentGender = rand(0, 1) ? 'male' : 'female';
            $stepParent = $this->createMember($team, $creator, [
                'gender' => $stepParentGender,
                'first_name' => $this->getRandomName($stepParentGender),
                'last_name' => $this->lastNames[array_rand($this->lastNames)],
                'nickname' => $stepParentGender === 'male' ? 'Stepdad' : 'Stepmom',
                'birth_date' => Carbon::create(rand(1958, 1975), rand(1, 12), rand(1, 28)),
                'is_living' => true,
            ]);
            $members['step_parent'] = $stepParent;

            // Step-parent married to one of the parents (the opposite gender one)
            $bioParent = $stepParentGender === 'male' ? $mother : $father;
            FamilyRelationship::createBidirectional($stepParent, $bioParent, $stepParentGender === 'male' ? 'husband' : 'wife');

            // Step-parent relationships to children
            $stepType = $stepParentGender === 'male' ? 'stepfather' : 'stepmother';
            foreach ($siblings as $sibling) {
                FamilyRelationship::createBidirectional($stepParent, $sibling, $stepType);
            }
        }

        // === UNCLE/AUNT (father's sibling) ===
        if (rand(0, 100) < 60) {
            $uncleAuntGender = rand(0, 1) ? 'male' : 'female';
            $uncleAunt = $this->createMember($team, $creator, [
                'gender' => $uncleAuntGender,
                'first_name' => $this->getRandomName($uncleAuntGender),
                'last_name' => $surname,
                'birth_date' => Carbon::parse($father->birth_date)->addYears(rand(-5, 5)),
                'is_living' => rand(0, 100) > 20,
            ]);
            $members['uncle_aunt'] = $uncleAunt;

            // Uncle/Aunt is sibling of father
            $sibRelType = $uncleAuntGender === 'male' ? 'brother' : 'sister';
            FamilyRelationship::createBidirectional($uncleAunt, $father, $sibRelType);

            // Uncle/Aunt is child of paternal grandparents
            FamilyRelationship::createBidirectional($uncleAunt, $paternalGrandfather, 'child_of_father');
            FamilyRelationship::createBidirectional($uncleAunt, $paternalGrandmother, 'child_of_mother');

            // Uncle/Aunt relationship to siblings (nieces/nephews)
            $uncleAuntType = $uncleAuntGender === 'male' ? 'uncle' : 'aunt';
            foreach ($siblings as $sibling) {
                FamilyRelationship::createBidirectional($uncleAunt, $sibling, $uncleAuntType);
            }

            // === COUSINS (children of uncle/aunt) ===
            $cousinCount = rand(0, 2);
            for ($c = 0; $c < $cousinCount; $c++) {
                $cousinGender = rand(0, 1) ? 'male' : 'female';
                $cousin = $this->createMember($team, $creator, [
                    'gender' => $cousinGender,
                    'first_name' => $this->getRandomName($cousinGender),
                    'last_name' => $uncleAuntGender === 'male' ? $surname : $this->lastNames[array_rand($this->lastNames)],
                    'birth_date' => Carbon::create(rand(1985, 2000), rand(1, 12), rand(1, 28)),
                    'is_living' => true,
                ]);
                $members['cousin_' . $c] = $cousin;

                // Cousin relationship to siblings
                foreach ($siblings as $sibling) {
                    FamilyRelationship::createBidirectional($cousin, $sibling, 'cousin');
                }
            }
        }

        return $members;
    }

    private function createMember(Team $team, User $creator, array $data): FamilyMember
    {
        $deathDate = null;
        if (!($data['is_living'] ?? true)) {
            $birthDate = Carbon::parse($data['birth_date']);
            $deathYear = $birthDate->year + rand(50, 95);
            if ($deathYear <= now()->year) {
                $deathDate = Carbon::create($deathYear, rand(1, 12), rand(1, 28));
            } else {
                $data['is_living'] = true;
            }
        }

        $member = FamilyMember::create([
            'team_id' => $team->id,
            'created_by' => $creator->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'nickname' => $data['nickname'] ?? null,
            'gender' => $data['gender'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'death_date' => $deathDate,
            'is_living' => $data['is_living'] ?? true,
            'notes' => rand(0, 100) > 80 ? 'Additional notes about this family member.' : null,
        ]);

        // Create birth timeline entry
        if ($data['birth_date']) {
            $birthEntry = TimelineEntry::create([
                'team_id' => $team->id,
                'user_id' => $creator->id,
                'title' => $member->display_name . ' was born',
                'content' => 'Welcome to the family!',
                'event_date' => $data['birth_date'],
                'event_type' => 'birth',
                'location' => $this->locations[array_rand($this->locations)],
                'people_involved' => [$member->display_name],
                'visibility' => 'immediate_family',
                'is_published' => true,
            ]);
            $birthEntry->familyMembers()->attach($member->id);
        }

        // Create death timeline entry
        if ($deathDate) {
            $deathEntry = TimelineEntry::create([
                'team_id' => $team->id,
                'user_id' => $creator->id,
                'title' => 'In loving memory of ' . $member->display_name,
                'content' => 'Forever in our hearts.',
                'event_date' => $deathDate,
                'event_type' => 'death',
                'location' => $this->locations[array_rand($this->locations)],
                'people_involved' => [$member->display_name],
                'visibility' => 'immediate_family',
                'is_published' => true,
            ]);
            $deathEntry->familyMembers()->attach($member->id);
        }

        return $member;
    }

    private function createTimelineEntries(Team $team, User $creator, array $members, int $count): void
    {
        $memberList = array_values($members);

        for ($i = 0; $i < $count; $i++) {
            $eventType = $this->getRandomEventType();
            $involvedMembers = $this->getRandomMembers($memberList, rand(1, 4));
            $eventDate = $this->getEventDateForType($eventType, $involvedMembers);

            $entry = TimelineEntry::create([
                'team_id' => $team->id,
                'user_id' => $creator->id,
                'title' => $this->generateTitle($eventType, $involvedMembers),
                'content' => $this->generateContent($eventType),
                'event_date' => $eventDate,
                'event_type' => $eventType,
                'location' => $this->locations[array_rand($this->locations)],
                'people_involved' => collect($involvedMembers)->pluck('display_name')->toArray(),
                'visibility' => ['immediate_family', 'extended_family', 'private'][rand(0, 2)],
                'is_published' => rand(0, 100) > 20,
            ]);

            $entry->familyMembers()->sync(collect($involvedMembers)->pluck('id'));
        }
    }

    private function getRandomEventType(): string
    {
        $types = ['story', 'marriage', 'milestone', 'photo', 'memory', 'tradition'];
        $weights = [30, 10, 25, 20, 10, 5];

        $total = array_sum($weights);
        $rand = rand(1, $total);
        $cumulative = 0;

        foreach ($types as $i => $type) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $type;
            }
        }

        return 'story';
    }

    private function getRandomMembers(array $members, int $count): array
    {
        shuffle($members);
        return array_slice($members, 0, min($count, count($members)));
    }

    private function getEventDateForType(string $type, array $members): Carbon
    {
        $member = $members[0] ?? null;

        switch ($type) {
            case 'marriage':
                $birthYear = $member?->birth_date ? Carbon::parse($member->birth_date)->year : 1980;
                return Carbon::create($birthYear + rand(22, 35), rand(1, 12), rand(1, 28));
            default:
                return Carbon::now()->subDays(rand(1, 365 * 30));
        }
    }

    private function generateTitle(string $type, array $members): string
    {
        $memberName = $members[0]->display_name ?? 'Family Member';

        $titles = [
            'story' => [
                "The time {$memberName} surprised everyone",
                "{$memberName}'s adventure",
                "Remember when {$memberName} learned something new",
                "The great {$memberName} story",
                "{$memberName} and the family gathering",
            ],
            'marriage' => [
                "{$memberName}'s wedding day",
                "{$memberName} got married!",
                "The wedding of {$memberName}",
                "{$memberName}'s special day",
            ],
            'milestone' => [
                "{$memberName}'s graduation",
                "{$memberName}'s first day of school",
                "{$memberName}'s retirement celebration",
                "{$memberName}'s birthday celebration",
                "{$memberName} bought their first house",
                "{$memberName}'s promotion",
            ],
            'photo' => [
                "Family photo at the beach",
                "Holiday gathering with {$memberName}",
                "Backyard barbecue photos",
                "{$memberName} at the reunion",
            ],
            'memory' => [
                "A special memory with {$memberName}",
                "That unforgettable day with {$memberName}",
                "{$memberName}'s wisdom",
            ],
            'tradition' => [
                "Annual Thanksgiving gathering",
                "Christmas Eve traditions",
                "Summer vacation tradition",
                "Sunday dinner tradition",
            ],
        ];

        $options = $titles[$type] ?? $titles['story'];
        return $options[array_rand($options)];
    }

    private function generateContent(string $type): ?string
    {
        if (rand(0, 100) > 70) return null;

        $contents = [
            "This was such a memorable day for our family.",
            "I still remember this like it was yesterday.",
            "Looking back at this moment brings so many happy memories.",
            "This is one of those stories that gets told at every gathering.",
            "What a wonderful time we had!",
            "Such precious memories.",
            "Found this old photo and it brought back memories.",
            "Everyone was there - a true family affair.",
        ];

        return $contents[array_rand($contents)];
    }

    private function getRandomName(string $gender): string
    {
        return $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
    }
}
