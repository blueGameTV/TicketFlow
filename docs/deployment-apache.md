# Déploiement Apache

L'installation automatique via `install.sh` reste la méthode recommandée. Ce document décrit les points importants du déploiement Apache.

## DocumentRoot

Apache doit publier uniquement :

```text
/var/www/ticketflow/public
```

La racine du dépôt, `config/`, `storage/` et les scripts internes ne doivent pas être publiés directement.

Un exemple de VirtualHost est disponible dans :

```text
deploy/apache/ticketflow.conf.example
```

## Modules

```bash
sudo a2enmod rewrite headers
```

Puis :

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Site Debian par défaut

Sur une installation dédiée à TicketFlow, le site Apache Debian par défaut peut être désactivé afin d'éviter l'affichage de la page “Apache2 Debian Default Page”.

L'installateur officiel effectue cette configuration automatiquement.

## HTTPS

Pour un usage en production, placez TicketFlow derrière HTTPS avec un certificat valide. L'application ne doit pas transmettre des identifiants ou cookies de session sur un réseau non sécurisé.

## Journaux

Apache :

```bash
sudo tail -f /var/log/apache2/ticketflow-error.log
sudo tail -f /var/log/apache2/ticketflow-access.log
```

TicketFlow :

```text
storage/logs/
```

## Vérifications

```bash
sudo apache2ctl configtest
cd /var/www/ticketflow
php scripts/healthcheck.php
php scripts/route_check.php
```
