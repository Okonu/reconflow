<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recon_rule_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->jsonb('values');
            $table->text('comment')->default('');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
        });

        Schema::create('recon_runs', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date');
            $table->unsignedInteger('version');
            $table->string('status', 16);
            $table->string('trigger', 16);
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('provisional')->default(false);
            $table->timestampTz('stale_at')->nullable();
            $table->foreignId('rule_config_id')->nullable()->constrained('recon_rule_configs')->nullOnDelete();
            $table->jsonb('rule_config');
            $table->jsonb('batches')->default('{}');
            $table->jsonb('summary')->default('{}');
            $table->text('blocked_reason')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('recon_runs')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestampsTz();
            $table->unique(['business_date', 'version']);
            $table->index(['business_date', 'status']);
        });

        Schema::create('recon_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('recon_runs')->cascadeOnDelete();
            $table->date('business_date');
            $table->string('section', 16);
            $table->string('transaction_id', 64)->nullable();
            $table->jsonb('payment_ids');
            $table->string('journal_id', 64)->nullable();
            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->decimal('actual_amount', 14, 2)->nullable();
            $table->decimal('posted_amount', 14, 2)->nullable();
            $table->decimal('variance', 14, 2)->nullable();
            $table->decimal('variance_pct', 9, 2)->nullable();
            $table->string('status', 24);
            $table->string('roll_up', 24);
            $table->string('rule_id', 24);
            $table->decimal('match_confidence', 4, 3)->nullable();
            $table->string('tag', 64)->nullable();
            $table->foreignId('prior_result_id')->nullable()->constrained('recon_results')->nullOnDelete();
            $table->date('prior_date')->nullable();
            $table->unsignedBigInteger('sale_record_id')->nullable();
            $table->jsonb('payment_record_ids');
            $table->jsonb('payment_identities');
            $table->jsonb('flags');
            $table->index(['run_id', 'section', 'status']);
            $table->index(['transaction_id']);
        });

        Schema::create('recon_item_states', function (Blueprint $table): void {
            $table->foreignId('result_id')->primary()->constrained('recon_results')->cascadeOnDelete();
            $table->date('business_date');
            $table->string('state', 16);
            $table->string('effective_status', 24);
            $table->foreignId('resolved_by_run_id')->nullable()->constrained('recon_runs')->nullOnDelete();
            $table->foreignId('resolved_by_result_id')->nullable()->constrained('recon_results')->nullOnDelete();
            $table->text('reason')->default('');
            $table->timestampTz('updated_at');
            $table->index(['business_date', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recon_item_states');
        Schema::dropIfExists('recon_results');
        Schema::dropIfExists('recon_runs');
        Schema::dropIfExists('recon_rule_configs');
    }
};
