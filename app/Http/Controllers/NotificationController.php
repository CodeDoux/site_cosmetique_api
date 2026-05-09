<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifService) {}

    // ─── GET /api/notifications ───────────────────────────────────────────────
    // Notifications du client connecté
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('destinataire_id', $request->user()->id)
            ->latest('dateEnvoi')
            ->paginate(20);

        return response()->json($notifications);
    }

    // ─── PATCH /api/notifications/{notification}/lire ────────────────────────
    public function marquerLu(Request $request, Notification $notification): JsonResponse
    {
        // Vérifier que la notification appartient à l'utilisateur connecté
        abort_if($notification->destinataire_id !== $request->user()->id, 403);

        $this->notifService->marquerCommeLu($notification);

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    // ─── PATCH /api/notifications/lire-tout ──────────────────────────────────
    public function marquerToutLu(Request $request): JsonResponse
    {
        $this->notifService->marquerToutCommeLu($request->user()->id);

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
    }
}
