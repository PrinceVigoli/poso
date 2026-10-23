<?php
namespace App\Support;

use App\Models\{AuditLog, Violation};
use Illuminate\Support\Facades\DB;

class CitizenAccess
{
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $code));
    }

    public static function issue(Violation $violation, string $reason): string
    {
        return DB::transaction(function () use ($violation, $reason) {
            $record = Violation::lockForUpdate()->findOrFail($violation->id);
            $code = strtoupper(bin2hex(random_bytes(16)));
            $record->update(['public_access_hash' => hash('sha256', $code)]);
            AuditLog::record('citizen_code_issued', 'violations', "Private citizen access replaced for record #{$record->id}. Reason: {$reason}");
            return implode('-', str_split($code, 8));
        });
    }
}
