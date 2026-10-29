@extends('layouts.base')

@section('title', $campaign->name.' · OrcQuest Tracker')

@section('body')
    @php
        $resourceNames = ['wood', 'metal', 'leather', 'gold'];
        $resourceIcons = collect($resourceNames)->mapWithKeys(fn ($resource) => [$resource => asset("images/icons/{$resource}-resource.png")]);
        $healthColors = ['Red' => '#a8201a', 'Orange' => '#d9741c', 'Yellow' => '#d6b11c'];
        $equipmentSlots = [
            ['item' => 'hand_item_1', 'left' => 'hand_item_1_left_enchant', 'right' => 'hand_item_1_right_enchant', 'label' => 'Hand 1'],
            ['item' => 'hand_item_2', 'left' => 'hand_item_2_left_enchant', 'right' => 'hand_item_2_right_enchant', 'label' => 'Hand 2'],
            ['item' => 'armor_item', 'left' => 'armor_left_enchant', 'right' => 'armor_right_enchant', 'label' => 'Armor'],
            ['item' => 'artifact_item', 'left' => 'artifact_left_enchant', 'right' => 'artifact_right_enchant', 'label' => 'Artifact'],
        ];
        $itemLabels = $items->pluck('label', 'index');
        $activeSlots = collect($state['characters'])->filter(fn ($character) => $character['active'] ?? false)->keys();
        $firstActiveSlot = $activeSlots->first();
    @endphp

    {{-- Header ----------------------------------------------------------------------------------- --}}
    <header class="torn-bottom sticky top-0 z-40 bg-orc text-white shadow-[0_2px_10px_rgba(0,0,0,.25)]">
        <div class="mx-auto flex h-14 max-w-[1600px] items-center gap-2 px-3 sm:gap-3 sm:px-4 lg:px-6">
            <a class="shrink-0" href="{{ route('campaigns.index') }}" title="All campaigns"><img class="size-9 drop-shadow" src="{{ asset('favicon.svg') }}" alt="All campaigns"></a>
            <details class="relative min-w-0" data-menu>
                <summary class="flex min-w-0 cursor-pointer list-none items-center gap-1 rounded-md py-1 pl-1 pr-1.5 hover:bg-white/10 [&::-webkit-details-marker]:hidden" title="Switch campaign">
                    <span class="min-w-0 leading-none">
                        <span class="hidden text-[10px] font-semibold uppercase tracking-[0.2em] text-sage sm:block">OrcQuest campaign</span>
                        <span class="font-display block max-w-[42vw] truncate text-xl sm:max-w-[16rem] sm:text-2xl lg:max-w-[22rem]" data-campaign-title>{{ $campaign->name }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-white/60 sm:hidden" data-save-status-secondary>All changes saved</span>
                    </span>
                    <x-icon name="chevron" class="size-4 shrink-0 text-white/70" />
                </summary>
                <div class="panel absolute left-0 top-full z-50 mt-2 grid w-[min(20rem,calc(100vw-1.5rem))] gap-0.5 p-2 text-ink">
                    <p class="field-label px-2 pt-1">Switch campaign</p>
                    <div class="grid max-h-[50vh] gap-0.5 overflow-y-auto">
                        @foreach ($campaigns as $other)
                            <a class="flex items-center justify-between gap-2 rounded-md px-2 py-2 text-sm hover:bg-paper-deep aria-[current=page]:bg-sage-soft aria-[current=page]:font-semibold aria-[current=page]:text-orc" href="{{ route('campaigns.show', $other) }}" @if ($other->id === $campaign->id) aria-current="page" @endif>
                                <span class="truncate">{{ $other->name }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="mt-1 grid gap-0.5 border-t border-line pt-1.5">
                        <a class="flex items-center gap-2 rounded-md px-2 py-2 text-sm font-semibold text-orc-700 hover:bg-sage-soft" href="{{ route('campaigns.index') }}#new-campaign"><x-icon name="plus" class="size-4" /> New campaign</a>
                        <a class="flex items-center gap-2 rounded-md px-2 py-2 text-sm text-ink-soft hover:bg-paper-deep" href="{{ route('campaigns.index') }}"><x-icon name="map" class="size-4" /> Manage campaigns</a>
                    </div>
                </div>
            </details>

            <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-black/30 bg-orc pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_14px_rgba(0,0,0,.2)] md:static md:ml-4 md:flex md:gap-1 md:border-0 md:bg-transparent md:pb-0 md:shadow-none" data-tabs aria-label="Sections">
                @foreach (['characters' => ['users', 'Characters'], 'resources' => ['hammer', 'Kraft & Rekup'], 'campaign' => ['map', 'Campaign'], 'history' => ['scroll', 'History']] as $tab => [$icon, $tabLabel])
                    <button class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-semibold text-white/60 transition-colors hover:text-white aria-pressed:text-white aria-pressed:shadow-[inset_0_3px_0_var(--color-sage)] md:flex-row md:gap-2 md:rounded-md md:px-3 md:text-sm md:hover:bg-white/10 md:aria-pressed:bg-white/15 md:aria-pressed:shadow-none" type="button" data-tab="{{ $tab }}" aria-pressed="{{ $tab === 'characters' ? 'true' : 'false' }}">
                        <x-icon :name="$icon" class="size-5 md:size-4" />
                        <span>{{ $tabLabel }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-1 sm:gap-1.5">
                <span class="mr-1 hidden text-xs font-medium text-white/60 sm:inline" data-save-status>All changes saved</span>
                <button class="icon-btn text-white hover:bg-white/10" type="button" data-undo disabled title="Undo" aria-label="Undo"><x-icon name="undo" /></button>
                <button class="icon-btn text-white hover:bg-white/10" type="button" data-redo disabled title="Redo" aria-label="Redo"><x-icon name="redo" /></button>
                <button class="btn btn-bone px-2.5 sm:px-3.5" type="button" data-save-now title="Save now">
                    <x-icon name="save" class="size-4" /><span class="hidden sm:inline">Save</span>
                </button>
                <details class="relative" data-menu>
                    <summary class="icon-btn cursor-pointer list-none text-white hover:bg-white/10 [&::-webkit-details-marker]:hidden" aria-label="More" title="More"><x-icon name="more" /></summary>
                    <div class="panel absolute right-0 top-full z-50 mt-2 grid w-[min(16rem,calc(100vw-1.5rem))] gap-0.5 p-2 text-ink">
                        <form method="post" action="{{ route('campaigns.duplicate', $campaign) }}">
                            @csrf
                            <button class="btn w-full justify-start text-ink-soft hover:bg-paper-deep" type="submit"><x-icon name="copy" class="size-4" /> Duplicate campaign</button>
                        </form>
                        <a class="btn justify-start text-ink-soft hover:bg-paper-deep" href="{{ route('campaigns.export', $campaign) }}"><x-icon name="save" class="size-4" /> Export to file</a>
                        <a class="btn justify-start text-ink-soft hover:bg-paper-deep" href="{{ route('campaigns.index') }}"><x-icon name="map" class="size-4" /> All campaigns</a>
                        <div class="my-1 border-t border-line"></div>
                        <a class="btn justify-start text-ink-soft hover:bg-paper-deep" href="{{ route('account') }}"><x-icon name="users" class="size-4" /> Account</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn w-full justify-start text-ink-soft hover:bg-paper-deep" type="submit"><x-icon name="x" class="size-4" /> Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </header>

    @if (session('status'))
        <div class="toast fixed inset-x-0 top-[4.25rem] z-50 mx-auto w-max max-w-[90vw] rounded-full bg-orc-600 px-4 py-2 text-sm font-semibold text-white shadow-lg" role="status">{{ session('status') }}</div>
    @endif

    <main class="mx-auto max-w-[1600px] px-3 pb-28 pt-4 sm:px-4 md:pb-12 lg:px-6">
        <form id="tracker-form" method="post" action="{{ route('campaigns.save', $campaign) }}" data-tracker-root data-version="{{ $campaign->version }}" data-campaigns-url="{{ route('campaigns.index') }}">
            @csrf

            {{-- Characters ------------------------------------------------------------------------ --}}
            <section data-tab-panel="characters">
                <div class="z-30 -mx-3 mb-3 bg-parchment/95 px-3 pb-2 pt-1 md:sticky md:top-[3.5rem] md:pt-3 sm:-mx-4 sm:px-4 lg:-mx-6 lg:px-6">
                    <div class="flex flex-wrap items-center gap-1.5" data-character-tabs>
                        @foreach ($state['characters'] as $slot => $character)
                            @php $chipHero = $summaries[$slot]['hero']; @endphp
                            <button class="flex shrink-0 items-center gap-1.5 rounded-full border border-line bg-paper py-1 pl-1 pr-2.5 text-left sm:gap-2 sm:pr-3 text-ink shadow-sm transition-colors hover:border-orc-600 aria-pressed:border-orc aria-pressed:bg-orc aria-pressed:text-white" type="button" data-character-tab="{{ $slot }}" aria-pressed="{{ (string) $slot === (string) $firstActiveSlot ? 'true' : 'false' }}" @if (! ($character['active'] ?? false)) hidden @endif>
                                <span class="relative">
                                    <img class="size-7 rounded-full sm:size-8 object-cover ring-1 ring-black/10" src="{{ $chipHero['icon'] ?? '' }}" alt="" data-chip-icon @if (! $chipHero) hidden @endif>
                                    <span class="portrait-fallback size-7 place-items-center rounded-full bg-paper-deep text-muted sm:size-8"><x-icon name="skull" class="size-4" /></span>
                                    <span class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full bg-danger ring-2 ring-paper" data-chip-alert title="Over a carrying limit" hidden></span>
                                </span>
                                <span class="min-w-0 leading-tight">
                                    <span class="block max-w-[6.5rem] truncate text-sm font-semibold sm:max-w-[8.5rem]" data-chip-name>{{ $chipHero['name'] ?? 'Character '.$slot }}</span>
                                    <span class="hidden max-w-[8.5rem] truncate text-[11px] opacity-70 sm:block" data-chip-player>{{ $character['player'] ?: 'No player' }}</span>
                                </span>
                            </button>
                        @endforeach
                        <button class="flex shrink-0 items-center gap-1.5 rounded-full border border-line bg-paper px-3 py-2 text-sm font-semibold text-ink-soft shadow-sm hover:border-orc-600 aria-pressed:border-orc aria-pressed:bg-orc aria-pressed:text-white" type="button" data-character-tab="all" aria-pressed="false" title="Show every character at once">
                            <x-icon name="users" class="size-4" /> Party
                        </button>
                        <button class="flex shrink-0 items-center gap-1.5 rounded-full border border-dashed border-orc-600/60 px-3 py-2 text-sm font-semibold text-orc-700 hover:bg-sage-soft" type="button" data-add-character @if ($activeSlots->count() >= count($state['characters'])) hidden @endif>
                            <x-icon name="plus" class="size-4" /> Add orc
                        </button>
                    </div>
                </div>

                <div class="grid gap-4">
                    @foreach ($state['characters'] as $slot => $character)
                        @php
                            $summary = $summaries[$slot];
                            $selectedHero = $summary['hero'];
                            $skillGroups = $selectedHero['skills'] ?? ['10' => [], '20' => [], '30' => []];
                            $meters = [
                                'armor' => ['Armor', $summary['armor_weight'], $summary['armor_limit']],
                                'hands' => ['Hands', $summary['hand_weight'], $summary['hand_limit']],
                                'inventory' => ['Inventory', $summary['inventory_weight'], $summary['inventory_limit']],
                            ];
                        @endphp
                        <article class="panel overflow-hidden" data-character="{{ $slot }}" @if ((string) $slot !== (string) $firstActiveSlot) hidden @endif>
                            <input type="hidden" name="characters[{{ $slot }}][active]" value="{{ ($character['active'] ?? false) ? 1 : 0 }}" data-character-active>

                            {{-- Identity strip --}}
                            <div class="grid gap-3 border-b border-line bg-paper-deep/60 p-3 sm:p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                <div class="flex min-w-0 items-start gap-3">
                                    <img class="size-16 shrink-0 rounded-lg border-2 border-orc object-cover shadow-sm sm:size-[4.5rem]" src="{{ $selectedHero['icon'] ?? '' }}" alt="{{ $selectedHero ? $selectedHero['name'].' portrait' : '' }}" data-hero-icon @if (! $selectedHero) hidden @endif>
                                    <div class="portrait-fallback size-16 shrink-0 place-items-center rounded-lg border-2 border-dashed border-line-strong text-line-strong sm:size-[4.5rem]"><x-icon name="skull" class="size-8" /></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="eyebrow">Character {{ $slot }}</p>
                                                <h2 class="font-display truncate text-[1.7rem] leading-tight text-ink" data-hero-name>{{ $selectedHero['name'] ?? 'Unassigned Hero' }}</h2>
                                            </div>
                                            <button class="icon-btn -mr-1 -mt-1 size-9 text-muted hover:bg-danger/10 hover:text-danger lg:hidden" type="button" data-remove-character title="Remove this character" aria-label="Remove this character"><x-icon name="trash" class="size-4" /></button>
                                        </div>
                                        <div class="mt-1.5 flex flex-wrap gap-1.5" data-hero-facts @if (! $selectedHero) hidden @endif>
                                            @if ($selectedHero)
                                                <span class="pill">{{ $selectedHero['class'] }}</span>
                                                <span class="pill"><span class="size-2.5 rounded-full" style="background: {{ $healthColors[$selectedHero['health_color']] ?? '#776e62' }}"></span>{{ $selectedHero['base_health'] }} {{ $selectedHero['health_color'] }} health</span>
                                                <span class="pill">Starts with {{ $selectedHero['starting_stuff'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="grid flex-1 grid-cols-3 gap-3 lg:w-80 lg:flex-none">
                                        @foreach ($meters as $key => [$meterLabel, $used, $limit])
                                            @php $over = $limit && $used > $limit; @endphp
                                            <div class="group min-w-0" data-weight="{{ $key }}" data-over="{{ $over ? 'true' : 'false' }}">
                                                <div class="flex items-baseline justify-between gap-1 text-[11px] font-semibold uppercase tracking-wide text-muted group-data-[over=true]:text-danger">
                                                    <span class="truncate">{{ $meterLabel }}</span>
                                                    <span class="text-xs tabular-nums text-ink-soft group-data-[over=true]:text-danger" data-weight-value>{{ $used }}/{{ $limit ?: '–' }}</span>
                                                </div>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-line">
                                                    <div class="h-full rounded-full bg-orc-600 transition-[width] group-data-[over=true]:bg-danger" style="width: {{ $limit ? min(100, round($used / $limit * 100)) : 0 }}%" data-weight-bar></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button class="icon-btn hidden size-9 text-muted hover:bg-danger/10 hover:text-danger lg:inline-grid" type="button" data-remove-character title="Remove this character" aria-label="Remove this character"><x-icon name="trash" class="size-4" /></button>
                                </div>
                            </div>

                            <div class="grid gap-3 p-3 sm:p-4">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] lg:max-w-2xl">
                                    <label>
                                        <span class="field-label">Player</span>
                                        <input class="control" name="characters[{{ $slot }}][player]" value="{{ $character['player'] }}" placeholder="Who's playing?" autocomplete="off" data-player-input>
                                    </label>
                                    <label>
                                        <span class="field-label">Hero</span>
                                        <select class="control" name="characters[{{ $slot }}][hero_id]" data-filter="hero" data-hero-select>
                                            <option value="">Choose hero</option>
                                            @foreach ($heroes as $hero)
                                                <option value="{{ $hero['id'] }}" @selected($character['hero_id'] === $hero['id'])>{{ $hero['name'] }} · {{ $hero['class'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>

                                <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                                    <div class="grid gap-3">
                                        {{-- Equipment --}}
                                        <details class="tray rounded-lg border border-line bg-white/50" data-character-tray="equipment" open>
                                            <summary>
                                                <span class="section-title">Equipment</span>
                                                <span class="min-w-0 flex-1 truncate text-right text-xs text-muted" data-tray-summary="equipment"></span>
                                                <x-icon name="chevron" class="chev size-4 text-muted" />
                                            </summary>
                                            <div class="@container/equipment grid gap-2 border-t border-line p-2 sm:p-3">
                                                @foreach ($equipmentSlots as $equip)
                                                    <div class="rounded-md border border-line bg-paper p-2" data-slot>
                                                        <div class="mb-1.5 flex items-center gap-1">
                                                            <button class="-ml-0.5 rounded p-0.5 text-line-strong hover:bg-paper-deep hover:text-muted" type="button" data-drag-handle data-move-item title="Tap to move, or drag" aria-label="Move this item"><x-icon name="grip" class="size-4" /></button>
                                                            <span class="text-sm font-semibold text-ink-soft">{{ $equip['label'] }}</span>
                                                            <button class="ml-auto inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-orc-700 hover:bg-sage-soft" type="button" data-move-item data-needs-item><x-icon name="move" class="size-3.5" /> Move</button>
                                                        </div>
                                                        <div class="equip-fields">
                                                            <label class="eq-left">
                                                                <span class="field-label">Left enchant</span>
                                                                <select class="control" name="characters[{{ $slot }}][equipment][{{ $equip['left'] }}]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero)>
                                                                    <option value="">None</option>
                                                                    @if ($current = ($character['equipment'][$equip['left']] ?? ''))
                                                                        <option value="{{ $current }}" selected>{{ $current }}</option>
                                                                    @endif
                                                                </select>
                                                            </label>
                                                            <label class="eq-item">
                                                                <span class="field-label">Item</span>
                                                                <select class="control font-medium" name="characters[{{ $slot }}][equipment][{{ $equip['item'] }}]" data-filter="item" data-location="{{ $equip['label'] }}" data-needs-hero @disabled(! $selectedHero)>
                                                                    <option value="">None</option>
                                                                    @if ($current = ($character['equipment'][$equip['item']] ?? ''))
                                                                        <option value="{{ $current }}" selected>{{ $itemLabels[$current] ?? $current }}</option>
                                                                    @endif
                                                                </select>
                                                            </label>
                                                            <label class="eq-right">
                                                                <span class="field-label">Right enchant</span>
                                                                <select class="control" name="characters[{{ $slot }}][equipment][{{ $equip['right'] }}]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero)>
                                                                    <option value="">None</option>
                                                                    @if ($current = ($character['equipment'][$equip['right']] ?? ''))
                                                                        <option value="{{ $current }}" selected>{{ $current }}</option>
                                                                    @endif
                                                                </select>
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>

                                        {{-- Skills --}}
                                        <details class="tray rounded-lg border border-line bg-white/50" data-character-tray="skills" open>
                                            <summary>
                                                <span class="section-title">Skill Cards</span>
                                                <span class="min-w-0 flex-1 truncate text-right text-xs text-muted" data-tray-summary="skills"></span>
                                                <x-icon name="chevron" class="chev size-4 text-muted" />
                                            </summary>
                                            <div class="@container/skills border-t border-line p-2 sm:p-3">
                                                <div class="grid gap-3 @xl/skills:grid-cols-3">
                                                    @foreach (['10' => 8, '20' => 4, '30' => 1] as $tier => $slots)
                                                        <div class="min-w-0">
                                                            <div class="mb-1.5 flex items-baseline justify-between">
                                                                <span class="font-display text-base text-orc-700">{{ $tier }} Badass Points</span>
                                                                <span class="text-xs font-semibold tabular-nums text-muted"><span data-skill-count="{{ $tier }}">0</span>/{{ $slots }}</span>
                                                            </div>
                                                            <div class="grid gap-1.5">
                                                                @for ($i = 0; $i < $slots; $i++)
                                                                    <label class="block" data-skill-slot>
                                                                        <span class="sr-only">{{ $tier }} point skill {{ $i + 1 }}</span>
                                                                        <select class="control" name="characters[{{ $slot }}][skills][{{ $tier }}][]" data-skill-tier="{{ $tier }}" data-needs-hero @disabled(! $selectedHero)>
                                                                            <option value="">Add a skill…</option>
                                                                            @foreach ($skillGroups[$tier] ?? [] as $skill)
                                                                                <option value="{{ $skill }}" @selected(($character['skills'][$tier][$i] ?? '') === $skill)>{{ $skill }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </label>
                                                                @endfor
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </details>
                                    </div>

                                    {{-- Inventory --}}
                                    <details class="tray rounded-lg border border-line bg-white/50" data-character-tray="inventory" open>
                                        <summary>
                                            <span class="section-title">Inventory</span>
                                            <span class="min-w-0 flex-1 truncate text-right text-xs text-muted" data-tray-summary="inventory"></span>
                                            <x-icon name="chevron" class="chev size-4 text-muted" />
                                        </summary>
                                        <div class="@container/inventory border-t border-line p-2 sm:p-3">
                                            <div class="inv-head mb-1 gap-1.5 px-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted" aria-hidden="true">
                                                <span class="text-center">#</span><span>Left enchant</span><span>Item</span><span>Right enchant</span><span class="text-center">Rekup</span>
                                            </div>
                                            <div class="grid gap-1.5">
                                                @foreach ($character['inventory'] as $index => $row)
                                                    @php $inventoryItem = $items->firstWhere('index', $row['item']); @endphp
                                                    <div class="inv-row rounded-md border border-transparent p-0.5 odd:bg-paper-deep/50" data-inventory-row data-slot data-empty="{{ $row['item'] === '' ? 'true' : 'false' }}">
                                                        <button class="inv-num flex flex-col items-center justify-center rounded text-muted hover:bg-paper-deep" type="button" data-drag-handle data-move-item title="Tap to move, or drag" aria-label="Move item in slot {{ $index + 1 }}">
                                                            <span class="text-[11px] font-semibold tabular-nums">{{ $index + 1 }}</span>
                                                            <x-icon name="grip" class="size-3.5 text-line-strong" />
                                                        </button>
                                                        <label class="inv-left">
                                                            <span class="sr-only">Left enchant</span>
                                                            <select class="control" name="characters[{{ $slot }}][inventory][{{ $index }}][left_enchant]" data-filter="enchant" data-enchant-side="left" data-needs-hero @disabled(! $selectedHero || $row['item'] === '')>
                                                                <option value="">Left enchant</option>
                                                                @if ($current = ($row['left_enchant'] ?? ''))
                                                                    <option value="{{ $current }}" selected>{{ $current }}</option>
                                                                @endif
                                                            </select>
                                                        </label>
                                                        <label class="inv-item">
                                                            <span class="sr-only">Item</span>
                                                            <select class="control font-medium" name="characters[{{ $slot }}][inventory][{{ $index }}][item]" data-filter="item" data-location="Inventory {{ $index + 1 }}" data-needs-hero @disabled(! $selectedHero)>
                                                                <option value="">Empty</option>
                                                                @if ($current = $row['item'])
                                                                    <option value="{{ $current }}" selected>{{ $itemLabels[$current] ?? $current }}</option>
                                                                @endif
                                                            </select>
                                                        </label>
                                                        <label class="inv-right">
                                                            <span class="sr-only">Right enchant</span>
                                                            <select class="control" name="characters[{{ $slot }}][inventory][{{ $index }}][right_enchant]" data-filter="enchant" data-enchant-side="right" data-needs-hero @disabled(! $selectedHero || $row['item'] === '')>
                                                                <option value="">Right enchant</option>
                                                                @if ($current = ($row['right_enchant'] ?? ''))
                                                                    <option value="{{ $current }}" selected>{{ $current }}</option>
                                                                @endif
                                                            </select>
                                                        </label>
                                                        <label class="inv-rekup h-10 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-line bg-white px-2 text-xs font-semibold text-muted has-checked:border-ember has-checked:bg-ember/10 has-checked:text-[#8a4210] sm:h-9" title="Break this card down into resources">
                                                            <input class="size-4" type="checkbox" name="characters[{{ $slot }}][inventory][{{ $index }}][rekup]" value="1" @checked($row['rekup'] || ($inventoryItem['always_rekup'] ?? false)) data-rekup data-needs-hero @disabled(! $selectedHero || ($inventoryItem['always_rekup'] ?? false))>
                                                            <span>Rekup</span>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </article>
                    @endforeach

                    <div class="panel grid place-items-center gap-3 p-10 text-center" data-no-characters @if ($activeSlots->isNotEmpty()) hidden @endif>
                        <x-icon name="skull" class="size-10 text-line-strong" />
                        <p class="font-display text-2xl text-stone">No orcs in this warband yet</p>
                        <button class="btn btn-primary" type="button" data-add-character><x-icon name="plus" class="size-4" /> Add an orc</button>
                    </div>
                </div>
            </section>

            {{-- Kraft & Rekup ------------------------------------------------------------------------- --}}
            <section class="grid items-start gap-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]" data-tab-panel="resources" hidden>
                <h2 class="page-title lg:col-span-2">Kraft &amp; Rekup</h2>

                <div class="grid gap-4">
                    <div class="chamfer">
                        <div class="chamfer-inner p-3 sm:p-4">
                            <div class="mb-2 flex items-baseline justify-between gap-2">
                                <h3 class="section-title">Rekup Pool</h3>
                                <span class="text-xs text-ink-soft/80">From inventory cards marked rekup</span>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach ($resourceNames as $resource)
                                    <div class="flex flex-col items-center rounded-md bg-white/70 px-1 py-2 shadow-sm">
                                        <img class="size-8 object-contain" src="{{ $resourceIcons[$resource] }}" alt="">
                                        <strong class="text-3xl font-bold leading-tight tabular-nums text-ink" data-resource="{{ $resource }}">{{ $resources['totals'][$resource] }}</strong>
                                        <span class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft/80">{{ ucfirst($resource) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="panel p-3 sm:p-4">
                        <div class="mb-1 flex items-baseline justify-between gap-2">
                            <h3 class="section-title">Ready to Kraft</h3>
                            <span class="text-xs text-muted"><span class="font-semibold text-ink" data-upgrades-count>0</span> affordable now</span>
                        </div>
                        <div class="max-h-[22rem] overflow-auto" data-upgrades-list></div>
                    </div>
                </div>

                <div class="panel @container/kraft p-3 sm:p-4">
                    <div class="mb-3 flex items-baseline justify-between gap-2">
                        <h3 class="section-title">Kraft Planner</h3>
                        <span class="text-right text-xs text-muted">Pick whole rekup cards with the least waste</span>
                    </div>
                    <div class="grid gap-2 @lg/kraft:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                        <label>
                            <span class="field-label">Upgrade target</span>
                            <select class="control" data-kraft-target>
                                <option value="">Choose an owned upgradeable item</option>
                            </select>
                        </label>
                        <div>
                            <div class="field-label flex items-center gap-1">
                                <label for="kraft-strategy">Strategy</label>
                                <button class="text-muted hover:text-orc-600" type="button" data-modal-open="strategy-help" aria-label="Explain strategies"><x-icon name="help" class="size-3.5" /></button>
                            </div>
                            <select class="control" id="kraft-strategy" data-kraft-strategy>
                                <option value="least_waste">Least wasted resources</option>
                                <option value="retain_wood">Keep the most wood</option>
                                <option value="retain_gold">Keep the most gold</option>
                                <option value="retain_metal">Keep the most metal</option>
                                <option value="retain_leather">Keep the most leather</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-3 @2xl/kraft:grid-cols-2">
                        <div class="rounded-md border border-line bg-white/60 p-3" data-kraft-summary></div>
                        <div class="rounded-md border border-line bg-white/60 p-3" data-kraft-suggestion></div>
                    </div>
                    <div class="mt-4">
                        <div class="mb-2 flex items-baseline justify-between gap-2">
                            <h4 class="font-display text-base text-orc-700">Almost Kraftable</h4>
                            <span class="text-xs text-muted">Owned upgrades that need more rekup</span>
                        </div>
                        <div class="grid gap-2 @md/kraft:grid-cols-2 @3xl/kraft:grid-cols-3" data-almost-craftable></div>
                    </div>
                </div>
            </section>

            {{-- Campaign ------------------------------------------------------------------------------ --}}
            <section class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]" data-tab-panel="campaign" hidden>
                <h2 class="page-title lg:col-span-2">Campaign</h2>

                <div class="panel min-w-0 p-3 sm:p-4">
                    <div class="mb-3 grid items-end gap-3 sm:grid-cols-[minmax(0,26rem)_1fr]">
                        <label>
                            <span class="field-label">Campaign name</span>
                            <input class="control" name="campaign_name" value="{{ $campaign->name }}" maxlength="120" required data-campaign-name>
                        </label>
                        <p class="text-sm text-muted sm:text-right"><span class="text-2xl font-bold tabular-nums text-orc-700" data-campaign-count>{{ collect($state['campaign'])->filter()->count() }}</span> quests completed</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($campaignQuests as $group => $quests)
                            <div class="rounded-lg border border-line bg-white/60" data-campaign-group>
                                <div class="flex items-baseline justify-between gap-2 border-b border-line px-3 py-2">
                                    <h3 class="section-title text-base">{{ $group }}</h3>
                                    <span class="shrink-0 text-xs font-semibold tabular-nums text-muted"><span data-campaign-group-count>0</span>/{{ count($quests) }}</span>
                                </div>
                                <div class="grid p-1">
                                    @foreach ($quests as $quest)
                                        @php $key = md5($group.'|'.$quest['quest']); @endphp
                                        <label class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded px-2 text-sm hover:bg-paper-deep has-checked:text-muted sm:min-h-9">
                                            <input class="size-[1.05rem] shrink-0" type="checkbox" name="campaign[{{ $key }}]" value="1" @checked($state['campaign'][$key] ?? false)>
                                            <span>{{ $quest['quest'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="panel p-3 sm:p-4 lg:sticky lg:top-20">
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <h3 class="section-title">Notes</h3>
                        <span class="text-xs text-muted">Campaign memory</span>
                    </div>
                    <textarea class="control min-h-64 resize-y lg:min-h-[28rem]" name="notes" rows="14" placeholder="Grudges, loot owed, who smashed which door…">{{ $state['notes'] }}</textarea>
                </div>
            </section>
        </form>

        {{-- History (outside the form: its filters are not tracker state) ------------------------ --}}
        <section class="grid gap-4" data-tab-panel="history" hidden>
            <h2 class="page-title">History</h2>
            <div class="panel p-3 sm:p-4">
                <div class="mb-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_12rem_12rem]">
                    <label>
                        <span class="sr-only">Search history</span>
                        <input class="control" type="search" placeholder="Search history…" data-history-search>
                    </label>
                    <label>
                        <span class="sr-only">Filter by type</span>
                        <select class="control" data-history-type>
                            <option value="">Everything</option>
                            <option value="character">Characters</option>
                            <option value="item">Items &amp; moves</option>
                            <option value="rekup">Rekup</option>
                            <option value="enchant">Enchants</option>
                            <option value="skill">Skills</option>
                            <option value="kraft">Kraft &amp; upgrades</option>
                            <option value="quest">Quests</option>
                            <option value="campaign">Campaign &amp; notes</option>
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Filter by character</span>
                        <select class="control" data-history-character>
                            <option value="">Whole warband</option>
                        </select>
                    </label>
                </div>
                <div data-history-list></div>
            </div>
        </section>
        @include('partials.fan-notice')
    </main>

    <div class="fixed inset-0 z-50 hidden overflow-y-auto bg-ink/60 p-4" data-modal="strategy-help" role="dialog" aria-modal="true" aria-labelledby="strategy-help-title">
        <div class="mx-auto mt-[8vh] w-[min(560px,100%)] rounded-lg bg-paper shadow-2xl" data-modal-card>
            <div class="torn-bottom flex items-center justify-between gap-2 rounded-t-lg bg-orc px-4 py-3 text-white">
                <h2 class="font-display text-xl" id="strategy-help-title">Kraft Strategies</h2>
                <button class="icon-btn size-9 hover:bg-white/10" type="button" data-modal-close aria-label="Close"><x-icon name="x" /></button>
            </div>
            <div class="grid gap-3 p-4 pt-6 text-sm leading-relaxed text-ink-soft">
                <p><strong class="text-ink">Least wasted resources:</strong> finds the payment closest to the kraft cost, then prefers fewer cards when several payments waste the same amount.</p>
                <p><strong class="text-ink">Keep the most wood / metal / leather / gold:</strong> avoids consuming cards that generate that resource whenever possible.</p>
                <p class="rounded-md bg-sage-soft p-3 text-orc"><em>Gorbag's advice:</em> any excess resources from a kraft are lost, so waste matters.</p>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 hidden bg-ink/50" data-modal="move-item" role="dialog" aria-modal="true" aria-labelledby="move-item-title">
        <div class="absolute inset-x-0 bottom-0 flex max-h-[85dvh] flex-col rounded-t-2xl bg-paper shadow-2xl sm:inset-x-auto sm:bottom-auto sm:left-1/2 sm:top-[8vh] sm:max-h-[84vh] sm:w-[30rem] sm:-translate-x-1/2 sm:rounded-lg">
            <div class="torn-bottom z-10 flex items-center gap-2 rounded-t-2xl bg-orc px-4 py-3 text-white sm:rounded-t-lg">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sage" data-move-from>Move</p>
                    <h2 class="font-display truncate text-xl" id="move-item-title" data-move-title></h2>
                </div>
                <button class="icon-btn ml-auto size-9 hover:bg-white/10" type="button" data-modal-close aria-label="Close"><x-icon name="x" /></button>
            </div>
            <div class="overflow-y-auto overscroll-contain px-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] pt-5" data-move-list></div>
        </div>
    </div>

    <script>
        window.orcQuestHistory = {{ Illuminate\Support\Js::from($history) }};
        window.orcQuestCatalog = {{ Illuminate\Support\Js::from([
            'heroes' => $heroes->values(),
            'items' => $items->values(),
            'leftEnchants' => $leftEnchants->values(),
            'rightEnchants' => $rightEnchants->values(),
        ]) }};
    </script>
@endsection
