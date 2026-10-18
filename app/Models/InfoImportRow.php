<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfoImportRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['raw_json' => 'array', 'normalized_json' => 'array', 'error_json' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InfoImportBatch::class, 'batch_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(InfoAttendee::class, 'info_attendee_id');
    }
}
