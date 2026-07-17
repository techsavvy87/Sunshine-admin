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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('invoice_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('discount_title')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->dateTime('issued_at')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'sent', 'partially_paid', 'paid', 'void'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
