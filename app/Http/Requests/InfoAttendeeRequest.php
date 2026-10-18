<?php

namespace App\Http\Requests;

use App\Services\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InfoAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('info.attendee.create');
    }

    public static function fields(): array
    {
        $rules = [
            'first_name' => 'required|string|max:255', 'last_name' => 'required|string|max:255',
            'phone_raw' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail) {
                if (! PhoneNumber::normalize($value)) {
                    $fail('Enter a valid phone number containing 7–15 digits.');
                }
            }],
            'email' => 'nullable|email|max:255', 'invite_count' => 'nullable|integer|min:0|max:100000',
            'feedback' => 'nullable|string|max:5000',
            'attendance_preference' => ['nullable', Rule::in(['In Person', 'Virtual'])],
        ];
        foreach (['member', 'first_time', 'consent', 'commits_to_invite', 'bus_shuttle'] as $field) {
            $rules[$field] = 'nullable|boolean';
        }
        foreach (['heard_about', 'summit_centre', 'location', 'abuja_area', 'bus_route'] as $field) {
            $rules[$field] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function rules(): array
    {
        return [...self::fields(), 'submission_id' => 'required|uuid'];
    }
}
