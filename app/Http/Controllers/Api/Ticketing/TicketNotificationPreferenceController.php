<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketNotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $pref = TicketNotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'database_enabled' => true,
                'mail_enabled' => true,
                'muted_events' => [],
            ]
        );

        return response()->json($pref);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'database_enabled' => 'sometimes|boolean',
            'mail_enabled' => 'sometimes|boolean',
            'muted_events' => 'nullable|array',
            'muted_events.*' => 'string|max:50',
        ]);

        $pref = TicketNotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'database_enabled' => $validated['database_enabled'] ?? true,
                'mail_enabled' => $validated['mail_enabled'] ?? true,
                'muted_events' => $validated['muted_events'] ?? [],
            ]
        );

        return response()->json($pref);
    }
}
