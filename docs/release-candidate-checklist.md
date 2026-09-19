# TicketFlow v0.9.0-rc1 — Checklist Release Candidate

Cette checklist doit être exécutée sur une copie de pré-production avant la v1.0.

## 1. Préparation technique

- [ ] Sauvegarde MySQL effectuée.
- [ ] Sauvegarde de `config/config.php` effectuée.
- [ ] `php scripts/healthcheck.php` ne retourne aucune erreur bloquante.
- [ ] `php scripts/security_audit.php` ne retourne aucune erreur bloquante.
- [ ] `php scripts/release_check.php` ne retourne aucune erreur bloquante.
- [ ] `php scripts/route_check.php` ne retourne aucune erreur bloquante.
- [ ] `php scripts/rc_preflight.php` termine avec succès.
- [ ] Apache redémarre sans erreur.
- [ ] Le cron TicketFlow s'exécute sans exception PHP.

## 2. Administrateur

- [ ] Connexion / déconnexion.
- [ ] Création, modification et désactivation d'un utilisateur.
- [ ] Création, modification et suppression d'un groupe.
- [ ] Consultation de tous les tickets.
- [ ] Recherche globale et filtres avancés.
- [ ] Vues enregistrées.
- [ ] Actions multiples.
- [ ] Exports utilisateurs et tickets.
- [ ] Statistiques.
- [ ] Audit.
- [ ] Configuration des automatisations.

## 3. IT

- [ ] Consultation des tickets autorisés.
- [ ] Prise en charge d'un ticket.
- [ ] Changement de statut autorisé.
- [ ] Échange avec le demandeur.
- [ ] Ajout et téléchargement de pièce jointe.
- [ ] Demande de validation Manager.
- [ ] Proposition de résolution.
- [ ] Recherche / filtres / vues enregistrées.
- [ ] Actions multiples dans le périmètre autorisé.

## 4. Manager

- [ ] Création d'un ticket personnel.
- [ ] Consultation uniquement de ses tickets actifs.
- [ ] Réception d'une demande de validation.
- [ ] Acceptation et refus d'une validation.
- [ ] Impossibilité d'envoyer des messages dans l'étape de validation si l'interface le prévoit.
- [ ] Aucun accès aux pages Administrateur ou IT réservées.

## 5. Collaborateur

- [ ] Création d'un ticket.
- [ ] Consultation de ses tickets actifs uniquement.
- [ ] Envoi de message sur son ticket.
- [ ] Ajout de pièce jointe.
- [ ] Confirmation ou refus d'une résolution proposée.
- [ ] Aucun accès à un ticket d'un autre utilisateur par modification directe de l'URL.

## 6. Sécurité

- [ ] Compte désactivé : session invalidée.
- [ ] URL Administrateur refusée à un Collaborateur.
- [ ] URL IT refusée à un Collaborateur/Manager non autorisé.
- [ ] Téléchargement d'une pièce jointe non autorisée refusé.
- [ ] Jetons CSRF présents sur les formulaires sensibles.
- [ ] Fichier `config/config.php` non accessible depuis le Web.
- [ ] Dossiers `storage/logs` et `storage/uploads` non listables publiquement.
- [ ] Aucun mot de passe ou secret dans le dépôt Git.

## 7. UX / navigateurs

- [ ] Chrome/Chromium desktop.
- [ ] Firefox desktop.
- [ ] Largeur 1920 px.
- [ ] Largeur 1366 px.
- [ ] Vue mobile étroite : aucun élément critique inaccessible.
- [ ] Recherche dynamique sans rechargement complet.
- [ ] Actions multiples correctement alignées.

## 8. Validation finale

Une anomalie bloquante empêche le passage à la v1.0. Les anomalies mineures doivent être consignées dans GitHub Issues avant la publication stable.
