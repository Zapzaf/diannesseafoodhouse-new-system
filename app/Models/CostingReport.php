<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostingReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const REASON_DELIVERY = 'delivery';
    public const REASON_PRODUCTION = 'production';
    public const REASON_OTHERS = 'others';

    protected $fillable = [
        'branch_id',
        'item_id',
        'current_price',
        'proposed_price',
        'reason_type',
        'reference_id',
        'reason',
        'costing_details',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'approval_remarks',
    ];

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:4',
            'proposed_price' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->hasMany(CostingReportAttachment::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function reasonTypeLabel(): string
    {
        return match ($this->reason_type) {
            self::REASON_DELIVERY => 'From Delivery',
            self::REASON_PRODUCTION => 'From Production',
            default => 'Others',
        };
    }
}
