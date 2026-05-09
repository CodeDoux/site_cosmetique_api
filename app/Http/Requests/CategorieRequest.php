<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            'store' => [
                'nom'         => 'required|string|max:255|unique:categories,nom',
                'description' => 'required|string',
            ],

            'update' => [
                'nom'         => 'sometimes|string|max:255|unique:categories,nom,' . $this->route('categorie')->id,
                'description' => 'sometimes|string',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'Une catégorie avec ce nom existe déjà.',
        ];
    }
}
