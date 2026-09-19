# Politique de sécurité

TicketFlow est encore en préversion. N'utilisez pas une préversion sur des données sensibles sans audit complémentaire.

## Signaler une vulnérabilité

Évitez de publier publiquement des mots de passe, jetons, clés SMTP, dumps MySQL ou détails exploitables dans une issue GitHub publique.

Pour un dépôt organisationnel, utilisez de préférence le canal privé de sécurité de l'organisation ou GitHub Private Vulnerability Reporting lorsqu'il est activé.

## Secrets

Les éléments suivants ne doivent jamais être commités :

- `config/config.php` ;
- `.env` ;
- dumps SQL contenant des données réelles ;
- fichiers `storage/uploads/` ;
- fichiers `storage/logs/` ;
- mots de passe SMTP ;
- identifiants MySQL.

## Production

Avant mise en production :

```bash
php scripts/security_audit.php
php scripts/release_check.php
```

Recommandations supplémentaires :

- HTTPS ;
- PHP à jour ;
- MySQL/MariaDB à jour ;
- permissions minimales ;
- sauvegardes régulières ;
- compte SQL dédié à TicketFlow ;
- surveillance des logs ;
- restriction réseau si TicketFlow est purement interne.
