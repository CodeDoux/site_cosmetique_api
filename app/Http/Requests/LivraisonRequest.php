<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LivraisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            // Livreur : prendre en charge une livraison
            'prendreEnCharge' => [],  // pas de body requis

            // Livreur : marquer comme expédiée (départ)
            'marquerExpediee' => [
                'dateExpedition' => 'nullable|date',
            ],

            // Livreur : marquer comme livrée ou non livrée
            'mettreAJourStatut' => [
                'statutLivraison' => 'required|in:LIVREE,NON_LIVREE',
                'dateLivraison'   => 'nullable|date',
                'commentaire'     => 'nullable|string|max:500', // raison si NON_LIVREE
            ],

            // Admin : assigner une livraison à un livreur
            'assigner' => [
                'livreur_id' => 'required|exists:users,id',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'statutLivraison.in'  => 'Le statut doit être LIVREE ou NON_LIVREE.',
            'livreur_id.exists'   => 'Le livreur sélectionné n\'existe pas.',
        ];
    }
}
