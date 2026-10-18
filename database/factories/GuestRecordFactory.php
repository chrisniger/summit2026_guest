<?php

namespace Database\Factories;

use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestRecordFactory extends Factory
{
    protected $model = GuestRecord::class;

    public function definition(): array
    {
        return ['full_name' => fake()->name(), 'phone_raw' => '08000000000', 'phone_normalized' => '+2348000000000', 'conference_session_id' => ConferenceSession::firstOrFail()->id];
    }
}
