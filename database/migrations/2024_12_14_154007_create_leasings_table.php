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
        Schema::create('leasings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->comment('that vehicle was leased');
            $table->date('start_date')->comment('start date');
            $table->date('end_date')->comment('end date');
            $table->integer('duration')->comment('days duration');
            $table->integer('price')->comment('price per period');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leasings');
    }
};
