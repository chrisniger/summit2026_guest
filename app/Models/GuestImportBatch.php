<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestImportBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['summary_json' => 'array', 'mapping_json' => 'array'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(GuestImportRow::class, 'batch_id');
    }
}
