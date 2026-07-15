<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value, cached to avoid a query on every page load.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $value = Cache::rememberForever('app-setting:'.$key, function () use ($key) {
                return static::query()->where('key', $key)->value('value');
            });
        } catch (\Throwable) {
            // Table may not exist yet (fresh environment before migrations).
            return $default;
        }

        return $value ?? $default;
    }

    /**
     * Store a setting value; null removes the override.
     */
    public static function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            static::query()->where('key', $key)->delete();
        } else {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('app-setting:'.$key);
    }
}
