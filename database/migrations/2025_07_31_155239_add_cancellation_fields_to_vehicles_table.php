<?php

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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->comment('When the sale was cancelled/unsold');
            $table->text('cancellation_reason')->nullable()->comment('Reason for cancelling the sale');
            $table->unsignedBigInteger('cancelled_by')->nullable()->comment('User who cancelled the sale');
            
            $table->foreign('cancelled_by')->references('id')->on('users');
            $table->index(['cancelled_at', 'sale_date']); // For efficient filtering queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex(['cancelled_at', 'sale_date']);
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'cancelled_by']);
        });
    }
};