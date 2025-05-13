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
        Schema::table('onus', function (Blueprint $table) {
            $table->string('admin_state')->nullable();
            $table->string('omcc_state')->nullable();
            $table->string('phase_state')->nullable();
            $table->timestamp('last_seen')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->dropColumn([
                'admin_state',
                'omcc_state',
                'phase_state',
                'last_seen'
            ]);
        });
    }
};
