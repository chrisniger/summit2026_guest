<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE guest_records MODIFY current_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE','WRONG NUMBER') NOT NULL DEFAULT 'NOT CONTACTED'");
        DB::statement("ALTER TABLE follow_up_activities MODIFY outcome ENUM('CONTACTED','UNREACHABLE','WRONG_NUMBER','NOTE_ONLY') NOT NULL");
        DB::statement("ALTER TABLE follow_up_activities MODIFY previous_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE','WRONG NUMBER') NOT NULL");
        DB::statement("ALTER TABLE follow_up_activities MODIFY new_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE','WRONG NUMBER') NULL");
        DB::statement("ALTER TABLE follow_up_status_history MODIFY from_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE','WRONG NUMBER') NULL");
        DB::statement("ALTER TABLE follow_up_status_history MODIFY to_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE','WRONG NUMBER') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('guest_records')->where('current_status', 'WRONG NUMBER')->update(['current_status' => 'UNREACHABLE']);
        DB::table('follow_up_activities')->where('outcome', 'WRONG_NUMBER')->update(['outcome' => 'UNREACHABLE']);
        DB::table('follow_up_activities')->where('previous_status', 'WRONG NUMBER')->update(['previous_status' => 'UNREACHABLE']);
        DB::table('follow_up_activities')->where('new_status', 'WRONG NUMBER')->update(['new_status' => 'UNREACHABLE']);
        DB::table('follow_up_status_history')->where('from_status', 'WRONG NUMBER')->update(['from_status' => 'UNREACHABLE']);
        DB::table('follow_up_status_history')->where('to_status', 'WRONG NUMBER')->update(['to_status' => 'UNREACHABLE']);

        DB::statement("ALTER TABLE guest_records MODIFY current_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE') NOT NULL DEFAULT 'NOT CONTACTED'");
        DB::statement("ALTER TABLE follow_up_activities MODIFY outcome ENUM('CONTACTED','UNREACHABLE','NOTE_ONLY') NOT NULL");
        DB::statement("ALTER TABLE follow_up_activities MODIFY previous_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE') NOT NULL");
        DB::statement("ALTER TABLE follow_up_activities MODIFY new_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE') NULL");
        DB::statement("ALTER TABLE follow_up_status_history MODIFY from_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE') NULL");
        DB::statement("ALTER TABLE follow_up_status_history MODIFY to_status ENUM('NOT CONTACTED','CONTACTED','UNREACHABLE') NOT NULL");
    }
};
