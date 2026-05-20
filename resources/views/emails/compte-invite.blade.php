<h2>Bienvenue sur Site Cosmétique</h2>

<p>Bonjour {{ $user->nomComplet }},</p>

<p>Votre compte a été créé avec succès.</p>

<p>Email : {{ $user->email }}</p>
<p>Mot de passe temporaire : <strong>{{ $motDePasseTemp }}</strong></p>

<p>Veuillez changer votre mot de passe après connexion.</p>
