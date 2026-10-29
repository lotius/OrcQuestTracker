<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OrcQuestCatalog
{
    private array $data;

    private array $itemsByIndex;

    public function __construct()
    {
        $this->data = json_decode(file_get_contents(resource_path('data/orcquest.json')), true);
        $this->data['items'] = $this->withLevels($this->data['items']);
        $this->itemsByIndex = array_column($this->data['items'], null, 'index');
    }

    /**
     * Kraft chains share an item name. Cards that cannot be upgraded are the top of a chain (level 3);
     * the lowest-coded upgradeable card and its copies (same kraft plan) are level 1; the rest are level 2.
     * Names without any upgradeable card (potions, resources, relics) have no level.
     */
    private function withLevels(array $items): array
    {
        $codeNumber = fn (array $item) => (int) preg_replace('/\D/', '', $item['code']);
        $levels = [];

        foreach (collect($items)->groupBy('name') as $chain) {
            $upgradeable = $chain->where('can_upgrade', true)->sortBy($codeNumber)->values();

            if ($upgradeable->isEmpty()) {
                continue;
            }

            $baseKraft = $upgradeable->first()['kraft'];
            $kraftVaries = $upgradeable->contains(fn (array $item) => $item['kraft'] !== $baseKraft);

            foreach ($chain as $item) {
                if (! $item['can_upgrade']) {
                    $levels[$item['index']] = 3;
                    continue;
                }

                $levels[$item['index']] = $kraftVaries
                    ? ($item['kraft'] === $baseKraft ? 1 : 2)
                    : ($upgradeable->search(fn (array $candidate) => $candidate['index'] === $item['index']) < $upgradeable->count() / 2 ? 1 : 2);
            }
        }

        return array_map(function (array $item) use ($levels) {
            $level = $levels[$item['index']] ?? null;

            return $item + [
                'level' => $level,
                'label' => $level ? $item['index'].' '.str_repeat('★', $level) : $item['index'],
            ];
        }, $items);
    }

    public function heroes(): Collection
    {
        return collect($this->data['heroes']);
    }

    public function items(): Collection
    {
        return collect($this->data['items']);
    }

    public function enchants(): Collection
    {
        return collect($this->data['enchants']);
    }

    public function campaignQuests(): Collection
    {
        return collect($this->data['campaign_quests']);
    }

    public function hero(?string $id): ?array
    {
        return $id ? $this->heroes()->firstWhere('id', $id) : null;
    }

    public function item(?string $index): ?array
    {
        return $index ? ($this->itemsByIndex[$index] ?? null) : null;
    }

    public function itemByCode(?string $code): ?array
    {
        return $code ? $this->items()->firstWhere('code', $code) : null;
    }

    public function enchantOptions(string $side): Collection
    {
        return $this->enchants()->map(function (array $enchant) use ($side) {
            $value = $enchant['code'].' - '.Arr::get($enchant, $side);

            return [
                'value' => $value,
                'label' => $value,
                'condition' => $enchant['condition'],
                'effect' => Arr::get($enchant, $side.'_effect'),
            ];
        })->filter(fn (array $option) => filled($option['value']) && ! str_ends_with($option['value'], ' -'))->values();
    }
}
