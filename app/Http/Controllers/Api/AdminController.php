<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        return response()->json([
            'summary' => [
                'customers' => User::where('role', 'customer')->count(),
                'staff' => User::where('role', 'staff')->count(),
                'cameras' => Camera::count(),
                'open_rentals' => Rental::whereIn('status', ['pending', 'confirmed', 'active'])->count(),
                'pending_rentals' => Rental::where('status', 'pending')->count(),
                'open_maintenance' => Maintenance::where('status', '!=', 'completed')->count(),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'account_type', 'created_at'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['users' => $users]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['customer', 'staff'])],
        ]);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'role' => ['You cannot change your own account role.'],
            ]);
        }

        DB::transaction(function () use ($user, $validated) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if (
                $lockedUser->role === 'staff' &&
                $validated['role'] === 'customer' &&
                User::where('role', 'staff')->lockForUpdate()->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'role' => ['The system must keep at least one admin/staff account.'],
                ]);
            }

            if ($lockedUser->role !== $validated['role']) {
                $lockedUser->update(['role' => $validated['role']]);
                $lockedUser->tokens()->delete();
            }
        });

        return response()->json([
            'user' => $user->fresh()->only([
                'id',
                'name',
                'email',
                'role',
                'account_type',
                'created_at',
            ]),
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->role === 'staff', 403, 'Admin/staff access is required.');
    }
}
