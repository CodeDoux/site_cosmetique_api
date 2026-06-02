<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    // ─── Récupérer l'ID de l'admin ────────────────────────────────────────────
    private function getAdminId(): ?int
    {
        return User::where('role', 'ADMIN')->value('id');
    }

    // ─── Envoyer une notification à l'admin ──────────────────────────────────
    public function envoyer(string $titre, string $message, string $type): ?Notification
    {
        $adminId = $this->getAdminId();
        if (!$adminId) return null;

        return Notification::create([
            'destinataire_id' => $adminId,
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

    // ─── Marquer toutes les notifications comme lues ─────────────────────────
    public function marquerToutCommeLu(): void
    {
        $adminId = $this->getAdminId();
        if (!$adminId) return;

        Notification::where('destinataire_id', $adminId)
            ->where('estLu', false)
            ->update(['estLu' => true]);
    }
}