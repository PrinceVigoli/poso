<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('violation_types', function (Blueprint $table) {
            $table->softDeletes();
            $table->decimal('fine_amount', 8, 2)->nullable()->default(null)->change();
        });
        Schema::table('citations', function (Blueprint $table) {
            $table->decimal('fine_amount', 8, 2)->nullable()->change();
            $table->string('treasury_receipt_no', 100)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
        });
        // Retire old choices without deleting any apprehensions or citation history.
        DB::table('violation_types')->whereNotIn('offense_name', ['No Side Mirror', 'No Helmet', 'No Vest'])->update(['deleted_at' => now()]);
        foreach (['No Side Mirror', 'No Helmet', 'No Vest'] as $name) {
            if (!DB::table('violation_types')->where('offense_name', $name)->exists()) {
                DB::table('violation_types')->insert(['offense_name' => $name, 'category' => 'Traffic Violation', 'fine_amount' => null, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('citations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['treasury_receipt_no', 'verified_at']);
        });
        Schema::table('violation_types', fn (Blueprint $table) => $table->dropSoftDeletes());
        // Nullable fine amounts are retained to avoid inventing ordinance amounts.
    }
};
