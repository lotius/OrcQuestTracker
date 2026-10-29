<?php

namespace App\Services;

use Illuminate\Support\Arr;

/**
 * The shape of a campaign's tracker state and the rules that keep it valid: defaults, upgrading older
 * saves, and turning (untrusted) form input into a clean state.
 */
class CampaignState
{
    public const CHARACTER_SLOTS = 6;

    public const INVENTORY_SLOTS = 15;

    public const DEFAULT_ACTIVE_CHARACTERS = 4;

    private const ITEM_KEYS = ['hand_item_1', 'hand_item_2', 'armor_item', 'artifact_item'];

    public function __construct(private OrcQuestCatalog $catalog)
    {
    }

    public function defaults(): array
    {
        return [
            'characters' => collect(range(1, self::CHARACTER_SLOTS))->mapWithKeys(fn (int $slot) => [
                (string) $slot => $this->emptyCharacter($slot <= self::DEFAULT_ACTIVE_CHARACTERS),
            ])->all(),
            'campaign' => [],
            'notes' => '',
        ];
    }

    public function emptyCharacter(bool $active = false): array
    {
        return [
            'active' => $active,
            'player' => '',
            'hero_id' => '',
            'skills' => ['10' => [], '20' => [], '30' => []],
            'equipment' => [
                'hand_item_1_left_enchant' => '',
                'hand_item_1' => '',
                'hand_item_1_right_enchant' => '',
                'hand_item_2_left_enchant' => '',
                'hand_item_2' => '',
                'hand_item_2_right_enchant' => '',
                'armor_left_enchant' => '',
                'armor_item' => '',
                'armor_right_enchant' => '',
                'artifact_left_enchant' => '',
                'artifact_item' => '',
                'artifact_right_enchant' => '',
            ],
            'inventory' => array_fill(0, self::INVENTORY_SLOTS, $this->emptyRow()),
        ];
    }

    /** Fills in anything missing from a stored state and upgrades older formats. */
    public function normalize(array $state): array
    {
        // States saved before characters could be added/removed: keep any slot that holds data.
        foreach ($state['characters'] ?? [] as $slot => $character) {
            if (! array_key_exists('active', $character)) {
                $state['characters'][$slot]['active'] = (int) $slot <= self::DEFAULT_ACTIVE_CHARACTERS || $this->characterHasData($character);
            }
        }

        $state = array_replace_recursive($this->defaults(), $state);
        $emptyEquipment = $this->emptyCharacter()['equipment'];

        foreach ($state['characters'] as &$character) {
            $equipment = $character['equipment'] ?? [];

            // Very old saves had a single pair of hand enchants.
            if (! empty($equipment['hand_left_enchant']) && empty($equipment['hand_item_1_left_enchant'])) {
                $equipment['hand_item_1_left_enchant'] = $equipment['hand_left_enchant'];
            }

            if (! empty($equipment['hand_right_enchant']) && empty($equipment['hand_item_1_right_enchant'])) {
                $equipment['hand_item_1_right_enchant'] = $equipment['hand_right_enchant'];
            }

            $character['equipment'] = array_intersect_key(array_replace($emptyEquipment, $equipment), $emptyEquipment);
            $character['inventory'] = array_map(fn ($row) => array_replace($this->emptyRow(), (array) $row), array_slice($character['inventory'], 0, self::INVENTORY_SLOTS));

            foreach ($character['inventory'] as &$row) {
                if ($this->alwaysRekup($row['item'])) {
                    $row['rekup'] = true;
                    $row['left_enchant'] = $row['right_enchant'] = '';
                }

                if ($row['item'] === '') {
                    $row['rekup'] = false;
                    $row['left_enchant'] = $row['right_enchant'] = '';
                }
            }
        }

        return $state;
    }

