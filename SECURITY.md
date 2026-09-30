# Sécurité de TicketFlow

Ce document explique simplement quelles versions utiliser, comment protéger une installation et quoi faire si vous découvrez un problème de sécurité.

# Quelle version utiliser ?

| Version | Recommandation |
| --- | --- |
| **1.2.x** | ✅ Version actuellement recommandée |
| 1.1.x | ⚠️ Fonctionne encore, mais mise à jour vers 1.2.x recommandée |
| 1.0.x | ⚠️ Ancienne version, mise à jour vers 1.2.x fortement recommandée |
| 0.x / versions de test | ❌ À ne pas utiliser en production |

Si votre installation utilise une ancienne version, vous pouvez normalement la mettre à jour avec :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/update.sh | sudo bash
```

La mise à jour crée une sauvegarde avant modification.

# J'ai trouvé une faille de sécurité

Si vous pensez avoir découvert une faille :

**ne publiez pas immédiatement les détails techniques dans une Issue GitHub publique.**

Pourquoi ?

Parce qu'une personne malveillante pourrait lire l'Issue et utiliser la faille avant qu'elle soit corrigée.

Utilisez de préférence le système privé de signalement de vulnérabilités de GitHub s'il est disponible pour le dépôt.

Dans votre signalement, indiquez si possible :

- la version de TicketFlow ;
- la page ou fonction concernée ;
- comment reproduire le problème ;
- ce qu'un attaquant pourrait éventuellement faire ;
- si un compte particulier est nécessaire ;
- les éventuels messages d'erreur.

N'envoyez pas de vraies données personnelles, mots de passe ou secrets.

# Informations à ne jamais publier

Certaines données doivent toujours rester privées.

Ne publiez jamais :

- `config/config.php` ;
- les fichiers `.env` contenant des secrets ;
- les mots de passe MariaDB/MySQL ;
- les mots de passe SMTP ;
- les clés API ;
- les jetons d'accès ;
- les cookies de session ;
- les sauvegardes de production ;
- les dumps SQL réels ;
- les pièces jointes réelles des utilisateurs ;
- les logs contenant des informations sensibles.

# Pourquoi config/config.php est sensible ?

Ce fichier peut contenir les informations permettant à TicketFlow de se connecter à la base de données ou à un serveur e-mail.

Il doit donc rester uniquement sur le serveur TicketFlow.

Permissions recommandées :

```bash
chown root:www-data /var/www/ticketflow/config/config.php
chmod 640 /var/www/ticketflow/config/config.php
```

Cela signifie simplement que le fichier n'est pas accessible librement à tous les utilisateurs du serveur.

# HTTPS est recommandé

Si TicketFlow est utilisé en production, utilisez **HTTPS**.

Avec HTTP simple, les communications ne bénéficient pas du chiffrement fourni par HTTPS.

Cela concerne notamment :

- les identifiants ;
- les cookies de session ;
- les messages ;
- certaines informations de ticket.

# Sauvegardes

Sauvegardez régulièrement :

- la base de données ;
- `config/config.php` ;
- `storage/uploads/`.

Une sauvegarde n'est utile que si elle peut réellement être restaurée.

Il est donc recommandé de tester occasionnellement une restauration sur une machine de test.

Le script `update.sh` crée également automatiquement une sauvegarde avant une mise à jour.

# Maintenir le serveur à jour

Le serveur qui héberge TicketFlow fait partie de la sécurité de TicketFlow.

Maintenez notamment à jour :

- Debian / Ubuntu ;
- Apache ;
- PHP ;
- MariaDB / MySQL.

Exemple :

```bash
apt update
apt upgrade
```

Avant une grosse mise à jour système sur un serveur de production, réalisez une sauvegarde.

# Compte Administrateur

Un compte Administrateur peut effectuer des actions importantes.

Quelques bonnes pratiques :

- utilisez un mot de passe unique ;
- ne partagez pas le même compte Administrateur entre plusieurs personnes ;
- désactivez les comptes qui ne sont plus utilisés ;
- ne donnez le rôle Administrateur qu'aux personnes qui en ont réellement besoin.

# Mots de passe

TicketFlow impose une politique minimale de mot de passe.

Les mots de passe sont stockés sous forme de hash grâce aux fonctions sécurisées de PHP et ne doivent jamais être enregistrés en clair.

Un Administrateur peut également forcer un utilisateur à changer son mot de passe lors de sa prochaine connexion.

# Protection intégrée à TicketFlow

TicketFlow utilise plusieurs mécanismes de sécurité, notamment :

- hachage des mots de passe ;
- protection CSRF sur les actions sensibles ;
- contrôle des rôles côté serveur ;
- contrôle de l'accès aux tickets ;
- pièces jointes stockées hors du dossier public ;
- sessions renforcées ;
- limitation des tentatives de connexion ;
- journal d'audit ;
- en-têtes HTTP de sécurité ;
- changement forcé du mot de passe ;
- assistant d'installation protégé par un jeton puis verrouillé.

Ces protections ne remplacent pas une bonne configuration du serveur.

# Pièces jointes

Les fichiers envoyés dans les tickets peuvent contenir des informations internes.

Le dossier `storage/uploads/` ne doit pas être exposé directement comme un dossier web public.

Les téléchargements doivent passer par TicketFlow afin que les permissions soient vérifiées.

# Vérifier une installation

Pour un Administrateur système :

```bash
cd /var/www/ticketflow

php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

Ces commandes permettent notamment de repérer certains problèmes de configuration.

# En cas de doute

Si vous ne savez pas si une information peut être publiée, considérez-la comme privée jusqu'à vérification.

En particulier, ne copiez jamais publiquement un fichier de configuration complet ou un extrait contenant un mot de passe, un jeton ou une clé.
