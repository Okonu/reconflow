<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recon_manual_matches', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_id', 64);
            $table->date('sale_date');
            $table->unsignedBigInteger('sale_record_id');
            $table->foreignId('sale_result_id')->nullable()->constrained('recon_results')->nullOnDelete();
            $table->string('payment_identity', 255)->unique();
            $table->string('payment_id', 64);
            $table->date('payment_date');
            $table->foreignId('payment_result_id')->nullable()->constrained('recon_results')->nullOnDelete();
            $table->foreignId('confirmed_by')->constrained('users');
            $table->text('reason');
            $table->timestampTz('created_at');
            $table->unique(['transaction_id', 'sale_date']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recon_manual_matches');
    }
};
