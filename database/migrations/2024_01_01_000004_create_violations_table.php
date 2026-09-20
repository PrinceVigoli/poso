<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('violator_id')->constrained()->onDelete('cascade');
            $table->foreignId('officer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('violation_type_id')->constrained()->onDelete('cascade');
            $table->date('violation_date');
            $table->string('location');
            $table->enum('status', ['pending', 'settled', 'dismissed'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
