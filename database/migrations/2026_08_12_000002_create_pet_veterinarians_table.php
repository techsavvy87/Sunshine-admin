<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_veterinarians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_profile_id')->constrained('pet_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_veterinarians');
    }
};
