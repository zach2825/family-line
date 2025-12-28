<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class TimelineEntry extends Model
{
    use HasFactory;
    use RevisionableTrait;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'title',
        'content',
        'event_date',
        'event_end_date',
        'event_type',
        'location',
        'people_involved',
        'family_surname',
        'has_audio',
        'audio_transcript',
        'visibility',
        'is_published',
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_end_date' => 'date',
        'people_involved' => 'array',
        'has_audio' => 'boolean',
        'is_published' => 'boolean',
    ];

    protected $revisionCreationsEnabled = true;

    public const EVENT_TYPES = [
        'story' => 'Story',
        'birth' => 'Birth',
        'death' => 'Death',
        'marriage' => 'Marriage',
        'milestone' => 'Milestone',
        'photo' => 'Photo',
        'document' => 'Document',
        'memory' => 'Memory',
        'tradition' => 'Family Tradition',
    ];

    public const VISIBILITY_OPTIONS = [
        'immediate_family' => 'Immediate Family',
        'extended_family' => 'Extended Family',
        'private' => 'Private (Only Me)',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function familyMembers(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'family_member_timeline_entry')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForSurname($query, $surname)
    {
        return $query->where('family_surname', $surname);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeVisibleTo($query, User $user)
    {
        // User can see entries from teams they belong to
        $teamIds = $user->allTeams()->pluck('id');

        return $query->whereIn('team_id', $teamIds)
            ->where(function ($q) use ($user) {
                // Show all immediate family entries
                $q->where('visibility', 'immediate_family')
                    // Or extended family entries if user has extended access
                    ->orWhere('visibility', 'extended_family')
                    // Or private entries only if user created them
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'private')
                            ->where('user_id', $user->id);
                    });
            });
    }

    public function scopeOrderedByDate($query, $direction = 'desc')
    {
        return $query->orderBy('event_date', $direction);
    }

    public function scopeForMember($query, $memberId)
    {
        return $query->whereHas('familyMembers', function ($q) use ($memberId) {
            $q->where('family_members.id', $memberId);
        });
    }

    public function getEventTypeLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }

    public function getVisibilityLabelAttribute(): string
    {
        return self::VISIBILITY_OPTIONS[$this->visibility] ?? $this->visibility;
    }
}
