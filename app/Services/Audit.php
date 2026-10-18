<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public function record(string $domain, string $action, Model $subject, ?User $actor, array $before = [], array $after = []): AuditLog
    {
        $allowed = ['role', 'is_active', 'enabled', 'starts_at_utc', 'ends_at_utc', 'attendance_locked_at', 'conference_session_id', 'current_status', 'officer_user_id', 'outcome', 'method', 'source', 'reason', 'voided_at', 'void_reason', 'classification', 'rows_total', 'rows_created', 'rows_matched', 'rows_skipped', 'changed_fields'];

        return AuditLog::create([
            'actor_user_id' => $actor?->id, 'domain' => $domain, 'action' => $action,
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'before_json' => array_intersect_key($before, array_flip($allowed)),
            'after_json' => array_intersect_key($after, array_flip($allowed)),
            'source_ip' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr(request()->userAgent() ?? '', 0, 500),
            'occurred_at' => now(),
        ]);
    }
}
