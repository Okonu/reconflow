<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exceptions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 255);
            $table->string('identity', 255);
            $table->date('business_date');
            $table->foreignId('result_id')->nullable()->constrained('recon_results')->nullOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('recon_runs')->nullOnDelete();
            $table->string('section', 16)->default('current');
            $table->string('status', 24);
            $table->string('family', 24);
            $table->string('category', 64);
            $table->string('severity', 16);
            $table->decimal('amount_at_risk', 14, 2)->default(0);
            $table->string('transaction_id', 64)->nullable();
            $table->jsonb('payment_ids');
            $table->string('region', 32)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('due_at')->nullable();
            $table->string('state', 24);
            $table->boolean('needs_review')->default(false);
            $table->boolean('soft')->default(false);
            $table->foreignId('predecessor_id')->nullable()->constrained('exceptions')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestampTz('escalated_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->index(['state', 'severity']);
            $table->index(['business_date', 'state']);
            $table->index(['owner_id', 'state']);
            $table->index('key');
            $table->index('result_id');
        });

        Schema::create('exception_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exception_id')->constrained('exceptions')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 254);
            $table->string('type', 24);
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24)->nullable();
            $table->text('comment')->default('');
            $table->jsonb('data');
            $table->timestampTz('created_at');
            $table->index('exception_id');
        });

        Schema::create('run_signoffs', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date');
            $table->foreignId('run_id')->constrained('recon_runs');
            $table->foreignId('signed_by')->constrained('users');
            $table->text('comment')->default('');
            $table->jsonb('carried_exception_ids');
            $table->timestampTz('signed_at');
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestampTz('reopened_at')->nullable();
            $table->index(['business_date', 'reopened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_signoffs');
        Schema::dropIfExists('exception_events');
        Schema::dropIfExists('exceptions');
    }
};
