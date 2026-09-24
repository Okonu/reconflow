<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recon_match_reviews', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date');
            $table->string('transaction_id', 64);
            $table->string('payment_identity', 255);
            $table->string('decision', 16);
            $table->foreignId('result_id')->nullable()->constrained('recon_results')->nullOnDelete();
            $table->foreignId('decided_by')->constrained('users');
            $table->text('reason')->default('');
            $table->timestampTz('created_at');
            $table->unique(['business_date', 'transaction_id', 'payment_identity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recon_match_reviews');
    }
};
