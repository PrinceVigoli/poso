<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $ids = DB::table('users')->where('role', 'officer')->pluck('id');
            DB::table('users')->whereIn('id', $ids)->update(['role' => 'enforcer', 'updated_at' => now()]);
            if ($ids->isNotEmpty()) {
                DB::table('audit_logs')->insert([
                    'user_id' => null, 'action' => 'updated', 'module' => 'users',
                    'details' => 'Retired Officer role; converted accounts to Enforcer, preserving record ownership. User IDs: '.$ids->implode(', '),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        // SQLite rebuilds this table to alter its CHECK constraint. Suppress
        // cascading deletes during that rebuild so linked records survive.
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'enforcer'])->default('enforcer')->change();
            });
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'officer', 'enforcer'])->default('officer')->change();
            });
        });
        // Preserve converted accounts; their old role cannot be inferred safely.
    }
};
