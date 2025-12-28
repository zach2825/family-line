<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyMember extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'created_by',
        'first_name',
        'last_name',
        'nickname',
        'birth_date',
        'death_date',
        'gender',
        'photo_path',
        'notes',
        'is_living',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'is_living' => 'boolean',
    ];

    protected $appends = ['full_name', 'display_name', 'age'];

    // Relationships
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function timelineEntries(): BelongsToMany
    {
        return $this->belongsToMany(TimelineEntry::class, 'family_member_timeline_entry')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function relationshipsFrom(): HasMany
    {
        return $this->hasMany(FamilyRelationship::class, 'family_member_id');
    }

    public function relationshipsTo(): HasMany
    {
        return $this->hasMany(FamilyRelationship::class, 'related_member_id');
    }

    // Family relationship helpers - specific types
    public function father()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->wherePivotIn('relationship_type', [FamilyRelationship::TYPE_CHILD_OF_FATHER, FamilyRelationship::TYPE_CHILD])
            ->where('gender', 'male')
            ->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    public function mother()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->wherePivotIn('relationship_type', [FamilyRelationship::TYPE_CHILD_OF_MOTHER, FamilyRelationship::TYPE_CHILD])
            ->where('gender', 'female')
            ->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    public function stepParents()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->wherePivotIn('relationship_type', FamilyRelationship::STEP_PARENT_TYPES)
            ->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    // Combined parents (father + mother + step-parents for backward compat)
    public function parents()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->wherePivotIn('relationship_type', array_merge(
            FamilyRelationship::CHILD_TYPES,
            ['stepchild_of_father', 'stepchild_of_mother']
        ))
            ->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    public function children()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->wherePivotIn('relationship_type', array_merge(
            FamilyRelationship::PARENT_TYPES,
            FamilyRelationship::STEP_PARENT_TYPES
        ))
            ->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    public function spouses()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->wherePivotIn('relationship_type', FamilyRelationship::SPOUSE_TYPES)
            ->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    public function siblings()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->wherePivotIn('relationship_type', FamilyRelationship::SIBLING_TYPES)
            ->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    /**
     * Get all relationships FROM this member (this member is the subject).
     */
    public function allRelatedMembers()
    {
        return $this->belongsToMany(
            FamilyMember::class,
            'family_relationships',
            'family_member_id',
            'related_member_id'
        )->withPivot('id', 'relationship_type')
            ->withTimestamps();
    }

    // Get all relationships for this member
    public function allRelationships()
    {
        return FamilyRelationship::where('family_member_id', $this->id)
            ->orWhere('related_member_id', $this->id)
            ->with(['member', 'relatedMember'])
            ->get();
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?: $this->full_name;
    }

    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        $endDate = $this->death_date ?? now();
        return $this->birth_date->diffInYears($endDate);
    }

    // Scopes
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeLiving($query)
    {
        return $query->where('is_living', true);
    }

    public function scopeDeceased($query)
    {
        return $query->where('is_living', false);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%");
        });
    }

    // Find by nickname or name
    public static function findByNameOrNickname(int $teamId, string $name): ?self
    {
        $name = trim($name);

        return static::forTeam($teamId)
            ->where(function ($q) use ($name) {
                $q->where('nickname', $name)
                    ->orWhere('first_name', $name)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) = ?", [$name]);
            })
            ->first();
    }
}
