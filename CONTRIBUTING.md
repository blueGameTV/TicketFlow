# Contribuer à TicketFlow

Merci de vouloir aider à améliorer **TicketFlow**.

Il n'est pas nécessaire d'être un développeur expérimenté pour contribuer.

Vous pouvez aider en :

- signalant un bug ;
- proposant une idée ;
- améliorant une explication ;
- corrigeant une faute ;
- améliorant l'interface ;
- proposant du code.

Ce guide explique simplement comment procéder.

# J'ai trouvé un bug

Avant de créer un nouveau signalement, vérifiez rapidement si le problème n'a pas déjà été signalé.

Dans votre message, essayez d'indiquer :

- la version de TicketFlow ;
- ce que vous étiez en train de faire ;
- ce que vous pensiez qu'il allait se passer ;
- ce qui s'est réellement passé ;
- le message d'erreur, s'il y en a un ;
- une capture d'écran si elle aide à comprendre.

Exemple :

```text
Version : TicketFlow v1.2.0
Rôle : Collaborateur
Page : Création d'un ticket

Problème :
Lorsque je clique sur « Envoyer », la page affiche une erreur.

Résultat attendu :
Le ticket devrait être créé.
```

Vous n'avez pas besoin de connaître PHP ou MariaDB pour signaler un bug.

# J'ai une idée

Vous pouvez proposer une amélioration en expliquant simplement :

1. le problème actuel ;
2. ce que vous aimeriez pouvoir faire ;
3. à qui cette fonction serait utile ;
4. éventuellement un exemple.

Une bonne proposition décrit surtout **le besoin**, pas uniquement la solution technique.

# Je veux modifier le code

La branche `main` contient la version stable actuelle.

Évitez de modifier directement `main`.

Commencez par créer une branche :

```bash
git checkout main
git pull
git checkout -b feature/nom-de-ma-modification
```

Exemples :

```text
feature/import-users
fix/ticket-display
ui/settings-page
docs/installation
security/session-check
```

# Faire une modification simple

Essayez de garder une contribution centrée sur un seul sujet.

Par exemple :

✅ une correction du calendrier

✅ une amélioration de la page Utilisateurs

✅ une correction de documentation

Évitez si possible :

❌ refaire le calendrier + le système d'e-mail + les statistiques dans la même Pull Request

Cela rend les changements plus faciles à vérifier.

# Quelques règles importantes pour le code

## Sécurité

TicketFlow contient des comptes utilisateurs, des tickets et parfois des pièces jointes internes.

Il faut donc toujours :

- vérifier les droits côté serveur ;
- protéger les formulaires sensibles contre les attaques CSRF ;
- valider les données envoyées par les utilisateurs ;
- utiliser des requêtes PDO préparées ;
- ne jamais enregistrer un mot de passe en clair ;
- ne jamais exposer les fichiers de configuration ;
- conserver les pièces jointes hors du dossier public.

Un bouton caché dans l'interface **n'est pas une protection de sécurité**. Le serveur doit toujours vérifier les permissions.

## PHP

Pour les nouveaux fichiers PHP :

```php
<?php

declare(strict_types=1);
```

TicketFlow doit rester compatible avec **PHP 8.1 ou plus récent**.

Pour les mots de passe, utilisez les fonctions prévues par PHP :

```php
password_hash()
password_verify()
```

## Base de données

Si une modification nécessite une nouvelle colonne ou une nouvelle table, ajoutez une migration dans :

```text
database/migrations/
```

Une migration doit autant que possible :

- conserver les données existantes ;
- fonctionner sur une installation déjà utilisée ;
- pouvoir être exécutée sans remettre TicketFlow à zéro ;
- ne jamais contenir de données réelles d'une entreprise.

## Interface

Essayez de conserver le style actuel de TicketFlow.

Pensez notamment à vérifier :

- thème clair ;
- thème sombre ;
- écran 1920 px ;
- écran 1366 px ;
- affichage mobile ;
- textes Français / English lorsqu'une page est traduite.

Lorsque TicketFlow utilise déjà AJAX ou `fetch()` sur une page, évitez de réintroduire un rechargement complet sans raison.

# Tester avant d'envoyer une modification

Pour vérifier rapidement la syntaxe PHP :

```bash
find public src templates scripts -name '*.php' -print0 | xargs -0 -n1 php -l
```

TicketFlow fournit aussi plusieurs outils :

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

Pour une grosse modification, testez également les rôles concernés :

- Administrateur ;
- Support IT ;
- Manager ;
- Collaborateur.

Exemple : si vous modifiez une validation Manager, testez au minimum le Support IT, le Manager N+1 et le Manager final.

# Envoyer une Pull Request

Une Pull Request est simplement une proposition de modification du projet.

Dans la description, indiquez :

- ce qui a été modifié ;
- pourquoi ;
- comment vous avez testé ;
- s'il y a une migration de base de données ;
- des captures d'écran si l'interface change.

Exemple :

```text
Correction de l'affichage des priorités dans ticket.php.

Testé avec :
- Administrateur
- Support IT
- thème clair
- thème sombre

Aucune migration SQL.
```

# Messages de commit

Utilisez un message court qui explique le changement.

Exemples :

```text
fix: repair ticket priority selector
ui: improve settings page
docs: simplify installation guide
security: harden session validation
feat: add user import
```

Préfixes utiles :

```text
feat:      nouvelle fonction
fix:       correction de bug
ui:        interface
security:  sécurité
docs:      documentation
refactor:  réorganisation du code
test:      tests
chore:     maintenance du projet
```

# Ne jamais envoyer sur GitHub

Ne publiez jamais :

- `config/config.php` réel ;
- un fichier `.env` avec des secrets ;
- un mot de passe ;
- un jeton ;
- une clé API ;
- un dump de base de données de production ;
- les fichiers réels de `storage/uploads/` ;
- des logs contenant des informations sensibles.

Si vous pensez avoir trouvé une faille de sécurité, **ne créez pas une Issue publique avec les détails permettant de l'exploiter**.

Consultez [SECURITY.md](SECURITY.md).

# Compatibilité des mises à jour

Une contribution ne doit pas obliger les utilisateurs à réinstaller TicketFlow depuis zéro.

Lorsqu'une modification touche la base de données ou la configuration, pensez aux utilisateurs qui possèdent déjà :

- v1.0.0 ;
- v1.1.0 ;
- une version stable récente.

L'objectif est de conserver autant que possible :

- leurs comptes ;
- leurs tickets ;
- leurs groupes ;
- leur configuration ;
- leurs pièces jointes.

# Besoin d'aide ?

Si vous débutez, ce n'est pas un problème.

Expliquez simplement :

- ce que vous voulez modifier ;
- où vous êtes bloqué ;
- ce que vous avez déjà essayé.

Une contribution claire, même petite, peut être utile au projet.
