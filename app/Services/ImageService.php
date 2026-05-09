<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Produit;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    // ─── Ajouter des images à un produit ──────────────────────────────────────

    public function store(Produit $produit, array $fichiers, ?int $indexPrimaire = null): Produit
    {
        // Si le produit n'a pas encore d'image primaire,
        // la première image uploadée devient automatiquement primaire
        $aPrimaire = $produit->images()->where('isPrimary', true)->exists();

        foreach ($fichiers as $index => $fichier) {
            $chemin = $fichier->store('produits', 'public');

            // Devient primaire si :
            // - c'est l'index choisi par l'admin
            // - OU le produit n'avait pas de primaire et c'est la première (index 0)
            $estPrimaire = ($indexPrimaire !== null)
                ? $index === $indexPrimaire
                : (!$aPrimaire && $index === 0);

            // Si on définit une nouvelle primaire, retirer l'ancienne
            if ($estPrimaire && $aPrimaire) {
                $produit->images()->where('isPrimary', true)->update(['isPrimary' => false]);
                $aPrimaire = false;
            }

            Image::create([
                'chemin'     => $chemin,
                'isPrimary'  => $estPrimaire,
                'altText'    => $produit->nom . ' - image ' . ($index + 1),
                'produit_id' => $produit->id,
            ]);
        }

        return $produit->fresh('images');
    }

    // ─── Définir une image comme primaire ─────────────────────────────────────

    public function definirPrimaire(Image $image): Image
    {
        // Retirer l'ancienne image primaire du même produit
        Image::where('produit_id', $image->produit_id)
            ->where('isPrimary', true)
            ->update(['isPrimary' => false]);

        // Définir la nouvelle
        $image->update(['isPrimary' => true]);

        return $image->fresh();
    }

    // ─── Modifier le texte alternatif ─────────────────────────────────────────

    public function updateAltText(Image $image, string $altText): Image
    {
        $image->update(['altText' => $altText]);
        return $image->fresh();
    }

    // ─── Supprimer une image ──────────────────────────────────────────────────

    public function destroy(Image $image): void
    {
        // Empêcher la suppression de la seule image d'un produit
        $totalImages = Image::where('produit_id', $image->produit_id)->count();

        if ($totalImages <= 1) {
            throw new \Exception('Impossible de supprimer la seule image du produit. Ajoutez une autre image d\'abord.');
        }

        // Si c'était la primaire, passer automatiquement la suivante en primaire
        if ($image->isPrimary) {
            $suivante = Image::where('produit_id', $image->produit_id)
                ->where('id', '!=', $image->id)
                ->first();

            $suivante?->update(['isPrimary' => true]);
        }

        // Supprimer le fichier du disque
        Storage::disk('public')->delete($image->chemin);

        $image->delete();
    }

    // ─── Lister les images d'un produit ──────────────────────────────────────

    public function parProduit(int $produitId)
    {
        return Image::where('produit_id', $produitId)
            ->orderByDesc('isPrimary') // la primaire en premier
            ->get();
    }

}
