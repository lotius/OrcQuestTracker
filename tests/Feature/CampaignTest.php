<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use App\Services\Campaigns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function campaign(?User $owner = null, string $name = 'Warband'): Campaign
    {
        return app(Campaigns::class)->create($owner ?? $this->user, $name);
    }

    private function saveInput(int $version, array $characters = []): array
    {
        return ['version' => $version, 'campaign_name' => 'Warband', 'characters' => $characters + [
            '1' => ['active' => '1', 'hero_id' => 'abgral', 'player' => 'Ribby'],
        ]];
    }

    public function test_home_opens_the_last_campaign_or_the_list(): void
    {
        $this->get('/')->assertRedirect(route('campaigns.index'));

        $campaign = $this->campaign();
        $this->get('/')->assertRedirect(route('campaigns.show', $campaign));
    }

    public function test_campaign_page_renders_with_lazy_option_lists(): void
    {
        $campaign = $this->campaign();

        $response = $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Warband');

        // Item selects ship only their placeholder until opened, so the page stays small.
        $this->assertLessThan(1_500_000, strlen($response->getContent()));
        $this->assertStringNotContainsString('<option value="BB 019 - Axe"', $response->getContent());
    }

    public function test_campaigns_are_private_to_their_owner(): void
    {
        $theirs = $this->campaign(User::factory()->create(), 'Not yours');

        $this->get(route('campaigns.show', $theirs))->assertForbidden();
        $this->postJson(route('campaigns.save', $theirs), $this->saveInput(1))->assertForbidden();
        $this->patch(route('campaigns.update', $theirs), ['name' => 'Mine now'])->assertForbidden();
        $this->delete(route('campaigns.destroy', $theirs))->assertForbidden();
        $this->get(route('campaigns.export', $theirs))->assertForbidden();
        $this->post(route('campaigns.store'), ['name' => 'Copy', 'copy_from' => $theirs->id]);

        $this->assertSame('Not yours', $theirs->fresh()->name);
        $this->assertSame([], $this->user->campaigns()->where('name', 'Copy')->first()->state['campaign']);
        $this->assertNull($this->user->campaigns()->where('name', 'Copy')->first()->state['characters']['1']['hero_id'] ?: null);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $campaign = $this->campaign();
        auth()->logout();

        $this->get(route('campaigns.show', $campaign))->assertRedirect('/login');
        $this->postJson(route('campaigns.save', $campaign), $this->saveInput(1))->assertUnauthorized();
    }

    public function test_saves_bump_the_version_and_record_history(): void
    {
        $campaign = $this->campaign();

        $this->postJson(route('campaigns.save', $campaign), $this->saveInput(1))
            ->assertOk()
            ->assertJsonPath('version', 2)
            ->assertJsonFragment(['text' => 'Chose Abgral (Brute) as the hero']);

        $this->assertSame('abgral', $campaign->fresh()->state['characters']['1']['hero_id']);
        $this->assertSame(2, $campaign->fresh()->version);
    }

    public function test_a_stale_save_is_refused_instead_of_overwriting_newer_data(): void
    {
        $campaign = $this->campaign();

        // Device A saves first...
        $this->postJson(route('campaigns.save', $campaign), $this->saveInput(1))->assertOk();

        // ...device B still holds version 1 and tries to save over it.
        $this->postJson(route('campaigns.save', $campaign), $this->saveInput(1, ['1' => ['active' => '1', 'hero_id' => 'orrus']]))
            ->assertStatus(409)
            ->assertJsonPath('version', 2);

        $this->assertSame('abgral', $campaign->fresh()->state['characters']['1']['hero_id']);
    }

    public function test_saves_are_sanitised_against_the_catalog(): void
    {
        $campaign = $this->campaign();

        $this->postJson(route('campaigns.save', $campaign), $this->saveInput(1, [
            '1' => ['active' => '1', 'hero_id' => 'abgral', 'equipment' => ['hand_item_1' => 'BB 019 - Axe', 'armor_item' => 'Not A Card'],
                'skills' => ['10' => ['(GD 004) Under Pressure', 'Some Other Hero Skill']],
                'inventory' => [['item' => 'BB 019 - Axe'], ['item' => 'WP 111 - Gold Teeth', 'left_enchant' => 'WP 189 - Brutal']]],
            '2' => ['active' => '1', 'hero_id' => 'abgral'],
        ]))->assertOk();

        $state = $campaign->fresh()->state;
        $this->assertSame('BB 019 - Axe', $state['characters']['1']['equipment']['hand_item_1']);
        $this->assertSame('', $state['characters']['1']['equipment']['armor_item'], 'unknown card dropped');
        $this->assertSame('', $state['characters']['1']['inventory'][0]['item'], 'a card cannot be in two places');
        $this->assertTrue($state['characters']['1']['inventory'][1]['rekup'], 'gold is always rekup');
        $this->assertSame('', $state['characters']['1']['inventory'][1]['left_enchant'], 'gold cannot be enchanted');
        $this->assertSame(['(GD 004) Under Pressure'], $state['characters']['1']['skills']['10']);
        $this->assertSame('', $state['characters']['2']['hero_id'], 'a hero can only be played once');
    }

    public function test_two_handed_weapons_survive_a_save(): void
    {
        $campaign = $this->campaign();

        // The browser does not submit the (disabled) mirrored second hand.
        $this->postJson(route('campaigns.save', $campaign), $this->saveInput(1, [
            '1' => ['active' => '1', 'hero_id' => 'abgral', 'equipment' => ['hand_item_1' => 'WP 167 - Crossbow']],
        ]))->assertOk();

        $equipment = $campaign->fresh()->state['characters']['1']['equipment'];
        $this->assertSame('WP 167 - Crossbow', $equipment['hand_item_1']);
        $this->assertSame('WP 167 - Crossbow', $equipment['hand_item_2']);
    }

    public function test_create_rename_duplicate_and_delete(): void
    {
        $this->post(route('campaigns.store'), ['name' => 'Sewer Fever'])->assertRedirect();
        $campaign = $this->user->campaigns()->firstWhere('name', 'Sewer Fever');

        $this->patch(route('campaigns.update', $campaign), ['name' => 'Sewer Fever II'])->assertRedirect();
        $this->assertSame('Sewer Fever II', $campaign->fresh()->name);

        $this->post(route('campaigns.duplicate', $campaign))->assertRedirect();
        $copy = $this->user->campaigns()->firstWhere('name', 'Sewer Fever II (copy)');
        $this->assertNotNull($copy);
        $this->assertGreaterThanOrEqual(2, $copy->events()->count(), 'history travels with the copy');

        $this->delete(route('campaigns.destroy', $campaign))->assertRedirect(route('campaigns.index'));
        $this->assertModelMissing($campaign);
        $this->assertModelExists($copy);
    }

    public function test_export_and_import_round_trip(): void
    {
        $campaign = $this->campaign();
        $this->postJson(route('campaigns.save', $campaign), $this->saveInput(1))->assertOk();

        $export = $this->get(route('campaigns.export', $campaign))->assertOk()->streamedContent();
        $file = UploadedFile::fake()->createWithContent('warband.json', $export);

        $this->post(route('campaigns.import'), ['file' => $file])->assertRedirect();

        $imported = $this->user->campaigns()->latest('id')->first();
        $this->assertNotSame($campaign->id, $imported->id);
        $this->assertSame('abgral', $imported->state['characters']['1']['hero_id']);
        $this->assertTrue($imported->events()->where('text', 'Imported from a campaign file')->exists());
    }

    public function test_import_rejects_unrelated_files(): void
    {
        $file = UploadedFile::fake()->createWithContent('notes.json', json_encode(['hello' => 'world']));

        $this->post(route('campaigns.import'), ['file' => $file])->assertSessionHasErrors('file');
    }
}
