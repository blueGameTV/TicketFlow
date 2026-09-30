# Authentification et comptes

## Connexion

TicketFlow permet la connexion avec l'identifiant utilisateur ou l'adresse e-mail.

Les mots de passe sont enregistrés avec `password_hash()` et vérifiés avec `password_verify()`.

Après une connexion réussie :

- l'identifiant de session PHP est renouvelé ;
- l'utilisateur est redirigé vers TicketFlow selon ses droits ;
- un compte désactivé est refusé ;
- les tentatives de connexion sont journalisées.

## Premier Administrateur

Lors d'une nouvelle installation, le premier Administrateur est créé depuis l'assistant sécurisé affiché par `install.sh` :

```text
http://IP_DU_SERVEUR/setup.php?token=...
```

Après création du compte, l'assistant est verrouillé automatiquement et le jeton d'installation n'est plus utilisable.

Le script `scripts/create_admin.php` reste un outil d'administration exceptionnel ; il ne remplace pas le parcours d'installation normal.

## Politique de mot de passe

TicketFlow impose au minimum :

- 12 caractères ;
- une majuscule ;
- une minuscule ;
- un chiffre.

La page de changement de mot de passe affiche un indicateur de robustesse.

## Changement forcé

Un Administrateur peut forcer le renouvellement du mot de passe d'un utilisateur.

À la prochaine connexion, TicketFlow bloque l'utilisation normale de l'interface jusqu'à la définition du nouveau mot de passe. Dans ce scénario imposé, l'ancien mot de passe n'est pas redemandé.

## Sessions

TicketFlow utilise notamment :

- renouvellement de l'ID après authentification ;
- cookies de session `HttpOnly` ;
- `SameSite=Lax` ;
- expiration après inactivité ;
- invalidation des sessions d'un compte désactivé.

## Protection CSRF

Les formulaires sensibles et actions d'administration utilisent des jetons CSRF validés côté serveur.

## Rôles

Les rôles fonctionnels sont :

- Administrateur ;
- Support IT ;
- Manager ;
- Collaborateur.

La clé historique interne du rôle Support IT reste `IT` afin de préserver la compatibilité.
