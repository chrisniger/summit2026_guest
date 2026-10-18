<?php

use App\Models\AuditLog;
use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Models\InfoImportBatch;
use App\Models\InfoImportRow;
use App\Models\InfoRegistrationEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\InfoAttendance;
use App\Services\PhoneNumber;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Browser-only fixtures, hard restricted to the disposable test database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'summit_2026_test') {
    throw new RuntimeException('Browser fixtures require summit_2026_test in testing mode.');
}
$action = $argv[1] ?? '';
$prefix = getenv('SUMMIT_BROWSER_PREFIX');
if (! $prefix || ! preg_match('/^browser_[a-f0-9]{12}$/', $prefix)) {
    throw new RuntimeException('A unique browser fixture prefix is required.');
}
if ($action === 'setup') {
    Artisan::call('db:seed', ['--force' => true]);
    foreach (array_keys(config('summit.roles')) as $role) {
        $user = User::create(['name' => 'Browser '.config('summit.roles.'.$role), 'email' => $prefix.'_'.$role.'@example.test', 'password' => getenv('SUMMIT_BROWSER_PASSWORD')]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
    }
    if (getenv('SUMMIT_BROWSER_PHASE2') === '1') {
        ConferenceSession::create(['conference_event_id' => ConferenceEvent::where('year', 2026)->firstOrFail()->id,
            'code' => $prefix, 'day_number' => 1, 'date_local' => now()->setTimezone('Africa/Lagos')->format('Y-m-d'),
            'name' => 'General', 'label' => 'Browser test session', 'enabled' => true,
            'starts_at_utc' => now()->subHour(), 'ends_at_utc' => now()->addHour()]);
        $actor = User::where('email', $prefix.'_info_com@example.test')->firstOrFail();
        foreach (['Pending' => '08087654321', 'Race' => '08087654322'] as $name => $phone) {
            InfoAttendee::create(['first_name' => $name, 'last_name' => $prefix, 'phone_raw' => $phone,
                'phone_normalized' => PhoneNumber::normalize($phone), 'created_by_user_id' => $actor->id,
                'registration_original_at_utc' => now()->subMinutes(5), 'registration_source' => 'onsite']);
        }
    }
    echo 'Test-only fixtures ready.';
} elseif ($action === 'cleanup') {
    $users = User::where('email', 'like', $prefix.'_%@example.test')->get();
    $ids = $users->pluck('id');
    $attendees = InfoAttendee::whereIn('created_by_user_id', $ids)->pluck('id');
    InfoAttendanceRecord::whereIn('info_attendee_id', $attendees)->delete();
    InfoRegistrationEvent::whereIn('info_attendee_id', $attendees)->delete();
    $batches = InfoImportBatch::whereIn('uploaded_by_user_id', $ids)->get();
    InfoImportRow::whereIn('batch_id', $batches->pluck('id'))->delete();
    InfoAttendee::whereIn('id', $attendees)->forceDelete();
    foreach ($batches as $batch) {
        Storage::disk('local')->delete($batch->private_path);
        $batch->delete();
    }
    ConferenceSession::where('code', $prefix)->delete();
    foreach ($users as $user) {
        AuditLog::where('actor_user_id', $user->id)->toBase()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();
    }
    echo 'Test-only staff fixtures removed.';
} elseif ($action === 'race') {
    $actor = User::where('email', $prefix.'_info_com@example.test')->firstOrFail();
    $attendee = InfoAttendee::where('first_name', 'Race')->where('last_name', $prefix)->firstOrFail();
    $session = ConferenceSession::where('code', $prefix)->firstOrFail();
    $result = app(InfoAttendance::class)->manual($actor, $attendee->id, $session->id);
    echo json_encode(['already_present' => $result['already_present'], 'record_id' => $result['record']->id]);
}
