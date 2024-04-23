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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('Owner');
            $table->string('title');
            $table->string('produced')->comment('The year when the vehicle produced')->nullable();
            $table->string('mileage')->nullable();
            $table->integer('cost')->comment('Cost in cents');
            $table->integer('price')->comment('Price in cents');
            $table->timestamp('sale_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
