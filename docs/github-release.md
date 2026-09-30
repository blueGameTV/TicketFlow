# Préparer une release GitHub

Ce document est destiné aux mainteneurs de TicketFlow.

## 1. Contrôles avant publication

Depuis la racine du projet :

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

Vérifiez également `git status`.

Ne publiez jamais :

- `config/config.php` réel ;
- fichiers `.env` ;
- logs ;
- uploads utilisateurs ;
- dumps contenant des données réelles ;
- secrets SMTP ou mots de passe.

## 2. Version

Mettez à jour au minimum :

- `VERSION` ;
- `CHANGELOG.md` ;
- le README si nécessaire ;
- les notes de version sous `docs/`.

## 3. Tag

Exemple pour v1.2.0 :

```bash
git tag -a v1.2.0 -m "TicketFlow v1.2.0"
git push origin v1.2.0
```

## 4. Release GitHub

Recommandations :

- titre : `TicketFlow vX.Y.Z Stable` ;
- tag : `vX.Y.Z` ;
- cible : `main` ;
- ne pas marquer en pré-release pour une version stable ;
- joindre l'archive `TicketFlow-vX.Y.Z.zip` ;
- publier la somme SHA-256 ;
- rappeler les commandes `install.sh` et `update.sh` appropriées.

## 5. Notes de version

Conservez dans `docs/` uniquement les notes des versions stables encore utiles à la compréhension du projet. Les documents de développement intermédiaires n'ont pas besoin de rester dans la documentation principale.
