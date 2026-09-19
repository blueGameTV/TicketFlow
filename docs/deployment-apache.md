# Déploiement Apache

## VirtualHost recommandé

Un exemple complet est disponible dans :

```text
deploy/apache/ticketflow.conf.example
```

Le point important est de publier uniquement :

```text
/var/www/ticketflow/public
```

et jamais la racine du projet.

## Modules utiles

```bash
sudo a2enmod rewrite headers
```

## Test de configuration

```bash
sudo apache2ctl configtest
```

## Journaux Apache

```bash
sudo tail -f /var/log/apache2/ticketflow-error.log
sudo tail -f /var/log/apache2/ticketflow-access.log
```

Les erreurs applicatives PHP sont également écrites dans :

```text
storage/logs/php-error.log
```
