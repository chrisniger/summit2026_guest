<?php

namespace App\Services;

use App\Models\InfoAttendee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class InfoAttendeeMatcher
{
    /** @param array<string, mixed> $data */
    public function candidates(array $data): Builder
    {
        $phone = PhoneNumber::normalize($data['phone_raw'] ?? null);
        $firstName = $this->normalizeName($data['first_name'] ?? null);
        $lastName = $this->normalizeName($data['last_name'] ?? null);

        return InfoAttendee::query()->where(function (Builder $query) use ($phone, $firstName, $lastName): void {
            $hasCandidateConstraint = false;

            if ($phone !== null) {
                $query->where('phone_normalized', $phone);
                $hasCandidateConstraint = true;
            }

            if ($firstName !== '' && $lastName !== '') {
                $method = $hasCandidateConstraint ? 'orWhere' : 'where';
                $query->{$method}(function (Builder $nameQuery) use ($firstName, $lastName): void {
                    $nameQuery->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
                        ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName]);
                });
                $hasCandidateConstraint = true;
            }

            if (! $hasCandidateConstraint) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function findExactMatch(array $data, ?int $exceptId = null): ?InfoAttendee
    {
        return $this->candidates($data)
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $exceptId))
            ->get()
            ->first(fn (InfoAttendee $attendee): bool => $this->matches($attendee, $data));
    }

    /** @param array<string, mixed> $data */
    public function matches(InfoAttendee $attendee, array $data): bool
    {
        return $attendee->phone_normalized === PhoneNumber::normalize($data['phone_raw'] ?? null)
            && $this->normalizeName($attendee->first_name) === $this->normalizeName($data['first_name'] ?? null)
            && $this->normalizeName($attendee->last_name) === $this->normalizeName($data['last_name'] ?? null);
    }

    public function normalizeName(?string $name): string
    {
        return Str::of((string) $name)->squish()->lower()->toString();
    }

    /** @param array<string, mixed> $data */
    public function identityKey(array $data): string
    {
        return implode("\0", [
            (string) PhoneNumber::normalize($data['phone_raw'] ?? null),
            $this->normalizeName($data['first_name'] ?? null),
            $this->normalizeName($data['last_name'] ?? null),
        ]);
    }
}
