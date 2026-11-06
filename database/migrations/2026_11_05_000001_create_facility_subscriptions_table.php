<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('facility_key')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_price_id')->nullable();
            $table->string('status');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('payment_failed_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('facility_subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_subscription_id')->constrained('facility_subscriptions')->cascadeOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->string('number')->nullable();
            $table->string('status');
            $table->integer('amount_due')->default(0);
            $table->integer('amount_paid')->default(0);
            $table->string('currency', 10)->default('usd');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('hosted_invoice_url')->nullable();
            $table->text('invoice_pdf')->nullable();
            $table->timestamps();
        });

        Schema::create('stripe_subscription_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_subscription_webhook_events');
        Schema::dropIfExists('facility_subscription_invoices');
        Schema::dropIfExists('facility_subscriptions');
    }
};
