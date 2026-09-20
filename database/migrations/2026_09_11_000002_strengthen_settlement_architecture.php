<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // Never silently remove payment history to make a constraint fit.
        if (DB::table('citations')->select('violation_id')->groupBy('violation_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate payment records require review before migration. No rows were removed.');
        }
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('credential_version')->default(0));
        Schema::table('citations', function (Blueprint $t) {
            $t->unique('violation_id');
            $t->date('receipt_date')->nullable();
            $t->index(['payment_status', 'due_date']);
            $t->index(['payment_status', 'paid_at']);
        });
        Schema::table('treasury_receipts', fn (Blueprint $t) => $t->date('receipt_date')->nullable());
        Schema::table('violations', function (Blueprint $t) {
            $t->index(['violation_date', 'id']);
            $t->index(['officer_id', 'id']);
        });
        Schema::table('payment_events', fn (Blueprint $t) => $t->index('created_at'));
        Schema::table('violators', fn (Blueprint $t) => $t->string('normalized_name')->nullable()->index());
        DB::table('violators')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('violators')->where('id', $row->id)->update(['normalized_name' => Str::lower(Str::squish($row->full_name))]);
            }
        });
        Schema::table('violation_types', fn (Blueprint $t) => $t->uuid('offense_key')->nullable()->index());
        // Existing version lineage is unknown: do not guess that similar names are one ordinance.
        DB::table('violation_types')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('violation_types')->where('id', $row->id)->update(['offense_key' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('violation_types', fn (Blueprint $t) => $t->dropColumn('offense_key'));
        Schema::table('violators', fn (Blueprint $t) => $t->dropColumn('normalized_name'));
        Schema::table('payment_events', fn (Blueprint $t) => $t->dropIndex(['created_at']));
        Schema::table('violations', function (Blueprint $t) { $t->dropIndex(['violation_date', 'id']); $t->dropIndex(['officer_id', 'id']); });
        Schema::table('treasury_receipts', fn (Blueprint $t) => $t->dropColumn('receipt_date'));
        Schema::table('citations', function (Blueprint $t) {
            $t->dropUnique(['violation_id']);
            $t->dropIndex(['payment_status', 'due_date']);
            $t->dropIndex(['payment_status', 'paid_at']);
            $t->dropColumn('receipt_date');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('credential_version'));
    }
};
