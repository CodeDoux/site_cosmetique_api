<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommandeRequest;
use App\Models\Commande;
use App\Services\CommandeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\PaiementService;

class CommandeController extends Controller
{
    public function __construct(private CommandeService $commandeService,  private PaiementService $paiementService,) {}

    // ─── GET /api/commandes ───────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        return response()->json($this->commandeService->lister($request->all()));
    }

    // ─── GET /api/commandes/mes-commandes ─────────────────────────────────────
    public function mesCommandes(Request $request): JsonResponse
    {
        return response()->json($this->commandeService->mesCommandes($request->user()->id));
    }

    // ─── GET /api/commandes/{commande} ────────────────────────────────────────
    public function show(Request $request, Commande $commande): JsonResponse
    {
        if ($request->user()?->isClient() && $commande->client_id !== $request->user()->id) {
            abort(403, 'Accès refusé.');
        }

        return response()->json($this->commandeService->show($commande));
    }

    // ─── POST /api/commandes ──────────────────────────────────────────────────
    // Accessible sans token (invité) ET avec token (client connecté)
    public function store(CommandeRequest $request): JsonResponse
    {
        $user = $request->user(); // null si invité

        // Si connecté, seul un CLIENT peut commander (pas un admin ou livreur)
        if ($user && !$user->isClient()) {
            abort(403, 'Seuls les clients peuvent passer une commande.');
        }

        $commande = $this->commandeService->store(
            $request->validated(),
            $user?->id  // null si invité → CommandeService crée le compte
        );

         // 2. Initier le paiement via PaiementService
        $resultPaiement = $this->paiementService->initier([
            'commande_id'  => $commande->id,
            'modePaiement' => $request->input('paiement.modePaiement'),
            'operateur'    => $request->input('paiement.operateur'),
            'telephone'    => $request->input('paiement.telephone'),
        ]);

        return response()->json([
            'commande'     => $commande,
            'paiement'     => $resultPaiement['paiement'],
            // URL de redirection Wave ou PayDunya (null si espèces)
            'checkout_url' => $resultPaiement['checkout_url'] ?? null,
            'message'      => $resultPaiement['message'],
        ], 201);
    }

    // ─── PATCH /api/commandes/{commande}/statut ───────────────────────────────
    public function changerStatut(CommandeRequest $request, Commande $commande): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        $commande = $this->commandeService->changerStatut($commande, $request->statut);

        return response()->json([
            'message'  => 'Statut de la commande mis à jour.',
            'commande' => $commande,
        ]);
    }
}
