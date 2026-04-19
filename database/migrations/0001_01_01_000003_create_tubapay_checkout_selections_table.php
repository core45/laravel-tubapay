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
        Schema::create(config('tubapay.database.checkout_selections_table', 'tubapay_checkout_selections'), function (Blueprint $table): void {
            $table->id();
            $table->string('external_ref', 100)->unique();
            $table->unsignedTinyInteger('installments');
            $table->json('consents_accepted')->nullable();
            $table->string('return_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('tubapay.database.checkout_selections_table', 'tubapay_checkout_selections'));
    }
};
