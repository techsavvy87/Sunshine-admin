<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_addresses', function (Blueprint $table) {
            $table->boolean('late_fees_enabled')->default(true);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('apply_late_fee')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('apply_late_fee'));
        Schema::table('facility_addresses', fn (Blueprint $table) => $table->dropColumn('late_fees_enabled'));
    }
};
