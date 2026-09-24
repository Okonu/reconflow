<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 64);
            $table->unsignedInteger('version');
            $table->jsonb('values');
            $table->text('comment')->default('');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['section', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_versions');
    }
};
