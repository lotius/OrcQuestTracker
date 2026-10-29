<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_asks_for_the_first_account(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertRedirect('/register');
        $this->get('/register')->assertOk()->assertSee('Claim this tracker')->assertSee('Not a Monolith product');
    }

    public function test_guests_see_the_sign_in_page_once_an_account_exists(): void
    {
        User::factory()->create();

        $this->get('/login')->assertOk()->assertSee('Unofficial fan-made tool');
    }
}
