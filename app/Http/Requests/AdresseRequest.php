<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdresseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tout utilisateur connecté peut gérer ses adresses
        return auth()->check();
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            'store' => [
                'rue'        => 'nullable|string|max:255',
                'ville'      => 'required|string|max:255',
                'quartier'   => 'nullable|string|max:255',
                'codePostal' => 'nullable|string|max:20',
            ],

            'update' => [
                'rue'        => 'nullable|string|max:255',
                'ville'      => 'sometimes|string|max:255',
                'quartier'   => 'nullable|string|max:255',
                'codePostal' => 'nullable|string|max:20',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'ville.required' => 'La ville est obligatoire.',
        ];
    }
}
