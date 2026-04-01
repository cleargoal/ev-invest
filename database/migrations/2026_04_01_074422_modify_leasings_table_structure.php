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
        Schema::table('leasings', function (Blueprint $table) {
            // Remove old columns
            $table->dropColumn(['vehicle_id', 'start_date', 'end_date', 'duration']);

            // Add new columns
            $table->tinyInteger('month')->comment('Month (1-12)')->after('id');
            $table->smallInteger('year')->comment('Year')->after('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leasings', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn(['month', 'year']);

            // Restore old columns
            $table->unsignedBigInteger('vehicle_id')->comment('that vehicle was leased')->after('id');
            $table->date('start_date')->comment('start date')->after('vehicle_id');
            $table->date('end_date')->comment('end date')->after('start_date');
            $table->integer('duration')->comment('days duration')->after('end_date');
        });
    }
};
