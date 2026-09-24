<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 24);
            $table->foreignId('exception_id')->nullable()->constrained('exceptions')->nullOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('recon_runs')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16);
            $table->string('model', 64);
            $table->string('prompt_version', 16);
            $table->char('prompt_hash', 64);
            $table->jsonb('input');
            $table->jsonb('output')->nullable();
            $table->string('error', 500)->nullable();
            $table->boolean('served_by_fallback')->default(false);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('override_action', 40)->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->index(['exception_id', 'id']);
            $table->index(['kind', 'created_at']);
        });

        Schema::create('ai_settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->jsonb('value');
            $table->timestampsTz();
        });

        Schema::create('ai_eval_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('eval_set', 64);
            $table->string('model', 64);
            $table->string('prompt_version', 16);
            $table->char('prompt_hash', 64);
            $table->unsignedInteger('cases');
            $table->unsignedInteger('action_correct');
            $table->unsignedInteger('cause_correct');
            $table->unsignedInteger('errors');
            $table->jsonb('results');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_eval_runs');
        Schema::dropIfExists('ai_settings');
        Schema::dropIfExists('ai_suggestions');
    }
};
