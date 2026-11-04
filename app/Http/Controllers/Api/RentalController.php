<?php

namespace App\Http\Controllers\Api;

use App\Models\Camera;
use App\Models\Rental;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'customer', 403, 'Customer access is required.');

        $rentals = Rental::with('camera')
            ->where('user_id', $request->user()->id)
            ->latest('start_date')
            ->get()
            ->map(fn (Rental $rental) => $this->rentalPayload($rental));

        return response()->json(['rentals' => $rentals]);
    }

    public function staffIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'staff', 403, 'Staff access is required.');

        $rentals = Rental::with(['camera', 'user'])
            ->latest('start_date')
            ->get()
            ->map(fn (Rental $rental) => [
                ...$this->rentalPayload($rental),
                'customer_name' => $rental->user->name,
                'customer_email' => $rental->user->email,
            ]);

        return response()->json(['rentals' => $rentals]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'customer', 403, 'Customer access is required.');

        $validated = $request->validate([
            'camera_id' => ['required', 'integer', 'exists:cameras,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $rental = DB::transaction(function () use ($request, $validated) {
            $camera = Camera::query()->lockForUpdate()->findOrFail($validated['camera_id']);

            if ($camera->availability !== 'available') {
                throw ValidationException::withMessages([
                    'camera_id' => ['This camera is not currently available.'],
                ]);
            }

            $hasOverlappingRental = Rental::query()
                ->where('camera_id', $camera->id)
                ->whereIn('status', ['pending', 'confirmed', 'active'])
                ->whereDate('start_date', '<=', $validated['end_date'])
                ->whereDate('end_date', '>=', $validated['start_date'])
                ->exists();

            $hasOverlappingMaintenance = $camera->maintenances()
                ->where('status', '!=', 'completed')
                ->whereBetween('scheduled_date', [$validated['start_date'], $validated['end_date']])
                ->exists();

            if ($hasOverlappingRental || $hasOverlappingMaintenance) {
                throw ValidationException::withMessages([
                    'start_date' => ['This camera is already booked or scheduled for maintenance during those dates.'],
                ]);
            }

            $days = Carbon::parse($validated['start_date'])
                ->diffInDays(Carbon::parse($validated['end_date'])) + 1;

            return Rental::create([
                'user_id' => $request->user()->id,
                'camera_id' => $camera->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'pending',
                'total' => $camera->daily_rate * $days,
            ])->load('camera');
        });

        return response()->json(['rental' => $this->rentalPayload($rental)], 201);
    }

    public function cancel(Request $request, Rental $rental): JsonResponse
    {
        abort_unless($request->user()->role === 'customer', 403, 'Customer access is required.');
        abort_unless($rental->user_id === $request->user()->id, 404);
        abort_unless(in_array($rental->status, ['pending', 'confirmed'], true), 409, 'This rental can no longer be cancelled.');

        $rental->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Rental cancelled successfully.']);
    }

    public function staffUpdate(Request $request, Rental $rental): JsonResponse
    {
        abort_unless($request->user()->role === 'staff', 403, 'Staff access is required.');

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,active,completed,cancelled'],
        ]);

        $allowedTransitions = match ($rental->status) {
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['active', 'cancelled'],
            'active' => ['completed'],
            default => [],
        };

        if (! in_array($validated['status'], $allowedTransitions, true)) {
            throw ValidationException::withMessages([
                'status' => ['This rental cannot be moved to that status.'],
            ]);
        }

        $rental->update(['status' => $validated['status']]);

        return response()->json([
            'rental' => [
                ...$this->rentalPayload($rental->load(['camera', 'user'])),
                'customer_name' => $rental->user->name,
                'customer_email' => $rental->user->email,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rentalPayload(Rental $rental): array
    {
        return [
            'id' => $rental->id,
            'camera_id' => $rental->camera_id,
            'camera_name' => $rental->camera->brand.' '.$rental->camera->model,
            'start_date' => $rental->start_date->format('Y-m-d'),
            'end_date' => $rental->end_date->format('Y-m-d'),
            'status' => $rental->status,
            'total' => (float) $rental->total,
        ];
    }
}
