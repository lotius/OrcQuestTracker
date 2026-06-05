<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OrcQuestCatalog
{
    private array $data;

    public function __construct()
    {
        $this->data = json_decode(file_get_contents(resource_path('data/orcquest.json')), true);
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
        return $index ? $this->items()->firstWhere('index', $index) : null;
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
