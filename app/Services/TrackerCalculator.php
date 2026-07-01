<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class TrackerCalculator
{
    public function __construct(private OrcQuestCatalog $catalog)
    {
    }

    public function characterSummary(array $character): array
    {
        $hero = $this->catalog->hero($character['hero_id'] ?? null);
        $inventoryWeight = 0;
        $handWeight = 0;
        $armorWeight = 0;
        $ownedItems = $this->ownedItems($character);

        foreach ($character['inventory'] ?? [] as $row) {
            $item = $this->catalog->item($row['item'] ?? null);

            if (($row['rekup'] ?? false) === true || ($item['always_rekup'] ?? false) === true) {
                $inventoryWeight += 1;
                continue;
            }

            $inventoryWeight += (int) Arr::get($item, 'weight', 0);
        }

        foreach (['hand_item_1', 'hand_item_2'] as $key) {
            $itemIndex = Arr::get($character, "equipment.$key");
            if ($key === 'hand_item_2' && $itemIndex && $itemIndex === Arr::get($character, 'equipment.hand_item_1')) {
                continue;
            }

            $handWeight += (int) Arr::get($this->catalog->item($itemIndex), 'weight', 0);
        }

        $armorWeight = (int) Arr::get($this->catalog->item(Arr::get($character, 'equipment.armor_item')), 'weight', 0);

        return [
            'hero' => $hero,
            'inventory_weight' => $inventoryWeight,
            'inventory_limit' => (int) Arr::get($hero, 'inventory_weight_limit', 0),
            'hand_weight' => $handWeight,
            'hand_limit' => (int) Arr::get($hero, 'hand_weight_limit', 0),
            'armor_weight' => $armorWeight,
            'armor_limit' => (int) Arr::get($hero, 'armor_weight_limit', 0),
            'owned_count' => $ownedItems->count(),
        ];
    }

    public function resources(array $state): array
    {
        $totals = ['wood' => 0, 'metal' => 0, 'leather' => 0, 'gold' => 0];
        $byCharacter = [];

        foreach ($state['characters'] as $slot => $character) {
            $row = ['wood' => 0, 'metal' => 0, 'leather' => 0, 'gold' => 0];

            foreach ($character['inventory'] ?? [] as $inventoryRow) {
                $item = $this->catalog->item($inventoryRow['item'] ?? null);

                if (! (($inventoryRow['rekup'] ?? false) || ($item['always_rekup'] ?? false) === true)) {
                    continue;
                }

                foreach ($row as $resource => $amount) {
                    $row[$resource] += (int) Arr::get($item, "rekup.$resource", 0);
                }
            }

            foreach ($row as $resource => $amount) {
                $totals[$resource] += $amount;
            }

            $byCharacter[$slot] = $row;
        }

        return ['totals' => $totals, 'by_character' => $byCharacter];
    }

    public function eligibleUpgrades(array $state): Collection
    {
        $resources = $this->resources($state)['totals'];

        return collect($state['characters'])->flatMap(function (array $character, string $slot) use ($resources) {
            $hero = $this->catalog->hero($character['hero_id'] ?? null);
            $heroName = $hero['name'] ?? 'Character '.$slot;

            return $this->ownedItems($character)
                ->filter(fn (array $owned) => ! $owned['rekup'])
                ->map(fn (array $owned) => $owned + ['item' => $this->catalog->item($owned['index'])])
                ->filter(fn (array $owned) => ($owned['item']['can_upgrade'] ?? false) === true)
                ->filter(fn (array $owned) => collect($owned['item']['kraft'])->every(
                    fn (int|float $cost, string $resource) => $cost <= $resources[$resource]
                ))
                ->map(fn (array $owned) => [
                    'character' => $heroName,
                    'location' => $owned['location'],
                    'item' => $owned['item'],
                ]);
        })->values();
    }

    public function unusedHeroes(array $state): Collection
    {
        $used = collect($state['characters'])->pluck('hero_id')->filter()->all();

        return $this->catalog->heroes()->reject(fn (array $hero) => in_array($hero['id'], $used, true))->values();
    }

    public function unusedItems(array $state): Collection
    {
        $used = collect($state['characters'])->flatMap(fn (array $character) => $this->ownedItems($character)->pluck('index'))->all();

        return $this->catalog->items()->reject(fn (array $item) => in_array($item['index'], $used, true))->values();
    }

    public function unusedEnchants(array $state, string $side): Collection
    {
        $used = collect($state['characters'])->flatMap(fn (array $character) => collect($character['equipment'])->filter())->all();

        return $this->catalog->enchantOptions($side)
            ->reject(fn (array $option) => in_array($option['value'], $used, true))
            ->values();
    }

    private function ownedItems(array $character): Collection
    {
        $equipment = collect($character['equipment'] ?? [])
            ->filter(fn ($value, string $key) => str_contains($key, 'item') && $value)
            ->reject(fn ($value, string $key) => $key === 'hand_item_2' && $value === Arr::get($character, 'equipment.hand_item_1'))
            ->map(fn ($index, string $key) => ['index' => $index, 'location' => str($key)->replace('_', ' ')->title()->toString(), 'rekup' => false]);

        $inventory = collect($character['inventory'] ?? [])
            ->filter(fn (array $row) => ! empty($row['item']))
            ->map(fn (array $row, int $index) => [
                'index' => $row['item'],
                'location' => 'Inventory '.($index + 1),
                'rekup' => (bool) ($row['rekup'] ?? false) || (bool) Arr::get($this->catalog->item($row['item'] ?? null), 'always_rekup', false),
            ]);

        return $equipment->merge($inventory)->values();
    }
}
