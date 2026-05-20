<?php

namespace App\Services;

use App\Models\Gamme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GammeService
{
// ─── Lister les gammes ────────────────────────────────────────────────────

    public function lister(array $filtres = [])
    {
        return Gamme::with(['produits.imagePrimaire','produits.images'])
            ->when(
                isset($filtres['statut']),
                fn($q) => $q->where('statut', $filtres['statut'])
            )
            ->when(
                isset($filtres['recherche']),
                fn($q) => $q->where('nom', 'like', '%' . $filtres['recherche'] . '%')
            )
            ->when(
                isset($filtres['prix_max']),
                fn($q) => $q->where('prix_fixe', '<=', $filtres['prix_max'])
            )
            ->latest()
            ->paginate($filtres['par_page'] ?? 12);
    }

    // ─── Détail d'une gamme ───────────────────────────────────────────────────

    public function show(Gamme $gamme): array
    {
        $gamme->load(['produits','produits.imagePrimaire', 'produits.categorie','produits.images']);

        return [
            'gamme'         => $gamme,
            'valeur_totale' => $gamme->valeur_totale,
            'economie'      => $gamme->economie,
            'disponible'    => $gamme->estDisponible(),
            'en_stock'      => $gamme->aAssezDeStock(),
        ];
    }

    // ─── Créer une gamme ──────────────────────────────────────────────────────

    public function store(array $data, $imageFile = null): Gamme
    {
        Log::info('GammeService::store appelé', ['data' => $data]);
        return DB::transaction(function () use ($data, $imageFile) {

            if ($imageFile) {
                $data['image'] = $imageFile->store('gammes', 'public');
            }

            // Extraire les produits avant la création
            $produits = $data['produits'] ?? [];
            unset($data['produits']);

            $gamme = Gamme::create($data);

            // Attacher les produits via la table pivot gamme_produit
            foreach ($produits as $p) {
                $gamme->produits()->attach($p['produit_id'], [
                    'quantite'        => $p['quantite'],
                    'valeur_unitaire' => $p['valeur_unitaire'] ?? null,
                ]);
            }

            return $gamme->load('produits');

        });

    }

    // ─── Modifier une gamme ───────────────────────────────────────────────────

    public function update(Gamme $gamme, array $data, $imageFile = null): Gamme
    {
        if ($imageFile) {
            if ($gamme->image) {
                Storage::disk('public')->delete($gamme->image);
            }
            $data['image'] = $imageFile->store('gammes', 'public');
        }

        $gamme->update($data);

        return $gamme->fresh('produits');
    }

    // ─── Supprimer une gamme ──────────────────────────────────────────────────

    public function destroy(Gamme $gamme): void
    {
        if ($gamme->image) {
            Storage::disk('public')->delete($gamme->image);
        }
        // cascade sur gamme_produit, set null sur ligne_commandes
        $gamme->delete();
    }

    // ─── Ajouter ou mettre à jour un produit dans la gamme ───────────────────

    public function ajouterProduit(Gamme $gamme, array $data): Gamme
    {
        // Si le produit est déjà dans la gamme → met à jour quantite/valeur_unitaire
        // Sinon → l'ajoute
        $gamme->produits()->syncWithoutDetaching([
            $data['produit_id'] => [
                'quantite'        => $data['quantite'],
                'valeur_unitaire' => $data['valeur_unitaire'] ?? null,
            ],
        ]);

        return $gamme->fresh('produits');
    }

    // ─── Retirer un produit de la gamme ──────────────────────────────────────

    public function retirerProduit(Gamme $gamme, int $produitId): Gamme
    {
        if ($gamme->produits()->count() <= 1) {
            throw new \Exception('Une gamme doit contenir au moins un produit.');
        }

        $gamme->produits()->detach($produitId);

        return $gamme->fresh('produits');
    }

    // ─── Vérifier et décrémenter le stock (appelé par CommandeService) ────────

    public function verifierEtDecrementerStock(Gamme $gamme, int $quantiteGammes = 1): void
    {
        if (!$gamme->estDisponible()) {
            throw new \Exception("La gamme \"{$gamme->nom}\" n'est plus disponible.");
        }

        foreach ($gamme->produits as $produit) {
            $qteNecessaire = $produit->pivot->quantite * $quantiteGammes;

            if ($produit->stock < $qteNecessaire) {
                throw new \Exception(
                    "Stock insuffisant pour \"{$produit->nom}\" dans la gamme \"{$gamme->nom}\"."
                );
            }

            $produit->decrement('stock', $qteNecessaire);

            if ($produit->fresh()->stock <= 0) {
                $produit->update(['statut' => 'EN_RUPTURE']);
            }
        }

        // Passer la gamme en EPUISEE si un produit manque désormais
        if (!$gamme->fresh()->aAssezDeStock()) {
            $gamme->update(['statut' => 'EPUISEE']);
        }
    }
}
