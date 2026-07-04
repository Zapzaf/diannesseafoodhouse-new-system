<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckRegister extends Model
{
    protected $table = 'check_register';

    protected $fillable = [
        'check_voucher_id',
        'check_date',
        'check_no',
        'payee',
        'particulars',
        'amount',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function checkVoucher(): BelongsTo
    {
        return $this->belongsTo(CheckVoucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
