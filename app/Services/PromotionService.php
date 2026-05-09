<?php

namespace App\Services;

use App\Models\Promotion;

class PromotionService
{
    // ─── Lister toutes les promotions (admin) ─────────────────────────────────

    public function lister()
    {
        return Promotion::with('produit')->latest()->paginate(15);
    }

    // ─── Créer une promotion ──────────────────────────────────────────────────

    public function store(array $data): Promotion
    {
        return Promotion::create($data);
    }

    // ─── Modifier une promotion ───────────────────────────────────────────────

    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->update($data);
        return $promotion->fresh('produit');
    }

    // ─── Supprimer une promotion ──────────────────────────────────────────────

    public function destroy(Promotion $promotion): void
    {
        $promotion->delete();
    }

    /**
     * Valider un code promo avant de passer commande
     * Retourne la réduction calculée si valide
     */
    public function validerCode(string $code, float $montant): array
    {
        $promo = Promotion::where('code', $code)->first();

        if (!$promo) {
            return ['valide' => false, 'message' => 'Code promo invalide.'];
        }

        if (!$promo->estValide()) {
            return ['valide' => false, 'message' => 'Ce code promo est expiré ou inactif.'];
        }

        if ($montant < $promo->montantMinCommande) {
            return [
                'valide'  => false,
                'message' => "Montant minimum requis : {$promo->montantMinCommande} FCFA.",
            ];
        }

        $reduction = $promo->calculerReduction($montant);

        return [
            'valide'      => true,
            'reduction'   => $reduction,
            'montantFinal'=> $montant - $reduction,
            'promotion'   => $promo,
            'message'     => "Code appliqué ! Vous économisez {$reduction} FCFA.",
        ];
    }
}
