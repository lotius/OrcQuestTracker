@extends('layouts.app')

@section('title', 'Campaigns · OrcQuest Tracker')

@section('content')
    <h1 class="page-title mb-4">Campaigns</h1>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="grid gap-3 sm:grid-cols-2" aria-label="Your campaigns">
            @forelse ($campaigns as ['model' => $campaign, 'heroes' => $heroes, 'quests' => $quests])
                <article class="panel flex flex-col p-4">
                    <div class="flex items-start justify-between gap-2">
                        <a class="min-w-0" href="{{ route('campaigns.show', $campaign) }}">
                            <h2 class="font-display truncate text-2xl leading-tight text-ink hover:text-orc-700">{{ $campaign->name }}</h2>
                        </a>
                        <details class="relative shrink-0" data-menu>
                            <summary class="icon-btn size-9 cursor-pointer list-none text-muted hover:bg-paper-deep [&::-webkit-details-marker]:hidden" aria-label="Campaign actions"><x-icon name="more" /></summary>
                            <div class="panel absolute right-0 top-full z-30 mt-1 grid w-56 gap-0.5 p-2">
                                <form class="grid gap-1.5 border-b border-line px-1 pb-2" method="post" action="{{ route('campaigns.update', $campaign) }}">
                                    @csrf
                                    @method('patch')
                                    <label class="field-label" for="rename-{{ $campaign->id }}">Rename</label>
                                    <div class="flex gap-1.5">
                                        <input class="control" id="rename-{{ $campaign->id }}" name="name" value="{{ $campaign->name }}" maxlength="120" required>
                                        <button class="btn btn-outline px-2.5" type="submit">Save</button>
                                    </div>
                                </form>
                                <form method="post" action="{{ route('campaigns.duplicate', $campaign) }}">
                                    @csrf
                                    <button class="btn w-full justify-start text-ink-soft hover:bg-paper-deep" type="submit"><x-icon name="copy" class="size-4" /> Duplicate</button>
                                </form>
                                <a class="btn justify-start text-ink-soft hover:bg-paper-deep" href="{{ route('campaigns.export', $campaign) }}"><x-icon name="save" class="size-4" /> Export to file</a>
                                <form method="post" action="{{ route('campaigns.destroy', $campaign) }}" onsubmit="return confirm(@js('Delete '.$campaign->name.'? Its characters, quests, notes and history will be gone for good. (Export it first if you might want it back.)'))">
                                    @csrf
                                    @method('delete')
                                    <button class="btn w-full justify-start text-danger hover:bg-danger/10" type="submit"><x-icon name="trash" class="size-4" /> Delete</button>
                                </form>
                            </div>
                        </details>
                    </div>

                    <div class="mt-2 flex min-h-9 items-center">
                        @forelse ($heroes as $hero)
                            <img class="-ml-1.5 size-9 rounded-full border-2 border-paper object-cover first:ml-0" src="{{ $hero['icon'] }}" alt="{{ $hero['name'] }}" title="{{ $hero['name'] }}">
                        @empty
                            <span class="text-sm text-muted">No heroes chosen yet</span>
                        @endforelse
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-muted">
                        <div><dt>Quests</dt><dd class="text-sm font-semibold text-ink">{{ $quests }}/{{ $questCount }}</dd></div>
                        <div><dt>History</dt><dd class="text-sm font-semibold text-ink">{{ $campaign->events_count }}</dd></div>
                        <div><dt>Updated</dt><dd class="text-sm font-semibold text-ink" title="{{ $campaign->updated_at }}">{{ $campaign->updated_at->diffForHumans(short: true) }}</dd></div>
                    </dl>

                    <a class="btn btn-primary mt-4" href="{{ route('campaigns.show', $campaign) }}">Open campaign</a>
                </article>
            @empty
                <div class="panel grid place-items-center gap-2 p-10 text-center sm:col-span-2">
                    <x-icon name="skull" class="size-10 text-line-strong" />
                    <p class="font-display text-2xl text-stone">No campaigns yet</p>
                    <p class="text-sm text-muted">Start one on the right, or import a campaign file.</p>
                </div>
            @endforelse
        </section>

        <aside class="grid gap-4 lg:sticky lg:top-20">
            <form class="panel grid gap-3 p-4" id="new-campaign" method="post" action="{{ route('campaigns.store') }}">
                @csrf
                <h2 class="section-title">New campaign</h2>
                <label>
                    <span class="field-label">Name</span>
                    <input class="control" name="name" value="{{ old('name') }}" maxlength="120" placeholder="e.g. Sewer Fever run" required @if ($campaigns->isEmpty() || request()->getRequestUri() === route('campaigns.index', [], false).'#new-campaign') autofocus @endif>
                </label>
                @if ($campaigns->isNotEmpty())
                    <label>
                        <span class="field-label">Start from</span>
                        <select class="control" name="copy_from">
                            <option value="">A fresh warband</option>
                            @foreach ($campaigns as ['model' => $campaign])
                                <option value="{{ $campaign->id }}">A copy of {{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @error('name') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                <button class="btn btn-primary" type="submit"><x-icon name="plus" class="size-4" /> Create campaign</button>
            </form>

            <form class="panel grid gap-3 p-4" method="post" action="{{ route('campaigns.import') }}" enctype="multipart/form-data">
                @csrf
                <h2 class="section-title">Import</h2>
                <p class="text-sm text-muted">Load a campaign exported from this tracker, or the save file from the original version (<code class="text-xs">orcquest-tracker-state.json</code>).</p>
                <input class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-md file:border-0 file:bg-paper-deep file:px-3 file:py-2 file:font-semibold file:text-ink-soft hover:file:bg-line" type="file" name="file" accept="application/json,.json" required>
                @error('file') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                <button class="btn btn-outline" type="submit">Import file</button>
            </form>
        </aside>
    </div>
@endsection
