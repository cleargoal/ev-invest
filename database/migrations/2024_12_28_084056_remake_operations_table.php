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
        Schema::dropIfExists('operations');
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('key')->unique()->comment('Unique operation key - for all calculations');
            $table->string('description');
            $table->boolean('car')->default(false)->comment('Indicator for only car operations');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
