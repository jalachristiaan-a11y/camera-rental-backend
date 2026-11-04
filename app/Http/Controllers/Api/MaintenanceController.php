<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $records = Maintenance::with('camera')
            ->latest('scheduled_date')
            ->get()
            ->map(fn (Maintenance $record) => $this->maintenancePayload($record));

        return response()->json(['maintenances' => $records]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'camera_id' => ['required', 'integer', 'exists:cameras,id'],
            'issue' => ['required', 'string', 'max:255'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'assigned_to' => ['nullable', 'string', 'max:120'],
        ]);

        $record = Maintenance::create([
            ...$validated,
            'status' => 'scheduled',
        ])->load('camera');

        return response()->json(['maintenance' => $this->maintenancePayload($record)], 201);
    }

    public function update(Request $request, Maintenance $maintenance): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'in-progress', 'completed'])],
        ]);

        $maintenance->update($validated);

        return response()->json([
            'maintenance' => $this->maintenancePayload($maintenance->load('camera')),
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->role === 'staff', 403, 'Staff access is required.');
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenancePayload(Maintenance $record): array
    {
        return [
            'id' => $record->id,
            'camera_id' => $record->camera_id,
            'camera_name' => $record->camera->brand.' '.$record->camera->model,
            'issue' => $record->issue,
            'scheduled_date' => $record->scheduled_date->format('Y-m-d'),
            'status' => $record->status,
            'assigned_to' => $record->assigned_to,
        ];
    }
}
