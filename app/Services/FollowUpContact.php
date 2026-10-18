<?php

namespace App\Services;

use App\Http\Requests\FollowUpActivityRequest;
use App\Models\FollowUpActivity;
use App\Models\FollowUpAssignment;
use App\Models\FollowUpStatusHistory;
use App\Models\GuestRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FollowUpContact
{
    public function record(User $actor, GuestRecord $guest, array $input): FollowUpActivity
    {
        Gate::forUser($actor)->authorize('contact', $guest);
        $data = Validator::make($input, FollowUpActivityRequest::fields())->validate();
        $instant = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['attempted_at'], 'Africa/Lagos')->utc();
        if ($instant->isFuture() || $instant->year < 2000) {
            throw ValidationException::withMessages(['attempted_at' => 'Enter the actual attempt time in WAT, between year 2000 and now.']);
        }

        return DB::transaction(function () use ($actor, $guest, $data, $instant): FollowUpActivity {
            $actor = User::findOrFail($actor->id);
            $guest = GuestRecord::whereKey($guest->id)->lockForUpdate()->firstOrFail();
            $visible = GuestRecord::visibleTo($actor)->whereKey($guest->id)->lockForUpdate()->first();
            abort_unless($visible && ! $guest->archived_at && $actor->hasPermission('guest.contact'), 403, 'You can no longer update this guest.');
            $assignment = FollowUpAssignment::where('guest_record_id', $guest->id)->whereNull('unassigned_at')->lockForUpdate()->first();
            if (! $actor->hasPermission('guest.assign')) {
                abort_unless($assignment && $assignment->officer_user_id === $actor->id, 403, 'This guest is no longer assigned to you.');
            }
            abort_unless($assignment?->id === (isset($data['assignment_id']) ? (int) $data['assignment_id'] : null),
                409, 'Assignment changed. Reload the guest before recording an attempt.');
            $fingerprint = hash('sha256', 'guest-contact:'.$actor->id.':'.$data['submission_id']);
            $replayed = FollowUpActivity::where('source_fingerprint', $fingerprint)->lockForUpdate()->first();
            if ($replayed) {
                abort_unless($replayed->guest_record_id === $guest->id && $replayed->assignment_id === $assignment?->id, 409, 'This submission was already used for another encounter.');

                return $replayed;
            }
            abort_unless($guest->version === (int) $data['version'], 409, 'This guest changed. Reload before recording another attempt.');
            $previous = $guest->current_status;
            $latest = FollowUpActivity::where('guest_record_id', $guest->id)->where('outcome', '!=', 'NOTE_ONLY')
                ->orderByDesc('attempted_at_utc')->orderByDesc('id')->lockForUpdate()->first();
            $applies = $data['outcome'] !== 'NOTE_ONLY' && (! $latest || $instant->greaterThanOrEqualTo($latest->attempted_at_utc));
            $next = $applies ? $data['outcome'] : $previous;
            $activity = new FollowUpActivity(['guest_record_id' => $guest->id, 'assignment_id' => $assignment?->id,
                'actor_user_id' => $actor->id, 'method' => $data['method'], 'attempted_at_utc' => $instant,
                'outcome' => $data['outcome'], 'comment' => $data['comment'], 'previous_status' => $previous, 'new_status' => $next]);
            $activity->forceFill(['source_fingerprint' => $fingerprint])->save();
            if ($data['outcome'] !== 'NOTE_ONLY') {
                FollowUpStatusHistory::create(['guest_record_id' => $guest->id, 'from_status' => $previous, 'to_status' => $next,
                    'changed_by_user_id' => $actor->id, 'changed_at_utc' => now(), 'activity_id' => $activity->id,
                    'reason' => $applies ? 'Contact outcome recorded.' : 'Earlier attempt recorded; later outcome retained.']);
            }
            $guest->forceFill(['current_status' => $next, 'version' => $guest->version + 1])->save();
            app(Audit::class)->record('follow_up', 'contact.recorded', $activity, $actor, ['current_status' => $previous],
                ['current_status' => $next, 'method' => $data['method'], 'outcome' => $data['outcome']]);

            return $activity;
        }, 3);
    }
}
