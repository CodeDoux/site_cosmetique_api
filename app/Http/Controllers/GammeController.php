<?php

namespace App\Http\Controllers;

use App\Http\Requests\GammeRequest;
use App\Models\Gamme;
use App\Services\GammeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GammeController extends Controller
{
    public function __construct(private GammeService $gammeService) {}

    // ─── GET /api/gammes ──────────────────────────────────────────────────────
    // Public | Filtres: statut, recherche, prix_max, par_page
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->gammeService->lister($request->all()));
    }

    // ─── GET /api/gammes/{gamme} ──────────────────────────────────────────────
    // Public : détail + économie calculée
    public function show(Gamme $gamme): JsonResponse
    {
        return response()->json($this->gammeService->show($gamme));
    }

    // ─── POST /api/gammes ─────────────────────────────────────────────────────
    // Admin uniquement
    public function store(GammeRequest $request): JsonResponse
    {
        $gamme = $this->gammeService->store(
            $request->validated(),
            $request->file('image')
        );

        return response()->json([
            'message' => 'Gamme créée avec succès.',
            'gamme'   => $gamme,
        ], 201);
    }

    // ─── PUT /api/gammes/{gamme} ──────────────────────────────────────────────
    // Admin uniquement
    public function update(GammeRequest $request, Gamme $gamme): JsonResponse
    {
        $gamme = $this->gammeService->update(
            $gamme,
            $request->validated(),
            $request->file('image')
        );

        return response()->json([
            'message' => 'Gamme mise à jour.',
            'gamme'   => $gamme,
        ]);
    }

    // ─── DELETE /api/gammes/{gamme} ───────────────────────────────────────────
    // Admin uniquement
    public function destroy(Request $request, Gamme $gamme): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        $this->gammeService->destroy($gamme);

        return response()->json(['message' => 'Gamme supprimée.']);
    }

    // ─── POST /api/gammes/{gamme}/produits ────────────────────────────────────
    // Admin : ajouter ou mettre à jour un produit dans la gamme
    public function ajouterProduit(GammeRequest $request, Gamme $gamme): JsonResponse
    {
        $gamme = $this->gammeService->ajouterProduit($gamme, $request->validated());

        return response()->json([
            'message' => 'Produit ajouté à la gamme.',
            'gamme'   => $gamme,
        ]);
    }

    // ─── DELETE /api/gammes/{gamme}/produits/{produit} ────────────────────────
    // Admin : retirer un produit de la gamme
    public function retirerProduit(Request $request, Gamme $gamme, int $produitId): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        $gamme = $this->gammeService->retirerProduit($gamme, $produitId);

        return response()->json([
            'message' => 'Produit retiré de la gamme.',
            'gamme'   => $gamme,
        ]);
    }
}
