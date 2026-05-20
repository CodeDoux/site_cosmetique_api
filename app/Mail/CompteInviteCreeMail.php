<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompteInviteCreeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public string $motDePasseTemp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🛍️ Votre commande est confirmée — Accédez à votre compte',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.compte-invite-cree',
            with: [
                'user'          => $this->user,
                'motDePasseTemp'=> $this->motDePasseTemp,
                'loginUrl'      => config('app.frontend_url') . '/login',
            ]
        );
    }
}
