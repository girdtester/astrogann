<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->signIn($user)->get('/profile')->assertOk();
    }

    public function test_name_can_be_updated_but_email_cannot(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->signIn($user)
            ->patch('/profile', ['name' => 'New Name', 'email' => 'hack@example.com', 'role' => 'superadmin'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('keep@example.com', $user->email);
        $this->assertTrue($user->isTrader());
    }

    public function test_users_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create();

        $this->signIn($user)->delete('/profile', ['password' => 'password'])->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
