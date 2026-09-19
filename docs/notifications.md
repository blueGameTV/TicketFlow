# V10 — Notifications internes

TicketFlow possède maintenant un centre de notifications interne pour chaque utilisateur.

## Événements couverts

- nouveau ticket : alerte les comptes IT actifs ;
- prise en charge : alerte le demandeur ;
- modification d'importance ou de statut : alerte le demandeur ;
- transfert : alerte le nouvel IT assigné ;
- message public : alerte le demandeur ou l'IT assigné selon l'auteur ;
- note interne : alerte l'IT assigné lorsqu'elle vient d'un autre intervenant ;
- validation Manager demandée : alerte le Manager concerné ;
- réponse du Manager : alerte l'IT demandeur / assigné ;
- résolution proposée : alerte le demandeur ;
- résolution confirmée ou refusée : alerte l'IT assigné.

## Non-lues

Le bandeau supérieur affiche un compteur. La page `notifications.php` permet de filtrer les notifications non lues et de les marquer comme lues individuellement ou toutes en une fois.

## Migration V9 -> V10

Une nouvelle table est requise :

```bash
mysql -u root -p < database/migrations/v10_notifications.sql
```

ou importer `database/migrations/v10_notifications.sql` dans phpMyAdmin.
