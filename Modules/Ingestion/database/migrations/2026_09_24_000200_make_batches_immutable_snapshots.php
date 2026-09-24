<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_batches', function (Blueprint $table): void {
            $table->foreignId('parent_batch_id')->nullable()->after('version')->constrained('source_batches')->nullOnDelete();
            $table->boolean('manual')->default(false)->after('mode');
            $table->unsignedInteger('rows_added')->default(0)->after('rows_loaded');
        });

        DB::statement("update source_batches set mode = 'pull', manual = false where origin = 'source_system'");
        DB::statement("update source_batches set mode = 'upload_replace', manual = true where origin = 'upload' and mode = 'replace'");
        DB::statement("update source_batches set mode = 'upload_append', manual = true where origin = 'upload' and mode = 'append'");
        DB::statement('update source_batches set rows_added = rows_loaded');

        DB::statement("create unique index source_batches_one_active on source_batches (source, business_date) where status = 'active'");
    }

    public function down(): void
    {
        DB::statement('drop index if exists source_batches_one_active');
        Schema::table('source_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_batch_id');
            $table->dropColumn(['manual', 'rows_added']);
        });
    }
};
