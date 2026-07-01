<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OrcQuest Upgrade Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
    @php
        $panel = 'rounded-lg border border-slate-200 bg-white p-3 shadow-[0_10px_22px_rgba(15,23,42,0.055)] sm:p-4';
        $label = 'mb-0.5 block text-[11px] font-extrabold uppercase text-slate-500';
        $control = 'min-h-9 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900 outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15';
        $button = 'min-h-10 rounded-md bg-emerald-800 px-3.5 text-sm font-extrabold text-white hover:bg-emerald-950 sm:px-4';
        $ghostButton = 'min-h-10 rounded-md border border-slate-500 px-3.5 text-sm font-extrabold text-white hover:bg-white/10 sm:px-4';
        $headerToolButton = 'min-h-10 rounded-md border border-slate-500 bg-slate-950 px-3.5 text-sm font-extrabold text-white hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40 sm:px-4';
        $sectionTitle = 'mb-1.5 mt-4 text-[11px] font-black uppercase tracking-wide text-emerald-950';
    @endphp

    <header class="mb-3 flex flex-col gap-3 border-b-4 border-emerald-800 bg-slate-950 px-3 py-3 text-white sm:px-4 md:flex-row md:items-center md:justify-between md:px-6">
        <div>
            <p class="text-xs font-extrabold uppercase text-emerald-300">OrcQuest</p>
            <h1 class="text-2xl font-black leading-none sm:text-3xl md:text-4xl">Upgrade Tracker</h1>
        </div>
        <div class="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center md:justify-end">
            @if (session('status'))
                <span class="font-bold text-emerald-200">{{ session('status') }}</span>
            @endif
            <form class="flex items-center gap-2" method="get" action="{{ route('tracker.index') }}">
                <select class="min-h-10 min-w-0 flex-1 rounded-md border border-slate-500 bg-slate-950 px-2.5 text-sm font-bold text-white md:flex-none" name="campaign_id" onchange="this.form.submit()">
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign['id'] }}" @selected($activeCampaignId === $campaign['id'])>{{ $campaign['name'] }}</option>
                    @endforeach
                </select>
                <button class="{{ $ghostButton }}" type="submit">Load</button>
            </form>
            <form method="post" action="{{ route('tracker.reset') }}">
                @csrf
                <input type="hidden" name="campaign_id" value="{{ $activeCampaignId }}">
                <button class="{{ $ghostButton }}" type="submit">Reset</button>
            </form>
            <div class="flex flex-wrap items-center gap-1.5 border-t border-slate-700 pt-2 md:border-l md:border-t-0 md:pl-3 md:pt-0">
                <span class="rounded-md bg-white/10 px-2.5 py-1.5 text-xs font-extrabold text-emerald-200" data-save-status>Auto-save ready</span>
                <button class="{{ $headerToolButton }}" type="button" data-undo disabled>Undo</button>
                <button class="{{ $headerToolButton }}" type="button" data-redo disabled>Redo</button>
                <button class="{{ $button }}" type="submit" form="tracker-form" name="intent" value="save">Save Tracker</button>
            </div>
        </div>
    </header>

    <main class="mx-auto mb-10 w-[min(1560px,calc(100%-12px))] sm:w-[min(1560px,calc(100%-20px))] md:w-[min(1560px,calc(100%-28px))]">
        <form id="tracker-form" method="post" action="{{ route('tracker.update') }}" data-tracker-root>
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $activeCampaignId }}">

            <div class="mb-2 flex flex-wrap items-center gap-1.5 rounded-md border border-slate-200 bg-white p-1.5 shadow-[0_10px_22px_rgba(15,23,42,0.055)]" data-tabs>
                <button class="min-h-9 rounded-md bg-emerald-800 px-3 text-sm font-extrabold text-white" type="button" data-tab="characters" aria-pressed="true">Characters</button>
                <button class="min-h-9 rounded-md px-3 text-sm font-extrabold text-emerald-950 hover:bg-emerald-50" type="button" data-tab="resources" aria-pressed="false">Resources & Upgrades</button>
                <button class="min-h-9 rounded-md px-3 text-sm font-extrabold text-emerald-950 hover:bg-emerald-50" type="button" data-tab="campaign" aria-pressed="false">Campaign</button>
            </div>

            <section class="grid gap-3 xl:grid-cols-[1fr_1.35fr]" data-tab-panel="resources" hidden>
                <div class="{{ $panel }}">
                    <div class="mb-2 flex items-center justify-between gap-2 font-black">
                        <span>Rekup Pool</span>
                        <small class="font-bold text-slate-500">Totals from inventory marked rekup</small>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach (['wood', 'metal', 'leather', 'gold'] as $resource)
                            <div class="min-h-16 rounded-md border border-slate-200 bg-slate-50 p-2.5">
                                <span class="block text-xs font-extrabold text-slate-500">{{ ucfirst($resource) }}</span>
                                <strong class="mt-0.5 block text-2xl font-black" data-resource="{{ $resource }}">{{ $resources['totals'][$resource] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="{{ $panel }}">
                    <div class="mb-2 flex items-center justify-between gap-2 font-black">
                        <span>Eligible Upgrades</span>
                        <small class="font-bold text-slate-500"><span data-upgrades-count>{{ $eligibleUpgrades->count() }}</span> ready</small>
                    </div>
                    <div class="max-h-52 overflow-auto" data-upgrades-list>
                        @forelse ($eligibleUpgrades as $upgrade)
                            <div class="grid min-h-9 items-center gap-1 border-t border-slate-200 py-1.5 first:border-t-0 md:grid-cols-[1fr_1.3fr_auto] md:gap-2">
                                <strong>{{ $upgrade['item']['index'] }}</strong>
                                <span class="text-slate-500">{{ $upgrade['character'] }} &middot; {{ $upgrade['location'] }}</span>
                                <small class="text-slate-500">
                                    W {{ $upgrade['item']['kraft']['wood'] }} /
                                    M {{ $upgrade['item']['kraft']['metal'] }} /
                                    L {{ $upgrade['item']['kraft']['leather'] }} /
                                    G {{ $upgrade['item']['kraft']['gold'] }}
                                </small>
                            </div>
                        @empty
                            <p class="text-slate-500">No items can be upgraded with the current pooled resources.</p>
                        @endforelse
                    </div>
                </div>

                <div class="{{ $panel }} xl:col-span-2">
                    <div class="mb-2 flex items-center justify-between gap-2 font-black">
                        <span>Kraft Planner</span>
                        <small class="font-bold text-slate-500">Find whole-card rekup payments with less waste</small>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-[1fr_0.55fr]">
                        <label>
                            <span class="{{ $label }} flex min-h-5 items-center">Upgrade Target</span>
                            <select class="{{ $control }}" data-kraft-target>
                                <option value="">Choose an owned upgradeable item</option>
                            </select>
                        </label>
                        <label>
                            <span class="{{ $label }} flex min-h-5 items-center gap-1">
                                Strategy
                                <button class="grid h-5 w-5 place-items-center rounded-full border border-slate-300 bg-slate-50 text-xs font-black text-slate-600 hover:border-emerald-700 hover:text-emerald-800" type="button" data-modal-open="strategy-help" aria-label="Show strategy help">?</button>
                            </span>
                            <select class="{{ $control }}" data-kraft-strategy>
                                <option value="least_waste">Least amount of wasted resources</option>
                                <option value="retain_wood">Retain the most wood</option>
                                <option value="retain_gold">Retain the most gold</option>
                                <option value="retain_metal">Retain the most metal</option>
                                <option value="retain_leather">Retain the most leather</option>
                            </select>
                        </label>
                    </div>
                    <div class="mt-3 grid gap-2 lg:grid-cols-[0.8fr_1.2fr]">
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-2.5" data-kraft-summary>
                            <p class="text-sm font-bold text-slate-500">Choose a target to plan a kraft.</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-2.5" data-kraft-suggestion>
                            <p class="text-sm font-bold text-slate-500">Exact payment cards will appear here.</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="mb-1.5 flex items-center justify-between gap-2 font-black">
                            <span>Almost Craftable</span>
                            <small class="font-bold text-slate-500">Owned upgrades that need more rekup</small>
                        </div>
                        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3" data-almost-craftable>
                            <p class="text-sm font-bold text-slate-500">Almost-craftable items will appear here.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-3" data-tab-panel="characters">
                @foreach ($state['characters'] as $slot => $character)
                    @php
                        $summary = $summaries[$slot];
                        $selectedHero = $summary['hero'];
                        $skillGroups = $selectedHero['skills'] ?? ['10' => [], '20' => [], '30' => []];
                    @endphp
                    <article class="{{ $panel }}" data-character="{{ $slot }}">
                        <div class="mb-2 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-center gap-2.5">
                                <img
                                    class="h-12 w-12 rounded-md border border-slate-200 bg-slate-100 object-cover shadow-sm sm:h-14 sm:w-14"
                                    src="{{ $selectedHero['icon'] ?? '' }}"
                                    alt="{{ $selectedHero ? $selectedHero['name'].' icon' : 'No hero selected' }}"
                                    data-hero-icon
                                    @if (! $selectedHero) hidden @endif
                                >
                                <div>
                                    <p class="text-xs font-extrabold uppercase text-emerald-700">Character {{ $slot }}</p>
                                    <h2 class="text-xl font-black leading-tight sm:text-2xl" data-hero-name>{{ $selectedHero['name'] ?? 'Unassigned Hero' }}</h2>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1 md:justify-end">
                                <span @class([
                                    'rounded-full px-2 py-1 text-xs font-black',
                                    'bg-red-50 text-red-700' => $summary['inventory_limit'] && $summary['inventory_weight'] > $summary['inventory_limit'],
                                    'bg-emerald-50 text-emerald-950' => ! ($summary['inventory_limit'] && $summary['inventory_weight'] > $summary['inventory_limit']),
                                ]) data-weight="inventory">
                                    Inv {{ $summary['inventory_weight'] }}/{{ $summary['inventory_limit'] ?: '-' }}
                                </span>
                                <span @class([
                                    'rounded-full px-2 py-1 text-xs font-black',
                                    'bg-red-50 text-red-700' => $summary['hand_limit'] && $summary['hand_weight'] > $summary['hand_limit'],
                                    'bg-emerald-50 text-emerald-950' => ! ($summary['hand_limit'] && $summary['hand_weight'] > $summary['hand_limit']),
                                ]) data-weight="hands">
                                    Hands {{ $summary['hand_weight'] }}/{{ $summary['hand_limit'] ?: '-' }}
                                </span>
                                <span @class([
                                    'rounded-full px-2 py-1 text-xs font-black',
                                    'bg-red-50 text-red-700' => $summary['armor_limit'] && $summary['armor_weight'] > $summary['armor_limit'],
                                    'bg-emerald-50 text-emerald-950' => ! ($summary['armor_limit'] && $summary['armor_weight'] > $summary['armor_limit']),
                                ]) data-weight="armor">
                                    Armor {{ $summary['armor_weight'] }}/{{ $summary['armor_limit'] ?: '-' }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-2 md:grid-cols-[0.8fr_1.2fr]">
                            <label>
                                <span class="{{ $label }}">Player</span>
                                <input class="{{ $control }}" name="characters[{{ $slot }}][player]" value="{{ $character['player'] }}">
                            </label>
                            <label>
                                <span class="{{ $label }}">Hero</span>
                                <select class="{{ $control }}" name="characters[{{ $slot }}][hero_id]" data-filter="hero" data-hero-select>
                                    <option value="">Choose hero</option>
                                    @foreach ($heroes as $hero)
                                        <option value="{{ $hero['id'] }}" @selected($character['hero_id'] === $hero['id'])>
                                            {{ $hero['name'] }} &middot; {{ $hero['class'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        @if ($selectedHero)
                            <div class="mt-2 flex flex-wrap gap-1.5" data-hero-facts>
                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-extrabold text-amber-900">Class: {{ $selectedHero['class'] }}</span>
                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-extrabold text-amber-900">Base Health: {{ $selectedHero['base_health'] }} {{ $selectedHero['health_color'] }} health</span>
                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-extrabold text-amber-900">Starting item(s):{{ $selectedHero['starting_stuff'] }}</span>
                            </div>
                        @endif

                        <div class="{{ $sectionTitle }}">Skill Cards Purchased</div>
                        <div class="grid gap-1.5 lg:grid-cols-[1fr_1fr_0.65fr]">
                            @foreach (['10' => 8, '20' => 4, '30' => 1] as $tier => $slots)
                                <div>
                                    <span class="{{ $label }}">{{ $tier }} Badass Points</span>
                                    @for ($i = 0; $i < $slots; $i++)
                                    <select class="{{ $control }} mb-1" name="characters[{{ $slot }}][skills][{{ $tier }}][]" data-skill-tier="{{ $tier }}" data-needs-hero @disabled(! $selectedHero)>
                                            <option value="">Empty</option>
                                            @foreach ($skillGroups[$tier] ?? [] as $skill)
                                                <option value="{{ $skill }}" @selected(($character['skills'][$tier][$i] ?? '') === $skill)>{{ $skill }}</option>
                                            @endforeach
                                        </select>
                                    @endfor
                                </div>
                            @endforeach
                        </div>

                        <div class="{{ $sectionTitle }}">Weapons, Armor, Enchants</div>
                        <div class="grid gap-1.5 md:grid-cols-2 2xl:grid-cols-4">
                            @foreach ([
                                'hand_item_1' => 'Hand item 1',
                                'hand_item_2' => 'Hand item 2',
                            ] as $handKey => $labelText)
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-2.5 2xl:col-span-2">
                                    <div class="mb-1.5 text-sm font-black text-emerald-950">{{ $labelText }}</div>
                                    <div class="grid gap-1.5 md:grid-cols-3">
                                        <label>
                                            <span class="{{ $label }}">Left enchant</span>
                                            <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][{{ $handKey }}_left_enchant]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero)>
                                                <option value="">None</option>
                                                @foreach ($leftEnchants as $option)
                                                    <option value="{{ $option['value'] }}" @selected(($character['equipment'][$handKey.'_left_enchant'] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span class="{{ $label }}">Item</span>
                                            <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][{{ $handKey }}]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                                <option value="">None</option>
                                                @foreach ($items as $item)
                                                    <option value="{{ $item['index'] }}" @selected($character['equipment'][$handKey] === $item['index'])>{{ $item['index'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span class="{{ $label }}">Right enchant</span>
                                            <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][{{ $handKey }}_right_enchant]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero)>
                                                <option value="">None</option>
                                                @foreach ($rightEnchants as $option)
                                                    <option value="{{ $option['value'] }}" @selected(($character['equipment'][$handKey.'_right_enchant'] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-2.5 2xl:col-span-2">
                                <div class="mb-1.5 text-sm font-black text-emerald-950">Armor</div>
                                <div class="grid gap-1.5 md:grid-cols-3">
                                    <label>
                                        <span class="{{ $label }}">Left enchant</span>
                                        <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][armor_left_enchant]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero)>
                                            <option value="">None</option>
                                            @foreach ($leftEnchants as $option)
                                                <option value="{{ $option['value'] }}" @selected($character['equipment']['armor_left_enchant'] === $option['value'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span class="{{ $label }}">Item</span>
                                        <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][armor_item]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                            <option value="">None</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item['index'] }}" @selected($character['equipment']['armor_item'] === $item['index'])>{{ $item['index'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span class="{{ $label }}">Right enchant</span>
                                        <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][armor_right_enchant]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero)>
                                            <option value="">None</option>
                                            @foreach ($rightEnchants as $option)
                                                <option value="{{ $option['value'] }}" @selected($character['equipment']['armor_right_enchant'] === $option['value'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-2.5 2xl:col-span-2">
                                <div class="mb-1.5 text-sm font-black text-emerald-950">Artifact</div>
                                <div class="grid gap-1.5 md:grid-cols-3">
                                    <label>
                                        <span class="{{ $label }}">Left enchant</span>
                                        <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][artifact_left_enchant]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero)>
                                            <option value="">None</option>
                                            @foreach ($leftEnchants as $option)
                                                <option value="{{ $option['value'] }}" @selected(($character['equipment']['artifact_left_enchant'] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span class="{{ $label }}">Item</span>
                                        <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][artifact_item]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                            <option value="">None</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item['index'] }}" @selected($character['equipment']['artifact_item'] === $item['index'])>{{ $item['index'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span class="{{ $label }}">Right enchant</span>
                                        <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][artifact_right_enchant]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero)>
                                            <option value="">None</option>
                                            @foreach ($rightEnchants as $option)
                                                <option value="{{ $option['value'] }}" @selected(($character['equipment']['artifact_right_enchant'] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="{{ $sectionTitle }}">Inventory Items</div>
                        <div class="grid gap-1.5 xl:grid-cols-2">
                            @foreach ($character['inventory'] as $index => $row)
                                @php $inventoryItem = $items->firstWhere('index', $row['item']); @endphp
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-2" data-inventory-row>
                                    <div class="grid gap-1.5 md:grid-cols-[0.75fr_1fr_0.75fr_auto]">
                                        <label>
                                            <span class="{{ $label }}">Left enchant</span>
                                            <select class="{{ $control }}" name="characters[{{ $slot }}][inventory][{{ $index }}][left_enchant]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero || $row['item'] === '')>
                                                <option value="">None</option>
                                                @foreach ($leftEnchants as $option)
                                                    <option value="{{ $option['value'] }}" @selected(($row['left_enchant'] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span class="{{ $label }}">Item</span>
                                            <select class="{{ $control }}" name="characters[{{ $slot }}][inventory][{{ $index }}][item]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                                <option value="">Empty</option>
                                                @foreach ($items as $item)
                                                    <option value="{{ $item['index'] }}" @selected($row['item'] === $item['index'])>{{ $item['index'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span class="{{ $label }}">Right enchant</span>
                                            <select class="{{ $control }}" name="characters[{{ $slot }}][inventory][{{ $index }}][right_enchant]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero || $row['item'] === '')>
                                                <option value="">None</option>
                                                @foreach ($rightEnchants as $option)
                                                    <option value="{{ $option['value'] }}" @selected(($row['right_enchant'] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="grid min-w-20 grid-cols-[18px_auto] items-end gap-1 pb-1.5 text-xs font-extrabold text-slate-500">
                                            <input class="h-4 w-4 accent-emerald-800 disabled:cursor-not-allowed disabled:opacity-40" type="checkbox" name="characters[{{ $slot }}][inventory][{{ $index }}][rekup]" value="1" @checked($row['rekup'] || ($inventoryItem['always_rekup'] ?? false)) data-rekup data-needs-hero @disabled(! $selectedHero || ($inventoryItem['always_rekup'] ?? false))>
                                            <span>Rekup</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="mt-3 grid gap-3 xl:grid-cols-[1.4fr_0.6fr]" data-tab-panel="campaign" hidden>
                <div class="{{ $panel }} min-w-0">
                    <div class="mb-2 flex items-center justify-between gap-2 font-black">
                        <span>Campaign</span>
                        <small class="font-bold text-slate-500"><span data-campaign-count>{{ collect($state['campaign'])->filter()->count() }}</span> completed</small>
                    </div>
                    <label class="mb-3 block">
                        <span class="{{ $label }}">Campaign Name</span>
                        <input class="{{ $control }}" name="campaign_name" value="{{ $activeCampaignName }}">
                    </label>
                    <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($campaignQuests as $group => $quests)
                            <div>
                                <h3 class="mb-1.5 text-sm font-black">{{ $group }}</h3>
                                @foreach ($quests as $quest)
                                    @php $key = md5($group.'|'.$quest['quest']); @endphp
                                    <label class="flex min-h-6 items-center gap-2 text-sm">
                                        <input class="h-4 w-4 accent-emerald-800" type="checkbox" name="campaign[{{ $key }}]" value="1" @checked($state['campaign'][$key] ?? false)>
                                        <span>{{ $quest['quest'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="{{ $panel }}">
                    <div class="mb-2 flex items-center justify-between gap-2 font-black">
                        <span>Notes</span>
                        <small class="font-bold text-slate-500">Freeform campaign memory</small>
                    </div>
                    <textarea class="{{ $control }} min-h-[260px] resize-y md:min-h-[320px]" name="notes" rows="14">{{ $state['notes'] }}</textarea>
                </div>
            </section>

            <div class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                <button class="min-h-10 rounded-md border border-emerald-800 px-4 text-sm font-extrabold text-emerald-950 hover:bg-emerald-50" type="submit" name="intent" value="save_as">Save As New Campaign</button>
            </div>
        </form>
    </main>
    <div class="fixed inset-0 z-50 hidden bg-slate-950/70 p-4" data-modal="strategy-help" role="dialog" aria-modal="true" aria-labelledby="strategy-help-title">
        <div class="mx-auto mt-12 w-[min(620px,100%)] rounded-lg border border-slate-200 bg-white p-4 shadow-2xl md:mt-20 md:p-5" data-modal-card>
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-xl font-black" id="strategy-help-title">Kraft Strategy Help</h2>
                <button class="grid h-9 w-9 place-items-center rounded-md border border-slate-300 text-lg font-black text-slate-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700" type="button" data-modal-close aria-label="Close strategy help">x</button>
            </div>
            <div class="grid gap-3 text-sm font-bold text-slate-600">
                <p><strong class="text-slate-950">Least amount of wasted resources:</strong> finds the closest payment to the kraft cost, then uses fewer cards when multiple payments waste the same amount.</p>
                <p><strong class="text-slate-950">Retain the most wood:</strong> avoids consuming cards that generate wood when possible.</p>
                <p><strong class="text-slate-950">Retain the most metal:</strong> avoids consuming cards that generate metal when possible.</p>
                <p><strong class="text-slate-950">Retain the most leather:</strong> avoids consuming cards that generate leather when possible.</p>
                <p><strong class="text-slate-950">Retain the most gold:</strong> avoids consuming cards that generate gold when possible.</p>
            </div>
        </div>
    </div>
    <script>
        window.orcQuestCatalog = {{ Illuminate\Support\Js::from([
            'heroes' => $heroes->values(),
            'items' => $items->values(),
            'leftEnchants' => $leftEnchants->values(),
            'rightEnchants' => $rightEnchants->values(),
        ]) }};
    </script>
</body>
</html>
