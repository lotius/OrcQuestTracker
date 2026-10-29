@extends('layouts.auth')

@section('title', 'Forgot password · OrcQuest Tracker')
@section('heading', 'Forgot your password?')

@section('content')
    @if (session('status'))
        <p class="mb-4 rounded-md bg-sage-soft px-3 py-2 text-sm text-orc" role="status">{{ session('status') }}</p>
    @endif
    <p class="mb-4 text-sm text-ink-soft">Enter the email you signed up with and we'll send you a link to choose a new password.</p>
    <form class="grid gap-4" method="post" action="{{ route('password.email') }}">
        @csrf
        <label>
            <span class="field-label">Email</span>
            <input class="control" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
        </label>
        <button class="btn btn-primary w-full" type="submit">Email me a reset link</button>
    </form>
@endsection

@section('after')
    <p class="mt-4 text-center text-sm text-muted"><a class="font-semibold text-orc-700 underline-offset-2 hover:underline" href="{{ route('login') }}">Back to sign in</a></p>
@endsection
