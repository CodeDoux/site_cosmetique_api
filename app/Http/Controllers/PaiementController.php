<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Controllers/Api/PaiementController.php
// ══════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaiementRequest;
use App\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    public function __construct(private PaiementService $paiementService) {}

    // ─── POST /api/paiements ──────────────────────────────────────────────────
    // Client initie un paiement pour une commande
    public function initier(PaiementRequest $request): JsonResponse
    {
        $result = $this->paiementService->initier($request->validated());

        return response()->json($result);
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
