<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'account_type' => ['required', Rule::in(['filmmaker', 'production_studio'])],
            'terms_accepted' => ['accepted'],
        ]);

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'customer',
            'account_type' => $validated['account_type'],
        ]);

        return response()->json([
            'token' => $user->createToken('cinelens-frontend')->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'role' => ['required', Rule::in(['customer', 'staff'])],
        ]);

        $throttleKey = hash('sha256', $validated['email'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts('login:'.$throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => ['Too many sign-in attempts. Please try again in one minute.'],
            ])->status(429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (
            ! $user ||
            ! Hash::check($validated['password'], $user->password) ||
            $user->role !== $validated['role']
        ) {
            RateLimiter::hit('login:'.$throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['The email, password, or account type is incorrect.'],
            ]);
        }

        RateLimiter::clear('login:'.$throttleKey);

        return response()->json([
            'token' => $user->createToken('cinelens-frontend')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Signed out successfully.']);
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, account_type: ?string}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'account_type' => $user->account_type,
        ];
    }
}
