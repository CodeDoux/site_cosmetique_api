<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'destinataire_id', 'titre', 'message', 'estLu', 'type',
    ];

    protected $casts = [
        'dateEnvoi' => 'datetime',
        'estLu'     => 'boolean',
    ];

    public function destinataire() { return $this->belongsTo(User::class, 'destinataire_id'); }
}
