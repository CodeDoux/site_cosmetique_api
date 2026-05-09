<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommandeRequest;
use App\Models\Commande;
use App\Services\CommandeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandeController extends Controller
{
    public function __construct(private CommandeService $commandeService) {}

    // ─── GET /api/commandes ───────────────────────────────────────────────────
    // Admin : toutes les commandes | Filtres: statut, client_id
    public function index(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403, 'Accès réservé à l\'administrateur.');

        $commandes = $this->commandeService->lister($request->all());

        return response()->json($commandes);
    }

    // ─── GET /api/commandes/mes-commandes ─────────────────────────────────────
    // Client : ses propres commandes
    public function mesCommandes(Request $request): JsonResponse
    {
        $commandes = $this->commandeService->mesCommandes($request->user()->id);

        return response()->json($commandes);
    }

    // ─── GET /api/commandes/{commande} ────────────────────────────────────────
    // Admin ou client propriétaire
    public function show(Request $request, Commande $commande): JsonResponse
    {
        // Un client ne peut voir que ses propres commandes
        if ($request->user()->isClient() && $commande->client_id !== $request->user()->id) {
            abort(403, 'Accès refusé.');
        }

        return response()->json($this->commandeService->show($commande));
    }

    // ─── POST /api/commandes ──────────────────────────────────────────────────
    // Client uniquement
    public function store(CommandeRequest $request): JsonResponse
    {
        abort_if(!$request->user()->isClient(), 403, 'Seuls les clients peuvent passer une commande.');

        $commande = $this->commandeService->store(
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'message'  => 'Commande passée avec succès.',
            'commande' => $commande,
        ], 201);
    }

    // ─── PATCH /api/commandes/{commande}/statut ───────────────────────────────
    // Admin uniquement
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
