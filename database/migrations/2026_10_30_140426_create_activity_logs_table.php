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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->string('action', 100);
            $table->float('rear_dist_cm')->nullable();
            $table->float('side_dist_cm')->nullable();
            $table->float('fuzzy_risk_score')->nullable(); // Value 0.0 - 100.0%
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};