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
        Schema::create('pet_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('pet_img')->nullable();
            $table->string('name');
            $table->enum('sex', ['male', 'female'])->default('male');
            $table->enum('spay_neuter', ['spayed', 'neutered', 'intact'])->nullable();
            $table->enum('type', ['Dog', 'Cat'])->default('Dog');
            $table->date('birthdate')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->foreignId('breed_id')->nullable()->constrained('breeds')->onDelete('set null');
            $table->decimal('weight', 5, 2)->nullable();
            $table->enum('size', ['small', 'medium', 'large', 'xlarge'])->default('medium');
            $table->foreignId('color_id')->nullable()->constrained('colors')->onDelete('set null');
            $table->foreignId('coat_type_id')->nullable()->constrained('coat_types')->onDelete('set null');
            $table->string('veterinarian_name');
            $table->string('veterinarian_phone');
            $table->json('pet_behavior_id')->nullable();
            $table->text('notes')->nullable();
            $table->enum('vaccine_status', ['missing', 'submitted', 'approved', 'declined', 'expired'])->default('missing');
            $table->enum('rating', ['green', 'yellow', 'red'])->nullable();
            $table->text('rating_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_profiles');
    }
};
