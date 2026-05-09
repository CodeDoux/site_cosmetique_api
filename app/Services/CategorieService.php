<?php

namespace App\Services;

use App\Models\Categorie;

class CategorieService
{
    // ─── Lister toutes les catégories ─────────────────────────────────────────

    public function lister()
    {
        // withCount : ajoute un champ "produits_count" sur chaque catégorie
        return Categorie::withCount('produits')->get();
    }

    // ─── Voir une catégorie avec ses produits ─────────────────────────────────

    public function show(Categorie $categorie): Categorie
    {
        return $categorie->load(['produits.imagePrimaire', 'produits.categorie']);
    }

    // ─── Créer une catégorie ──────────────────────────────────────────────────

    public function store(array $data): Categorie
    {
        return Categorie::create($data);
    }

    // ─── Modifier une catégorie ───────────────────────────────────────────────

    public function update(Categorie $categorie, array $data): Categorie
    {
        $categorie->update($data);
        return $categorie->fresh();
    }

    // ─── Supprimer une catégorie ──────────────────────────────────────────────

    public function destroy(Categorie $categorie): void
    {
        // La suppression cascade sur les produits (défini dans la migration)
        $categorie->delete();
    }
}
