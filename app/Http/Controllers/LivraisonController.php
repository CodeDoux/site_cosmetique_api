<?php

namespace App\Http\Controllers;

use App\Http\Requests\LivraisonRequest;
use App\Models\Livraison;
use App\Services\LivraisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LivraisonController extends Controller
{
    public function __construct(private LivraisonService $livraisonService) {}

    // ─── GET /api/livraisons ──────────────────────────────────────────────────
    // Admin : toutes les livraisons | Filtres: statut, livreur_id
    public function index(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        $livraisons = $this->livraisonService->listerTout($request->all());

        return response()->json($livraisons);
    }

    // ─── GET /api/livraisons/mes-livraisons ───────────────────────────────────
    // Livreur : ses livraisons assignées | Filtres: statut
    public function mesLivraisons(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        $livraisons = $this->livraisonService->mesLivraisons(
            $request->user()->id,
            $request->all()
        );

        return response()->json($livraisons);
    }

    // ─── GET /api/livraisons/{livraison} ──────────────────────────────────────
    // Admin ou Livreur assigné
    public function show(Request $request, Livraison $livraison): JsonResponse
    {
        $user = $request->user();

        // Un livreur ne peut voir que ses livraisons assignées
        if ($user->isLivreur() && $livraison->livreur_id !== $user->id) {
            abort(403, 'Vous n\'êtes pas assigné à cette livraison.');
        }

        return response()->json($this->livraisonService->show($livraison));
    }

    // ─── POST /api/livraisons/{livraison}/assigner ────────────────────────────
    // Admin : assigner un livreur à une livraison
    public function assigner(LivraisonRequest $request, Livraison $livraison): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        $livraison = $this->livraisonService->assigner(
            $livraison,
            $request->livreur_id
        );

        return response()->json([
            'message'   => 'Livreur assigné avec succès.',
            'livraison' => $livraison,
        ]);
    }

    // ─── POST /api/livraisons/{livraison}/prendre-en-charge ───────────────────
    // Livreur : prendre en charge une livraison non assignée
    public function prendreEnCharge(Request $request, Livraison $livraison): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        $livraison = $this->livraisonService->prendreEnCharge(
            $livraison,
            $request->user()->id
        );

        return response()->json([
            'message'   => 'Livraison prise en charge.',
            'livraison' => $livraison,
        ]);
    }

    // ─── PATCH /api/livraisons/{livraison}/expedier ───────────────────────────
    // Livreur : marquer comme expédiée (il part avec la commande)
    public function marquerExpediee(LivraisonRequest $request, Livraison $livraison): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur');

        $livraison = $this->livraisonService->marquerExpediee(
            $livraison,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message'   => 'Livraison marquée comme expédiée.',
            'livraison' => $livraison,
        ]);
    }

    // ─── PATCH /api/livraisons/{livraison}/statut ─────────────────────────────
    // Livreur : LIVREE ou NON_LIVREE
    public function mettreAJourStatut(LivraisonRequest $request, Livraison $livraison): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur');

        $livraison = $this->livraisonService->mettreAJourStatut(
            $livraison,
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message'   => 'Statut de livraison mis à jour.',
            'livraison' => $livraison,
        ]);
    }

    // ─── GET /api/livraisons/livreurs-disponibles ─────────────────────────────
    // Admin : liste des livreurs avec leur charge de travail actuelle
    public function livreursDisponibles(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        return response()->json($this->livraisonService->livreursDisponibles());
    }
}
