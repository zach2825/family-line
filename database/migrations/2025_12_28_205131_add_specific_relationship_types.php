<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // Add specific parent/child relationship types
        $newTypes = [
            // Specific parent types (immediate family)
            ['slug' => 'father', 'label' => 'Father', 'category' => 'immediate', 'inverse_slug' => 'child_of_father', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 1],
            ['slug' => 'mother', 'label' => 'Mother', 'category' => 'immediate', 'inverse_slug' => 'child_of_mother', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 2],
            ['slug' => 'child_of_father', 'label' => 'Child', 'category' => 'immediate', 'inverse_slug' => 'father', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 3],
            ['slug' => 'child_of_mother', 'label' => 'Child', 'category' => 'immediate', 'inverse_slug' => 'mother', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 4],

            // Step parents (extended)
            ['slug' => 'stepfather', 'label' => 'Stepfather', 'category' => 'extended', 'inverse_slug' => 'stepchild_of_father', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 16],
            ['slug' => 'stepmother', 'label' => 'Stepmother', 'category' => 'extended', 'inverse_slug' => 'stepchild_of_mother', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 17],
            ['slug' => 'stepchild_of_father', 'label' => 'Stepchild', 'category' => 'extended', 'inverse_slug' => 'stepfather', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 18],
            ['slug' => 'stepchild_of_mother', 'label' => 'Stepchild', 'category' => 'extended', 'inverse_slug' => 'stepmother', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 19],

            // Brother/Sister for clarity
            ['slug' => 'brother', 'label' => 'Brother', 'category' => 'immediate', 'inverse_slug' => 'sibling_of_brother', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 5],
            ['slug' => 'sister', 'label' => 'Sister', 'category' => 'immediate', 'inverse_slug' => 'sibling_of_sister', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 6],
            ['slug' => 'sibling_of_brother', 'label' => 'Sibling', 'category' => 'immediate', 'inverse_slug' => 'brother', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 7],
            ['slug' => 'sibling_of_sister', 'label' => 'Sibling', 'category' => 'immediate', 'inverse_slug' => 'sister', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 8],

            // Husband/Wife
            ['slug' => 'husband', 'label' => 'Husband', 'category' => 'immediate', 'inverse_slug' => 'wife', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 9],
            ['slug' => 'wife', 'label' => 'Wife', 'category' => 'immediate', 'inverse_slug' => 'husband', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 10],

            // Grandparents specific
            ['slug' => 'grandfather', 'label' => 'Grandfather', 'category' => 'extended', 'inverse_slug' => 'grandchild_of_gf', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 21],
            ['slug' => 'grandmother', 'label' => 'Grandmother', 'category' => 'extended', 'inverse_slug' => 'grandchild_of_gm', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 22],
            ['slug' => 'grandchild_of_gf', 'label' => 'Grandchild', 'category' => 'extended', 'inverse_slug' => 'grandfather', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 23],
            ['slug' => 'grandchild_of_gm', 'label' => 'Grandchild', 'category' => 'extended', 'inverse_slug' => 'grandmother', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 24],

            // Aunt/Uncle specific
            ['slug' => 'uncle', 'label' => 'Uncle', 'category' => 'extended', 'inverse_slug' => 'niece_nephew_of_uncle', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 25],
            ['slug' => 'aunt', 'label' => 'Aunt', 'category' => 'extended', 'inverse_slug' => 'niece_nephew_of_aunt', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 26],
            ['slug' => 'niece_nephew_of_uncle', 'label' => 'Niece/Nephew', 'category' => 'extended', 'inverse_slug' => 'uncle', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 27],
            ['slug' => 'niece_nephew_of_aunt', 'label' => 'Niece/Nephew', 'category' => 'extended', 'inverse_slug' => 'aunt', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 28],
        ];

        foreach ($newTypes as $type) {
            // Only insert if slug doesn't already exist
            if (!DB::table('relationship_types')->where('slug', $type['slug'])->exists()) {
                DB::table('relationship_types')->insert(array_merge($type, [
                    'team_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the new specific types
        DB::table('relationship_types')->whereIn('slug', [
            'father', 'mother', 'child_of_father', 'child_of_mother',
            'stepfather', 'stepmother', 'stepchild_of_father', 'stepchild_of_mother',
            'brother', 'sister', 'sibling_of_brother', 'sibling_of_sister',
            'husband', 'wife',
            'grandfather', 'grandmother', 'grandchild_of_gf', 'grandchild_of_gm',
            'uncle', 'aunt', 'niece_nephew_of_uncle', 'niece_nephew_of_aunt',
        ])->delete();
    }
};
