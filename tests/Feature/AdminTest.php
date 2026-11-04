<?php

namespace Tests\Feature;

use App\Models\Camera;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_summary_and_user_directory_require_admin_staff_access(): void
    {
        $customer = $this->createUser('customer');
        $staff = $this->createUser('staff');
        $this->createCamera();
        Rental::create([
            'user_id' => $customer->id,
            'camera_id' => Camera::firstOrFail()->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            'total' => 50,
        ]);
        Maintenance::create([
            'camera_id' => Camera::firstOrFail()->id,
            'issue' => 'Sensor clean',
            'scheduled_date' => now()->addDay()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->getJson('/api/staff/admin/overview')->assertUnauthorized();

        $customerToken = $customer->createToken('test')->plainTextToken;
        $this->withToken($customerToken)
            ->getJson('/api/staff/admin/users')
            ->assertForbidden();

        Auth::forgetGuards();
        $staffToken = $staff->createToken('test')->plainTextToken;
        $this->withToken($staffToken)
            ->getJson('/api/staff/admin/overview')
            ->assertOk()
            ->assertJsonPath('summary.customers', 1)
            ->assertJsonPath('summary.staff', 1)
            ->assertJsonPath('summary.cameras', 1)
            ->assertJsonPath('summary.pending_rentals', 1)
            ->assertJsonPath('summary.open_maintenance', 1);

        Auth::forgetGuards();
        $this->withToken($staffToken)
            ->getJson('/api/staff/admin/users?search=customer')
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.role', 'customer');
    }

    public function test_admin_can_change_user_account_type_and_revoke_old_tokens(): void
    {
        $staff = $this->createUser('staff');
        $customer = $this->createUser('customer');
        $staffToken = $staff->createToken('admin-test')->plainTextToken;
        $customer->createToken('old-user-session');

        $this->withToken($staffToken)
            ->patchJson("/api/staff/admin/users/{$customer->id}/role", ['role' => 'staff'])
            ->assertOk()
            ->assertJsonPath('user.role', 'staff');

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'role' => 'staff']);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $customer->id,
            'name' => 'old-user-session',
        ]);
    }

    public function test_admin_cannot_change_own_role_or_demote_the_final_admin(): void
    {
        $staff = $this->createUser('staff');
        $otherStaff = $this->createUser('staff');
        $token = $staff->createToken('admin-test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/staff/admin/users/{$staff->id}/role", ['role' => 'customer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        Auth::forgetGuards();
        $this->withToken($token)
            ->patchJson("/api/staff/admin/users/{$otherStaff->id}/role", ['role' => 'customer'])
            ->assertOk();

        Auth::forgetGuards();
        $this->withToken($token)
            ->patchJson("/api/staff/admin/users/{$otherStaff->id}/role", ['role' => 'staff'])
            ->assertOk();
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => 'camera-rental-123',
            'role' => $role,
        ]);
    }

    private function createCamera(): Camera
    {
        return Camera::create([
            'brand' => 'Canon',
            'model' => 'EOS 80D',
            'category' => 'DSLR',
            'daily_rate' => 25,
            'availability' => 'available',
            'description' => 'A DSLR test camera.',
            'specs' => ['24 MP'],
        ]);
    }
}
