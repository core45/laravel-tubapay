<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('tubapay.database.table', 'tubapay_transactions'), function (Blueprint $table) {
            $table->id();

            // Reference to your local order/payment
            $table->string('external_ref', 100)->index();

            // TubaPay identifiers
            $table->string('agreement_number', 50)->nullable()->index();
            $table->string('transaction_link', 500)->nullable();

            // Status tracking
            $table->string('status', 50)->default('draft');
            $table->string('previous_status', 50)->nullable();
            $table->timestamp('status_changed_at')->nullable();

            // Financial details
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PLN');
            $table->unsignedTinyInteger('installments')->nullable();

            // Customer info (denormalized for quick access)
            $table->string('customer_email', 255)->nullable()->index();
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_name', 255)->nullable();

            // TubaPay metadata
            $table->string('origin_company', 50)->nullable();
            $table->string('template_name', 100)->nullable();

            // Raw payload storage (for debugging/auditing)
            $table->json('last_webhook_payload')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['status', 'created_at']);
            $table->index(['customer_email', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('tubapay.database.table', 'tubapay_transactions'));
    }
};
