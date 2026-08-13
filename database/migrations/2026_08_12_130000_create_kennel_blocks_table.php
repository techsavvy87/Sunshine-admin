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
        Schema::create('kennel_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kennel_id')->constrained('kennels')->onDelete('cascade');
            $table->date('blocked_from');
            $table->date('blocked_to');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['kennel_id', 'blocked_from', 'blocked_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kennel_blocks');
    }
};
