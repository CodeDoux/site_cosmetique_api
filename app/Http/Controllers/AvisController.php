<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Controllers/Api/AvisController.php
// ══════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvisRequest;
use App\Models\Avis;
use App\Services\AvisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvisController extends Controller
{
    public function __construct(private AvisService $avisService) {}

    // ─── GET /api/avis/{produitId} ────────────────────────────────────────────
    // Public : avis approuvés d'un produit
    public function parProduit(int $produitId): JsonResponse
    {
        return response()->json($this->avisService->parProduit($produitId));
    }

    // ─── GET /api/avis/en-attente ─────────────────────────────────────────────
    // Admin : avis en attente de modération
    public function enAttente(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);
        return response()->json($this->avisService->enAttente());
    }

    // ─── POST /api/avis ───────────────────────────────────────────────────────
    // Client uniquement
    public function store(AvisRequest $request): JsonResponse
    {
        $avis = $this->avisService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Avis soumis, en attente de validation par l\'administrateur.',
            'avis'    => $avis,
        ], 201);
    }

    // ─── PATCH /api/avis/{avis}/moderer ──────────────────────────────────────
    // Admin : approuver ou rejeter un avis
    public function moderer(Request $request, Avis $avis): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);
        $request->validate(['statut' => 'required|in:APPROUVEE,REJETEE']);

        $avis = $this->avisService->moderer($avis, $request->statut);

        return response()->json(['message' => 'Avis modéré.', 'avis' => $avis]);
    }

    // ─── DELETE /api/avis/{avis} ──────────────────────────────────────────────
    // Admin uniquement
    public function destroy(Request $request, Avis $avis): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);
        $this->avisService->destroy($avis);
        return response()->json(['message' => 'Avis supprimé.']);
    }
}
