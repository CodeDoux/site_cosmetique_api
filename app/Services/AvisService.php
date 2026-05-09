<?php

namespace App\Services;

use App\Models\Avis;
use App\Models\User;

class AvisService
{
    // ─── Avis approuvés d'un produit (public) ────────────────────────────────

    public function parProduit(int $produitId)
    {
        return Avis::where('produit_id', $produitId)
            ->where('statut', 'APPROUVEE')
            ->with('client:id,nomComplet,image')
            ->latest('dateAvis')
            ->get();
    }

    // ─── Tous les avis en attente (admin) ─────────────────────────────────────

    public function enAttente()
    {
        return Avis::where('statut', 'EN_ATTENTE')
            ->with(['client', 'produit'])
            ->latest()
            ->get();
    }

    // ─── Laisser un avis ──────────────────────────────────────────────────────

    public function store(array $data, User $client): Avis
    {
        // Vérifier que le client a bien acheté et reçu le produit
        $aAchete = $client->commandes()
            ->whereHas('lignesCommande', fn($q) => $q->where('produit_id', $data['produit_id']))
            ->where('statut', 'LIVREE')
            ->exists();

        if (!$aAchete) {
            throw new \Exception('Vous devez avoir acheté et reçu ce produit pour laisser un avis.');
        }

        // Vérifier qu'il n'a pas déjà laissé un avis pour ce produit
        $dejaLaisseAvis = Avis::where('client_id', $client->id)
            ->where('produit_id', $data['produit_id'])
            ->exists();

        if ($dejaLaisseAvis) {
            throw new \Exception('Vous avez déjà laissé un avis pour ce produit.');
        }

        return Avis::create(array_merge($data, ['client_id' => $client->id]));
    }

    // ─── Modérer un avis (admin) ──────────────────────────────────────────────

    public function moderer(Avis $avis, string $statut): Avis
    {
        $avis->update([
            'statut'     => $statut,
            'estVerifie' => true,
        ]);

        return $avis->fresh();
    }

    // ─── Supprimer un avis (admin) ────────────────────────────────────────────

    public function destroy(Avis $avis): void
    {
        $avis->delete();
    }
}
