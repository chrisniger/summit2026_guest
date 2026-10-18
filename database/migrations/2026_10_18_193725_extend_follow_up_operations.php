<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('follow_up_activities', function (Blueprint $table) {
            $table->char('source_fingerprint', 64)->nullable()->unique();
            $table->index(['guest_record_id', 'attempted_at_utc'], 'follow_up_guest_attempt_time');
        });
        Schema::table('follow_up_assignments', function (Blueprint $table) {
            $table->text('closure_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('follow_up_assignments', fn (Blueprint $table) => $table->dropColumn('closure_reason'));
        Schema::table('follow_up_activities', function (Blueprint $table) {
            $table->dropUnique(['source_fingerprint']);
            $table->dropIndex('follow_up_guest_attempt_time');
            $table->dropColumn('source_fingerprint');
        });
    }
};
