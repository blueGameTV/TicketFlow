# SLA TicketFlow

TicketFlow définit des objectifs de prise en charge et de résolution selon l'importance du ticket.

| Importance | Prise en charge | Résolution |
|---|---:|---:|
| Faible | 8 h | 5 jours |
| Normale | 4 h | 2 jours |
| Haute | 1 h | 8 h |
| Critique | 15 min | 4 h |

Les délais sont actuellement calculés en temps calendaire continu.

## Prise en charge

La prise en charge est considérée comme effectuée lors de la première attribution du ticket à un membre du Support IT.

## Résolution

Le délai de résolution s'arrête lorsque le workflow de résolution atteint l'état prévu après confirmation du demandeur.

## Alertes SLA

Les tâches automatiques vérifient régulièrement les dépassements et peuvent créer des notifications pour les personnes concernées.

Le cron principal est :

```bash
php scripts/cron/run.php
```

Il est normalement installé automatiquement et exécuté toutes les cinq minutes.

## Visibilité

Les informations SLA détaillées sont réservées aux Administrateurs dans l'interface TicketFlow.
