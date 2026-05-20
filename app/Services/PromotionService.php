<?php

namespace App\Services;

use App\Models\Produit;
use App\Models\Promotion;
use App\Models\PromotionProduit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    // ─── Liste toutes les promotions ─────────────────────────────────────────

    public function index()
    {
        return Promotion::with(['produits'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    // ─── Créer une promotion ──────────────────────────────────────────────────

    public function store(array $data): Promotion
    {
        return DB::transaction(function () use ($data) {

            $promotion = Promotion::create([
                'nom'                => $data['nom'],
                'description'        => $data['description'] ?? null,
                'code'               => $data['code']               ?? null,
                'type'               => $data['type'],
                'valeur'             => $data['valeur'],
                'montantMinCommande' => $data['montantMinCommande'] ?? 0,
                'dateDebut'          => $data['dateDebut'],
                'dateFin'            => $data['dateFin']            ?? null,
                'estActif'           => $data['estActif']           ?? true,
            ]);

            // Associer les produits via la table pivot promotion_produits
            if (!empty($data['produit_ids']) && is_array($data['produit_ids'])) {
                foreach ($data['produit_ids'] as $produitId) {
                    PromotionProduit::create([
                        'promo_id'         => $promotion->id,
                        'produit_id'       => $produitId,
                        'montant_reduction' => null,
                    ]);
                }
            }

            return $promotion->load(['produits']);
        });
    }

    // ─── Voir une promotion ───────────────────────────────────────────────────

    public function show($id): Promotion
    {
        return Promotion::with(['produits'])->findOrFail($id);
    }

    // ─── Mettre à jour une promotion ──────────────────────────────────────────

    public function update(array $data, $id): Promotion
    {
        return DB::transaction(function () use ($data, $id) {

            $promotion = Promotion::findOrFail($id);

            $promotion->update([
                'nom'                => $data['nom']                ?? $promotion->nom,
                'description'        => $data['description']        ?? $promotion->description,
                'code'               => $data['code']               ?? $promotion->code,
                'type'               => $data['type']               ?? $promotion->type,
                'valeur'             => $data['valeur']             ?? $promotion->valeur,
                'montantMinCommande' => $data['montantMinCommande'] ?? $promotion->montantMinCommande,
                'dateDebut'          => $data['dateDebut']          ?? $promotion->dateDebut,
                'dateFin'            => $data['dateFin']            ?? $promotion->dateFin,
                'estActif'           => $data['estActif']           ?? $promotion->estActif,
            ]);

            // Sync des produits si produit_ids fourni
            if (array_key_exists('produit_ids', $data)) {
                // Supprimer les anciennes associations
                PromotionProduit::where('promo_id', $promotion->id)->delete();

                // Créer les nouvelles
                if (!empty($data['produit_ids'])) {
                    foreach ($data['produit_ids'] as $produitId) {
                        PromotionProduit::create([
                            'promo_id'         => $promotion->id,
                            'produit_id'       => $produitId,
                            'montant_reduction' => null,
                        ]);
                    }
                }
            }

            return $promotion->load(['produits']);
        });
    }

    // ─── Supprimer une promotion ──────────────────────────────────────────────

    public function destroy($id): bool
    {
        return DB::transaction(function () use ($id) {
            $promotion = Promotion::findOrFail($id);

            // Supprimer les associations pivot
            PromotionProduit::where('promo_id', $promotion->id)->delete();

            return $promotion->delete();
        });
    }

    // ─── Activer / Désactiver ─────────────────────────────────────────────────

    public function toggle($id): Promotion
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->update(['estActif' => !$promotion->estActif]);
        return $promotion->fresh();
    }

    // ─── Promotions actives ───────────────────────────────────────────────────

    public function getPromotionsActives()
    {
        $now = Carbon::now();

        return Promotion::with(['produits'])
            ->where('estActif', true)
            ->where('dateDebut', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('dateFin')->orWhere('dateFin', '>=', $now);
            })
            ->orderBy('valeur', 'desc')
            ->get();
    }

    // ─── Promotion active pour un produit ─────────────────────────────────────

    public function getPromotionActiveForProduit($produitId): ?Promotion
    {
        $now = Carbon::now();

        return Promotion::whereHas('produits', function ($q) use ($produitId) {
            $q->where('produits.id', $produitId);
        })
            ->where('estActif', true)
            ->where('dateDebut', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('dateFin')->orWhere('dateFin', '>=', $now);
            })
            ->orderBy('valeur', 'desc')
            ->first();
    }

    // ─── Calculer le prix avec promotion ──────────────────────────────────────

    public function calculerPrixAvecPromotion($produitId, $prixOriginal = null): array
    {
        $promotion = $this->getPromotionActiveForProduit($produitId);
        $produit   = Produit::findOrFail($produitId);
        $prix      = $prixOriginal ?? $produit->prix;

        if (!$promotion) {
            return [
                'prix_original'         => $prix,
                'prix_avec_promo'       => $prix,
                'reduction_pourcentage' => 0,
                'economie'              => 0,
                'promotion'             => null,
            ];
        }

        $prixAvecPromo = $promotion->type === 'POURCENTAGE'
            ? $prix * (1 - $promotion->valeur / 100)
            : max(0, $prix - $promotion->valeur);

        return [
            'prix_original'         => $prix,
            'prix_avec_promo'       => round($prixAvecPromo, 2),
            'reduction_pourcentage' => $promotion->type === 'POURCENTAGE'
                ? $promotion->valeur
                : round(($promotion->valeur / $prix) * 100, 1),
            'economie'              => round($prix - $prixAvecPromo, 2),
            'promotion'             => $promotion,
        ];
    }

    // ─── Vérifier si supprimable ──────────────────────────────────────────────

    public function peutEtreSupprimee($id): bool
    {
        // Vérifie qu'aucune commande en cours n'utilise cette promotion
        $count = DB::table('commandes')
            ->where('promotion_id', $id)
            ->whereIn('statut', ['EN_ATTENTE', 'EN_PREPARATION', 'EN_LIVRAISON'])
            ->count();

        return $count === 0;
    }

    // ─── Associer un produit ──────────────────────────────────────────────────

    public function associerProduit($promoId, $produitId, $montantReduction = null): PromotionProduit
    {
        $existing = PromotionProduit::where('promo_id', $promoId)
            ->where('produit_id', $produitId)
            ->first();

        if ($existing) {
            if ($montantReduction !== null) {
                $existing->update(['montant_reduction' => $montantReduction]);
            }
            return $existing;
        }

        return PromotionProduit::create([
            'promo_id'         => $promoId,
            'produit_id'       => $produitId,
            'montant_reduction' => $montantReduction,
        ]);
    }

    // ─── Dissocier un produit ─────────────────────────────────────────────────

    public function dissocierProduit($promoId, $produitId): void
    {
        $deleted = PromotionProduit::where('promo_id', $promoId)
            ->where('produit_id', $produitId)
            ->delete();

        if ($deleted === 0) {
            throw new \Exception("Association promotion-produit non trouvée.");
        }
    }
}