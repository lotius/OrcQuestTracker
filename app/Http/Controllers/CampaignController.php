<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\Campaigns;
use App\Services\OrcQuestCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function __construct(private Campaigns $campaigns)
    {
    }

    /** Opens the campaign used last, or the campaign list. */
    public function home(Request $request): RedirectResponse
    {
        $campaign = $request->user()->campaigns()->orderByDesc('last_opened_at')->orderByDesc('updated_at')->first();

        return $campaign
            ? redirect()->route('campaigns.show', $campaign)
            : redirect()->route('campaigns.index');
    }

    public function index(Request $request, OrcQuestCatalog $catalog): View
    {
        $heroes = $catalog->heroes()->keyBy('id');
        $questCount = $catalog->campaignQuests()->count();

        $campaigns = $request->user()->campaigns()
            ->withCount('events')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Campaign $campaign) => [
                'model' => $campaign,
                'heroes' => collect($campaign->state['characters'] ?? [])
                    ->filter(fn ($character) => ($character['active'] ?? false) && isset($heroes[$character['hero_id'] ?? '']))
                    ->map(fn ($character) => $heroes[$character['hero_id']])
                    ->values(),
                'quests' => collect($campaign->state['campaign'] ?? [])->filter()->count(),
            ]);

        return view('campaigns.index', compact('campaigns', 'questCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'copy_from' => ['nullable', 'integer'],
        ]);

        $source = isset($data['copy_from']) ? $request->user()->campaigns()->find($data['copy_from']) : null;
        $campaign = $source
            ? $this->campaigns->duplicate($source, $data['name'])
            : $this->campaigns->create($request->user(), $data['name']);

        return redirect()->route('campaigns.show', $campaign);
    }

    public function show(Request $request, Campaign $campaign, TrackerController $tracker): View
    {
        Gate::authorize('manage', $campaign);

        $campaign->forceFill(['last_opened_at' => now()])->saveQuietly();

        return $tracker->page($request, $campaign);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('manage', $campaign);

        $this->campaigns->rename($campaign, $request->validate(['name' => ['required', 'string', 'max:120']])['name']);

        return back()->with('status', 'Campaign renamed.');
    }

    public function duplicate(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('manage', $campaign);

        return redirect()->route('campaigns.show', $this->campaigns->duplicate($campaign, $campaign->name.' (copy)'));
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('manage', $campaign);

        $name = $campaign->name;
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('status', "Deleted {$name}.");
    }

    public function export(Campaign $campaign): StreamedResponse
    {
        Gate::authorize('manage', $campaign);

        $filename = Str::slug($campaign->name ?: 'campaign').'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print(json_encode($this->campaigns->export($campaign), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        try {
            $data = json_decode($request->file('file')->get(), true, flags: JSON_THROW_ON_ERROR);
            $imported = $this->campaigns->import($request->user(), is_array($data) ? $data : []);
        } catch (\JsonException|InvalidArgumentException) {
            return back()->withErrors(['file' => 'That file is not an OrcQuest tracker campaign.']);
        }

        if (! $imported) {
            return back()->withErrors(['file' => 'No campaigns were found in that file.']);
        }

        return count($imported) === 1
            ? redirect()->route('campaigns.show', $imported[0])->with('status', "Imported {$imported[0]->name}.")
            : redirect()->route('campaigns.index')->with('status', 'Imported '.count($imported).' campaigns.');
    }
}
