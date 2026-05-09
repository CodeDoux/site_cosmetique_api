<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $action     = $this->route()->getActionMethod();
        $promotion  = $this->route('promotion');

        return match ($action) {

            'store' => [
                'nom'                => 'required|string|max:255',
                'description'        => 'nullable|string',
                'code'               => 'nullable|string|max:50|unique:promotions,code',
                'type'               => 'required|in:POURCENTAGE,MONTANT_FIXE',
                'valeur'             => 'required|numeric|min:0',
                'montantMinCommande' => 'nullable|numeric|min:0',
                'estActif'           => 'boolean',
                'dateDebut'          => 'required|date',
                'dateFin'            => 'nullable|date|after:dateDebut',
                'produit_id'         => 'nullable|exists:produits,id',
            ],

            'update' => [
                'nom'                => 'sometimes|string|max:255',
                'description'        => 'nullable|string',
                'code'               => 'nullable|string|max:50|unique:promotions,code,' . $promotion?->id,
                'type'               => 'sometimes|in:POURCENTAGE,MONTANT_FIXE',
                'valeur'             => 'sometimes|numeric|min:0',
                'montantMinCommande' => 'nullable|numeric|min:0',
                'estActif'           => 'boolean',
                'dateDebut'          => 'sometimes|date',
                'dateFin'            => 'nullable|date|after:dateDebut',
                'produit_id'         => 'nullable|exists:produits,id',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'code.unique'       => 'Ce code promo est déjà utilisé.',
            'dateFin.after'     => 'La date de fin doit être après la date de début.',
            'produit_id.exists' => 'Le produit sélectionné n\'existe pas.',
        ];
    }
}
