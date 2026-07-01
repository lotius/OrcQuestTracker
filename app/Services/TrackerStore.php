<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrackerStore
{
    private const PATH = 'orcquest-tracker-state.json';
    private const DEFAULT_CAMPAIGN_ID = 'default-campaign';

    public function load(?string $campaignId = null): array
    {
        $file = $this->loadFile();
        $campaignId = $campaignId ?: $file['active_campaign_id'];

        if (! isset($file['campaigns'][$campaignId])) {
            $campaignId = array_key_first($file['campaigns']);
        }

        return $this->normalizeState($file['campaigns'][$campaignId]['state'] ?? []);
    }

    public function save(string $campaignId, array $state, string $name): string
    {
        $file = $this->loadFile();
        $campaignId = $campaignId ?: $this->uniqueCampaignId($name, $file);

        $file['campaigns'][$campaignId] = [
            'name' => trim($name) !== '' ? trim($name) : 'Untitled Campaign',
            'state' => $this->normalizeState($state),
            'updated_at' => now()->toDateTimeString(),
        ];
        $file['active_campaign_id'] = $campaignId;

        $this->saveFile($file);

        return $campaignId;
    }

    public function saveAs(array $state, string $name): string
    {
        $file = $this->loadFile();
        $campaignId = $this->uniqueCampaignId($name, $file);

        $file['campaigns'][$campaignId] = [
            'name' => trim($name) !== '' ? trim($name) : 'Untitled Campaign',
            'state' => $this->normalizeState($state),
            'updated_at' => now()->toDateTimeString(),
        ];
        $file['active_campaign_id'] = $campaignId;

        $this->saveFile($file);

        return $campaignId;
    }

    public function setActive(string $campaignId): void
    {
        $file = $this->loadFile();

        if (isset($file['campaigns'][$campaignId])) {
            $file['active_campaign_id'] = $campaignId;
            $this->saveFile($file);
        }
    }

    public function activeCampaignId(): string
    {
        return $this->loadFile()['active_campaign_id'];
    }

    public function activeCampaignName(): string
    {
        $file = $this->loadFile();

        return $file['campaigns'][$file['active_campaign_id']]['name'] ?? 'Default Campaign';
    }

    public function campaigns(): array
    {
        return collect($this->loadFile()['campaigns'])
            ->map(fn (array $campaign, string $id) => [
                'id' => $id,
                'name' => $campaign['name'],
                'updated_at' => $campaign['updated_at'] ?? null,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function reset(?string $campaignId = null): void
    {
        $file = $this->loadFile();
        $campaignId = $campaignId ?: $file['active_campaign_id'];

        if (isset($file['campaigns'][$campaignId])) {
            $file['campaigns'][$campaignId]['state'] = $this->defaults();
            $file['campaigns'][$campaignId]['updated_at'] = now()->toDateTimeString();
            $file['active_campaign_id'] = $campaignId;
            $this->saveFile($file);
        }
    }

    public function defaults(): array
    {
        return [
            'characters' => collect(range(1, 6))->mapWithKeys(fn (int $slot) => [
                (string) $slot => [
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
                        'artifact_item' => '',
                    ],
                    'inventory' => collect(range(1, 15))->map(fn () => ['item' => '', 'rekup' => false])->all(),
                ],
            ])->all(),
            'campaign' => [],
            'notes' => '',
        ];
    }

    private function loadFile(): array
    {
        if (! Storage::exists(self::PATH)) {
            return $this->emptyFile();
        }

        $saved = json_decode(Storage::get(self::PATH), true) ?: [];

        if (isset($saved['characters'])) {
            return [
                'active_campaign_id' => self::DEFAULT_CAMPAIGN_ID,
                'campaigns' => [
                    self::DEFAULT_CAMPAIGN_ID => [
                        'name' => 'Default Campaign',
                        'state' => $this->normalizeState($saved),
                        'updated_at' => now()->toDateTimeString(),
                    ],
                ],
            ];
        }

        return array_replace_recursive($this->emptyFile(), $saved);
    }

    private function emptyFile(): array
    {
        return [
            'active_campaign_id' => self::DEFAULT_CAMPAIGN_ID,
            'campaigns' => [
                self::DEFAULT_CAMPAIGN_ID => [
                    'name' => 'Default Campaign',
                    'state' => $this->defaults(),
                    'updated_at' => null,
                ],
            ],
        ];
    }

    private function saveFile(array $file): void
    {
        Storage::put(self::PATH, json_encode($file, JSON_PRETTY_PRINT));
    }

    private function normalizeState(array $state): array
    {
        $state = array_replace_recursive($this->defaults(), $state);

        foreach ($state['characters'] as &$character) {
            $equipment = $character['equipment'] ?? [];

            if (! empty($equipment['hand_left_enchant']) && empty($equipment['hand_item_1_left_enchant'])) {
                $equipment['hand_item_1_left_enchant'] = $equipment['hand_left_enchant'];
            }

            if (! empty($equipment['hand_right_enchant']) && empty($equipment['hand_item_1_right_enchant'])) {
                $equipment['hand_item_1_right_enchant'] = $equipment['hand_right_enchant'];
            }

            unset($equipment['hand_left_enchant'], $equipment['hand_right_enchant']);
            $character['equipment'] = array_replace($this->defaults()['characters']['1']['equipment'], $equipment);
        }

        return $state;
    }

    private function uniqueCampaignId(string $name, array $file): string
    {
        $base = Str::slug($name) ?: 'campaign';
        $id = $base;
        $suffix = 2;

        while (isset($file['campaigns'][$id])) {
            $id = $base.'-'.$suffix;
            $suffix++;
        }

        return $id;
    }
}
