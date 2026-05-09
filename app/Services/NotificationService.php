<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    // ─── Envoyer une notification ─────────────────────────────────────────────

    public function envoyer(int $destinataireId, string $titre, string $message, string $type): Notification
    {
        return Notification::create([
            'destinataire_id' => $destinataireId,
            'titre'           => $titre,
            'message'         => $message,
            'type'            => $type,
            'estLu'           => false,
        ]);
    }

    // ─── Marquer une notification comme lue ──────────────────────────────────

    public function marquerCommeLu(Notification $notification): void
    {
        $notification->update(['estLu' => true]);
    }

    // ─── Marquer toutes les notifications comme lues ──────────────────────────

    public function marquerToutCommeLu(int $userId): void
    {
        Notification::where('destinataire_id', $userId)
            ->where('estLu', false)
            ->update(['estLu' => true]);
    }
}
