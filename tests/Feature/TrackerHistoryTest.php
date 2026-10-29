<?php

namespace Tests\Feature;

use App\Services\OrcQuestCatalog;
use App\Services\TrackerHistory;
use App\Services\CampaignState;
use Tests\TestCase;

class TrackerHistoryTest extends TestCase
{
    private TrackerHistory $history;

    private array $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = app(TrackerHistory::class);
        $this->base = app(CampaignState::class)->defaults();
        $this->base['characters']['1']['hero_id'] = 'abgral';
    }

    private function texts(array $before, array $after, array $context = []): array
    {
        return array_column($this->history->diff($before, $after, $context), 'text');
    }

    public function test_items_have_kraft_levels(): void
    {
        $catalog = app(OrcQuestCatalog::class);

        $this->assertSame([1, 1, 2, 3], $catalog->items()->where('name', 'Bow')->pluck('level')->all());
        $this->assertSame('BB 019 - Axe ★', $catalog->item('BB 019 - Axe')['label']);
        $this->assertNull($catalog->item('WP 111 - Gold Teeth')['level']);
    }

    public function test_records_added_moved_and_removed_items(): void
    {
        $after = $this->base;
        $after['characters']['1']['inventory'][0]['item'] = 'BB 019 - Axe';
        $this->assertSame(['Added BB 019 - Axe ★ to Inventory 1'], $this->texts($this->base, $after));

        $moved = $this->base;
        $moved['characters']['1']['equipment']['hand_item_1'] = 'BB 019 - Axe';
        $this->assertSame(['Moved BB 019 - Axe ★ from Inventory 1 to Hand 1'], $this->texts($after, $moved));

        $this->assertSame(['Removed BB 019 - Axe ★ from Hand 1'], $this->texts($moved, $this->base));
    }

    public function test_records_trades_between_characters(): void
    {
        $before = $this->base;
        $before['characters']['2']['hero_id'] = 'orrus';
        $before['characters']['1']['inventory'][0]['item'] = 'BB 019 - Axe';
        $after = $before;
        $after['characters']['1']['inventory'][0]['item'] = '';
        $after['characters']['2']['inventory'][3]['item'] = 'BB 019 - Axe';

        $events = $this->history->diff($before, $after);

        $this->assertSame('Received BB 019 - Axe ★ from Abgral (Inventory 4)', $events[0]['text']);
        $this->assertSame('Orrus', $events[0]['who']);
    }

    public function test_records_rekup_enchants_and_skills(): void
    {
        $before = $this->base;
        $before['characters']['1']['inventory'][0]['item'] = 'BB 019 - Axe';
        $after = $before;
        $after['characters']['1']['inventory'][0]['rekup'] = true;
        $after['characters']['1']['inventory'][0]['left_enchant'] = 'WP 189 - Brutal';
        $after['characters']['1']['skills']['10'] = ['(GD 004) Under Pressure'];

        $this->assertSame([
            'Learned (GD 004) Under Pressure (10 BP)',
            'Marked BB 019 - Axe ★ for rekup',
            'Enchanted BB 019 - Axe ★ with WP 189 - Brutal (left)',
        ], $this->texts($before, $after));
    }

    public function test_records_upgrades_and_kraft_payments_without_duplicate_removals(): void
    {
        $before = $this->base;
        $before['characters']['1']['equipment']['hand_item_1'] = 'BB 019 - Axe';
        $before['characters']['1']['inventory'][0] = ['item' => 'WP 111 - Gold Teeth', 'rekup' => true, 'left_enchant' => '', 'right_enchant' => ''];
        $before['characters']['1']['inventory'][1] = ['item' => 'WP 112 - Gold Teeth', 'rekup' => true, 'left_enchant' => '', 'right_enchant' => ''];
        $after = $before;
        $after['characters']['1']['equipment']['hand_item_1'] = 'BB 021 - Axe';
        $after['characters']['1']['inventory'][0]['item'] = '';
        $after['characters']['1']['inventory'][1]['item'] = '';

        $texts = $this->texts($before, $after, ['kraft' => [
            'text' => 'Krafted BB 019 - Axe ★ into BB 021 - Axe ★★',
            'slot' => '1',
            'consumed' => ['WP 111 - Gold Teeth', 'WP 112 - Gold Teeth'],
        ]]);

        $this->assertSame([
            'Krafted BB 019 - Axe ★ into BB 021 - Axe ★★',
            'Upgraded BB 019 - Axe ★ to BB 021 - Axe ★★ (Hand 1)',
        ], $texts);
    }

    public function test_two_handed_mirror_is_not_a_second_card(): void
    {
        $after = $this->base;
        $after['characters']['1']['equipment']['hand_item_1'] = 'WP 074 - Bow';
        $after['characters']['1']['equipment']['hand_item_2'] = 'WP 074 - Bow';

        $this->assertSame(['Added WP 074 - Bow ★ to Hand 1'], $this->texts($this->base, $after));
    }

    public function test_records_characters_joining_and_leaving_and_quests(): void
    {
        $before = $this->base;
        $after = $before;
        $after['characters']['5']['active'] = true;
        $this->assertSame(['Character 5 joined the warband'], $this->texts($before, $after));

        $before['characters']['1']['active'] = true;
        $after = $before;
        $after['characters']['1'] = app(CampaignState::class)->defaults()['characters']['1'];
        $after['characters']['1']['active'] = false;
        $this->assertSame(['Abgral left the warband'], $this->texts($before, $after));

        $quest = app(OrcQuestCatalog::class)->campaignQuests()->first();
        $after = $this->base;
        $after['campaign'][md5($quest['group'].'|'.$quest['quest'])] = true;
        $this->assertSame(["Completed {$quest['quest']} ({$quest['group']})"], $this->texts($this->base, $after));
    }

    public function test_tags_undo_events(): void
    {
        $after = $this->base;
        $after['notes'] = 'Mek owes Orrus a pie';
        $events = $this->history->diff($this->base, $after, ['source' => 'undo']);

        $this->assertSame('undo', $events[0]['source']);
        $this->assertSame('notes', $events[0]['coalesce']);
    }
}
