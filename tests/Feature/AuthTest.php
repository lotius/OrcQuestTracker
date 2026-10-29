<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** A sign-up as a person submits it: hidden trap field empty, form shown a few seconds ago. */
    private function signup(array $fields = []): array
    {
        return $fields + [
            'name' => 'Ribby', 'email' => 'ribby@example.com', 'password' => 'secret-orc', 'password_confirmation' => 'secret-orc',
            'website' => '', 'form_token' => encrypt(now()->subSeconds(5)->timestamp),
        ];
    }

    public function test_first_account_inherits_the_pre_login_save_file(): void
    {
        Storage::fake();
        Storage::put('orcquest-tracker-state.json', json_encode([
            'active_campaign_id' => 'default-campaign',
            'campaigns' => [
                'default-campaign' => [
                    'name' => 'Primary Campaign',
                    'state' => ['characters' => ['1' => ['hero_id' => 'abgral', 'player' => 'Ribby']]],
                    'history' => [['id' => '01J000000000000000000000AA', 'at' => '2026-10-29T17:00:00+00:00', 'type' => 'quest', 'text' => 'Completed something']],
                ],
                'other' => ['name' => 'Side Quest', 'state' => ['characters' => []]],
            ],
        ]));

        $this->post('/register', $this->signup())->assertRedirect('/');

        $user = User::firstWhere('email', 'ribby@example.com');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Primary Campaign', 'Side Quest'], $user->campaigns()->orderBy('id')->pluck('name')->all());

        $primary = $user->campaigns()->firstWhere('name', 'Primary Campaign');
        $this->assertSame('abgral', $primary->state['characters']['1']['hero_id']);
        $this->assertSame(2, $primary->events()->count());
        Storage::assertMissing('orcquest-tracker-state.json');
    }

    public function test_registration_can_be_closed_after_the_first_account(): void
    {
        config(['tracker.registration' => false]);
        User::factory()->create();

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->signup(['email' => 'x@example.com']))->assertNotFound();
    }

    public function test_sign_up_bots_are_turned_away(): void
    {
        $this->post('/register', $this->signup(['website' => 'http://spam.example']))->assertSessionHasErrors('form');
        $this->post('/register', $this->signup(['form_token' => encrypt(now()->timestamp)]))->assertSessionHasErrors('form');
        $this->post('/register', $this->signup(['form_token' => 'forged']))->assertSessionHasErrors('form');
        $this->post('/register', array_diff_key($this->signup(), ['form_token' => true]))->assertSessionHasErrors('form');

        $this->assertDatabaseCount('users', 0);

        $this->post('/register', $this->signup())->assertRedirect('/');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_sign_in_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'orc@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => 'orc@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'orc@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sign_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'orc@example.com']);

        $this->post('/login', ['email' => 'orc@example.com', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_forgotten_password_can_be_reset_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'orc@example.com']);

        $this->get('/login')->assertSee('Forgot password?');
        $this->post('/forgot-password', ['email' => 'orc@example.com'])->assertSessionHas('status');
        // Unknown emails get the same reply, so the form can't reveal who has an account.
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=orc@example.com")->assertOk()->assertSee('Choose a new password');
        $this->post('/reset-password', ['token' => 'wrong', 'email' => 'orc@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => 'orc@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertRedirect('/login');

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
        $this->post('/login', ['email' => 'orc@example.com', 'password' => 'brand-new-pass'])->assertRedirect('/');
    }
}
