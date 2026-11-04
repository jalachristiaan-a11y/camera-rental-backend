<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_use_the_issued_token(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Riley',
            'last_name' => 'Morgan',
            'email' => 'RILEY@example.com',
            'password' => 'camera-rental-123',
            'password_confirmation' => 'camera-rental-123',
            'account_type' => 'filmmaker',
            'terms_accepted' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Riley Morgan')
            ->assertJsonPath('user.email', 'riley@example.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'account_type']]);

        $this->assertDatabaseHas('users', [
            'email' => 'riley@example.com',
            'role' => 'customer',
            'account_type' => 'filmmaker',
        ]);
        $this->assertTrue(Hash::check('camera-rental-123', User::first()->password));

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'riley@example.com');
    }

    public function test_customer_can_sign_in_and_sign_out(): void
    {
        $user = User::create([
            'name' => 'Casey Customer',
            'email' => 'casey@example.com',
            'password' => 'camera-rental-123',
            'role' => 'customer',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'camera-rental-123',
            'role' => 'customer',
        ])->assertOk()->assertJsonPath('user.role', 'customer');

        $token = $response->json('token');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_staff_user_can_sign_in_with_the_staff_role(): void
    {
        $staff = User::create([
            'name' => 'Morgan Staff',
            'email' => 'morgan@example.com',
            'password' => 'camera-rental-123',
            'role' => 'staff',
        ]);

        $this->postJson('/api/login', [
            'email' => $staff->email,
            'password' => 'camera-rental-123',
            'role' => 'staff',
        ])->assertOk()->assertJsonPath('user.role', 'staff');
    }

    public function test_customer_registration_cannot_grant_staff_access(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Taylor',
            'last_name' => 'Staff',
            'email' => 'taylor@example.com',
            'password' => 'camera-rental-123',
            'password_confirmation' => 'camera-rental-123',
            'account_type' => 'production_studio',
            'terms_accepted' => true,
            'role' => 'staff',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'customer');
    }

    public function test_sign_in_rejects_wrong_password_and_account_type(): void
    {
        User::create([
            'name' => 'Sam Customer',
            'email' => 'sam@example.com',
            'password' => 'camera-rental-123',
            'role' => 'customer',
        ]);

        $this->postJson('/api/login', [
            'email' => 'sam@example.com',
            'password' => 'wrong-password',
            'role' => 'customer',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/login', [
            'email' => 'sam@example.com',
            'password' => 'camera-rental-123',
            'role' => 'staff',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
