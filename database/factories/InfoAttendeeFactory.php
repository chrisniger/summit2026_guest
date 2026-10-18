<?php

namespace Database\Factories;

use App\Models\InfoAttendee;
use Illuminate\Database\Eloquent\Factories\Factory;

class InfoAttendeeFactory extends Factory
{
    protected $model = InfoAttendee::class;

    public function definition(): array
    {
        return ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'phone_raw' => '08000000000', 'phone_normalized' => '+2348000000000', 'registration_source' => 'onsite'];
    }
}
