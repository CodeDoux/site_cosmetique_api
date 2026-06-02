<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use App\Models\Promotion;
class PromotionController extends Controller
{
    public function __construct(private PromotionService $promotionService) {}

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index()
    {
        try {
            return response()->json($this->promotionService->index());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── Créer ───────────────────────────────────────────────────────────────

    public function store(PromotionRequest $request)
    {
        try {
            $promotion = $this->promotionService->store($request->validated());
            return response()->json($promotion, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── Voir ────────────────────────────────────────────────────────────────

    public function show($id)
    {
        try {
            return response()->json($this->promotionService->show($id));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Promotion non trouvée.'], 404);
        }
    }

    // ─── Mettre à jour ───────────────────────────────────────────────────────

    public function update(PromotionRequest $request, $id)
    {
        try {
            $promotion = $this->promotionService->update($request->validated(), $id);
            return response()->json($promotion);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── Supprimer ───────────────────────────────────────────────────────────

    public function destroy($id)
    {
        try {
            if (!$this->promotionService->peutEtreSupprimee($id)) {
                return response()->json([
                    'message' => 'Impossible de supprimer : cette promotion est liée à des commandes en cours.'
                ], 409);
            }

            $this->promotionService->destroy($id);
            return response()->json(['message' => 'Promotion supprimée.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── Toggle actif ─────────────────────────────────────────────────────────

    public function toggle($id)
    {
        try {
            $promotion = $this->promotionService->toggle($id);
            return response()->json([
                'message'   => $promotion->estActif ? 'Promotion activée.' : 'Promotion désactivée.',
                'promotion' => $promotion,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // PromotionController.php
public function verifier(Request $request)
{
    $request->validate([
        'code'    => 'required|string',
        'montant' => 'required|numeric',
    ]);
    $promo = Promotion::where('code', $request->code)
        ->where('estActif', true)
        ->first();

    if (!$promo) {
        return response()->json(['message' => 'Code promo invalide.'], 422);
    }

    // Vérifier montant minimum
    if ($promo->montantMinCommande && $request->montant < $promo->montantMinCommande) {
        return response()->json([
            'message' => 'Montant minimum de ' . number_format($promo->montantMinCommande, 0, ',', ' ') . ' Fr requis.'
        ], 422);
    }

    // Calculer la réduction
    $reduction = $promo->type_valeur === 'POURCENTAGE'
        ? ($request->montant * $promo->valeur / 100)
        : $promo->valeur;

    return response()->json([
        'message'   => 'Code promo valide !',
        'reduction' => $reduction,
        'promo'     => [
            'nom'    => $promo->nom,
            'valeur' => $promo->valeur,
            'type'   => $promo->type_valeur,
        ],
    ]);
}

    // ─── Promotions actives ───────────────────────────────────────────────────

    public function actives()
    {
        try {
            return response()->json($this->promotionService->getPromotionsActives());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── Calculer prix avec promo ─────────────────────────────────────────────

    public function calculerPrix($produitId)
    {
        try {
            return response()->json(
                $this->promotionService->calculerPrixAvecPromotion($produitId)
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── Associer un produit ──────────────────────────────────────────────────

    public function associerProduit(Request $request, $id)
    {
        $request->validate([
            'produit_id'       => 'required|exists:produits,id',
            'montant_reduction' => 'nullable|numeric|min:0',
        ]);

        try {
            $association = $this->promotionService->associerProduit(
                $id,
                $request->produit_id,
                $request->montant_reduction
            );
            return response()->json(['message' => 'Produit associé.', 'association' => $association]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── Dissocier un produit ─────────────────────────────────────────────────

    public function dissocierProduit($id, $produitId)
    {
        try {
            $this->promotionService->dissocierProduit($id, $produitId);
            return response()->json(['message' => 'Produit dissocié.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}