<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdresseRequest;
use App\Models\Adresse;
use App\Services\AdresseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdresseController extends Controller
{
    public function __construct(private AdresseService $adresseService) {}

    // ─── GET /api/adresses ────────────────────────────────────────────────────
    // Client : toutes ses adresses
    public function index(Request $request): JsonResponse
    {
        $adresses = $this->adresseService->lister($request->user()->id);

        return response()->json($adresses);
    }

    // ─── GET /api/adresses/{adresse} ──────────────────────────────────────────
    public function show(Request $request, Adresse $adresse): JsonResponse
    {
        $adresse = $this->adresseService->show($adresse, $request->user()->id);

        return response()->json($adresse);
    }

    // ─── POST /api/adresses ───────────────────────────────────────────────────
    public function store(AdresseRequest $request): JsonResponse
    {
        $adresse = $this->adresseService->store(
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Adresse ajoutée.',
            'adresse' => $adresse,
        ], 201);
    }

    // ─── PUT /api/adresses/{adresse} ──────────────────────────────────────────
    public function update(AdresseRequest $request, Adresse $adresse): JsonResponse
    {
        $adresse = $this->adresseService->update(
            $adresse,
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Adresse mise à jour.',
            'adresse' => $adresse,
        ]);
    }

    // ─── DELETE /api/adresses/{adresse} ───────────────────────────────────────
    public function destroy(Request $request, Adresse $adresse): JsonResponse
    {
        $this->adresseService->destroy($adresse, $request->user()->id);

        return response()->json(['message' => 'Adresse supprimée.']);
    }
}
