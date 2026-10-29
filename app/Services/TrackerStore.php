<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrackerStore
{
    private const PATH = 'orcquest-tracker-state.json';
    private const DEFAULT_CAMPAIGN_ID = 'default-campaign';
    private const DEFAULT_ACTIVE_CHARACTERS = 4;

    public function load(?string $campaignId = null): array
    {
        $file = $this->loadFile();
        $campaignId = $campaignId ?: $file['active_campaign_id'];

        if (! isset($file['campaigns'][$campaignId])) {
            $campaignId = array_key_first($file['campaigns']);
        }

        return $this->normalizeState($file['campaigns'][$campaignId]['state'] ?? []);
    }

    /**
     * @param  array  $events  history entries produced by TrackerHistory::diff()
     * @return array{0: string, 1: array} the campaign id and the history entries actually stored
     */
    public function save(string $campaignId, array $state, string $name, array $events = []): array
    {
        $file = $this->loadFile();
        $campaignId = $campaignId ?: $this->uniqueCampaignId($name, $file);
        $existing = $file['campaigns'][$campaignId] ?? [];
        $name = trim($name) !== '' ? trim($name) : 'Untitled Campaign';

        if (isset($existing['name']) && $existing['name'] !== $name) {
            $events[] = $this->event('campaign', "Renamed campaign to {$name}", coalesce: 'campaign-name');
        }

        [$history, $stored] = $this->appendHistory($existing['history'] ?? [], $events);

        $file['campaigns'][$campaignId] = [
            'name' => $name,
            'state' => $this->normalizeState($state),
            'history' => $history,
            'updated_at' => now()->toDateTimeString(),
        ];
        $file['active_campaign_id'] = $campaignId;

        $this->saveFile($file);

        return [$campaignId, $stored];
    }

    /** Forks the campaign, carrying its history along so the new copy remembers how it got here. */
    public function saveAs(array $state, string $name, ?string $fromCampaignId = null, array $events = []): string
    {
        $file = $this->loadFile();
        $campaignId = $this->uniqueCampaignId($name, $file);
        $source = $file['campaigns'][$fromCampaignId] ?? null;
        $name = trim($name) !== '' ? trim($name) : 'Untitled Campaign';

        $events[] = $this->event('campaign', $source ? "Saved as new campaign {$name} (copied from {$source['name']})" : "Created campaign {$name}");
        [$history] = $this->appendHistory($source['history'] ?? [], $events);

        $file['campaigns'][$campaignId] = [
            'name' => $name,
            'state' => $this->normalizeState($state),
            'history' => $history,
            'updated_at' => now()->toDateTimeString(),
        ];
        $file['active_campaign_id'] = $campaignId;

        $this->saveFile($file);

        return $campaignId;
    }

    public function history(?string $campaignId = null): array
    {
        $file = $this->loadFile();

        return $file['campaigns'][$campaignId ?: $file['active_campaign_id']]['history'] ?? [];
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
            [$file['campaigns'][$campaignId]['history']] = $this->appendHistory(
                $file['campaigns'][$campaignId]['history'] ?? [],
                [$this->event('campaign', 'Reset the campaign: characters, quests and notes cleared')],
            );
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
                    'active' => $slot <= self::DEFAULT_ACTIVE_CHARACTERS,
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
                    'inventory' => collect(range(1, 15))->map(fn () => [
                        'item' => '',
                        'rekup' => false,
                        'left_enchant' => '',
                        'right_enchant' => '',
                    ])->all(),
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

        // Shallow merge so default character values never leak into saved states before normalizeState() runs.
        $file = array_replace($this->emptyFile(), $saved);

        if (empty($file['campaigns'])) {
            $file['campaigns'] = $this->emptyFile()['campaigns'];
        }

        return $file;
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
        // States saved before characters could be added/removed: keep any slot that holds data.
        foreach ($state['characters'] ?? [] as $slot => $character) {
            if (! array_key_exists('active', $character)) {
                $state['characters'][$slot]['active'] = (int) $slot <= self::DEFAULT_ACTIVE_CHARACTERS || $this->characterHasData($character);
            }
        }

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

            foreach ($character['inventory'] as &$row) {
                $row = array_replace([
                    'item' => '',
                    'rekup' => false,
                    'left_enchant' => '',
                    'right_enchant' => '',
                ], $row);
            }
        }

        return $state;
    }

    /**
     * Appends events, folding a rapid repeat of the same text-field edit into the previous entry.
     *
     * @return array{0: array, 1: array} the full history and the entries added or updated
     */
    private function appendHistory(array $history, array $events): array
    {
        $stored = [];

        foreach ($events as $event) {
            $last = end($history);
            $coalesce = $event['coalesce'] ?? null;

            if ($coalesce && $last && ($last['coalesce'] ?? null) === $coalesce
                && now()->diffInSeconds($last['at'], true) < TrackerHistory::COALESCE_SECONDS) {
                $history[array_key_last($history)] = array_replace($last, ['text' => $event['text'], 'at' => $event['at']]);
                $stored[] = $history[array_key_last($history)];
                continue;
            }

            $history[] = $event;
            $stored[] = $event;
        }

        return [$history, $stored];
    }

    private function event(string $type, string $text, ?string $coalesce = null): array
    {
        return array_filter([
            'id' => (string) Str::ulid(),
            'at' => now()->toIso8601String(),
            'type' => $type,
            'text' => $text,
            'coalesce' => $coalesce,
        ]);
    }

    private function characterHasData(array $character): bool
    {
        return filled($character['player'] ?? '')
            || filled($character['hero_id'] ?? '')
            || collect($character['equipment'] ?? [])->filter()->isNotEmpty()
            || collect($character['inventory'] ?? [])->pluck('item')->filter()->isNotEmpty();
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