    /**
     * Builds a state from validated form input. Anything not in the catalog is dropped and every card can
     * only sit in one place, so a stale or tampered request can never corrupt a campaign.
     */
    public function fromInput(array $input): array
    {
        $state = $this->defaults();
        $heroes = $this->catalog->heroes()->keyBy('id');
        $enchants = [
            'left' => $this->catalog->enchantOptions('left')->pluck('value')->flip(),
            'right' => $this->catalog->enchantOptions('right')->pluck('value')->flip(),
        ];
        $placed = [];
        $usedEnchantCodes = [];

        $item = function (?string $index) use (&$placed): string {
            if (! $index || ! $this->catalog->item($index) || isset($placed[$index])) {
                return '';
            }

            $placed[$index] = true;

            return $index;
        };

        $enchant = function (?string $value, string $side) use ($enchants, &$usedEnchantCodes): string {
            $code = explode(' - ', (string) $value)[0];

            if (! $value || ! isset($enchants[$side][$value]) || isset($usedEnchantCodes[$code])) {
                return '';
            }

            $usedEnchantCodes[$code] = true;

            return $value;
        };

        $usedHeroes = [];

        foreach ($state['characters'] as $slot => &$character) {
            $incoming = $input['characters'][$slot] ?? [];
            $heroId = $incoming['hero_id'] ?? '';
            $hero = $heroes[$heroId] ?? null;

            if ($hero && ! isset($usedHeroes[$heroId])) {
                $usedHeroes[$heroId] = true;
                $character['hero_id'] = $heroId;
            }

            $character['active'] = (bool) ($incoming['active'] ?? false);
            $character['player'] = mb_substr(trim((string) ($incoming['player'] ?? '')), 0, 80);

            foreach (['10', '20', '30'] as $tier) {
                $allowed = $character['hero_id'] ? ($hero['skills'][$tier] ?? []) : [];
                $character['skills'][$tier] = array_values(array_unique(array_intersect(array_filter((array) ($incoming['skills'][$tier] ?? [])), $allowed)));
            }

            $equipment = (array) ($incoming['equipment'] ?? []);
            $twoHanded = $this->hands($equipment['hand_item_1'] ?? '') >= 2 && ($equipment['hand_item_2'] ?? '') === ($equipment['hand_item_1'] ?? '');

            foreach (self::ITEM_KEYS as $key) {
                $prefix = preg_replace('/_item$/', '', $key);
                $value = $key === 'hand_item_2' && $twoHanded ? '' : $item($equipment[$key] ?? '');
                $character['equipment'][$key] = $value;
                $character['equipment'][$prefix.'_left_enchant'] = $enchant($equipment[$prefix.'_left_enchant'] ?? '', 'left');
                $character['equipment'][$prefix.'_right_enchant'] = $enchant($equipment[$prefix.'_right_enchant'] ?? '', 'right');
            }

            foreach ($character['inventory'] as $position => &$row) {
                $incomingRow = (array) ($incoming['inventory'][$position] ?? []);
                $row['item'] = $item($incomingRow['item'] ?? '');
                $row['rekup'] = $row['item'] !== '' && ($this->alwaysRekup($row['item']) || filter_var($incomingRow['rekup'] ?? false, FILTER_VALIDATE_BOOLEAN));
                $canEnchant = $row['item'] !== '' && ! $this->alwaysRekup($row['item']);
                $row['left_enchant'] = $canEnchant ? $enchant($incomingRow['left_enchant'] ?? '', 'left') : '';
                $row['right_enchant'] = $canEnchant ? $enchant($incomingRow['right_enchant'] ?? '', 'right') : '';
            }
            unset($row);

            $this->normalizeHands($character);
        }
        unset($character);

        $questKeys = $this->catalog->campaignQuests()->map(fn (array $quest) => md5($quest['group'].'|'.$quest['quest']))->flip();
        $state['campaign'] = collect($input['campaign'] ?? [])
            ->filter(fn ($done, $key) => isset($questKeys[$key]) && filter_var($done, FILTER_VALIDATE_BOOLEAN))
            ->map(fn () => true)
            ->all();
        $state['notes'] = (string) ($input['notes'] ?? '');

        return $state;
    }

    /** Two-handed weapons fill both hands; anything displaced moves to the first free inventory slot. */
    private function normalizeHands(array &$character): void
    {
        $equipment = &$character['equipment'];
        $first = $equipment['hand_item_1'];
        $second = $equipment['hand_item_2'];

        if ($this->hands($second) >= 2 && $second !== $first) {
            $this->moveToInventory($character, $first, $equipment['hand_item_1_left_enchant'], $equipment['hand_item_1_right_enchant']);
            $first = $second;
            $equipment['hand_item_1_left_enchant'] = $equipment['hand_item_2_left_enchant'];
            $equipment['hand_item_1_right_enchant'] = $equipment['hand_item_2_right_enchant'];
            $second = '';
        }

        if ($this->hands($first) >= 2) {
            if ($second && $second !== $first) {
                $this->moveToInventory($character, $second, $equipment['hand_item_2_left_enchant'], $equipment['hand_item_2_right_enchant']);
            }

            $equipment['hand_item_1'] = $first;
            $equipment['hand_item_2'] = $first;
            $equipment['hand_item_2_left_enchant'] = $equipment['hand_item_2_right_enchant'] = '';

            return;
        }

        $equipment['hand_item_1'] = $first;
        $equipment['hand_item_2'] = $second === $first ? '' : $second;
    }

    private function moveToInventory(array &$character, string $index, string $left, string $right): void
    {
        if ($index === '') {
            return;
        }

        foreach ($character['inventory'] as &$row) {
            if ($row['item'] === '') {
                $always = $this->alwaysRekup($index);
                $row = ['item' => $index, 'rekup' => $always, 'left_enchant' => $always ? '' : $left, 'right_enchant' => $always ? '' : $right];

                return;
            }
        }
    }

    private function emptyRow(): array
    {
        return ['item' => '', 'rekup' => false, 'left_enchant' => '', 'right_enchant' => ''];
    }

    private function hands(?string $index): int
    {
        return (int) Arr::get($this->catalog->item($index), 'hands', 0);
    }

    private function alwaysRekup(?string $index): bool
    {
        return (bool) Arr::get($this->catalog->item($index), 'always_rekup', false);
    }

    private function characterHasData(array $character): bool
    {
        return filled($character['player'] ?? '')
            || filled($character['hero_id'] ?? '')
            || collect($character['equipment'] ?? [])->filter()->isNotEmpty()
            || collect($character['inventory'] ?? [])->pluck('item')->filter()->isNotEmpty();
    }
}
