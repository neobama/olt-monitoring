<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('onus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_device_id')->constrained()->onDelete('cascade');
            $table->string('onu_id')->nullable();
            $table->string('serial_number')->unique()->nullable();
            $table->string('interface');
            $table->string('description')->nullable();
            $table->float('rx_power')->nullable();
            $table->float('tx_power')->nullable();
            $table->enum('status', ['online', 'offline', 'los'])->default('offline');
            $table->boolean('is_online')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('onus');
    }
}; 