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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pet_id')->constrained('pet_profiles')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->unsignedBigInteger('kennel_id')->nullable();
            $table->unsignedBigInteger('cat_room_id')->nullable();
            $table->string('additional_service_ids')->nullable();
            $table->foreignId('staff_id')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('status', ['cancelled', 'no_show', 'checked_in', 'in_progress', 'completed', 'finished', 'issue', 'wait listed'])->default('checked_in');
            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('apply_late_fee')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
