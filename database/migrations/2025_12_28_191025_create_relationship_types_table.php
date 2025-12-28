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
        Schema::create('relationship_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete(); // null = system default
            $table->string('slug')->unique(); // parent, child, spouse, cousin, etc.
            $table->string('label'); // Parent, Child, Spouse, Cousin
            $table->string('category'); // immediate, extended, non-family
            $table->string('inverse_slug')->nullable(); // For bidirectional: parent <-> child
            $table->boolean('is_bidirectional')->default(false); // spouse, sibling are bidirectional
            $table->boolean('is_system')->default(false); // System types can't be deleted
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'category']);
        });

        // Seed with default relationship types
        $types = [
            // Immediate family
            ['slug' => 'parent', 'label' => 'Parent', 'category' => 'immediate', 'inverse_slug' => 'child', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 1],
            ['slug' => 'child', 'label' => 'Child', 'category' => 'immediate', 'inverse_slug' => 'parent', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 2],
            ['slug' => 'spouse', 'label' => 'Spouse', 'category' => 'immediate', 'inverse_slug' => 'spouse', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 3],
            ['slug' => 'sibling', 'label' => 'Sibling', 'category' => 'immediate', 'inverse_slug' => 'sibling', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 4],

            // Extended family
            ['slug' => 'grandparent', 'label' => 'Grandparent', 'category' => 'extended', 'inverse_slug' => 'grandchild', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 10],
            ['slug' => 'grandchild', 'label' => 'Grandchild', 'category' => 'extended', 'inverse_slug' => 'grandparent', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 11],
            ['slug' => 'aunt_uncle', 'label' => 'Aunt/Uncle', 'category' => 'extended', 'inverse_slug' => 'niece_nephew', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 12],
            ['slug' => 'niece_nephew', 'label' => 'Niece/Nephew', 'category' => 'extended', 'inverse_slug' => 'aunt_uncle', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 13],
            ['slug' => 'cousin', 'label' => 'Cousin', 'category' => 'extended', 'inverse_slug' => 'cousin', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 14],
            ['slug' => 'in_law', 'label' => 'In-Law', 'category' => 'extended', 'inverse_slug' => 'in_law', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 15],
            ['slug' => 'step_parent', 'label' => 'Step-Parent', 'category' => 'extended', 'inverse_slug' => 'step_child', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 16],
            ['slug' => 'step_child', 'label' => 'Step-Child', 'category' => 'extended', 'inverse_slug' => 'step_parent', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 17],
            ['slug' => 'step_sibling', 'label' => 'Step-Sibling', 'category' => 'extended', 'inverse_slug' => 'step_sibling', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 18],
            ['slug' => 'half_sibling', 'label' => 'Half-Sibling', 'category' => 'extended', 'inverse_slug' => 'half_sibling', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 19],

            // Non-family
            ['slug' => 'friend', 'label' => 'Friend', 'category' => 'non_family', 'inverse_slug' => 'friend', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 30],
            ['slug' => 'godparent', 'label' => 'Godparent', 'category' => 'non_family', 'inverse_slug' => 'godchild', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 31],
            ['slug' => 'godchild', 'label' => 'Godchild', 'category' => 'non_family', 'inverse_slug' => 'godparent', 'is_bidirectional' => false, 'is_system' => true, 'sort_order' => 32],
            ['slug' => 'partner', 'label' => 'Partner', 'category' => 'immediate', 'inverse_slug' => 'partner', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 5],
            ['slug' => 'ex_spouse', 'label' => 'Ex-Spouse', 'category' => 'extended', 'inverse_slug' => 'ex_spouse', 'is_bidirectional' => true, 'is_system' => true, 'sort_order' => 20],
        ];

        $now = now();
        foreach ($types as $type) {
            DB::table('relationship_types')->insert(array_merge($type, [
                'team_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationship_types');
    }
};
