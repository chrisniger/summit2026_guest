<?php

namespace App\Services;

use App\Models\FollowUpAssignment;
use App\Models\GuestRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FollowUpAssignments
{
    public function officers(): Builder
    {
        return User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('name', 'officer'))->orderBy('name');
    }

    public function preview(User $actor, array $input): array
    {
        Gate::forUser($actor)->authorize('create', FollowUpAssignment::class);
        $data = Validator::make($input, [
            'guest_ids' => 'required|array|min:1|max:200', 'guest_ids.*' => 'required|integer|distinct',
            'action' => 'required|in:assign,reassign,unassign', 'officer_user_id' => 'nullable|required_unless:action,unassign|integer',
            'reason' => 'nullable|string|max:1000',
        ])->validate();
        $data['reason'] ??= null;
        $ids = array_map('intval', $data['guest_ids']);
        sort($ids);
        $guests = GuestRecord::visibleTo($actor)->whereIn('id', $ids)->whereNull('archived_at')->with('activeAssignment.officer', 'session')->orderBy('id')->get();
        abort_unless($guests->count() === count($ids), 403, 'Some selected guests are unavailable to you.');
        $officer = $data['action'] === 'unassign' ? null : $this->officers()->find($data['officer_user_id']);
        if ($data['action'] !== 'unassign' && ! $officer) {
            throw ValidationException::withMessages(['officer_user_id' => 'Select an active staff member with the Follow-Up Officer role.']);
        }
        if ($data['action'] === 'assign' && $guests->contains(fn ($guest) => $guest->activeAssignment !== null)) {
            throw ValidationException::withMessages(['action' => 'Assign accepts unassigned guests only. Choose Reassign to explicitly replace existing ownership.']);
        }
        $payload = ['actor_id' => $actor->id, 'expires_at' => now()->addMinutes(15)->timestamp,
            'action' => $data['action'], 'officer_id' => $officer?->id, 'reason' => $data['reason'],
            'guests' => $guests->map(fn ($guest) => ['id' => $guest->id, 'version' => $guest->version, 'assignment_id' => $guest->activeAssignment?->id])->all()];

        return ['guests' => $guests, 'officer' => $officer, 'action' => $data['action'], 'reason' => $data['reason'],
            'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR))];
    }

    public function commit(User $actor, string $token): array
    {
        Gate::forUser($actor)->authorize('create', FollowUpAssignment::class);
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['confirmation' => 'Invalid confirmation. Review your selection again.']);
        }
        abort_unless(($payload['actor_id'] ?? null) === $actor->id && ($payload['expires_at'] ?? 0) >= now()->timestamp, 409, 'Confirmation expired or belongs to another account. Review the selection again.');

        return DB::transaction(function () use ($actor, $payload): array {
            $actor = User::findOrFail($actor->id);
            Gate::forUser($actor)->authorize('create', FollowUpAssignment::class);
            $guests = new Collection;
            foreach ($payload['guests'] as $snapshot) {
                $guest = GuestRecord::whereKey($snapshot['id'])->lockForUpdate()->firstOrFail();
                $visible = GuestRecord::visibleTo($actor)->whereKey($guest->id)->lockForUpdate()->first();
                abort_unless($visible && ! $guest->archived_at, 403, 'A selected guest is no longer available.');
                $assignment = FollowUpAssignment::where('guest_record_id', $guest->id)->whereNull('unassigned_at')->lockForUpdate()->first();
                abort_unless($guest->version === $snapshot['version'] && $assignment?->id === $snapshot['assignment_id'],
                    409, 'A selected guest or assignment changed. No changes were saved. Review your selection again.');
                $guest->setRelation('activeAssignment', $assignment);
                $guests->push($guest);
            }
            $officer = null;
            if ($payload['action'] !== 'unassign') {
                $officer = $this->officers()->whereKey($payload['officer_id'])->lockForUpdate()->first();
                if (! $officer) {
                    throw ValidationException::withMessages(['officer_user_id' => 'The selected officer is no longer active or eligible. Review the selection.']);
                }
            }
            $result = ['assigned' => 0, 'reassigned' => 0, 'unassigned' => 0, 'unchanged' => 0];
            foreach ($guests as $guest) {
                $current = $guest->activeAssignment;
                if (($payload['action'] === 'unassign' && ! $current) || ($current && $current->officer_user_id === $officer?->id)) {
                    $result['unchanged']++;

                    continue;
                }
                $instant = now();
                if ($current) {
                    $current->update(['unassigned_at' => $instant, 'closure_reason' => $payload['reason']]);
                    app(Audit::class)->record('follow_up', 'assignment.closed', $current, $actor, ['officer_user_id' => $current->officer_user_id], ['reason' => $payload['reason']]);
                }
                if ($officer) {
                    $assignment = FollowUpAssignment::create(['guest_record_id' => $guest->id, 'officer_user_id' => $officer->id,
                        'assigned_by_user_id' => $actor->id, 'assigned_at' => $instant, 'reason' => $payload['reason']]);
                    app(Audit::class)->record('follow_up', $current ? 'assignment.reassigned' : 'assignment.created', $assignment, $actor,
                        ['officer_user_id' => $current?->officer_user_id], ['officer_user_id' => $officer->id, 'reason' => $payload['reason']]);
                    $result[$current ? 'reassigned' : 'assigned']++;
                } else {
                    $result['unassigned']++;
                }
                $guest->forceFill(['version' => $guest->version + 1])->save();
            }

            return $result;
        }, 3);
    }
}
