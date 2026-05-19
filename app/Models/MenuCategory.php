<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    protected $fillable = ['name', 'branch_id', 'description'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }
}