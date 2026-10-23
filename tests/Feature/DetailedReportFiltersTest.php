<?php

namespace Tests\Feature;

use App\Models\{Citation, PaymentEvent, User, Violation, ViolationType, Violator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetailedReportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $admin = User::create(['name' => 'Report Admin', 'username' => 'report-admin', 'email' => 'report@example.test', 'password' => 'password', 'role' => 'admin']);
        $other = User::create(['name' => 'Other Enforcer', 'username' => 'other', 'email' => 'other@example.test', 'password' => 'password', 'role' => 'enforcer', 'is_active' => false]);
        $type = ViolationType::create(['offense_name' => 'No helmet', 'fine_amount' => 500]);
        $rows = [];
        foreach ([[$admin, 'Juan dela Cruz', 'Poblacion', 'ABC-123', 'Market checkpoint', 'Student ID', 500], [$other, 'Maria Santos', 'San Jose', 'XYZ-987', 'School gate', 'None', 900]] as [$officer, $name, $address, $plate, $location, $id, $fine]) {
            $person = Violator::create(['full_name' => $name, 'address' => $address, 'vehicle_plate' => $plate]);
            $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $officer->id, 'violation_type_id' => $type->id, 'violation_date' => today(), 'location' => $location, 'confiscated_id' => $id]);
            Citation::create(['violation_id' => $v->id, 'ticket_no' => 'TOP-'.$v->id, 'treasury_receipt_no' => 'OR-'.$v->id, 'fine_amount' => $fine, 'payment_status' => 'paid', 'paid_at' => today(), 'due_date' => today()->addDays(15)]);
            PaymentEvent::create(['violation_id' => $v->id, 'user_id' => $admin->id, 'action' => 'verified', 'before_state' => [], 'after_state' => []]);
            $rows[] = $v;
        }
        $this->actingAs($admin);
        return $rows;
    }

    public function test_no_helmet_and_selected_dates_exclude_other_offenses_and_dates(): void
    {
        [$helmet, $other] = $this->records();
        $parking = ViolationType::create(['offense_name' => 'Illegal parking', 'fine_amount' => 200]);
        $other->update(['violation_type_id' => $parking->id]);
        $outsideDate = $helmet->replicate();
        $outsideDate->violation_date = today()->subDay();
        $outsideDate->save();
        foreach ([
            ['period' => 'daily', 'date' => today()->toDateString()],
            ['period' => 'custom', 'date_from' => today()->toDateString(), 'date_to' => today()->addDay()->toDateString()],
        ] as $dates) {
            $query = $dates + ['type' => $helmet->violation_type_id];
            $this->get(route('reports.period', $query))->assertOk()
                ->assertSee('for="date_from">From', false)
                ->assertSee('for="date_to">To', false)
                ->assertDontSee('More filters')
                ->assertDontSee('Date within the period')
                ->assertViewHas('records', fn ($rows) => $rows->pluck('id')->all() === [$helmet->id])
                ->assertViewHas('settlements', fn ($rows) => $rows->pluck('id')->all() === [$helmet->id])
                ->assertViewHas('paymentEvents', fn ($rows) => $rows->pluck('violation_id')->all() === [$helmet->id]);
            $csv = $this->get(route('reports.period', $query + ['download' => 'csv']))->assertOk()->streamedContent();
            $this->assertStringContainsString('No helmet', $csv);
            $this->assertStringNotContainsString('Illegal parking', $csv);
            $this->assertStringNotContainsString('Apprehension,'.$outsideDate->id.',', $csv);
        }
    }

    public function test_report_dropdown_matches_current_offense_catalog_and_still_finds_historical_records(): void
    {
        [$record] = $this->records();
        $oldType = $record->violationType;
        $oldType->delete();
        $currentType = ViolationType::create(['offense_name' => 'Amended helmet offense', 'fine_amount' => 600, 'offense_key' => $oldType->offense_key]);
        $inactive = ViolationType::create(['offense_name' => 'Inactive catalog offense', 'fine_amount' => 200, 'is_active' => false]);
        $this->get(route('reports.period', ['type' => $currentType->id]))->assertOk()
            ->assertViewHas('violationTypes', function ($types) use ($oldType, $currentType, $inactive) {
                return !$types->contains('id', $oldType->id)
                    && $types->contains('id', $currentType->id)
                    && $types->contains('id', $inactive->id)
                    && $types->count() === ViolationType::count();
            })
            ->assertViewHas('records', fn ($rows) => $rows->pluck('id')->contains($record->id));
    }
}
