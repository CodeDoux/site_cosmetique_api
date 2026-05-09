<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageRequest;
use App\Models\Image;
use App\Models\Produit;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function __construct(private ImageService $imageService) {}

    // ─── GET /api/produits/{produit}/images ───────────────────────────────────
    // Public : voir les images d'un produit
    public function index(Produit $produit): JsonResponse
    {
        $images = $this->imageService->parProduit($produit->id);

        return response()->json($images);
    }

    // ─── POST /api/produits/{produit}/images ──────────────────────────────────
    // Admin : ajouter des images à un produit
    public function store(ImageRequest $request, Produit $produit): JsonResponse
    {
        $produit = $this->imageService->store(
            $produit,
            $request->file('images'),
            $request->input('imagePrimaire')
        );

        return response()->json([
            'message' => 'Images ajoutées avec succès.',
            'images'  => $produit->images,
        ], 201);
    }

    // ─── PATCH /api/images/{image}/primaire ───────────────────────────────────
    // Admin : définir une image comme principale
    public function definirPrimaire(Request $request, Image $image): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        $image = $this->imageService->definirPrimaire($image);

        return response()->json([
            'message' => 'Image définie comme principale.',
            'image'   => $image,
        ]);
    }

    // ─── PATCH /api/images/{image}/alt-text ───────────────────────────────────
    // Admin : modifier le texte alternatif (SEO + accessibilité)
    public function updateAltText(ImageRequest $request, Image $image): JsonResponse
    {
        $image = $this->imageService->updateAltText($image, $request->altText);

        return response()->json([
            'message' => 'Texte alternatif mis à jour.',
            'image'   => $image,
        ]);
    }

    // ─── DELETE /api/images/{image} ───────────────────────────────────────────
    // Admin : supprimer une image
    public function destroy(Request $request, Image $image): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        $this->imageService->destroy($image);

        return response()->json(['message' => 'Image supprimée.']);
    }
}
