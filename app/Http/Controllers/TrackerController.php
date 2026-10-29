<?php

namespace App\Http\Controllers;

use App\Exceptions\CampaignConflict;
use App\Models\Campaign;
use App\Services\CampaignState;
use App\Services\Campaigns;
use App\Services\OrcQuestCatalog;
use App\Services\TrackerCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TrackerController extends Controller
{
    public function __construct(
        private OrcQuestCatalog $catalog,
        private TrackerCalculator $calculator,
        private CampaignState $state,
        private Campaigns $campaigns,
    ) {
    }

    public function page(Request $request, Campaign $campaign): View
    {
        $state = $this->state->normalize($campaign->state);

        return view('tracker', [
            'campaign' => $campaign,
            'state' => $state,
            'campaigns' => $request->user()->campaigns()->orderBy('name')->get(['id', 'name']),
            'heroes' => $this->catalog->heroes(),
            'items' => $this->catalog->items(),
            'leftEnchants' => $this->catalog->enchantOptions('left'),
            'rightEnchants' => $this->catalog->enchantOptions('right'),
            'campaignQuests' => $this->catalog->campaignQuests()->groupBy('group'),
            'summaries' => collect($state['characters'])->map(fn (array $character) => $this->calculator->characterSummary($character)),
            'resources' => $this->calculator->resources($state),
            'history' => $this->campaigns->history($campaign),
        ]);
    }

    /** Auto-save endpoint. Refuses (409) saves made from a copy of the campaign that is no longer current. */
    public function save(Request $request, Campaign $campaign): JsonResponse
    {
        Gate::authorize('manage', $campaign);

        $input = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'characters' => ['array', 'max:'.CampaignState::CHARACTER_SLOTS],
            'characters.*' => ['array'],
            'characters.*.player' => ['nullable', 'string', 'max:80'],
            'characters.*.inventory' => ['array', 'max:'.CampaignState::INVENTORY_SLOTS],
            'campaign' => ['array'],
            'notes' => ['nullable', 'string', 'max:50000'],
            'campaign_name' => ['nullable', 'string', 'max:120'],
            'history_source' => ['nullable', 'in:undo,redo'],
            'history_kraft' => ['nullable', 'string', 'max:2000'],
            'history_kraft_slot' => ['nullable', 'string', 'max:4'],
            'history_consumed' => ['array', 'max:100'],
            'history_consumed.*' => ['string', 'max:120'],
        ]);

        try {
            $result = $this->campaigns->save($campaign, $this->state->fromInput($request->all()), $input['campaign_name'] ?? null, (int) $input['version'], [
                'source' => $input['history_source'] ?? null,
                'kraft' => [
                    'text' => $input['history_kraft'] ?? null,
                    'slot' => $input['history_kraft_slot'] ?? null,
                    'consumed' => $input['history_consumed'] ?? [],
                ],
            ]);
        } catch (CampaignConflict $conflict) {
            return response()->json(['message' => $conflict->getMessage(), 'version' => $conflict->currentVersion], 409);
        }

        return response()->json($result);
    }
}
