# Authentification TicketFlow

## Fonctionnement

- Mot de passe stocké avec `password_hash()` et vérifié avec `password_verify()`.
- Session PHP renouvelée après connexion (`session_regenerate_id`).
- Cookies de session `HttpOnly` et `SameSite=Lax`.
- Protection CSRF sur connexion/déconnexion.
- Un compte désactivé ne peut pas se connecter.
- Message d'erreur de connexion volontairement générique.
- Redirection du dashboard selon le rôle.

## Créer le premier administrateur

Depuis la racine du projet :

```bash
php scripts/create_admin.php
```

Le script demande prénom, nom, e-mail et mot de passe.
Le mot de passe doit faire au minimum 12 caractères.

## Rôles reconnus

- Administrateur
- IT
- Manager
- Collaborateur
