<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('violators', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('license_no')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_no')->nullable();
            $table->date('birthdate')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violators');
    }
};
