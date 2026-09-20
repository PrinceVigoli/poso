<?php

namespace Tests\Feature;

use Tests\TestCase;

class OrdinanceLandingTest extends TestCase
{
    public function test_landing_page_lists_the_published_ordinances(): void
    {
        $response = $this->get(route('home'))->assertOk()
            ->assertSee('Ordinances enforced in Luna')
            ->assertSee('MUNICIPAL ORDINANCES');

        foreach (config('ordinances.ordinances') as $ordinance) {
            $response->assertSee('Ordinance No. '.$ordinance['number'])
                ->assertSee('series of '.$ordinance['year'])
                ->assertSee($ordinance['title']);
        }
    }

    /** The point of the section is the content, not a pointer to a document. */
    public function test_provisions_and_penalties_are_rendered_on_the_page_itself(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (config('ordinances.ordinances') as $ordinance) {
            foreach ($ordinance['provisions'] as $provision) {
                $response->assertSee($provision);
            }
            foreach ($ordinance['penalties'] as $penalty) {
                $response->assertSee($penalty['offense'])->assertSee($penalty['penalty']);
            }
        }
    }

    public function test_known_penalty_amounts_appear_verbatim(): void
    {
        // Spot-checks transcribed from the signed scans. If a figure is ever
        // edited in config, this fails loudly rather than silently misinforming.
        $this->get(route('home'))->assertOk()
            ->assertSee('Confiscation of the noisy muffler plus ₱300.00 fine')       // 347 s.2017
            ->assertSee('₱250.00 or 8 hours community service')                       // 362 s.2018
            ->assertSee('₱500.00 for the first 4 km, plus ₱50.00 for each succeeding km to the impounding area') // 403 s.2020
            ->assertSee('₱2,500.00 and impoundment of the tricycle, plus ₱50.00 per day impounding fee'); // 541 s.2024
    }

    public function test_every_ordinance_has_a_known_theme_and_required_fields(): void
    {
        $themes = array_keys(config('ordinances.themes'));
        foreach (config('ordinances.ordinances') as $ordinance) {
            foreach (['number', 'year', 'title', 'summary', 'theme'] as $field) {
                $this->assertNotEmpty($ordinance[$field] ?? null, "Ordinance is missing '{$field}'.");
            }
            $this->assertNotEmpty($ordinance['provisions'] ?? [], "Ordinance No. {$ordinance['number']} has no provisions.");
            $this->assertContains($ordinance['theme'], $themes, "Ordinance No. {$ordinance['number']} uses an unknown theme.");

            foreach ($ordinance['penalties'] as $penalty) {
                $this->assertNotEmpty($penalty['offense'] ?? null, "A penalty on No. {$ordinance['number']} has no offense.");
                $this->assertNotEmpty($penalty['penalty'] ?? null, "A penalty on No. {$ordinance['number']} has no amount.");
            }
        }
    }

    public function test_each_ordinance_is_uniquely_identified(): void
    {
        $refs = array_map(fn ($o) => $o['number'].'-'.$o['year'], config('ordinances.ordinances'));
        $this->assertSame(array_values(array_unique($refs)), $refs, 'Duplicate ordinance number/year pairs.');
    }

    public function test_landing_page_states_the_signed_ordinance_is_the_official_text(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('the signed ordinance on file with the Sangguniang Bayan is the official text', false);
    }

    public function test_citizens_reach_the_ordinances_without_signing_in(): void
    {
        $this->assertGuest();
        $this->get(route('home'))->assertOk()->assertSee('Ordinances enforced in Luna');
    }
}
