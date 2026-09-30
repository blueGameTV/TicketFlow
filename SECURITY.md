# Politique de sécurité de TicketFlow

TicketFlow **v1.2.0** est la version stable courante du projet.

## Versions prises en charge

| Version | Support sécurité |
| --- | --- |
| **1.2.x** | ✅ Supportée |
| 1.1.x | ⚠️ Mise à jour vers 1.2.x recommandée |
| 1.0.x | ⚠️ Mise à jour vers 1.2.x fortement recommandée |
| 0.x / Release Candidates | ❌ Non supportées pour la production |

## Signaler une vulnérabilité

Ne publiez pas une vulnérabilité exploitable dans une Issue GitHub publique. Utilisez de préférence GitHub Private Vulnerability Reporting lorsqu'il est activé, ou contactez le mainteneur par un canal privé.

Un signalement utile indique la version, le composant concerné, les étapes de reproduction, l'impact observé et les prérequis nécessaires. Ne joignez pas de données personnelles ni de secrets réels.

## Secrets et données sensibles

Ne commitez jamais :

- `config/config.php` ;
- fichiers `.env` ;
- mots de passe MariaDB/MySQL ou SMTP ;
- clés API et jetons ;
- cookies ou sessions ;
- dumps de production ;
- vraies pièces jointes dans `storage/uploads/` ;
- logs ou sauvegardes de production.

## Mesures de sécurité intégrées

TicketFlow inclut notamment le hachage des mots de passe, CSRF, contrôles de rôles côté serveur, protection des pièces jointes, sessions durcies, verrouillage après tentatives de connexion, audit, en-têtes HTTP de sécurité, changement forcé du mot de passe et assistant d'installation à jeton unique.

## Vérifications recommandées

```bash
cd /var/www/ticketflow
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

## Production

Utilisez HTTPS, maintenez Debian/Ubuntu, Apache, PHP et MariaDB/MySQL à jour, appliquez le moindre privilège, utilisez un compte SQL dédié, protégez SSH, sauvegardez régulièrement la base/configuration/uploads et testez les restaurations.

Le fichier local de configuration devrait rester protégé, par exemple :

```bash
chown root:www-data /var/www/ticketflow/config/config.php
chmod 640 /var/www/ticketflow/config/config.php
```
