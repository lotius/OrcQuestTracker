<?php

namespace App\Http\Controllers;

use App\Services\OrcQuestCatalog;
use App\Services\TrackerCalculator;
use App\Services\TrackerHistory;
use App\Services\TrackerStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackerController extends Controller
{
    public function __construct(
        private OrcQuestCatalog $catalog,
        private TrackerCalculator $calculator,
        private TrackerStore $store,
        private TrackerHistory $history,
    ) {
    }

    public function index(): View
    {
        if (request()->filled('campaign_id')) {
            $this->store->setActive(request('campaign_id'));
        }

        $activeCampaignId = $this->store->activeCampaignId();
        $state = $this->store->load($activeCampaignId);
        $this->normalizeAlwaysRekup($state);

        return view('tracker', [
            'state' => $state,
            'campaigns' => $this->store->campaigns(),
            'activeCampaignId' => $activeCampaignId,
            'activeCampaignName' => $this->store->activeCampaignName(),
            'heroes' => $this->catalog->heroes(),
            'items' => $this->catalog->items(),
            'leftEnchants' => $this->catalog->enchantOptions('left'),
            'rightEnchants' => $this->catalog->enchantOptions('right'),
            'campaignQuests' => $this->catalog->campaignQuests()->groupBy('group'),
            'summaries' => collect($state['characters'])->map(fn (array $character) => $this->calculator->characterSummary($character)),
            'resources' => $this->calculator->resources($state),
            'eligibleUpgrades' => $this->calculator->eligibleUpgrades($state),
            'history' => array_slice($this->store->history($activeCampaignId), -2000),
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'characters' => ['array'],
            'characters.*.active' => ['nullable', 'boolean'],
            'characters.*.player' => ['nullable', 'string', 'max:80'],
            'characters.*.hero_id' => ['nullable', 'string'],
            'characters.*.skills' => ['array'],
            'characters.*.skills.*' => ['array'],
            'characters.*.skills.*.*' => ['nullable', 'string'],
            'characters.*.equipment' => ['array'],
            'characters.*.equipment.*' => ['nullable', 'string'],
            'characters.*.inventory' => ['array'],
            'characters.*.inventory.*.item' => ['nullable', 'string'],
            'characters.*.inventory.*.rekup' => ['nullable', 'boolean'],
            'characters.*.inventory.*.left_enchant' => ['nullable', 'string'],
            'characters.*.inventory.*.right_enchant' => ['nullable', 'string'],
            'campaign' => ['array'],
            'campaign.*' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'campaign_id' => ['nullable', 'string'],
            'campaign_name' => ['nullable', 'string', 'max:120'],
            'intent' => ['nullable', 'string'],
            'history_source' => ['nullable', 'in:undo,redo'],
            'history_kraft' => ['nullable', 'string', 'max:2000'],
            'history_kraft_slot' => ['nullable', 'string'],
            'history_consumed' => ['array'],
            'history_consumed.*' => ['string'],
        ]);

        $state = $this->store->defaults();
        $incomingCharacters = $validated['characters'] ?? [];

        foreach ($state['characters'] as $slot => $character) {
            $incoming = $incomingCharacters[$slot] ?? [];
            $state['characters'][$slot]['active'] = (bool) ($incoming['active'] ?? false);
            $state['characters'][$slot]['player'] = $incoming['player'] ?? '';
            $state['characters'][$slot]['hero_id'] = $incoming['hero_id'] ?? '';
            $state['characters'][$slot]['skills'] = [
                '10' => array_values(array_filter($incoming['skills']['10'] ?? [])),
                '20' => array_values(array_filter($incoming['skills']['20'] ?? [])),
                '30' => array_values(array_filter($incoming['skills']['30'] ?? [])),
            ];
            $state['characters'][$slot]['equipment'] = array_replace(
                $state['characters'][$slot]['equipment'],
                $incoming['equipment'] ?? []
            );

            foreach ($state['characters'][$slot]['inventory'] as $index => $row) {
                $item = $incoming['inventory'][$index]['item'] ?? '';
                $alwaysRekup = $this->itemAlwaysRekup($item);

                $state['characters'][$slot]['inventory'][$index] = [
                    'item' => $item,
                    'rekup' => $item !== '' && ($alwaysRekup || (bool) ($incoming['inventory'][$index]['rekup'] ?? false)),
                    'left_enchant' => $item !== '' && ! $alwaysRekup ? ($incoming['inventory'][$index]['left_enchant'] ?? '') : '',
                    'right_enchant' => $item !== '' && ! $alwaysRekup ? ($incoming['inventory'][$index]['right_enchant'] ?? '') : '',
                ];
            }

            $this->normalizeHands($state['characters'][$slot]);
        }

        $state['campaign'] = collect($validated['campaign'] ?? [])->map(fn ($value) => (bool) $value)->all();
        $state['notes'] = $validated['notes'] ?? '';

        $campaignName = $validated['campaign_name'] ?? $this->store->activeCampaignName();
        $campaignId = $validated['campaign_id'] ?? $this->store->activeCampaignId();
        $events = $this->history->diff($this->store->load($campaignId), $state, [
            'source' => $validated['history_source'] ?? null,
            'kraft' => [
                'text' => $validated['history_kraft'] ?? null,
                'slot' => $validated['history_kraft_slot'] ?? null,
                'consumed' => $validated['history_consumed'] ?? [],
            ],
        ]);

        if (($validated['intent'] ?? '') === 'save_as') {
            $campaignId = $this->store->saveAs($state, $campaignName, $campaignId, $events);
            $stored = [];
        } else {
            [$campaignId, $stored] = $this->store->save($campaignId, $state, $campaignName, $events);
        }

        if ($request->ajax()) {
            return response()->json(['campaign_id' => $campaignId, 'events' => $stored]);
        }

        return redirect()
            ->route('tracker.index', ['campaign_id' => $campaignId])
            ->with('status', ($validated['intent'] ?? '') === 'save_as' ? 'Campaign saved as new.' : 'Tracker saved.');
    }

    public function reset(): RedirectResponse
    {
        $this->store->reset(request('campaign_id'));

        return back()->with('status', 'Tracker reset.');
    }

    private function normalizeHands(array &$character): void
    {
        $firstHand = $character['equipment']['hand_item_1'] ?? '';
        $secondHand = $character['equipment']['hand_item_2'] ?? '';

        if ($this->itemHands($secondHand) >= 2 && $secondHand !== $firstHand) {
            $this->moveToInventory(
                $character,
                $firstHand,
                $character['equipment']['hand_item_1_left_enchant'] ?? '',
                $character['equipment']['hand_item_1_right_enchant'] ?? '',
            );
            $firstHand = $secondHand;
            $character['equipment']['hand_item_1_left_enchant'] = $character['equipment']['hand_item_2_left_enchant'] ?? '';
            $character['equipment']['hand_item_1_right_enchant'] = $character['equipment']['hand_item_2_right_enchant'] ?? '';
        }

        if ($this->itemHands($firstHand) >= 2) {
            if ($secondHand && $secondHand !== $firstHand) {
                $this->moveToInventory(
                    $character,
                    $secondHand,
                    $character['equipment']['hand_item_2_left_enchant'] ?? '',
                    $character['equipment']['hand_item_2_right_enchant'] ?? '',
                );
            }

            $character['equipment']['hand_item_1'] = $firstHand;
            $character['equipment']['hand_item_2'] = $firstHand;
            $character['equipment']['hand_item_2_left_enchant'] = '';
            $character['equipment']['hand_item_2_right_enchant'] = '';
            return;
        }

        if ($firstHand && $secondHand === $firstHand) {
            $character['equipment']['hand_item_2'] = '';
            $character['equipment']['hand_item_2_left_enchant'] = '';
            $character['equipment']['hand_item_2_right_enchant'] = '';
        }
    }

    private function itemHands(?string $itemIndex): int
    {
        return (int) Arr::get($this->catalog->item($itemIndex), 'hands', 0);
    }

    private function moveToInventory(array &$character, ?string $itemIndex, string $leftEnchant = '', string $rightEnchant = ''): void
    {
        if (! $itemIndex) {
            return;
        }

        foreach ($character['inventory'] as &$row) {
            if (($row['item'] ?? '') === '') {
                $alwaysRekup = $this->itemAlwaysRekup($itemIndex);
                $row = [
                    'item' => $itemIndex,
                    'rekup' => $alwaysRekup,
                    'left_enchant' => $alwaysRekup ? '' : $leftEnchant,
                    'right_enchant' => $alwaysRekup ? '' : $rightEnchant,
                ];
                return;
            }
        }
    }

    private function normalizeAlwaysRekup(array &$state): void
    {
        foreach ($state['characters'] as &$character) {
            foreach ($character['inventory'] as &$row) {
                if ($this->itemAlwaysRekup($row['item'] ?? null)) {
                    $row['rekup'] = true;
                    $row['left_enchant'] = '';
                    $row['right_enchant'] = '';
                }

                if (($row['item'] ?? '') === '') {
                    $row['left_enchant'] = '';
                    $row['right_enchant'] = '';
                }
            }
        }
    }

    private function itemAlwaysRekup(?string $itemIndex): bool
    {
        return (bool) Arr::get($this->catalog->item($itemIndex), 'always_rekup', false);
    }
}
