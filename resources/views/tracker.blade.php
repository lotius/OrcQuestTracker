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
        $panel = 'rounded-lg border border-slate-200 bg-white p-4 shadow-[0_12px_28px_rgba(15,23,42,0.06)]';
        $label = 'mb-1 block text-xs font-extrabold text-slate-500';
        $control = 'min-h-10 w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15';
        $button = 'min-h-11 rounded-md bg-emerald-800 px-5 text-sm font-extrabold text-white hover:bg-emerald-950';
        $ghostButton = 'min-h-11 rounded-md border border-slate-500 px-5 text-sm font-extrabold text-white hover:bg-white/10';
        $sectionTitle = 'mb-2 mt-5 text-xs font-black uppercase text-emerald-950';
    @endphp

    <header class="mb-5 flex flex-col gap-4 border-b-4 border-emerald-800 bg-slate-950 px-5 py-5 text-white md:flex-row md:items-center md:justify-between md:px-8">
        <div>
            <p class="text-xs font-extrabold uppercase text-emerald-300">OrcQuest</p>
            <h1 class="text-3xl font-black leading-none md:text-5xl">Upgrade Tracker</h1>
        </div>
        <div class="flex w-full flex-col gap-3 md:w-auto md:flex-row md:items-center md:justify-end">
            @if (session('status'))
                <span class="font-bold text-emerald-200">{{ session('status') }}</span>
            @endif
            <form class="flex items-center gap-2" method="get" action="{{ route('tracker.index') }}">
                <select class="min-h-11 rounded-md border border-slate-500 bg-slate-950 px-3 text-sm font-bold text-white" name="campaign_id" onchange="this.form.submit()">
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
        </div>
    </header>

    <main class="mx-auto mb-14 w-[min(1560px,calc(100%-18px))] md:w-[min(1560px,calc(100%-32px))]">
        <form id="tracker-form" method="post" action="{{ route('tracker.update') }}" data-tracker-root>
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $activeCampaignId }}">

            <section class="grid gap-3 xl:grid-cols-[1fr_1.35fr]">
                <div class="{{ $panel }}">
                    <div class="mb-3 flex items-center justify-between gap-3 font-black">
                        <span>Rekup Pool</span>
                        <small class="font-bold text-slate-500">Totals from inventory marked rekup</small>
                    </div>
                    <label class="mb-3 block">
                        <span class="{{ $label }}">Campaign Name</span>
                        <input class="{{ $control }}" name="campaign_name" value="{{ $activeCampaignName }}">
                    </label>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach (['wood', 'metal', 'leather', 'gold'] as $resource)
                            <div class="min-h-20 rounded-md border border-slate-200 bg-slate-50 p-3">
                                <span class="block text-xs font-extrabold text-slate-500">{{ ucfirst($resource) }}</span>
                                <strong class="mt-1 block text-3xl font-black" data-resource="{{ $resource }}">{{ $resources['totals'][$resource] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="{{ $panel }}">
                    <div class="mb-3 flex items-center justify-between gap-3 font-black">
                        <span>Eligible Upgrades</span>
                        <small class="font-bold text-slate-500"><span data-upgrades-count>{{ $eligibleUpgrades->count() }}</span> ready</small>
                    </div>
                    <div class="max-h-52 overflow-auto" data-upgrades-list>
                        @forelse ($eligibleUpgrades as $upgrade)
                            <div class="grid min-h-10 items-center gap-1 border-t border-slate-200 py-2 first:border-t-0 md:grid-cols-[1fr_1.3fr_auto] md:gap-3">
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
            </section>

            <div class="pointer-events-none sticky top-2 z-10 my-3 flex justify-end">
                <button class="{{ $button }} pointer-events-auto shadow-lg shadow-emerald-950/20" type="submit">Save Tracker</button>
            </div>

            <section class="grid gap-4">
                @foreach ($state['characters'] as $slot => $character)
                    @php
                        $summary = $summaries[$slot];
                        $selectedHero = $summary['hero'];
                        $skillGroups = $selectedHero['skills'] ?? ['10' => [], '20' => [], '30' => []];
                    @endphp
                    <article class="{{ $panel }}" data-character="{{ $slot }}">
                        <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-center gap-3">
                                <img
                                    class="h-16 w-16 rounded-lg border border-slate-200 bg-slate-100 object-cover shadow-sm"
                                    src="{{ $selectedHero['icon'] ?? '' }}"
                                    alt="{{ $selectedHero ? $selectedHero['name'].' icon' : 'No hero selected' }}"
                                    data-hero-icon
                                    @if (! $selectedHero) hidden @endif
                                >
                                <div>
                                    <p class="text-xs font-extrabold uppercase text-emerald-700">Character {{ $slot }}</p>
                                    <h2 class="text-2xl font-black leading-tight" data-hero-name>{{ $selectedHero['name'] ?? 'Unassigned Hero' }}</h2>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1.5 md:justify-end">
                                <span @class([
                                    'rounded-full px-2.5 py-1.5 text-xs font-black',
                                    'bg-red-50 text-red-700' => $summary['inventory_limit'] && $summary['inventory_weight'] > $summary['inventory_limit'],
                                    'bg-emerald-50 text-emerald-950' => ! ($summary['inventory_limit'] && $summary['inventory_weight'] > $summary['inventory_limit']),
                                ]) data-weight="inventory">
                                    Inv {{ $summary['inventory_weight'] }}/{{ $summary['inventory_limit'] ?: '-' }}
                                </span>
                                <span @class([
                                    'rounded-full px-2.5 py-1.5 text-xs font-black',
                                    'bg-red-50 text-red-700' => $summary['hand_limit'] && $summary['hand_weight'] > $summary['hand_limit'],
                                    'bg-emerald-50 text-emerald-950' => ! ($summary['hand_limit'] && $summary['hand_weight'] > $summary['hand_limit']),
                                ]) data-weight="hands">
                                    Hands {{ $summary['hand_weight'] }}/{{ $summary['hand_limit'] ?: '-' }}
                                </span>
                                <span @class([
                                    'rounded-full px-2.5 py-1.5 text-xs font-black',
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
                            <div class="mt-3 flex flex-wrap gap-2" data-hero-facts>
                                <span class="rounded-md bg-amber-50 px-2 py-1.5 text-xs font-extrabold text-amber-900">Class: {{ $selectedHero['class'] }}</span>
                                <span class="rounded-md bg-amber-50 px-2 py-1.5 text-xs font-extrabold text-amber-900">Base Health: {{ $selectedHero['base_health'] }} {{ $selectedHero['health_color'] }} health</span>
                                <span class="rounded-md bg-amber-50 px-2 py-1.5 text-xs font-extrabold text-amber-900">Starting item(s):{{ $selectedHero['starting_stuff'] }}</span>
                            </div>
                        @endif

                        <div class="{{ $sectionTitle }}">Skill Cards Purchased</div>
                        <div class="grid gap-2 lg:grid-cols-[1fr_1fr_0.65fr]">
                            @foreach (['10' => 8, '20' => 4, '30' => 1] as $tier => $slots)
                                <div>
                                    <span class="{{ $label }}">{{ $tier }} Badass Points</span>
                                    @for ($i = 0; $i < $slots; $i++)
                                    <select class="{{ $control }} mb-1.5" name="characters[{{ $slot }}][skills][{{ $tier }}][]" data-skill-tier="{{ $tier }}" data-needs-hero @disabled(! $selectedHero)>
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
                        <div class="grid gap-2 md:grid-cols-2 2xl:grid-cols-4">
                            <label>
                                <span class="{{ $label }}">Hand left enchant</span>
                                <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][hand_left_enchant]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero)>
                                    <option value="">None</option>
                                    @foreach ($leftEnchants as $option)
                                        <option value="{{ $option['value'] }}" @selected($character['equipment']['hand_left_enchant'] === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @foreach (['hand_item_1' => 'Hand item 1', 'hand_item_2' => 'Hand item 2'] as $key => $labelText)
                                <label>
                                    <span class="{{ $label }}">{{ $labelText }}</span>
                                    <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][{{ $key }}]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                        <option value="">None</option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item['index'] }}" @selected($character['equipment'][$key] === $item['index'])>{{ $item['index'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endforeach
                            <label>
                                <span class="{{ $label }}">Hand right enchant</span>
                                <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][hand_right_enchant]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero)>
                                    <option value="">None</option>
                                    @foreach ($rightEnchants as $option)
                                        <option value="{{ $option['value'] }}" @selected($character['equipment']['hand_right_enchant'] === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="{{ $label }}">Armor left enchant</span>
                                <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][armor_left_enchant]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero)>
                                    <option value="">None</option>
                                    @foreach ($leftEnchants as $option)
                                        <option value="{{ $option['value'] }}" @selected($character['equipment']['armor_left_enchant'] === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="{{ $label }}">Armor item</span>
                                <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][armor_item]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                    <option value="">None</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item['index'] }}" @selected($character['equipment']['armor_item'] === $item['index'])>{{ $item['index'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="{{ $label }}">Armor right enchant</span>
                                <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][armor_right_enchant]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero)>
                                    <option value="">None</option>
                                    @foreach ($rightEnchants as $option)
                                        <option value="{{ $option['value'] }}" @selected($character['equipment']['armor_right_enchant'] === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="{{ $label }}">Artifact item</span>
                                <select class="{{ $control }}" name="characters[{{ $slot }}][equipment][artifact_item]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                    <option value="">None</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item['index'] }}" @selected($character['equipment']['artifact_item'] === $item['index'])>{{ $item['index'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="{{ $sectionTitle }}">Inventory Items</div>
                        <div class="grid gap-2 md:grid-cols-2 2xl:grid-cols-3">
                            @foreach ($character['inventory'] as $index => $row)
                                <div class="flex items-center gap-2">
                                    <select class="{{ $control }}" name="characters[{{ $slot }}][inventory][{{ $index }}][item]" data-filter="item" data-needs-hero @disabled(! $selectedHero)>
                                        <option value="">Empty</option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item['index'] }}" @selected($row['item'] === $item['index'])>{{ $item['index'] }}</option>
                                        @endforeach
                                    </select>
                                    <label class="grid min-w-20 grid-cols-[18px_auto] items-center gap-1 text-xs font-extrabold text-slate-500">
                                        <input class="h-4 w-4 accent-emerald-800 disabled:cursor-not-allowed disabled:opacity-40" type="checkbox" name="characters[{{ $slot }}][inventory][{{ $index }}][rekup]" value="1" @checked($row['rekup']) data-rekup data-needs-hero @disabled(! $selectedHero)>
                                        <span>Rekup</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[1.4fr_0.6fr]">
                <div class="{{ $panel }} min-w-0">
                    <div class="mb-3 flex items-center justify-between gap-3 font-black">
                        <span>Campaign</span>
                        <small class="font-bold text-slate-500"><span data-campaign-count>{{ collect($state['campaign'])->filter()->count() }}</span> completed</small>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($campaignQuests as $group => $quests)
                            <div>
                                <h3 class="mb-2 text-base font-black">{{ $group }}</h3>
                                @foreach ($quests as $quest)
                                    @php $key = md5($group.'|'.$quest['quest']); @endphp
                                    <label class="flex min-h-7 items-center gap-2 text-sm">
                                        <input class="h-4 w-4 accent-emerald-800" type="checkbox" name="campaign[{{ $key }}]" value="1" @checked($state['campaign'][$key] ?? false)>
                                        <span>{{ $quest['quest'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="{{ $panel }}">
                    <div class="mb-3 flex items-center justify-between gap-3 font-black">
                        <span>Notes</span>
                        <small class="font-bold text-slate-500">Freeform campaign memory</small>
                    </div>
                    <textarea class="{{ $control }} min-h-[360px] resize-y" name="notes" rows="16">{{ $state['notes'] }}</textarea>
                </div>
            </section>

            <div class="mt-5 flex flex-col gap-2 md:flex-row md:justify-end">
                <button class="{{ $button }} min-w-56" type="submit" name="intent" value="save">Save Tracker</button>
                <button class="min-h-11 rounded-md border border-emerald-800 px-5 text-sm font-extrabold text-emerald-950 hover:bg-emerald-50" type="submit" name="intent" value="save_as">Save As New Campaign</button>
            </div>
        </form>
    </main>
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
