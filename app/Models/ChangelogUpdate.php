<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChangelogUpdate extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'image',
        'released_at',
        'is_published',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'date',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Single source of truth for the update types this module supports —
     * used by the form's <select>, the badge styling, and validation, so
     * adding a new type only ever means changing it here.
     *
     * @var array<string, array{label: string, badge: string, icon: string}>
     */
    public const TYPES = [
        'new_feature' => ['label' => 'New Feature', 'badge' => 'bg-primary-soft text-primary', 'icon' => 'sparkles'],
        'improvement' => ['label' => 'Improvement', 'badge' => 'bg-info-soft text-info', 'icon' => 'trending-up'],
        'bug_fix' => ['label' => 'Bug Fix', 'badge' => 'bg-warning-soft text-warning', 'icon' => 'bug'],
        'security' => ['label' => 'Security', 'badge' => 'bg-danger-soft text-danger', 'icon' => 'shield-check'],
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return self::TYPES[$this->type]['badge'] ?? 'bg-secondary-soft text-secondary';
    }

    public function getTypeIconAttribute(): string
    {
        return self::TYPES[$this->type]['icon'] ?? 'info';
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }
}
