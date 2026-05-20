<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserService
{
    // ─── Liste paginée avec filtres ───────────────────────────────────────────

    public function lister(array $filtres = [])
    {
        return User::query()
            ->when(
                !empty($filtres['search']),
                fn($q) => $q->where(function ($q) use ($filtres) {
                    $q->where('nomComplet', 'like', '%' . $filtres['search'] . '%')
                      ->orWhere('email',    'like', '%' . $filtres['search'] . '%')
                      ->orWhere('tel',      'like', '%' . $filtres['search'] . '%');
                })
            )
            ->when(
                !empty($filtres['role']),
                fn($q) => $q->where('role', $filtres['role'])
            )
            ->latest()
            ->paginate($filtres['per_page'] ?? 12);
    }

    // ─── Créer un utilisateur ─────────────────────────────────────────────────

    public function store(array $data): User
    {
        return User::create([
            'nomComplet' => $data['nomComplet'],
            'email'      => $data['email'],
            'tel'        => $data['tel'] ?? null,
            'role'       => $data['role'] ?? 'CLIENT',
            'password'   => $data['password'], // hashé par le cast du model
        ]);
    }

    // ─── Voir un utilisateur ──────────────────────────────────────────────────

    public function show(User $user): User
    {
        return $user->load(['commandes', 'livraisons']);
    }

    // ─── Mettre à jour ────────────────────────────────────────────────────────

    public function update(User $user, array $data): User
    {
        $payload = [
            'nomComplet' => $data['nomComplet'] ?? $user->nomComplet,
            'email'      => $data['email']      ?? $user->email,
            'tel'        => $data['tel']        ?? $user->tel,
            'role'       => $data['role']       ?? $user->role,
        ];

        // Mettre à jour le mot de passe uniquement s'il est fourni
        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        return $user->fresh();
    }

    // ─── Changer le rôle ──────────────────────────────────────────────────────

    public function changerRole(User $user, string $role): User
    {
        $user->update(['role' => $role]);
        return $user->fresh();
    }

    // ─── Supprimer ────────────────────────────────────────────────────────────

    public function destroy(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->delete();
        });
    }
}