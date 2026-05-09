<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nomComplet', 'email', 'password', 'tel', 'image', 'role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dateInscription'   => 'datetime',
        'password'          => 'hashed',
    ];

    // Relations
    public function adresses()    { return $this->hasMany(Adresse::class, 'user_id'); }
    public function commandes()   { return $this->hasMany(Commande::class, 'client_id'); }
    public function avis()        { return $this->hasMany(Avis::class, 'client_id'); }
    public function notifications(){ return $this->hasMany(Notification::class, 'destinataire_id'); }

    // Scopes rôles
    public function scopeAdmins($q)   { return $q->where('role', 'ADMIN'); }
    public function scopeClients($q)  { return $q->where('role', 'CLIENT'); }
    public function scopeLivreurs($q) { return $q->where('role', 'LIVREUR'); }

    public function isAdmin()   { return $this->role === 'ADMIN'; }
    public function isClient()  { return $this->role === 'CLIENT'; }
    public function isLivreur() { return $this->role === 'LIVREUR'; }
    public function livraisons() { return $this->hasMany(Livraison::class, 'livreur_id'); }
}
