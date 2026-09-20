<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditMigrationTest extends TestCase
{
    public function test_existing_records_are_snapshotted_and_receipt_conflicts_are_preserved_for_review(): void
    {
        $paths = collect(glob(database_path('migrations/*.php')))->reject(fn ($path) => str_contains($path, '2026_09_11_'))->map(fn ($path) => 'database/migrations/'.basename($path))->all();
        $this->artisan('migrate:fresh', ['--path' => $paths])->assertExitCode(0);
        $user = DB::table('users')->insertGetId(['name' => 'Historic Enforcer', 'username' => 'historic', 'email' => 'historic@example.test', 'password' => 'unused', 'role' => 'enforcer']);
        $type = DB::table('violation_types')->value('id');
        $ids = [];
        foreach (['First Person', 'Second Person'] as $name) {
            $person = DB::table('violators')->insertGetId(['full_name' => $name, 'address' => 'Saved legacy address']);
            $id = DB::table('violations')->insertGetId(['violator_id' => $person, 'officer_id' => $user, 'violation_type_id' => $type, 'violation_date' => '2026-09-01', 'status' => 'settled']);
            DB::table('citations')->insert(['violation_id' => $id, 'ticket_no' => 'LEGACY-'.$id, 'fine_amount' => 100, 'due_date' => '2026-09-16', 'payment_status' => 'paid', 'paid_at' => '2026-09-02', 'treasury_receipt_no' => 'OR-SHARED']);
            $ids[] = $id;
        }
        $migration = require database_path('migrations/2026_09_11_000001_preserve_incident_details_and_receipt_allocations.php');
        $migration->up();
        $this->assertSame(2, DB::table('violations')->count());
        $this->assertSame(2, DB::table('citations')->where('paid_at', '2026-09-02')->count());
        $this->assertSame(2, DB::table('violations')->where('officer_id', $user)->count());
        $this->assertSame('Saved legacy address', json_decode(DB::table('violations')->where('id', $ids[0])->value('person_snapshot'), true)['address']);
        $this->assertSame(2, DB::table('violations')->where('snapshot_source', 'legacy')->count());
        $this->assertSame(1, DB::table('treasury_receipts')->count());
        $this->assertNull(DB::table('treasury_receipts')->value('violator_id'));
        $this->assertTrue((bool) DB::table('treasury_receipts')->value('needs_review'));
        $this->assertSame(2, DB::table('citations')->whereNotNull('treasury_receipt_id')->count());
    }
}