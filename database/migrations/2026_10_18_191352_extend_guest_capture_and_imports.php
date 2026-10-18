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
        Schema::table('guest_records', function (Blueprint $table) {
            $table->char('source_fingerprint', 64)->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
        });
        Schema::table('guest_import_batches', function (Blueprint $table) {
            $table->string('private_path')->nullable();
            $table->unsignedInteger('rows_review')->default(0);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->text('failure_message')->nullable();
        });
        Schema::table('guest_import_rows', function (Blueprint $table) {
            $table->unique(['batch_id', 'worksheet_name', 'row_number'], 'guest_import_source_row_unique');
        });
        Schema::table('guest_import_rows', fn (Blueprint $table) => $table->dropUnique(['batch_id', 'row_number']));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_import_rows', function (Blueprint $table) {
            $table->unique(['batch_id', 'row_number']);
        });
        Schema::table('guest_import_rows', fn (Blueprint $table) => $table->dropUnique('guest_import_source_row_unique'));
        Schema::table('guest_import_batches', fn (Blueprint $table) => $table->dropColumn(['private_path', 'rows_review', 'rows_processed', 'rows_skipped', 'failure_message']));
        Schema::table('guest_records', function (Blueprint $table) {
            $table->dropUnique(['source_fingerprint']);
            $table->dropColumn(['source_fingerprint', 'version']);
        });
    }
};
