<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategorieRequest;
use App\Models\Categorie;
use App\Services\CategorieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    public function __construct(private CategorieService $categorieService) {}

    // ─── GET /api/categories ──────────────────────────────────────────────────
    // Public
    public function index(): JsonResponse
    {
        return response()->json($this->categorieService->lister());
    }

    // ─── GET /api/categories/{categorie} ─────────────────────────────────────
    // Public
    public function show(Categorie $categorie): JsonResponse
    {
        return response()->json($this->categorieService->show($categorie));
    }

    // ─── POST /api/categories ─────────────────────────────────────────────────
    // Admin uniquement
    public function store(CategorieRequest $request): JsonResponse
    {
        $categorie = $this->categorieService->store($request->validated());

        return response()->json([
            'message'   => 'Catégorie créée.',
            'categorie' => $categorie,
        ], 201);
    }

    // ─── PUT /api/categories/{categorie} ──────────────────────────────────────
    // Admin uniquement
    public function update(CategorieRequest $request, Categorie $categorie): JsonResponse
    {
        $categorie = $this->categorieService->update($categorie, $request->validated());

        return response()->json([
            'message'   => 'Catégorie mise à jour.',
            'categorie' => $categorie,
        ]);
    }

    // ─── DELETE /api/categories/{categorie} ───────────────────────────────────
    // Admin uniquement
    public function destroy(Categorie $categorie): JsonResponse
    {
        $this->categorieService->destroy($categorie);

        return response()->json(['message' => 'Catégorie supprimée.']);
    }

}
