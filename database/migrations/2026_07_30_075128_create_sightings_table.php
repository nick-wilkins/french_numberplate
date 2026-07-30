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
        Schema::create('sightings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('code', 4)->index();
            $table->unsignedTinyInteger('car')->index();
            $table->unsignedTinyInteger('variant');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->smallInteger('rotation');
            $table->unsignedTinyInteger('scale');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sightings');
    }
};
