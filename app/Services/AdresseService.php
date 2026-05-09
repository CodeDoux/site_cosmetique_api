<?php

namespace App\Services;

use App\Models\Adresse;

class AdresseService
{
    // ─── Lister les adresses d'un utilisateur ─────────────────────────────────

    public function lister(int $userId)
    {
        return Adresse::where('user_id', $userId)->get();
    }

    // ─── Voir une adresse ─────────────────────────────────────────────────────

    public function show(Adresse $adresse, int $userId): Adresse
    {
        // Vérifier que l'adresse appartient bien à l'utilisateur
        $this->verifierProprietaire($adresse, $userId);

        return $adresse;
    }

    // ─── Créer une adresse ────────────────────────────────────────────────────

    public function store(array $data, int $userId): Adresse
    {
        return Adresse::create(array_merge($data, ['user_id' => $userId]));
    }

    // ─── Modifier une adresse ─────────────────────────────────────────────────

    public function update(Adresse $adresse, array $data, int $userId): Adresse
    {
        $this->verifierProprietaire($adresse, $userId);

        $adresse->update($data);

        return $adresse->fresh();
    }

    // ─── Supprimer une adresse ────────────────────────────────────────────────

    public function destroy(Adresse $adresse, int $userId): void
    {
        $this->verifierProprietaire($adresse, $userId);

        // Vérifier que l'adresse n'est pas utilisée par une livraison en cours
        $utilisee = $adresse->livraisons()
            ->where('statutLivraison', 'EN_COURS')
            ->exists();

        if ($utilisee) {
            throw new \Exception('Impossible de supprimer cette adresse : elle est utilisée par une livraison en cours.');
        }

        $adresse->delete();
    }

    // ─── Vérification propriétaire ────────────────────────────────────────────

    private function verifierProprietaire(Adresse $adresse, int $userId): void
    {
        if ($adresse->user_id !== $userId) {
            abort(403, 'Cette adresse ne vous appartient pas.');
        }
    }
}
