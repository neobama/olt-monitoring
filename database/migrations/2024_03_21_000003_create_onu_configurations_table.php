<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('onu_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onu_id')->constrained()->onDelete('cascade');
            $table->string('vlan')->nullable();
            $table->string('tcont')->nullable();
            $table->string('vlan_name')->nullable();
            $table->string('gem_port')->nullable();
            $table->string('service_profile')->nullable();
            $table->json('additional_config')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('onu_configurations');
    }
}; 