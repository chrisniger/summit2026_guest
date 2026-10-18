<x-layout :title="$attendee ? 'Edit attendee' : 'Register attendee'">
<div class="page-heading"><div><span class="eyebrow">INFO COM · REGISTRATION</span><h1>{{ $attendee ? 'Edit attendee' : 'Register attendee' }}</h1><p>{{ $attendee ? 'Correct participant details while preserving attendance history.' : 'Capture an attendee. Active-session attendance is handled automatically for in-person registrations.' }}</p></div><a href="{{ route('info.check-in') }}" class="button secondary">Back to check-in</a></div>
<section class="panel edit-panel" style="margin-top:0"><form method="POST" action="{{ $attendee ? route('info.attendees.update',$attendee) : route('info.attendees.store') }}" class="form-stack">@csrf @if($attendee) @method('PATCH') @endif
<input type="hidden" name="submission_id" value="{{ old('submission_id',$submissionId) }}">
<div class="form-grid">
@foreach(['first_name'=>'First name','last_name'=>'Surname','phone_raw'=>'Phone number','email'=>'Email address'] as $field=>$label)
<div><label for="{{ $field }}">{{ $label }}{{ $field!=='email' ? ' *':'' }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$attendee?->$field) }}" type="{{ $field==='email'?'email':($field==='phone_raw'?'tel':'text') }}" @required($field!=='email') maxlength="{{ $field==='phone_raw'?50:255 }}" autocomplete="{{ match($field){'first_name'=>'given-name','last_name'=>'family-name','phone_raw'=>'tel',default=>'email'} }}"></div>
@endforeach
@foreach(['member'=>'Summit Bible Church member?','first_time'=>'First conference attendance?','consent'=>'Consent to registration data collection?'] as $field=>$label)
<div><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}"><option value="">Not answered</option><option value="1" @selected((string)old($field,$attendee?->$field===null?'':(int)$attendee->$field)==='1')>Yes</option><option value="0" @selected((string)old($field,$attendee?->$field===null?'':(int)$attendee->$field)==='0')>No</option></select></div>
@endforeach
<div><label for="attendance_preference">Attendance preference</label><select id="attendance_preference" name="attendance_preference"><option value="">Not specified</option>@foreach(['In Person','Virtual'] as $option)<option @selected(old('attendance_preference',$attendee?->attendance_preference)===$option)>{{ $option }}</option>@endforeach</select><small>Virtual preference never automatically counts as physical presence.</small></div>
<div><label for="heard_about">How did you hear about the conference?</label><input id="heard_about" name="heard_about" list="referral-options" value="{{ old('heard_about',$attendee?->heard_about) }}" maxlength="255"><datalist id="referral-options">@foreach(config('summit.referral_options') as $option)<option value="{{ $option }}">@endforeach</datalist></div>
</div>
<details class="additional-details"><summary>Additional registration details (optional)</summary><div class="form-grid">
@foreach(['commits_to_invite'=>'Commits to invite?','bus_shuttle'=>'Bus shuttle required?'] as $field=>$label)
<div><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}"><option value="">Not answered</option><option value="1" @selected((string)old($field,$attendee?->$field===null?'':(int)$attendee->$field)==='1')>Yes</option><option value="0" @selected((string)old($field,$attendee?->$field===null?'':(int)$attendee->$field)==='0')>No</option></select></div>
@endforeach
@foreach(['summit_centre'=>'Summit centre','location'=>'Location','abuja_area'=>'Abuja area','bus_route'=>'Bus route'] as $field=>$label)
<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$attendee?->$field) }}" maxlength="255"></div>
@endforeach
<div><label for="invite_count">Number of people to invite</label><input id="invite_count" name="invite_count" type="number" min="0" max="100000" value="{{ old('invite_count',$attendee?->invite_count) }}"></div>
</div>
<div><label for="feedback">Feedback / registration notes</label><textarea id="feedback" name="feedback" rows="3" maxlength="5000">{{ old('feedback',$attendee?->feedback) }}</textarea></div>
</details>
<div class="button-row"><button class="button primary">{{ $attendee?'Save changes':'Save registration' }} <x-icon name="arrow" /></button></div>
</form></section>
</x-layout>

