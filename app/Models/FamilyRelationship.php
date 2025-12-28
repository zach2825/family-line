<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyRelationship extends Model
{
    use HasFactory;

    // Legacy generic types (kept for backward compatibility)
    public const TYPE_PARENT = 'parent';
    public const TYPE_CHILD = 'child';
    public const TYPE_SPOUSE = 'spouse';
    public const TYPE_SIBLING = 'sibling';

    // Specific parent types
    public const TYPE_FATHER = 'father';
    public const TYPE_MOTHER = 'mother';
    public const TYPE_CHILD_OF_FATHER = 'child_of_father';
    public const TYPE_CHILD_OF_MOTHER = 'child_of_mother';

    // Step-parent types
    public const TYPE_STEPFATHER = 'stepfather';
    public const TYPE_STEPMOTHER = 'stepmother';

    // Spouse types
    public const TYPE_HUSBAND = 'husband';
    public const TYPE_WIFE = 'wife';

    // Sibling types
    public const TYPE_BROTHER = 'brother';
    public const TYPE_SISTER = 'sister';

    // Grandparent types
    public const TYPE_GRANDFATHER = 'grandfather';
    public const TYPE_GRANDMOTHER = 'grandmother';

    // Aunt/Uncle types
    public const TYPE_UNCLE = 'uncle';
    public const TYPE_AUNT = 'aunt';

    // Helper arrays for querying
    public const PARENT_TYPES = [self::TYPE_FATHER, self::TYPE_MOTHER, self::TYPE_PARENT];
    public const CHILD_TYPES = [self::TYPE_CHILD_OF_FATHER, self::TYPE_CHILD_OF_MOTHER, self::TYPE_CHILD];
    public const STEP_PARENT_TYPES = [self::TYPE_STEPFATHER, self::TYPE_STEPMOTHER];
    public const SPOUSE_TYPES = [self::TYPE_HUSBAND, self::TYPE_WIFE, self::TYPE_SPOUSE];
    public const SIBLING_TYPES = [self::TYPE_BROTHER, self::TYPE_SISTER, self::TYPE_SIBLING];

    protected $fillable = [
        'family_member_id',
        'related_member_id',
        'relationship_type',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }

    public function relatedMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'related_member_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->relationship_type] ?? $this->relationship_type;
    }

    /**
     * Create a relationship and its inverse (bidirectional).
     */
    public static function createBidirectional(
        FamilyMember $member1,
        FamilyMember $member2,
        string $type
    ): array {
        $relationships = [];

        // Create the primary relationship
        $relationships[] = static::firstOrCreate([
            'family_member_id' => $member1->id,
            'related_member_id' => $member2->id,
            'relationship_type' => $type,
        ]);

        // Get the inverse type from the database
        $relationshipType = RelationshipType::where('slug', $type)->first();
        $inverseType = $relationshipType?->inverse_slug ?? $type;

        $relationships[] = static::firstOrCreate([
            'family_member_id' => $member2->id,
            'related_member_id' => $member1->id,
            'relationship_type' => $inverseType,
        ]);

        return $relationships;
    }

    /**
     * Remove a relationship and its inverse.
     */
    public static function removeBidirectional(
        FamilyMember $member1,
        FamilyMember $member2,
        string $type
    ): void {
        // Get the inverse type from the database
        $relationshipType = RelationshipType::where('slug', $type)->first();
        $inverseType = $relationshipType?->inverse_slug ?? $type;

        static::where([
            'family_member_id' => $member1->id,
            'related_member_id' => $member2->id,
            'relationship_type' => $type,
        ])->delete();

        static::where([
            'family_member_id' => $member2->id,
            'related_member_id' => $member1->id,
            'relationship_type' => $inverseType,
        ])->delete();
    }

    /**
     * Get human-readable description of relationship.
     * E.g., "John is the parent of Jane"
     */
    public function getDescriptionAttribute(): string
    {
        $memberName = $this->member->display_name ?? 'Unknown';
        $relatedName = $this->relatedMember->display_name ?? 'Unknown';

        return match ($this->relationship_type) {
            self::TYPE_PARENT => "{$memberName} is the parent of {$relatedName}",
            self::TYPE_CHILD => "{$memberName} is the child of {$relatedName}",
            self::TYPE_SPOUSE => "{$memberName} is married to {$relatedName}",
            self::TYPE_SIBLING => "{$memberName} is a sibling of {$relatedName}",
            default => "{$memberName} is related to {$relatedName}",
        };
    }
}
