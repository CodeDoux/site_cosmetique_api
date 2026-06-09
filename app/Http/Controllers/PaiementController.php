<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Controllers/Api/PaiementController.php
// ══════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PaiementService;
use App\Models\Paiement;
use App\Models\Commande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    public function __construct(private PaiementService $paiementService) {}


    // ══════════════════════════════════════════════════════════
    // INITIER UN PAIEMENT EN LIGNE (appelé depuis Angular)
    // POST /api/paiements/{commande}/initier
    // ══════════════════════════════════════════════════════════

    public function initierPaiementEnLigne(Request $request, Commande $commande): JsonResponse
    {
        $request->validate([
            'modePaiement' => 'required|in:EN_LIGNE,EN_ESPECE',
            'operateur'    => 'nullable|in:ORANGE_MONEY,WAVE,FREE_MONEY',
            'telephone'    => 'nullable|string',
        ]);

        $result = $this->paiementService->initier([
            'commande_id'  => $commande->id,
            'modePaiement' => $request->modePaiement,
            'operateur'    => $request->operateur,
            'telephone'    => $request->telephone,
        ]);

        return response()->json($result);
    }


    // ══════════════════════════════════════════════════════════
    // WEBHOOK PAYDUNYA
    // POST /api/paiements/webhook/paydunya (sans auth:sanctum)
    // Appelé automatiquement par PayDunya après chaque paiement
    // ══════════════════════════════════════════════════════════

    public function webhookPayDunya(Request $request): JsonResponse
    {
        Log::info('PayDunya webhook reçu', $request->all());
        $this->paiementService->traiterWebhookPayDunya($request->all());
        return response()->json(['status' => 'ok']);
    }


    // ══════════════════════════════════════════════════════════
    // VÉRIFIER LE STATUT D'UN PAIEMENT (après retour PayDunya)
    // GET /api/paiements/{reference}/statut
    // ══════════════════════════════════════════════════════════

    public function verifierStatut(string $reference): JsonResponse
    {
        $paiement = Paiement::where('reference', $reference)
            ->with('commande')
            ->first();

        if (!$paiement) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        return response()->json([
            'statut'   => $paiement->statutPaiement,
            'paiement' => $paiement,
        ]);
    }


    // ══════════════════════════════════════════════════════════
    // LISTE DES PAIEMENTS (admin)
    // GET /api/paiements
    // ══════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $paiements = Paiement::with('commande')
            ->when($request->search, fn($q) =>
                $q->where('reference', 'like', '%'.$request->search.'%')
                  ->orWhere('telephone', 'like', '%'.$request->search.'%')
            )
            ->when($request->statut, fn($q) =>
                $q->where('statutPaiement', $request->statut)
            )
            ->when($request->mode, fn($q) =>
                $q->where('modePaiement', $request->mode)
            )
            ->latest('datePaiement')
            ->paginate($request->per_page ?? 12);

        return response()->json($paiements);
    }


    // ══════════════════════════════════════════════════════════
    // DÉTAIL D'UN PAIEMENT (admin)
    // GET /api/paiements/{paiement}
    // ══════════════════════════════════════════════════════════

    public function show(Paiement $paiement): JsonResponse
    {
        return response()->json($paiement->load('commande'));
    }


    // ══════════════════════════════════════════════════════════
    // CHANGER STATUT (admin)
    // PATCH /api/paiements/{paiement}/statut
    // ══════════════════════════════════════════════════════════

    public function changerStatut(Request $request, Paiement $paiement): JsonResponse
    {
        $request->validate([
            'statutPaiement' => 'required|in:PAYEE,NON_PAYEE,REMBOURSE',
        ]);

        $paiement->update(['statutPaiement' => $request->statutPaiement]);

        return response()->json([
            'message'  => 'Statut mis à jour.',
            'paiement' => $paiement->fresh(),
        ]);
    }


    // ══════════════════════════════════════════════════════════
    // REMBOURSER (admin)
    // PATCH /api/paiements/{paiement}/rembourser
    // ══════════════════════════════════════════════════════════

    public function rembourser(Request $request, Paiement $paiement): JsonResponse
{
    if ($paiement->statutPaiement !== 'PAYEE') {
        return response()->json([
            'message' => 'Seuls les paiements payés peuvent être remboursés.'
        ], 422);
    }

    // ─── Rembourser le paiement ───────────────────────────────────────
    $paiement->update(['statutPaiement' => 'REMBOURSE']);

    // ─── Annuler la commande + restaurer le stock ─────────────────────
    $commande = $paiement->commande;
    if ($commande && $commande->statut !== 'ANNULEE') {
        $this->paiementService->annulerCommande($commande);
    }

    return response()->json([
        'message'  => 'Paiement remboursé et commande annulée.',
        'paiement' => $paiement->fresh(),
    ]);
}
}