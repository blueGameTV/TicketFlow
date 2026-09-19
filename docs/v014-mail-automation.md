# TicketFlow v0.14.0 — e-mails et automatisations

## Migration

```bash
mysql -u root -p ticketflow < database/migrations/v014_mail_automation.sql
```

## Configuration e-mail

Ajoutez une section `mail` à `config/config.php` en vous basant sur `config/config.example.php`.

Transports disponibles :

- `disabled` : aucun traitement e-mail ;
- `log` : simulation dans `storage/logs/mail.log` ;
- `smtp` : envoi via serveur SMTP.

Exemple Mailpit / MailHog local :

```php
'mail' => [
    'transport' => 'smtp',
    'host' => '127.0.0.1',
    'port' => 1025,
    'encryption' => 'none',
    'username' => '',
    'password' => '',
    'from_email' => 'ticketflow@entreprise.local',
    'from_name' => 'TicketFlow',
],
```

## Tâche cron

La v0.14 centralise les tâches automatiques dans :

```bash
php scripts/cron/run.php
```

Exécution conseillée toutes les 5 minutes :

```cron
*/5 * * * * /usr/bin/php /var/www/ticketflow/scripts/cron/run.php >> /var/www/ticketflow/storage/logs/cron.log 2>&1
```

Cette tâche contrôle :

- les dépassements SLA ;
- les rappels Manager ;
- les rappels de confirmation de résolution ;
- la fermeture automatique optionnelle ;
- le résumé quotidien ;
- le nettoyage des notifications lues ;
- la file d'e-mails.

## Sécurité

Les secrets SMTP ne sont pas stockés dans la base de données. Ils restent dans `config/config.php`, qui doit rester hors Git.
