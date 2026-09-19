# V12 — Security, Audit & Live UI

## Sécurité
- verrouillage temporaire après 5 échecs de connexion sur 15 minutes ;
- expiration des sessions après 30 minutes d'inactivité ;
- table des sessions actives/révoquées ;
- journal des tentatives de connexion ;
- changement de mot de passe obligatoire pour les nouveaux comptes si activé ;
- politique minimale : 12 caractères, majuscule, minuscule et chiffre ;
- journal d'audit Administrateur.

## Interface dynamique
- compteur de notifications actualisé toutes les 3 secondes ;
- page ticket actualisée toutes les 2,5 secondes pour le statut, l'importance, l'IT assigné et les messages ;
- actions ticket envoyées avec `fetch()` sans rechargement complet de la page ;
- les formulaires PHP restent fonctionnels sans JavaScript comme solution de secours.

Cette V12 utilise un polling AJAX volontairement compatible avec Apache/PHP classique. Une évolution WebSocket/SSE reste possible plus tard.

## V12.1 - Synchronisation complète de la page ticket

La page ticket utilise maintenant une synchronisation automatique sans rechargement du navigateur.
Les changements de statut, priorité, IT assigné, messages, notes internes, validations Manager,
résolution proposée/confirmée/refusée, historique et pièces jointes sont détectés automatiquement.

Le navigateur interroge un état léger environ toutes les 1,2 secondes lorsque l'onglet est visible.
Lorsqu'un changement est détecté, seul le fragment HTML du ticket est rechargé via `fetch()`.
Les textes en cours de saisie dans les zones de commentaire sont préservés pendant la synchronisation.
Les formulaires, y compris les pièces jointes, sont envoyés en AJAX sans rechargement complet.
