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
        $action    = $this->route()->getActionMethod();
        $promotion = $this->route('promotion');

        return match ($action) {

            'store' => [
                'nom'                => 'required|string|max:255',
                'description'        => 'nullable|string',
                // ← 'code' correspond au champ en BDD (pas 'codePromo')
                'code'               => 'nullable|string|max:50|unique:promotions,code',
                'type'               => 'required|in:POURCENTAGE,MONTANT_FIXE',
                'valeur'             => 'required|numeric|min:0',
                // ← nullable car default(0) en BDD
                'montantMinCommande' => 'nullable|numeric|min:0',
                'estActif'           => 'boolean',
                'dateDebut'          => 'required|date',
                // ← nullable car certaines promos n'ont pas de date de fin
                'dateFin'            => 'nullable|date|after:dateDebut',
                // ← tableau d'IDs pour la table pivot promotion_produits
                'produit_ids'        => 'nullable|array',
                'produit_ids.*'      => 'exists:produits,id',
            ],

            'update' => [
                'nom'                => 'sometimes|string|max:255',
                'description'        => 'nullable|string',
                // ← ignore l'ID courant pour la vérification unique
                'code'               => 'nullable|string|max:50|unique:promotions,code,' . $promotion?->id,
                'type'               => 'sometimes|in:POURCENTAGE,MONTANT_FIXE',
                'valeur'             => 'sometimes|numeric|min:0',
                'montantMinCommande' => 'nullable|numeric|min:0',
                'estActif'           => 'boolean',
                'dateDebut'          => 'sometimes|date',
                'dateFin'            => 'nullable|date|after:dateDebut',
                'produit_ids'        => 'nullable|array',
                'produit_ids.*'      => 'exists:produits,id',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'nom.required'        => 'Le nom de la promotion est obligatoire.',
            'type.required'       => 'Le type de réduction est obligatoire.',
            'type.in'             => 'Le type doit être POURCENTAGE ou MONTANT_FIXE.',
            'valeur.required'     => 'La valeur de la réduction est obligatoire.',
            'valeur.min'          => 'La valeur doit être supérieure ou égale à 0.',
            'code.unique'         => 'Ce code promo est déjà utilisé.',
            'dateFin.after'       => 'La date de fin doit être après la date de début.',
            'produit_ids.array'   => 'Les produits doivent être un tableau.',
            'produit_ids.*.exists'=> 'Un des produits sélectionnés n\'existe pas.',
        ];
    }
}