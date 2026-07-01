<?php

namespace App\Http\Controllers;

use App\Services\OrcQuestCatalog;
use App\Services\TrackerCalculator;
use App\Services\TrackerStore;
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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'characters' => ['array'],
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
            'campaign' => ['array'],
            'campaign.*' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'campaign_id' => ['nullable', 'string'],
            'campaign_name' => ['nullable', 'string', 'max:120'],
            'intent' => ['nullable', 'string'],
        ]);

        $state = $this->store->defaults();
        $incomingCharacters = $validated['characters'] ?? [];

        foreach ($state['characters'] as $slot => $character) {
            $incoming = $incomingCharacters[$slot] ?? [];
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
                ];
            }

            $this->normalizeHands($state['characters'][$slot]);
        }

        $state['campaign'] = collect($validated['campaign'] ?? [])->map(fn ($value) => (bool) $value)->all();
        $state['notes'] = $validated['notes'] ?? '';

        $campaignName = $validated['campaign_name'] ?? $this->store->activeCampaignName();
        $campaignId = ($validated['intent'] ?? '') === 'save_as'
            ? $this->store->saveAs($state, $campaignName)
            : $this->store->save($validated['campaign_id'] ?? $this->store->activeCampaignId(), $state, $campaignName);

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
            $this->moveToInventory($character, $firstHand);
            $firstHand = $secondHand;
        }

        if ($this->itemHands($firstHand) >= 2) {
            if ($secondHand && $secondHand !== $firstHand) {
                $this->moveToInventory($character, $secondHand);
            }

            $character['equipment']['hand_item_1'] = $firstHand;
            $character['equipment']['hand_item_2'] = $firstHand;
            return;
        }

        if ($firstHand && $secondHand === $firstHand) {
            $character['equipment']['hand_item_2'] = '';
        }
    }

    private function itemHands(?string $itemIndex): int
    {
        return (int) Arr::get($this->catalog->item($itemIndex), 'hands', 0);
    }

    private function moveToInventory(array &$character, ?string $itemIndex): void
    {
        if (! $itemIndex) {
            return;
        }

        foreach ($character['inventory'] as &$row) {
            if (($row['item'] ?? '') === '') {
                $row = ['item' => $itemIndex, 'rekup' => $this->itemAlwaysRekup($itemIndex)];
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
                }
            }
        }
    }

    private function itemAlwaysRekup(?string $itemIndex): bool
    {
        return (bool) Arr::get($this->catalog->item($itemIndex), 'always_rekup', false);
    }
}
