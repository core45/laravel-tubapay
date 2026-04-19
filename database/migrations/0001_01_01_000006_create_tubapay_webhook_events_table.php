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
        Schema::create(config('tubapay.database.webhook_events_table', 'tubapay_webhook_events'), function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->string('status', 30)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('payload_hash', 64);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('tubapay.database.webhook_events_table', 'tubapay_webhook_events'));
    }
};
