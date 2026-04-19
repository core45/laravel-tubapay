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
        Schema::table(config('tubapay.database.table', 'tubapay_transactions'), function (Blueprint $table): void {
            $table->json('consents_accepted')->nullable()->after('installments');
            $table->string('selection_source', 30)->nullable()->after('consents_accepted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('tubapay.database.table', 'tubapay_transactions'), function (Blueprint $table): void {
            $table->dropColumn(['consents_accepted', 'selection_source']);
        });
    }
};
