<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->json('person_snapshot')->nullable();
            $table->string('snapshot_source', 20)->default('captured');
            $table->string('public_access_hash', 64)->nullable()->unique();
            $table->string('submission_token', 40)->nullable()->unique();
        });
        // Historical incident details cannot be reconstructed. Preserve the
        // available profile and explicitly mark this as a legacy snapshot.
        DB::table('violations')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $person = DB::table('violators')->where('id', $row->violator_id)->first();
                $snapshot = [];
                foreach (['full_name', 'address', 'vehicle_plate', 'license_no', 'vehicle_type', 'contact_no', 'birthdate'] as $field) {
                    $snapshot[$field] = $person?->$field;
                }
                DB::table('violations')->where('id', $row->id)->update(['person_snapshot' => json_encode($snapshot), 'snapshot_source' => 'legacy']);
            }
        });
        Schema::create('treasury_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference_key', 100)->unique();
            $table->string('receipt_no', 100);
            $table->foreignId('violator_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestamps();
        });
        Schema::table('citations', function (Blueprint $table) {
            $table->foreignId('treasury_receipt_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('verification_version')->default(0);
        });
        DB::table('citations')->whereNotNull('treasury_receipt_no')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $key = strtoupper(preg_replace('/[\s-]+/', '', $row->treasury_receipt_no));
                if ($key === '') { continue; }
                $personId = DB::table('violations')->where('id', $row->violation_id)->value('violator_id');
                $receipt = DB::table('treasury_receipts')->where('reference_key', $key)->first();
                if (!$receipt) {
                    $id = DB::table('treasury_receipts')->insertGetId(['reference_key' => $key, 'receipt_no' => $row->treasury_receipt_no, 'violator_id' => $personId, 'total_amount' => null, 'needs_review' => true, 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    $id = $receipt->id;
                    if ($receipt->violator_id != $personId) {
                        DB::table('treasury_receipts')->where('id', $id)->update(['violator_id' => null, 'needs_review' => true]);
                    }
                }
                DB::table('citations')->where('id', $row->id)->update(['treasury_receipt_id' => $id]);
            }
        });
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('violation_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->json('before_state');
            $table->json('after_state');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::table('citations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treasury_receipt_id');
            $table->dropColumn('verification_version');
        });
        Schema::dropIfExists('treasury_receipts');
        Schema::table('violations', function (Blueprint $table) {
            $table->dropUnique(['public_access_hash']);
            $table->dropUnique(['submission_token']);
            $table->dropColumn(['person_snapshot', 'snapshot_source', 'public_access_hash', 'submission_token']);
        });
    }
};
