<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

class PosTerminal extends Model
{
    protected $fillable = ['branch_id', 'name', 'code', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Most branches only ever run one physical register, so terminal
     * management shouldn't be a manual setup step for them. This returns
     * the branch's first terminal (any status), auto-creating a default
     * "Register 1" the first time a branch is found with none at all.
     * A branch that already has terminals — even if all are inactive, which
     * is a deliberate admin choice — is left untouched.
     */
    public static function ensureDefaultForBranch(int $branchId): self
    {
        $existing = static::where('branch_id', $branchId)->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        try {
            return static::create([
                'branch_id' => $branchId,
                'name' => 'Register 1',
                'code' => 'T1',
                'is_active' => true,
            ]);
        } catch (QueryException $e) {
            // Lost a race with another concurrent request provisioning the
            // same branch's default terminal — just return the one that won.
            return static::where('branch_id', $branchId)->where('code', 'T1')->firstOrFail();
        }
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashShifts(): HasMany
    {
        return $this->hasMany(CashShift::class);
    }

    public function zReadings(): HasMany
    {
        return $this->hasMany(ZReading::class);
    }
}
