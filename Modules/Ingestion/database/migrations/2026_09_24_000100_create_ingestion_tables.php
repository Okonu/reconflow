<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 16);
            $table->date('business_date');
            $table->unsignedInteger('version');
            $table->string('origin', 16);
            $table->string('status', 16)->default('active');
            $table->string('mode', 16);
            $table->string('filename')->nullable();
            $table->char('checksum', 64);
            $table->unsignedInteger('rows_received');
            $table->unsignedInteger('rows_loaded');
            $table->unsignedInteger('rows_quarantined');
            $table->jsonb('dq_summary');
            $table->timestampTz('extracted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->constrained('source_batches')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'business_date', 'version']);
            $table->index(['source', 'business_date', 'status']);
            $table->index('checksum');
        });

        Schema::create('sales_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('source_batches')->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('row_number');
            $table->string('transaction_id', 64);
            $table->timestampTz('sold_at');
            $table->string('agent_id', 64);
            $table->string('customer_phone', 20);
            $table->string('region', 32);
            $table->string('product_sku', 64);
            $table->decimal('expected_amount', 14, 2);
            $table->char('currency', 3);
            $table->string('payment_reference', 128)->nullable();
            $table->index(['batch_id', 'transaction_id']);
            $table->index(['business_date', 'transaction_id']);
        });

        Schema::create('payment_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('source_batches')->cascadeOnDelete();
            $table->date('business_date');
            $table->date('payment_date');
            $table->unsignedInteger('row_number');
            $table->string('payment_id', 64);
            $table->timestampTz('paid_at');
            $table->string('channel', 16);
            $table->string('payer_phone', 20)->nullable();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->string('reference', 128)->nullable();
            $table->index(['batch_id', 'payment_id']);
            $table->index(['paid_at']);
            $table->index(['payment_date']);
        });

        Schema::create('posting_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('source_batches')->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('row_number');
            $table->string('journal_id', 64);
            $table->date('posting_date');
            $table->string('transaction_id', 64);
            $table->string('account', 64);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->string('status', 16);
            $table->index(['batch_id', 'transaction_id']);
            $table->index(['business_date', 'journal_id']);
        });

        Schema::create('quarantined_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('source_batches')->cascadeOnDelete();
            $table->string('source', 16);
            $table->date('business_date');
            $table->unsignedInteger('row_number');
            $table->string('record_key', 128);
            $table->jsonb('reasons');
            $table->jsonb('raw');
            $table->timestampTz('created_at');
            $table->index(['batch_id']);
        });

        Schema::create('upload_staging', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source', 16);
            $table->date('business_date');
            $table->string('filename');
            $table->string('extension', 8);
            $table->unsignedInteger('size_bytes');
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->jsonb('header_check');
            $table->jsonb('rows')->nullable();
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_valid')->default(0);
            $table->unsignedInteger('rows_invalid')->default(0);
            $table->foreignId('duplicate_of_batch_id')->nullable()->constrained('source_batches')->nullOnDelete();
            $table->boolean('date_has_data')->default(false);
            $table->string('state', 16)->default('staged');
            $table->string('mode', 16)->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('source_batches')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->index(['state', 'expires_at']);
        });

        Schema::create('mock_source_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 16);
            $table->date('record_date');
            $table->timestampTz('occurred_at')->nullable();
            $table->unsignedInteger('sequence');
            $table->jsonb('payload');
            $table->index(['source', 'record_date', 'sequence']);
            $table->index(['source', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_source_rows');
        Schema::dropIfExists('upload_staging');
        Schema::dropIfExists('quarantined_rows');
        Schema::dropIfExists('posting_records');
        Schema::dropIfExists('payment_records');
        Schema::dropIfExists('sales_records');
        Schema::dropIfExists('source_batches');
    }
};
