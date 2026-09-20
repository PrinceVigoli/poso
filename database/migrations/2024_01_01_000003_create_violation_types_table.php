<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('violation_types', function (Blueprint $table) {
            $table->id();
            $table->string('offense_name');
            $table->string('category')->default('Traffic Violation'); // unified single category
            $table->decimal('fine_amount', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_types');
    }
};
