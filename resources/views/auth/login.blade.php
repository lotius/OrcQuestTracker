@extends('layouts.auth')

@section('title', 'Sign in · OrcQuest Tracker')
@section('heading', 'Sign in')

@section('content')
    <form class="grid gap-4" method="post" action="{{ route('login') }}">
        @csrf
        <label>
            <span class="field-label">Email</span>
            <input class="control" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </label>
        <label>
            <span class="field-label">Password</span>
            <input class="control" type="password" name="password" autocomplete="current-password" required>
        </label>
        @error('email')
            <p class="rounded-md bg-danger/10 px-3 py-2 text-sm font-medium text-danger">{{ $message }}</p>
        @enderror
        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input class="size-4" type="checkbox" name="remember" value="1" checked>
            Keep me signed in on this device
        </label>
        <button class="btn btn-primary w-full" type="submit">Sign in</button>
    </form>
@endsection

@section('after')
    @if ($canRegister)
        <p class="mt-4 text-center text-sm text-muted">No account yet? <a class="font-semibold text-orc-700 underline-offset-2 hover:underline" href="{{ route('register') }}">Create one</a></p>
    @endif
@endsection
