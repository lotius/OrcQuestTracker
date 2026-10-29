<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Turns the difference between two saved tracker states into readable history events, so every change is
 * captured no matter how it was made (form edits, drag and drop, undo, the kraft planner).
 */
class TrackerHistory
{
    private const EQUIPMENT_LOCATIONS = [
        'hand_item_1' => ['Hand 1', 'hand_item_1_left_enchant', 'hand_item_1_right_enchant'],
        'hand_item_2' => ['Hand 2', 'hand_item_2_left_enchant', 'hand_item_2_right_enchant'],
        'armor_item' => ['Armor', 'armor_left_enchant', 'armor_right_enchant'],
        'artifact_item' => ['Artifact', 'artifact_left_enchant', 'artifact_right_enchant'],
    ];

    /** Typing in text fields saves often; edits to the same field within this window update one entry. */
    public const COALESCE_SECONDS = 600;

    private array $events = [];

    private array $after = [];

    private string $now = '';

    public function __construct(private OrcQuestCatalog $catalog)
    {
    }

    /**
     * @param  array  $context  ['source' => 'undo'|'redo'|null, 'kraft' => ['text' => string, 'consumed' => string[]]]
     */
    public function diff(array $before, array $after, array $context = []): array
    {
        $this->events = [];
        $this->after = $after;
        $this->now = now()->toIso8601String();
        $consumed = $context['kraft']['consumed'] ?? [];
        $krafting = filled($context['kraft']['text'] ?? null);

        if (filled($context['kraft']['text'] ?? null)) {
            $this->record('kraft', $context['kraft']['slot'] ?? null, $context['kraft']['text']);
        }

        foreach ($after['characters'] as $slot => $character) {
            $this->diffCharacter((string) $slot, $before['characters'][$slot] ?? [], $character, $before);
        }

        $this->diffItems($before, $after, $consumed, $krafting);
        $this->diffQuests($before['campaign'] ?? [], $after['campaign'] ?? []);

        if (($before['notes'] ?? '') !== ($after['notes'] ?? '')) {
            $this->record('notes', null, 'Updated campaign notes', coalesce: 'notes');
        }

        $source = $context['source'] ?? null;

        return array_map(fn (array $event) => $source ? $event + ['source' => $source] : $event, $this->events);
    }

    private function diffCharacter(string $slot, array $old, array $new, array $before): void
    {
        $wasActive = (bool) ($old['active'] ?? false);
        $isActive = (bool) ($new['active'] ?? false);

        if (! $wasActive && $isActive) {
            $this->record('character', $slot, "Character {$slot} joined the warband");
        }

        if ($wasActive && ! $isActive) {
            // Leaving clears the sheet; the items it held are still logged individually below.
            $this->record('character', $slot, $this->nameIn($before, $slot).' left the warband', who: $this->nameIn($before, $slot), heroId: $old['hero_id'] ?? '');

            return;
        }

        if (($old['hero_id'] ?? '') !== ($new['hero_id'] ?? '')) {
            $oldHero = $this->catalog->hero($old['hero_id'] ?? null);
            $newHero = $this->catalog->hero($new['hero_id'] ?? null);

            $this->record('character', $slot, match (true) {
                $newHero && $oldHero => "Switched hero from {$oldHero['name']} to {$newHero['name']}",
                (bool) $newHero => "Chose {$newHero['name']} ({$newHero['class']}) as the hero",
                default => "Cleared hero {$oldHero['name']}",
            });
        }

        if (trim($old['player'] ?? '') !== trim($new['player'] ?? '')) {
            $player = trim($new['player'] ?? '');
            $this->record('character', $slot, $player !== '' ? "Player set to {$player}" : 'Player name cleared', coalesce: "player:{$slot}");
        }

        foreach (['10', '20', '30'] as $tier) {
            $oldSkills = array_values(array_filter($old['skills'][$tier] ?? []));
            $newSkills = array_values(array_filter($new['skills'][$tier] ?? []));

            foreach (array_diff($newSkills, $oldSkills) as $skill) {
                $this->record('skill', $slot, "Learned {$skill} ({$tier} BP)");
            }

            foreach (array_diff($oldSkills, $newSkills) as $skill) {
                $this->record('skill', $slot, "Removed skill {$skill} ({$tier} BP)");
            }
        }

        // Enchants sitting on an equipment slot without an item
        foreach (self::EQUIPMENT_LOCATIONS as $itemKey => [$label, $leftKey, $rightKey]) {
            if (($old['equipment'][$itemKey] ?? '') !== '' || ($new['equipment'][$itemKey] ?? '') !== '') {
                continue;
            }

            foreach (['left' => $leftKey, 'right' => $rightKey] as $side => $key) {
                $this->diffEnchant($slot, $label.' slot', $side, $old['equipment'][$key] ?? '', $new['equipment'][$key] ?? '');
            }
        }
    }

