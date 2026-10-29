@extends('layouts.auth')

@section('title', 'Choose a new password · OrcQuest Tracker')
@section('heading', 'Choose a new password')

@section('content')
    <form class="grid gap-4" method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>
            <span class="field-label">Email</span>
            <input class="control" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" required>
            @error('email') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="field-label">New password</span>
            <input class="control" type="password" name="password" minlength="8" autocomplete="new-password" required autofocus>
            @error('password') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="field-label">Confirm new password</span>
            <input class="control" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
        </label>
        <button class="btn btn-primary w-full" type="submit">Save new password</button>
    </form>
@endsection
