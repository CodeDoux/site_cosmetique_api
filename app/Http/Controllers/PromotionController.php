<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Controllers/Api/PromotionController.php
// ══════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromotionRequest;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(private PromotionService $promotionService) {}

    // ─── GET /api/promotions ──────────────────────────────────────────────────
    // Admin uniquement
    public function index(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);
        return response()->json($this->promotionService->lister());
    }

    // ─── POST /api/promotions ─────────────────────────────────────────────────
    // Admin uniquement
    public function store(PromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotionService->store($request->validated());

        return response()->json([
            'message'   => 'Promotion créée.',
            'promotion' => $promotion,
        ], 201);
    }

    // ─── PUT /api/promotions/{promotion} ──────────────────────────────────────
    // Admin uniquement
    public function update(PromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion = $this->promotionService->update($promotion, $request->validated());

        return response()->json([
            'message'   => 'Promotion mise à jour.',
            'promotion' => $promotion,
        ]);
    }

    // ─── DELETE /api/promotions/{promotion} ───────────────────────────────────
    // Admin uniquement
    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);
        $this->promotionService->destroy($promotion);
        return response()->json(['message' => 'Promotion supprimée.']);
    }

    // ─── POST /api/promotions/valider-code ────────────────────────────────────
    // Client : vérifier un code promo avant commande
    public function validerCode(Request $request): JsonResponse
    {
        $request->validate([
            'code'    => 'required|string',
            'montant' => 'required|numeric|min:0',
        ]);

        $result = $this->promotionService->validerCode($request->code, $request->montant);

        return response()->json($result);
    }
}
