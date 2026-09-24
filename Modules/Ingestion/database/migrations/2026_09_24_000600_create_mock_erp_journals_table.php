<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_erp_journals', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 100)->unique();
            $table->string('journal_id', 64)->unique();
            $table->date('posting_date');
            $table->string('reversal_of', 64)->nullable();
            $table->jsonb('request');
            $table->jsonb('response');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_erp_journals');
    }
};
