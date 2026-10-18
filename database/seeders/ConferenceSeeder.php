<?php

namespace Database\Seeders;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use Illuminate\Database\Seeder;

class ConferenceSeeder extends Seeder
{
    public function run(): void
    {
        $event = ConferenceEvent::firstOrCreate(['year' => 2026], [
            'name' => 'SUMMIT 2026', 'starts_on' => '2026-10-27', 'ends_on' => '2026-11-01', 'timezone' => 'Africa/Lagos',
        ]);
        $sessions = [
            ['2026-10-27-G', 1, '2026-10-27', 'General', 'Day 1'],
            ['2026-10-28-G', 2, '2026-10-28', 'General', 'Day 2'],
            ['2026-10-29-G', 3, '2026-10-29', 'General', 'Day 3'],
            ['2026-10-30-G', 4, '2026-10-30', 'General', 'Day 4'],
            ['2026-10-31-AM', 5, '2026-10-31', 'Morning', 'Day 5 Morning'],
            ['2026-10-31-PM', 5, '2026-10-31', 'Evening', 'Day 5 Evening'],
            ['2026-11-01-AM', 6, '2026-11-01', 'Morning', 'Day 6 Morning'],
            ['2026-11-01-PM', 6, '2026-11-01', 'Evening', 'Day 6 Evening'],
        ];
        foreach ($sessions as [$code, $day, $date, $name, $label]) {
            ConferenceSession::firstOrCreate(['code' => $code], [
                'conference_event_id' => $event->id, 'day_number' => $day, 'date_local' => $date, 'name' => $name, 'label' => $label,
            ]);
        }
    }
}
