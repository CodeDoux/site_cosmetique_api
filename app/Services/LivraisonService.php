<?php

namespace App\Services;

use App\Models\Livraison;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LivraisonService
{
    public function __construct(private NotificationService $notifService) {}

    // ─── Toutes les livraisons (admin) ────────────────────────────────────────

    public function listerTout(array $filtres = [])
    {
        return Livraison::with(['commande.client', 'adresse', 'livreur'])
            ->when(
                isset($filtres['statut']),
                fn($q) => $q->where('statutLivraison', $filtres['statut'])
            )
            ->when(
                isset($filtres['livreur_id']),
                fn($q) => $q->where('livreur_id', $filtres['livreur_id'])
            )
            ->latest()
            ->paginate(15);
    }

    // ─── Livraisons du livreur connecté ───────────────────────────────────────

    public function mesLivraisons(int $livreurId, array $filtres = [])
    {
        return Livraison::where('livreur_id', $livreurId)
            ->with(['commande.client', 'commande.lignesCommande.produit', 'adresse'])
            ->when(
                isset($filtres['statut']),
                fn($q) => $q->where('statutLivraison', $filtres['statut'])
            )
            ->latest()
            ->paginate(10);
    }

    // ─── Détail d'une livraison ────────────────────────────────────────────────

    public function show(Livraison $livraison): Livraison
    {
        return $livraison->load([
            'commande.client',
            'commande.lignesCommande.produit.imagePrimaire',
            'adresse',
            'livreur',
        ]);
    }

    // ─── Admin : assigner un livreur à une livraison ──────────────────────────

    public function assigner(Livraison $livraison, int $livreurId): Livraison
    {
        $livreur = User::where('id', $livreurId)
            ->where('role', 'LIVREUR')
            ->firstOrFail();

        $livraison->update(['livreur_id' => $livreurId]);

        // Notifier le livreur
        $this->notifService->envoyer(
            $livreurId,
            'Nouvelle livraison assignée',
            "La livraison {$livraison->reference} vous a été assignée.",
            'LIVRAISON'
        );

        return $livraison->fresh(['commande.client', 'adresse', 'livreur']);
    }

    // ─── Livreur : prendre en charge une livraison ────────────────────────────
    // (Livraison sans livreur assigné → le livreur la prend lui-même)

    public function prendreEnCharge(Livraison $livraison, int $livreurId): Livraison
    {
        if ($livraison->livreur_id && $livraison->livreur_id !== $livreurId) {
            throw new \Exception('Cette livraison est déjà assignée à un autre livreur.');
        }

        if ($livraison->statutLivraison !== 'EN_COURS') {
            throw new \Exception('Cette livraison n\'est plus disponible.');
        }

        $livraison->update(['livreur_id' => $livreurId]);

        // Notifier le client
        $this->notifService->envoyer(
            $livraison->commande->client_id,
            'Livraison prise en charge',
            "Un livreur a pris en charge votre commande {$livraison->commande->reference}.",
            'LIVRAISON'
        );

        return $livraison->fresh(['commande.client', 'adresse']);
    }

    // ─── Livreur : marquer comme expédiée (en route) ─────────────────────────

    public function marquerExpediee(Livraison $livraison, int $livreurId, array $data = []): Livraison
    {
        $this->verifierAccesLivreur($livraison, $livreurId);

        $livraison->update([
            'dateExpedition' => $data['dateExpedition'] ?? now(),
        ]);

        // Mettre à jour la commande
        $livraison->commande->update(['statut' => 'EN_LIVRAISON']);

        // Notifier le client
        $this->notifService->envoyer(
            $livraison->commande->client_id,
            'Votre commande est en route ! 🚚',
            "Votre commande {$livraison->commande->reference} est en cours de livraison.",
            'LIVRAISON'
        );

        return $livraison->fresh();
    }

    // ─── Livreur : mettre à jour le statut (LIVREE / NON_LIVREE) ─────────────

    public function mettreAJourStatut(Livraison $livraison, int $livreurId, array $data): Livraison
    {
        $this->verifierAccesLivreur($livraison, $livreurId);

        return DB::transaction(function () use ($livraison, $data) {

            $livraison->update([
                'statutLivraison' => $data['statutLivraison'],
                'dateLivraison'   => $data['dateLivraison'] ?? now(),
            ]);

            if ($data['statutLivraison'] === 'LIVREE') {

                // Commande livrée
                $livraison->commande->update(['statut' => 'LIVREE']);

                // Notifier le client
                $this->notifService->envoyer(
                    $livraison->commande->client_id,
                    'Commande livrée ✓',
                    "Votre commande {$livraison->commande->reference} a bien été livrée. Merci !",
                    'LIVRAISON'
                );

            } else {
                // NON_LIVREE : remettre en attente pour replanifier
                $livraison->commande->update(['statut' => 'EN_PREPARATION']);

                // Notifier l'admin
                $admins = User::where('role', 'ADMIN')->get();
                foreach ($admins as $admin) {
                    $this->notifService->envoyer(
                        $admin->id,
                        'Échec de livraison',
                        "La livraison {$livraison->reference} n'a pas pu être effectuée. Raison : " . ($data['commentaire'] ?? 'Non précisée'),
                        'LIVRAISON'
                    );
                }
            }

            return $livraison->fresh(['commande', 'adresse']);
        });
    }

    // ─── Livreurs disponibles (admin) ─────────────────────────────────────────

    public function livreursDisponibles()
    {
        return User::where('role', 'LIVREUR')
            ->withCount([
                'livraisons as livraisons_en_cours' => fn($q) => $q->where('statutLivraison', 'EN_COURS'),
            ])
            ->get();
    }

    // ─── Vérifier que le livreur a accès à cette livraison ───────────────────

    private function verifierAccesLivreur(Livraison $livraison, int $livreurId): void
    {
        if ($livraison->livreur_id !== $livreurId) {
            throw new \Exception('Vous n\'êtes pas assigné à cette livraison.');
        }
    }
}
