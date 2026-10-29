<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

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

        $this->post('/register', [
            'name' => 'Ribby', 'email' => 'ribby@example.com', 'password' => 'secret-orc', 'password_confirmation' => 'secret-orc',
        ])->assertRedirect('/');

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
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-orc', 'password_confirmation' => 'secret-orc'])->assertNotFound();
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
}
