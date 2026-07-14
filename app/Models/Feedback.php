<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'feedback';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'branch_id',
        'name',
        'date',
        'food_taste_rating',
        'overall_experience',
        'service_satisfaction',
        'speed_of_service',
        'cleanliness',
        'friendliness',
        'improvements',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The branch this feedback belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The rating fields and their display labels.
     *
     * @var array<string, string>
     */
    public const RATING_FIELDS = [
        'food_taste_rating' => 'Food Taste',
        'overall_experience' => 'Overall Experience',
        'service_satisfaction' => 'Service Satisfaction',
        'speed_of_service' => 'Speed of Service',
        'cleanliness' => 'Cleanliness',
        'friendliness' => 'Friendliness',
    ];

    /**
     * Average of all rating fields for this feedback entry.
     */
    public function getAverageRatingAttribute(): float
    {
        $ratings = array_map(fn (string $field) => (int) $this->{$field}, array_keys(self::RATING_FIELDS));

        return round(array_sum($ratings) / count($ratings), 2);
    }
}
