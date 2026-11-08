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
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_code', 64)->unique();
            $table->string('device_name');
            $table->uuid('owner_id')->nullable();
            $table->boolean('is_online')->default(false);
            $table->enum('garage_status', ['CLOSED', 'OPEN'])->default('CLOSED');
            $table->enum('system_mode', ['STANDBY', 'PARKING_IN', 'EXITING'])->default('STANDBY');
            $table->integer('wifi_rssi')->default(0);
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};