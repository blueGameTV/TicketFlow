# SLA TicketFlow — V11

La V11 ajoute des objectifs de prise en charge et de résolution selon l'importance du ticket.

| Importance | Prise en charge | Résolution |
|---|---:|---:|
| Faible | 8 h | 5 jours |
| Normale | 4 h | 2 jours |
| Haute | 1 h | 8 h |
| Critique | 15 min | 4 h |

Les délais sont actuellement calculés en temps calendaire continu. Les horaires ouvrés pourront être ajoutés dans une évolution ultérieure.

La prise en charge est considérée comme effectuée lors de la première attribution du ticket à un technicien IT. Le délai de résolution s'arrête lorsque le demandeur confirme la solution et que le ticket passe à `Résolu`.

## Alertes automatiques

Le script `scripts/check_sla.php` contrôle les dépassements et crée des notifications internes. Pour un contrôle toutes les 5 minutes :

```cron
*/5 * * * * /usr/bin/php /var/www/ticketflow/scripts/check_sla.php >/dev/null 2>&1
```

Les alertes sont envoyées au technicien assigné (ou à l'équipe IT si le ticket n'est pas attribué) ainsi qu'aux Administrateurs.
