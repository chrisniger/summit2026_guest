<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_user_id', 'domain', 'action', 'subject_type', 'subject_id', 'before_json', 'after_json', 'source_ip', 'user_agent', 'occurred_at'];

    protected function casts(): array
    {
        return ['before_json' => 'array', 'after_json' => 'array', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit records are append-only.'));
    }
}
