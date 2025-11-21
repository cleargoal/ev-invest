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
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_cancelled')->default(false)->comment('Whether this payment has been cancelled');
            $table->timestamp('cancelled_at')->nullable()->comment('When the payment was cancelled');
            $table->unsignedBigInteger('cancelled_by')->nullable()->comment('User who cancelled the payment');
            
            $table->foreign('cancelled_by')->references('id')->on('users');
            $table->index(['is_cancelled', 'confirmed']); // For efficient filtering queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex(['is_cancelled', 'confirmed']);
            $table->dropColumn(['is_cancelled', 'cancelled_at', 'cancelled_by']);
        });
    }
};