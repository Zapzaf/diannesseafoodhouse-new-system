<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZReading extends Model
{
    protected $fillable = [
        'branch_id', 'pos_terminal_id', 'business_date', 'sequence_number', 'reading_number',
        'generated_by', 'generated_at', 'snapshot', 'status',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'business_date' => 'date',
        'generated_at' => 'datetime',
        'voided_at' => 'datetime',
        'snapshot' => 'array',
    ];

    /**
     * Fields that make up the frozen snapshot. Once a reading is locked (or
     * voided), none of these may change — only the void_* fields may be set,
     * and only while transitioning locked -> voided. This is a
     * defense-in-depth guard: the controller already enforces this, but a
     * model-level guard keeps the record immutable even if some other code
     * path ever touches it directly.
     */
    private const IMMUTABLE_FIELDS = [
        'branch_id', 'pos_terminal_id', 'business_date', 'sequence_number',
        'reading_number', 'generated_by', 'generated_at', 'snapshot',
    ];

    protected static function booted(): void
    {
        static::updating(function (ZReading $reading): void {
            $originalStatus = $reading->getOriginal('status');

            if ($originalStatus === 'voided') {
                throw new \RuntimeException('A voided Z Reading cannot be modified.');
            }

            if ($originalStatus === 'locked' && $reading->isDirty(self::IMMUTABLE_FIELDS)) {
                throw new \RuntimeException('A locked Z Reading snapshot cannot be modified.');
            }
        });

        static::deleting(function (ZReading $reading): void {
            throw new \RuntimeException('Z Readings cannot be deleted. Void the reading instead to preserve the audit trail.');
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public static function makeReadingNumber(int $terminalId, int $sequence): string
    {
        return 'Z-T' . $terminalId . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