    private function diffItems(array $before, array $after, array $consumed, bool $krafting): void
    {
        $old = $this->itemLocations($before);
        $new = $this->itemLocations($after);

        $removed = array_diff_key($old, $new);
        $added = array_diff_key($new, $old);

        // A card swapped for the next level of the same item by the same character is an upgrade.
        foreach ($removed as $index => $from) {
            $item = $this->catalog->item($index);

            foreach ($added as $newIndex => $to) {
                $next = $this->catalog->item($newIndex);

                if ($item && $next && $item['level'] && $next['name'] === $item['name']
                    && $next['level'] === $item['level'] + 1 && $to['slot'] === $from['slot']) {
                    $this->record('kraft', $to['slot'], "Upgraded {$item['label']} to {$next['label']} ({$to['label']})");
                    $this->diffEnchants($to['slot'], $next['label'], $from, $to);
                    unset($removed[$index], $added[$newIndex]);
                    break;
                }
            }
        }

        foreach ($removed as $index => $from) {
            if (in_array($index, $consumed, true)) {
                continue;
            }

            $this->record('item', $from['slot'], 'Removed '.$this->label($index)." from {$from['label']}", who: $this->nameIn($before, $from['slot']), heroId: $before['characters'][$from['slot']]['hero_id'] ?? '');
        }

        foreach ($added as $index => $to) {
            $this->record('item', $to['slot'], 'Added '.$this->label($index)." to {$to['label']}".($to['rekup'] ? ' (rekup)' : ''));
        }

        foreach (array_intersect_key($new, $old) as $index => $to) {
            $from = $old[$index];
            $label = $this->label($index);

            if ($from['slot'] !== $to['slot']) {
                $this->record('item', $to['slot'], "Received {$label} from ".$this->nameIn($before, $from['slot'])." ({$to['label']})");
            } elseif ($from['location'] !== $to['location'] && ! ($krafting && $from['inventory'] && $to['inventory'])) {
                // (Paying for a kraft compacts inventories; those shuffles are not worth logging.)
                $this->record('item', $to['slot'], "Moved {$label} from {$from['label']} to {$to['label']}");
            }

            if ($to['inventory'] && $from['inventory'] && $from['rekup'] !== $to['rekup']) {
                $this->record('rekup', $to['slot'], $to['rekup'] ? "Marked {$label} for rekup" : "Took {$label} out of rekup");
            }

            $this->diffEnchants($to['slot'], $label, $from, $to);
        }
    }

    private function diffEnchants(string $slot, string $itemLabel, array $from, array $to): void
    {
        foreach (['left', 'right'] as $side) {
            $this->diffEnchant($slot, $itemLabel, $side, $from[$side], $to[$side]);
        }
    }

    private function diffEnchant(string $slot, string $target, string $side, string $old, string $new): void
    {
        if ($old === $new) {
            return;
        }

        $this->record('enchant', $slot, match (true) {
            $old === '' => "Enchanted {$target} with {$new} ({$side})",
            $new === '' => "Removed {$side} enchant {$old} from {$target}",
            default => "Changed {$side} enchant on {$target} from {$old} to {$new}",
        });
    }

    private function diffQuests(array $before, array $after): void
    {
        $quests = $this->catalog->campaignQuests()->mapWithKeys(fn (array $quest) => [
            md5($quest['group'].'|'.$quest['quest']) => $quest,
        ]);

        foreach ($quests as $key => $quest) {
            $was = (bool) ($before[$key] ?? false);
            $is = (bool) ($after[$key] ?? false);

            if ($was !== $is) {
                $this->record('quest', null, ($is ? 'Completed ' : 'Marked incomplete: ')."{$quest['quest']} ({$quest['group']})");
            }
        }
    }

    /** Every owned card keyed by item index (cards are unique across the party). */
    private function itemLocations(array $state): array
    {
        $locations = [];

        foreach ($state['characters'] as $slot => $character) {
            foreach (self::EQUIPMENT_LOCATIONS as $itemKey => [$label, $leftKey, $rightKey]) {
                $index = $character['equipment'][$itemKey] ?? '';

                // The second hand mirrors a two-handed weapon; it is not a separate card.
                if ($index === '' || ($itemKey === 'hand_item_2' && $index === ($character['equipment']['hand_item_1'] ?? ''))) {
                    continue;
                }

                $locations[$index] = [
                    'slot' => (string) $slot,
                    'location' => $itemKey,
                    'label' => $label,
                    'inventory' => false,
                    'rekup' => false,
                    'left' => $character['equipment'][$leftKey] ?? '',
                    'right' => $character['equipment'][$rightKey] ?? '',
                ];
            }

            foreach ($character['inventory'] ?? [] as $position => $row) {
                if (($row['item'] ?? '') === '') {
                    continue;
                }

                $locations[$row['item']] = [
                    'slot' => (string) $slot,
                    'location' => "inventory:{$position}",
                    'label' => 'Inventory '.($position + 1),
                    'inventory' => true,
                    'rekup' => (bool) ($row['rekup'] ?? false),
                    'left' => $row['left_enchant'] ?? '',
                    'right' => $row['right_enchant'] ?? '',
                ];
            }
        }

        return $locations;
    }

    private function label(string $index): string
    {
        return $this->catalog->item($index)['label'] ?? $index;
    }

    private function nameIn(array $state, ?string $slot): string
    {
        $hero = $this->catalog->hero($state['characters'][$slot]['hero_id'] ?? null);

        return $hero['name'] ?? "Character {$slot}";
    }

    private function record(string $type, ?string $slot, string $text, ?string $coalesce = null, ?string $who = null, ?string $heroId = null): void
    {
        $this->events[] = array_filter([
            'id' => (string) Str::ulid(),
            'at' => $this->now,
            'type' => $type,
            'slot' => $slot,
            'who' => $slot !== null ? ($who ?? $this->nameIn($this->after, $slot)) : null,
            'hero_id' => $slot !== null ? ($heroId ?? ($this->after['characters'][$slot]['hero_id'] ?? '')) : null,
            'text' => $text,
            'coalesce' => $coalesce,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
