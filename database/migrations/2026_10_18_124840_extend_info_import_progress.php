<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('info_import_batches', function (Blueprint $table) {
            $table->string('private_path')->nullable();
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_review')->default(0);
            $table->text('failure_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('info_import_batches', fn (Blueprint $table) => $table->dropColumn(['private_path', 'rows_processed', 'rows_skipped', 'rows_review', 'failure_message']));
    }
};
