<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'commande_id'  => 'required|exists:commandes,id',
            'modePaiement' => 'required|in:EN_LIGNE,EN_ESPECE',
            // operateur obligatoire seulement si paiement en ligne
            'operateur'    => 'required_if:modePaiement,EN_LIGNE|in:ORANGE_MONEY,WAVE,FREE_MONEY',
            // telephone obligatoire seulement si paiement en ligne
            'telephone'    => 'required_if:modePaiement,EN_LIGNE|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'commande_id.exists'      => 'La commande sélectionnée n\'existe pas.',
            'operateur.required_if'   => 'L\'opérateur est requis pour un paiement en ligne.',
            'telephone.required_if'   => 'Le numéro de téléphone est requis pour un paiement en ligne.',
            'operateur.in'            => 'L\'opérateur doit être ORANGE_MONEY, WAVE ou FREE_MONEY.',
        ];
    }
}
