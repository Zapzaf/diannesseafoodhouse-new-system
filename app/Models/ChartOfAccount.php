<?php

namespace App\Models;

use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartOfAccount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep name_normalized in sync with name on every save — from the
        // controller, the seeder, a future importer, or tinker — so
        // duplicate detection never depends on every caller remembering to
        // normalize it themselves.
        static::saving(function (ChartOfAccount $account): void {
            if ($account->isDirty('name')) {
                $account->name_normalized = NameNormalizer::normalize($account->name);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether an account with this name (ignoring case, spacing, and
     * singular/plural wording) already exists — e.g. "Meal Expense" vs.
     * "Meals Expense". Used by create/edit validation and available to any
     * other caller (importer, artisan command, etc.) that needs the same check.
     */
    public static function existsWithName(string $name, ?int $ignoreId = null): bool
    {
        return static::query()
            ->where('name_normalized', NameNormalizer::normalize($name))
            ->when($ignoreId, fn ($q, $id) => $q->whereKeyNot($id))
            ->exists();
    }
}
