@extends('layouts.auth')

@section('title', 'Create account · OrcQuest Tracker')
@section('heading', $firstAccount ? 'Claim this tracker' : 'Create an account')

@section('content')
    @if ($firstAccount)
        <p class="mb-4 rounded-md bg-sage-soft px-3 py-2 text-sm text-orc">This is the first account on this tracker. Any campaigns saved before accounts existed will be moved into it.</p>
    @endif
    <form class="grid gap-4" method="post" action="{{ route('register') }}">
        @csrf
        {{-- Bot traps: a field people never see, and the time the form was shown. --}}
        <div class="absolute -left-[10000px] top-auto size-px overflow-hidden" aria-hidden="true">
            <label>Leave this field empty <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
        </div>
        <input type="hidden" name="form_token" value="{{ encrypt(now()->timestamp) }}">
        @error('form') <p class="rounded-md bg-danger/10 px-3 py-2 text-sm font-medium text-danger">{{ $message }}</p> @enderror
        <label>
            <span class="field-label">Name</span>
            <input class="control" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
            @error('name') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="field-label">Email</span>
            <input class="control" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
            @error('email') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="field-label">Password</span>
            <input class="control" type="password" name="password" autocomplete="new-password" minlength="8" required>
            @error('password') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="field-label">Confirm password</span>
            <input class="control" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
        </label>
        <button class="btn btn-primary w-full" type="submit">Create account</button>
    </form>
@endsection

@section('after')
    @unless ($firstAccount)
        <p class="mt-4 text-center text-sm text-muted">Already have an account? <a class="font-semibold text-orc-700 underline-offset-2 hover:underline" href="{{ route('login') }}">Sign in</a></p>
    @endunless
@endsection
