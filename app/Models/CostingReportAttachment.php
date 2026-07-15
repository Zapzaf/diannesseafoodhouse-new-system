<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CostingReportAttachment extends Model
{
    protected $fillable = [
        'costing_report_id',
        'path',
        'original_name',
        'size',
    ];

    public function costingReport(): BelongsTo
    {
        return $this->belongsTo(CostingReport::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format(max(1, $bytes) / 1024, 0).' KB';
    }
}
