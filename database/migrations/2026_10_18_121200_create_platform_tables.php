<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'user_id']);
        });
        Schema::create('conference_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('timezone')->default('Africa/Lagos');
            $table->timestamps();
        });
        Schema::create('conference_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_event_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->unsignedTinyInteger('day_number');
            $table->date('date_local')->index();
            $table->enum('name', ['General', 'Morning', 'Evening']);
            $table->string('label');
            $table->dateTime('starts_at_utc')->nullable()->index();
            $table->dateTime('ends_at_utc')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamp('attendance_locked_at')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE conference_sessions ADD CONSTRAINT session_interval_valid CHECK ((starts_at_utc IS NULL AND ends_at_utc IS NULL AND enabled = 0) OR (starts_at_utc IS NOT NULL AND ends_at_utc IS NOT NULL AND starts_at_utc < ends_at_utc))');
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('domain', ['info_com', 'follow_up', 'platform'])->index();
            $table->string('action')->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('occurred_at')->index();
        });
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['app_settings', 'audit_logs', 'conference_sessions', 'conference_events', 'permission_user', 'permission_role', 'role_user', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_active', 'last_login_at']));
    }
};
