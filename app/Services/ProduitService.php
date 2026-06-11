<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProduitService
{
    // ─── Lister avec filtres ──────────────────────────────────────────────────

    public function lister(array $filtres = [])
    {
        $query = Produit::with(['categorie', 'imagePrimaire','images'])
            ->when(
                isset($filtres['categorie_id']),
                fn($q) => $q->where('categorie_id', $filtres['categorie_id'])
            )
            ->when(
                isset($filtres['statut']),
                fn($q) => $q->where('statut', $filtres['statut'])
            )
            ->when(
                isset($filtres['recherche']),
                fn($q) => $q->where('nom', 'like', '%' . $filtres['recherche'] . '%')
            )
            ->when(
                isset($filtres['marque']),
                fn($q) => $q->where('marque', 'like', '%' . $filtres['marque'] . '%')
            )
            ->when(
                isset($filtres['prix_min']),
                fn($q) => $q->where('prix', '>=', $filtres['prix_min'])
            )
           ->when(
            !empty($filtres['en_promo']) && $filtres['en_promo'] == 1,
            fn($q) => $q->whereNotNull('prixPromo')
            )
            ->when(
                isset($filtres['prix_max']),
                fn($q) => $q->where('prix', '<=', $filtres['prix_max'])
            );

        // Tri : dateAjout, prix, note
        $tri   = $filtres['tri'] ?? 'created_at';
        $ordre = $filtres['ordre'] ?? 'desc';
        $query->orderBy($tri, $ordre);

        return $query->paginate($filtres['par_page'] ?? 15);
    }

    // ─── Voir un produit ──────────────────────────────────────────────────────

    public function show(Produit $produit): Produit
    {
        return $produit->load(['categorie', 'images', 'avis.client']);
    }

    // ─── Créer un produit ─────────────────────────────────────────────────────

    public function store(array $data, array $fichiers = []): Produit
    {
        return DB::transaction(function () use ($data, $fichiers) {

            $produit = Produit::create([
                'nom'              => $data['nom'],
                'description'      => $data['description'],
                'stock'            => $data['stock'],
                'prix'             => $data['prix'],
                'prixPromo'        => $data['prixPromo'] ?? null,
                'seuilAlerteStock' => $data['seuilAlerteStock'] ?? 5,
                'categorie_id'     => $data['categorie_id'],
                'note'             => $data['note'] ?? null,
                'marque'           => $data['marque'] ?? null,
                'statut'           => $data['stock'] > 0 ? 'DISPONIBLE' : 'EN_RUPTURE',
            ]);

            // Enregistrer les images
            foreach ($fichiers as $index => $fichier) {
                $chemin = $fichier->store('produits', 'public');
                Image::create([
                    'chemin'     => $chemin,
                    'isPrimary'  => $index === (int)($data['imagePrimaire'] ?? 0),
                    'altText'    => $produit->nom . ' - image ' . ($index + 1),
                    'produit_id' => $produit->id,
                ]);
            }

            return $produit->load(['categorie', 'images']);
        });
    }

    // ─── Modifier un produit ──────────────────────────────────────────────────

    public function update(Produit $produit, array $data): Produit
    {
        // Mise à jour du statut en fonction du stock
        if (isset($data['stock'])) {
            $data['statut'] = $data['stock'] > 0 ? 'DISPONIBLE' : 'EN_RUPTURE';
        }

        $produit->update($data);

        return $produit->fresh(['categorie', 'images']);
    }

    // ─── Supprimer un produit ─────────────────────────────────────────────────

    public function destroy(Produit $produit): void
    {
        // Supprimer les images du disque avant de supprimer le produit
        foreach ($produit->images as $image) {
            Storage::disk('public')->delete($image->chemin);
        }

        $produit->delete();
    }

    // ─── Produits en alerte de stock ──────────────────────────────────────────

    public function produitsEnAlerteStock()
    {
        return Produit::with('categorie')
            ->whereRaw('stock <= seuilAlerteStock')
            ->get();
    }

    // ─── Ajouter des images à un produit existant ─────────────────────────────

    public function ajouterImages(Produit $produit, array $fichiers): Produit
    {
        foreach ($fichiers as $index => $fichier) {
            $chemin = $fichier->store('produits', 'public');
            Image::create([
                'chemin'     => $chemin,
                'isPrimary'  => false,
                'altText'    => $produit->nom . ' - image ' . ($index + 1),
                'produit_id' => $produit->id,
            ]);
        }

        return $produit->fresh('images');
    }

    // ─── Supprimer une image ──────────────────────────────────────────────────

    public function supprimerImage(Image $image): void
    {
        Storage::disk('public')->delete($image->chemin);
        $image->delete();
    }
}
