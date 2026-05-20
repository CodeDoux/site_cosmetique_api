<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Controllers/Api/PaiementController.php
// ══════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaiementRequest;
use App\Services\PaiementService;
use App\Models\Paiement;
use App\Services\PayDunyaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    public function __construct(private PaiementService $paiementService, private PayDunyaService $payDunya) {}

    // ─── POST /api/paiements ──────────────────────────────────────────────────
    // Client initie un paiement pour une commande
    public function initier(PaiementRequest $request): JsonResponse
    {
        $result = $this->paiementService->initier($request->validated());

        return response()->json($result);
    }

     /**
     * Initier un paiement en ligne
     */
    public function initierPaiementEnLigne(Request $request, Commande $commande)
    {
        $result = $this->payDunya->creerFacture([
            'commande_id' => $commande->id,
            'montant'     => (int) $commande->montantTotal,
            'description' => "Commande CoBeauty #{$commande->id}",
        ]);

        if (($result['response_code'] ?? '') !== '00') {
            return response()->json([
                'message' => $result['description'] ?? 'Erreur PayDunya'
            ], 500);
        }

        // Sauvegarder le token PayDunya dans le paiement
        $commande->paiement()->update([
            'reference' => $result['token'],
        ]);

        return response()->json([
            'payment_url' => $result['response_text'],
            'token'       => $result['token'],
        ]);
    }

    /**
     * Callback PayDunya (webhook)
     */
    public function callback(Request $request)
    {
        $token = $request->data['invoice']['token'] ?? null;

        if (!$token) {
            return response()->json(['status' => 'error'], 400);
        }

        // Vérifier le statut auprès de PayDunya
        $result = $this->payDunya->verifierFacture($token);

        if (($result['status'] ?? '') === 'completed') {
            $paiement = Paiement::where('reference', $token)->first();
            if ($paiement) {
                $paiement->update(['statutPaiement' => 'PAYEE']);
                $paiement->commande()->update(['statut' => 'EN_PREPARATION']);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Vérifier manuellement depuis Angular après retour
     */
    public function verifierStatut(string $token)
    {
        $result   = $this->payDunya->verifierFacture($token);
        $paiement = Paiement::where('reference', $token)->first();

        return response()->json([
            'statut'   => $result['status'] ?? 'unknown',
            'paiement' => $paiement,
        ]);
    }

       // ─── GET /api/paiements ───────────────────────────────────────────────────
    public function index(Request $request)
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

    // ─── GET /api/paiements/{paiement} ────────────────────────────────────────
    public function show(Paiement $paiement)
    {
        return response()->json($paiement->load('commande'));
    }

    // ─── POST /api/paiements ──────────────────────────────────────────────────
    public function store(Request $request)
    {
        $result = $this->paiementService->initier($request->all());
        return response()->json($result, 201);
    }

    public function changerStatut(Request $request, Paiement $paiement)
{
    $request->validate(['statutPaiement' => 'required|in:PAYEE,NON_PAYEE,REMBOURSE']);
    $paiement->update(['statutPaiement' => $request->statutPaiement]);
    return response()->json(['message' => 'Statut mis à jour.', 'paiement' => $paiement->fresh()]);
}

    // ─── PATCH /api/paiements/{paiement}/rembourser ───────────────────────────
    public function rembourser(Request $request, Paiement $paiement)
    {
        if ($paiement->statutPaiement !== 'PAYEE') {
            return response()->json([
                'message' => 'Seuls les paiements payés peuvent être remboursés.'
            ], 422);
        }

        $paiement->update(['statutPaiement' => 'REMBOURSE']);

        // Optionnel : notifier le client
        // $this->notifService->envoyer(...);

        return response()->json([
            'message'  => 'Paiement remboursé avec succès.',
            'paiement' => $paiement->fresh(),
        ]);
    }
    // ─── POST /api/paiements/webhook/wave ─────────────────────────────────────
    // Appelé automatiquement par Wave (pas de auth:sanctum sur cette route !)
    public function webhookWave(Request $request): JsonResponse
    {
        // Vérification de la signature Wave pour sécuriser le webhook
        $signature       = $request->header('Wave-Signature');
        $payload         = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, config('services.wave.webhook_secret'));

        if (!hash_equals($expectedSignature, $signature ?? '')) {
            Log::warning('Wave webhook - signature invalide');
            return response()->json(['error' => 'Signature invalide.'], 401);
        }

        $this->paiementService->traiterWebhookWave($request->all());

        return response()->json(['status' => 'ok']);
    }

    // ─── POST /api/paiements/webhook/paydunya ────────────────────────────────
    // Appelé automatiquement par PayDunya
    public function webhookPayDunya(Request $request): JsonResponse
    {
        $this->paiementService->traiterWebhookPayDunya($request->all());

        return response()->json(['status' => 'ok']);
    }
}
