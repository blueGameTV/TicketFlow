# Politique de sécurité de TicketFlow

TicketFlow **v1.1.0** est la version stable actuelle du projet.

La sécurité reste une responsabilité partagée entre l'application, le système Debian/Ubuntu, Apache, PHP, MariaDB/MySQL et l'administrateur du serveur.

## Versions prises en charge

| Version | Support sécurité |
| --- | --- |
| **1.1.x** | ✅ Supportée |\n| 1.0.x | ⚠️ Mise à jour vers 1.1.x recommandée |
| 0.x / Release Candidates | ❌ Non supportées pour la production |

Les correctifs de sécurité sont destinés en priorité à la dernière version stable publiée.

## Signaler une vulnérabilité

Ne publiez **pas** une vulnérabilité exploitable dans une Issue GitHub publique.

Utilisez de préférence :

1. **GitHub Private Vulnerability Reporting**, s'il est activé sur le dépôt ;
2. à défaut, contactez le mainteneur du dépôt par un canal privé avant toute publication publique.

Le signalement devrait contenir :

- la version concernée ;
- le composant ou la page affectée ;
- les étapes de reproduction ;
- l'impact observé ;
- les prérequis nécessaires à l'exploitation ;
- un correctif proposé, si disponible.

Évitez d'inclure des données personnelles ou de vrais secrets dans le rapport.

## Divulgation responsable

Merci de laisser un délai raisonnable pour :

- reproduire le problème ;
- évaluer son impact ;
- préparer un correctif ;
- publier une nouvelle version ;
- informer les utilisateurs concernés.

Une vulnérabilité corrigée pourra ensuite être documentée publiquement sans exposer inutilement des systèmes encore non mis à jour.

## Secrets et données sensibles

Les éléments suivants ne doivent jamais être commités sur GitHub :

- `config/config.php` ;
- fichiers `.env` ;
- mots de passe MariaDB/MySQL ;
- mots de passe ou jetons SMTP ;
- clés API ;
- jetons d'installation ;
- cookies ou identifiants de session ;
- dumps SQL contenant des données réelles ;
- `storage/uploads/` avec de vraies pièces jointes ;
- `storage/logs/` avec des données de production ;
- sauvegardes de production.

Le dépôt public doit uniquement contenir des exemples sans secret, par exemple :

```text
config/config.example.php
```

## Mesures de sécurité intégrées

TicketFlow inclut notamment :

- hachage des mots de passe avec `password_hash()` ;
- vérification avec `password_verify()` ;
- protection CSRF sur les actions sensibles ;
- contrôles de rôles côté serveur ;
- contrôles d'accès aux tickets ;
- protection des téléchargements de pièces jointes ;
- stockage des uploads hors du DocumentRoot ;
- sessions PHP durcies ;
- invalidation des sessions des comptes désactivés ;
- limitation des tentatives de connexion ;
- journal d'audit ;
- en-têtes HTTP de sécurité ;
- assistant d'installation protégé par jeton puis verrouillé après installation ;
- permissions restrictives sur `config/config.php`.

## Vérifications recommandées

Après installation ou mise à jour :

```bash
cd /var/www/ticketflow

php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

Avant une mise en production importante :

```bash
php scripts/rc_preflight.php
```

Aucune erreur bloquante ne doit rester sans analyse.

## Recommandations de production

Pour un serveur de production :

- utiliser **HTTPS** avec un certificat valide ;
- maintenir Debian/Ubuntu à jour ;
- maintenir Apache, PHP et MariaDB/MySQL à jour ;
- exposer TicketFlow uniquement aux réseaux nécessaires ;
- utiliser un pare-feu ;
- utiliser un compte SQL dédié à TicketFlow ;
- appliquer le principe du moindre privilège ;
- sauvegarder régulièrement la base, `config/config.php` et `storage/uploads/` ;
- protéger les sauvegardes ;
- surveiller les logs Apache, PHP, TicketFlow et MariaDB ;
- limiter l'accès SSH ;
- éviter d'exécuter l'application elle-même avec les droits root ;
- tester les restaurations de sauvegarde ;
- configurer correctement le serveur SMTP avant d'utiliser les e-mails réels.

## Permissions recommandées

Le fichier de configuration local doit rester protégé, par exemple :

```bash
chown root:www-data /var/www/ticketflow/config/config.php
chmod 640 /var/www/ticketflow/config/config.php
```

Le répertoire `storage/` doit être accessible au serveur Web sans être publiquement exposé.

## Installation

L'installateur officiel peut être lancé avec :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

Avant de l'utiliser sur un serveur sensible, il est recommandé de consulter le script `install.sh` présent dans le dépôt et de vérifier que la branche ou le tag utilisé correspond bien à la version souhaitée.

## Mises à jour de sécurité

Lorsqu'un correctif de sécurité est publié :

1. sauvegardez la base et la configuration ;
2. consultez les notes de version ;
3. appliquez la mise à jour rapidement ;
4. relancez les scripts de contrôle ;
5. vérifiez les journaux après la mise à jour.

## Hors périmètre

Ne sont pas considérés comme des vulnérabilités TicketFlow lorsqu'ils résultent uniquement :

- d'un serveur compromis au niveau root ;
- d'un système d'exploitation non maintenu ;
- d'une mauvaise configuration réseau indépendante de l'application ;
- d'identifiants administrateur volontairement partagés ;
- de modifications locales non présentes dans la version officielle.

Cela n'empêche pas de signaler un comportement inattendu si vous pensez que TicketFlow pourrait mieux se protéger dans ces situations.
