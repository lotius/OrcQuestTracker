@extends('layouts.base')

@section('body')
    <header class="torn-bottom sticky top-0 z-40 bg-orc text-white shadow-[0_2px_10px_rgba(0,0,0,.25)]">
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-3 sm:px-4">
            <a class="flex min-w-0 items-center gap-2" href="{{ route('campaigns.index') }}">
                <img class="size-9 shrink-0 drop-shadow" src="{{ asset('favicon.svg') }}" alt="">
                <span class="min-w-0 leading-none">
                    <span class="hidden text-[10px] font-semibold uppercase tracking-[0.2em] text-sage sm:block">OrcQuest WarPath</span>
                    <span class="font-display block truncate text-xl sm:text-2xl">Upgrade Tracker</span>
                </span>
            </a>
            <nav class="ml-auto flex items-center gap-1 text-sm font-semibold">
                <a class="rounded-md px-2.5 py-2 text-white/75 hover:bg-white/10 hover:text-white aria-[current=page]:bg-white/15 aria-[current=page]:text-white" href="{{ route('campaigns.index') }}" @if (request()->routeIs('campaigns.index')) aria-current="page" @endif>Campaigns</a>
                <a class="rounded-md px-2.5 py-2 text-white/75 hover:bg-white/10 hover:text-white aria-[current=page]:bg-white/15 aria-[current=page]:text-white" href="{{ route('account') }}" @if (request()->routeIs('account')) aria-current="page" @endif>Account</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md px-2.5 py-2 text-white/75 hover:bg-white/10 hover:text-white" type="submit">Sign out</button>
                </form>
            </nav>
        </div>
    </header>

    @if (session('status'))
        <div class="toast fixed inset-x-0 top-[4.25rem] z-50 mx-auto w-max max-w-[90vw] rounded-full bg-orc-600 px-4 py-2 text-sm font-semibold text-white shadow-lg" role="status">{{ session('status') }}</div>
    @endif

    <main class="mx-auto max-w-6xl px-3 pb-16 pt-5 sm:px-4">
        @yield('content')
        @include('partials.fan-notice')
    </main>
@endsection
