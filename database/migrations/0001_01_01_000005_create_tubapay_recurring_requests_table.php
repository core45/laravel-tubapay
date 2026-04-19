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
        Schema::create(config('tubapay.database.recurring_requests_table', 'tubapay_recurring_requests'), function (Blueprint $table): void {
            $table->id();
            $table->string('external_ref', 100)->nullable()->index();
            $table->string('agreement_number', 50)->nullable()->index();
            $table->string('payment_schedule_id', 100)->nullable()->unique();
            $table->unsignedInteger('rate_number')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('request_total_amount', 12, 2);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('tubapay.database.recurring_requests_table', 'tubapay_recurring_requests'));
    }
};
