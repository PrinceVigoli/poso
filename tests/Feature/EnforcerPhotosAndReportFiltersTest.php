<?php

namespace Tests\Feature;

use App\Models\{Citation, PaymentEvent, User, Violation, ViolationType, Violator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnforcerPhotosAndReportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'enforcer'): User
    {
        $name = $role.User::count();
        return User::create(['name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password', 'role' => $role]);
    }

    private function input(): array
    {
        return ['location' => 'Poblacion checkpoint', 'top_number' => '000123-TOP', 'full_name' => 'Minor Test', 'address' => 'Luna', 'confiscated_id' => 'None',
            'violation_type_id' => ViolationType::create(['offense_name' => 'No helmet', 'fine_amount' => 500])->id];
    }

    public function test_ticket_and_photos_survive_match_duplicate_edit_and_confirmation_with_private_access(): void
    {
        Storage::fake('local');
        $owner = $this->account();
        $this->actingAs($owner);
        $input = $this->input();
        $person = Violator::create(['full_name' => $input['full_name']]);
        Violation::create(['violator_id' => $person->id, 'officer_id' => $owner->id, 'violation_type_id' => $input['violation_type_id'], 'violation_date' => today()]);
        $this->post(route('enforcer.preview'), $input + ['minor_photos' => [UploadedFile::fake()->image('minor.jpg')]])
            ->assertViewIs('violations.confirm_match')->assertSee('000123-TOP');
        $input['photo_token'] = session('enforcer_input.photo_token');
        $input['matched_violator_id'] = $person->id;
        $this->post(route('enforcer.preview'), $input)->assertViewIs('violations.confirm_duplicate');
        $input['photo_token'] = session('enforcer_input.photo_token');
        $input['confirm_duplicate'] = 1;
        $this->post(route('enforcer.preview'), $input)->assertRedirect(route('enforcer.review'));
        $this->get(route('enforcer.review'))->assertOk()->assertSee('000123-TOP')->assertSee('Attached picture 1');
        $this->post(route('enforcer.edit'))->assertRedirect(route('enforcer.create'));
        $this->get(route('enforcer.create'))->assertSee('1 picture(s) attached.');
        $input['photo_token'] = session('enforcer_input.photo_token');
        $this->post(route('enforcer.preview'), $input)->assertRedirect(route('enforcer.review'));
        $draft = session('enforcer_preview');
        $this->get(route('enforcer.photo', ['token' => $draft['input']['photo_token'], 'photo' => 0]))->assertOk();
        $this->post(route('enforcer.confirm'), ['preview_token' => $draft['token'], 'confirmed' => 1, 'top_number' => 'TAMPERED'])->assertSessionHasNoErrors();
        $v = Violation::latest('id')->firstOrFail();
        $this->assertSame('000123-TOP', $v->citation->ticket_no);
        $this->assertCount(1, $v->minor_photos);
        Storage::disk('local')->assertExists($v->minor_photos[0]);
        $url = route('violations.minor-photo', ['violation' => $v, 'photo' => 0]);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->account())->get($url)->assertForbidden();
        $this->get(route('enforcer.photo', ['token' => $draft['input']['photo_token'], 'photo' => 0]))->assertNotFound();
        $this->actingAs($this->account('admin'))->get($url)->assertOk();
        $this->get(route('violations.show', $v))->assertOk()->assertSee('000123-TOP')->assertSee('Attached picture 1');
        $this->get(route('violations.minor-photo', ['violation' => $v, 'photo' => 7]))->assertNotFound();
        $this->post(route('logout'));
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_ticket_is_required_unique_and_photos_are_optional_and_validated(): void
    {
        Storage::fake('local');
        $this->actingAs($this->account());
        $input = $this->input();
        $this->post(route('enforcer.preview'), array_replace($input, ['top_number' => '']))->assertSessionHasErrors('top_number');
        $this->post(route('enforcer.preview'), $input + ['minor_photos' => [UploadedFile::fake()->create('document.pdf')]])->assertSessionHasErrors('minor_photos.0');
        $this->post(route('enforcer.preview'), $input + ['minor_photos' => array_fill(0, 6, UploadedFile::fake()->image('minor.png'))])->assertSessionHasErrors('minor_photos');
        $this->post(route('enforcer.preview'), $input + ['minor_photos' => [UploadedFile::fake()->image('large.jpg')->size(5121)]])->assertSessionHasErrors('minor_photos.0');
        $this->post(route('enforcer.preview'), $input + ['photo_token' => str_repeat('x', 40)])->assertSessionHasErrors('minor_photos');
        $this->post(route('enforcer.preview'), $input)->assertRedirect(route('enforcer.review'));
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame([], Violation::firstOrFail()->minor_photos);
        $this->post(route('enforcer.preview'), $input)->assertSessionHasErrors('top_number');
    }

    public function test_removed_photos_are_not_saved_and_abandoned_uploads_are_pruned(): void
    {
        Storage::fake('local');
        $this->actingAs($this->account());
        $input = $this->input();
        $this->post(route('enforcer.preview'), $input + ['minor_photos' => [UploadedFile::fake()->image('minor.jpg')]])->assertRedirect();
        $paths = session('enforcer_preview.minor_photos');
        $input['photo_token'] = session('enforcer_input.photo_token');
        $this->post(route('enforcer.edit'));
        $this->post(route('enforcer.preview'), $input + ['remove_photos' => 1])->assertRedirect();
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame([], Violation::firstOrFail()->minor_photos);
        Storage::disk('local')->put('minor-photos/retained.jpg', 'test');
        Violation::firstOrFail()->update(['minor_photos' => ['minor-photos/retained.jpg']]);
        $this->travel(2)->days();
        $this->artisan('poso:prune-minor-photos')->assertSuccessful();
        Storage::disk('local')->assertMissing($paths[0]);
        Storage::disk('local')->assertExists('minor-photos/retained.jpg');
        $this->travelBack();
    }

    public function test_report_filters_cover_records_settlements_events_and_csv_including_retired_offenses(): void
    {
        $admin = $this->account('admin');
        $owner = $this->account();
        $helmet = ViolationType::create(['offense_name' => 'No helmet', 'fine_amount' => 500]);
        $parking = ViolationType::create(['offense_name' => 'Illegal parking', 'fine_amount' => 200]);
        $retired = ViolationType::create(['offense_name' => 'No helmet (old)', 'fine_amount' => 300, 'offense_key' => $helmet->offense_key]);
        $retired->delete();
        $ids = [];
        foreach ([[$helmet, 'pending', 'Helmet Unpaid'], [$retired, 'paid', 'Helmet Paid'], [$parking, 'paid', 'Parking Paid']] as [$type, $status, $name]) {
            $person = Violator::create(['full_name' => $name, 'address' => 'Luna']);
            $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $owner->id, 'violation_type_id' => $type->id, 'violation_date' => today()]);
            Citation::create(['violation_id' => $v->id, 'ticket_no' => 'TOP-'.$v->id, 'fine_amount' => 500, 'payment_status' => $status, 'paid_at' => $status === 'paid' ? today() : null, 'due_date' => today()->addDays(15)]);
            PaymentEvent::create(['violation_id' => $v->id, 'user_id' => $admin->id, 'action' => 'verified', 'before_state' => [], 'after_state' => []]);
            $ids[] = $v->id;
        }
        $this->actingAs($admin);
        $query = ['type' => $helmet->id];
        $this->get(route('reports.period', $query))->assertOk()
            ->assertViewHas('records', fn ($rows) => $rows->pluck('id')->all() === [$ids[0], $ids[1]])
            ->assertViewHas('settlements', fn ($rows) => $rows->pluck('id')->all() === [$ids[1]])
            ->assertViewHas('paymentEvents', fn ($rows) => $rows->total() === 2)
            ->assertSee('Unpaid')->assertSee('Paid')->assertDontSee('Paid, Parking');
        foreach (['paid' => $ids[1], 'unpaid' => $ids[0]] as $status => $id) {
            $this->get(route('reports.period', $query + ['payment_status' => $status]))->assertOk()
                ->assertViewHas('records', fn ($rows) => $rows->pluck('id')->all() === [$id])
                ->assertViewHas('settlements', fn ($rows) => $rows->total() === ($status === 'paid' ? 1 : 0))
                ->assertViewHas('paymentEvents', fn ($rows) => $rows->pluck('violation_id')->all() === [$id]);
            $csv = $this->get(route('reports.period', $query + ['payment_status' => $status, 'download' => 'csv']))->assertOk()->streamedContent();
            $this->assertStringContainsString(ucfirst($status).', Helmet', $csv);
            $this->assertStringNotContainsString('Paid, Parking', $csv);
            $this->assertStringNotContainsString(($status === 'paid' ? 'Unpaid' : 'Paid').', Helmet', $csv);
        }
        $this->get(route('reports.period', ['type' => 999999]))->assertSessionHasErrors('type');
        $this->get(route('reports.period', ['payment_status' => 'invalid']))->assertSessionHasErrors('payment_status');
    }
    public function test_custom_and_annual_reports_apply_inclusive_boundaries_to_every_section_and_csv(): void
    {
        $admin = $this->account('admin');
        $type = ViolationType::create(['offense_name' => 'No helmet', 'fine_amount' => 500]);
        $ids = [];
        foreach (['2025-12-31', '2026-01-01', '2026-09-30', '2026-10-01', '2026-12-31', '2027-01-01'] as $date) {
            $person = Violator::create(['full_name' => 'Person, '.$date]);
            $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $admin->id, 'violation_type_id' => $type->id, 'violation_date' => $date]);
            Citation::create(['violation_id' => $v->id, 'ticket_no' => 'RANGE-'.$v->id, 'treasury_receipt_no' => 'RANGE-'.$v->id, 'fine_amount' => 500, 'payment_status' => 'paid', 'paid_at' => $date.' 23:59:59', 'due_date' => $date]);
            $event = new PaymentEvent(['violation_id' => $v->id, 'user_id' => $admin->id, 'action' => 'verified', 'before_state' => [], 'after_state' => []]);
            $event->created_at = $date.' 23:59:59';
            $event->save();
            $ids[] = $v->id;
        }
        $this->actingAs($admin);
        foreach ([
            [['period' => 'custom', 'date_from' => '2026-01-01', 'date_to' => '2026-09-30'], [$ids[1], $ids[2]]],
            [['period' => 'annual', 'year' => 2026], array_slice($ids, 1, 4)],
            [['period' => 'custom', 'date_from' => '2026-09-30', 'date_to' => '2026-09-30'], [$ids[2]]],
        ] as [$query, $expected]) {
            $this->get(route('reports.period', $query))->assertOk()
                ->assertViewHas('records', fn ($rows) => $rows->pluck('id')->all() === $expected)
                ->assertViewHas('settlements', fn ($rows) => $rows->pluck('id')->all() === $expected)
                ->assertViewHas('paymentEvents', fn ($rows) => $rows->pluck('violation_id')->all() === $expected);
            $csv = $this->get(route('reports.period', $query + ['download' => 'csv']))->assertOk()->streamedContent();
            foreach ($ids as $id) {
                if (in_array($id, $expected)) $this->assertStringContainsString('RANGE-'.$id, $csv);
                else $this->assertStringNotContainsString('RANGE-'.$id, $csv);
            }
        }
        $this->get(route('reports.period', ['period' => 'custom']))->assertSessionHasErrors(['date_from', 'date_to']);
        $this->get(route('reports.period', ['period' => 'custom', 'date_from' => '2026-09-30', 'date_to' => '2026-01-01']))->assertSessionHasErrors('date_to');
    }

    public function test_surname_first_names_are_displayed_and_searchable_in_either_order(): void
    {
        $admin = $this->account('admin');
        $type = ViolationType::create(['offense_name' => 'No helmet', 'fine_amount' => 500]);
        $person = Violator::create(['full_name' => 'Juan dela Cruz']);
        $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $admin->id, 'violation_type_id' => $type->id, 'violation_date' => today()]);
        $this->assertSame('dela Cruz, Juan', $person->display_name);
        $this->assertSame('dela Cruz, Juan', $v->personDetail('full_name'));
        $this->assertSame('juan dela cruz', \App\Support\PersonName::normalized('dela Cruz, Juan'));
        $this->actingAs($admin);
        foreach (['dela Cruz, Juan', 'dela Cruz Juan', 'Juan dela Cruz', 'dela Cruz'] as $term) {
            $this->get(route('violations.index', ['search' => $term]))->assertOk()->assertViewHas('violations', fn ($rows) => $rows->pluck('id')->all() === [$v->id]);
            $this->get(route('violators.index', ['status' => 'all', 'search' => $term]))->assertOk()->assertSee('dela Cruz, Juan');
        }
        $this->post(route('search.lookup'), ['query' => 'dela Cruz, Juan'])->assertRedirect();
        $this->get(route('search.results'))->assertOk()->assertViewHas('records', fn ($rows) => $rows->pluck('id')->all() === [$v->id]);
        $this->actingAs($this->account())->get(route('enforcer.create'))->assertOk()->assertSee('Open camera')->assertSee('enforcer-camera.js')->assertDontSee('type="file" class="form-control"', false);
    }
}
