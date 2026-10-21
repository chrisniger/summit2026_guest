<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FollowUpActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contact', $this->route('guest'));
    }

    public static function fields(): array
    {
        return [
            'submission_id' => 'required|uuid', 'assignment_id' => 'nullable|integer|min:1', 'version' => 'required|integer|min:1',
            'method' => 'required|in:call,sms,whatsapp,visit,other', 'outcome' => 'required|in:CONTACTED,UNREACHABLE,WRONG_NUMBER,NOTE_ONLY',
            'attempted_at' => 'required|date_format:Y-m-d\TH:i', 'comment' => 'nullable|string|max:5000',
            'two_way_confirmed' => 'required_if:outcome,CONTACTED|accepted_if:outcome,CONTACTED',
            'failed_attempt_confirmed' => 'required_if:outcome,UNREACHABLE|accepted_if:outcome,UNREACHABLE',
            'wrong_number_confirmed' => 'required_if:outcome,WRONG_NUMBER|accepted_if:outcome,WRONG_NUMBER',
            'return_to' => 'nullable|in:list',
        ];
    }

    public function rules(): array
    {
        return self::fields();
    }
}
