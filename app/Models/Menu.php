<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Menu extends Model
{
    protected $fillable = ['branch_id', 'category_id', 'menu_category_id', 'name', 'menu_description', 'selling_price', 'image', 'category', 'created_by'];

    protected $casts = [
        'selling_price' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * Inventory items (ingredients) required per unit of this menu item.
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'menu_inventory')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Compute the COGS per single unit of this menu item.
     */
    public function computeUnitCost(): float
    {
        $cost = 0.0;
        foreach ($this->items as $item) {
            $cost += (float) ($item->unit_price ?? 0) * (float) $item->pivot->quantity_required;
        }
        return $cost;
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->itemCategory?->name 
            ? ($this->itemCategory->location?->name . ' > ' . $this->itemCategory->name)
            : (string) $this->category;
    }
}