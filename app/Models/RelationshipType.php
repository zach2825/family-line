<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationshipType extends Model
{
    protected $fillable = [
        'team_id',
        'slug',
        'label',
        'category',
        'inverse_slug',
        'is_bidirectional',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_bidirectional' => 'boolean',
        'is_system' => 'boolean',
    ];

    public const CATEGORY_IMMEDIATE = 'immediate';
    public const CATEGORY_EXTENDED = 'extended';
    public const CATEGORY_NON_FAMILY = 'non_family';

    public const CATEGORIES = [
        self::CATEGORY_IMMEDIATE => 'Immediate Family',
        self::CATEGORY_EXTENDED => 'Extended Family',
        self::CATEGORY_NON_FAMILY => 'Non-Family',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the inverse relationship type.
     */
    public function inverse(): ?self
    {
        if (!$this->inverse_slug) {
            return null;
        }

        return static::where('slug', $this->inverse_slug)->first();
    }

    /**
     * Get all available relationship types for a team.
     * Includes system defaults plus team-specific custom types.
     */
    public static function forTeam(?int $teamId = null): \Illuminate\Database\Eloquent\Builder
    {
        return static::where(function ($q) use ($teamId) {
            $q->whereNull('team_id'); // System defaults
            if ($teamId) {
                $q->orWhere('team_id', $teamId); // Team-specific
            }
        })->orderBy('sort_order');
    }

    /**
     * Get relationship types grouped by category.
     * Returns simple arrays with only essential fields for frontend.
     */
    public static function groupedForTeam(?int $teamId = null): array
    {
        $types = static::forTeam($teamId)
            ->select('slug', 'label', 'category')
            ->get();

        return [
            'immediate' => $types->where('category', self::CATEGORY_IMMEDIATE)->values()->map(fn($t) => [
                'slug' => $t->slug,
                'label' => $t->label,
            ])->toArray(),
            'extended' => $types->where('category', self::CATEGORY_EXTENDED)->values()->map(fn($t) => [
                'slug' => $t->slug,
                'label' => $t->label,
            ])->toArray(),
            'non_family' => $types->where('category', self::CATEGORY_NON_FAMILY)->values()->map(fn($t) => [
                'slug' => $t->slug,
                'label' => $t->label,
            ])->toArray(),
        ];
    }
}
