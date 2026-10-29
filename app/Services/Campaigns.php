<?php

namespace App\Services;

use App\Exceptions\CampaignConflict;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Every write to a campaign goes through here: versioned saves (stale writes are refused, never merged
 * over newer data), the history log, duplication, and import/export.
 */
class Campaigns
{
    public const EXPORT_FORMAT = 'orcquest-campaign';

    /** The pre-database tracker kept everything in this file (storage/app/private). */
    public const LEGACY_FILE = 'orcquest-tracker-state.json';

    public function __construct(private CampaignState $state, private TrackerHistory $history)
    {
    }

    public function create(User $user, string $name, ?array $state = null, array $events = []): Campaign
    {
        return DB::transaction(function () use ($user, $name, $state, $events) {
            $campaign = $user->campaigns()->create([
                'name' => $this->cleanName($name),
                'state' => $this->state->normalize($state ?? $this->state->defaults()),
                'version' => 1,
            ]);

            $this->appendEvents($campaign, $events);

            return $campaign;
        });
    }

    /**
     * @param  array  $context  passed to TrackerHistory::diff()
     * @return array{version: int, events: array}
     *
     * @throws CampaignConflict when the campaign was saved elsewhere since $expectedVersion
     */
    public function save(Campaign $campaign, array $state, ?string $name, int $expectedVersion, array $context = []): array
    {
        return DB::transaction(function () use ($campaign, $state, $name, $expectedVersion, $context) {
            $current = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($current->version !== $expectedVersion) {
                throw new CampaignConflict($current->version);
            }

            $events = $this->history->diff($this->state->normalize($current->state), $state, $context);
            $name = $name === null ? $current->name : $this->cleanName($name);

            if ($name !== $current->name) {
                $events[] = $this->event('campaign', "Renamed campaign to {$name}", coalesce: 'campaign-name');
            }

            // The version check in the WHERE clause makes the write itself atomic, even without row locks (SQLite).
            $updated = Campaign::query()
                ->whereKey($campaign->id)
                ->where('version', $expectedVersion)
                ->update([
                    'name' => $name,
                    'state' => json_encode($this->state->normalize($state)),
                    'version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw new CampaignConflict($expectedVersion + 1);
            }

            return ['version' => $expectedVersion + 1, 'events' => $this->appendEvents($current, $events)];
        });
    }

    public function rename(Campaign $campaign, string $name): void
    {
        $name = $this->cleanName($name);

        if ($name === $campaign->name) {
            return;
        }

        DB::transaction(function () use ($campaign, $name) {
            $campaign->update(['name' => $name, 'version' => $campaign->version + 1]);
            $this->appendEvents($campaign, [$this->event('campaign', "Renamed campaign to {$name}")]);
        });
    }

    public function duplicate(Campaign $source, string $name): Campaign
    {
        return DB::transaction(function () use ($source, $name) {
            $copy = $source->user->campaigns()->create([
                'name' => $this->cleanName($name),
                'state' => $source->state,
                'version' => 1,
            ]);

            foreach ($source->events()->orderBy('id')->cursor() as $event) {
                $copy->events()->create($event->only(['ulid', 'type', 'slot', 'who', 'hero_id', 'text', 'source', 'coalesce', 'happened_at']));
            }

            $this->appendEvents($copy, [$this->event('campaign', "Duplicated from {$source->name}")]);

            return $copy;
        });
    }

    public function history(Campaign $campaign, int $limit = 2000): array
    {
        return $campaign->events()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map->toHistoryEntry()
            ->values()
            ->all();
    }

    public function export(Campaign $campaign): array
    {
        return [
            'format' => self::EXPORT_FORMAT,
            'format_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'name' => $campaign->name,
            'state' => $this->state->normalize($campaign->state),
            'history' => $this->history($campaign, PHP_INT_MAX),
        ];
    }

    /**
     * Imports a campaign export, or the old single-file tracker save (which may hold several campaigns).
     *
     * @return Campaign[]
     *
     * @throws InvalidArgumentException when the data is not a recognised tracker file
     */
    public function import(User $user, array $data): array
    {
        if (($data['format'] ?? null) === self::EXPORT_FORMAT && is_array($data['state'] ?? null)) {
            return [$this->create($user, (string) ($data['name'] ?? 'Imported Campaign'), $data['state'], [
                ...(array) ($data['history'] ?? []),
                $this->event('campaign', 'Imported from a campaign file'),
            ])];
        }

        if (is_array($data['campaigns'] ?? null)) {
            return collect($data['campaigns'])
                ->filter(fn ($campaign) => is_array($campaign) && is_array($campaign['state'] ?? null))
                ->map(fn (array $campaign) => $this->create($user, (string) ($campaign['name'] ?? 'Imported Campaign'), $campaign['state'], [
                    ...(array) ($campaign['history'] ?? []),
                    $this->event('campaign', 'Imported from the original tracker save file'),
                ]))
                ->values()
                ->all();
        }

        if (is_array($data['characters'] ?? null)) {
            return [$this->create($user, 'Imported Campaign', $data, [$this->event('campaign', 'Imported from the original tracker save file')])];
        }

        throw new InvalidArgumentException('That file is not an OrcQuest tracker campaign.');
    }

    /** How many campaigns import() would create from this data. */
    public function importableCount(array $data): int
    {
        if (($data['format'] ?? null) === self::EXPORT_FORMAT) {
            return 1;
        }

        if (is_array($data['campaigns'] ?? null)) {
            return count(array_filter($data['campaigns'], fn ($campaign) => is_array($campaign) && is_array($campaign['state'] ?? null)));
        }

        return is_array($data['characters'] ?? null) ? 1 : 0;
    }

    private function appendEvents(Campaign $campaign, array $events): array
    {
        $stored = [];
        $last = $campaign->events()->orderByDesc('id')->first();

        foreach ($events as $event) {
            if (! is_array($event) || blank($event['text'] ?? null)) {
                continue;
            }

            $coalesce = $event['coalesce'] ?? null;
            $at = isset($event['at']) ? Carbon::parse($event['at']) : now();

            if ($coalesce && $last && $last->coalesce === $coalesce
                && $last->happened_at->diffInSeconds($at, true) < TrackerHistory::COALESCE_SECONDS) {
                $last->update(['text' => $event['text'], 'happened_at' => $at]);
                $stored[] = $last->toHistoryEntry();
                continue;
            }

            $last = $campaign->events()->create([
                'ulid' => $event['id'] ?? (string) Str::ulid(),
                'type' => Str::limit((string) ($event['type'] ?? 'campaign'), 20, ''),
                'slot' => isset($event['slot']) ? (string) $event['slot'] : null,
                'who' => isset($event['who']) ? Str::limit((string) $event['who'], 80, '') : null,
                'hero_id' => $event['hero_id'] ?? null,
                'text' => (string) $event['text'],
                'source' => $event['source'] ?? null,
                'coalesce' => $coalesce,
                'happened_at' => $at,
            ]);
            $stored[] = $last->toHistoryEntry();
        }

        return $stored;
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

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name !== '' ? mb_substr($name, 0, 120) : 'Untitled Campaign';
    }
}
