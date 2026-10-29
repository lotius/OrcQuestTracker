@extends('layouts.base')

@section('body')
    <main class="grid min-h-dvh place-items-center px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-5 flex flex-col items-center gap-2 text-center">
                <img class="size-20 drop-shadow-md" src="{{ asset('favicon.svg') }}" alt="">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted">OrcQuest WarPath</p>
                <h1 class="font-display text-4xl leading-none text-ink">Upgrade Tracker</h1>
                <p class="rounded-full border border-line-strong bg-paper px-3 py-1 text-xs font-semibold text-ink-soft">Unofficial fan project · Not a Monolith product</p>
            </div>
            <div class="panel overflow-hidden">
                <div class="torn-bottom bg-orc px-5 py-3 text-white">
                    <h2 class="font-display text-xl">@yield('heading')</h2>
                </div>
                <div class="p-5 pt-6">
                    @yield('content')
                </div>
            </div>
            @yield('after')
            @include('partials.fan-notice')
        </div>
    </main>
@endsection
