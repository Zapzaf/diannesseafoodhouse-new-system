<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'address', 'manager_id', 'is_active',
        'vat_enabled', 'vat_percentage',
        'pwd_discount_enabled', 'senior_discount_enabled',
        'contact_number', 'tin_number',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'vat_enabled' => 'boolean',
            'vat_percentage' => 'decimal:2',
            'pwd_discount_enabled' => 'boolean',
            'senior_discount_enabled' => 'boolean',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function mailSetting(): HasOne
    {
        return $this->hasOne(MailSetting::class);
    }
}
