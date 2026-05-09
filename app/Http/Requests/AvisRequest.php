<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClient() ?? false;
    }

    public function rules(): array
    {
        return [
            'produit_id'  => 'required|exists:produits,id',
            'note'        => 'required|integer|between:1,5',
            'commentaire' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'produit_id.exists' => 'Le produit sélectionné n\'existe pas.',
            'note.between'      => 'La note doit être entre 1 (mauvais) et 5 (excellent).',
        ];
    }
}
