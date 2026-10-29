<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Campaigns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public static function registrationOpen(): bool
    {
        return config('tracker.registration') || User::query()->doesntExist();
    }

    public function showLogin(): View|RedirectResponse
    {
        return User::query()->doesntExist()
            ? redirect()->route('register')
            : view('auth.login', ['canRegister' => self::registrationOpen()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages(['email' => 'That email and password do not match an account.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function showRegister(): View
    {
        abort_unless(self::registrationOpen(), 404);

        return view('auth.register', ['firstAccount' => User::query()->doesntExist()]);
    }

    public function register(Request $request, Campaigns $campaigns): RedirectResponse
    {
        abort_unless(self::registrationOpen(), 404);

        if (! $this->looksHuman($request)) {
            throw ValidationException::withMessages(['form' => 'That sign-up looked automated. Wait a moment, then try again.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $firstAccount = User::query()->doesntExist();
        $user = User::create($data);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $imported = $firstAccount ? $this->importLegacySave($user, $campaigns) : 0;

        return redirect()->route('home')->with('status', $imported
            ? "Welcome! Your existing tracker data was imported ({$imported} ".Str::plural('campaign', $imported).').'
            : 'Welcome to the warband!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Bots fill in every field (including the hidden one) and submit instantly; people don't. */
    private function looksHuman(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return false;
        }

        try {
            $shownAt = (int) decrypt((string) $request->input('form_token'));
        } catch (DecryptException) {
            return false;
        }

        $elapsed = now()->timestamp - $shownAt;

        return $elapsed >= config('tracker.signup_min_seconds') && $elapsed <= 86400;
    }

    /** The first account inherits the campaigns saved by the pre-login version of the tracker. */
    private function importLegacySave(User $user, Campaigns $campaigns): int
    {
        if (! Storage::exists(Campaigns::LEGACY_FILE)) {
            return 0;
        }

        try {
            $imported = $campaigns->import($user, json_decode(Storage::get(Campaigns::LEGACY_FILE), true) ?: []);
            Storage::move(Campaigns::LEGACY_FILE, Campaigns::LEGACY_FILE.'.imported-'.now()->format('Ymd-His'));

            return count($imported);
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }
}
