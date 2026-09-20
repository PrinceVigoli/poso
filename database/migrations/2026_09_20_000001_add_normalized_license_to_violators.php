<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    /**
     * Citizens may now look a record up by licence or plate number as well as
     * by name. Both are written inconsistently (N01-23-456789 / N0123456789,
     * ABC 123 / ABC-123), so store an indexed comparison form beside each raw
     * value, exactly as normalized_name already does for full_name.
     *
     * Additive and reversible. The original license_no and vehicle_plate
     * columns are never modified.
     */
    public function up(): void
    {
        Schema::table('violators', function (Blueprint $t) {
            $t->string('normalized_license')->nullable()->index();
            $t->string('normalized_plate')->nullable()->index();
        });

        DB::table('violators')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('violators')->where('id', $row->id)->update([
                    'normalized_license' => $this->squash($row->license_no),
                    'normalized_plate' => $this->squash($row->vehicle_plate),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('violators', fn (Blueprint $t) => $t->dropColumn(['normalized_license', 'normalized_plate']));
    }

    /** Blank stays null, so records without one never match each other. */
    private function squash(?string $value): ?string
    {
        $squashed = strtoupper(preg_replace('/[\s-]+/', '', (string) $value));

        return $squashed === '' ? null : $squashed;
    }
};
