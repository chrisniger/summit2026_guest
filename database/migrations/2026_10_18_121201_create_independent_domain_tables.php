<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['info', 'guest'] as $domain) {
            Schema::create($domain.'_import_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conference_event_id')->constrained()->restrictOnDelete();
                $table->string('filename');
                $table->char('sha256', 64)->index();
                $table->enum('status', ['uploaded', 'previewed', 'processing', 'completed', 'failed'])->default('uploaded');
                $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
                foreach (['rows_total', 'rows_valid', 'rows_invalid', 'rows_created', 'rows_matched', 'auto_attendance_created'] as $counter) {
                    $table->unsignedInteger($counter)->default(0);
                }
                $table->json('summary_json')->nullable();
                $table->json('mapping_json')->nullable();
                $table->timestamps();
            });
            Schema::create($domain.'_import_rows', function (Blueprint $table) use ($domain) {
                $table->id();
                $table->foreignId('batch_id')->constrained($domain.'_import_batches')->restrictOnDelete();
                $table->string('worksheet_name')->nullable();
                $table->unsignedInteger('row_number');
                $table->json('raw_json');
                $table->json('normalized_json')->nullable();
                $table->char('row_fingerprint', 64)->index();
                $table->string('classification')->nullable();
                $table->json('error_json')->nullable();
                $table->string('processing_state')->default('pending');
                $table->timestamps();
                $table->unique(['batch_id', 'row_number']);
            });
        }
        Schema::create('info_attendees', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->index();
            $table->string('phone_raw', 50);
            $table->string('phone_normalized', 30)->nullable()->index();
            $table->string('email')->nullable();
            foreach (['member', 'commits_to_invite', 'first_time', 'bus_shuttle', 'consent'] as $field) {
                $table->boolean($field)->nullable();
            }
            foreach (['summit_centre', 'location', 'abuja_area', 'bus_route', 'attendance_preference', 'heard_about', 'source_external_id'] as $field) {
                $table->string($field)->nullable();
            }
            $table->unsignedInteger('invite_count')->nullable();
            $table->text('feedback')->nullable();
            $table->dateTime('registration_original_at_utc')->nullable()->index();
            $table->enum('registration_source', ['onsite', 'csv', 'other'])->default('onsite');
            $table->json('raw_source_json')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['first_name', 'last_name']);
        });
        Schema::table('info_import_rows', function (Blueprint $table) {
            $table->foreignId('info_attendee_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('info_registration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('info_attendee_id')->constrained()->restrictOnDelete();
            $table->enum('source', ['onsite', 'csv']);
            $table->dateTime('registered_at_utc')->index();
            $table->foreignId('import_row_id')->nullable()->constrained('info_import_rows')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('source_fingerprint', 64)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('info_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('info_attendee_id')->constrained()->restrictOnDelete();
            $table->foreignId('conference_session_id')->constrained()->restrictOnDelete();
            $table->dateTime('checked_in_at_utc');
            $table->enum('source', ['manual', 'registration_auto', 'csv_auto', 'admin_adjustment']);
            $table->foreignId('marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registration_event_id')->nullable()->constrained('info_registration_events')->restrictOnDelete();
            $table->foreignId('import_row_id')->nullable()->constrained('info_import_rows')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['info_attendee_id', 'conference_session_id'], 'attendance_attendee_session_unique');
        });
        Schema::create('guest_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_session_id')->constrained()->restrictOnDelete();
            $table->string('full_name')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_raw', 50);
            $table->string('phone_normalized', 30)->nullable()->index();
            $table->string('gender')->nullable();
            $table->text('home_address')->nullable();
            $table->string('heard_about')->nullable();
            $table->dateTime('captured_at_original_utc')->nullable();
            $table->text('entry_notes')->nullable();
            $table->enum('current_status', ['NOT CONTACTED', 'CONTACTED', 'UNREACHABLE'])->default('NOT CONTACTED');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('import_row_id')->nullable()->constrained('guest_import_rows')->restrictOnDelete();
            $table->string('legacy_officer_name')->nullable();
            $table->json('raw_source_json')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['conference_session_id', 'current_status']);
        });
        Schema::table('guest_import_rows', function (Blueprint $table) {
            $table->foreignId('guest_record_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('follow_up_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('officer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('unassigned_at')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('active_guest_id')->nullable()->storedAs('CASE WHEN unassigned_at IS NULL THEN guest_record_id ELSE NULL END');
            $table->unique('active_guest_id');
            $table->index(['officer_user_id', 'unassigned_at']);
            $table->timestamps();
        });
        Schema::create('follow_up_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('follow_up_assignments')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('method', ['call', 'sms', 'whatsapp', 'visit', 'other']);
            $table->dateTime('attempted_at_utc')->index();
            $table->enum('outcome', ['CONTACTED', 'UNREACHABLE', 'NOTE_ONLY']);
            $table->text('comment')->nullable();
            $table->enum('previous_status', ['NOT CONTACTED', 'CONTACTED', 'UNREACHABLE']);
            $table->enum('new_status', ['NOT CONTACTED', 'CONTACTED', 'UNREACHABLE'])->nullable();
            $table->timestamps();
        });
        Schema::create('follow_up_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_record_id')->constrained()->restrictOnDelete();
            $table->enum('from_status', ['NOT CONTACTED', 'CONTACTED', 'UNREACHABLE'])->nullable();
            $table->enum('to_status', ['NOT CONTACTED', 'CONTACTED', 'UNREACHABLE']);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at_utc');
            $table->text('reason')->nullable();
            $table->foreignId('activity_id')->nullable()->constrained('follow_up_activities')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE follow_up_assignments ADD CONSTRAINT assignment_interval_valid CHECK (unassigned_at IS NULL OR unassigned_at >= assigned_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_status_history');
        Schema::dropIfExists('follow_up_activities');
        Schema::dropIfExists('follow_up_assignments');
        Schema::table('guest_import_rows', fn (Blueprint $table) => $table->dropConstrainedForeignId('guest_record_id'));
        Schema::dropIfExists('guest_records');
        Schema::dropIfExists('info_attendance_records');
        Schema::dropIfExists('info_registration_events');
        Schema::table('info_import_rows', fn (Blueprint $table) => $table->dropConstrainedForeignId('info_attendee_id'));
        Schema::dropIfExists('info_attendees');
        foreach (['guest', 'info'] as $domain) {
            Schema::dropIfExists($domain.'_import_rows');
            Schema::dropIfExists($domain.'_import_batches');
        }
    }
};
