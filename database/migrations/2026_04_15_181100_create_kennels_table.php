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
        Schema::create('kennels', function (Blueprint $table) {
            $table->id();
            $table->string('img')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('capacity');
            $table->enum('kennel_type', ['Canine','Feline'])->default('Canine');
            $table->enum('status', ['In Service', 'Out of Service', 'Cleaning'])->default('In Service');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kennels');
    }
};
