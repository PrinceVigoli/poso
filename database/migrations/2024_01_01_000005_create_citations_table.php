<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('violation_id')->constrained()->onDelete('cascade');
            $table->string('ticket_no')->unique();
            $table->decimal('fine_amount', 8, 2);
            $table->date('due_date');
            $table->enum('payment_status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->date('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
    }
};
