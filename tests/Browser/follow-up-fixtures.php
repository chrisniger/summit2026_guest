<?php

use App\Models\AuditLog;
use App\Models\ConferenceSession;
use App\Models\FollowUpActivity;
use App\Models\FollowUpAssignment;
use App\Models\FollowUpStatusHistory;
use App\Models\GuestRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\FollowUpAssignments;
use App\Services\FollowUpContact;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'summit_2026_test') {
    throw new RuntimeException('Follow-up fixtures require the disposable summit_2026_test database.');
}
$prefix = getenv('SUMMIT_BROWSER_PREFIX');
if (! $prefix || ! preg_match('/^browser_[a-f0-9]{12}$/', $prefix)) {
    throw new RuntimeException('Unique synthetic fixture prefix required.');
}
$action = $argv[1] ?? '';
$user = fn (string $role): User => User::where('email', $prefix.'_'.$role.'@example.test')->firstOrFail();
$guest = fn (string $label): GuestRecord => GuestRecord::where('full_name', $label.' '.$prefix)->firstOrFail();
$service = app(FollowUpAssignments::class);
$selection = fn (GuestRecord $record, User $officer): array => $service->preview($user('follow_up_admin'),
    ['guest_ids' => [$record->id], 'officer_user_id' => $officer->id, 'action' => 'reassign', 'reason' => 'Synthetic reviewed allocation']);
$input = fn (GuestRecord $record): array => ['version' => $record->fresh()->version,
    'assignment_id' => $record->activeAssignment()->first()?->id, 'submission_id' => getenv('SUMMIT_BROWSER_SUBMISSION'),
    'outcome' => 'UNREACHABLE', 'method' => 'call', 'attempted_at' => now()->setTimezone('Africa/Lagos')->format('Y-m-d\TH:i'),
    'failed_attempt_confirmed' => 1, 'comment' => 'Synthetic actual attempt'];
if ($action === 'setup') {
    Artisan::call('db:seed', ['--force' => true]);
    foreach ([...array_combine(array_keys(config('summit.roles')), array_keys(config('summit.roles'))), 'other_officer' => 'officer'] as $suffix => $role) {
        $record = User::create(['name' => 'Browser '.$suffix, 'email' => $prefix.'_'.$suffix.'@example.test', 'password' => getenv('SUMMIT_BROWSER_PASSWORD')]);
        $record->roles()->attach(Role::where('name', $role)->firstOrFail());
    }
    foreach (['Bulk One', 'Bulk Two', 'Private', 'Race Contact', 'Race Reassignment', 'Race Assignment'] as $index => $label) {
        GuestRecord::create(['full_name' => $label.' '.$prefix, 'phone_raw' => '0805555555'.$index,
            'phone_normalized' => '+234805555555'.$index, 'created_by_user_id' => $user('follow_up_admin')->id,
            'conference_session_id' => ConferenceSession::currentConference()->firstOrFail()->id]);
    }
    foreach (['Race Contact', 'Race Reassignment'] as $label) {
        $service->commit($user('follow_up_admin'), $selection($guest($label), $user('officer'))['token']);
    }
    $service->commit($user('follow_up_admin'), $selection($guest('Private'), $user('other_officer'))['token']);
    echo json_encode(['prefix' => $prefix, 'officer_id' => $user('officer')->id, 'other_officer_id' => $user('other_officer')->id,
        'bulk_one_id' => $guest('Bulk One')->id, 'bulk_two_id' => $guest('Bulk Two')->id,
        'contact_id' => $guest('Race Contact')->id, 'contact_input' => $input($guest('Race Contact')),
        'reassignment_id' => $guest('Race Reassignment')->id, 'reassignment_input' => $input($guest('Race Reassignment')),
        'reassignment_token' => $selection($guest('Race Reassignment'), $user('other_officer'))['token'],
        'bulk_tokens' => [$selection($guest('Race Assignment'), $user('officer'))['token'],
            $selection($guest('Race Assignment'), $user('other_officer'))['token']]]);
} elseif ($action === 'cleanup') {
    $users = User::where('email', 'like', $prefix.'_%@example.test')->get();
    $ids = $users->modelKeys();
    $guests = GuestRecord::whereIn('created_by_user_id', $ids)->pluck('id');
    FollowUpStatusHistory::whereIn('guest_record_id', $guests)->delete();
    FollowUpActivity::whereIn('guest_record_id', $guests)->delete();
    FollowUpAssignment::whereIn('guest_record_id', $guests)->delete();
    GuestRecord::whereIn('id', $guests)->delete();
    AuditLog::whereIn('actor_user_id', $ids)->toBase()->delete();
    DB::table('sessions')->whereIn('user_id', $ids)->delete();
    foreach ($users as $record) {
        $record->delete();
    }
    echo json_encode(['cleaned_users' => count($ids)]);
} elseif ($action === 'facts') {
    echo json_encode(GuestRecord::where('full_name', 'like', '%'.$prefix)->withCount('activities', 'assignments')
        ->with('activeAssignment')->get()->mapWithKeys(fn ($record) => [$record->full_name => [
            'status' => $record->current_status, 'activities' => $record->activities_count, 'assignments' => $record->assignments_count,
            'active_officer' => $record->activeAssignment?->officer_user_id]])->all());
} else {
    $barrier = (float) getenv('SUMMIT_BROWSER_BARRIER');
    while (microtime(true) < $barrier) {
        usleep(10000);
    }
    try {
        if ($action === 'contact') {
            $data = json_decode(getenv('SUMMIT_BROWSER_CONTACT_INPUT'), true, 512, JSON_THROW_ON_ERROR);
            $activity = app(FollowUpContact::class)->record($user('officer'), GuestRecord::findOrFail(getenv('SUMMIT_BROWSER_GUEST_ID')), $data);
            echo json_encode(['status' => 200, 'activity_id' => $activity->id]);
        } elseif ($action === 'assign') {
            echo json_encode(['status' => 200, 'result' => $service->commit($user('follow_up_admin'), getenv('SUMMIT_BROWSER_ASSIGNMENT_TOKEN'))]);
        } else {
            throw new RuntimeException('Unknown fixture action.');
        }
    } catch (AuthorizationException|HttpExceptionInterface $error) {
        echo json_encode(['status' => $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 403]);
    }
}
