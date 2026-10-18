<?php

use App\Models\ConferenceSession;
use App\Models\FollowUpActivity;
use App\Models\FollowUpStatusHistory;
use App\Models\GuestImportBatch;
use App\Models\GuestRecord;
use App\Models\InfoAttendee;
use App\Models\InfoImportBatch;
use App\Models\User;
use App\Services\FollowUpAssignments;
use App\Services\FollowUpContact;
use App\Services\FollowUpReports;
use App\Services\GuestImport;
use App\Services\GuestWorkbook;
use App\Services\InfoAttendance;
use App\Services\InfoCsvImport;
use App\Services\InfoReports;
use App\Services\LocalBackup;
use App\Services\Operations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$releaseAction = $argv[1] ?? '';
if ($releaseAction === 'setup' || $releaseAction === 'cleanup') {
    if ($releaseAction === 'cleanup') {
        $argv[1] = 'bootstrap';
        require __DIR__.'/fixtures.php';
        $userIds = User::where('email', 'like', $prefix.'_%@example.test')->pluck('id');
        $guestIds = GuestRecord::whereIn('created_by_user_id', $userIds)->pluck('id');
        FollowUpStatusHistory::whereIn('guest_record_id', $guestIds)->delete();
        FollowUpActivity::whereIn('guest_record_id', $guestIds)->delete();
        $argv[1] = 'cleanup';
    } else {
        require __DIR__.'/fixtures.php';
        exit;
    }
} else {
    $argv[1] = 'bootstrap';
    require __DIR__.'/fixtures.php';
}
$actor = User::where('email', $prefix.'_super_admin@example.test')->firstOrFail();
$officer = User::where('email', $prefix.'_officer@example.test')->firstOrFail();
$session = ConferenceSession::where('code', $prefix)->firstOrFail();
if ($releaseAction === 'cleanup') {
    $argv[1] = 'cleanup';
    require __DIR__.'/fixtures.php';
    exit;
} elseif ($releaseAction === 'volume') {
    $csv = function (array $headers, array $rows, string $name): UploadedFile {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $file = UploadedFile::fake()->createWithContent($name, stream_get_contents($stream));
        fclose($stream);

        return $file;
    };
    $rows = [];
    for ($i = 0; $i < 1749; $i++) {
        $data = array_fill_keys(InfoCsvImport::HEADERS, '');
        $data = [...$data, 'Created' => '2026-01-01T00:00:00Z', 'First Name' => 'Volume'.$i, 'Surname' => $prefix,
            'Phone' => '+447'.str_pad((string) $i, 9, '0', STR_PAD_LEFT), 'Attendance' => 'In Person'];
        $rows[] = array_values($data);
    }
    $info = app(InfoCsvImport::class)->preview($actor, $csv(InfoCsvImport::HEADERS, $rows, 'volume-info.csv'));
    app(InfoCsvImport::class)->commit($actor, $info);
    $rows = [];
    for ($i = 0; $i < 1000; $i++) {
        $rows[] = ['2026-10-27', 'General', 'Volume Guest '.$i.' '.$prefix, '+447'.str_pad((string) $i, 9, '0', STR_PAD_LEFT), '', '', '', 'Synthetic only'];
    }
    $guest = app(GuestImport::class)->upload($actor, $csv(GuestWorkbook::CSV_HEADERS, $rows, 'volume-guests.csv'), $session->conference_event_id);
    $map = [];
    foreach ($guest->summary_json['groups'] as $key => $group) {
        $map[$key] = $group['suggested_session'];
    }
    app(GuestImport::class)->preview($actor, $guest, ['CSV'], $map, 'Africa/Lagos');
    app(GuestImport::class)->commit($actor, $guest);
    $info->timestamps = false;
    $info->updated_at = now()->subMinutes(11);
    $info->save();
    $guest->timestamps = false;
    $guest->updated_at = now()->subMinutes(11);
    $guest->save();
    app(Operations::class)->maintain('recover', 0, true);
    app(InfoCsvImport::class)->commit($actor, $info->fresh());
    app(GuestImport::class)->commit($actor, $guest->fresh());
    echo json_encode(['info_batch' => $info->id, 'guest_batch' => $guest->id, 'recovered_batches' => 2]);
} elseif ($releaseAction === 'assign') {
    $guests = GuestRecord::where('created_by_user_id', $actor->id)->orderBy('id')->limit(200)->get();
    $assignments = app(FollowUpAssignments::class);
    $assignments->commit($actor, $assignments->preview($actor, ['guest_ids' => $guests->modelKeys(), 'officer_user_id' => $officer->id,
        'action' => 'assign', 'reason' => 'Synthetic volume allocation'])['token']);
    $guest = $guests->first()->fresh();
    echo json_encode(['guest_id' => $guest->id, 'input' => ['submission_id' => (string) Str::uuid(), 'version' => $guest->version,
        'assignment_id' => $guest->activeAssignment()->first()->id, 'method' => 'call', 'outcome' => 'CONTACTED',
        'attempted_at' => now()->setTimezone('Africa/Lagos')->format('Y-m-d\TH:i'), 'two_way_confirmed' => 1, 'comment' => 'Synthetic confirmed conversation']]);
} elseif ($releaseAction === 'check-in') {
    $staff = User::where('email', $prefix.'_info_com@example.test')->firstOrFail();
    $attendee = InfoAttendee::where('first_name', 'Volume0')->where('last_name', $prefix)->firstOrFail();
    $result = app(InfoAttendance::class)->manual($staff, $attendee->id, $session->id);
    echo json_encode(['record_id' => $result['record']->id, 'already_present' => $result['already_present']]);
} elseif ($releaseAction === 'contact') {
    $record = app(FollowUpContact::class)->record($officer, GuestRecord::findOrFail(getenv('SUMMIT_RELEASE_GUEST_ID')),
        json_decode(getenv('SUMMIT_RELEASE_INPUT'), true, 512, JSON_THROW_ON_ERROR));
    echo json_encode(['activity_id' => $record->id]);
} elseif ($releaseAction === 'facts') {
    $info = app(InfoReports::class)->build(['session_id' => $session->id]);
    $summary = app(FollowUpReports::class)->summary($actor, []);
    echo json_encode(['info_created' => InfoImportBatch::where('uploaded_by_user_id', $actor->id)->sum('rows_created'),
        'guest_created' => GuestImportBatch::where('uploaded_by_user_id', $actor->id)->sum('rows_created'),
        'guest_summary' => $summary, 'info_present' => DB::table('info_attendance_records')->count(),
        'completed_info' => InfoImportBatch::where('uploaded_by_user_id', $actor->id)->where('status', 'completed')->count(),
        'completed_guest' => GuestImportBatch::where('uploaded_by_user_id', $actor->id)->where('status', 'completed')->count()]);
} elseif ($releaseAction === 'backup') {
    $backup = app(LocalBackup::class);
    $password = base64_encode(random_bytes(48));
    Artisan::call('down');
    try {
        $archive = $backup->create($password, getenv('SUMMIT_MYSQLDUMP'));
    } finally {
        Artisan::call('up');
    }
    $result = $backup->verify($archive, $password, getenv('SUMMIT_MYSQL'));
    $rejected = false;
    try {
        $backup->verify($archive, base64_encode(random_bytes(48)), getenv('SUMMIT_MYSQL'));
    } catch (Throwable) {
        $rejected = true;
    }
    $result['wrong_password_rejected'] = $rejected;
    $result['rehearsal_database_removed'] = ! DB::select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [LocalBackup::RESTORE_DATABASE]);
    unlink($archive);
    unlink(LocalBackup::directory('summit_2026_test').'/latest-verified.json');
    echo json_encode($result);
}
