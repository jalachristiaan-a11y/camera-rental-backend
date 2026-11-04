<?php

namespace App\Http\Controllers\Api;

use App\Models\Camera;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CameraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cameras = Camera::query()
            ->orderBy('category')
            ->orderBy('brand')
            ->orderBy('model')
            ->get()
            ->map(function (Camera $camera) {
                if ($camera->maintenances()
                    ->where('status', 'in-progress')
                    ->exists()) {
                    $camera->availability = 'maintenance';
                }

                return $camera;
            });

        return response()->json(['cameras' => $cameras]);
    }
}
