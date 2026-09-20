<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\NotificationPresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($notification) {
                $data = is_array($notification->data) ? $notification->data : [];
                $isSeen = $notification->read_at !== null;

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? 'Notification',
                    'subtitle' => $data['message'] ?? '',
                    'time' => optional($notification->created_at)->diffForHumans(),
                    'isSeen' => $isSeen,
                    'url' => $data['url'] ?? null,
                    'icon' => NotificationPresentation::icon($data),
                    'color' => NotificationPresentation::color($data, $isSeen),
                    'domain' => NotificationPresentation::domainLabel($data),
                    'event' => $data['event'] ?? null,
                    'created_at' => $notification->created_at,
                ];
            });

        return response()->json([
            'data' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['string'],
        ]);

        $query = $request->user()->unreadNotifications();

        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marquées comme lues']);
    }

    public function markUnread(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        $request->user()
            ->notifications()
            ->whereIn('id', $data['ids'])
            ->update(['read_at' => null]);

        return response()->json(['message' => 'Notifications marquées comme non lues']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return response()->json(['message' => 'Notification supprimée']);
    }
}
