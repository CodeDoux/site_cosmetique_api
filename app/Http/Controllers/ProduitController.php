<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProduitRequest;
use App\Models\Image;
use App\Models\Produit;
use App\Services\ProduitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProduitController extends Controller
{
    public function __construct(private ProduitService $produitService) {}

    // ─── GET /api/produits ────────────────────────────────────────────────────
    // Public | Filtres: categorie_id, statut, recherche, prix_min, prix_max, tri, ordre, par_page
    public function index(Request $request): JsonResponse
    {
        $produits = $this->produitService->lister($request->all());
        return response()->json($produits);
    }

    // ─── GET /api/produits/{produit} ──────────────────────────────────────────
    // Public
    public function show(Produit $produit): JsonResponse
    {
        return response()->json($this->produitService->show($produit));
    }

    // ─── POST /api/produits ───────────────────────────────────────────────────
    // Admin uniquement
    public function store(ProduitRequest $request): JsonResponse
    {
        $produit = $this->produitService->store(
            $request->validated(),
            $request->file('images', [])
        );

        return response()->json([
            'message' => 'Produit créé avec succès.',
            'produit' => $produit,
        ], 201);
    }

    // ─── PUT /api/produits/{produit} ──────────────────────────────────────────
    // Admin uniquement
    public function update(ProduitRequest $request, Produit $produit): JsonResponse
    {
        $produit = $this->produitService->update($produit, $request->validated());

        return response()->json([
            'message' => 'Produit mis à jour.',
            'produit' => $produit,
        ]);
    }

    // ─── DELETE /api/produits/{produit} ───────────────────────────────────────
    // Admin uniquement
    public function destroy(Produit $produit): JsonResponse
    {
        $this->produitService->destroy($produit);

        return response()->json(['message' => 'Produit supprimé.']);
    }

    // ─── GET /api/produits/alerte-stock ──────────────────────────────────────
    // Admin uniquement
    public function alerteStock(): JsonResponse
    {
        return response()->json($this->produitService->produitsEnAlerteStock());
    }

    // ─── POST /api/produits/{produit}/images ──────────────────────────────────
    // Admin uniquement
    public function ajouterImages(Request $request, Produit $produit): JsonResponse
    {
        $request->validate([
            'images'   => 'required|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $produit = $this->produitService->ajouterImages($produit, $request->file('images'));

        return response()->json([
            'message' => 'Images ajoutées.',
            'produit' => $produit,
        ]);
    }

    // ─── DELETE /api/produits/images/{image} ──────────────────────────────────
    // Admin uniquement
    public function supprimerImage(Image $image): JsonResponse
    {
        $this->produitService->supprimerImage($image);

        return response()->json(['message' => 'Image supprimée.']);
    }
}
