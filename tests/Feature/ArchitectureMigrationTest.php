<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class ArchitectureMigrationTest extends TestCase
{
    private function legacy(bool $duplicate): int
    {
        $paths = collect(glob(database_path('migrations/*.php')))->reject(fn ($p) => str_contains($p, '2026_09_11_000002'))->map(fn ($p) => 'database/migrations/'.basename($p))->all();
        $this->artisan('migrate:fresh', ['--path'=>$paths])->assertExitCode(0);
        $user = DB::table('users')->insertGetId(['name'=>'Legacy enforcer','username'=>'legacy','email'=>'legacy@example.test','password'=>'unused','role'=>'enforcer']);
        $person=DB::table('violators')->insertGetId(['full_name'=>'  Juan  Dela Cruz ','address'=>'Historical address']);
        $id=DB::table('violations')->insertGetId(['violator_id'=>$person,'officer_id'=>$user,'violation_type_id'=>DB::table('violation_types')->value('id'),'violation_date'=>'2026-09-01','status'=>'settled','person_snapshot'=>json_encode(['full_name'=>'Juan Dela Cruz','address'=>'Historical address'])]);
        foreach(range(1,$duplicate ? 2 : 1) as $number) DB::table('citations')->insert(['violation_id'=>$id,'ticket_no'=>'LEGACY-'.$number,'fine_amount'=>500,'due_date'=>'2026-09-16','payment_status'=>'paid','paid_at'=>'2026-09-02','treasury_receipt_no'=>'OR-LEGACY']);
        return $id;
    }

    public function test_migration_preserves_history_and_leaves_unknown_receipt_dates_empty(): void
    {
        $id=$this->legacy(false);
        $before=DB::table('violations')->where('id',$id)->first();
        $migration=require database_path('migrations/2026_09_11_000002_strengthen_settlement_architecture.php');
        $migration->up();
        $this->assertEquals($before,DB::table('violations')->where('id',$id)->first());
        $this->assertDatabaseHas('citations',['violation_id'=>$id,'paid_at'=>'2026-09-02','fine_amount'=>500,'treasury_receipt_no'=>'OR-LEGACY','receipt_date'=>null]);
        $this->assertSame('juan dela cruz',DB::table('violators')->value('normalized_name'));
        $this->assertSame(0,(int)DB::table('users')->value('credential_version'));
        $this->assertSame(0,DB::table('violation_types')->whereNull('offense_key')->count());
    }

    public function test_duplicate_preflight_stops_before_schema_changes_or_data_removal(): void
    {
        $id=$this->legacy(true);
        $migration=require database_path('migrations/2026_09_11_000002_strengthen_settlement_architecture.php');
        try { $migration->up(); $this->fail('Expected duplicate preflight to reject the migration.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Duplicate payment records',$e->getMessage()); }
        $this->assertSame(2,DB::table('citations')->where('violation_id',$id)->count());
        $this->assertFalse(Schema::hasColumn('users','credential_version'));
    }
}
