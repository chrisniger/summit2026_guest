<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestImportRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['raw_json' => 'array', 'normalized_json' => 'array', 'error_json' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GuestImportBatch::class, 'batch_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(GuestRecord::class, 'guest_record_id');
    }
}
