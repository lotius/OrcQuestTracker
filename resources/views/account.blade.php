@extends('layouts.app')

@section('title', 'Account · OrcQuest Tracker')

@section('content')
    <h1 class="page-title mb-4">Account</h1>

    <div class="grid max-w-3xl gap-4 md:grid-cols-2">
        <form class="panel grid content-start gap-3 p-4" method="post" action="{{ route('account.update') }}">
            @csrf
            @method('put')
            <h2 class="section-title">Profile</h2>
            <label>
                <span class="field-label">Name</span>
                <input class="control" name="name" value="{{ old('name', $user->name) }}" maxlength="80" required autocomplete="name">
                @error('name') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="field-label">Email</span>
                <input class="control" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                @error('email') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
            </label>
            <button class="btn btn-primary" type="submit">Save profile</button>
        </form>

        <form class="panel grid content-start gap-3 p-4" method="post" action="{{ route('account.password') }}">
            @csrf
            @method('put')
            <h2 class="section-title">Change password</h2>
            <label>
                <span class="field-label">Current password</span>
                <input class="control" type="password" name="current_password" required autocomplete="current-password">
                @error('current_password', 'password') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="field-label">New password</span>
                <input class="control" type="password" name="password" minlength="8" required autocomplete="new-password">
                @error('password', 'password') <span class="mt-1 block text-sm text-danger">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="field-label">Confirm new password</span>
                <input class="control" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
            </label>
            <button class="btn btn-primary" type="submit">Change password</button>
        </form>
    </div>
@endsection
