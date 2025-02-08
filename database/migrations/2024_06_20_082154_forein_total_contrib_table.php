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
        Schema::table('totals', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')
            ->cascadeOnDelete()->noActionOnUpdate();
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')
            ->cascadeOnDelete()->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('totals', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
        });
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
        });
    }
};
