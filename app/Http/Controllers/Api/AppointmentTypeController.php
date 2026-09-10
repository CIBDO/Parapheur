<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AppointmentType::query()->orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:appointment_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'blocks_calendar' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $type = AppointmentType::query()->create($data);

        return response()->json($type, 201);
    }

    public function update(Request $request, AppointmentType $appointmentType): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:appointment_types,code,'.$appointmentType->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'blocks_calendar' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $appointmentType->update($data);

        return response()->json($appointmentType);
    }

    public function destroy(AppointmentType $appointmentType): JsonResponse
    {
        $appointmentType->delete();

        return response()->json(['message' => 'Type supprimé.']);
    }
}
