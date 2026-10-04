<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Kullanıcının tüm bildirimlerini getirir.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->paginate(
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Kullanıcının sadece okunmamış bildirimlerini getirir.
     */
    public function unread(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->unread(
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Okunmamış bildirim sayısını getirir.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'count' => $this->notificationService->unreadCount(
                    $request->user()
                ),
            ],
        ]);
    }

    /**
     * Tek bir bildirimi okundu olarak işaretler.
     */
    public function markAsRead(
        Request $request,
        string $notification
    ): JsonResponse {
        $updatedNotification = $this->notificationService->markAsRead(
            $request->user(),
            $notification
        );

        return response()->json([
            'success' => true,
            'message' => 'Bildirim okundu olarak işaretlendi.',
            'data' => $updatedNotification,
        ]);
    }

    /**
     * Kullanıcının tüm bildirimlerini okundu yapar.
     */
    public function markAllAsRead(
        Request $request
    ): JsonResponse {
        $updatedCount = $this->notificationService->markAllAsRead(
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Tüm bildirimler okundu olarak işaretlendi.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ]);
    }

    /**
     * Tek bir bildirimi siler.
     */
    public function destroy(
        Request $request,
        string $notification
    ): JsonResponse {
        $this->notificationService->delete(
            $request->user(),
            $notification
        );

        return response()->json([
            'success' => true,
            'message' => 'Bildirim silindi.',
        ]);
    }
}