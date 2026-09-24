<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exception_id')->constrained('exceptions');
            $table->string('type', 32);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('USD');
            $table->text('reason');
            $table->foreignId('proposed_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->string('state', 24);
            $table->boolean('high_value')->default(false);
            $table->uuid('idempotency_key')->unique();
            $table->jsonb('journal');
            $table->string('erp_journal_id', 64)->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->unsignedInteger('posting_attempts')->default(0);
            $table->timestampsTz();
            $table->index(['state']);
            $table->index(['exception_id']);
        });

        Schema::create('erp_postings_out', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adjustment_id')->constrained('adjustments')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->jsonb('request');
            $table->jsonb('response')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('succeeded');
            $table->text('error')->nullable();
            $table->timestampTz('created_at');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_postings_out');
        Schema::dropIfExists('adjustments');
    }
};
